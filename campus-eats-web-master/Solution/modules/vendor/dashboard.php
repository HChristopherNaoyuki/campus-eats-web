<?php
/**
 * Vendor Dashboard Page - Matching Mockup 25.png
 *
 * This page serves as the main dashboard for vendors.
 *
 * CORRECTIONS (Version 18.0 - Visual Parity):
 * - Updated layout to match mockup 25.png
 * - Added shop status card with toggle
 * - Added KPI cards (Menu Items, Orders, Revenue, Status)
 * - Added revenue summary section
 * - Added incoming orders section
 * - Added recent completed orders section
 * - Improved responsive behavior
 * - Removed inline styles and moved to vendor.css
 *
 * SOURCE: Mockup - 25.png
 *
 * @version 18.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();
requireVendor();

$db = getDB();
$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();

$vendor = $db->fetchOne
(
    "SELECT vendor_id, vendor_name, description, is_open, is_approved
     FROM vendors WHERE vendor_user_id = :user_id",
    array('user_id' => $currentUser['user_id'])
);

if (!$vendor)
{
    writeLog("Vendor dashboard: No vendor profile found for user ID " . $currentUser['user_id'], "VENDOR");
    header('Location: ' . BASE_URL . '/modules/auth/logout.php');
    exit();
}

$_SESSION['vendor_id'] = $vendor['vendor_id'];
$_SESSION['vendor_name'] = $vendor['vendor_name'];
$_SESSION['vendor_is_open'] = $vendor['is_open'];

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_shop']))
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (validateCsrfToken($submittedToken))
    {
        $newStatus = $vendor['is_open'] ? 0 : 1;
        $db->executeQuery
        (
            "UPDATE vendors SET is_open = :is_open WHERE vendor_id = :vendor_id",
            array('is_open' => $newStatus, 'vendor_id' => $vendor['vendor_id'])
        );
        $vendor['is_open'] = $newStatus;
        $_SESSION['vendor_is_open'] = $newStatus;

        writeLog(
            "Vendor ID {$vendor['vendor_id']} toggled shop status to " . ($newStatus ? 'open' : 'closed'),
            "VENDOR"
        );

        $message = 'Shop ' . ($newStatus ? 'opened' : 'closed') . ' successfully.';
        $csrfToken = getCsrfToken();
    }
    else
    {
        $error = 'Security validation failed. Please refresh the page.';
        writeLog("Vendor dashboard CSRF validation failed for vendor ID: {$vendor['vendor_id']}", "VENDOR");
    }
}

try
{
    $totalMenuItems = (int)($db->fetchOne
    (
        "SELECT COUNT(*) as count FROM menu_items WHERE vendor_id = :vendor_id",
        array('vendor_id' => $vendor['vendor_id'])
    )['count'] ?? 0);

    $pendingOrders = (int)($db->fetchOne
    (
        "SELECT COUNT(*) as count FROM orders
         WHERE vendor_id = :vendor_id
         AND order_status IN ('pending', 'accepted', 'preparing', 'ready')",
        array('vendor_id' => $vendor['vendor_id'])
    )['count'] ?? 0);

    $todayRevenue = (float)($db->fetchOne
    (
        "SELECT COALESCE(SUM(total_amount), 0) as total
         FROM orders
         WHERE vendor_id = :vendor_id
           AND DATE(order_placed_at) = CURDATE()
           AND order_status = 'completed'",
        array('vendor_id' => $vendor['vendor_id'])
    )['total'] ?? 0);

    $weekRevenue = (float)($db->fetchOne
    (
        "SELECT COALESCE(SUM(total_amount), 0) as total
         FROM orders
         WHERE vendor_id = :vendor_id
           AND YEARWEEK(order_placed_at) = YEARWEEK(CURDATE())
           AND order_status = 'completed'",
        array('vendor_id' => $vendor['vendor_id'])
    )['total'] ?? 0);

    $monthRevenue = (float)($db->fetchOne
    (
        "SELECT COALESCE(SUM(total_amount), 0) as total
         FROM orders
         WHERE vendor_id = :vendor_id
           AND MONTH(order_placed_at) = MONTH(CURDATE())
           AND YEAR(order_placed_at) = YEAR(CURDATE())
           AND order_status = 'completed'",
        array('vendor_id' => $vendor['vendor_id'])
    )['total'] ?? 0);

    $totalRevenue = (float)($db->fetchOne
    (
        "SELECT COALESCE(SUM(total_amount), 0) as total
         FROM orders
         WHERE vendor_id = :vendor_id
           AND order_status = 'completed'",
        array('vendor_id' => $vendor['vendor_id'])
    )['total'] ?? 0);

    $avgOrderValue = (float)($db->fetchOne
    (
        "SELECT COALESCE(AVG(total_amount), 0) as avg_value
         FROM orders
         WHERE vendor_id = :vendor_id
           AND order_status = 'completed'",
        array('vendor_id' => $vendor['vendor_id'])
    )['avg_value'] ?? 0);

    $incomingOrders = $db->fetchAll
    (
        "SELECT o.order_id, o.order_number, o.total_amount, o.order_placed_at,
                u.full_name as customer_name
         FROM orders o
         JOIN users u ON o.user_id = u.user_id
         WHERE o.vendor_id = :vendor_id
           AND o.order_status IN ('pending', 'accepted')
         ORDER BY o.order_placed_at ASC
         LIMIT 5",
        array('vendor_id' => $vendor['vendor_id'])
    );

    $recentCompletedOrders = $db->fetchAll
    (
        "SELECT o.order_id, o.order_number, o.total_amount, o.order_placed_at,
                o.order_status, u.full_name as customer_name
         FROM orders o
         JOIN users u ON o.user_id = u.user_id
         WHERE o.vendor_id = :vendor_id
           AND o.order_status = 'completed'
         ORDER BY o.order_placed_at DESC
         LIMIT 5",
        array('vendor_id' => $vendor['vendor_id'])
    );
}
catch (Exception $e)
{
    writeLog("Vendor dashboard error: " . $e->getMessage(), "VENDOR");
    $error = 'Unable to load dashboard data.';
    $totalMenuItems = 0;
    $pendingOrders = 0;
    $todayRevenue = 0;
    $weekRevenue = 0;
    $monthRevenue = 0;
    $totalRevenue = 0;
    $avgOrderValue = 0;
    $incomingOrders = array();
    $recentCompletedOrders = array();
}

function escapeVendorDash($string)
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
    <meta name="csrf-token" content="<?php echo escapeVendorDash($csrfToken); ?>">
    <title>Vendor Dashboard · Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/vendor.css">
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/vendor_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="vendor-content">
                <div class="container">
                    <!-- Shop Status Card - Matching Mockup 25.png -->
                    <div class="shop-status-card">
                        <div class="shop-status-info">
                            <i class="fas <?php echo $vendor['is_open'] ? 'fa-door-open' : 'fa-door-closed'; ?>"></i>
                            <div>
                                <h3>Welcome back, <?php echo escapeVendorDash($vendor['vendor_name']); ?></h3>
                                <p class="text-small">
                                    Your shop is currently <strong><?php echo $vendor['is_open'] ? 'Open' : 'Closed'; ?></strong>
                                </p>
                            </div>
                        </div>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?php echo escapeVendorDash($csrfToken); ?>">
                            <input type="hidden" name="toggle_shop" value="1">
                            <button type="submit" class="btn <?php echo $vendor['is_open'] ? 'btn-warning' : 'btn-success'; ?>">
                                <i class="fas <?php echo $vendor['is_open'] ? 'fa-door-closed' : 'fa-door-open'; ?>"></i>
                                <?php echo $vendor['is_open'] ? 'Close Shop' : 'Open Shop'; ?>
                            </button>
                        </form>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Success</div>
                                <div class="alert-message"><?php echo escapeVendorDash($message); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeVendorDash($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- KPI Cards - Matching Mockup 25.png -->
                    <div class="vendor-stats-grid">
                        <div class="vendor-stat-card">
                            <div class="vendor-stat-icon"><i class="fas fa-utensils"></i></div>
                            <div>
                                <div class="vendor-stat-value"><?php echo $totalMenuItems; ?></div>
                                <div class="vendor-stat-label">Menu Items</div>
                            </div>
                        </div>
                        <div class="vendor-stat-card">
                            <div class="vendor-stat-icon"><i class="fas fa-shopping-cart"></i></div>
                            <div>
                                <div class="vendor-stat-value"><?php echo $pendingOrders; ?></div>
                                <div class="vendor-stat-label">Active Orders</div>
                            </div>
                        </div>
                        <div class="vendor-stat-card">
                            <div class="vendor-stat-icon"><i class="fas fa-chart-line"></i></div>
                            <div>
                                <div class="vendor-stat-value">R <?php echo number_format($todayRevenue, 2); ?></div>
                                <div class="vendor-stat-label">Today's Revenue</div>
                            </div>
                        </div>
                        <div class="vendor-stat-card">
                            <div class="vendor-stat-icon"><i class="fas fa-store"></i></div>
                            <div>
                                <div class="vendor-stat-value"><?php echo $vendor['is_open'] ? 'Open' : 'Closed'; ?></div>
                                <div class="vendor-stat-label">Shop Status</div>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue Summary - Matching Mockup 25.png -->
                    <div class="revenue-stats">
                        <div class="revenue-item">
                            <div class="revenue-label">Today</div>
                            <div class="revenue-value">R <?php echo number_format($todayRevenue, 2); ?></div>
                        </div>
                        <div class="revenue-item">
                            <div class="revenue-label">This Week</div>
                            <div class="revenue-value">R <?php echo number_format($weekRevenue, 2); ?></div>
                        </div>
                        <div class="revenue-item">
                            <div class="revenue-label">This Month</div>
                            <div class="revenue-value">R <?php echo number_format($monthRevenue, 2); ?></div>
                        </div>
                        <div class="revenue-item">
                            <div class="revenue-label">Total Revenue</div>
                            <div class="revenue-value">R <?php echo number_format($totalRevenue, 2); ?></div>
                        </div>
                    </div>

                    <!-- Dashboard Grid - Matching Mockup 25.png -->
                    <div class="vendor-dashboard-grid">
                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h3><i class="fas fa-clock"></i> Incoming Orders</h3>
                                <a href="orders.php" class="btn btn-outline btn-sm">View All</a>
                            </div>
                            <div class="dashboard-card-body">
                                <?php if (empty($incomingOrders)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-receipt"></i>
                                        <p>No pending orders.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-container">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Order #</th>
                                                    <th>Customer</th>
                                                    <th>Amount</th>
                                                    <th>Time</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($incomingOrders as $order): ?>
                                                <tr>
                                                    <td><?php echo escapeVendorDash($order['order_number']); ?></td>
                                                    <td><?php echo escapeVendorDash($order['customer_name']); ?></td>
                                                    <td>R <?php echo number_format($order['total_amount'], 2); ?></td>
                                                    <td><?php echo date('g:i A', strtotime($order['order_placed_at'])); ?></td>
                                                    <td><a href="orders.php" class="btn btn-primary btn-sm">View</a></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h3><i class="fas fa-check-circle"></i> Recent Completed Orders</h3>
                            </div>
                            <div class="dashboard-card-body">
                                <?php if (empty($recentCompletedOrders)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-receipt"></i>
                                        <p>No completed orders yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-container">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Order #</th>
                                                    <th>Customer</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentCompletedOrders as $order): ?>
                                                <tr>
                                                    <td><?php echo escapeVendorDash($order['order_number']); ?></td>
                                                    <td><?php echo escapeVendorDash($order['customer_name']); ?></td>
                                                    <td>R <?php echo number_format($order['total_amount'], 2); ?></td>
                                                    <td><span class="badge status-completed">Completed</span></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
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