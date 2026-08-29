<?php
/**
 * Application-Wide Constants Configuration File
 *
 * This file defines all application-wide constants used throughout the system.
 *
 * CORRECTIONS (Version 13.0 - API Integration):
 * - Added API_BASE_URL for Fake Restaurant API integration
 * - Added API timeout and retry settings
 * - Fixed APP_DEBUG definition to prevent undefined constant errors
 *
 * @version 13.0
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
// Fake Restaurant API Configuration
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
// Path Constants (File System)
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
// URL Constants (for HTML links and redirects)
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

if (!defined('CSP_POLICY'))
{
    define('CSP_POLICY', "default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data: https://images.unsplash.com https://fakerestaurantapi.runasp.net; connect-src 'self' https://fakerestaurantapi.runasp.net");
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

if (!defined('PAYMENT_METHOD_CAMPUS_WALLET'))
{
    define('PAYMENT_METHOD_CAMPUS_WALLET', 'campus_wallet');
}

if (!defined('PAYMENT_METHOD_CREDIT_CARD'))
{
    define('PAYMENT_METHOD_CREDIT_CARD', 'debit_card');
}

if (!defined('PAYMENT_METHOD_MEAL_PLAN'))
{
    define('PAYMENT_METHOD_MEAL_PLAN', 'coupons');
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
// Demo Accounts - Single Source of Truth
// =============================================================================

if (!defined('DEMO_ACCOUNTS'))
{
    define('DEMO_ACCOUNTS', serialize(array(
        // Admin Users (2) - Fixed User IDs: 1001, 1002
        array(
            'user_id'      => 1001,
            'full_name'    => 'John Doe',
            'username'     => 'johndoe',
            'email'        => 'john.doe@example.com',
            'password'     => 'AdminPass123!',
            'account_type' => 'admin',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        array(
            'user_id'      => 1002,
            'full_name'    => 'Sarah Wilson',
            'username'     => 'sarahw',
            'email'        => 'sarah.wilson@example.com',
            'password'     => 'AdminPass987%',
            'account_type' => 'admin',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        // Vendor Users (2) - Fixed User IDs: 2001, 2002
        array(
            'user_id'      => 2001,
            'full_name'    => 'Jane Smith',
            'username'     => 'janesmith',
            'email'        => 'jane.smith@example.com',
            'password'     => 'VendorPass456$',
            'account_type' => 'vendor',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => 'Jane\'s Bakery',
            'description'  => 'Fresh baked goods, pastries, and artisanal breads.'
        ),
        array(
            'user_id'      => 2002,
            'full_name'    => 'Michael Brown',
            'username'     => 'mikeb',
            'email'        => 'mike.brown@example.com',
            'password'     => 'VendorPass789#',
            'account_type' => 'vendor',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => 'Brown\'s Deli',
            'description'  => 'Sandwiches, wraps, and daily specials.'
        ),
        // Standard Users (2) - Fixed User IDs: 3001, 3002
        array(
            'user_id'      => 3001,
            'full_name'    => 'Emily Davis',
            'username'     => 'emilyd',
            'email'        => 'emily.davis@example.com',
            'password'     => 'StandardPass321@',
            'account_type' => 'standard',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        array(
            'user_id'      => 3002,
            'full_name'    => 'Robert Taylor',
            'username'     => 'robertt',
            'email'        => 'robert.taylor@example.com',
            'password'     => 'StandardPass654#',
            'account_type' => 'standard',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        // Student Users (2) - Fixed User IDs: 4001, 4002
        array(
            'user_id'      => 4001,
            'full_name'    => 'David Lee',
            'username'     => 'davidl',
            'email'        => 'david.lee@example.com',
            'password'     => 'StudentPass234$',
            'account_type' => 'student',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        array(
            'user_id'      => 4002,
            'full_name'    => 'Maria Garcia',
            'username'     => 'mariag',
            'email'        => 'maria.garcia@example.com',
            'password'     => 'StudentPass567%',
            'account_type' => 'student',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        )
    )));
}

// =============================================================================
// Error Logging Configuration
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
    'API_BASE_URL'
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