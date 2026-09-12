<?php
/**
 * Update Menu Item API Endpoint (Corrected)
 *
 * This endpoint allows vendors to update existing menu items.
 *
 * CORRECTIONS:
 * - Added CSRF token validation
 * - Added ownership verification
 * - Added input validation for all fields
 * - Added vendor status validation
 * - Added stock validation
 * - Improved error handling
 *
 * SOURCE: campus-eats-process-document.pdf (Page 11, Section 6.2 - Update existing menu items)
 *
 * @version 4.0
 */

// Set JSON content type before any output
header('Content-Type: application/json');

// Set CORS headers for API access
header
(
    'Access-Control-Allow-Origin: ' .
    (
        isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === 'https://campuseats.example.com'
        ? $_SERVER['HTTP_ORIGIN']
        : ''
    )
);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
    http_response_code(200);
    exit();
}

// Load required dependencies - FIXED: Using auth.php directly instead of session.php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php';  // Changed from session.php to auth.php
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

// Start secure session
startSecureSession();

// Verify user is authenticated
if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'message' => 'Authentication required. Please log in.'
    ));
    exit();
}

// Verify user has vendor privileges
if (!isVendor())
{
    http_response_code(403);
    echo json_encode(array(
        'success' => false,
        'message' => 'Vendor access required.'
    ));
    exit();
}

// Verify HTTP method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ));
    exit();
}

// Parse and validate JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !is_array($input))
{
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid request data. Please provide valid JSON.'
    ));
    exit();
}

// Validate CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';

if (!validateCsrfToken($csrfToken))
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userId = getCurrentUserId() ?? 'unknown';
    writeLog("CSRF validation failed for update menu item. IP: $ipAddress, User: $userId", "CSRF");

    http_response_code(403);
    echo json_encode
    (
        array
        (
            'success' => false,
            'message' => 'Security validation failed. Please refresh the page and try again.'
        )
    );
    exit();
}

// Validate required fields
if (empty($input['item_id']) || empty($input['item_name']) || !isset($input['price']) || $input['price'] <= 0)
{
    echo json_encode(array(
        'success' => false,
        'message' => 'Item ID, item name, and valid price are required.'
    ));
    exit();
}

// Sanitize and validate input
$itemId = (int)$input['item_id'];
$itemName = trim($input['item_name']);
$description = isset($input['description']) ? trim($input['description']) : '';
$price = (float)$input['price'];
$category = isset($input['category']) ? trim($input['category']) : 'General';
$isAvailable = isset($input['is_available']) ? (int)$input['is_available'] : 1;
$quantityAvailable = isset($input['quantity_available']) ? (int)$input['quantity_available'] : 0;

// Validate item ID
if ($itemId <= 0)
{
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid item ID.'
    ));
    exit();
}

// Validate item name
if (strlen($itemName) < 2 || strlen($itemName) > 100)
{
    echo json_encode(array(
        'success' => false,
        'message' => 'Item name must be between 2 and 100 characters.'
    ));
    exit();
}

// Validate price
if ($price < 0.01)
{
    echo json_encode(array(
        'success' => false,
        'message' => 'Price must be greater than zero.'
    ));
    exit();
}

// Validate quantity
if ($quantityAvailable < 0)
{
    echo json_encode(array(
        'success' => false,
        'message' => 'Quantity available cannot be negative.'
    ));
    exit();
}

try
{
    $db = getDB();
    $userId = getCurrentUserId();

    // Get vendor information
    $vendor = $db->fetchOne
    (
        "SELECT vendor_id, is_open, is_approved FROM vendors WHERE vendor_user_id = :user_id",
        array('user_id' => $userId)
    );

    if (!$vendor)
    {
        echo json_encode(array(
            'success' => false,
            'message' => 'Vendor profile not found.'
        ));
        exit();
    }

    // Check if vendor is approved
    if ($vendor['is_approved'] != 1)
    {
        echo json_encode(array(
            'success' => false,
            'message' => 'Your vendor account is not approved. Please contact an administrator.'
        ));
        exit();
    }

    // Verify the menu item belongs to this vendor
    $verify = $db->fetchOne
    (
        "SELECT item_id, is_available FROM menu_items WHERE item_id = :item_id AND vendor_id = :vendor_id",
        array(
            'item_id' => $itemId,
            'vendor_id' => $vendor['vendor_id']
        )
    );

    if (!$verify)
    {
        echo json_encode(array(
            'success' => false,
            'message' => 'Menu item not found or access denied.'
        ));
        exit();
    }

    // Update the menu item
    $sql = "UPDATE menu_items
            SET item_name = :item_name,
                description = :description,
                price = :price,
                category = :category,
                is_available = :is_available,
                quantity_available = :quantity_available,
                updated_at = NOW()
            WHERE item_id = :item_id AND vendor_id = :vendor_id";

    $db->executeQuery
    (
        $sql,
        array
        (
            'item_name' => $itemName,
            'description' => $description,
            'price' => $price,
            'category' => $category,
            'is_available' => $isAvailable,
            'quantity_available' => $quantityAvailable,
            'item_id' => $itemId,
            'vendor_id' => $vendor['vendor_id']
        )
    );

    writeLog("Vendor ID {$vendor['vendor_id']} updated menu item ID: $itemId", "VENDOR");

    // Generate new CSRF token for subsequent requests
    generateCsrfToken(true);

    echo json_encode(array(
        'success' => true,
        'message' => 'Menu item updated successfully.',
        'item_id' => $itemId
    ));
}
catch (PDOException $e)
{
    writeLog("Update menu item PDO error: " . $e->getMessage(), "VENDOR_ERROR");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ));
}
catch (Exception $e)
{
    writeLog("Update menu item error: " . $e->getMessage(), "VENDOR");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ));
}
?>