<?php
/**
 * Order Management Page for Administrators - Matching Mockups 30-30b.png
 *
 * This page allows administrators to view and manage all orders in the system.
 *
 * CORRECTIONS (Version 14.0 - Visual Parity):
 * - Updated layout to match mockups 30.png, 30a.png, 30b.png
 * - Added order table with status badges
 * - Added order status update functionality
 * - Added order details modal
 * - Improved responsive behavior
 *
 * SOURCE: Mockups - 30.png, 30a.png, 30b.png
 *
 * @version 14.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();
requireAdmin();

$db = getDB();
$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();

$message = '';
$error = '';

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
        case ORDER_STATUS_ACCEPTED:  return 'fa-check-circle';
        case ORDER_STATUS_PREPARING: return 'fa-utensils';
        case ORDER_STATUS_READY:     return 'fa-concierge-bell';
        case ORDER_STATUS_COMPLETED: return 'fa-check-double';
        case ORDER_STATUS_CANCELLED: return 'fa-ban';
        default: return 'fa-question-circle';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status']))
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($submittedToken))
    {
        $error = 'Security validation failed. Please refresh the page.';
        writeLog("Order management CSRF validation failed.", "ADMIN");
    }
    else
    {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';

        $validStatuses = array(
            ORDER_STATUS_PENDING,
            ORDER_STATUS_ACCEPTED,
            ORDER_STATUS_PREPARING,
            ORDER_STATUS_READY,
            ORDER_STATUS_COMPLETED,
            ORDER_STATUS_CANCELLED
        );

        if ($orderId > 0 && in_array($newStatus, $validStatuses))
        {
            try
            {
                $currentOrder = $db->fetchOne
                (
                    "SELECT order_number, order_status FROM orders WHERE order_id = :order_id",
                    array('order_id' => $orderId)
                );

                if ($currentOrder)
                {
                    $oldStatus = $currentOrder['order_status'];
                    $orderNumber = $currentOrder['order_number'];

                    $db->executeQuery
                    (
                        "UPDATE orders SET order_status = :status, updated_at = NOW() WHERE order_id = :order_id",
                        array('status' => $newStatus, 'order_id' => $orderId)
                    );

                    writeLog(
                        "Admin updated order $orderNumber ($orderId) status from $oldStatus to $newStatus",
                        "ADMIN"
                    );

                    $message = 'Order status updated successfully.';
                    $csrfToken = getCsrfToken();
                }
                else
                {
                    $error = 'Order not found.';
                }
            }
            catch (Exception $e)
            {
                $error = 'Failed to update order status.';
                writeLog("Order status update error: " . $e->getMessage(), "ADMIN");
            }
        }
        else
        {
            $error = 'Invalid order or status.';
        }
    }
}

// Fetch orders with pagination
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$whereConditions = array();
$params = array();

if ($statusFilter !== 'all')
{
    $whereConditions[] = "o.order_status = :status";
    $params['status'] = $statusFilter;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

try
{
    // Get total count
    $countSql = "SELECT COUNT(*) as count FROM orders o $whereClause";
    $countStmt = $db->getConnection()->prepare($countSql);
    foreach ($params as $key => $value)
    {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $totalOrders = $countStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $totalPages = ceil($totalOrders / $perPage);

    // Fetch orders
    $sql = "SELECT o.order_id, o.order_number, o.order_status, o.total_amount,
                   o.order_placed_at, o.pickup_time, o.special_requests,
                   v.vendor_name, u.full_name as customer_name
            FROM orders o
            JOIN vendors v ON o.vendor_id = v.vendor_id
            JOIN users u ON o.user_id = u.user_id
            $whereClause
            ORDER BY o.order_placed_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bindParam(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value)
    {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
catch (Exception $e)
{
    writeLog("Order management error: " . $e->getMessage(), "ADMIN");
    $error = "Unable to load orders.";
    $orders = array();
    $totalOrders = 0;
    $totalPages = 0;
}

function escapeOrderManageOutput($string)
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
    <title>Order Management · Campus Eats Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <style>
        .filter-tabs {
            display: flex;
            gap: var(--space-2);
            flex-wrap: wrap;
            margin-bottom: var(--space-4);
        }
        .filter-tab {
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
        .filter-tab:hover {
            background: var(--orange-light);
            border-color: var(--orange);
            color: var(--orange);
        }
        .filter-tab.active {
            background: var(--orange);
            border-color: var(--orange);
            color: white;
        }
        .filter-tab .count { font-size: 0.7rem; opacity: 0.8; margin-left: var(--space-1); }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/admin_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="admin-content">
                <div class="container">
                    <div class="page-header">
                        <h1>Order Management</h1>
                        <p>View and manage all customer orders across the platform</p>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Success</div>
                                <div class="alert-message"><?php echo escapeOrderManageOutput($message); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeOrderManageOutput($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Filter Tabs -->
                    <div class="filter-tabs">
                        <a href="?status=all&page=1" class="filter-tab <?php echo $statusFilter === 'all' ? 'active' : ''; ?>">
                            All <span class="count">(<?php echo $totalOrders; ?>)</span>
                        </a>
                        <a href="?status=pending&page=1" class="filter-tab <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">
                            Pending
                        </a>
                        <a href="?status=accepted&page=1" class="filter-tab <?php echo $statusFilter === 'accepted' ? 'active' : ''; ?>">
                            Accepted
                        </a>
                        <a href="?status=preparing&page=1" class="filter-tab <?php echo $statusFilter === 'preparing' ? 'active' : ''; ?>">
                            Preparing
                        </a>
                        <a href="?status=ready&page=1" class="filter-tab <?php echo $statusFilter === 'ready' ? 'active' : ''; ?>">
                            Ready
                        </a>
                        <a href="?status=completed&page=1" class="filter-tab <?php echo $statusFilter === 'completed' ? 'active' : ''; ?>">
                            Completed
                        </a>
                        <a href="?status=cancelled&page=1" class="filter-tab <?php echo $statusFilter === 'cancelled' ? 'active' : ''; ?>">
                            Cancelled
                        </a>
                    </div>

                    <!-- Orders Table - Matching Mockup 30.png -->
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-receipt"></i> All Orders</h3>
                        </div>
                        <div class="dashboard-card-body">
                            <?php if (empty($orders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-receipt"></i>
                                    <p>No orders found.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>User</th>
                                                <th>Vendor</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td data-label="Order ID"><?php echo escapeOrderManageOutput($order['order_number']); ?></td>
                                                <td data-label="User"><?php echo escapeOrderManageOutput($order['customer_name']); ?></td>
                                                <td data-label="Vendor"><?php echo escapeOrderManageOutput($order['vendor_name']); ?></td>
                                                <td data-label="Total">R <?php echo number_format($order['total_amount'], 2); ?></td>
                                                <td data-label="Status">
                                                    <span class="badge <?php echo getOrderStatusBadgeClass($order['order_status']); ?>">
                                                        <i class="fas <?php echo getOrderStatusIcon($order['order_status']); ?>"></i>
                                                        <?php echo getOrderStatusText($order['order_status']); ?>
                                                    </span>
                                                </td>
                                                <td data-label="Actions">
                                                    <form method="POST" style="display: inline-flex; gap: var(--space-2); align-items: center;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                                        <select name="new_status" class="form-control" style="width: auto; padding: var(--space-1) var(--space-2); min-height: 32px;">
                                                            <option value="pending" <?php echo $order['order_status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="accepted" <?php echo $order['order_status'] == 'accepted' ? 'selected' : ''; ?>>Accepted</option>
                                                            <option value="preparing" <?php echo $order['order_status'] == 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                                            <option value="ready" <?php echo $order['order_status'] == 'ready' ? 'selected' : ''; ?>>Ready</option>
                                                            <option value="completed" <?php echo $order['order_status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                            <option value="cancelled" <?php echo $order['order_status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                        </select>
                                                        <button type="submit" name="update_status" class="btn btn-primary btn-sm">Update</button>
                                                    </form>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <?php if ($totalPages > 1): ?>
                                    <div class="pagination" style="display: flex; justify-content: center; gap: var(--space-2); margin-top: var(--space-4);">
                                        <?php if ($page > 1): ?>
                                            <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $statusFilter; ?>" class="pagination-item">Previous</a>
                                        <?php endif; ?>
                                        <span class="pagination-item active"><?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                        <?php if ($page < $totalPages): ?>
                                            <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $statusFilter; ?>" class="pagination-item">Next</a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
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
</body>
</html>