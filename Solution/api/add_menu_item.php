<?php
/**
 * Add Menu Item API Endpoint
 *
 * Allows a vendor to add a new menu item.
 *
 * CORRECTION (Finding 3.1 in the code review report):
 * The contents of this file and of get_menu_item.php were previously
 * swapped. This file contained the GET-only "fetch a single menu item"
 * logic, and get_menu_item.php contained the POST-only "insert a new
 * menu item" logic. As a result, vendor.js issued a POST to this file
 * and received HTTP 405 "Method not allowed. Use GET.", and the vendor
 * "Add Menu Item" feature was entirely non-functional.
 *
 * The two files have been restored to match their names and their
 * callers in Solution/assets/js/vendor.js:
 *   - This file, add_menu_item.php, accepts POST and inserts a row.
 *   - get_menu_item.php accepts GET and returns a single item.
 *
 * SOURCE: Code review report, Finding 3.1
 * SOURCE: campus-eats-process-document.pdf Section 6.2
 * SOURCE: Solution/assets/js/vendor.js saveMenuItem()
 *
 * @version 6.0
 */

header('Content-Type: application/json');

header(
    'Access-Control-Allow-Origin: ' .
    (
        isset($_SERVER['HTTP_ORIGIN']) &&
        $_SERVER['HTTP_ORIGIN'] === 'https://campuseats.example.com'
        ? $_SERVER['HTTP_ORIGIN']
        : ''
    )
);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

startSecureSession();

if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'message' => 'Authentication required. Please log in.'
    ));
    exit();
}

if (!isVendor())
{
    http_response_code(403);
    echo json_encode(array(
        'success' => false,
        'message' => 'Vendor access required.'
    ));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ));
    exit();
}

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

$csrfToken = isset($_SERVER['HTTP_X_CSRF_TOKEN'])
    ? $_SERVER['HTTP_X_CSRF_TOKEN']
    : (isset($input['csrf_token']) ? $input['csrf_token'] : '');

if (!validateCsrfToken($csrfToken))
{
    $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    $userId = getCurrentUserId() ?: 'unknown';
    writeLog(
        "CSRF validation failed for add menu item. IP: $ipAddress, User: $userId",
        "CSRF"
    );

    http_response_code(403);
    echo json_encode(array(
        'success' => false,
        'message' => 'Security validation failed. Please refresh the page and try again.'
    ));
    exit();
}

$itemName = isset($input['item_name']) ? trim($input['item_name']) : '';
$description = isset($input['description']) ? trim($input['description']) : '';
$price = isset($input['price']) ? (float)$input['price'] : 0.0;
$category = isset($input['category']) ? trim($input['category']) : 'General';
$isAvailable = isset($input['is_available']) ? (int)$input['is_available'] : 1;
$quantityAvailable = isset($input['quantity_available'])
    ? (int)$input['quantity_available']
    : 0;

if (strlen($itemName) < 2 || strlen($itemName) > 100)
{
    echo json_encode(array(
        'success' => false,
        'message' => 'Item name must be between 2 and 100 characters.'
    ));
    exit();
}

if ($price < 0.01)
{
    echo json_encode(array(
        'success' => false,
        'message' => 'Price must be greater than zero.'
    ));
    exit();
}

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

    $vendor = $db->fetchOne(
        "SELECT vendor_id, is_approved
         FROM vendors
         WHERE vendor_user_id = :user_id
         LIMIT 1",
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

    if ((int)$vendor['is_approved'] !== 1)
    {
        echo json_encode(array(
            'success' => false,
            'message' => 'Your vendor account is not approved. '
                       . 'Please contact an administrator.'
        ));
        exit();
    }

    $sql = "INSERT INTO menu_items
                (vendor_id, item_name, description, price,
                 quantity_available, category, is_available)
            VALUES
                (:vendor_id, :item_name, :description, :price,
                 :quantity_available, :category, :is_available)";

    $itemId = $db->insert($sql, array(
        'vendor_id' => $vendor['vendor_id'],
        'item_name' => $itemName,
        'description' => $description,
        'price' => $price,
        'quantity_available' => $quantityAvailable,
        'category' => $category,
        'is_available' => $isAvailable
    ));

    if (!$itemId)
    {
        writeLog(
            "Failed to add menu item for vendor ID {$vendor['vendor_id']}",
            "VENDOR"
        );

        http_response_code(500);
        echo json_encode(array(
            'success' => false,
            'message' => 'Failed to add menu item. Please try again.'
        ));
        exit();
    }

    writeLog(
        "Vendor ID {$vendor['vendor_id']} added menu item: $itemName (ID: $itemId)",
        "VENDOR"
    );

    generateCsrfToken(true);

    echo json_encode(array(
        'success' => true,
        'message' => 'Menu item added successfully.',
        'item_id' => $itemId,
        'csrf_token' => getCsrfToken()
    ));
}
catch (PDOException $e)
{
    writeLog('Add menu item PDO error: ' . $e->getMessage(), "VENDOR_ERROR");
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ));
}
catch (Exception $e)
{
    writeLog('Add menu item error: ' . $e->getMessage(), "VENDOR");
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ));
}