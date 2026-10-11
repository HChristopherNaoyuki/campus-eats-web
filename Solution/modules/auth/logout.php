<?php
/**
 * Logout Page
 *
 * Securely destroys the current session and redirects to the
 * public landing page. Cookie deletion uses the same path (`/`)
 * that startSecureSession() sets, so the browser actually drops
 * the session cookie.
 *
 * CORRECTIONS (Version 7.0 - Session Clear Fix):
 *
 * - Cookie is expired with path `/` (matching startSecureSession)
 *   and also with ROOT_URL path as a safety net for hosts that
 *   set a more specific path.
 * - $_SESSION is emptied, session is destroyed, and the process
 *   flag `_SESSION_INITIALIZED` is cleared so a later request
 *   cannot treat the old session as still active.
 * - Redirect goes to a clean index.php URL (no query string) so
 *   index.php does not re-enter logout handling.
 * - 303 See Other prevents the browser from replaying a POST.
 *
 * SOURCE: Error log – infinite post-logout redirect loop.
 * SOURCE: Clean Code, Robert C. Martin, Chapters 2–4.
 *
 * @version 7.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

if (!defined('BASE_URL') || !defined('ROOT_URL'))
{
    die('Configuration constants are not defined.');
}

startSecureSession();

$userId = function_exists('getCurrentUserId') ? getCurrentUserId() : '';
$username = function_exists('getCurrentUserName') ? getCurrentUserName() : '';

writeLog(
    "Logout requested for user: $username (ID: $userId)",
    'AUTH'
);

// ---------------------------------------------------------------------------
// Full session destruction
// ---------------------------------------------------------------------------

$_SESSION = array();

if (ini_get('session.use_cookies'))
{
    $params = session_get_cookie_params();
    $secureFlag = !empty($params['secure'])
        || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookieName = session_name();

    // Path must match the path used when the cookie was set (`/`).
    setcookie(
        $cookieName,
        '',
        time() - 42000,
        '/',
        isset($params['domain']) ? $params['domain'] : '',
        $secureFlag,
        true
    );

    // Safety: also expire under the application base path.
    if (defined('ROOT_URL') && ROOT_URL !== '' && ROOT_URL !== '/')
    {
        setcookie(
            $cookieName,
            '',
            time() - 42000,
            ROOT_URL,
            isset($params['domain']) ? $params['domain'] : '',
            $secureFlag,
            true
        );
    }
}

if (session_status() === PHP_SESSION_ACTIVE)
{
    session_destroy();
}

$GLOBALS['_SESSION_INITIALIZED'] = false;

writeLog("User logged out successfully: $username", 'AUTH');

// ---------------------------------------------------------------------------
// Redirect to a clean landing page (no ?logout= query)
// ---------------------------------------------------------------------------

while (ob_get_level() > 0)
{
    ob_end_clean();
}

$redirectUrl = ROOT_URL . '/index.php';

writeLog('Redirecting to: ' . $redirectUrl, 'AUTH');

header('HTTP/1.1 303 See Other');
header('Location: ' . $redirectUrl);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

exit();