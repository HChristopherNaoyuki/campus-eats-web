<?php
/**
 * Logout Page
 *
 * This page handles user logout by securely destroying the current session
 * and redirecting to the home page.
 *
 * @version 6.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';

// Verify constants are defined
if (!defined('BASE_URL'))
{
    die('BASE_URL constant is not defined. Please check constants.php');
}

if (!defined('ROOT_URL'))
{
    die('ROOT_URL constant is not defined. Please check constants.php');
}

// Use auth.php directly
require_once dirname(__DIR__, 2) . '/includes/auth.php';

// =============================================================================
// Start Secure Session
// =============================================================================

startSecureSession();

// =============================================================================
// Perform Logout
// =============================================================================

$userId = getCurrentUserId();
$username = getCurrentUserName();
writeLog("Logout requested for user: $username (ID: $userId)", "AUTH");

// Destroy the session completely
destroySession();

// Clear any additional session data that might persist
$_SESSION = array();

// Ensure the session cookie is removed
if (ini_get('session.use_cookies'))
{
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        $params['secure'] ?? false,
        $params['httponly'] ?? true
    );
}

writeLog("User logged out successfully: $username", "AUTH");

// =============================================================================
// Redirect to Home Page
// =============================================================================

while (ob_get_level() > 0)
{
    ob_end_clean();
}

$redirectUrl = ROOT_URL . '/index.php?logout=' . time();

writeLog("Redirecting to: $redirectUrl", "AUTH");

header('HTTP/1.1 303 See Other');
header('Location: ' . $redirectUrl);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

exit();