<?php
/**
 * Delete Menu Item API Endpoint (Corrected)
 *
 * This endpoint allows vendors to delete menu items (only if never ordered).
 *
 * CORRECTIONS:
 * - Added CSRF token validation
 * - Added proper authentication check
 * - Added ownership verification before deletion
 *
 * Source: campus-eats-process-document.pdf (Page 11, Section 6.2 - Remove menu items)
 *
 * @version 3.0
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
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Credentials: true');

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

startSecureSession();

if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Authentication required'));
    exit();
}

if (!isVendor())
{
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => 'Vendor access required'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed. Use POST.'));
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input)
{
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Invalid request data'));
    exit();
}

// Validate CSRF token.
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';

if (!validateCsrfToken($csrfToken))
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userId = getCurrentUserId() ?? 'unknown';
    writeLog("CSRF validation failed for delete menu item. IP: $ipAddress, User: $userId", "CSRF");

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

if (empty($input['item_id']))
{
    echo json_encode(array('success' => false, 'message' => 'Item ID is required.'));
    exit();
}

try
{
    $db = getDB();
    $userId = getCurrentUserId();
    $itemId = (int)$input['item_id'];

    if ($itemId <= 0)
    {
        echo json_encode(array('success' => false, 'message' => 'Invalid item ID.'));
        exit();
    }

    // Get vendor ID for current user.
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

    // Verify the menu item belongs to this vendor.
    $verify = $db->fetchOne
    (
        "SELECT item_id FROM menu_items WHERE item_id = :item_id AND vendor_id = :vendor_id",
        array('item_id' => $itemId, 'vendor_id' => $vendor['vendor_id'])
    );

    if (!$verify)
    {
        echo json_encode(array('success' => false, 'message' => 'Menu item not found or access denied.'));
        exit();
    }

    // Check if the item has ever been ordered.
    $hasOrders = $db->fetchOne
    (
        "SELECT COUNT(*) as count FROM order_items WHERE item_id = :item_id",
        array('item_id' => $itemId)
    );

    if ($hasOrders['count'] > 0)
    {
        echo json_encode
        (
            array
            (
                'success' => false,
                'message' => 'Cannot delete item that has been ordered. Mark as unavailable instead.'
            )
        );
        exit();
    }

    $db->executeQuery
    (
        "DELETE FROM menu_items WHERE item_id = :item_id AND vendor_id = :vendor_id",
        array('item_id' => $itemId, 'vendor_id' => $vendor['vendor_id'])
    );

    writeLog("Vendor ID {$vendor['vendor_id']} deleted menu item ID: $itemId", "VENDOR");
    echo json_encode(array('success' => true, 'message' => 'Menu item deleted successfully.'));
}
catch (Exception $e)
{
    writeLog("Delete menu item error: " . $e->getMessage(), "VENDOR");
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'An error occurred. Please try again later.'));
}
?>