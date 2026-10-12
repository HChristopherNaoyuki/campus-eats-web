<?php
/**
 * Logout Page
 *
 * Destroys the session and redirects to the public landing page.
 * Tolerates a guest session (empty user id) without treating it as an
 * error. Cookie deletion uses path `/` to match startSecureSession().
 *
 * CORRECTIONS (Version 8.0 - Guest-safe logout):
 *
 * - Logs "guest" when no user is present instead of empty strings.
 * - Clears the session cookie on path `/` and on ROOT_URL so the
 *   browser drops CAMPUS_EATS_SESSION regardless of how it was set.
 * - Redirects to a clean index.php URL with 303 See Other and
 *   cache-busting headers.
 *
 * SOURCE: Software Engineering Report, section 2.2 Authentication.
 * SOURCE: Clean Code, Robert C. Martin.
 *
 * @version 8.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';

if (!defined('BASE_URL') || !defined('ROOT_URL'))
{
    die('Configuration constants are not defined.');
}

startSecureSession();

$userId = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
$username = function_exists('getCurrentUserName') ? getCurrentUserName() : null;

if ($userId === null || $userId === '' || $userId === 0)
{
    writeLog('Logout requested for guest session', 'AUTH');
}
else
{
    writeLog(
        'Logout requested for user: ' . $username . ' (ID: ' . $userId . ')',
        'AUTH'
    );
}

$_SESSION = array();

if (ini_get('session.use_cookies'))
{
    $params = session_get_cookie_params();
    $secureFlag = !empty($params['secure'])
        || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $cookieName = session_name();

    setcookie(
        $cookieName,
        '',
        time() - 42000,
        '/',
        isset($params['domain']) ? $params['domain'] : '',
        $secureFlag,
        true
    );

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

writeLog('User logged out successfully', 'AUTH');

while (ob_get_level() > 0)
{
    ob_end_clean();
}

header('HTTP/1.1 303 See Other');
header('Location: ' . ROOT_URL . '/index.php');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

exit();