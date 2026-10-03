<?php
/**
 * Application-Wide Constants Configuration File
 *
 * Defines all application-wide constants used throughout the system.
 *
 * CORRECTIONS (Version 25.0 - Technical Audit and Fixes Report):
 * - Added ALLOWED_CORS_ORIGIN. The constant is read from the environment
 *   and falls back to a safe default. The API endpoints read this
 *   constant to build the Access-Control-Allow-Origin header. The
 *   previous approach hard-coded the origin in each endpoint, which
 *   meant development hosts could not fetch the Firebase configuration.
 *
 * CORRECTIONS (Version 24.0 - Single Source of Truth):
 * - Removed DEMO_ACCOUNTS (now loaded from config/demo_accounts.php).
 * - Removed FIREBASE_* (now loaded from config/firebase_config.php).
 * - Fixed CSP_POLICY to match the live CSP and remove unsafe-inline.
 * - Added CSP_NONCE generation for inline style and script blocks.
 * - Fixed PAYMENT_METHOD_* values to match their names.
 * - Fixed SERVICE_FEE_THRESHOLD_HIGH boundary semantics.
 *
 * SOURCE: Technical Audit and Fixes Report.
 * SOURCE: Issue report items 1, 2, 3, 11, 12, 14, 15, 26.
 * SOURCE: Campus Eats Technical Audit Report, Section 11.
 *
 * @version 25.0
 */

// =============================================================================
// Environment Detection
// =============================================================================
//
// APP_ENV identifies the deployment environment. The value is read from
// the environment and falls back to "development" when unset. The value
// is not used to relax any security control. It is recorded in logs so
// an operator can distinguish a development log entry from a production
// entry.
// =============================================================================

if (!defined('APP_ENV'))
{
    define('APP_ENV', getenv('APP_ENV') ?: 'development');
}

// =============================================================================
// Debug Flag
// =============================================================================
//
// APP_DEBUG controls how much detail is exposed when an uncaught
// exception reaches handleException(). When the flag is true, the
// browser receives the exception message, file, line, and stack trace.
// When the flag is false, the browser receives a generic message and
// the details go only to the log.
//
// Resolution order:
//   1. The APP_DEBUG environment variable, if set.
//   2. A SERVER_NAME heuristic that treats common development hostnames
//      as debug environments.
//   3. A safe default of false.
//
// The heuristic is a convenience. It does not relax any other control.
// An operator can override the value by setting APP_DEBUG on the host.
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
// CSP Nonce Generation
// =============================================================================
//
// The Content Security Policy uses a nonce to permit specific inline
// style and script blocks. The nonce is a random value generated once
// per request and included in the CSP header and in the nonce attribute
// of each permitted inline block.
//
// The generation order is:
//   1. random_bytes, when available.
//   2. openssl_random_pseudo_bytes, when random_bytes is not available.
//   3. A hash of a unique identifier, as a last resort.
//
// The nonce is base64-encoded so it contains only characters that are
// valid in a CSP nonce attribute.
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
        // The hash produces 32 raw bytes. The first 24 base64 characters
        // are used so the length is comparable to the other branches.
        $nonce = base64_encode(hash('sha256', uniqid('csp', true), true));
        $nonce = substr($nonce, 0, 24);
    }

    define('CSP_NONCE', $nonce);
}

// =============================================================================
// Content Security Policy
// =============================================================================
//
// The CSP is defined once here and consumed by setSecurityHeaders() in
// Solution/includes/auth.php. The policy is not modified by any other
// file.
//
// The connect-src directive lists every host that the browser is
// permitted to contact through fetch, XMLHttpRequest, or WebSocket.
// The list includes:
//
//   - 'self' for the same origin.
//   - https://fakerestaurantapi.runasp.net for the restaurant catalogue.
//   - The Firebase Realtime Database host for reads and writes.
//   - The Google Identity endpoints that the Firebase Authentication
//     SDK contacts when it signs a user in anonymously.
//
// The script-src directive lists every host that the browser is
// permitted to load JavaScript from. The list includes 'self', the
// Font Awesome CDN, the Google static content host used by the Firebase
// SDK, and the request nonce for any inline script block.
//
// The style-src directive lists every host that the browser is
// permitted to load stylesheets from. The list includes 'self', the
// Font Awesome CDN, and the request nonce for inline style blocks.
//
// unsafe-inline and unsafe-eval are deliberately absent. Their absence
// is the reason every inline block carries the nonce.
//
// SOURCE: Issue report items 1, 2, 12, 19.
// SOURCE: Technical Audit and Fixes Report.
// =============================================================================

if (!defined('CSP_POLICY'))
{
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
// CORS Configuration
// =============================================================================
//
// ALLOWED_CORS_ORIGIN is the single origin that the API endpoints
// reflect in the Access-Control-Allow-Origin header. Each deployment
// sets this value to its own origin.
//
// The value is read from the environment so the same code can be
// deployed to development, staging, and production hosts without
// modification. The default is the local development origin.
//
// When the value is an empty string, the API endpoints omit the header
// and the browser applies its default same-origin policy. This is the
// correct behaviour for a deployment that does not accept cross-origin
// requests.
//
// The header is only emitted when the request Origin matches the
// configured value exactly. A request from a different origin receives
// no Access-Control-Allow-Origin header, and the browser blocks the
// response.
//
// SOURCE: Technical Audit and Fixes Report.
// SOURCE: Campus Eats Technical Audit Report, Sections 1 and 11.
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
//
// API_BASE_URL is the base URL of the Fake Restaurant API. The value is
// read from the environment so the same code can target a staging API
// without modification.
//
// API_TIMEOUT is the per-request timeout in seconds.
// API_RETRY_ATTEMPTS is the number of attempts for a transient failure.
// API_RETRY_DELAY is the base delay between retries in seconds.
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
//
// BASE_PATH is the Solution directory.
// ROOT_PATH is the repository root, one level above Solution.
// MODULES_PATH, INCLUDES_PATH, ASSETS_PATH, CONFIG_PATH, and SQL_PATH
// are the subdirectories of BASE_PATH used by the application.
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
//
// ROOT_URL is the URL path to the repository root, relative to the web
// server document root. The value is read from the environment so the
// application can be deployed under a subdirectory without modification.
//
// BASE_URL is the URL path to the Solution directory.
// ASSETS_URL is the URL path to the assets directory.
// API_URL is the URL path to the api directory.
//
// The values are normalised so they always begin with a single slash
// and never end with a slash.
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
//
// SESSION_NAME is the name of the session cookie. The name is a
// non-default value so the cookie cannot be confused with a session
// cookie from another application on the same host.
//
// SESSION_LIFETIME is the maximum session age in seconds. The value is
// used by isLoggedIn() to reject a session that has exceeded its
// lifetime.
//
// SESSION_REGEN_INTERVAL is the interval in seconds at which the
// session identifier is regenerated. Regeneration limits the window
// during which a stolen identifier is usable.
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
//
// BCRYPT_COST is the cost factor passed to password_hash(). The value
// is used by hashPassword() in Solution/includes/password_validation.php.
// A higher cost increases the time required to compute a hash and
// therefore the time required to attempt a password guess.
// =============================================================================

if (!defined('BCRYPT_COST'))
{
    define('BCRYPT_COST', 12);
}

// =============================================================================
// Order Status Constants
// =============================================================================
//
// The order status values are the strings stored in the order_status
// column of the orders table. The same strings are used in the
// application code and in the API responses. The strings are lowercase.
//
// The values are not modified by the Firebase Realtime Database rules.
// The rules for the orders node require the status field to be a
// string. They do not constrain the value further, so the lowercase
// strings used by MySQL are valid.
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
//
// The payment method values are the strings stored in the
// payment_method column of the payments table. The values are lowercase
// and use underscores as separators.
//
// ALLOWED_PAYMENT_METHODS_ARRAY is a serialised array of the permitted
// values. The array is consumed by the checkout page and by the
// process_payment endpoint. The serialised form is used because
// constants cannot hold an array in PHP versions earlier than 7.0. The
// application supports those versions, so the serialised form is the
// portable choice.
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
//
// The payment status values are the strings stored in the
// payment_status column of the payments table. The values are lowercase.
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
//
// The service fee rules from the process document Section 10.1:
//
//   - A subtotal below R500 is charged a 10 percent service fee.
//   - A subtotal from R500 up to but not including R1000 is charged a
//     6.5 percent service fee.
//   - A subtotal of R1000 or more is charged no service fee.
//
// SERVICE_FEE_THRESHOLD_LOW is the lower bound of the middle tier. A
// subtotal strictly below this value is charged the low rate.
//
// SERVICE_FEE_THRESHOLD_HIGH is the lower bound of the top tier. A
// subtotal strictly below this value is charged the middle rate. A
// subtotal at or above this value is charged no fee. The boundary is
// therefore exclusive at the high end, which matches the process
// document wording "1,000 rand and above".
//
// SERVICE_FEE_RATE_LOW and SERVICE_FEE_RATE_MID are the two rates.
//
// TAX_RATE is 20 percent, applied to the subtotal plus the service fee.
//
// ROUNDING_MULTIPLE is 5. The total after tax is rounded up to the
// nearest multiple of this value.
//
// STUDENT_DISCOUNT_RATE is 2.5 percent, applied to the Student role.
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
//
// The database connection parameters are read from the environment so
// the same code can target a development database and a production
// database without modification. The defaults are suitable for a local
// WampServer installation.
//
// DB_CHARSET is utf8mb4, which is the character set that supports the
// full range of Unicode characters including emoji. The collation used
// by the installer is utf8mb4_unicode_ci.
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
//
// ERROR_LOG_PATH is the path to the general application log file.
// AUDIT_LOG_PATH is the path to the audit log file. Both files live
// under the repository's Issues directory, which the .htaccess file
// denies to remote requests.
//
// The paths are defined here so that error_logging.php can read them
// before it defines its own fallback values. When the constants are
// already defined, the fallback values in error_logging.php are not
// used.
//
// LOG_LEVEL is the minimum level that is written to the log. A DEBUG
// message is dropped in production. An INFO message and above are
// written.
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
//
// FIREBASE_SYNC_PATH is the root path under which the client-side
// synchronization worker writes a lightweight projection of the
// current user's state. The path is defined here so that both the
// server-side FirebaseWriter and the client-side firebase.js use the
// same value.
//
// The existing Firebase Realtime Database rules define a sync node
// whose children are keyed by Firebase UID. The path used by the
// application is therefore "sync/{firebaseUid}".
//
// SOURCE: Technical Audit and Fixes Report, Section 3.3.
// =============================================================================

if (!defined('FIREBASE_SYNC_PATH'))
{
    define('FIREBASE_SYNC_PATH', 'sync');
}

// =============================================================================
// Required Constants Validation
// =============================================================================
//
// The list below names the constants that must be defined for the
// application to operate. When a constant is missing, the file logs the
// name and halts. Halting is preferable to proceeding with an undefined
// constant, which would produce a confusing error later in the request.
//
// The check runs at the bottom of the file so that the constants it
// validates have been defined above.
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