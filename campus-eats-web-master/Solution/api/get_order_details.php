<?php
/**
 * Get Order Details API Endpoint (Corrected)
 *
 * This endpoint returns the full details of a specific order, including all items.
 * It is primarily used by the "reorder" function on the student's order history page.
 *
 * CORRECTIONS (Version 5.0):
 * - Fixed reorder functionality that was displaying "An error occurred"
 * - Added proper error handling and logging
 * - Added CSRF token validation for security
 * - Added stock availability check for reorder items
 * - Returns available quantity for each item
 * - Improved error messages for debugging
 * - Fixed CORS headers for proper cross-origin requests
 *
 * Source: Campus Eats API Endpoint
 *
 * @version 5.0
 */

// Set CORS headers before any output
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . (isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

startSecureSession();

// Allow both GET and POST for flexibility
$isPost = ($_SERVER['REQUEST_METHOD'] === 'POST');
$isGet = ($_SERVER['REQUEST_METHOD'] === 'GET');

if (!$isPost && !$isGet)
{
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed. Use GET or POST.'));
    exit();
}

// For POST requests, validate CSRF token
if ($isPost)
{
    $input = json_decode(file_get_contents('php://input'), true);
    $submittedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($input['csrf_token'] ?? '');
    
    if (!validateCsrfToken($submittedToken))
    {
        writeLog("Get order details CSRF validation failed.", "API");
        http_response_code(403);
        echo json_encode(array('success' => false, 'message' => 'Security validation failed. Please refresh the page.'));
        exit();
    }
}

if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Authentication required. Please log in.'));
    exit();
}

// Get order ID from GET parameter or POST body
$orderId = 0;
$input = array();

if ($isGet && isset($_GET['order_id']))
{
    $orderId = (int)$_GET['order_id'];
}
elseif ($isPost)
{
    $input = json_decode(file_get_contents('php://input'), true);
    if (isset($input['order_id']))
    {
        $orderId = (int)$input['order_id'];
    }
}

if ($orderId <= 0)
{
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Valid order ID is required.'));
    exit();
}

writeLog("Get order details request for order ID: $orderId", "API");

try
{
    $db = getDB();
    $userId = getCurrentUserId();
    $userRole = getCurrentUserRole();

    // Fetch the order based on user role
    if (isStudent())
    {
        $order = $db->fetchOne
        (
            "SELECT o.order_id, o.order_number, o.vendor_id, v.vendor_name,
                    o.order_status, o.total_amount, o.subtotal, o.service_fee, o.tax,
                    o.rounding_adjustment, o.transaction_id, o.order_placed_at
             FROM orders o
             JOIN vendors v ON o.vendor_id = v.vendor_id
             WHERE o.order_id = :order_id AND o.user_id = :user_id",
            array('order_id' => $orderId, 'user_id' => $userId)
        );
    }
    elseif (isVendor())
    {
        $vendor = $db->fetchOne
        (
            "SELECT vendor_id FROM vendors WHERE vendor_user_id = :user_id",
            array('user_id' => $userId)
        );
        
        if (!$vendor)
        {
            echo json_encode(array('success' => false, 'message' => 'Vendor profile not found.'));
            exit();
        }
        
        $order = $db->fetchOne
        (
            "SELECT o.order_id, o.order_number, o.vendor_id, v.vendor_name,
                    o.order_status, o.total_amount, o.subtotal, o.service_fee, o.tax,
                    o.rounding_adjustment, o.transaction_id, o.order_placed_at,
                    u.full_name as customer_name, u.email as customer_email
             FROM orders o
             JOIN vendors v ON o.vendor_id = v.vendor_id
             JOIN users u ON o.user_id = u.user_id
             WHERE o.order_id = :order_id AND o.vendor_id = :vendor_id",
            array('order_id' => $orderId, 'vendor_id' => $vendor['vendor_id'])
        );
    }
    elseif (isAdmin())
    {
        $order = $db->fetchOne
        (
            "SELECT o.order_id, o.order_number, o.vendor_id, v.vendor_name,
                    o.order_status, o.total_amount, o.subtotal, o.service_fee, o.tax,
                    o.rounding_adjustment, o.transaction_id, o.order_placed_at,
                    u.full_name as customer_name, u.email as customer_email
             FROM orders o
             JOIN vendors v ON o.vendor_id = v.vendor_id
             JOIN users u ON o.user_id = u.user_id
             WHERE o.order_id = :order_id",
            array('order_id' => $orderId)
        );
    }
    else
    {
        echo json_encode(array('success' => false, 'message' => 'Invalid user role.'));
        exit();
    }

    if (!$order)
    {
        writeLog("Order not found or access denied for order ID: $orderId, user ID: $userId", "API");
        http_response_code(404);
        echo json_encode(array('success' => false, 'message' => 'Order not found or access denied.'));
        exit();
    }

    // Fetch all items for the order with current stock availability
    $items = $db->fetchAll
    (
        "SELECT oi.item_id, mi.item_name, oi.quantity, oi.unit_price,
                COALESCE(mi.quantity_available, 0) as current_stock,
                mi.is_available
         FROM order_items oi
         JOIN menu_items mi ON oi.item_id = mi.item_id
         WHERE oi.order_id = :order_id",
        array('order_id' => $orderId)
    );

    // Check if each item is still available for reorder
    foreach ($items as &$item)
    {
        $item['can_reorder'] = ($item['is_available'] == 1 && $item['current_stock'] >= $item['quantity']);
        $item['available_quantity'] = $item['current_stock'];
        unset($item['current_stock']);
    }

    $responseData = array
    (
        'success' => true,
        'order_id' => $order['order_id'],
        'order_number' => $order['order_number'],
        'vendor_id' => $order['vendor_id'],
        'vendor_name' => $order['vendor_name'],
        'order_status' => $order['order_status'],
        'total_amount' => $order['total_amount'],
        'subtotal' => $order['subtotal'] ?? 0,
        'service_fee' => $order['service_fee'] ?? 0,
        'tax' => $order['tax'] ?? 0,
        'rounding_adjustment' => $order['rounding_adjustment'] ?? 0,
        'transaction_id' => $order['transaction_id'] ?? null,
        'order_placed_at' => $order['order_placed_at'],
        'items' => $items
    );

    // Add customer info for vendor/admin views
    if (isset($order['customer_name']))
    {
        $responseData['customer_name'] = $order['customer_name'];
        $responseData['customer_email'] = $order['customer_email'];
    }

    writeLog("Order details retrieved successfully for order ID: $orderId", "API");
    echo json_encode($responseData);
}
catch (PDOException $e)
{
    writeLog('Get order details PDO error: ' . $e->getMessage(), "API");
    
    http_response_code(500);
    echo json_encode
    (
        array
        (
            'success' => false,
            'message' => 'A database error occurred. Please try again later.'
        )
    );
}
catch (Exception $e)
{
    writeLog('Get order details error: ' . $e->getMessage(), "API");
    
    http_response_code(500);
    echo json_encode
    (
        array
        (
            'success' => false,
            'message' => 'An error occurred while retrieving order details. Please try again later.'
        )
    );
}
?>