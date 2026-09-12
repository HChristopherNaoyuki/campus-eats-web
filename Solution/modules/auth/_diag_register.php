<?php
/**
 * Registration diagnostic.
 *
 * Checks the on-disk state of the files the registration page depends on,
 * walks the include chain, and reports any failure with the exact file
 * and line. Delete this file after use.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: text/plain; charset=utf-8');

echo "Campus Eats - Registration Diagnostic\n";
echo "======================================\n\n";

// ---- 1. File presence -------------------------------------------------------

$base = dirname(__DIR__, 2);

$required = array(
    'config/constants.php',
    'config/database.php',
    'config/error_logging.php',
    'config/demo_accounts.php',
    'includes/auth.php',
    'includes/password_validation.php',
    'includes/user_id.php',
    'modules/auth/register.php',
    'assets/js/auth.js',
    'sql/install.sql'
);

echo "File presence:\n";
foreach ($required as $rel)
{
    $path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    $exists = file_exists($path) ? 'yes' : 'NO';
    echo "  " . str_pad($rel, 40) . " : " . $exists . "\n";
}

echo "\n";

// ---- 2. userCount method presence -------------------------------------------

$dbContent = file_exists($base . '/config/database.php')
    ? file_get_contents($base . '/config/database.php')
    : '';

echo "database.php checks:\n";
echo "  contains function userCount          : "
    . (strpos($dbContent, 'function userCount') !== false ? 'yes' : 'NO') . "\n";
echo "  contains splitSqlStatements          : "
    . (strpos($dbContent, 'splitSqlStatements') !== false ? 'yes' : 'NO') . "\n";
echo "  contains MYSQL_ATTR_USE_BUFFERED_QUERY: "
    . (strpos($dbContent, 'MYSQL_ATTR_USE_BUFFERED_QUERY') !== false ? 'yes' : 'NO') . "\n";

echo "\n";

// ---- 3. Include chain execution ---------------------------------------------

echo "Include chain:\n";

$chain = array(
    'config/constants.php',
    'config/error_logging.php',
    'includes/password_validation.php',
    'includes/user_id.php',
    'config/database.php',
    'includes/auth.php'
);

foreach ($chain as $rel)
{
    $path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);

    if (!file_exists($path))
    {
        echo "  " . str_pad($rel, 40) . " : SKIPPED (missing)\n";
        continue;
    }

    try
    {
        require_once $path;
        echo "  " . str_pad($rel, 40) . " : OK\n";
    }
    catch (Throwable $t)
    {
        echo "  " . str_pad($rel, 40) . " : FATAL "
            . get_class($t) . ": " . $t->getMessage()
            . " in " . $t->getFile() . " line " . $t->getLine() . "\n";
        echo "\nStopped at this file.\n";
        break;
    }
}

echo "\n";

// ---- 4. Function and method presence ----------------------------------------

echo "Function presence:\n";

$functions = array(
    'escapeOutput',
    'getDB',
    'getCsrfToken',
    'validateCsrfToken',
    'startSecureSession',
    'validatePasswordPolicy',
    'hashPassword',
    'generateAlphanumericUserId',
    'formatUserIdForDisplay'
);

foreach ($functions as $fn)
{
    echo "  " . str_pad($fn, 40) . " : "
        . (function_exists($fn) ? 'yes' : 'NO') . "\n";
}

echo "\n";

if (function_exists('getDB'))
{
    try
    {
        $db = getDB();
        echo "DatabaseConnection instantiated : yes\n";
        echo "userCount method present        : "
            . (method_exists($db, 'userCount') ? 'yes' : 'NO') . "\n";

        if (method_exists($db, 'userCount'))
        {
            $count = $db->userCount();
            echo "Current user count              : " . $count . "\n";
        }
    }
    catch (Throwable $t)
    {
        echo "DatabaseConnection failed       : "
            . get_class($t) . ": " . $t->getMessage()
            . " in " . $t->getFile() . " line " . $t->getLine() . "\n";
    }
}

echo "\n";

// ---- 5. register.php on-disk content ----------------------------------------

echo "register.php checks:\n";

$registerPath = $base . '/modules/auth/register.php';
$registerContent = file_exists($registerPath) ? file_get_contents($registerPath) : '';

echo "  contains userCount()              : "
    . (strpos($registerContent, 'userCount()') !== false ? 'yes' : 'NO') . "\n";
echo "  contains isFirstUser              : "
    . (strpos($registerContent, 'isFirstUser') !== false ? 'yes' : 'NO') . "\n";
echo "  contains Admin role               : "
    . (strpos($registerContent, 'Admin') !== false ? 'yes' : 'NO') . "\n";
echo "  contains escapeOutput             : "
    . (strpos($registerContent, 'escapeOutput') !== false ? 'yes' : 'NO') . "\n";
echo "  contains copy-user-id-btn         : "
    . (strpos($registerContent, 'copy-user-id-btn') !== false ? 'yes' : 'NO') . "\n";

echo "\n";

// ---- 6. Last log entries ----------------------------------------------------

echo "Last 20 lines of Issues/error_log.txt:\n";

$errLog = $base . '/../Issues/error_log.txt';

if (file_exists($errLog))
{
    $lines = file($errLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_slice($lines, -20);

    foreach ($lines as $line)
    {
        echo "  " . $line . "\n";
    }
}
else
{
    echo "  (file not found at " . $errLog . ")\n";
}

echo "\n";

echo "Last 20 lines of Issues/audit_log.txt:\n";

$auditLog = $base . '/../Issues/audit_log.txt';

if (file_exists($auditLog))
{
    $lines = file($auditLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_slice($lines, -20);

    foreach ($lines as $line)
    {
        echo "  " . $line . "\n";
    }
}
else
{
    echo "  (file not found at " . $auditLog . ")\n";
}

echo "\nDone.\n";