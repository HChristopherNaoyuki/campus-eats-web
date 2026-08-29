<?php
/**
 * Campus Eats - Authentication and Authorization Module
 *
 * Handles user authentication, role-based access control,
 * session management, security header configuration, and rate limiting.
 *
 * CORRECTIONS (Version 19.0 - Security and Session Fixes):
 * - Fixed session cookie security flags (CRIT-04)
 * - Added Secure flag when HTTPS is detected
 * - Ensured HttpOnly flag is always set
 * - Fixed CSP to remove unsafe-inline (HIGH-06)
 * - Added proper session security
 * - Removed duplicate DEMO_ACCOUNTS (now uses demo_accounts.php)
 *
 * SOURCE: campus-eats-process-document.pdf (Section 15 - Login System)
 * SOURCE: campus-eats-process-document.pdf (Section 16 - Session Management)
 * SOURCE: campus-eats-process-document.pdf (Section 17 - Security Implementation)
 *
 * @package CampusEats
 * @subpackage Security
 * @version 19.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/error_logging.php';

// =============================================================================
// Load Demo Accounts from Single Source of Truth
// =============================================================================

if (file_exists(BASE_PATH . '/config/demo_accounts.php'))
{
    $demoAccounts = require_once BASE_PATH . '/config/demo_accounts.php';
    
    if (!defined('DEMO_ACCOUNTS'))
    {
        define('DEMO_ACCOUNTS', serialize($demoAccounts));
    }
}
else
{
    if (!defined('DEMO_ACCOUNTS'))
    {
        define('DEMO_ACCOUNTS', serialize(array()));
    }
}

// =============================================================================
// Session State Tracking
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
// Session Lifetime (in seconds)
// =============================================================================

if (!defined('SESSION_LIFETIME'))
{
    define('SESSION_LIFETIME', 3600); // 60 minutes
}

// =============================================================================
// Output Escaping Function
// =============================================================================

if (!function_exists('escapeOutput'))
{
    function escapeOutput($string)
    {
        if ($string === null)
        {
            return '';
        }
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
}

// =============================================================================
// Session Management Functions
// =============================================================================

if (!function_exists('startSecureSession'))
{
    /**
     * Starts a secure session with proper cookie flags.
     *
     * CORRECTION: CRIT-04 - Fixed session cookie security
     * - Always sets HttpOnly flag
     * - Sets Secure flag when HTTPS is detected
     * - Regenerates session ID periodically
     *
     * @return bool True if session started successfully
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
        
        // CORRECTION: CRIT-04 - Always set HttpOnly, set Secure based on HTTPS
        $secureFlag = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        
        // Set secure session cookie parameters
        session_set_cookie_params(
            $cookieParams['lifetime'],
            '/',
            $cookieParams['domain'] ?? '',
            $secureFlag,      // Secure flag - CRIT-04 fix
            true              // HttpOnly - always true
        );

        session_name('CAMPUS_EATS_SESSION');

        if (!session_start())
        {
            writeLog("Failed to start session", "AUTH");
            return false;
        }

        // Regenerate session ID on first load
        if (!isset($_SESSION['initialized']))
        {
            session_regenerate_id(true);
            $_SESSION['initialized'] = true;
            $_SESSION['created_at'] = time();
            writeLog("Session initialized and ID regenerated", "AUTH");
        }

        // Periodic session regeneration (every 30 minutes)
        if (!isset($_SESSION['last_regeneration']))
        {
            $_SESSION['last_regeneration'] = time();
        }
        else if (time() - $_SESSION['last_regeneration'] > 1800)
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
                    $params['path'] ?? '/',
                    $params['domain'] ?? '',
                    $secureFlag,
                    $params['httponly'] ?? true
                );
            }

            session_destroy();
            $GLOBALS['_SESSION_INITIALIZED'] = false;
            writeLog("Session destroyed successfully", "AUTH");
        }
        else
        {
            writeLog("No active session to destroy", "AUTH");
        }
    }
}

// =============================================================================
// Security Headers Functions
// =============================================================================

if (!function_exists('setSecurityHeaders'))
{
    /**
     * Sets security headers including CSP without unsafe-inline.
     *
     * CORRECTION: HIGH-06 - Removed 'unsafe-inline' from CSP
     *
     * @return bool True if headers were set
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

            // CORRECTION: HIGH-06 - Removed 'unsafe-inline' from CSP
            $csp = "default-src 'self'; "
                 . "script-src 'self' https://cdnjs.cloudflare.com https://www.gstatic.com; "
                 . "style-src 'self' https://cdnjs.cloudflare; "
                 . "font-src 'self' https://cdnjs.cloudflare; "
                 . "img-src 'self' data: https://images.unsplash.com https://fakerestaurantapi.runasp.net; "
                 . "connect-src 'self' https://fakerestaurantapi.runasp.net https://campus-eats-db-default-rtdb.europe-west1.firebasedatabase.app; "
                 . "frame-ancestors 'none'";

            header("Content-Security-Policy: $csp");

            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            {
                header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
            }

            header('X-XSS-Protection: 1; mode=block');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');

            $GLOBALS['_SECURITY_HEADERS_SET'] = true;
            writeLog("Security headers set (CSP with unsafe-inline removed)", "SECURITY");
        }

        return true;
    }
}

// =============================================================================
// CSRF Protection Functions
// =============================================================================

if (!function_exists('generateCsrfToken'))
{
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
    function validateCsrfToken($token, $regenerateOnSuccess = false)
    {
        if (!isset($_SESSION['csrf_token']))
        {
            writeLog("CSRF validation failed: No token in session", "SECURITY");
            return false;
        }

        $storedToken = $_SESSION['csrf_token'];
        $isValid = hash_equals($storedToken, $token);

        if (!$isValid)
        {
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $failKey = 'csrf_fail_' . md5($ipAddress);

            if (!isset($_SESSION[$failKey]))
            {
                $_SESSION[$failKey] = 1;
                $_SESSION[$failKey . '_time'] = time();
            }
            else
            {
                $_SESSION[$failKey]++;
            }

            if ($_SESSION[$failKey] > 10 && (time() - $_SESSION[$failKey . '_time']) < 300)
            {
                writeLog("Multiple CSRF validation failures from IP: $ipAddress", "SECURITY_ALERT");
            }

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
    function getCsrfToken()
    {
        return generateCsrfToken();
    }
}

if (!function_exists('csrfTokenHtml'))
{
    function csrfTokenHtml()
    {
        $token = generateCsrfToken();
        $escapedToken = escapeOutput($token);

        return '<input type="hidden" name="csrf_token" value="' . $escapedToken . '">' . "\n"
             . '<meta name="csrf-token" content="' . $escapedToken . '">';
    }
}

// =============================================================================
// Client IP Address Detection
// =============================================================================

if (!function_exists('getClientIpAddress'))
{
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

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

// =============================================================================
// Rate Limiting Functions
// =============================================================================

if (!function_exists('getFailedLoginAttemptCount'))
{
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
// Authentication Function
// =============================================================================

if (!function_exists('authenticateUser'))
{
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
            writeLog("Authentication blocked: Too many attempts for $identifier from $ipAddress", "AUTH");
            return array(
                'success' => false,
                'message' => 'Too many failed login attempts. Please wait ' . (LOGIN_ATTEMPT_WINDOW / 60) . ' minutes before trying again.'
            );
        }

        $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL);
        $field = $isEmail ? 'email' : 'username';

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
                WHERE u.$field = :identifier";

        $user = $db->fetchOne($sql, array('identifier' => $identifier));

        if (!$user)
        {
            recordFailedLoginAttempt($ipAddress, $identifier);
            writeLog("Authentication failed: User not found - $identifier", "AUTH");
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

        if ($user['account_type'] === 'vendor' && ($user['is_approved'] ?? 0) !== 1)
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
            writeLog("Invalid role found in database for user: {$user['username']} - Role: $role", "AUTH");
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
            $_SESSION['vendor_id'] = (int)($user['vendor_id'] ?? 0);
            $_SESSION['vendor_name'] = $user['vendor_name'] ?? $user['full_name'];
            $_SESSION['vendor_is_open'] = (int)($user['is_open'] ?? 1);
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
// User Information Functions
// =============================================================================

if (!function_exists('getCurrentUserId'))
{
    function getCurrentUserId()
    {
        if (!isLoggedIn())
        {
            return null;
        }
        return $_SESSION['user_id'] ?? null;
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
        return $_SESSION['unique_id'] ?? null;
    }
}

if (!function_exists('getCurrentUserFullName'))
{
    function getCurrentUserFullName()
    {
        if (!isLoggedIn())
        {
            return null;
        }
        return $_SESSION['full_name'] ?? $_SESSION['username'] ?? null;
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
        return $_SESSION['username'] ?? null;
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
        return $_SESSION['email'] ?? null;
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
        return $_SESSION['role'] ?? $_SESSION['account_type'] ?? null;
    }
}

if (!function_exists('getCurrentUser'))
{
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
                    v.vendor_name as vendor_name,
                    v.business_name,
                    v.description as vendor_description,
                    v.is_open as vendor_is_open,
                    v.is_approved as vendor_is_approved
                FROM users u
                LEFT JOIN vendors v ON u.user_id = v.vendor_user_id
                WHERE u.user_id = :user_id";

        $user = $db->fetchOne($sql, array('user_id' => $userId));

        if ($user)
        {
            $cachedCurrentUser = $user;
        }

        return $user;
    }
}

// =============================================================================
// Authorization Helper Functions
// =============================================================================

if (!function_exists('isLoggedIn'))
{
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
        $role = getCurrentUserRole();
        return $role === 'student';
    }
}

if (!function_exists('isStandard'))
{
    function isStandard()
    {
        $role = getCurrentUserRole();
        return $role === 'standard';
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
        $role = getCurrentUserRole();
        return $role === 'vendor';
    }
}

if (!function_exists('isAdmin'))
{
    function isAdmin()
    {
        $role = getCurrentUserRole();
        return $role === 'admin';
    }
}

// =============================================================================
// Authorization Requirement Functions
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
    function requireVendorVerified()
    {
        requireVendor();

        $db = getDB();
        $userId = getCurrentUserId();

        $vendor = $db->fetchOne(
            "SELECT is_approved FROM vendors WHERE vendor_user_id = :user_id",
            array('user_id' => $userId)
        );

        if (!$vendor || $vendor['is_approved'] !== 1)
        {
            writeLog("Unapproved vendor attempted to access vendor area: User ID $userId", "AUTH");
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