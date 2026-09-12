<?php
/**
 * Application-Wide Constants Configuration File
 *
 * Defines all application-wide constants used throughout the system.
 *
 * CORRECTIONS (Version 24.0 - Single Source of Truth):
 * - Removed DEMO_ACCOUNTS (now loaded from config/demo_accounts.php)
 * - Removed FIREBASE_* (now loaded from config/firebase_config.php)
 * - Fixed CSP_POLICY to match the live CSP and remove unsafe-inline
 * - Added CSP_NONCE generation for inline style and script blocks
 * - Fixed PAYMENT_METHOD_* values to match their names
 * - Fixed SERVICE_FEE_THRESHOLD_HIGH boundary semantics
 *
 * SOURCE: Issue report - items 1, 2, 3, 11, 12, 14, 15, 26
 *
 * @version 24.0
 */

// =============================================================================
// Environment Detection
// =============================================================================

if (!defined('APP_ENV'))
{
    define('APP_ENV', getenv('APP_ENV') ?: 'development');
}

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
    // Connect sources include Google Identity endpoints required by
    // Firebase Authentication signInAnonymously(). Without these, the
    // browser blocks the identity request and all Firebase reads and
    // writes fail.
    //
    // SOURCE: Issue report - items 1, 2, 12, 19
    $connectSources = array(
        "'self'",
        'https://fakerestaurantapi.runasp.net',
        'https://campus-eats-db-default-rtdb.europe-west1.firebasedatabase.app',
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
// API Configuration
// =============================================================================

if (!defined('API_BASE_URL'))
{
    define('API_BASE_URL', getenv('API_BASE_URL') ?: 'https://fakerestaurantapi.runasp.net');
}

if (!defined('API_TIMEOUT'))
{
    define('API_TIMEOUT', 30);
}

if (!defined('API_RETRY_ATTEMPTS'))
{
    define('API_RETRY_ATTEMPTS', 3);
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
    define('BASE_URL', ROOT_URL . '/solution');
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

if (!defined('LOG_LEVEL'))
{
    define('LOG_LEVEL', APP_DEBUG ? 'DEBUG' : 'INFO');
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
    'CSP_POLICY'
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
    $errorMessage = "Missing required constants: " . implode(', ', $missingConstants);
    error_log($errorMessage);

    if (APP_DEBUG)
    {
        die('<h1>Configuration Error</h1><p>' . htmlspecialchars($errorMessage) . '</p>');
    }
    else
    {
        die('System configuration error. Please contact the administrator.');
    }
}