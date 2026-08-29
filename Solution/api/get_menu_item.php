<?php
/**
 * Add Menu Item API Endpoint (Corrected)
 *
 * This endpoint allows vendors to add new menu items.
 *
 * CORRECTIONS (Version 5.0 - Functional Implementation):
 * - Corrected the file to perform its namesake function: adding menu items.
 * - Removed the mislabelled GET logic.
 * - Added CSRF token validation.
 * - Added ownership and vendor status verification.
 * - Added input validation for all fields.
 * - Added stock validation.
 * - Improved error handling and logging.
 *
 * SOURCE: campus-eats-process-document.pdf (Page 11, Section 6.2 - Add new items on the menu)
 * SOURCE: Full Code Review Report - Section 2.1
 *
 * @version 5.0
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

// Load required dependencies
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php';
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
    writeLog("CSRF validation failed for add menu item. IP: $ipAddress, User: $userId", "CSRF");

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
if (empty($input['item_name']) || !isset($input['price']) || $input['price'] <= 0)
{
    echo json_encode(array(
        'success' => false,
        'message' => 'Item name and valid price are required.'
    ));
    exit();
}

// Sanitize and validate input
$itemName = trim($input['item_name']);
$description = isset($input['description']) ? trim($input['description']) : '';
$price = (float)$input['price'];
$category = isset($input['category']) ? trim($input['category']) : 'General';
$isAvailable = isset($input['is_available']) ? (int)$input['is_available'] : 1;
$quantityAvailable = isset($input['quantity_available']) ? (int)$input['quantity_available'] : 0;

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

    // Insert the new menu item
    $sql = "INSERT INTO menu_items
            (vendor_id, item_name, description, price, quantity_available, category, is_available)
            VALUES
            (:vendor_id, :item_name, :description, :price, :quantity_available, :category, :is_available)";

    $itemId = $db->insert
    (
        $sql,
        array
        (
            'vendor_id' => $vendor['vendor_id'],
            'item_name' => $itemName,
            'description' => $description,
            'price' => $price,
            'quantity_available' => $quantityAvailable,
            'category' => $category,
            'is_available' => $isAvailable
        )
    );

    if ($itemId)
    {
        writeLog("Vendor ID {$vendor['vendor_id']} added menu item: $itemName (ID: $itemId)", "VENDOR");

        // Generate new CSRF token for subsequent requests
        generateCsrfToken(true);

        echo json_encode(array(
            'success' => true,
            'message' => 'Menu item added successfully.',
            'item_id' => $itemId
        ));
    }
    else
    {
        writeLog("Failed to add menu item for vendor ID {$vendor['vendor_id']}", "VENDOR");
        http_response_code(500);
        echo json_encode(array(
            'success' => false,
            'message' => 'Failed to add menu item. Please try again.'
        ));
    }
}
catch (PDOException $e)
{
    writeLog("Add menu item PDO error: " . $e->getMessage(), "VENDOR_ERROR");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ));
}
catch (Exception $e)
{
    writeLog("Add menu item error: " . $e->getMessage(), "VENDOR");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ));
}
?>