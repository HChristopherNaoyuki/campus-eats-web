<?php
/**
 * Order History Page for Students and Standard Users
 *
 * This page displays a list of the user's past orders.
 *
 * CORRECTIONS (Version 17.0 - Standard User Access Fix):
 * - Replaced requireStudent() with requireStudentOrStandard()
 * - Standard users can now view their order history
 * - Fixes FUNC-02 from the scope note
 *
 * SOURCE: campus-eats-process-document.pdf (Section 6.1 - View order history)
 * SOURCE: Mockups - Order history design
 * SOURCE: Scope Note - FUNC-02
 *
 * @version 17.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

// Start secure session and require student OR standard role
startSecureSession();

// =============================================================================
// CORRECTION: FUNC-02 - Allow Standard users to view order history
// Previous code called requireStudent() which blocked Standard users.
// Standard users now have full access to order history.
// Source: Scope Note - FUNC-02
// =============================================================================
requireStudentOrStandard();

$db = getDB();
$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();

// Get filter parameter
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Build query based on filter
$statusCondition = '';
$params = array('user_id' => $currentUser['user_id']);

if ($filter === 'pending')
{
    $statusCondition = "AND o.order_status IN ('pending', 'accepted', 'preparing', 'ready')";
}
elseif ($filter === 'completed')
{
    $statusCondition = "AND o.order_status = 'completed'";
}
elseif ($filter === 'cancelled')
{
    $statusCondition = "AND o.order_status = 'cancelled'";
}

try
{
    // Get total count
    $countResult = $db->fetchOne(
        "SELECT COUNT(*) as count FROM orders o
         WHERE o.user_id = :user_id $statusCondition",
        $params
    );
    $totalOrders = (int)($countResult['count'] ?? 0);
    $totalPages = ceil($totalOrders / $perPage);

    // Fetch orders with items
    $sql = "SELECT
                o.order_id,
                o.order_number,
                o.order_status,
                o.total_amount,
                o.pickup_time,
                o.order_placed_at,
                o.special_requests,
                v.vendor_name,
                v.vendor_id
            FROM orders o
            JOIN vendors v ON o.vendor_id = v.vendor_id
            WHERE o.user_id = :user_id $statusCondition
            ORDER BY o.order_placed_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bindValue(':user_id', $currentUser['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch items for each order
    foreach ($orders as &$order)
    {
        $items = $db->fetchAll(
            "SELECT oi.quantity, oi.unit_price, oi.subtotal, mi.item_name
             FROM order_items oi
             JOIN menu_items mi ON oi.item_id = mi.item_id
             WHERE oi.order_id = :order_id",
            array('order_id' => $order['order_id'])
        );
        $order['items'] = $items;
    }
    unset($order);
}
catch (Exception $e)
{
    writeLog("Order history error: " . $e->getMessage(), "ORDER_HISTORY");
    $orders = array();
    $totalOrders = 0;
    $totalPages = 0;
    $error = "Unable to load order history. Please try again later.";
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
        case ORDER_STATUS_READY:     return 'Ready';
        case ORDER_STATUS_COMPLETED: return 'Completed';
        case ORDER_STATUS_CANCELLED: return 'Cancelled';
        default: return ucfirst($status);
    }
}

function escapeHistoryOutput($string)
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
    <meta name="csrf-token" content="<?php echo escapeHistoryOutput($csrfToken); ?>">
    <title>My Orders · Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <style>
        .filter-tabs
        {
            display: flex;
            gap: var(--space-2);
            flex-wrap: wrap;
            margin-bottom: var(--space-5);
        }

        .filter-btn
        {
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
            padding: var(--space-2) var(--space-4);
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
        }

        .filter-btn.active
        {
            background: var(--orange);
            border-color: var(--orange);
            color: white;
        }

        .order-card
        {
            background: white;
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-4);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
            transition: box-shadow var(--transition-base);
        }

        .order-card:hover
        {
            box-shadow: var(--shadow-md);
        }

        .order-card-header
        {
            background: var(--gray-50);
            padding: var(--space-3) var(--space-5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: var(--space-3);
            border-bottom: 1px solid var(--gray-200);
        }

        .order-number
        {
            font-weight: 600;
            color: var(--gray-800);
        }

        .order-date
        {
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .order-card-body
        {
            padding: var(--space-4) var(--space-5);
        }

        .order-items
        {
            margin-bottom: var(--space-4);
        }

        .order-item
        {
            display: flex;
            justify-content: space-between;
            padding: var(--space-2) 0;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.875rem;
        }

        .order-item:last-child
        {
            border-bottom: none;
        }

        .order-footer
        {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: var(--space-3);
            border-top: 1px solid var(--gray-200);
            flex-wrap: wrap;
            gap: var(--space-3);
        }

        .order-total
        {
            font-weight: 700;
            font-size: 1.125rem;
            color: var(--orange);
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
            padding: var(--space-12) var(--space-6);
            color: var(--gray-500);
        }

        .empty-state i
        {
            font-size: 3rem;
            margin-bottom: var(--space-4);
            color: var(--gray-300);
        }

        .empty-state h3
        {
            color: var(--gray-600);
            margin-bottom: var(--space-2);
        }

        .pagination
        {
            display: flex;
            justify-content: center;
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

        @media (max-width: 768px)
        {
            .filter-tabs { justify-content: center; }
            .order-card-header { flex-direction: column; text-align: center; }
            .order-footer { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/student_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="student-content">
                <div class="container">
                    <div class="page-header">
                        <h1>My Orders</h1>
                        <p>View your order history and track current orders</p>
                    </div>

                    <div class="filter-tabs">
                        <a href="?filter=all&page=1" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i> All Orders
                        </a>
                        <a href="?filter=pending&page=1" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                            <i class="fas fa-clock"></i> Active
                        </a>
                        <a href="?filter=completed&page=1" class="filter-btn <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                            <i class="fas fa-check-double"></i> Completed
                        </a>
                        <a href="?filter=cancelled&page=1" class="filter-btn <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                            <i class="fas fa-ban"></i> Cancelled
                        </a>
                    </div>

                    <?php if (empty($orders)): ?>
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <h3>No Orders Found</h3>
                            <p>You haven't placed any orders yet.</p>
                            <a href="dashboard.php" class="btn btn-primary">Browse Vendors</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <div class="order-card">
                                <div class="order-card-header">
                                    <div>
                                        <span class="order-number">
                                            <i class="fas fa-hashtag"></i>
                                            <?php echo escapeHistoryOutput($order['order_number']); ?>
                                        </span>
                                        <span class="order-date">
                                            <?php echo date('M j, Y g:i A', strtotime($order['order_placed_at'])); ?>
                                        </span>
                                    </div>
                                    <span class="badge <?php echo getOrderStatusBadgeClass($order['order_status']); ?>">
                                        <?php echo getOrderStatusText($order['order_status']); ?>
                                    </span>
                                </div>
                                <div class="order-card-body">
                                    <p style="margin-bottom: var(--space-3);">
                                        <i class="fas fa-store" style="color: var(--orange);"></i>
                                        <strong><?php echo escapeHistoryOutput($order['vendor_name']); ?></strong>
                                    </p>

                                    <div class="order-items">
                                        <?php foreach ($order['items'] as $item): ?>
                                            <div class="order-item">
                                                <span><?php echo $item['quantity']; ?>x <?php echo escapeHistoryOutput($item['item_name']); ?></span>
                                                <span>R <?php echo number_format($item['subtotal'], 2); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="order-footer">
                                        <div class="order-total">
                                            Total: R <?php echo number_format($order['total_amount'], 2); ?>
                                        </div>
                                        <div style="display: flex; gap: var(--space-2);">
                                            <a href="order_tracking.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-primary btn-sm">
                                                <i class="fas fa-truck"></i> Track Order
                                            </a>
                                            <?php if ($order['order_status'] === ORDER_STATUS_COMPLETED): ?>
                                                <a href="dashboard.php" class="btn btn-outline btn-sm">
                                                    <i class="fas fa-redo"></i> Order Again
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($totalPages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>" class="pagination-item">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                <?php endif; ?>
                                <span class="pagination-item active"><?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>" class="pagination-item">
                                        <i class="fas fa-chevron-right"></i>
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