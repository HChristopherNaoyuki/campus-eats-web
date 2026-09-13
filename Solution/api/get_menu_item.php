<?php
/**
 * Get Single Menu Item API Endpoint
 *
 * Returns a single menu item for editing. Only the vendor that owns the
 * item may read it.
 *
 * CORRECTION (Finding 3.1 in the code review report):
 * The contents of this file and of add_menu_item.php were previously
 * swapped. This file contained the POST-only "insert a new menu item"
 * logic, and add_menu_item.php contained the GET-only "fetch a single
 * menu item" logic. As a result, vendor.js issued a GET to this file
 * and received HTTP 405 "Method not allowed. Use POST.", and the
 * "Edit Menu Item" modal was entirely non-functional.
 *
 * The two files have been restored to match their names and their
 * callers in Solution/assets/js/vendor.js:
 *   - This file, get_menu_item.php, accepts GET and returns one item.
 *   - add_menu_item.php accepts POST and inserts a row.
 *
 * SOURCE: Code review report, Finding 3.1
 * SOURCE: campus-eats-process-document.pdf Section 6.2
 * SOURCE: Solution/assets/js/vendor.js openEditItemModal()
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
header('Access-Control-Allow-Methods: GET, OPTIONS');
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET')
{
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ));
    exit();
}

if (empty($_GET['item_id']))
{
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'Item ID is required.'
    ));
    exit();
}

try
{
    $db = getDB();
    $userId = getCurrentUserId();
    $itemId = (int)$_GET['item_id'];

    if ($itemId <= 0)
    {
        http_response_code(400);
        echo json_encode(array(
            'success' => false,
            'message' => 'Invalid item ID.'
        ));
        exit();
    }

    $vendor = $db->fetchOne(
        "SELECT vendor_id
         FROM vendors
         WHERE vendor_user_id = :user_id AND is_approved = 1
         LIMIT 1",
        array('user_id' => $userId)
    );

    if (!$vendor)
    {
        echo json_encode(array(
            'success' => false,
            'message' => 'Vendor profile not found or not approved.'
        ));
        exit();
    }

    $menuItem = $db->fetchOne(
        "SELECT item_id, item_name, description, price, category,
                is_available, quantity_available
         FROM menu_items
         WHERE item_id = :item_id AND vendor_id = :vendor_id
         LIMIT 1",
        array(
            'item_id' => $itemId,
            'vendor_id' => $vendor['vendor_id']
        )
    );

    if (!$menuItem)
    {
        http_response_code(404);
        echo json_encode(array(
            'success' => false,
            'message' => 'Menu item not found or access denied.'
        ));
        exit();
    }

    echo json_encode(array(
        'success' => true,
        'menu_item' => $menuItem
    ));
}
catch (PDOException $e)
{
    writeLog('Get menu item PDO error: ' . $e->getMessage(), "VENDOR_ERROR");
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ));
}
catch (Exception $e)
{
    writeLog('Get menu item error: ' . $e->getMessage(), "VENDOR");
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ));
}