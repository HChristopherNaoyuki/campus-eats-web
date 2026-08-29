<?php
/**
 * Update Order Status Page for Vendors
 *
 * This page allows vendors to update the status of individual orders.
 *
 * SOURCE: campus-eats-process-document.pdf (Section 6.2 - Order management)
 *
 * @version 10.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

// Start secure session and require verified vendor
startSecureSession();
requireVendorVerified();

$db = getDB();
$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();

$vendor = $db->fetchOne
(
    "SELECT vendor_id, vendor_name, is_open FROM vendors WHERE vendor_user_id = :user_id",
    array('user_id' => $currentUser['user_id'])
);

if (!$vendor)
{
    writeLog("Vendor update_status: No vendor profile found for user ID " . $currentUser['user_id'], "VENDOR");
    header('Location: ' . BASE_URL . '/modules/auth/logout.php');
    exit();
}

$message = '';
$error = '';
$order = null;
$orderItems = array();

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId > 0)
{
    $order = $db->fetchOne
    (
        "SELECT o.order_id, o.order_number, o.order_status, o.total_amount,
                o.pickup_time, o.special_requests, o.order_placed_at,
                u.full_name as customer_name, u.email as customer_email
         FROM orders o
         JOIN users u ON o.user_id = u.user_id
         WHERE o.order_id = :order_id AND o.vendor_id = :vendor_id",
        array('order_id' => $orderId, 'vendor_id' => $vendor['vendor_id'])
    );

    if ($order)
    {
        $orderItems = $db->fetchAll
        (
            "SELECT oi.quantity, oi.unit_price, oi.subtotal,
                    mi.item_name
             FROM order_items oi
             JOIN menu_items mi ON oi.item_id = mi.item_id
             WHERE oi.order_id = :order_id",
            array('order_id' => $orderId)
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_status']))
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($submittedToken))
    {
        $error = 'Security validation failed. Please refresh the page and try again.';
        writeLog("Vendor update_status CSRF validation failed for vendor ID: {$vendor['vendor_id']}", "VENDOR");
    }
    else
    {
        $submittedOrderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';

        if ($submittedOrderId <= 0 || empty($newStatus))
        {
            $error = 'Invalid request. Order ID and status are required.';
        }
        else
        {
            $verify = $db->fetchOne
            (
                "SELECT order_id, order_status FROM orders
                 WHERE order_id = :order_id AND vendor_id = :vendor_id",
                array('order_id' => $submittedOrderId, 'vendor_id' => $vendor['vendor_id'])
            );

            if (!$verify)
            {
                $error = 'Order not found or does not belong to you.';
            }
            else
            {
                if (!$vendor['is_open'] && $newStatus !== ORDER_STATUS_CANCELLED)
                {
                    $error = 'Your shop is currently closed. You cannot update order statuses. Open your shop first.';
                }
                else
                {
                    $currentStatus = $verify['order_status'];
                    $validTransitions = array
                    (
                        ORDER_STATUS_PENDING   => array(ORDER_STATUS_ACCEPTED, ORDER_STATUS_CANCELLED),
                        ORDER_STATUS_ACCEPTED  => array(ORDER_STATUS_PREPARING, ORDER_STATUS_CANCELLED),
                        ORDER_STATUS_PREPARING => array(ORDER_STATUS_READY, ORDER_STATUS_CANCELLED),
                        ORDER_STATUS_READY     => array(ORDER_STATUS_COMPLETED),
                        ORDER_STATUS_COMPLETED => array(),
                        ORDER_STATUS_CANCELLED => array()
                    );

                    $allowedStatuses = $validTransitions[$currentStatus] ?? array();

                    if (!in_array($newStatus, $allowedStatuses, true))
                    {
                        $error = 'Invalid status transition from ' . $currentStatus . ' to ' . $newStatus . '.';
                    }
                    else
                    {
                        $db->executeQuery
                        (
                            "UPDATE orders SET order_status = :status, updated_at = NOW() WHERE order_id = :order_id",
                            array('status' => $newStatus, 'order_id' => $submittedOrderId)
                        );

                        $message = 'Order status updated successfully from ' . $currentStatus . ' to ' . $newStatus . '.';
                        writeLog("Vendor ID {$vendor['vendor_id']} updated order $submittedOrderId from $currentStatus to $newStatus", "VENDOR");

                        $order = $db->fetchOne
                        (
                            "SELECT o.order_id, o.order_number, o.order_status, o.total_amount,
                                    o.pickup_time, o.special_requests, o.order_placed_at,
                                    u.full_name as customer_name, u.email as customer_email
                             FROM orders o
                             JOIN users u ON o.user_id = u.user_id
                             WHERE o.order_id = :order_id AND o.vendor_id = :vendor_id",
                            array('order_id' => $submittedOrderId, 'vendor_id' => $vendor['vendor_id'])
                        );
                    }
                }
            }
        }
    }
    $csrfToken = getCsrfToken();
}

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

function getAvailableStatusOptions($currentStatus, $isShopOpen)
{
    $options = array();

    if ($currentStatus === ORDER_STATUS_PENDING)
    {
        $options[ORDER_STATUS_ACCEPTED] = 'Accept Order';
        $options[ORDER_STATUS_CANCELLED] = 'Cancel Order';
    }
    elseif ($currentStatus === ORDER_STATUS_ACCEPTED)
    {
        $options[ORDER_STATUS_PREPARING] = 'Start Preparing';
        $options[ORDER_STATUS_CANCELLED] = 'Cancel Order';
    }
    elseif ($currentStatus === ORDER_STATUS_PREPARING)
    {
        $options[ORDER_STATUS_READY] = 'Mark Ready for Pickup';
        $options[ORDER_STATUS_CANCELLED] = 'Cancel Order';
    }
    elseif ($currentStatus === ORDER_STATUS_READY)
    {
        $options[ORDER_STATUS_COMPLETED] = 'Mark as Completed';
    }

    return $options;
}

function escapeUpdateStatusOutput($string)
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
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Update Order Status · Vendor Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <style>
        .order-details-card
        {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--gray-100);
        }

        .order-details-header
        {
            background: linear-gradient(135deg, var(--orange), var(--orange-dark));
            color: white;
            padding: var(--space-4) var(--space-6);
        }

        .order-details-header h2
        {
            color: white;
            margin-bottom: var(--space-1);
            font-size: 1.25rem;
        }

        .order-details-header p
        {
            opacity: 0.9;
            margin: 0;
            font-size: 0.875rem;
        }

        .order-details-body { padding: var(--space-5) var(--space-6); }

        .info-grid
        {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--space-4);
            margin-bottom: var(--space-5);
            padding-bottom: var(--space-4);
            border-bottom: 1px solid var(--gray-200);
        }

        .info-item { display: flex; flex-direction: column; }
        .info-label { font-size: 0.6875rem; color: var(--gray-500); text-transform: uppercase; letter-spacing: 0.02em; margin-bottom: var(--space-1); }
        .info-value { font-size: 0.9375rem; font-weight: 500; }
        .info-value.total-amount { font-size: 1.25rem; color: var(--orange); font-weight: 600; }

        .current-status-badge
        {
            display: inline-block;
            padding: var(--space-2) var(--space-4);
            border-radius: var(--radius-full);
            font-size: 0.875rem;
            font-weight: 600;
        }

        .items-table
        {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: var(--space-5);
        }

        .items-table th,
        .items-table td
        {
            padding: var(--space-3);
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .items-table th
        {
            background: var(--gray-50);
            font-weight: 600;
            font-size: 0.8125rem;
        }

        .items-table tfoot td
        {
            border-top: 2px solid var(--gray-300);
            font-weight: 600;
            padding-top: var(--space-4);
        }

        .text-right { text-align: right; }

        .special-requests-box
        {
            background: var(--gray-50);
            padding: var(--space-3) var(--space-4);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-5);
        }

        .special-requests-box p { margin-top: var(--space-2); color: var(--gray-700); line-height: 1.5; }

        .status-update-section
        {
            margin-top: var(--space-5);
            padding-top: var(--space-5);
            border-top: 1px solid var(--gray-200);
        }

        .shop-closed-warning
        {
            background: var(--warning-bg);
            border-left: 4px solid var(--warning);
            padding: var(--space-3) var(--space-4);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-4);
            display: flex;
            align-items: center;
            gap: var(--space-3);
        }

        .shop-closed-warning i { font-size: 1.25rem; color: var(--warning); }
        .shop-closed-warning p { margin: 0; font-size: 0.875rem; color: var(--warning-text); }

        .status-form { max-width: 400px; }

        .status-form select
        {
            width: 100%;
            padding: var(--space-3);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            font-size: 0.9375rem;
            margin-bottom: var(--space-4);
        }

        .status-form select:focus
        {
            border-color: var(--orange);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.1);
        }

        .form-actions
        {
            display: flex;
            gap: var(--space-3);
            flex-wrap: wrap;
        }

        .status-pending { background: var(--warning-bg); color: var(--warning-text); }
        .status-accepted { background: var(--info-bg); color: var(--info-text); }
        .status-preparing { background: #E8F0FE; color: #007AFF; }
        .status-ready { background: #E8F5E9; color: #1B7A3D; }
        .status-completed { background: var(--gray-200); color: var(--gray-700); }
        .status-cancelled { background: var(--error-bg); color: var(--error-text); }

        .empty-state
        {
            text-align: center;
            padding: var(--space-8) var(--space-4);
            color: var(--gray-500);
        }

        .empty-state i { font-size: 3rem; margin-bottom: var(--space-4); color: var(--gray-300); }
        .empty-state h3 { color: var(--gray-600); margin-bottom: var(--space-2); }
        .empty-state p { margin-bottom: var(--space-4); }

        @media (max-width: 768px)
        {
            .info-grid { grid-template-columns: 1fr; gap: var(--space-3); }
            .items-table { display: block; overflow-x: auto; }
            .status-form { max-width: 100%; }
            .order-details-body { padding: var(--space-4); }
            .form-actions { flex-direction: column; }
            .form-actions .btn { width: 100%; }
        }

        @media (max-width: 480px)
        {
            .order-details-header { padding: var(--space-3) var(--space-4); }
            .order-details-header h2 { font-size: 1rem; }
            .order-details-body { padding: var(--space-3); }
            .info-value.total-amount { font-size: 1.125rem; }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/vendor_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="vendor-content">
                <div class="container">
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Success</div>
                                <div class="alert-message"><?php echo escapeUpdateStatusOutput($message); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeUpdateStatusOutput($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!$order && $orderId > 0): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Order Not Found</div>
                                <div class="alert-message">Order not found or does not belong to your vendor account.</div>
                            </div>
                        </div>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <h3>Order Not Found</h3>
                            <p>The requested order could not be found or you do not have permission to view it.</p>
                            <a href="orders.php" class="btn btn-primary">Return to Orders</a>
                        </div>

                    <?php elseif (!$order && $orderId == 0): ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <h3>No Order Selected</h3>
                            <p>Please select an order from the Orders page to update its status.</p>
                            <a href="orders.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> View Orders
                            </a>
                        </div>

                    <?php elseif ($order): ?>
                        <div class="order-details-card">
                            <div class="order-details-header">
                                <h2>
                                    <i class="fas fa-receipt"></i>
                                    Order #<?php echo escapeUpdateStatusOutput($order['order_number']); ?>
                                </h2>
                                <p>Placed on <?php echo date('F j, Y \a\t g:i A', strtotime($order['order_placed_at'])); ?></p>
                            </div>

                            <div class="order-details-body">
                                <div class="info-grid">
                                    <div class="info-item">
                                        <span class="info-label">Customer Name</span>
                                        <span class="info-value">
                                            <i class="fas fa-user"></i>
                                            <?php echo escapeUpdateStatusOutput($order['customer_name']); ?>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Customer Email</span>
                                        <span class="info-value">
                                            <i class="fas fa-envelope"></i>
                                            <?php echo escapeUpdateStatusOutput($order['customer_email']); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($order['pickup_time'])): ?>
                                        <div class="info-item">
                                            <span class="info-label">Requested Pickup Time</span>
                                            <span class="info-value">
                                                <i class="fas fa-clock"></i>
                                                <?php echo escapeUpdateStatusOutput($order['pickup_time']); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="info-item">
                                        <span class="info-label">Current Status</span>
                                        <span class="info-value">
                                            <span class="current-status-badge <?php echo getOrderStatusBadgeClass($order['order_status']); ?>">
                                                <i class="fas <?php echo getOrderStatusIcon($order['order_status']); ?>"></i>
                                                <?php echo getOrderStatusText($order['order_status']); ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-label">Total Amount</span>
                                        <span class="info-value total-amount">
                                            <i class="fas fa-rand"></i>
                                            R <?php echo number_format($order['total_amount'], 2); ?>
                                        </span>
                                    </div>
                                </div>

                                <h4>
                                    <i class="fas fa-shopping-cart"></i>
                                    Order Items
                                </h4>
                                <table class="items-table">
                                    <thead>
                                        <tr>
                                            <th>Item Name</th>
                                            <th class="text-right">Quantity</th>
                                            <th class="text-right">Unit Price</th>
                                            <th class="text-right">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $itemSubtotalSum = 0;
                                        foreach ($orderItems as $item):
                                            $itemSubtotalSum += $item['subtotal'];
                                        ?>
                                        <tr>
                                            <td><?php echo escapeUpdateStatusOutput($item['item_name']); ?></td>
                                            <td class="text-right"><?php echo (int)$item['quantity']; ?></td>
                                            <td class="text-right">R <?php echo number_format($item['unit_price'], 2); ?></td>
                                            <td class="text-right">R <?php echo number_format($item['subtotal'], 2); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-right"><strong>Total</strong></td>
                                            <td class="text-right"><strong>R <?php echo number_format($itemSubtotalSum, 2); ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>

                                <?php if (!empty($order['special_requests'])): ?>
                                    <div class="special-requests-box">
                                        <strong><i class="fas fa-comment-dots"></i> Special Requests:</strong>
                                        <p><?php echo nl2br(escapeUpdateStatusOutput($order['special_requests'])); ?></p>
                                    </div>
                                <?php endif; ?>

                                <div class="status-update-section">
                                    <h4>
                                        <i class="fas fa-sync-alt"></i>
                                        Update Order Status
                                    </h4>

                                    <?php if (!$vendor['is_open'] && $order['order_status'] !== ORDER_STATUS_COMPLETED && $order['order_status'] !== ORDER_STATUS_CANCELLED): ?>
                                        <div class="shop-closed-warning">
                                            <i class="fas fa-door-closed"></i>
                                            <p>Your shop is currently closed. You can only cancel pending orders. Open your shop to process other status updates.</p>
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                    $availableOptions = getAvailableStatusOptions($order['order_status'], $vendor['is_open']);
                                    $hasOptions = !empty($availableOptions);
                                    $isCompletedOrCancelled = in_array($order['order_status'], array(ORDER_STATUS_COMPLETED, ORDER_STATUS_CANCELLED));
                                    ?>

                                    <?php if ($isCompletedOrCancelled): ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            <div class="alert-content">
                                                <div class="alert-title">Information</div>
                                                <div class="alert-message">This order has been <?php echo $order['order_status'] === ORDER_STATUS_COMPLETED ? 'completed' : 'cancelled'; ?> and cannot be modified further.</div>
                                            </div>
                                        </div>
                                        <div class="form-actions">
                                            <a href="orders.php" class="btn btn-primary">
                                                <i class="fas fa-arrow-left"></i> Back to Orders
                                            </a>
                                        </div>

                                    <?php elseif ($hasOptions): ?>
                                        <form method="POST" action="" class="status-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">

                                            <div class="form-group">
                                                <label class="form-label" for="new_status">Select New Status</label>
                                                <select id="new_status" name="new_status" required>
                                                    <option value="">-- Select Status --</option>
                                                    <?php foreach ($availableOptions as $statusValue => $statusLabel): ?>
                                                        <option value="<?php echo $statusValue; ?>">
                                                            <?php echo $statusLabel; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="form-actions">
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Update Status
                                                </button>
                                                <a href="orders.php" class="btn btn-outline">
                                                    <i class="fas fa-arrow-left"></i> Back to Orders
                                                </a>
                                            </div>
                                        </form>

                                    <?php else: ?>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle"></i>
                                            <div class="alert-content">
                                                <div class="alert-title">Information</div>
                                                <div class="alert-message">No further status updates are available for this order.</div>
                                            </div>
                                        </div>
                                        <div class="form-actions">
                                            <a href="orders.php" class="btn btn-primary">
                                                <i class="fas fa-arrow-left"></i> Back to Orders
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
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