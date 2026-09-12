<?php
/**
 * Orders Page for Vendors
 *
 * This page allows vendors to view and manage all orders placed with their vendor.
 *
 * SOURCE: campus-eats-process-document.pdf (Section 10.2 - Vendor Interface)
 * SOURCE: Mockup - 25.png
 *
 * @version 19.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

// Start secure session and require verified vendor
startSecureSession();
requireVendorVerified();

// Get database connection and current user
$db = getDB();
$currentUser = getCurrentUser();

// Get vendor information
$vendor = $db->fetchOne
(
    "SELECT vendor_id, vendor_name, is_open FROM vendors WHERE vendor_user_id = :user_id",
    array('user_id' => $currentUser['user_id'])
);

if (!$vendor)
{
    writeLog("Vendor orders: No vendor profile found for user ID " . $currentUser['user_id'], "VENDOR");
    header('Location: ' . BASE_URL . '/modules/auth/logout.php');
    exit();
}

$message = '';
$error = '';
$csrfToken = getCsrfToken();

// Handle POST form submissions for status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($submittedToken))
    {
        $error = 'Security validation failed. Please refresh the page and try again.';
        writeLog("Vendor orders CSRF validation failed for vendor ID: {$vendor['vendor_id']}", "VENDOR");
    }
    else
    {
        $action = $_POST['action'] ?? '';
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';

        $validTransitions = array(
            ORDER_STATUS_PENDING   => array(ORDER_STATUS_ACCEPTED, ORDER_STATUS_CANCELLED),
            ORDER_STATUS_ACCEPTED  => array(ORDER_STATUS_PREPARING, ORDER_STATUS_CANCELLED),
            ORDER_STATUS_PREPARING => array(ORDER_STATUS_READY, ORDER_STATUS_CANCELLED),
            ORDER_STATUS_READY     => array(ORDER_STATUS_COMPLETED)
        );

        if ($action === 'update_status' && $orderId > 0 && !empty($newStatus))
        {
            $order = $db->fetchOne
            (
                "SELECT order_id, order_status FROM orders
                 WHERE order_id = :order_id AND vendor_id = :vendor_id",
                array('order_id' => $orderId, 'vendor_id' => $vendor['vendor_id'])
            );

            if (!$order)
            {
                $error = 'Order not found or does not belong to you.';
            }
            elseif (!in_array($newStatus, $validTransitions[$order['order_status']] ?? array()))
            {
                $error = 'Invalid status transition.';
            }
            else
            {
                $db->executeQuery
                (
                    "UPDATE orders SET order_status = :status, updated_at = NOW() WHERE order_id = :order_id",
                    array('status' => $newStatus, 'order_id' => $orderId)
                );
                $message = 'Order status updated successfully.';
                writeLog("Vendor ID {$vendor['vendor_id']} updated order $orderId to $newStatus", "VENDOR");
            }
        }
    }

    $csrfToken = getCsrfToken();
}

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$itemsPerPage = 15;
$offset = ($page - 1) * $itemsPerPage;

// Build filter condition
$filterCondition = '';
$params = array('vendor_id' => $vendor['vendor_id']);

if ($filter === 'pending')
{
    $filterCondition = "AND o.order_status = :status";
    $params['status'] = ORDER_STATUS_PENDING;
}
elseif ($filter === 'active')
{
    $filterCondition = "AND o.order_status IN (:status1, :status2, :status3)";
    $params['status1'] = ORDER_STATUS_ACCEPTED;
    $params['status2'] = ORDER_STATUS_PREPARING;
    $params['status3'] = ORDER_STATUS_READY;
}
elseif ($filter === 'completed')
{
    $filterCondition = "AND o.order_status = :status";
    $params['status'] = ORDER_STATUS_COMPLETED;
}

$pdo = $db->getConnection();

try
{
    // Check which columns exist in the orders table
    $columns = $db->fetchAll("SHOW COLUMNS FROM orders");
    $existingColumns = array();

    foreach ($columns as $col)
    {
        $existingColumns[] = $col['Field'];
    }

    // Build SELECT clause based on existing columns
    $selectFields = array(
        'o.order_id',
        'o.order_number',
        'o.order_status',
        'o.total_amount',
        'o.pickup_time',
        'o.special_requests',
        'o.order_placed_at',
        'u.full_name as customer_name',
        'u.email as customer_email'
    );

    if (in_array('subtotal', $existingColumns))
    {
        $selectFields[] = 'o.subtotal';
    }
    else
    {
        $selectFields[] = '0 as subtotal';
    }

    if (in_array('service_fee', $existingColumns))
    {
        $selectFields[] = 'o.service_fee';
    }
    else
    {
        $selectFields[] = '0 as service_fee';
    }

    if (in_array('tax', $existingColumns))
    {
        $selectFields[] = 'o.tax';
    }
    else
    {
        $selectFields[] = '0 as tax';
    }

    if (in_array('rounding_adjustment', $existingColumns))
    {
        $selectFields[] = 'o.rounding_adjustment';
    }
    else
    {
        $selectFields[] = '0 as rounding_adjustment';
    }

    if (in_array('transaction_id', $existingColumns))
    {
        $selectFields[] = 'o.transaction_id';
    }
    else
    {
        $selectFields[] = 'NULL as transaction_id';
    }

    $selectClause = implode(', ', $selectFields);

    // Get total count for pagination
    $countSql = "SELECT COUNT(DISTINCT o.order_id) as count
                 FROM orders o
                 WHERE o.vendor_id = :vendor_id $filterCondition";
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value)
    {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $totalOrders = $countStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $totalPages = ceil($totalOrders / $itemsPerPage);

    // Fetch orders and their items
    $sql = "SELECT
                $selectClause,
                oi.order_item_id,
                oi.quantity as item_quantity,
                oi.unit_price,
                oi.subtotal as item_subtotal,
                mi.item_name
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            LEFT JOIN order_items oi ON o.order_id = oi.order_id
            LEFT JOIN menu_items mi ON oi.item_id = mi.item_id
            WHERE o.vendor_id = :vendor_id $filterCondition
            ORDER BY
                CASE
                    WHEN o.order_status = 'pending' THEN 1
                    WHEN o.order_status = 'accepted' THEN 2
                    WHEN o.order_status = 'preparing' THEN 3
                    WHEN o.order_status = 'ready' THEN 4
                    ELSE 5
                END,
                o.order_placed_at DESC
            LIMIT :limit OFFSET :offset
        ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':vendor_id', $vendor['vendor_id'], PDO::PARAM_INT);
    $stmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    foreach ($params as $key => $value)
    {
        if ($key !== 'vendor_id')
        {
            $stmt->bindValue(':' . $key, $value);
        }
    }

    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
catch (Exception $e)
{
    writeLog("Vendor orders query error: " . $e->getMessage(), "VENDOR");
    $error = "Unable to load orders. Please try again later.";
    $rows = array();
    $totalOrders = 0;
    $totalPages = 0;
}

// Group items under their parent orders
$orders = array();

foreach ($rows as $row)
{
    $orderId = $row['order_id'];

    if (!isset($orders[$orderId]))
    {
        $orders[$orderId] = array(
            'order_id' => $orderId,
            'order_number' => $row['order_number'],
            'order_status' => $row['order_status'],
            'total_amount' => $row['total_amount'],
            'subtotal' => $row['subtotal'] ?? 0,
            'service_fee' => $row['service_fee'] ?? 0,
            'tax' => $row['tax'] ?? 0,
            'rounding_adjustment' => $row['rounding_adjustment'] ?? 0,
            'transaction_id' => $row['transaction_id'] ?? 'N/A',
            'pickup_time' => $row['pickup_time'],
            'special_requests' => $row['special_requests'],
            'order_placed_at' => $row['order_placed_at'],
            'customer_name' => $row['customer_name'],
            'customer_email' => $row['customer_email'],
            'items' => array()
        );
    }

    if (!empty($row['item_name']))
    {
        $orders[$orderId]['items'][] = array(
            'item_name' => $row['item_name'],
            'quantity' => $row['item_quantity'],
            'unit_price' => $row['unit_price'],
            'subtotal' => $row['item_subtotal']
        );
    }
}

$orders = array_values($orders);

function getOrderStatusBadgeClass($status)
{
    switch ($status)
    {
        case ORDER_STATUS_PENDING:   return 'status-pending';
        case ORDER_STATUS_ACCEPTED:  return 'status-accepted';
        case ORDER_STATUS_PREPARING: return 'status-preparing';
        case ORDER_STATUS_READY:     return 'status-ready';
        case ORDER_STATUS_COMPLETED: return 'status-completed';
        case ORDER_STATUS_CANCELLED: return 'status-cancelled';
        default: return 'status-pending';
    }
}

function getOrderStatusText($status)
{
    switch ($status)
    {
        case ORDER_STATUS_PENDING:   return 'Pending';
        case ORDER_STATUS_ACCEPTED:  return 'Accepted';
        case ORDER_STATUS_PREPARING: return 'Preparing';
        case ORDER_STATUS_READY:     return 'Ready for Pickup';
        case ORDER_STATUS_COMPLETED: return 'Completed';
        case ORDER_STATUS_CANCELLED: return 'Cancelled';
        default: return 'Pending';
    }
}

function getOrderStatusIcon($status)
{
    switch ($status)
    {
        case ORDER_STATUS_PENDING:   return 'fa-clock';
        case ORDER_STATUS_ACCEPTED:  return 'fa-check';
        case ORDER_STATUS_PREPARING: return 'fa-utensils';
        case ORDER_STATUS_READY:     return 'fa-concierge-bell';
        case ORDER_STATUS_COMPLETED: return 'fa-check-double';
        case ORDER_STATUS_CANCELLED: return 'fa-ban';
        default: return 'fa-question';
    }
}

function escapeVendorOutput($string)
{
    if ($string === null) return '';
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeVendorOutput($csrfToken); ?>">
    <title>Manage Orders · Vendor Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <style>
        .filter-tabs
        {
            display: flex;
            gap: var(--space-2);
            flex-wrap: wrap;
            margin-bottom: var(--space-6);
        }

        .filter-btn
        {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-5);
            border-radius: var(--radius-full);
            font-size: 0.8125rem;
            font-weight: 500;
            text-decoration: none;
            transition: all var(--transition-fast);
            background: white;
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }

        .filter-btn:hover
        {
            background: var(--orange-light);
            border-color: var(--orange);
            color: var(--orange);
            transform: translateY(-1px);
        }

        .filter-btn.active
        {
            background: var(--orange);
            border-color: var(--orange);
            color: white;
        }

        .filter-btn .count { font-size: 0.75rem; opacity: 0.8; }

        .order-card
        {
            background: white;
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-5);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: box-shadow var(--transition-base);
            border: 1px solid var(--gray-100);
        }

        .order-card:hover { box-shadow: var(--shadow-lg); }

        .order-card-header
        {
            background: linear-gradient(135deg, var(--orange), var(--orange-dark));
            color: white;
            padding: var(--space-3) var(--space-5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-3);
        }

        .order-info { display: flex; flex-direction: column; }
        .order-number { font-weight: 600; font-size: 0.875rem; }
        .order-date { font-size: 0.75rem; opacity: 0.85; }
        .order-amount { font-weight: 600; font-size: 1.125rem; }

        .order-card-body { padding: var(--space-4) var(--space-5); }

        .customer-info
        {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-4);
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-4);
            border-bottom: 1px solid var(--gray-200);
        }

        .customer-detail
        {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            font-size: 0.8125rem;
        }

        .customer-detail i { width: 16px; color: var(--orange); }

        .order-items-list { margin-bottom: var(--space-4); }

        .order-item-row
        {
            display: flex;
            justify-content: space-between;
            padding: var(--space-2) 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .order-item-row:last-child { border-bottom: none; }

        .receipt-summary
        {
            background: var(--gray-50);
            padding: var(--space-3) var(--space-4);
            border-radius: var(--radius-md);
            margin-top: var(--space-4);
        }

        .receipt-row
        {
            display: flex;
            justify-content: space-between;
            padding: var(--space-1) 0;
            font-size: 0.8125rem;
        }

        .receipt-total
        {
            font-weight: 700;
            font-size: 0.9375rem;
            color: var(--orange);
            border-top: 1px solid var(--gray-300);
            margin-top: var(--space-1);
            padding-top: var(--space-2);
        }

        .special-requests
        {
            background: var(--gray-50);
            padding: var(--space-3) var(--space-4);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-4);
        }

        .special-requests p { margin-top: var(--space-2); font-size: 0.8125rem; color: var(--gray-700); }

        .order-actions
        {
            display: flex;
            gap: var(--space-3);
            flex-wrap: wrap;
            margin-top: var(--space-4);
        }

        .status-pending { background: var(--warning-bg); color: var(--warning-text); }
        .status-accepted { background: var(--info-bg); color: var(--info-text); }
        .status-preparing { background: #E8F0FE; color: #007AFF; }
        .status-ready { background: #E8F5E9; color: #1B7A3D; }
        .status-completed { background: var(--gray-200); color: var(--gray-700); }
        .status-cancelled { background: var(--error-bg); color: var(--error-text); }

        .pagination
        {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: var(--space-2);
            margin-top: var(--space-6);
        }

        .pagination-item
        {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.25rem;
            height: 2.25rem;
            padding: 0 var(--space-3);
            font-size: 0.875rem;
            color: var(--gray-700);
            text-decoration: none;
            border-radius: var(--radius-md);
            transition: all var(--transition-fast);
            background: white;
            border: 1px solid var(--gray-200);
        }

        .pagination-item:hover
        {
            background: var(--orange-light);
            border-color: var(--orange);
            color: var(--orange);
        }

        .pagination-item.active
        {
            background: var(--orange);
            border-color: var(--orange);
            color: white;
        }

        .empty-state
        {
            text-align: center;
            padding: var(--space-8) var(--space-4);
            color: var(--gray-500);
        }

        .empty-state i { font-size: 3rem; margin-bottom: var(--space-4); color: var(--gray-300); }
        .empty-state h3 { color: var(--gray-600); margin-bottom: var(--space-2); }
        .empty-state p { margin-bottom: 0; }

        @media (max-width: 768px)
        {
            .order-card-header { flex-direction: column; text-align: center; }
            .customer-info { flex-direction: column; gap: var(--space-2); }
            .filter-tabs { justify-content: center; }
            .order-actions { justify-content: center; }
            .receipt-summary { font-size: 0.75rem; }
        }

        @media (max-width: 480px)
        {
            .filter-btn { padding: var(--space-1) var(--space-3); font-size: 0.75rem; }
            .order-card-header { padding: var(--space-3); }
            .order-card-body { padding: var(--space-3); }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/vendor_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="vendor-content">
                <div class="container">
                    <div class="page-header">
                        <h1>Manage Orders</h1>
                        <p>View and update customer orders</p>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Success</div>
                                <div class="alert-message"><?php echo escapeVendorOutput($message); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeVendorOutput($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="filter-tabs">
                        <a href="?filter=all&page=1" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i> All Orders
                            <span class="count">(<?php echo $totalOrders; ?>)</span>
                        </a>
                        <a href="?filter=pending&page=1" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                            <i class="fas fa-clock"></i> Pending
                        </a>
                        <a href="?filter=active&page=1" class="filter-btn <?php echo $filter === 'active' ? 'active' : ''; ?>">
                            <i class="fas fa-spinner"></i> Active
                        </a>
                        <a href="?filter=completed&page=1" class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                            <i class="fas fa-check-double"></i> Completed
                        </a>
                    </div>

                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <h3>No Orders Found</h3>
                            <p>There are no orders matching your filter criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="orders-list">
                            <?php foreach ($orders as $order): ?>
                                <div class="order-card">
                                    <div class="order-card-header">
                                        <div class="order-info">
                                            <span class="order-number">
                                                <i class="fas fa-hashtag"></i>
                                                <?php echo escapeVendorOutput($order['order_number']); ?>
                                            </span>
                                            <span class="order-date">
                                                <i class="fas fa-calendar-alt"></i>
                                                <?php echo date('M j, Y g:i A', strtotime($order['order_placed_at'])); ?>
                                            </span>
                                        </div>
                                        <div class="order-status">
                                            <span class="badge <?php echo getOrderStatusBadgeClass($order['order_status']); ?>">
                                                <i class="fas <?php echo getOrderStatusIcon($order['order_status']); ?>"></i>
                                                <?php echo getOrderStatusText($order['order_status']); ?>
                                            </span>
                                        </div>
                                        <div class="order-amount">
                                            <strong>R <?php echo number_format($order['total_amount'], 2); ?></strong>
                                        </div>
                                    </div>

                                    <div class="order-card-body">
                                        <div class="customer-info">
                                            <div class="customer-detail">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo escapeVendorOutput($order['customer_name']); ?></span>
                                            </div>
                                            <div class="customer-detail">
                                                <i class="fas fa-envelope"></i>
                                                <span><?php echo escapeVendorOutput($order['customer_email']); ?></span>
                                            </div>
                                            <?php if (!empty($order['pickup_time'])): ?>
                                                <div class="customer-detail">
                                                    <i class="fas fa-clock"></i>
                                                    <span>Pickup: <?php echo escapeVendorOutput($order['pickup_time']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="order-items-list">
                                            <strong><i class="fas fa-shopping-cart"></i> Items:</strong>
                                            <?php foreach ($order['items'] as $item): ?>
                                                <div class="order-item-row">
                                                    <span><?php echo $item['quantity']; ?>x <?php echo escapeVendorOutput($item['item_name']); ?></span>
                                                    <span>R <?php echo number_format($item['subtotal'], 2); ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="receipt-summary">
                                            <div class="receipt-row">
                                                <span>Subtotal:</span>
                                                <span>R <?php echo number_format($order['subtotal'], 2); ?></span>
                                            </div>
                                            <?php if (isset($order['service_fee']) && $order['service_fee'] > 0): ?>
                                            <div class="receipt-row">
                                                <span>Service Fee:</span>
                                                <span>R <?php echo number_format($order['service_fee'], 2); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (isset($order['tax']) && $order['tax'] > 0): ?>
                                            <div class="receipt-row">
                                                <span>Tax (20%):</span>
                                                <span>R <?php echo number_format($order['tax'], 2); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <?php if (isset($order['rounding_adjustment']) && $order['rounding_adjustment'] != 0): ?>
                                            <div class="receipt-row">
                                                <span>Rounding Adjustment:</span>
                                                <span>R <?php echo number_format($order['rounding_adjustment'], 2); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <div class="receipt-row receipt-total">
                                                <span>Total:</span>
                                                <span>R <?php echo number_format($order['total_amount'], 2); ?></span>
                                            </div>
                                        </div>

                                        <?php if (!empty($order['special_requests'])): ?>
                                            <div class="special-requests">
                                                <strong><i class="fas fa-comment-dots"></i> Special Requests:</strong>
                                                <p><?php echo nl2br(escapeVendorOutput($order['special_requests'])); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="order-actions">
                                            <?php if ($order['order_status'] === ORDER_STATUS_PENDING): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeVendorOutput($csrfToken); ?>">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="new_status" value="<?php echo ORDER_STATUS_ACCEPTED; ?>">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check"></i> Accept Order
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Reject this order? This action cannot be undone.');">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeVendorOutput($csrfToken); ?>">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="new_status" value="<?php echo ORDER_STATUS_CANCELLED; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm">
                                                        <i class="fas fa-times"></i> Reject Order
                                                    </button>
                                                </form>
                                            <?php elseif ($order['order_status'] === ORDER_STATUS_ACCEPTED): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeVendorOutput($csrfToken); ?>">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="new_status" value="<?php echo ORDER_STATUS_PREPARING; ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-utensils"></i> Start Preparing
                                                    </button>
                                                </form>
                                            <?php elseif ($order['order_status'] === ORDER_STATUS_PREPARING): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeVendorOutput($csrfToken); ?>">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="new_status" value="<?php echo ORDER_STATUS_READY; ?>">
                                                    <button type="submit" class="btn btn-primary btn-sm">
                                                        <i class="fas fa-concierge-bell"></i> Mark Ready for Pickup
                                                    </button>
                                                </form>
                                            <?php elseif ($order['order_status'] === ORDER_STATUS_READY): ?>
                                                <span class="badge badge-info">
                                                    <i class="fas fa-clock"></i> Waiting for Customer Pickup
                                                </span>
                                            <?php elseif ($order['order_status'] === ORDER_STATUS_COMPLETED): ?>
                                                <span class="badge badge-success">
                                                    <i class="fas fa-check-double"></i> Completed
                                                </span>
                                            <?php elseif ($order['order_status'] === ORDER_STATUS_CANCELLED): ?>
                                                <span class="badge badge-error">
                                                    <i class="fas fa-ban"></i> Cancelled
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>" class="pagination-item">
                                        <i class="fas fa-chevron-left"></i> Previous
                                    </a>
                                <?php endif; ?>
                                <span class="pagination-item active"><?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>" class="pagination-item">
                                        Next <i class="fas fa-chevron-right"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <button class="sidebar-toggle" id="menuToggleBtn" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <script src="<?php echo ASSETS_URL; ?>/js/dashboard-common.js"></script>
</body>
</html>