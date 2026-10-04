<?php
/**
 * Application-Wide Constants Configuration File
 *
 * Defines all application-wide constants used throughout the system.
 *
 * CORRECTIONS (Version 26.0 - REPORT.txt Alignment):
 * - Added the resilience helper include. The helper registers a
 *   global exception handler that converts an uncaught error into a
 *   JSON 503 for an API request and a friendly retrying page for a
 *   page request.
 * - Added the ALLOWED_CORS_ORIGIN constant. The value is read from the
 *   environment with a safe default. It is used by the API endpoints
 *   to build the Access-Control-Allow-Origin header.
 * - Added the FIREBASE_SYNC_PATH constant. The value is used by the
 *   client-side synchronization worker and by the server-side
 *   projection writer.
 * - Retained every constant from Version 25.0.
 *
 * SOURCE: REPORT.txt, Robust Error Handling and Database Fault Tolerance.
 * SOURCE: Technical Audit and Fixes Report.
 *
 * @version 26.0
 */

// =============================================================================
// Environment Detection
// =============================================================================

if (!defined('APP_ENV'))
{
    define('APP_ENV', getenv('APP_ENV') ?: 'development');
}

// =============================================================================
// Debug Flag
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
        $serverName = isset($_SERVER['SERVER_NAME'])
            ? $_SERVER['SERVER_NAME']
            : '';

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
// CSP Nonce Generation
// =============================================================================

if (!defined('CSP_NONCE'))
{
    $nonce = '';

    if (function_exists('random_bytes'))
    {
        $nonce = base64_encode(random_bytes(16));
    }
    elseif (function_exists('openssl_random_pseudo_bytes'))
    {
        $nonce = base64_encode(openssl_random_pseudo_bytes(16));
    }
    else
    {
        $nonce = base64_encode(hash('sha256', uniqid('csp', true), true));
        $nonce = substr($nonce, 0, 24);
    }

    define('CSP_NONCE', $nonce);
}

// =============================================================================
// Content Security Policy
// =============================================================================

if (!defined('CSP_POLICY'))
{
    $connectSources = array(
        "'self'",
        'https://fakerestaurantapi.runasp.net',
        'https://campus-eats-db-default-rtdb.europe-west1.firebasedatabase.app',
        'wss://campus-eats-db-default-rtdb.europe-west1.firebasedatabase.app',
        'https://identitytoolkit.googleapis.com',
        'https://securetoken.googleapis.com',
        'https://www.googleapis.com',
        'https://firebaseinstallations.googleapis.com'
    );

    $scriptSources = array(
        "'self'",
        'https://cdnjs.cloudflare.com',
        'https://www.gstatic.com',
        "'nonce-" . CSP_NONCE . "'"
    );

    $styleSources = array(
        "'self'",
        'https://cdnjs.cloudflare.com',
        "'nonce-" . CSP_NONCE . "'"
    );

    $fontSources = array(
        "'self'",
        'https://cdnjs.cloudflare.com',
        'data:'
    );

    $imgSources = array(
        "'self'",
        'data:',
        'https://images.unsplash.com',
        'https://fakerestaurantapi.runasp.net'
    );

    $policy = "default-src 'self'; "
            . "script-src " . implode(' ', $scriptSources) . "; "
            . "style-src " . implode(' ', $styleSources) . "; "
            . "font-src " . implode(' ', $fontSources) . "; "
            . "img-src " . implode(' ', $imgSources) . "; "
            . "connect-src " . implode(' ', $connectSources) . "; "
            . "frame-ancestors 'none'; "
            . "base-uri 'self'; "
            . "form-action 'self'";

    define('CSP_POLICY', $policy);
}

// =============================================================================
// CORS Configuration
// =============================================================================

if (!defined('ALLOWED_CORS_ORIGIN'))
{
    define(
        'ALLOWED_CORS_ORIGIN',
        getenv('ALLOWED_CORS_ORIGIN') ?: 'http://localhost'
    );
}

// =============================================================================
// API Configuration
// =============================================================================

if (!defined('API_BASE_URL'))
{
    define(
        'API_BASE_URL',
        getenv('API_BASE_URL') ?: 'https://fakerestaurantapi.runasp.net'
    );
}

if (!defined('API_TIMEOUT'))
{
    define('API_TIMEOUT', 10);
}

if (!defined('API_RETRY_ATTEMPTS'))
{
    define('API_RETRY_ATTEMPTS', 2);
}

if (!defined('API_RETRY_DELAY'))
{
    define('API_RETRY_DELAY', 1);
}

// =============================================================================
// Path Constants
// =============================================================================

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

if (!defined('ROOT_PATH'))
{
    define('ROOT_PATH', dirname(__DIR__, 2));
}

if (!defined('MODULES_PATH'))
{
    define('MODULES_PATH', BASE_PATH . '/modules');
}

if (!defined('INCLUDES_PATH'))
{
    define('INCLUDES_PATH', BASE_PATH . '/includes');
}

if (!defined('ASSETS_PATH'))
{
    define('ASSETS_PATH', BASE_PATH . '/assets');
}

if (!defined('CONFIG_PATH'))
{
    define('CONFIG_PATH', BASE_PATH . '/config');
}

if (!defined('SQL_PATH'))
{
    define('SQL_PATH', BASE_PATH . '/sql');
}

// =============================================================================
// URL Constants
// =============================================================================

if (!defined('ROOT_URL'))
{
    $rootUrl = getenv('ROOT_URL') ?: '/campus-eats-web';
    $rootUrl = '/' . trim($rootUrl, '/');

    if (strlen($rootUrl) > 1 && substr($rootUrl, -1) === '/')
    {
        $rootUrl = rtrim($rootUrl, '/');
    }

    define('ROOT_URL', $rootUrl);
}

if (!defined('BASE_URL'))
{
    define('BASE_URL', ROOT_URL . '/Solution');
}

if (!defined('ASSETS_URL'))
{
    define('ASSETS_URL', BASE_URL . '/assets');
}

if (!defined('API_URL'))
{
    define('API_URL', BASE_URL . '/api');
}

// =============================================================================
// Session Configuration
// =============================================================================

if (!defined('SESSION_NAME'))
{
    define('SESSION_NAME', 'campus_eats_session');
}

if (!defined('SESSION_LIFETIME'))
{
    define('SESSION_LIFETIME', 7200);
}

if (!defined('SESSION_REGEN_INTERVAL'))
{
    define('SESSION_REGEN_INTERVAL', 1800);
}

// =============================================================================
// Security Configuration
// =============================================================================

if (!defined('BCRYPT_COST'))
{
    define('BCRYPT_COST', 12);
}

// =============================================================================
// Order Status Constants
// =============================================================================

if (!defined('ORDER_STATUS_PENDING'))
{
    define('ORDER_STATUS_PENDING', 'pending');
}

if (!defined('ORDER_STATUS_ACCEPTED'))
{
    define('ORDER_STATUS_ACCEPTED', 'accepted');
}

if (!defined('ORDER_STATUS_PREPARING'))
{
    define('ORDER_STATUS_PREPARING', 'preparing');
}

if (!defined('ORDER_STATUS_READY'))
{
    define('ORDER_STATUS_READY', 'ready');
}

if (!defined('ORDER_STATUS_COMPLETED'))
{
    define('ORDER_STATUS_COMPLETED', 'completed');
}

if (!defined('ORDER_STATUS_CANCELLED'))
{
    define('ORDER_STATUS_CANCELLED', 'cancelled');
}

// =============================================================================
// Payment Method Constants
// =============================================================================

if (!defined('PAYMENT_METHOD_DEBIT_CARD'))
{
    define('PAYMENT_METHOD_DEBIT_CARD', 'debit_card');
}

if (!defined('PAYMENT_METHOD_CAMPUS_WALLET'))
{
    define('PAYMENT_METHOD_CAMPUS_WALLET', 'campus_wallet');
}

if (!defined('PAYMENT_METHOD_COUPONS'))
{
    define('PAYMENT_METHOD_COUPONS', 'coupons');
}

if (!defined('ALLOWED_PAYMENT_METHODS_ARRAY'))
{
    define(
        'ALLOWED_PAYMENT_METHODS_ARRAY',
        serialize(
            array(
                PAYMENT_METHOD_DEBIT_CARD,
                PAYMENT_METHOD_CAMPUS_WALLET,
                PAYMENT_METHOD_COUPONS
            )
        )
    );
}

// =============================================================================
// Payment Status Constants
// =============================================================================

if (!defined('PAYMENT_STATUS_PENDING'))
{
    define('PAYMENT_STATUS_PENDING', 'pending');
}

if (!defined('PAYMENT_STATUS_COMPLETED'))
{
    define('PAYMENT_STATUS_COMPLETED', 'completed');
}

if (!defined('PAYMENT_STATUS_FAILED'))
{
    define('PAYMENT_STATUS_FAILED', 'failed');
}

if (!defined('PAYMENT_STATUS_REFUNDED'))
{
    define('PAYMENT_STATUS_REFUNDED', 'refunded');
}

// =============================================================================
// Financial Calculation Constants
// =============================================================================

if (!defined('SERVICE_FEE_THRESHOLD_LOW'))
{
    define('SERVICE_FEE_THRESHOLD_LOW', 500);
}

if (!defined('SERVICE_FEE_THRESHOLD_HIGH'))
{
    define('SERVICE_FEE_THRESHOLD_HIGH', 1000);
}

if (!defined('SERVICE_FEE_RATE_LOW'))
{
    define('SERVICE_FEE_RATE_LOW', 0.10);
}

if (!defined('SERVICE_FEE_RATE_MID'))
{
    define('SERVICE_FEE_RATE_MID', 0.065);
}

if (!defined('TAX_RATE'))
{
    define('TAX_RATE', 0.20);
}

if (!defined('ROUNDING_MULTIPLE'))
{
    define('ROUNDING_MULTIPLE', 5);
}

if (!defined('STUDENT_DISCOUNT_RATE'))
{
    define('STUDENT_DISCOUNT_RATE', 0.025);
}

// =============================================================================
// Database Configuration
// =============================================================================

if (!defined('DB_HOST'))
{
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}

if (!defined('DB_NAME'))
{
    define('DB_NAME', getenv('DB_NAME') ?: 'campus_eats');
}

if (!defined('DB_USER'))
{
    define('DB_USER', getenv('DB_USER') ?: 'root');
}

if (!defined('DB_PASS'))
{
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

// =============================================================================
// Error Logging
// =============================================================================

if (!defined('ERROR_LOG_PATH'))
{
    define('ERROR_LOG_PATH', ROOT_PATH . '/Issues/error_log.txt');
}

if (!defined('AUDIT_LOG_PATH'))
{
    define('AUDIT_LOG_PATH', ROOT_PATH . '/Issues/audit_log.txt');
}

if (!defined('LOG_LEVEL'))
{
    define('LOG_LEVEL', APP_DEBUG ? 'DEBUG' : 'INFO');
}

// =============================================================================
// Firebase Realtime Database Paths
// =============================================================================

if (!defined('FIREBASE_SYNC_PATH'))
{
    define('FIREBASE_SYNC_PATH', 'sync');
}

// =============================================================================
// Resilience Helper
// =============================================================================
//
// The resilience helper registers a global exception handler that
// converts an uncaught error into a JSON 503 for an API request and a
// friendly retrying page for a page request. The helper is loaded here
// so that every entry point that includes constants.php is covered.
//
// When the helper is not present, the default handler in
// error_logging.php remains in effect. That handler produces a 500
// response and logs the error. The application remains functional.
//
// SOURCE: REPORT.txt, Robust Error Handling.
// =============================================================================

if (!defined('CAMPUS_EATS_RESILIENCE_LOADED'))
{
    define('CAMPUS_EATS_RESILIENCE_LOADED', true);

    $resiliencePath = __DIR__ . '/../includes/resilience.php';

    if (file_exists($resiliencePath))
    {
        require_once $resiliencePath;
    }
}

// =============================================================================
// Required Constants Validation
// =============================================================================

$requiredConstants = array(
    'BASE_PATH',
    'ROOT_PATH',
    'ROOT_URL',
    'BASE_URL',
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_CHARSET',
    'SESSION_NAME',
    'SESSION_LIFETIME',
    'ORDER_STATUS_PENDING',
    'PAYMENT_STATUS_PENDING',
    'BCRYPT_COST',
    'API_BASE_URL',
    'CSP_NONCE',
    'CSP_POLICY',
    'ALLOWED_CORS_ORIGIN'
);

$missingConstants = array();

foreach ($requiredConstants as $constant)
{
    if (!defined($constant))
    {
        $missingConstants[] = $constant;
    }
}

if (!empty($missingConstants))
{
    $errorMessage = "Missing required constants: "
        . implode(', ', $missingConstants);
    error_log($errorMessage);

    if (APP_DEBUG)
    {
        die(
            '<h1>Configuration Error</h1><p>'
                . htmlspecialchars($errorMessage)
                . '</p>'
        );
    }
    else
    {
        die(
            'System configuration error. '
                . 'Please contact the administrator.'
        );
    }
}