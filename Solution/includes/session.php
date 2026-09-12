<?php
/**
 * Campus Eats - Session Management (Compatibility Wrapper)
 *
 * This file serves as a compatibility wrapper that includes auth.php.
 * All session and authentication functions have been consolidated into auth.php
 * to prevent duplicate function declaration errors.
 *
 * CORRECTIONS (Version 6.0 - Deprecation Warning Fix):
 * - Fixed deprecation warning for session.php includes.
 * - Added proper redirection to auth.php.
 * - Improved error handling for missing auth.php.
 *
 * @version 6.0
 * @deprecated Use auth.php directly instead of session.php
 */

// Prevent direct file access
if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

// =============================================================================
// Include the Single Source of Truth
// =============================================================================

$authFile = dirname(__DIR__) . '/includes/auth.php';

if (!file_exists($authFile))
{
    error_log("CRITICAL: auth.php not found at $authFile");
    die('System configuration error. Please contact the administrator.');
}

require_once $authFile;

// =============================================================================
// Deprecation Notice (Development Only)
// =============================================================================

$backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1);
$callerFile = $backtrace[0]['file'] ?? 'unknown';

$isDevelopment = false;

if (isset($_SERVER['SERVER_NAME']))
{
    $serverName = $_SERVER['SERVER_NAME'];
    $isDevelopment = (
        strpos($serverName, 'localhost') !== false ||
        strpos($serverName, '127.0.0.1') !== false ||
        strpos($serverName, '192.168.') !== false ||
        strpos($serverName, '.test') !== false ||
        strpos($serverName, '.local') !== false
    );
}

if (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'development')
{
    $isDevelopment = true;
}

// CORRECTION: Only log deprecation warning in development
if ($isDevelopment)
{
    $deprecationMessage = "DEPRECATED: session.php included from $callerFile. "
                        . "Please update to include auth.php directly instead. "
                        . "This file will be removed in a future version.";

    error_log($deprecationMessage);

    if (function_exists('writeLog'))
    {
        writeLog($deprecationMessage, "DEPRECATED");
    }
}

// =============================================================================
// Verification and Testing Helpers (Development Only)
// =============================================================================

if (basename($_SERVER['SCRIPT_FILENAME']) === 'session.php' && $isDevelopment)
{
    header('Content-Type: text/plain');

    echo "=== Campus Eats Session.php Compatibility Wrapper ===\n\n";
    echo "This file is a compatibility wrapper that includes auth.php.\n";
    echo "All session and authentication functions have been consolidated into auth.php.\n\n";

    echo "Status: Working correctly\n";
    echo "auth.php loaded: " . (function_exists('startSecureSession') ? 'Yes' : 'No') . "\n";
    echo "csrfTokenHtml function available: " . (function_exists('csrfTokenHtml') ? 'Yes' : 'No') . "\n";
    echo "authenticateUser function available: " . (function_exists('authenticateUser') ? 'Yes' : 'No') . "\n\n";

    echo "Deprecation Notice:\n";
    echo "This file is deprecated. Please update your code to include auth.php directly.\n";

    exit();
}