<?php
/**
 * Order Tracking Page for Students
 *
 * This page displays real-time order tracking information.
 *
 * SOURCE: campus-eats-process-document.pdf (Section 6.1 - Track order status)
 * SOURCE: Mockups - Order tracking design
 *
 * @version 15.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

// Start secure session and require student role
startSecureSession();
requireStudent();

$db = getDB();
$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();

function getTableColumns($db, $tableName)
{
    $allowedTables = array('orders', 'payments', 'users', 'vendors', 'menu_items', 'order_items');

    if (!in_array($tableName, $allowedTables, true))
    {
        writeLog("Attempted to access non-allowed table: $tableName", "SECURITY");
        return array();
    }

    try
    {
        $columns = $db->fetchAll("SHOW COLUMNS FROM `$tableName`");
        $columnNames = array();

        foreach ($columns as $column)
        {
            $columnNames[] = $column['Field'];
        }

        return $columnNames;
    }
    catch (Exception $e)
    {
        writeLog("Failed to get table columns for $tableName: " . $e->getMessage(), "ORDER_TRACKING");
        return array();
    }
}

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($orderId <= 0)
{
    writeLog("Order tracking: Invalid order ID: $orderId", "ORDER_TRACKING");
    header('Location: order_history.php');
    exit();
}

$orderColumns = getTableColumns($db, 'orders');

$selectFields = array(
    'o.order_id',
    'o.order_number',
    'o.order_status',
    'o.total_amount',
    'o.pickup_time',
    'o.special_requests',
    'o.order_placed_at',
    'v.vendor_name',
    'v.vendor_id'
);

if (in_array('subtotal', $orderColumns))
{
    $selectFields[] = 'o.subtotal';
}
else
{
    $selectFields[] = '0 as subtotal';
}

if (in_array('service_fee', $orderColumns))
{
    $selectFields[] = 'o.service_fee';
}
else
{
    $selectFields[] = '0 as service_fee';
}

if (in_array('tax', $orderColumns))
{
    $selectFields[] = 'o.tax';
}
else
{
    $selectFields[] = '0 as tax';
}

if (in_array('rounding_adjustment', $orderColumns))
{
    $selectFields[] = 'o.rounding_adjustment';
}
else
{
    $selectFields[] = '0 as rounding_adjustment';
}

if (in_array('transaction_id', $orderColumns))
{
    $selectFields[] = 'o.transaction_id';
}
else
{
    $selectFields[] = 'NULL as transaction_id';
}

$selectClause = implode(', ', $selectFields);

$order = $db->fetchOne
(
    "SELECT $selectClause
     FROM orders o
     JOIN vendors v ON o.vendor_id = v.vendor_id
     WHERE o.order_id = :order_id AND o.user_id = :user_id",
    array(
        'order_id' => $orderId,
        'user_id' => $currentUser['user_id']
    )
);

if (!$order)
{
    writeLog("Order tracking: Order not found or access denied for order ID: $orderId", "ORDER_TRACKING");
    header('Location: order_history.php');
    exit();
}

$orderItems = $db->fetchAll
(
    "SELECT oi.quantity, oi.unit_price, oi.subtotal, mi.item_name
     FROM order_items oi
     JOIN menu_items mi ON oi.item_id = mi.item_id
     WHERE oi.order_id = :order_id",
    array('order_id' => $orderId)
);

$progressPercent = 0;
$statusMessage = '';
$statusIcon = '';

switch ($order['order_status'])
{
    case ORDER_STATUS_PENDING:
        $progressPercent = 0;
        $statusMessage = 'Your order has been received and is awaiting vendor confirmation.';
        $statusIcon = 'fa-clock';
        break;
    case ORDER_STATUS_ACCEPTED:
        $progressPercent = 25;
        $statusMessage = 'Your order has been accepted by the vendor.';
        $statusIcon = 'fa-check';
        break;
    case ORDER_STATUS_PREPARING:
        $progressPercent = 50;
        $statusMessage = 'Your order is being prepared.';
        $statusIcon = 'fa-utensils';
        break;
    case ORDER_STATUS_READY:
        $progressPercent = 75;
        $statusMessage = 'Your order is ready for pickup!';
        $statusIcon = 'fa-concierge-bell';
        break;
    case ORDER_STATUS_COMPLETED:
        $progressPercent = 100;
        $statusMessage = 'Thank you for using Campus Eats. Order completed.';
        $statusIcon = 'fa-check-double';
        break;
    case ORDER_STATUS_CANCELLED:
        $progressPercent = 0;
        $statusMessage = 'This order has been cancelled.';
        $statusIcon = 'fa-ban';
        break;
    default:
        $progressPercent = 0;
        $statusMessage = 'Status update pending.';
        $statusIcon = 'fa-question';
        break;
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
        case ORDER_STATUS_PENDING:   return 'Pending Confirmation';
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

function escapeTrackingOutput($string)
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
    <title>Track Order · Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <style>
        .tracking-container
        {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid var(--gray-100);
        }

        .tracking-header
        {
            background: linear-gradient(135deg, var(--orange), var(--orange-dark));
            color: white;
            padding: var(--space-4) var(--space-6);
            text-align: center;
        }

        .tracking-header h1 { color: white; margin-bottom: var(--space-2); font-size: 1.5rem; }
        .tracking-header p { opacity: 0.9; margin-bottom: 0; }

        .order-info-card
        {
            padding: var(--space-4) var(--space-6);
            border-bottom: 1px solid var(--gray-200);
        }

        .order-info-grid
        {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--space-4);
        }

        .order-info-item
        {
            display: flex;
            flex-direction: column;
        }

        .order-info-label
        {
            font-size: 0.6875rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: var(--space-1);
        }

        .order-info-value
        {
            font-size: 0.9375rem;
            font-weight: 500;
            color: var(--gray-800);
        }

        .transaction-id
        {
            font-family: monospace;
            font-size: 0.75rem;
            background: var(--gray-100);
            padding: var(--space-1) var(--space-2);
            border-radius: var(--radius-sm);
            display: inline-block;
        }

        .tracking-steps
        {
            padding: var(--space-4) var(--space-6);
            position: relative;
        }

        .steps-container
        {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: var(--space-5) 0;
        }

        .tracking-step
        {
            text-align: center;
            flex: 1;
            position: relative;
            z-index: var(--z-low);
        }

        .step-icon
        {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--space-3);
            font-size: 1.25rem;
            color: var(--gray-500);
            transition: all var(--transition-base);
        }

        .tracking-step.completed .step-icon
        {
            background: var(--success);
            color: white;
        }

        .tracking-step.active .step-icon
        {
            background: var(--orange);
            color: white;
            box-shadow: 0 0 0 4px var(--orange-light);
        }

        .tracking-step p { margin: 0; font-weight: 500; font-size: 0.8125rem; }
        .tracking-step small { font-size: 0.6875rem; color: var(--gray-500); }

        .progress-bar-container
        {
            position: absolute;
            top: 28px;
            left: 0;
            width: 100%;
            height: 4px;
            background-color: var(--gray-200);
            z-index: var(--z-negative);
            border-radius: var(--radius-full);
        }

        .progress-fill
        {
            height: 100%;
            background: linear-gradient(90deg, var(--orange), var(--orange-dark));
            width: 0%;
            transition: width var(--transition-slow);
            border-radius: var(--radius-full);
        }

        .status-message
        {
            text-align: center;
            padding: var(--space-4);
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            margin-top: var(--space-4);
        }

        .status-message i { font-size: 2rem; color: var(--orange); margin-bottom: var(--space-2); }
        .status-message p { margin: 0; font-weight: 500; color: var(--gray-700); }

        .items-section { padding: var(--space-4) var(--space-6); border-top: 1px solid var(--gray-200); }

        .items-table
        {
            width: 100%;
            border-collapse: collapse;
            margin-top: var(--space-4);
        }

        .items-table th,
        .items-table td
        {
            padding: var(--space-2) var(--space-3);
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .items-table th
        {
            background: var(--gray-50);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--gray-600);
        }

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
            margin-top: var(--space-4);
        }

        .special-requests p { margin-top: var(--space-2); font-size: 0.875rem; color: var(--gray-700); }

        .action-buttons
        {
            padding: var(--space-4) var(--space-6);
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: var(--space-3);
            justify-content: center;
        }

        .auto-refresh-note
        {
            text-align: center;
            font-size: 0.75rem;
            color: var(--gray-500);
            padding: var(--space-3);
            border-top: 1px solid var(--gray-200);
        }

        .status-pending { background: var(--warning-bg); color: var(--warning-text); }
        .status-accepted { background: var(--info-bg); color: var(--info-text); }
        .status-preparing { background: #E8F0FE; color: #007AFF; }
        .status-ready { background: #E8F5E9; color: #1B7A3D; }
        .status-completed { background: var(--gray-200); color: var(--gray-700); }
        .status-cancelled { background: var(--error-bg); color: var(--error-text); }

        @media (max-width: 768px)
        {
            .tracking-step p { font-size: 0.7rem; }
            .step-icon { width: 44px; height: 44px; font-size: 1rem; }
            .progress-bar-container { top: 22px; }
            .order-info-grid { grid-template-columns: 1fr; gap: var(--space-2); }

            .items-table,
            .items-table tbody,
            .items-table tr,
            .items-table td
            {
                display: block;
            }

            .items-table thead { display: none; }

            .items-table tr
            {
                margin-bottom: var(--space-2);
                padding: var(--space-2);
                border: 1px solid var(--gray-200);
                border-radius: var(--radius-md);
            }

            .items-table td
            {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: var(--space-1) var(--space-2);
                border-bottom: none;
            }

            .items-table td::before
            {
                content: attr(data-label);
                font-weight: 600;
                font-size: 0.75rem;
                color: var(--gray-500);
                margin-right: var(--space-3);
            }

            .action-buttons { flex-direction: column; }
            .action-buttons .btn { width: 100%; }
            .tracking-header { padding: var(--space-3) var(--space-4); }
            .tracking-header h1 { font-size: 1.25rem; }
        }

        @media (max-width: 480px)
        {
            .tracking-steps { padding: var(--space-3) var(--space-4); }
            .steps-container { margin: var(--space-3) 0; }
            .step-icon { width: 36px; height: 36px; font-size: 0.875rem; }
            .progress-bar-container { top: 18px; }
            .order-info-card { padding: var(--space-3) var(--space-4); }
            .items-section { padding: var(--space-3) var(--space-4); }
            .action-buttons { padding: var(--space-3) var(--space-4); }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/student_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="student-content">
                <div class="container">
                    <div class="tracking-container">
                        <div class="tracking-header">
                            <h1><i class="fas fa-truck"></i> Track Your Order</h1>
                            <p>Real-time updates for order #<?php echo escapeTrackingOutput($order['order_number']); ?></p>
                        </div>

                        <div class="order-info-card">
                            <div class="order-info-grid">
                                <div class="order-info-item">
                                    <span class="order-info-label">Vendor</span>
                                    <span class="order-info-value">
                                        <i class="fas fa-store"></i>
                                        <?php echo escapeTrackingOutput($order['vendor_name']); ?>
                                    </span>
                                </div>
                                <div class="order-info-item">
                                    <span class="order-info-label">Order Placed</span>
                                    <span class="order-info-value">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo date('F j, Y \a\t g:i A', strtotime($order['order_placed_at'])); ?>
                                    </span>
                                </div>
                                <?php if (!empty($order['pickup_time'])): ?>
                                <div class="order-info-item">
                                    <span class="order-info-label">Pickup Time</span>
                                    <span class="order-info-value">
                                        <i class="fas fa-clock"></i>
                                        <?php echo escapeTrackingOutput($order['pickup_time']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($order['transaction_id']) && $order['transaction_id'] !== 'NULL'): ?>
                                <div class="order-info-item">
                                    <span class="order-info-label">Transaction ID</span>
                                    <span class="order-info-value transaction-id">
                                        <i class="fas fa-hashtag"></i>
                                        <?php echo escapeTrackingOutput($order['transaction_id']); ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="tracking-steps">
                            <div class="steps-container">
                                <div class="tracking-step <?php echo $progressPercent >= 0 ? 'completed' : ''; ?> <?php echo $order['order_status'] === ORDER_STATUS_PENDING ? 'active' : ''; ?>">
                                    <div class="step-icon"><i class="fas fa-receipt"></i></div>
                                    <p>Order Placed</p>
                                    <small><?php echo date('g:i A', strtotime($order['order_placed_at'])); ?></small>
                                </div>
                                <div class="tracking-step <?php echo $progressPercent >= 25 ? 'completed' : ''; ?> <?php echo $order['order_status'] === ORDER_STATUS_ACCEPTED ? 'active' : ''; ?>">
                                    <div class="step-icon"><i class="fas fa-check"></i></div>
                                    <p>Accepted</p>
                                </div>
                                <div class="tracking-step <?php echo $progressPercent >= 50 ? 'completed' : ''; ?> <?php echo $order['order_status'] === ORDER_STATUS_PREPARING ? 'active' : ''; ?>">
                                    <div class="step-icon"><i class="fas fa-utensils"></i></div>
                                    <p>Preparing</p>
                                </div>
                                <div class="tracking-step <?php echo $progressPercent >= 75 ? 'completed' : ''; ?> <?php echo $order['order_status'] === ORDER_STATUS_READY ? 'active' : ''; ?>">
                                    <div class="step-icon"><i class="fas fa-concierge-bell"></i></div>
                                    <p>Ready</p>
                                </div>
                                <div class="tracking-step <?php echo $progressPercent >= 100 ? 'completed' : ''; ?> <?php echo $order['order_status'] === ORDER_STATUS_COMPLETED ? 'active' : ''; ?>">
                                    <div class="step-icon"><i class="fas fa-check-double"></i></div>
                                    <p>Completed</p>
                                </div>
                                <div class="progress-bar-container">
                                    <div class="progress-fill" style="width: <?php echo $progressPercent; ?>%;"></div>
                                </div>
                            </div>

                            <div class="status-message">
                                <i class="fas <?php echo $statusIcon; ?>"></i>
                                <p><?php echo $statusMessage; ?></p>
                            </div>
                        </div>

                        <div class="items-section">
                            <h3><i class="fas fa-shopping-cart"></i> Order Items</h3>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td data-label="Item"><?php echo escapeTrackingOutput($item['item_name']); ?></td>
                                        <td data-label="Quantity"><?php echo $item['quantity']; ?></td>
                                        <td data-label="Unit Price">R <?php echo number_format($item['unit_price'], 2); ?></td>
                                        <td data-label="Subtotal">R <?php echo number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="receipt-summary">
                            <?php if (isset($order['subtotal']) && $order['subtotal'] > 0): ?>
                            <div class="receipt-row">
                                <span>Subtotal:</span>
                                <span>R <?php echo number_format($order['subtotal'], 2); ?></span>
                            </div>
                            <?php endif; ?>

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
                                <span>Total Paid:</span>
                                <span>R <?php echo number_format($order['total_amount'], 2); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($order['special_requests'])): ?>
                        <div class="special-requests">
                            <strong><i class="fas fa-comment-dots"></i> Special Requests:</strong>
                            <p><?php echo nl2br(escapeTrackingOutput($order['special_requests'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="action-buttons">
                            <a href="order_history.php" class="btn btn-outline">
                                <i class="fas fa-history"></i> Back to Orders
                            </a>
                            <?php if ($order['order_status'] === ORDER_STATUS_COMPLETED): ?>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-store"></i> Order Again
                            </a>
                            <?php endif; ?>
                        </div>

                        <div class="auto-refresh-note">
                            <i class="fas fa-sync-alt"></i>
                            Page automatically refreshes every 30 seconds for real-time updates.
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <button class="sidebar-toggle" id="menuToggleBtn" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <script src="<?php echo ASSETS_URL; ?>/js/dashboard-common.js"></script>
    <script>
        let refreshTimeout = null;

        function startAutoRefresh()
        {
            if (refreshTimeout)
            {
                clearTimeout(refreshTimeout);
            }
            refreshTimeout = setTimeout(function()
            {
                location.reload();
            }, 30000);
        }

        document.addEventListener('DOMContentLoaded', function()
        {
            startAutoRefresh();
        });

        window.addEventListener('beforeunload', function()
        {
            if (refreshTimeout)
            {
                clearTimeout(refreshTimeout);
                refreshTimeout = null;
            }
        });
    </script>
</body>
</html>