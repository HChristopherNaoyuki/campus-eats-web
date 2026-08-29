<?php
/**
 * Get Single Menu Item API Endpoint (Corrected)
 *
 * This endpoint returns a single menu item for editing.
 *
 * CORRECTIONS (Version 4.0):
 * - Removed CSRF validation from GET endpoint (idempotent operation)
 * - CSRF protection is not required for GET requests as they are read-only
 * - Source: Security analysis report section 2.1 - Issue 2
 * - Added proper authentication check
 * - Added ownership verification
 *
 * Source: campus-eats-process-document.pdf (Page 11, Section 6.2)
 *
 * @version 4.0
 */

header('Content-Type: application/json');

header
(
    'Access-Control-Allow-Origin: ' .
    (
        isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === 'https://campuseats.example.com'
        ? $_SERVER['HTTP_ORIGIN']
        : ''
    )
);
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Credentials: true');

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

startSecureSession();

// Verify user is authenticated.
if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Authentication required'));
    exit();
}

// Verify user has vendor privileges.
if (!isVendor())
{
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => 'Vendor access required'));
    exit();
}

// Verify HTTP method is GET.
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
{
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed. Use GET.'));
    exit();
}

// Validate required parameter.
if (empty($_GET['item_id']))
{
    echo json_encode(array('success' => false, 'message' => 'Item ID is required.'));
    exit();
}

try
{
    $db = getDB();
    $userId = getCurrentUserId();
    $itemId = (int)$_GET['item_id'];

    // Validate item ID is positive.
    if ($itemId <= 0)
    {
        echo json_encode(array('success' => false, 'message' => 'Invalid item ID.'));
        exit();
    }

    // Get vendor ID for current user.
    // Only approved vendors can access menu items.
    $vendor = $db->fetchOne
    (
        "SELECT vendor_id FROM vendors WHERE vendor_user_id = :user_id AND is_approved = 1",
        array('user_id' => $userId)
    );

    if (!$vendor)
    {
        echo json_encode(array('success' => false, 'message' => 'Vendor profile not found or not approved.'));
        exit();
    }

    // Fetch the menu item and verify ownership.
    // This ensures vendors can only access their own menu items.
    $menuItem = $db->fetchOne
    (
        "SELECT item_id, item_name, description, price, category, is_available
         FROM menu_items
         WHERE item_id = :item_id AND vendor_id = :vendor_id",
        array('item_id' => $itemId, 'vendor_id' => $vendor['vendor_id'])
    );

    if (!$menuItem)
    {
        echo json_encode(array('success' => false, 'message' => 'Menu item not found or access denied.'));
        exit();
    }

    // Return the menu item data.
    echo json_encode(array('success' => true, 'menu_item' => $menuItem));
}
catch (Exception $e)
{
    writeLog("Get menu item error: " . $e->getMessage(), "VENDOR");
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'An error occurred. Please try again later.'));
}
?>