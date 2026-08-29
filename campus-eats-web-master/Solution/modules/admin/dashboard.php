<?php
/**
 * Administrator Dashboard Page - Matching Mockups 26-26b.png
 *
 * This page shows system statistics and pending approvals for administrators.
 * Now integrates data from the Fake Restaurant API.
 *
 * CORRECTIONS (Version 21.0 - Visual Parity):
 * - Updated layout to match mockups 26.png, 26a.png, 26b.png
 * - Added stats cards with icons
 * - Added revenue summary section
 * - Added pending approvals section
 * - Added recent orders section
 * - Improved responsive behavior
 * - Removed inline styles and moved to admin.css
 *
 * SOURCE: API Documentation - Fake Restaurant API
 * SOURCE: Mockups - 26.png, 26a.png, 26b.png
 *
 * @version 21.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/api_service.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();
requireAdmin();

$db = getDB();
$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();

$message = '';
$error = '';
$apiRestaurants = array();
$apiTotalItems = 0;

// =============================================================================
// Fetch API Data
// =============================================================================

$apiService = getApiService();

try
{
    // Fetch all restaurants from the API
    $apiRestaurants = $apiService->getAllRestaurants();
    
    // Count total menu items across all restaurants
    foreach ($apiRestaurants as $restaurant)
    {
        try
        {
            $menu = $apiService->getRestaurantMenu($restaurant['restaurantID']);
            $apiTotalItems += count($menu);
        }
        catch (Exception $e)
        {
            // Skip restaurants that don't have a menu
            continue;
        }
    }
    
    writeLog("Admin dashboard: Fetched " . count($apiRestaurants) . " restaurants with $apiTotalItems items", "API");
}
catch (Exception $e)
{
    writeLog("Admin dashboard API error: " . $e->getMessage(), "API_ERROR");
    $error = "Unable to load restaurant data from API.";
}

// =============================================================================
// Handle POST actions for user/vendor approvals
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($submittedToken))
    {
        $error = 'Security validation failed. Please refresh the page.';
        writeLog("CSRF validation failed on admin dashboard POST.", "ADMIN");
    }
    else
    {
        $action = $_POST['action'] ?? '';
        $userId = (int)($_POST['user_id'] ?? 0);
        $vendorId = (int)($_POST['vendor_id'] ?? 0);

        if ($action === 'approve_user' && $userId > 0)
        {
            $db->executeQuery
            (
                "UPDATE users SET is_verified = 1 WHERE user_id = :user_id",
                array('user_id' => $userId)
            );
            $message = 'User approved successfully.';
            writeLog("Admin approved user ID: $userId", "ADMIN");
        }
        elseif ($action === 'approve_vendor' && $vendorId > 0)
        {
            $vendor = $db->fetchOne
            (
                "SELECT vendor_user_id FROM vendors WHERE vendor_id = :vendor_id",
                array('vendor_id' => $vendorId)
            );
            if ($vendor)
            {
                $db->executeQuery
                (
                    "UPDATE vendors SET is_approved = 1 WHERE vendor_id = :vendor_id",
                    array('vendor_id' => $vendorId)
                );
                $db->executeQuery
                (
                    "UPDATE users SET is_verified = 1 WHERE user_id = :user_id",
                    array('user_id' => $vendor['vendor_user_id'])
                );
                $message = 'Vendor approved successfully.';
                writeLog("Admin approved vendor ID: $vendorId", "ADMIN");
            }
        }
        elseif ($action === 'reject_vendor' && $vendorId > 0)
        {
            $vendor = $db->fetchOne
            (
                "SELECT vendor_user_id FROM vendors WHERE vendor_id = :vendor_id",
                array('vendor_id' => $vendorId)
            );
            if ($vendor)
            {
                $db->executeQuery
                (
                    "DELETE FROM vendors WHERE vendor_id = :vendor_id",
                    array('vendor_id' => $vendorId)
                );
                $db->executeQuery
                (
                    "DELETE FROM users WHERE user_id = :user_id",
                    array('user_id' => $vendor['vendor_user_id'])
                );
                $message = 'Vendor application rejected and removed.';
                writeLog("Admin rejected vendor ID: $vendorId", "ADMIN");
            }
        }
    }
    $csrfToken = getCsrfToken();
}

// =============================================================================
// Fetch Dashboard Statistics
// =============================================================================

try
{
    // User statistics
    $totalUsers = (int)($db->fetchOne
    (
        "SELECT COUNT(*) as count FROM users WHERE account_type != 'admin'"
    )['count'] ?? 0);

    $activeVendors = (int)($db->fetchOne
    (
        "SELECT COUNT(*) as count FROM vendors v
         JOIN users u ON v.vendor_user_id = u.user_id
         WHERE v.is_approved = 1 AND u.is_verified = 1 AND u.is_active = 1"
    )['count'] ?? 0);

    $totalMenuItems = (int)($db->fetchOne
    (
        "SELECT COUNT(*) as count FROM menu_items"
    )['count'] ?? 0);

    $totalOrders = (int)($db->fetchOne
    (
        "SELECT COUNT(*) as count FROM orders"
    )['count'] ?? 0);

    // Today's platform revenue
    $todayRevenue = (float)($db->fetchOne
    (
        "SELECT COALESCE(SUM(total_amount), 0) as total
         FROM orders
         WHERE DATE(order_placed_at) = CURDATE()
           AND order_status = 'completed'"
    )['total'] ?? 0);

    // Week platform revenue
    $weekRevenue = (float)($db->fetchOne
    (
        "SELECT COALESCE(SUM(total_amount), 0) as total
         FROM orders
         WHERE YEARWEEK(order_placed_at) = YEARWEEK(CURDATE())
           AND order_status = 'completed'"
    )['total'] ?? 0);

    // Month platform revenue
    $monthRevenue = (float)($db->fetchOne
    (
        "SELECT COALESCE(SUM(total_amount), 0) as total
         FROM orders
         WHERE MONTH(order_placed_at) = MONTH(CURDATE())
           AND YEAR(order_placed_at) = YEAR(CURDATE())
           AND order_status = 'completed'"
    )['total'] ?? 0);

    // Total platform lifetime revenue
    $totalRevenue = (float)($db->fetchOne
    (
        "SELECT COALESCE(SUM(total_amount), 0) as total
         FROM orders
         WHERE order_status = 'completed'"
    )['total'] ?? 0);

    // Average order value
    $avgOrderValue = (float)($db->fetchOne
    (
        "SELECT COALESCE(AVG(total_amount), 0) as avg_value
         FROM orders
         WHERE order_status = 'completed'"
    )['avg_value'] ?? 0);

    // Recent orders
    $recentOrders = $db->fetchAll
    (
        "SELECT o.order_id, o.order_number, o.order_status, o.total_amount, o.order_placed_at,
                v.vendor_name, u.full_name as customer_name
         FROM orders o
         JOIN vendors v ON o.vendor_id = v.vendor_id
         JOIN users u ON o.user_id = u.user_id
         ORDER BY o.order_placed_at DESC
         LIMIT 5"
    );

    // Pending vendors
    $pendingVendors = $db->fetchAll
    (
        "SELECT v.vendor_id, v.vendor_name, v.created_at,
                u.full_name as owner_name, u.email as owner_email
         FROM vendors v
         JOIN users u ON v.vendor_user_id = u.user_id
         WHERE v.is_approved = 0
         ORDER BY v.created_at DESC"
    );

    // Pending users
    $pendingUsers = $db->fetchAll
    (
        "SELECT user_id, full_name, username, email, account_type, created_at
         FROM users
         WHERE is_verified = 0 AND account_type IN ('student', 'vendor')
         ORDER BY created_at DESC"
    );
}
catch (Exception $e)
{
    writeLog("Admin dashboard error: " . $e->getMessage(), "ADMIN");
    $error = 'Unable to load dashboard data.';
    $totalUsers = 0;
    $activeVendors = 0;
    $totalMenuItems = 0;
    $totalOrders = 0;
    $todayRevenue = 0;
    $weekRevenue = 0;
    $monthRevenue = 0;
    $totalRevenue = 0;
    $avgOrderValue = 0;
    $recentOrders = array();
    $pendingVendors = array();
    $pendingUsers = array();
}

// Combine local and API vendor counts
$totalVendors = $activeVendors + count($apiRestaurants);

function escapeAdminOutput($string)
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
    <title>Admin Dashboard · Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/admin_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="admin-content">
                <div class="container">
                    <!-- Welcome Card -->
                    <div class="welcome-card">
                        <h1>Welcome to Campus Eats</h1>
                        <p>Manage users, vendors, menus, and orders for your campus food network, all in one place.</p>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Success</div>
                                <div class="alert-message"><?php echo escapeAdminOutput($message); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeAdminOutput($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Stats Grid - Matching Mockup 26.png -->
                    <div class="admin-stats-grid">
                        <div class="admin-stat-card">
                            <div class="admin-stat-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-content">
                                <div class="admin-stat-number"><?php echo number_format($totalUsers); ?></div>
                                <div class="admin-stat-label">Total Users</div>
                            </div>
                        </div>
                        <div class="admin-stat-card">
                            <div class="admin-stat-icon"><i class="fas fa-store"></i></div>
                            <div class="stat-content">
                                <div class="admin-stat-number"><?php echo number_format($totalVendors); ?></div>
                                <div class="admin-stat-label">Active Vendors</div>
                            </div>
                        </div>
                        <div class="admin-stat-card">
                            <div class="admin-stat-icon"><i class="fas fa-utensils"></i></div>
                            <div class="stat-content">
                                <div class="admin-stat-number"><?php echo number_format($totalMenuItems + $apiTotalItems); ?></div>
                                <div class="admin-stat-label">Menu Items</div>
                            </div>
                        </div>
                        <div class="admin-stat-card">
                            <div class="admin-stat-icon"><i class="fas fa-shopping-cart"></i></div>
                            <div class="stat-content">
                                <div class="admin-stat-number"><?php echo number_format($totalOrders); ?></div>
                                <div class="admin-stat-label">Total Orders</div>
                            </div>
                        </div>
                    </div>

                    <!-- Revenue Summary - Matching Mockup 26.png -->
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

                    <!-- Dashboard Grid - Matching Mockup 26b.png -->
                    <div class="admin-dashboard-grid">
                        <!-- Pending Approvals Card -->
                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h3><i class="fas fa-clock"></i> Pending Approvals</h3>
                            </div>
                            <div class="dashboard-card-body">
                                <?php if (empty($pendingUsers) && empty($pendingVendors)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-check-circle"></i>
                                        <p>All caught up. No pending approvals.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-container">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Name</th>
                                                    <th>Email</th>
                                                    <th>Type</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pendingUsers as $user): ?>
                                                <tr>
                                                    <td data-label="Name"><?php echo escapeAdminOutput($user['full_name']); ?></td>
                                                    <td data-label="Email"><?php echo escapeAdminOutput($user['email']); ?></td>
                                                    <td data-label="Type"><span class="badge badge-info"><?php echo ucfirst(escapeAdminOutput($user['account_type'])); ?></span></td>
                                                    <td data-label="Action">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                            <input type="hidden" name="action" value="approve_user">
                                                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                                <?php foreach ($pendingVendors as $vendor): ?>
                                                <tr>
                                                    <td data-label="Name"><?php echo escapeAdminOutput($vendor['vendor_name']); ?></td>
                                                    <td data-label="Email"><?php echo escapeAdminOutput($vendor['owner_email']); ?></td>
                                                    <td data-label="Type"><span class="badge badge-warning">Vendor (Pending)</span></td>
                                                    <td data-label="Action">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="vendor_id" value="<?php echo $vendor['vendor_id']; ?>">
                                                            <input type="hidden" name="action" value="approve_vendor">
                                                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                        </form>
                                                        <form method="POST" class="inline-form">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="vendor_id" value="<?php echo $vendor['vendor_id']; ?>">
                                                            <input type="hidden" name="action" value="reject_vendor">
                                                            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Reject this vendor application? This will permanently delete the vendor account and associated user.');">Reject</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Recent Orders Card - Matching Mockup 26b.png -->
                        <div class="dashboard-card">
                            <div class="dashboard-card-header">
                                <h3><i class="fas fa-history"></i> Recent Orders</h3>
                                <a href="order_management.php" class="btn btn-outline btn-sm">View All</a>
                            </div>
                            <div class="dashboard-card-body">
                                <?php if (empty($recentOrders)): ?>
                                    <div class="empty-state">
                                        <i class="fas fa-receipt"></i>
                                        <p>No orders yet.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="table-container">
                                        <table class="data-table">
                                            <thead>
                                                <tr>
                                                    <th>Order #</th>
                                                    <th>Customer</th>
                                                    <th>Vendor</th>
                                                    <th>Total</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recentOrders as $order): ?>
                                                <tr>
                                                    <td data-label="Order #"><?php echo escapeAdminOutput($order['order_number']); ?></td>
                                                    <td data-label="Customer"><?php echo escapeAdminOutput($order['customer_name']); ?></td>
                                                    <td data-label="Vendor"><?php echo escapeAdminOutput($order['vendor_name']); ?></td>
                                                    <td data-label="Total">R <?php echo number_format($order['total_amount'], 2); ?></td>
                                                    <td data-label="Status"><span class="badge status-<?php echo escapeAdminOutput($order['order_status']); ?>"><?php echo ucfirst(escapeAdminOutput($order['order_status'])); ?></span></td>
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

    <script>
        document.addEventListener('DOMContentLoaded', function()
        {
            const toggleBtn = document.getElementById('menuToggleBtn');
            const sidebar = document.querySelector('.sidebar-admin, .sidebar-vendor, .sidebar-student');

            if (toggleBtn && sidebar)
            {
                if (window.innerWidth <= 768)
                {
                    toggleBtn.style.display = 'flex';
                }

                window.addEventListener('resize', function()
                {
                    if (window.innerWidth <= 768)
                    {
                        toggleBtn.style.display = 'flex';
                    }
                    else
                    {
                        toggleBtn.style.display = 'none';
                        sidebar.classList.remove('mobile-open');
                    }
                });

                toggleBtn.addEventListener('click', function()
                {
                    sidebar.classList.toggle('mobile-open');
                });

                document.addEventListener('click', function(event)
                {
                    if (window.innerWidth <= 768)
                    {
                        const isClickInsideSidebar = sidebar.contains(event.target);
                        const isClickOnToggle = toggleBtn.contains(event.target);
                        if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('mobile-open'))
                        {
                            sidebar.classList.remove('mobile-open');
                        }
                    }
                });
            }
        });
    </script>

    <script src="<?php echo ASSETS_URL; ?>/js/admin.js"></script>
</body>
</html>