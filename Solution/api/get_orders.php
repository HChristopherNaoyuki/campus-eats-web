<?php
/**
 * Get Orders API Endpoint (Corrected)
 *
 * This endpoint returns the current user's order history.
 *
 * CORRECTIONS:
 * - Added startSecureSession() call
 * - Performance optimised with bound parameters
 * - Added pagination with proper parameter binding
 *
 * Source: campus-eats-process-document.pdf (Section 6.1 - View order history)
 *
 * @version 4.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/session.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';

startSecureSession();

if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Authentication required'));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET')
{
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed'));
    exit();
}

try
{
    $db = getDB();
    $userId = getCurrentUserId();

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $perPage;

    $sql =
        "SELECT
            o.order_id,
            o.order_number,
            o.order_status,
            o.total_amount,
            o.pickup_time,
            o.special_requests,
            o.order_placed_at,
            v.vendor_name,
            v.vendor_id,
            oi.quantity,
            oi.unit_price,
            oi.subtotal,
            mi.item_name,
            mi.description
         FROM orders o
         JOIN vendors v ON o.vendor_id = v.vendor_id
         LEFT JOIN order_items oi ON o.order_id = oi.order_id
         LEFT JOIN menu_items mi ON oi.item_id = mi.item_id
         WHERE o.user_id = :user_id
         ORDER BY o.order_placed_at DESC
         LIMIT :limit OFFSET :offset";

    $pdo = $db->getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orders = array();

    foreach ($rows as $row)
    {
        $orderId = $row['order_id'];

        if (!isset($orders[$orderId]))
        {
            $orders[$orderId] = array
            (
                'order_id' => $orderId,
                'order_number' => $row['order_number'],
                'order_status' => $row['order_status'],
                'total_amount' => (float)$row['total_amount'],
                'pickup_time' => $row['pickup_time'],
                'special_requests' => $row['special_requests'],
                'order_placed_at' => $row['order_placed_at'],
                'vendor_name' => $row['vendor_name'],
                'vendor_id' => $row['vendor_id'],
                'items' => array()
            );
        }

        if (!empty($row['item_name']))
        {
            $orders[$orderId]['items'][] = array
            (
                'item_name' => $row['item_name'],
                'description' => $row['description'],
                'quantity' => (int)$row['quantity'],
                'unit_price' => (float)$row['unit_price'],
                'subtotal' => (float)$row['subtotal']
            );
        }
    }

    $countResult = $db->fetchOne
    (
        "SELECT COUNT(*) AS total FROM orders WHERE user_id = :user_id",
        array('user_id' => $userId)
    );

    $totalOrders = (int)($countResult['total'] ?? 0);
    $totalPages = ceil($totalOrders / $perPage);

    echo json_encode
    (
        array
        (
            'success' => true,
            'orders' => array_values($orders),
            'pagination' => array
            (
                'current_page' => $page,
                'per_page' => $perPage,
                'total_orders' => $totalOrders,
                'total_pages' => $totalPages
            )
        )
    );
}
catch (Exception $exception)
{
    writeLog('Get orders error: ' . $exception->getMessage(), "API");

    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'An error occurred while retrieving orders.'));
}
?>