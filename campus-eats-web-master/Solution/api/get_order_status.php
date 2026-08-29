<?php
/**
 * Get Order Status API Endpoint (Corrected)
 *
 * This endpoint returns the current status of a specific order for real-time tracking.
 *
 * CORRECTIONS:
 * - Added startSecureSession() call
 * - Added role-based access control
 * - Added generic error messages only
 * - Added HTTP 404 for missing orders
 *
 * Source: campus-eats-process-document.pdf (Page 11, Section 6.1 - Track order status)
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
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Credentials: true');

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

startSecureSession();

if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Authentication required. Please log in.'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET')
{
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed. Use GET.'));
    exit();
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0)
{
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Valid order ID is required.'));
    exit();
}

try
{
    $db = getDB();
    $userId = getCurrentUserId();
    $userRole = getCurrentUserRole();

    if (isStudent())
    {
        $sql = "SELECT o.order_id, o.order_number, o.order_status, o.total_amount,
                       o.pickup_time, o.order_placed_at, o.updated_at,
                       v.vendor_name, v.vendor_id
                FROM orders o
                JOIN vendors v ON o.vendor_id = v.vendor_id
                WHERE o.order_id = :order_id AND o.user_id = :user_id";

        $order = $db->fetchOne
        (
            $sql,
            array
            (
                'order_id' => $orderId,
                'user_id' => $userId
            )
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

        $sql = "SELECT o.order_id, o.order_number, o.order_status, o.total_amount,
                       o.pickup_time, o.order_placed_at, o.updated_at, o.special_requests,
                       v.vendor_name, v.vendor_id,
                       u.full_name as customer_name, u.email as customer_email
                FROM orders o
                JOIN vendors v ON o.vendor_id = v.vendor_id
                JOIN users u ON o.user_id = u.user_id
                WHERE o.order_id = :order_id AND o.vendor_id = :vendor_id";

        $order = $db->fetchOne
        (
            $sql,
            array
            (
                'order_id' => $orderId,
                'vendor_id' => $vendor['vendor_id']
            )
        );
    }
    elseif (isAdmin())
    {
        $sql = "SELECT o.order_id, o.order_number, o.order_status, o.total_amount,
                       o.pickup_time, o.order_placed_at, o.updated_at, o.special_requests,
                       v.vendor_name, v.vendor_id,
                       u.full_name as customer_name, u.email as customer_email
                FROM orders o
                JOIN vendors v ON o.vendor_id = v.vendor_id
                JOIN users u ON o.user_id = u.user_id
                WHERE o.order_id = :order_id";

        $order = $db->fetchOne($sql, array('order_id' => $orderId));
    }
    else
    {
        echo json_encode(array('success' => false, 'message' => 'Invalid user role.'));
        exit();
    }

    if (!$order)
    {
        http_response_code(404);
        echo json_encode(array('success' => false, 'message' => 'Order not found or access denied.'));
        exit();
    }

    // Fetch order items.
    $itemsSql = "SELECT oi.quantity, oi.unit_price, oi.subtotal, mi.item_name
                 FROM order_items oi
                 JOIN menu_items mi ON oi.item_id = mi.item_id
                 WHERE oi.order_id = :order_id";

    $orderItems = $db->fetchAll($itemsSql, array('order_id' => $orderId));

    $orderPlacedTimestamp = strtotime($order['order_placed_at']);
    $estimatedReadyTime = null;

    if ($order['order_status'] === ORDER_STATUS_PENDING)
    {
        $estimatedReadyTime = date('Y-m-d H:i:s', $orderPlacedTimestamp + (20 * 60));
    }
    elseif ($order['order_status'] === ORDER_STATUS_ACCEPTED)
    {
        $estimatedReadyTime = date('Y-m-d H:i:s', $orderPlacedTimestamp + (15 * 60));
    }
    elseif ($order['order_status'] === ORDER_STATUS_PREPARING)
    {
        $estimatedReadyTime = date('Y-m-d H:i:s', $orderPlacedTimestamp + (10 * 60));
    }
    elseif ($order['order_status'] === ORDER_STATUS_READY)
    {
        $estimatedReadyTime = $order['updated_at'];
    }

    $statusMessages = array
    (
        ORDER_STATUS_PENDING   => 'Your order has been received and is awaiting confirmation from the vendor.',
        ORDER_STATUS_ACCEPTED  => 'Your order has been accepted by the vendor and will be prepared shortly.',
        ORDER_STATUS_PREPARING => 'The kitchen is preparing your order. It will be ready soon.',
        ORDER_STATUS_READY     => 'Your order is ready for pickup! Please collect it from the vendor.',
        ORDER_STATUS_COMPLETED => 'Order completed. Thank you for using CampusEats!',
        ORDER_STATUS_CANCELLED => 'This order has been cancelled.'
    );

    $progressPercentage = 0;

    switch ($order['order_status'])
    {
        case ORDER_STATUS_PENDING:   $progressPercentage = 0; break;
        case ORDER_STATUS_ACCEPTED:  $progressPercentage = 25; break;
        case ORDER_STATUS_PREPARING: $progressPercentage = 50; break;
        case ORDER_STATUS_READY:     $progressPercentage = 75; break;
        case ORDER_STATUS_COMPLETED: $progressPercentage = 100; break;
        default: $progressPercentage = 0; break;
    }

    $response = array
    (
        'success' => true,
        'order' => array
        (
            'order_id' => $order['order_id'],
            'order_number' => $order['order_number'],
            'status' => $order['order_status'],
            'status_text' => ucfirst(str_replace('_', ' ', $order['order_status'])),
            'status_message' => $statusMessages[$order['order_status']] ?? 'Status update pending.',
            'progress_percentage' => $progressPercentage,
            'total_amount' => (float)$order['total_amount'],
            'pickup_time' => $order['pickup_time'],
            'order_placed_at' => $order['order_placed_at'],
            'last_updated' => $order['updated_at'],
            'estimated_ready_time' => $estimatedReadyTime,
            'vendor_name' => $order['vendor_name'],
            'vendor_id' => $order['vendor_id'],
            'items' => $orderItems
        )
    );

    if (isset($order['customer_name']))
    {
        $response['order']['customer_name'] = $order['customer_name'];
        $response['order']['customer_email'] = $order['customer_email'];
    }

    if (isset($order['special_requests']) && !empty($order['special_requests']))
    {
        $response['order']['special_requests'] = $order['special_requests'];
    }

    echo json_encode($response);
}
catch (Exception $exception)
{
    writeLog('Get order status error: ' . $exception->getMessage(), "API");

    http_response_code(500);
    echo json_encode
    (
        array
        (
            'success' => false,
            'message' => 'An error occurred while retrieving order status. Please try again later.'
        )
    );
}
?>