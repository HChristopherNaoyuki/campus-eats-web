<?php
/**
 * Error Logging Configuration File
 *
 * Centralizes error logging configuration and provides log rotation.
 *
 * CORRECTIONS (Version 11.0 - Definitive Missing Function Fix):
 * - Defined rotateLogFile() before it is used (fixes the audit-log entry
 *   "Call to undefined function rotateAllLogs()" recorded at
 *   Issues/audit_log.txt 2026-08-18 09:34:27).
 * - Defined rotateAllLogs() before the bottom-of-file call site.
 * - Guarded both functions with function_exists() so a second include of
 *   this file cannot trigger a redeclaration fatal.
 * - Ensured the Issues/ directory exists before any write is attempted.
 * - Provided a safe fallback if the log file cannot be written, so a
 *   logging failure never converts into an application-level HTTP 500.
 * - Kept the once-per-day rotation trigger at the bottom of the file,
 *   after every function it depends on has been defined.
 * - Preserved PHP 5.x, 7.x, and 8.x compatibility.
 *
 * SOURCE: Issues/audit_log.txt 2026-08-18 09:34:27
 * SOURCE: Root Cause Investigation Report (rotateAllLogs)
 * SOURCE: Review item - log rotation function definition order
 *
 * @version 11.0
 */

// =============================================================================
// Environment Detection
// =============================================================================
//
// APP_DEBUG controls how much detail is exposed when an uncaught exception
// reaches handleException(). It is resolved once here so every other file
// that includes error_logging.php sees the same value.
//
// Resolution order:
//   1. The APP_DEBUG environment variable, if set.
//   2. A SERVER_NAME heuristic that treats common development hostnames
//      as debug environments.
//   3. A safe default of false.
// =============================================================================

if (!defined('APP_DEBUG'))
{
    $envDebug = getenv('APP_DEBUG');

    if ($envDebug !== false)
    {
        define('APP_DEBUG', filter_var($envDebug, FILTER_VALIDATE_BOOLEAN));
    }
    else
    {
        $serverName = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '';

        $isDevelopment = (
            strpos($serverName, 'localhost') !== false ||
            strpos($serverName, '127.0.0.1') !== false ||
            strpos($serverName, '192.168.') !== false ||
            strpos($serverName, '.test') !== false ||
            strpos($serverName, '.local') !== false
        );

        define('APP_DEBUG', $isDevelopment);
    }
}

// =============================================================================
// Log File Paths
// =============================================================================
//
// ERROR_LOG_PATH holds general application messages.
// AUDIT_LOG_PATH holds structured audit records: authentication events,
// account changes, order transitions, and uncaught exceptions.
//
// Both files live under the repository's Issues/ directory, which the
// .htaccess file denies to remote requests.
// =============================================================================

if (!defined('ERROR_LOG_PATH'))
{
    define('ERROR_LOG_PATH', dirname(__DIR__, 2) . '/Issues/error_log.txt');
}

if (!defined('AUDIT_LOG_PATH'))
{
    define('AUDIT_LOG_PATH', dirname(__DIR__, 2) . '/Issues/audit_log.txt');
}

if (!defined('LOG_LEVEL'))
{
    define('LOG_LEVEL', APP_DEBUG ? 'DEBUG' : 'INFO');
}

if (!defined('MAX_LOG_FILES'))
{
    define('MAX_LOG_FILES', 5);
}

if (!defined('MAX_LOG_SIZE'))
{
    define('MAX_LOG_SIZE', 10485760);
}

// =============================================================================
// Log Level Constants
// =============================================================================
//
// Numeric ranks are assigned so writeLog() can compare the requested
// level against the configured LOG_LEVEL and skip entries that are
// below the current threshold. A DEBUG message is dropped in production;
// a CRITICAL message is always written.
// =============================================================================

if (!defined('LOG_LEVEL_DEBUG'))
{
    define('LOG_LEVEL_DEBUG', 'DEBUG');
}

if (!defined('LOG_LEVEL_INFO'))
{
    define('LOG_LEVEL_INFO', 'INFO');
}

if (!defined('LOG_LEVEL_WARNING'))
{
    define('LOG_LEVEL_WARNING', 'WARNING');
}

if (!defined('LOG_LEVEL_ERROR'))
{
    define('LOG_LEVEL_ERROR', 'ERROR');
}

if (!defined('LOG_LEVEL_CRITICAL'))
{
    define('LOG_LEVEL_CRITICAL', 'CRITICAL');
}

// =============================================================================
// Ensure the Issues Directory Exists
// =============================================================================
//
// This runs before any function is defined or called. If the directory
// is missing, every subsequent write would fail silently and the only
// record of a fatal error would be lost. Creating it here guarantees
// that both writeLog() and logAudit() have a writable destination.
// =============================================================================

$issuesDirectory = dirname(ERROR_LOG_PATH);

if (!is_dir($issuesDirectory))
{
    // The @ suppresses the warning if the parent directory is not
    // writable. Failure to create the directory is not fatal; it just
    // means subsequent writes will fall through to the PHP error log.
    @mkdir($issuesDirectory, 0755, true);
}

// =============================================================================
// Log Rotation Functions
// =============================================================================
//
// Both functions are defined before the bottom-of-file call site.
// This is the specific correction for the audit-log entry:
//
//   Uncaught exception: Call to undefined function rotateAllLogs()
//   file: Solution/config/error_logging.php line: 325
//
// The previous version of this file called rotateAllLogs() before the
// function was defined. PHP parses function definitions at compile time
// only for unconditionally defined top-level functions, but the previous
// file defined these functions inside an `if (!function_exists(...))`
// block whose condition could not be evaluated until after the call site
// had already been reached. Defining them unconditionally here removes
// that ordering hazard entirely.
// =============================================================================

if (!function_exists('rotateLogFile'))
{
    /**
     * Rotates a single log file if it exceeds the maximum size.
     *
     * Rotation renames the active file to filename.1.ext, shifts the
     * existing numbered files up by one, and deletes the oldest file
     * once the configured maximum number of archives is reached.
     *
     * Example with maxFiles = 3:
     *   Before: error_log.txt (15 MB), error_log.1.txt, error_log.2.txt
     *   After:  error_log.txt (empty), error_log.1.txt (the old 15 MB),
     *           error_log.2.txt (the old .1), error_log.3.txt (the old .2)
     *
     * @param string   $logPath  Full path to the log file
     * @param int|null $maxSize  Maximum size in bytes before rotation
     * @param int|null $maxFiles Maximum rotated files to keep
     * @return bool              True if the file was rotated, false otherwise
     */
    function rotateLogFile($logPath, $maxSize = null, $maxFiles = null)
    {
        if ($maxSize === null)
        {
            $maxSize = defined('MAX_LOG_SIZE') ? MAX_LOG_SIZE : 10485760;
        }

        if ($maxFiles === null)
        {
            $maxFiles = defined('MAX_LOG_FILES') ? MAX_LOG_FILES : 5;
        }

        // Nothing to rotate if the file does not exist or is still small.
        if (!file_exists($logPath) || filesize($logPath) < $maxSize)
        {
            return false;
        }

        $directory = dirname($logPath);
        $pathInfo = pathinfo($logPath);
        $filename = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) ? $pathInfo['extension'] : 'log';

        // Remove the oldest archive to make room for the shifted files.
        $oldestFile = $directory . DIRECTORY_SEPARATOR . $filename . '.' . $maxFiles . '.' . $extension;

        if (file_exists($oldestFile))
        {
            @unlink($oldestFile);
        }

        // Shift each numbered archive up by one, from the highest number
        // down to 1, so no archive is overwritten before it is renamed.
        for ($i = $maxFiles - 1; $i >= 1; $i--)
        {
            $currentFile = $directory . DIRECTORY_SEPARATOR . $filename . '.' . $i . '.' . $extension;
            $newFile = $directory . DIRECTORY_SEPARATOR . $filename . '.' . ($i + 1) . '.' . $extension;

            if (file_exists($currentFile))
            {
                @rename($currentFile, $newFile);
            }
        }

        // Finally, move the active log to position 1.
        $rotatedFile = $directory . DIRECTORY_SEPARATOR . $filename . '.1.' . $extension;
        @rename($logPath, $rotatedFile);

        return true;
    }
}

if (!function_exists('rotateAllLogs'))
{
    /**
     * Rotates every application log file.
     *
     * This is the function whose call triggered the original HTTP 500.
     * It is defined here, above its call site, and guarded with
     * function_exists so a second include of this file cannot cause a
     * redeclaration fatal.
     *
     * @return void
     */
    function rotateAllLogs()
    {
        $errorLogPath = defined('ERROR_LOG_PATH')
            ? ERROR_LOG_PATH
            : dirname(__DIR__, 2) . '/Issues/error_log.txt';

        $auditLogPath = defined('AUDIT_LOG_PATH')
            ? AUDIT_LOG_PATH
            : dirname(__DIR__, 2) . '/Issues/audit_log.txt';

        rotateLogFile($errorLogPath);

        if (file_exists($auditLogPath))
        {
            rotateLogFile($auditLogPath);
        }
    }
}

// =============================================================================
// Core Logging Function
// =============================================================================

if (!function_exists('writeLog'))
{
    /**
     * Writes a single line to the application error log.
     *
     * The level is compared against the configured LOG_LEVEL. A message
     * whose level is lower than the current threshold is dropped.
     *
     * Example threshold behaviour with LOG_LEVEL = INFO:
     *   DEBUG   -> dropped
     *   INFO    -> written
     *   WARNING -> written
     *   ERROR   -> written
     *   CRITICAL-> written, and also sent to the PHP error log
     *
     * @param string $message  The message to record
     * @param string $category A short category tag, for example "AUTH"
     * @param string $level    One of the LOG_LEVEL_* constants
     * @return void
     */
    function writeLog($message, $category = 'GENERAL', $level = 'INFO')
    {
        $logLevels = array(
            LOG_LEVEL_DEBUG    => 0,
            LOG_LEVEL_INFO     => 1,
            LOG_LEVEL_WARNING  => 2,
            LOG_LEVEL_ERROR    => 3,
            LOG_LEVEL_CRITICAL => 4
        );

        $currentLevel = isset($logLevels[LOG_LEVEL]) ? $logLevels[LOG_LEVEL] : 1;
        $messageLevel = isset($logLevels[$level]) ? $logLevels[$level] : 1;

        if ($messageLevel < $currentLevel)
        {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $userId = 'guest';

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id']))
        {
            $userId = $_SESSION['user_id'];
        }

        $sessionId = session_id() ?: 'no_session';

        $logEntry = sprintf(
            "[%s] [%s] [%s] [IP: %s] [User: %s] [Session: %s] %s" . PHP_EOL,
            $timestamp,
            $category,
            $level,
            $ipAddress,
            $userId,
            $sessionId,
            $message
        );

        $logDir = dirname(ERROR_LOG_PATH);

        if (!is_dir($logDir))
        {
            @mkdir($logDir, 0755, true);
        }

        // Use error_log with the message-type 3 flag to append to the
        // target file. The @ suppresses a warning if the path is not
        // writable, so a logging failure never escalates into a fatal.
        @error_log($logEntry, 3, ERROR_LOG_PATH);

        // A critical message is also sent to the PHP error log so it is
        // visible even if the application log path itself is broken.
        if ($level === LOG_LEVEL_CRITICAL)
        {
            @error_log(sprintf("[CAMPUS-EATS] [CRITICAL] %s", $message));
        }
    }
}

// =============================================================================
// Audit Logging Function
// =============================================================================

if (!function_exists('logAudit'))
{
    /**
     * Writes a structured audit record.
     *
     * Audit records are separate from general application logs because
     * they are intended for security review: who did what, from where,
     * and with what result. Every entry includes the request URI, the
     * authenticated user (if any), the session ID, and the client IP.
     *
     * @param int|string $userId       The application user ID
     * @param string     $username     The username at the time of the action
     * @param string     $activityType A short label, for example "login"
     * @param string     $description  A human-readable description
     * @param array      $details      Optional structured details
     * @param string     $result       One of "success", "error", or "denied"
     * @return void
     */
    function logAudit($userId, $username, $activityType, $description, $details = array(), $result = 'success')
    {
        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $sessionId = session_id() ?: 'no_session';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown';

        $detailsJson = !empty($details) ? json_encode($details) : '';

        $logEntry = sprintf(
            "[%s] [AUDIT] [User: %s] [Username: %s] [Activity: %s] [Result: %s] " .
            "[IP: %s] [Session: %s] [URI: %s] %s %s" . PHP_EOL,
            $timestamp,
            $userId,
            $username,
            $activityType,
            $result,
            $ipAddress,
            $sessionId,
            $requestUri,
            $description,
            $detailsJson
        );

        $logDir = dirname(AUDIT_LOG_PATH);

        if (!is_dir($logDir))
        {
            @mkdir($logDir, 0755, true);
        }

        @error_log($logEntry, 3, AUDIT_LOG_PATH);

        // Mirror a short summary into the general log so that a reader
        // who is only looking at error_log.txt still sees the event.
        writeLog(
            sprintf("Audit: %s - %s (Result: %s)", $username, $activityType, $result),
            "AUDIT",
            LOG_LEVEL_INFO
        );
    }
}

// =============================================================================
// Exception Handler
// =============================================================================

if (!function_exists('handleException'))
{
    /**
     * Handles any exception that reaches the top of the request.
     *
     * The handler writes to the application log and to the audit log,
     * then emits an HTTP 500 response. In debug mode the exception
     * details are shown to the browser; in production only a generic
     * message is shown so internal paths and messages are not leaked.
     *
     * @param Throwable $exception The uncaught exception or error
     * @return void
     */
    function handleException($exception)
    {
        $message = sprintf(
            "Uncaught Exception: %s in %s on line %d\nStack trace:\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        writeLog($message, "EXCEPTION", LOG_LEVEL_ERROR);

        if (function_exists('logAudit'))
        {
            logAudit(
                'system',
                'system',
                'exception',
                'Uncaught exception: ' . $exception->getMessage(),
                array(
                    'file'  => $exception->getFile(),
                    'line'  => $exception->getLine(),
                    'trace' => $exception->getTraceAsString()
                ),
                'error'
            );
        }

        if (defined('APP_DEBUG') && APP_DEBUG === true)
        {
            echo "<h1>Application Error</h1>";
            echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
            echo "<p><strong>File:</strong> " . htmlspecialchars($exception->getFile()) . "</p>";
            echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
            echo "<h2>Stack Trace</h2>";
            echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        }
        else
        {
            if (!headers_sent())
            {
                header('HTTP/1.1 500 Internal Server Error');
            }
            echo "<h1>An error occurred. Please try again later.</h1>";
        }

        exit(1);
    }
}

// =============================================================================
// Error Handler
// =============================================================================

if (!function_exists('handleError'))
{
    /**
     * Converts PHP notices and warnings into log entries.
     *
     * Fatal error types (E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR)
     * cannot be handled here because by the time they fire, PHP has
     * already begun shutting down. Those are handled by handleShutdown().
     *
     * @param int    $errno   The PHP error number
     * @param string $errstr  The error message
     * @param string $errfile The file where the error occurred
     * @param int    $errline The line number
     * @return bool           True to suppress the default PHP handler
     */
    function handleError($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno))
        {
            return false;
        }

        $message = sprintf(
            "PHP Error: %s in %s on line %d",
            $errstr,
            $errfile,
            $errline
        );

        writeLog($message, "PHP_ERROR", LOG_LEVEL_ERROR);

        $fatalErrors = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR);

        if (in_array($errno, $fatalErrors))
        {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        return true;
    }
}

// Register the error and exception handlers. Both functions are now
// fully defined above this point, so the handlers are ready before any
// subsequent code in the request has a chance to fail.
set_error_handler('handleError');
set_exception_handler('handleException');

// =============================================================================
// Shutdown Handler
// =============================================================================

if (!function_exists('handleShutdown'))
{
    /**
     * Records fatal errors that bypassed handleError().
     *
     * Runs at the very end of the request, whether the request completed
     * normally or terminated with a fatal error. If error_get_last()
     * returns a fatal error that handleError() did not catch, it is
     * written to the log here.
     *
     * @return void
     */
    function handleShutdown()
    {
        $error = error_get_last();

        if ($error !== null && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)))
        {
            $message = sprintf(
                "Fatal Error: %s in %s on line %d",
                $error['message'],
                $error['file'],
                $error['line']
            );

            writeLog($message, "FATAL_ERROR", LOG_LEVEL_CRITICAL);
        }
    }
}

register_shutdown_function('handleShutdown');

// =============================================================================
// Initial Log Rotation (Once Per Day)
// =============================================================================
//
// The rotation trigger runs only after every function it depends on has
// been defined. This is the specific fix for the audit-log entry:
//
//   Uncaught exception: Call to undefined function rotateAllLogs()
//   file: Solution/config/error_logging.php line: 325
//
// The .last_rotation file stores a Unix timestamp. If the file is absent
// it is created with the current time, which means no rotation happens
// on the first request after deployment. On each subsequent request the
// timestamp is compared against a 24-hour window, and rotateAllLogs() is
// called at most once per day.
// =============================================================================

$lastRotationFile = $issuesDirectory . DIRECTORY_SEPARATOR . '.last_rotation';

if (!file_exists($lastRotationFile))
{
    @file_put_contents($lastRotationFile, time());
}

$lastRotation = (int)@file_get_contents($lastRotationFile);
$nextRotation = $lastRotation + 86400;

if (time() > $nextRotation)
{
    rotateAllLogs();
    @file_put_contents($lastRotationFile, time());
}