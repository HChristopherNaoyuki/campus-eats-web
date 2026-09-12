<?php
/**
 * Vendor Respond to Order API Endpoint (Complete Implementation)
 *
 * Allows vendors to accept, reject, or update order status with CSRF protection.
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
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Credentials: true');

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

startSecureSession();

requireVendor();

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

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';

if (!validateCsrfToken($csrfToken))
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userId = getCurrentUserId() ?? 'unknown';
    writeLog("CSRF validation failed for vendor order update. IP: $ipAddress, User: $userId", "CSRF");

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

if (empty($input['order_id']) || empty($input['status']))
{
    echo json_encode(array('success' => false, 'message' => 'Order ID and status are required'));
    exit();
}

$validTransitions = array
(
    ORDER_STATUS_PENDING   => array(ORDER_STATUS_ACCEPTED, ORDER_STATUS_CANCELLED),
    ORDER_STATUS_ACCEPTED  => array(ORDER_STATUS_PREPARING, ORDER_STATUS_CANCELLED),
    ORDER_STATUS_PREPARING => array(ORDER_STATUS_READY, ORDER_STATUS_CANCELLED),
    ORDER_STATUS_READY     => array(ORDER_STATUS_COMPLETED),
    ORDER_STATUS_COMPLETED => array(),
    ORDER_STATUS_CANCELLED => array()
);

try
{
    $db = getDB();
    $userId = getCurrentUserId();

    $vendor = $db->fetchOne
    (
        "SELECT vendor_id FROM vendors WHERE vendor_user_id = :user_id",
        array('user_id' => $userId)
    );

    if (!$vendor)
    {
        echo json_encode(array('success' => false, 'message' => 'Vendor not found'));
        exit();
    }

    $order = $db->fetchOne
    (
        "SELECT order_id, order_status FROM orders
         WHERE order_id = :order_id AND vendor_id = :vendor_id",
        array
        (
            'order_id' => $input['order_id'],
            'vendor_id' => $vendor['vendor_id']
        )
    );

    if (!$order)
    {
        echo json_encode
        (
            array
            (
                'success' => false,
                'message' => 'Order not found or does not belong to you'
            )
        );
        exit();
    }

    $newStatus = $input['status'];
    $allowedStatuses = $validTransitions[$order['order_status']] ?? array();

    if (!in_array($newStatus, $allowedStatuses, true))
    {
        echo json_encode
        (
            array
            (
                'success' => false,
                'message' => 'Invalid status transition from ' . $order['order_status'] . ' to ' . $newStatus
            )
        );
        exit();
    }

    $updateSql = "UPDATE orders SET order_status = :status, updated_at = NOW() WHERE order_id = :order_id";
    $db->executeQuery
    (
        $updateSql,
        array
        (
            'status' => $newStatus,
            'order_id' => $input['order_id']
        )
    );

    writeLog
    (
        "Vendor ID {$vendor['vendor_id']} updated order ID {$input['order_id']} status to $newStatus",
        "VENDOR"
    );

    echo json_encode
    (
        array
        (
            'success' => true,
            'message' => 'Order status updated successfully',
            'order_id' => $input['order_id'],
            'new_status' => $newStatus
        )
    );
}
catch (Exception $exception)
{
    writeLog('Vendor respond order error: ' . $exception->getMessage(), "VENDOR");

    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'An error occurred. Please try again later.'));
}
?>