<?php
/**
 * Campus Eats - Authentication and Authorisation Module
 *
 * Handles user authentication, role-based access control, session
 * management, security header configuration, and rate limiting. The
 * module is MySQL-authoritative. It does not call Firebase. Google SSO
 * is handled by Solution/includes/oauth_google.php, which sets the
 * application session through the shared helper defined here.
 *
 * CORRECTIONS (Version 25.0 - Technical Audit):
 *
 * - Fix 1 (case-insensitive email and username lookup). The email and
 *   username lookups now use LOWER() on both sides of the comparison.
 *   The previous query compared the stored value directly to the
 *   submitted value. On a MySQL collation that is case-sensitive, a
 *   submitted email such as "Amara.Nkosi@campuseats.test" did not
 *   match the stored "amara.nkosi@campuseats.test". The correction
 *   makes the lookup case-insensitive on every collation. The change
 *   does not affect the password verification. The change does not
 *   affect the session creation. The change does not weaken any
 *   security control.
 *
 * - Fix 2 (unique ID normalisation). The unique ID lookup now strips
 *   hyphens and upper-cases the identifier before the format check.
 *   The format check accepts the 16-character canonical form. The
 *   normalisation supports both "ADMN4K7P2Q9XRT5M" and
 *   "ADMN-4K7P-2Q9X-RT5M". The normalisation does not accept any
 *   identifier that the format check rejects.
 *
 * - Retained all Version 24.0 behaviour: the 16-character User ID
 *   login branch, the canonical escapeOutput() helper, the CSP
 *   constants, the HttpOnly session cookie, the Secure flag when
 *   HTTPS is present, the CSRF tokens built from random_bytes and
 *   compared with hash_equals, and the failed-attempt rate limiter.
 *
 * - Retained the removal of every demo-account code path. No account
 *   is created by this file.
 *
 * SOURCE: Campus Eats PHP Web Platform - Technical Audit Report.
 * SOURCE: Notes - Make use of SSO. Users should also be able to use
 *         Google SSO. Include multi-language support for at least two
 *         South African languages: English and Afrikaans.
 *
 * @version 25.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/error_logging.php';

// =============================================================================
// Canonical Output Escaping Helper
// =============================================================================

if (!function_exists('escapeOutput'))
{
    /**
     * Escapes a value for safe HTML output.
     *
     * This is the single canonical escaping helper for the
     * application. Every other file that needs to escape output for
     * HTML should call this function rather than defining its own
     * copy.
     *
     * @param mixed $string The value to escape
     * @return string Escaped string safe for insertion into HTML
     */
    function escapeOutput($string)
    {
        if ($string === null)
        {
            return '';
        }

        return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
    }
}

// =============================================================================
// Session State
// =============================================================================

if (!isset($GLOBALS['_SESSION_INITIALIZED']))
{
    $GLOBALS['_SESSION_INITIALIZED'] = false;
}

if (!isset($GLOBALS['_SECURITY_HEADERS_SET']))
{
    $GLOBALS['_SECURITY_HEADERS_SET'] = false;
}

// =============================================================================
// Rate Limiting Constants
// =============================================================================

if (!defined('MAX_LOGIN_ATTEMPTS'))
{
    define('MAX_LOGIN_ATTEMPTS', 5);
}

if (!defined('LOGIN_ATTEMPT_WINDOW'))
{
    define('LOGIN_ATTEMPT_WINDOW', 900);
}

if (!defined('MAX_RESET_ATTEMPTS'))
{
    define('MAX_RESET_ATTEMPTS', 3);
}

if (!defined('RESET_ATTEMPT_WINDOW'))
{
    define('RESET_ATTEMPT_WINDOW', 3600);
}

if (!defined('ALLOWED_ROLES'))
{
    define('ALLOWED_ROLES', serialize(array('admin', 'vendor', 'student', 'standard')));
}

// =============================================================================
// Session Management
// =============================================================================

if (!function_exists('startSecureSession'))
{
    /**
     * Starts a secure session with HttpOnly and Secure cookie flags.
     *
     * The Secure flag is set only when the request is running over
     * HTTPS, so development over plain HTTP still works. The HttpOnly
     * flag is always set so client-side JavaScript cannot read the
     * session cookie.
     *
     * @return bool True on success
     */
    function startSecureSession()
    {
        if ($GLOBALS['_SESSION_INITIALIZED'] === true)
        {
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE)
        {
            $GLOBALS['_SESSION_INITIALIZED'] = true;
            return true;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        session_set_cookie_params(
            array(
                'lifetime' => 0,
                'path'     => '/',
                'domain'   => '',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            )
        );

        if (defined('SESSION_NAME'))
        {
            session_name(SESSION_NAME);
        }

        $started = session_start();
        $GLOBALS['_SESSION_INITIALIZED'] = $started;
        return $started;
    }
}

if (!function_exists('destroySession'))
{
    /**
     * Destroys the current session completely.
     *
     * Clears session data, invalidates the session cookie, and
     * regenerates the session identifier.
     *
     * @return void
     */
    function destroySession()
    {
        if (session_status() === PHP_SESSION_ACTIVE)
        {
            $_SESSION = array();

            if (ini_get('session.use_cookies'))
            {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            session_destroy();
        }

        $GLOBALS['_SESSION_INITIALIZED'] = false;
    }
}

// =============================================================================
// Security Headers
// =============================================================================

if (!function_exists('setSecurityHeaders'))
{
    /**
     * Sets the security headers required by the platform.
     *
     * The headers are applied once per request. Subsequent calls are
     * ignored.
     *
     * @return void
     */
    function setSecurityHeaders()
    {
        if ($GLOBALS['_SECURITY_HEADERS_SET'] === true)
        {
            return;
        }

        if (headers_sent())
        {
            return;
        }

        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-XSS-Protection: 1; mode=block');

        if (defined('CSP_HEADER') && CSP_HEADER !== '')
        {
            header('Content-Security-Policy: ' . CSP_HEADER);
        }

        $GLOBALS['_SECURITY_HEADERS_SET'] = true;
    }
}

// =============================================================================
// CSRF Token Helpers
// =============================================================================

if (!function_exists('getCsrfToken'))
{
    /**
     * Generates or returns the current CSRF token.
     *
     * The token is stored in the session and is compared with
     * hash_equals on every state-changing request. The token is
     * generated with random_bytes when it does not yet exist.
     *
     * @return string The CSRF token
     */
    function getCsrfToken()
    {
        startSecureSession();

        if (empty($_SESSION['csrf_token']))
        {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCsrfToken'))
{
    /**
     * Validates a submitted CSRF token against the session token.
     *
     * @param string $token The token received from the client
     * @return bool True when the token matches
     */
    function validateCsrfToken($token)
    {
        startSecureSession();

        if (empty($_SESSION['csrf_token']) || empty($token))
        {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// =============================================================================
// Rate Limiting Helpers
// =============================================================================

if (!function_exists('getFailedLoginAttemptCount'))
{
    /**
     * Returns the number of failed login attempts recorded for the
     * given IP address within the configured window.
     *
     * @param string $ipAddress The client IP address
     * @return int The number of failed attempts
     */
    function getFailedLoginAttemptCount($ipAddress)
    {
        $db = getDB();

        if (!$db->isAvailable())
        {
            return 0;
        }

        $row = $db->fetchOne(
            "SELECT COUNT(*) AS attempt_count
             FROM login_attempts
             WHERE ip_address = :ip
               AND attempted_at >= (NOW() - INTERVAL :window SECOND)",
            array(
                'ip'     => $ipAddress,
                'window' => LOGIN_ATTEMPT_WINDOW
            )
        );

        return $row ? (int)$row['attempt_count'] : 0;
    }
}

if (!function_exists('recordFailedLoginAttempt'))
{
    /**
     * Records a failed login attempt for rate limiting.
     *
     * @param string $ipAddress The client IP address
     * @param string $username  The identifier that was attempted
     * @return void
     */
    function recordFailedLoginAttempt($ipAddress, $username)
    {
        $db = getDB();

        if (!$db->isAvailable())
        {
            return;
        }

        $db->executeQuery(
            "INSERT INTO login_attempts (ip_address, username, attempted_at)
             VALUES (:ip, :username, NOW())",
            array(
                'ip'       => $ipAddress,
                'username' => substr($username, 0, 100)
            )
        );
    }
}

if (!function_exists('clearFailedLoginAttempts'))
{
    /**
     * Clears failed login attempts for the given IP after a successful
     * authentication.
     *
     * @param string $ipAddress The client IP address
     * @return void
     */
    function clearFailedLoginAttempts($ipAddress)
    {
        $db = getDB();

        if (!$db->isAvailable())
        {
            return;
        }

        $db->executeQuery(
            "DELETE FROM login_attempts WHERE ip_address = :ip",
            array('ip' => $ipAddress)
        );
    }
}

// =============================================================================
// Authentication Lookup (Version 25.0 Normalisation)
// =============================================================================

if (!function_exists('authenticateUser'))
{
    /**
     * Authenticates a user by identifier and password.
     *
     * The identifier may be an email address, a username, a 16-character
     * unique ID, or a hyphenated unique ID. Hyphens are stripped and the
     * value is upper-cased before the unique-ID path is evaluated. Email
     * and username comparisons are performed with LOWER() so that the
     * lookup is case-insensitive on every MySQL collation.
     *
     * Password verification uses password_verify() against the stored
     * bcrypt hash. Plain-text passwords are never logged or stored.
     *
     * On success the session is populated and regenerated. On failure a
     * uniform message is returned so that user enumeration is not possible.
     *
     * @param string $identifier Email, username, or unique ID
     * @param string $password   The plain-text password
     * @return array{success:bool,message:string,user?:array}
     */
    function authenticateUser($identifier, $password)
    {
        $db = getDB();

        if (!$db->isAvailable())
        {
            writeLog('Authentication aborted: database unavailable', 'AUTH');
            return array(
                'success' => false,
                'message' => 'Authentication service temporarily unavailable.'
            );
        }

        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

        if (getFailedLoginAttemptCount($ipAddress) >= MAX_LOGIN_ATTEMPTS)
        {
            writeLog("Rate limit exceeded for IP $ipAddress", 'AUTH');
            return array(
                'success' => false,
                'message' => 'Too many failed attempts. Please try again later.'
            );
        }

        $normalizedIdentifier = trim($identifier);
        $lookupValue = $normalizedIdentifier;
        $field = null;

        // Branch 1: email address (case-insensitive)
        if (filter_var($normalizedIdentifier, FILTER_VALIDATE_EMAIL))
        {
            $field = 'email';
            $lookupValue = strtolower($normalizedIdentifier);
        }
        else
        {
            // Branch 2: unique ID (strip hyphens, upper-case, length check)
            $candidateId = strtoupper(str_replace('-', '', $normalizedIdentifier));

            if (preg_match('/^[A-Z0-9]{16}$/', $candidateId))
            {
                $field = 'unique_id';
                $lookupValue = $candidateId;
            }
            else
            {
                // Branch 3: username (case-insensitive)
                $field = 'username';
                $lookupValue = strtolower($normalizedIdentifier);
            }
        }

        // Build the query according to the resolved field.
        // Email and username use LOWER() for collation safety.
        // Unique ID uses the normalised 16-character value.
        if ($field === 'email')
        {
            $user = $db->fetchOne(
                "SELECT u.user_id,
                        u.unique_id,
                        u.full_name,
                        u.username,
                        u.email,
                        u.password_hash,
                        u.account_type,
                        u.is_verified,
                        u.is_active
                 FROM users u
                 WHERE LOWER(u.email) = :identifier
                 LIMIT 1",
                array('identifier' => $lookupValue)
            );
        }
        elseif ($field === 'unique_id')
        {
            $user = $db->fetchOne(
                "SELECT u.user_id,
                        u.unique_id,
                        u.full_name,
                        u.username,
                        u.email,
                        u.password_hash,
                        u.account_type,
                        u.is_verified,
                        u.is_active
                 FROM users u
                 WHERE u.unique_id = :identifier
                 LIMIT 1",
                array('identifier' => $lookupValue)
            );
        }
        else
        {
            $user = $db->fetchOne(
                "SELECT u.user_id,
                        u.unique_id,
                        u.full_name,
                        u.username,
                        u.email,
                        u.password_hash,
                        u.account_type,
                        u.is_verified,
                        u.is_active
                 FROM users u
                 WHERE LOWER(u.username) = :identifier
                 LIMIT 1",
                array('identifier' => $lookupValue)
            );
        }

        if (!$user)
        {
            recordFailedLoginAttempt($ipAddress, $normalizedIdentifier);
            writeLog("Authentication failed: user not found for identifier type $field", 'AUTH');
            return array(
                'success' => false,
                'message' => 'Invalid credentials.'
            );
        }

        if (!(int)$user['is_active'])
        {
            recordFailedLoginAttempt($ipAddress, $normalizedIdentifier);
            writeLog("Authentication failed: inactive account user_id " . $user['user_id'], 'AUTH');
            return array(
                'success' => false,
                'message' => 'Invalid credentials.'
            );
        }

        if (!password_verify($password, $user['password_hash']))
        {
            recordFailedLoginAttempt($ipAddress, $normalizedIdentifier);
            writeLog("Authentication failed: password mismatch for user_id " . $user['user_id'], 'AUTH');
            return array(
                'success' => false,
                'message' => 'Invalid credentials.'
            );
        }

        // Successful authentication path.
        clearFailedLoginAttempts($ipAddress);
        createAuthenticatedSession($user);

        writeLog(
            "Authentication succeeded for user_id " . $user['user_id']
            . " (unique_id " . $user['unique_id'] . ")",
            'AUTH'
        );

        return array(
            'success' => true,
            'message' => 'Authentication successful.',
            'user'    => $user
        );
    }
}

if (!function_exists('createAuthenticatedSession'))
{
    /**
     * Creates the authenticated session for a verified user.
     *
     * Regenerates the session identifier, stores the required claims,
     * and records the session row for later management.
     *
     * @param array $user The user row returned by the lookup
     * @return void
     */
    function createAuthenticatedSession(array $user)
    {
        startSecureSession();
        session_regenerate_id(true);

        $_SESSION['user_id']          = (int)$user['user_id'];
        $_SESSION['unique_id']        = $user['unique_id'];
        $_SESSION['full_name']        = $user['full_name'];
        $_SESSION['username']         = $user['username'];
        $_SESSION['email']            = $user['email'];
        $_SESSION['account_type']     = $user['account_type'];
        $_SESSION['is_verified']      = (int)$user['is_verified'];
        $_SESSION['authenticated_at'] = time();

        $db = getDB();
        if ($db->isAvailable())
        {
            $db->executeQuery(
                "INSERT INTO user_sessions
                    (session_id, user_id, ip_address, user_agent, created_at, last_activity)
                 VALUES
                    (:sid, :uid, :ip, :ua, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE
                    last_activity = NOW()",
                array(
                    'sid' => session_id(),
                    'uid' => (int)$user['user_id'],
                    'ip'  => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0',
                    'ua'  => isset($_SERVER['HTTP_USER_AGENT'])
                             ? substr($_SERVER['HTTP_USER_AGENT'], 0, 512)
                             : null
                )
            );
        }
    }
}

// =============================================================================
// Session Query Helpers
// =============================================================================

if (!function_exists('isLoggedIn'))
{
    /**
     * Returns true when a valid authenticated session exists.
     *
     * @return bool
     */
    function isLoggedIn()
    {
        startSecureSession();
        return !empty($_SESSION['user_id']) && !empty($_SESSION['account_type']);
    }
}

if (!function_exists('getCurrentUserId'))
{
    /**
     * Returns the currently authenticated user identifier, or null.
     *
     * @return int|null
     */
    function getCurrentUserId()
    {
        startSecureSession();
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }
}

if (!function_exists('getCurrentUniqueId'))
{
    /**
     * Returns the currently authenticated unique ID, or null.
     *
     * @return string|null
     */
    function getCurrentUniqueId()
    {
        startSecureSession();
        return isset($_SESSION['unique_id']) ? $_SESSION['unique_id'] : null;
    }
}

if (!function_exists('getCurrentUserName'))
{
    /**
     * Returns the currently authenticated display name, or null.
     *
     * @return string|null
     */
    function getCurrentUserName()
    {
        startSecureSession();
        return isset($_SESSION['full_name']) ? $_SESSION['full_name'] : null;
    }
}

if (!function_exists('getCurrentUserRole'))
{
    /**
     * Returns the currently authenticated account type, or null.
     *
     * @return string|null
     */
    function getCurrentUserRole()
    {
        startSecureSession();
        return isset($_SESSION['account_type']) ? $_SESSION['account_type'] : null;
    }
}

if (!function_exists('isAdmin'))
{
    /**
     * Returns true when the current user has the admin role.
     *
     * @return bool
     */
    function isAdmin()
    {
        return getCurrentUserRole() === 'admin';
    }
}

if (!function_exists('isVendor'))
{
    /**
     * Returns true when the current user has the vendor role.
     *
     * @return bool
     */
    function isVendor()
    {
        return getCurrentUserRole() === 'vendor';
    }
}

if (!function_exists('isStudent'))
{
    /**
     * Returns true when the current user has the student or standard role.
     *
     * @return bool
     */
    function isStudent()
    {
        $role = getCurrentUserRole();
        return $role === 'student' || $role === 'standard';
    }
}

// =============================================================================
// Role Guards
// =============================================================================

if (!function_exists('requireLogin'))
{
    /**
     * Requires that a valid session exists. Redirects to login when
     * the requirement is not met.
     *
     * @return void
     */
    function requireLogin()
    {
        if (!isLoggedIn())
        {
            header('Location: ' . BASE_URL . '/modules/auth/login.php');
            exit();
        }
    }
}

if (!function_exists('requireVendor'))
{
    /**
     * Requires the vendor role. Redirects according to the current
     * role when the requirement is not met.
     *
     * @return void
     */
    function requireVendor()
    {
        if (!isLoggedIn())
        {
            header('Location: ' . BASE_URL . '/modules/auth/login.php');
            exit();
        }

        if (!isVendor())
        {
            if (isAdmin())
            {
                header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
            }
            elseif (isStudent())
            {
                header('Location: ' . BASE_URL . '/modules/student/dashboard.php');
            }
            else
            {
                header('Location: ' . BASE_URL . '/modules/auth/login.php');
            }
            exit();
        }
    }
}

if (!function_exists('requireVendorVerified'))
{
    /**
     * Requires that the current vendor account is approved.
     *
     * @return void
     */
    function requireVendorVerified()
    {
        requireVendor();

        $db = getDB();
        $userId = getCurrentUserId();

        $vendor = $db->fetchOne(
            "SELECT is_approved FROM vendors
             WHERE vendor_user_id = :user_id
             LIMIT 1",
            array('user_id' => $userId)
        );

        if (!$vendor || (int)$vendor['is_approved'] !== 1)
        {
            writeLog(
                "Unapproved vendor attempted vendor area: User ID $userId",
                "AUTH"
            );
            header('Location: ' . BASE_URL . '/modules/auth/logout.php');
            exit();
        }
    }
}

if (!function_exists('requireAdmin'))
{
    /**
     * Requires the admin role. Redirects according to the current
     * role when the requirement is not met.
     *
     * @return void
     */
    function requireAdmin()
    {
        if (!isLoggedIn())
        {
            header('Location: ' . BASE_URL . '/modules/auth/login.php');
            exit();
        }

        if (!isAdmin())
        {
            if (isVendor())
            {
                header('Location: ' . BASE_URL . '/modules/vendor/dashboard.php');
            }
            elseif (isStudent())
            {
                header('Location: ' . BASE_URL . '/modules/student/dashboard.php');
            }
            else
            {
                header('Location: ' . BASE_URL . '/modules/auth/login.php');
            }
            exit();
        }
    }
}

if (!function_exists('logout'))
{
    /**
     * Logs the current user out and redirects to the landing page.
     *
     * @return void
     */
    function logout()
    {
        $userId = getCurrentUserId();
        $username = getCurrentUserName();

        writeLog(
            "Logout initiated for user: $username (ID: $userId)",
            "AUTH"
        );
        destroySession();
        writeLog("User logged out successfully: $username", "AUTH");

        $redirectUrl = ROOT_URL . '/index.php?logout=' . time();
        header('HTTP/1.1 303 See Other');
        header('Location: ' . $redirectUrl);
        exit();
    }
}