<?php
/**
 * Error Logging Configuration File
 *
 * Centralizes error logging configuration to prevent duplicate constant definitions.
 *
 * CORRECTIONS (Version 8.0 - Critical Fixes):
 * - Added missing rotateAllLogs() function (CRIT-01)
 * - Added missing rotateLogFile() function
 * - Fixed exception handler to not re-throw errors (CRIT-02)
 * - Added proper error recovery
 * - Added directory creation for log files
 * - Ensured all @ operators are removed and proper error handling is used
 *
 * @version 8.0
 */

// =============================================================================
// Check if APP_DEBUG is defined
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
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        
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
// Log File Path Configuration
// =============================================================================

if (!defined('ERROR_LOG_PATH'))
{
    define('ERROR_LOG_PATH', dirname(__DIR__, 2) . '/Issues/error_log.txt');
}

if (!defined('LOG_LEVEL'))
{
    define('LOG_LEVEL', APP_DEBUG ? 'DEBUG' : 'INFO');
}

if (!defined('AUDIT_LOG_PATH'))
{
    define('AUDIT_LOG_PATH', dirname(__DIR__, 2) . '/Issues/audit_log.txt');
}

if (!defined('MAX_LOG_FILES'))
{
    define('MAX_LOG_FILES', 5);
}

if (!defined('MAX_LOG_SIZE'))
{
    define('MAX_LOG_SIZE', 10485760); // 10MB
}

// =============================================================================
// Log Level Constants
// =============================================================================

define('LOG_LEVEL_DEBUG', 'DEBUG');
define('LOG_LEVEL_INFO', 'INFO');
define('LOG_LEVEL_WARNING', 'WARNING');
define('LOG_LEVEL_ERROR', 'ERROR');
define('LOG_LEVEL_CRITICAL', 'CRITICAL');

// =============================================================================
// Activity Types for Audit Logging
// =============================================================================

define('ACTIVITY_LOGIN', 'login');
define('ACTIVITY_LOGOUT', 'logout');
define('ACTIVITY_REGISTER', 'register');
define('ACTIVITY_PASSWORD_RESET', 'password_reset');
define('ACTIVITY_ORDER_PLACED', 'order_placed');
define('ACTIVITY_ORDER_UPDATED', 'order_updated');
define('ACTIVITY_CART_ADD', 'cart_add');
define('ACTIVITY_CART_REMOVE', 'cart_remove');
define('ACTIVITY_CART_UPDATE', 'cart_update');
define('ACTIVITY_CART_CLEAR', 'cart_clear');
define('ACTIVITY_CHECKOUT', 'checkout');
define('ACTIVITY_PAYMENT', 'payment');
define('ACTIVITY_MENU_ADD', 'menu_add');
define('ACTIVITY_MENU_EDIT', 'menu_edit');
define('ACTIVITY_MENU_DELETE', 'menu_delete');
define('ACTIVITY_VENDOR_APPROVE', 'vendor_approve');
define('ACTIVITY_VENDOR_SUSPEND', 'vendor_suspend');
define('ACTIVITY_USER_APPROVE', 'user_approve');
define('ACTIVITY_USER_SUSPEND', 'user_suspend');

// =============================================================================
// Ensure Log Directory Exists
// =============================================================================

$logDir = dirname(ERROR_LOG_PATH);
if (!is_dir($logDir))
{
    mkdir($logDir, 0755, true);
}

$auditDir = dirname(AUDIT_LOG_PATH);
if (!is_dir($auditDir))
{
    mkdir($auditDir, 0755, true);
}

// =============================================================================
// CORRECTION: Log Rotation Functions - Added missing functions
// =============================================================================

if (!function_exists('rotateLogFile'))
{
    /**
     * Rotates a single log file if it exceeds the maximum size.
     *
     * @param string $logPath Path to the log file
     * @param int $maxSize Maximum file size in bytes
     * @param int $maxFiles Maximum number of rotated files to keep
     * @return bool True if rotation was performed, false otherwise
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
        
        // Check if the log file exists and exceeds the size limit
        if (!file_exists($logPath) || filesize($logPath) < $maxSize)
        {
            return false;
        }
        
        $directory = dirname($logPath);
        $pathInfo = pathinfo($logPath);
        
        // Ensure directory exists
        if (!is_dir($directory))
        {
            mkdir($directory, 0755, true);
        }
        
        // Remove the oldest rotated file if it exists
        $oldestFile = $directory . '/' . $pathInfo['filename'] . '.' . $maxFiles . '.' . $pathInfo['extension'];
        if (file_exists($oldestFile))
        {
            unlink($oldestFile);
        }
        
        // Shift existing rotated files (rename .N to .N+1)
        for ($i = $maxFiles - 1; $i >= 1; $i--)
        {
            $currentFile = $directory . '/' . $pathInfo['filename'] . '.' . $i . '.' . $pathInfo['extension'];
            $newFile = $directory . '/' . $pathInfo['filename'] . '.' . ($i + 1) . '.' . $pathInfo['extension'];
            
            if (file_exists($currentFile))
            {
                rename($currentFile, $newFile);
            }
        }
        
        // Rename the current log file to .1
        $rotatedFile = $directory . '/' . $pathInfo['filename'] . '.1.' . $pathInfo['extension'];
        rename($logPath, $rotatedFile);
        
        return true;
    }
}

if (!function_exists('rotateAllLogs'))
{
    /**
     * Rotates all log files (error and audit logs).
     *
     * CORRECTION: This function was being called at line 325 but was not defined.
     * This was causing a fatal error on every page load.
     * Source: error_logging.php line 325
     * Issue: CRIT-01
     *
     * @return void
     */
    function rotateAllLogs()
    {
        $errorLogPath = defined('ERROR_LOG_PATH') ? ERROR_LOG_PATH : dirname(__DIR__, 2) . '/Issues/error_log.txt';
        $auditLogPath = defined('AUDIT_LOG_PATH') ? AUDIT_LOG_PATH : dirname(__DIR__, 2) . '/Issues/audit_log.txt';
        
        // Rotate error log
        rotateLogFile($errorLogPath);
        
        // Rotate audit log if it exists
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
    function writeLog($message, $category = 'GENERAL', $level = 'INFO')
    {
        $logLevels = array(
            LOG_LEVEL_DEBUG   => 0,
            LOG_LEVEL_INFO    => 1,
            LOG_LEVEL_WARNING => 2,
            LOG_LEVEL_ERROR   => 3,
            LOG_LEVEL_CRITICAL=> 4
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

        // Ensure the Issues directory exists
        $logDir = dirname(ERROR_LOG_PATH);
        if (!is_dir($logDir))
        {
            mkdir($logDir, 0755, true);
        }

        error_log($logEntry, 3, ERROR_LOG_PATH);

        if ($level === LOG_LEVEL_CRITICAL)
        {
            error_log(sprintf("[CAMPUS-EATS] [CRITICAL] %s", $message));
        }
    }
}

// =============================================================================
// Audit Logging Functions
// =============================================================================

if (!function_exists('logAudit'))
{
    function logAudit($userId, $username, $activityType, $description, $details = array(), $result = 'success')
    {
        $timestamp = date('Y-m-d H:i:s');
        $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown';
        $sessionId = session_id() ?: 'no_session';
        $requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'unknown';

        $detailsJson = !empty($details) ? json_encode($details) : '';

        $logEntry = sprintf(
            "[%s] [AUDIT] [User: %s] [Username: %s] [Activity: %s] [Result: %s] [IP: %s] [Session: %s] [URI: %s] %s %s" . PHP_EOL,
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

        // Ensure the Issues directory exists
        $logDir = dirname(AUDIT_LOG_PATH);
        if (!is_dir($logDir))
        {
            mkdir($logDir, 0755, true);
        }

        error_log($logEntry, 3, AUDIT_LOG_PATH);

        writeLog(
            sprintf("Audit: %s - %s (Result: %s)", $username, $activityType, $result),
            "AUDIT",
            LOG_LEVEL_INFO
        );
    }
}

// =============================================================================
// CORRECTION: Exception Handler - Don't re-throw (CRIT-02)
// =============================================================================

if (!function_exists('handleException'))
{
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

        // CORRECTION: Display error in debug mode, otherwise show generic message
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
                echo "<h1>An error occurred. Please try again later.</h1>";
            }
            else
            {
                echo "<h1>An error occurred. Please try again later.</h1>";
            }
        }
        
        // CORRECTION: Exit cleanly without throwing further
        exit(1);
    }
}

// =============================================================================
// CORRECTION: Error Handler - Log but don't throw (CRIT-02)
// =============================================================================

if (!function_exists('handleError'))
{
    function handleError($errno, $errstr, $errfile, $errline)
    {
        // Respect error_reporting level
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
        
        // CORRECTION: Log but don't throw - let execution continue
        // Only throw for fatal errors
        $fatalErrors = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR);
        if (in_array($errno, $fatalErrors))
        {
            throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
        }
        
        return true;
    }
}

// =============================================================================
// Set Error and Exception Handlers
// =============================================================================

set_error_handler('handleError');
set_exception_handler('handleException');

// =============================================================================
// Shutdown Handler for Fatal Errors
// =============================================================================

if (!function_exists('handleShutdown'))
{
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
// Initial Log Rotation on First Load
// =============================================================================

// Ensure the Issues directory exists
$issuesDir = dirname(ERROR_LOG_PATH);
if (!is_dir($issuesDir))
{
    mkdir($issuesDir, 0755, true);
}

$lastRotationFile = dirname(ERROR_LOG_PATH) . '/.last_rotation';

// Create the last rotation file if it doesn't exist
if (!file_exists($lastRotationFile))
{
    file_put_contents($lastRotationFile, time());
}

// Check if rotation is needed (once per day)
if (file_exists($lastRotationFile))
{
    $lastRotation = (int)file_get_contents($lastRotationFile);
    $nextRotation = $lastRotation + 86400; // 24 hours

    if (time() > $nextRotation)
    {
        // Ensure the function exists before calling it
        if (function_exists('rotateAllLogs'))
        {
            rotateAllLogs();
        }
        file_put_contents($lastRotationFile, time());
    }
}