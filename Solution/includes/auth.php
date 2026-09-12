<?php
/**
 * Campus Eats - Authentication and Authorization Module
 *
 * Handles user authentication, role-based access control, session
 * management, security header configuration, and rate limiting.
 *
 * CORRECTIONS (Version 22.0):
 * - authenticateUser() now accepts a 16-character User ID as a login
 *   credential, in addition to email and username. The login page
 *   (modules/auth/login.php) already advertises "16-character User ID
 *   or you@campus.edu" in its placeholder text and labels the field
 *   "User ID or Email". The previous implementation of authenticateUser()
 *   only branched between the `email` and `username` columns and never
 *   queried the `unique_id` column, so any user who followed the on-screen
 *   instruction and typed their User ID was rejected with "Invalid
 *   email/username or password". This version adds a third branch.
 * - The 16-character check uses the shared validateUserIdFormat() helper
 *   from includes/user_id.php, so the same rule is applied here as in
 *   forgot_password.php and register.php.
 * - Display hyphens are stripped before the lookup, so a user may type
 *   either "XXXX-XXXX-XXXX-XXXX" or "XXXXXXXXXXXXXXXX" and both forms
 *   match the continuous value stored in the users.unique_id column.
 * - Retains all previous corrections: CSP_POLICY constant, CSP_NONCE
 *   for inline blocks, escapeOutput() as the single canonical escaping
 *   helper, HttpOnly session cookies, Secure flag when HTTPS is present,
 *   and CSRF tokens built from random_bytes and compared with hash_equals.
 *
 * SOURCE: campus-eats-process-document.pdf Section 11.1
 * SOURCE: Solution/includes/user_id.php validateUserIdFormat()
 * SOURCE: Review item 2 - Login cannot actually use the 16-character User ID
 *
 * @version 22.0
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
     * Escapes a string for safe HTML output.
     *
     * This is the single canonical escaping helper for the application.
     * Every other file that needs to escape output for HTML should call
     * this function rather than defining its own copy.
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
     * The Secure flag is set only when the request is running over HTTPS,
     * so development over plain HTTP still works. The HttpOnly flag is
     * always set so client-side JavaScript cannot read the session cookie.
     *
     * @return bool True on success
     */
    function startSecureSession()
    {
        if ($GLOBALS['_SESSION_INITIALIZED'] === true)
        {
            return true;
        }

        if (ob_get_level() === 0)
        {
            ob_start();
        }

        if (session_status() === PHP_SESSION_ACTIVE)
        {
            $GLOBALS['_SESSION_INITIALIZED'] = true;
            return true;
        }

        $cookieParams = session_get_cookie_params();
        $secureFlag = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params(
            $cookieParams['lifetime'],
            '/',
            isset($cookieParams['domain']) ? $cookieParams['domain'] : '',
            $secureFlag,
            true
        );

        session_name('CAMPUS_EATS_SESSION');

        if (!session_start())
        {
            writeLog("Failed to start session", "AUTH");
            return false;
        }

        if (!isset($_SESSION['initialized']))
        {
            session_regenerate_id(true);
            $_SESSION['initialized'] = true;
            $_SESSION['created_at'] = time();
            writeLog("Session initialized and ID regenerated", "AUTH");
        }

        if (!isset($_SESSION['last_regeneration']))
        {
            $_SESSION['last_regeneration'] = time();
        }
        elseif (time() - $_SESSION['last_regeneration'] > SESSION_REGEN_INTERVAL)
        {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
            writeLog("Session ID regenerated (periodic)", "AUTH");
        }

        $GLOBALS['_SESSION_INITIALIZED'] = true;
        writeLog("Secure session started successfully", "AUTH");
        return true;
    }
}

if (!function_exists('regenerateSession'))
{
    /**
     * Regenerates the session ID and issues a fresh CSRF token.
     *
     * Called on every successful login and on every password reset that
     * affects the current session. Regenerating the ID on login prevents
     * session fixation: an attacker who somehow learned the pre-login
     * session ID cannot reuse it after the user authenticates.
     *
     * @return bool True on success
     */
    function regenerateSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE)
        {
            return false;
        }

        session_regenerate_id(true);
        generateCsrfToken(true);
        $_SESSION['last_regeneration'] = time();
        writeLog("Session regenerated successfully", "AUTH");
        return true;
    }
}

if (!function_exists('destroySession'))
{
    /**
     * Destroys the current session and removes the session cookie.
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
                $secureFlag = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    isset($params['path']) ? $params['path'] : '/',
                    isset($params['domain']) ? $params['domain'] : '',
                    $secureFlag,
                    isset($params['httponly']) ? $params['httponly'] : true
                );
            }

            session_destroy();
            $GLOBALS['_SESSION_INITIALIZED'] = false;
            writeLog("Session destroyed successfully", "AUTH");
        }
    }
}

// =============================================================================
// Security Headers
// =============================================================================

if (!function_exists('setSecurityHeaders'))
{
    /**
     * Sets security headers including the canonical CSP.
     *
     * The CSP is loaded from the CSP_POLICY constant so there is only
     * one definition of the policy for the whole application. The nonce
     * CSP_NONCE is used by any inline <style> or <script> block that is
     * rendered by the application.
     *
     * @return bool True on success
     */
    function setSecurityHeaders()
    {
        if ($GLOBALS['_SECURITY_HEADERS_SET'] === true)
        {
            return true;
        }

        if (!headers_sent())
        {
            header('X-Frame-Options: DENY');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header('Content-Security-Policy: ' . CSP_POLICY);

            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }

            header('X-XSS-Protection: 1; mode=block');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');

            $GLOBALS['_SECURITY_HEADERS_SET'] = true;
            writeLog("Security headers set (single canonical CSP with nonce)", "SECURITY");
        }

        return true;
    }
}

// =============================================================================
// CSRF Protection
// =============================================================================

if (!function_exists('generateCsrfToken'))
{
    /**
     * Generates a CSRF token and stores it in the session.
     *
     * @param bool $forceRegeneration When true, always issues a new token
     * @return string The current token
     */
    function generateCsrfToken($forceRegeneration = false)
    {
        if (!$forceRegeneration && isset($_SESSION['csrf_token']))
        {
            return $_SESSION['csrf_token'];
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;

        if (!isset($_SESSION['csrf_token_version']))
        {
            $_SESSION['csrf_token_version'] = 0;
        }
        $_SESSION['csrf_token_version']++;

        writeLog("New CSRF token generated (version: " . $_SESSION['csrf_token_version'] . ")", "SECURITY");
        return $token;
    }
}

if (!function_exists('validateCsrfToken'))
{
    /**
     * Validates a submitted CSRF token against the session token.
     *
     * Uses hash_equals for a constant-time comparison, so the comparison
     * time does not leak how many leading characters matched.
     *
     * @param string $token The token to validate
     * @param bool $regenerateOnSuccess When true, issues a fresh token on success
     * @return bool True if valid
     */
    function validateCsrfToken($token, $regenerateOnSuccess = false)
    {
        if (!isset($_SESSION['csrf_token']))
        {
            writeLog("CSRF validation failed: No token in session", "SECURITY");
            return false;
        }

        $storedToken = $_SESSION['csrf_token'];
        $isValid = hash_equals($storedToken, (string)$token);

        if (!$isValid)
        {
            writeLog("CSRF validation failed for token", "SECURITY");
        }
        else
        {
            if ($regenerateOnSuccess)
            {
                generateCsrfToken(true);
            }
            writeLog("CSRF token validated successfully", "SECURITY");
        }

        return $isValid;
    }
}

if (!function_exists('getCsrfToken'))
{
    /**
     * Returns the current CSRF token, generating one if necessary.
     *
     * @return string The current token
     */
    function getCsrfToken()
    {
        return generateCsrfToken();
    }
}

if (!function_exists('csrfTokenHtml'))
{
    /**
     * Returns the hidden input and meta tag for the CSRF token.
     *
     * @return string HTML fragment with the token
     */
    function csrfTokenHtml()
    {
        $token = generateCsrfToken();
        $escapedToken = escapeOutput($token);

        return '<input type="hidden" name="csrf_token" value="' . $escapedToken . '">' . "\n"
             . '<meta name="csrf-token" content="' . $escapedToken . '">';
    }
}

// =============================================================================
// Client IP Address
// =============================================================================

if (!function_exists('getClientIpAddress'))
{
    /**
     * Returns the client IP address, honouring common proxy headers.
     *
     * @return string The client IP address
     */
    function getClientIpAddress()
    {
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP']))
        {
            return $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (isset($_SERVER['HTTP_X_REAL_IP']))
        {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}

// =============================================================================
// Rate Limiting
// =============================================================================

if (!function_exists('getFailedLoginAttemptCount'))
{
    /**
     * Returns the number of recent failed login attempts for the given
     * IP address and identifier.
     *
     * @param string $ipAddress The client IP address
     * @param string $username The identifier used in the login attempt
     * @return int The number of attempts within the window
     */
    function getFailedLoginAttemptCount($ipAddress, $username)
    {
        $db = getDB();

        $result = $db->fetchOne(
            "SELECT COUNT(*) as attempt_count
             FROM login_attempts
             WHERE ip_address = :ip_address
               AND username = :username
               AND attempted_at > DATE_SUB(NOW(), INTERVAL :window SECOND)",
            array(
                'ip_address' => $ipAddress,
                'username' => $username,
                'window' => LOGIN_ATTEMPT_WINDOW
            )
        );

        return (int)($result['attempt_count'] ?? 0);
    }
}

if (!function_exists('recordFailedLoginAttempt'))
{
    /**
     * Records a failed login attempt.
     *
     * @param string $ipAddress The client IP address
     * @param string $username The identifier used in the login attempt
     * @return void
     */
    function recordFailedLoginAttempt($ipAddress, $username)
    {
        $db = getDB();

        $db->insert(
            "INSERT INTO login_attempts (ip_address, username, attempted_at)
             VALUES (:ip_address, :username, NOW())",
            array(
                'ip_address' => $ipAddress,
                'username' => $username
            )
        );

        writeLog("Recorded failed login attempt for username: $username from IP: $ipAddress", "AUTH");
    }
}

if (!function_exists('clearFailedLoginAttempts'))
{
    /**
     * Clears failed login attempts for the given IP address and identifier.
     *
     * Called after a successful authentication so the user starts with a
     * clean rate-limit budget.
     *
     * @param string $ipAddress The client IP address
     * @param string $username The identifier used in the login attempt
     * @return void
     */
    function clearFailedLoginAttempts($ipAddress, $username)
    {
        $db = getDB();

        $db->executeQuery(
            "DELETE FROM login_attempts
             WHERE ip_address = :ip_address AND username = :username",
            array(
                'ip_address' => $ipAddress,
                'username' => $username
            )
        );

        writeLog("Cleared failed login attempts for username: $username from IP: $ipAddress", "AUTH");
    }
}

// =============================================================================
// Authentication
// =============================================================================

if (!function_exists('authenticateUser'))
{
    /**
     * Authenticates a user by email, username, or 16-character User ID.
     *
     * CORRECTION:
     * The login page (modules/auth/login.php) advertises a 16-character
     * User ID as an accepted credential. The previous implementation of
     * this function only branched between the `email` and `username`
     * columns, so any user who typed their User ID was rejected. This
     * version adds a third branch that queries the `unique_id` column.
     *
     * Identifier classification order:
     *   1. Email, if it passes FILTER_VALIDATE_EMAIL. An email is never
     *      a valid 16-character ID, so this check is unambiguous.
     *   2. 16-character User ID, if the hyphen-stripped value passes
     *      validateUserIdFormat(). This accepts both the raw form
     *      "XXXXXXXXXXXXXXXX" and the display form "XXXX-XXXX-XXXX-XXXX".
     *   3. Username, as the fallback for any other string.
     *
     * The same validation used by forgot_password.php and register.php
     * is applied here, so the ID format is handled consistently across
     * all three auth flows.
     *
     * The failed-attempt rate limit is keyed on the raw identifier the
     * user typed, so a user who mistypes their User ID twice and then
     * their email twice is not penalised as though they had made four
     * attempts against one credential.
     *
     * @param string $identifier Email, username, or 16-character User ID
     * @param string $password The plain-text password to verify
     * @param string $csrfToken Optional CSRF token from the login form
     * @return array Result array with 'success', 'message', and 'user'
     */
    function authenticateUser($identifier, $password, $csrfToken = '')
    {
        if (!empty($csrfToken) && !validateCsrfToken($csrfToken, false))
        {
            writeLog("CSRF validation failed during authentication", "AUTH");
            return array(
                'success' => false,
                'message' => 'Security validation failed. Please refresh the page and try again.'
            );
        }

        $db = getDB();
        $ipAddress = getClientIpAddress();

        $attemptCount = getFailedLoginAttemptCount($ipAddress, $identifier);

        if ($attemptCount >= MAX_LOGIN_ATTEMPTS)
        {
            writeLog("Authentication blocked: Too many attempts for $identifier", "AUTH");
            return array(
                'success' => false,
                'message' => 'Too many failed login attempts. Please wait '
                    . (LOGIN_ATTEMPT_WINDOW / 60) . ' minutes before trying again.'
            );
        }

        // =====================================================================
        // CORRECTION: Classify the identifier into one of three columns.
        // =====================================================================
        // The previous implementation used only two branches (email or
        // username). This version adds the unique_id branch so the login
        // page's advertised "16-character User ID" credential is accepted.
        //
        // Hyphens are stripped from the candidate User ID before the format
        // check, so a user may type either the raw or the display form.
        // Both normalise to the continuous value stored in unique_id.
        // =====================================================================

        $normalizedIdentifier = trim($identifier);
        $field = 'username';
        $lookupValue = $normalizedIdentifier;

        if (filter_var($normalizedIdentifier, FILTER_VALIDATE_EMAIL))
        {
            $field = 'email';
        }
        else
        {
            // Strip the display hyphens before checking the raw ID format.
            $candidateUserId = str_replace('-', '', $normalizedIdentifier);

            // The helper is defined in includes/user_id.php. Guard the call
            // so this module remains usable if that file is not loaded.
            if (function_exists('validateUserIdFormat') && validateUserIdFormat($candidateUserId))
            {
                $field = 'unique_id';
                $lookupValue = $candidateUserId;
            }
        }

        $sql = "SELECT
                    u.user_id,
                    u.unique_id,
                    u.full_name,
                    u.username,
                    u.email,
                    u.password_hash,
                    u.account_type,
                    u.is_active,
                    u.is_verified,
                    v.vendor_id,
                    v.vendor_name,
                    v.is_approved
                FROM users u
                LEFT JOIN vendors v ON u.user_id = v.vendor_user_id
                WHERE u.$field = :identifier
                LIMIT 1";

        $user = $db->fetchOne($sql, array('identifier' => $lookupValue));

        if (!$user)
        {
            recordFailedLoginAttempt($ipAddress, $identifier);
            writeLog("Authentication failed: User not found - $identifier (field: $field)", "AUTH");
            return array(
                'success' => false,
                'message' => 'Invalid email/username or password.'
            );
        }

        if (!password_verify($password, $user['password_hash']))
        {
            recordFailedLoginAttempt($ipAddress, $identifier);
            writeLog("Authentication failed: Incorrect password for user: {$user['username']}", "AUTH");
            return array(
                'success' => false,
                'message' => 'Invalid email/username or password.'
            );
        }

        if ($user['is_active'] !== 1)
        {
            writeLog("Authentication blocked: Inactive account - {$user['username']}", "AUTH");
            return array(
                'success' => false,
                'message' => 'Your account has been suspended. Please contact an administrator.'
            );
        }

        if ($user['is_verified'] !== 1)
        {
            writeLog("Authentication blocked: Unverified account - {$user['username']}", "AUTH");
            return array(
                'success' => false,
                'message' => 'Your account has not been verified yet. Please wait for administrator approval.'
            );
        }

        if ($user['account_type'] === 'vendor' && (isset($user['is_approved']) ? $user['is_approved'] : 0) !== 1)
        {
            writeLog("Authentication blocked: Unapproved vendor account - {$user['username']}", "AUTH");
            return array(
                'success' => false,
                'message' => 'Your vendor account is pending administrative approval.'
            );
        }

        clearFailedLoginAttempts($ipAddress, $identifier);

        if (session_status() !== PHP_SESSION_ACTIVE)
        {
            startSecureSession();
        }
        regenerateSession();

        $role = $user['account_type'];
        $allowedRoles = unserialize(ALLOWED_ROLES);

        if (!in_array($role, $allowedRoles))
        {
            writeLog("Invalid role found for user: {$user['username']} - Role: $role", "AUTH");
            $role = 'student';
        }

        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['unique_id'] = $user['unique_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $role;
        $_SESSION['account_type'] = $role;
        $_SESSION['logged_in'] = true;
        $_SESSION['session_start_time'] = time();

        if ($user['account_type'] === 'vendor')
        {
            $_SESSION['vendor_id'] = (int)(isset($user['vendor_id']) ? $user['vendor_id'] : 0);
            $_SESSION['vendor_name'] = isset($user['vendor_name'])
                ? $user['vendor_name']
                : $user['full_name'];
            $_SESSION['vendor_is_open'] = 1;
        }

        writeLog("User authenticated successfully: {$user['username']} (Role: {$role})", "AUTH");

        return array(
            'success' => true,
            'message' => 'Login successful.',
            'user' => $user
        );
    }
}

// =============================================================================
// Current User Accessors
// =============================================================================

if (!function_exists('getCurrentUserId'))
{
    function getCurrentUserId()
    {
        if (!isLoggedIn())
        {
            return null;
        }
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }
}

if (!function_exists('getCurrentUserUniqueId'))
{
    function getCurrentUserUniqueId()
    {
        if (!isLoggedIn())
        {
            return null;
        }
        return isset($_SESSION['unique_id']) ? $_SESSION['unique_id'] : null;
    }
}

if (!function_exists('getCurrentUserName'))
{
    function getCurrentUserName()
    {
        if (!isLoggedIn())
        {
            return null;
        }
        return isset($_SESSION['username']) ? $_SESSION['username'] : null;
    }
}

if (!function_exists('getCurrentUserEmail'))
{
    function getCurrentUserEmail()
    {
        if (!isLoggedIn())
        {
            return null;
        }
        return isset($_SESSION['email']) ? $_SESSION['email'] : null;
    }
}

if (!function_exists('getCurrentUserRole'))
{
    function getCurrentUserRole()
    {
        if (!isLoggedIn())
        {
            return null;
        }
        return isset($_SESSION['role'])
            ? $_SESSION['role']
            : (isset($_SESSION['account_type']) ? $_SESSION['account_type'] : null);
    }
}

if (!function_exists('getCurrentUser'))
{
    /**
     * Returns the full current user record, or null if not logged in.
     *
     * The result is cached for the lifetime of the request so multiple
     * calls within the same page do not trigger repeated database reads.
     *
     * @return array|null The current user record
     */
    function getCurrentUser()
    {
        static $cachedCurrentUser = null;

        if ($cachedCurrentUser !== null)
        {
            return $cachedCurrentUser;
        }

        if (!isLoggedIn())
        {
            return null;
        }

        $db = getDB();
        $userId = getCurrentUserId();

        if ($userId === null)
        {
            return null;
        }

        $sql = "SELECT
                    u.user_id,
                    u.unique_id,
                    u.full_name,
                    u.username,
                    u.email,
                    u.account_type,
                    u.is_active,
                    u.is_verified,
                    u.created_at,
                    u.updated_at,
                    v.vendor_id,
                    v.vendor_name,
                    v.business_name,
                    v.description as vendor_description,
                    v.is_open as vendor_is_open,
                    v.is_approved as vendor_is_approved
                FROM users u
                LEFT JOIN vendors v ON u.user_id = v.vendor_user_id
                WHERE u.user_id = :user_id
                LIMIT 1";

        $user = $db->fetchOne($sql, array('user_id' => $userId));

        if ($user)
        {
            $cachedCurrentUser = $user;
        }

        return $user;
    }
}

// =============================================================================
// Authorization Helpers
// =============================================================================

if (!function_exists('isLoggedIn'))
{
    /**
     * Returns true if the current request has an active authenticated session.
     *
     * @return bool True if logged in and the session has not exceeded SESSION_LIFETIME
     */
    function isLoggedIn()
    {
        return isset($_SESSION['user_id'])
            && isset($_SESSION['logged_in'])
            && $_SESSION['logged_in'] === true
            && isset($_SESSION['session_start_time'])
            && (time() - $_SESSION['session_start_time']) < SESSION_LIFETIME;
    }
}

if (!function_exists('isStudent'))
{
    function isStudent()
    {
        return getCurrentUserRole() === 'student';
    }
}

if (!function_exists('isStandard'))
{
    function isStandard()
    {
        return getCurrentUserRole() === 'standard';
    }
}

if (!function_exists('isStudentOrStandard'))
{
    function isStudentOrStandard()
    {
        $role = getCurrentUserRole();
        return $role === 'student' || $role === 'standard';
    }
}

if (!function_exists('isVendor'))
{
    function isVendor()
    {
        return getCurrentUserRole() === 'vendor';
    }
}

if (!function_exists('isAdmin'))
{
    function isAdmin()
    {
        return getCurrentUserRole() === 'admin';
    }
}

// =============================================================================
// Authorization Requirements
// =============================================================================

if (!function_exists('requireLogin'))
{
    function requireLogin()
    {
        if (!isLoggedIn())
        {
            header('Location: ' . BASE_URL . '/modules/auth/login.php');
            exit();
        }
    }
}

if (!function_exists('requireStudent'))
{
    function requireStudent()
    {
        if (!isLoggedIn())
        {
            header('Location: ' . BASE_URL . '/modules/auth/login.php');
            exit();
        }

        if (!isStudent())
        {
            if (isAdmin())
            {
                header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
            }
            elseif (isVendor())
            {
                header('Location: ' . BASE_URL . '/modules/vendor/dashboard.php');
            }
            else
            {
                header('Location: ' . BASE_URL . '/modules/auth/login.php');
            }
            exit();
        }
    }
}

if (!function_exists('requireStudentOrStandard'))
{
    function requireStudentOrStandard()
    {
        if (!isLoggedIn())
        {
            header('Location: ' . BASE_URL . '/modules/auth/login.php');
            exit();
        }

        if (!isStudent() && !isStandard())
        {
            if (isAdmin())
            {
                header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
            }
            elseif (isVendor())
            {
                header('Location: ' . BASE_URL . '/modules/vendor/dashboard.php');
            }
            else
            {
                header('Location: ' . BASE_URL . '/modules/auth/login.php');
            }
            exit();
        }
    }
}

if (!function_exists('requireVendor'))
{
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
     * Requires an authenticated, approved vendor.
     *
     * @return void
     */
    function requireVendorVerified()
    {
        requireVendor();

        $db = getDB();
        $userId = getCurrentUserId();

        $vendor = $db->fetchOne(
            "SELECT is_approved FROM vendors WHERE vendor_user_id = :user_id LIMIT 1",
            array('user_id' => $userId)
        );

        if (!$vendor || $vendor['is_approved'] !== 1)
        {
            writeLog("Unapproved vendor attempted vendor area: User ID $userId", "AUTH");
            header('Location: ' . BASE_URL . '/modules/auth/logout.php');
            exit();
        }
    }
}

if (!function_exists('requireAdmin'))
{
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
     * The 303 See Other status is used so that a POST that triggers a
     * logout (for example, a "Logout" button in a form) is converted to
     * a GET, preventing the browser from repeating the POST if the user
     * refreshes the redirected page.
     *
     * @return void
     */
    function logout()
    {
        $userId = getCurrentUserId();
        $username = getCurrentUserName();

        writeLog("Logout initiated for user: $username (ID: $userId)", "AUTH");
        destroySession();
        writeLog("User logged out successfully: $username", "AUTH");

        $redirectUrl = ROOT_URL . '/index.php?logout=' . time();
        header('HTTP/1.1 303 See Other');
        header('Location: ' . $redirectUrl);
        exit();
    }
}