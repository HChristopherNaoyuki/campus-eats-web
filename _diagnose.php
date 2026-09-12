<?php
/**
 * Temporary diagnostic script.
 *
 * Reads the current application logs and walks the include chain used by
 * index.php so the exact failing file can be identified without a terminal.
 *
 * DELETE THIS FILE after use. It reveals environment details and log
 * contents, and it must not remain on disk.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

header('Content-Type: text/html; charset=utf-8');

function section($title)
{
    echo "<h2 style='font-family: monospace; background: #eee; padding: 8px; margin-top: 24px;'>"
        . htmlspecialchars($title)
        . "</h2>";
}

function line($label, $value)
{
    echo "<p style='font-family: monospace; margin: 4px 0;'><strong>"
        . htmlspecialchars($label)
        . ":</strong> "
        . htmlspecialchars((string)$value)
        . "</p>";
}

function tailFile($path, $lines)
{
    if (!file_exists($path))
    {
        return array("(file does not exist: " . $path . ")");
    }

    if (!is_readable($path))
    {
        return array("(file exists but is not readable: " . $path . ")");
    }

    $size = filesize($path);

    if ($size === 0)
    {
        return array("(file is empty: " . $path . ")");
    }

    $handle = fopen($path, 'r');

    if ($handle === false)
    {
        return array("(could not open: " . $path . ")");
    }

    // Read the whole file if it is small; otherwise read the last chunk.
    $chunkSize = 16384;
    $content = '';

    if ($size <= $chunkSize)
    {
        $content = fread($handle, $size);
    }
    else
    {
        fseek($handle, -$chunkSize, SEEK_END);
        $content = fread($handle, $chunkSize);
    }

    fclose($handle);

    $allLines = preg_split('/\r\n|\r|\n/', $content);
    $allLines = array_filter($allLines, function($ln) { return trim($ln) !== ''; });
    $allLines = array_slice($allLines, -$lines);

    return $allLines;
}

echo "<html><head><title>Campus Eats Diagnostic</title></head><body style='font-family: sans-serif; padding: 20px;'>";
echo "<h1>Campus Eats Diagnostic</h1>";

// =========================================================================
// 1. Environment
// =========================================================================

section("Environment");

line("PHP version", PHP_VERSION);
line("display_errors", ini_get('display_errors'));
line("log_errors", ini_get('log_errors'));
line("error_log (php.ini)", ini_get('error_log') ?: '(not set)');
line("Server software", isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown');
line("Document root", isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : 'unknown');
line("SCRIPT_FILENAME", isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : 'unknown');

$requiredExtensions = array('pdo', 'pdo_mysql', 'mysqli', 'mbstring', 'json', 'session', 'curl', 'openssl');

foreach ($requiredExtensions as $ext)
{
    line("extension " . $ext, extension_loaded($ext) ? 'loaded' : 'MISSING');
}

// =========================================================================
// 2. Include chain walk
// =========================================================================

section("Include chain walk");

$base = __DIR__;
$steps = array(
    'Solution/config/constants.php',
    'Solution/config/error_logging.php',
    'Solution/config/database.php',
    'Solution/includes/password_validation.php',
    'Solution/includes/user_id.php',
    'Solution/includes/auth.php'
);

foreach ($steps as $relative)
{
    $full = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!file_exists($full))
    {
        line($relative, "MISSING on disk");
        continue;
    }

    // Run a syntax-only check by shelling out if PHP CLI is available.
    // If it is not, we fall back to a lightweight parse check.
    $syntax = '(not checked)';
    $phpCli = PHP_BINARY;

    if ($phpCli && file_exists($phpCli))
    {
        $cmd = escapeshellarg($phpCli) . ' -l ' . escapeshellarg($full) . ' 2>&1';
        $output = @shell_exec($cmd);
        $syntax = trim((string)$output);
    }

    line($relative, "exists, " . $syntax);
}

// =========================================================================
// 3. Try to load the include chain in the same order as index.php
// =========================================================================

section("Include chain execution");

$toLoad = array(
    'Solution/config/constants.php',
    'Solution/includes/auth.php'
);

foreach ($toLoad as $relative)
{
    $full = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);

    if (!file_exists($full))
    {
        line("require " . $relative, "skipped, file missing");
        continue;
    }

    try
    {
        require_once $full;
        line("require " . $relative, "OK");
    }
    catch (Throwable $t)
    {
        line("require " . $relative, "FATAL " . get_class($t) . ": " . $t->getMessage()
            . " in " . $t->getFile() . " line " . $t->getLine());
        echo "<p style='color:red; font-family: monospace;'>Stopped at this file. The error above names the exact file and line.</p>";
        break;
    }
}

// =========================================================================
// 4. Verify the corrected files are on disk
// =========================================================================

section("On-disk verification of corrected files");

$databasePath = $base . DIRECTORY_SEPARATOR . 'Solution' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
$errorLoggingPath = $base . DIRECTORY_SEPARATOR . 'Solution' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'error_logging.php';

$databaseContent = file_exists($databasePath) ? file_get_contents($databasePath) : '';
$errorLoggingContent = file_exists($errorLoggingPath) ? file_get_contents($errorLoggingPath) : '';

line(
    "database.php contains MYSQL_ATTR_USE_BUFFERED_QUERY",
    strpos($databaseContent, 'MYSQL_ATTR_USE_BUFFERED_QUERY') !== false ? 'YES' : 'NO (still old version)'
);

line(
    "database.php contains closeCursor",
    substr_count($databaseContent, 'closeCursor') . ' occurrence(s)'
);

line(
    "error_logging.php defines rotateAllLogs",
    strpos($errorLoggingContent, 'function rotateAllLogs') !== false ? 'YES' : 'NO (still old version)'
);

// Report the position of the rotateAllLogs definition vs the call site.
$defPos = strpos($errorLoggingContent, 'function rotateAllLogs');
$callPos = strrpos($errorLoggingContent, 'rotateAllLogs();');

line(
    "rotateAllLogs definition before call site",
    ($defPos !== false && $callPos !== false && $defPos < $callPos) ? 'YES' : 'NO or not found'
);

// =========================================================================
// 5. Read the logs
// =========================================================================

section("Issues/audit_log.txt (last 20 lines)");
foreach (tailFile($base . '/Issues/audit_log.txt', 20) as $ln)
{
    echo "<pre style='background:#f7f7f7; padding:8px; margin:2px 0;'>" . htmlspecialchars($ln) . "</pre>";
}

section("Issues/error_log.txt (last 20 lines)");
foreach (tailFile($base . '/Issues/error_log.txt', 20) as $ln)
{
    echo "<pre style='background:#f7f7f7; padding:8px; margin:2px 0;'>" . htmlspecialchars($ln) . "</pre>";
}

section("Apache error log (last 30 lines)");
$apacheLogCandidates = array(
    'C:/wamp64/logs/apache_error.log',
    'C:/wamp64/bin/apache/apache2.4.65/logs/error.log',
    'C:/wamp64/logs/error.log'
);

$found = false;

foreach ($apacheLogCandidates as $candidate)
{
    if (file_exists($candidate))
    {
        line("Reading", $candidate);

        foreach (tailFile($candidate, 30) as $ln)
        {
            echo "<pre style='background:#f7f7f7; padding:8px; margin:2px 0;'>" . htmlspecialchars($ln) . "</pre>";
        }

        $found = true;
        break;
    }
}

if (!$found)
{
    echo "<p>No Apache error log found at any of the standard locations.</p>";
}

section("PHP error log (last 30 lines)");
$phpLogCandidates = array(
    'C:/wamp64/logs/php_error.log',
    'C:/wamp64/logs/php_error.log.txt',
    ini_get('error_log')
);

$found = false;

foreach ($phpLogCandidates as $candidate)
{
    if (!empty($candidate) && file_exists($candidate))
    {
        line("Reading", $candidate);

        foreach (tailFile($candidate, 30) as $ln)
        {
            echo "<pre style='background:#f7f7f7; padding:8px; margin:2px 0;'>" . htmlspecialchars($ln) . "</pre>";
        }

        $found = true;
        break;
    }
}

if (!$found)
{
    echo "<p>No PHP error log found at any of the standard locations.</p>";
}

echo "<hr><p><strong>Delete this file now.</strong> Path: " . htmlspecialchars(__FILE__) . "</p>";
echo "</body></html>";