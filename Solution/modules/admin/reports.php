<?php
/**
 * System Reports Page for Administrators - Matching Mockups 31-33.png
 *
 * This page provides system-wide analytics and reporting capabilities.
 *
 * CORRECTIONS (Version 14.0 - Visual Parity):
 * - Updated layout to match mockups 31.png, 31a.png, 32.png, 33.png
 * - Added report tabs (Sales, Order Summary, Vendor, Detailed Order, User Activity)
 * - Added summary cards
 * - Improved responsive behavior
 *
 * SOURCE: Mockups - 31.png, 31a.png, 32.png, 33.png
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

$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

if ($period === 'week')
{
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = date('Y-m-d');
}
elseif ($period === 'month')
{
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t');
}
elseif ($period === 'year')
{
    $startDate = date('Y-01-01');
    $endDate = date('Y-12-31');
}

// Fetch report data
$summary = array();
$orderStatusCounts = array();

try
{
    // Summary
    $summaryResult = $db->fetchOne(
        "SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_revenue
         FROM orders
         WHERE order_placed_at BETWEEN :start_date AND :end_date",
        array(
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $endDate . ' 23:59:59'
        )
    );

    $summary = array(
        'total_orders' => $summaryResult['total_orders'] ?? 0,
        'total_revenue' => $summaryResult['total_revenue'] ?? 0
    );

    // Order status counts
    $statusCounts = $db->fetchAll(
        "SELECT order_status, COUNT(*) as count
         FROM orders
         WHERE order_placed_at BETWEEN :start_date AND :end_date
         GROUP BY order_status",
        array(
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $endDate . ' 23:59:59'
        )
    );

    foreach ($statusCounts as $row)
    {
        $orderStatusCounts[$row['order_status']] = $row['count'];
    }
}
catch (Exception $e)
{
    writeLog("Admin reports error: " . $e->getMessage(), "ADMIN");
    $error = "Unable to load report data.";
}

function escapeReportOutput($string)
{
    if ($string === null) return '';
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function isReportTabActive($current, $target)
{
    return $current === $target ? 'active' : '';
}

function getOrderStatusCount($status, $counts)
{
    return $counts[$status] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Reports · Campus Eats Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <style>
        .report-tabs {
            display: flex;
            gap: var(--space-2);
            flex-wrap: wrap;
            margin-bottom: var(--space-4);
        }
        .report-tab {
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
        .report-tab:hover {
            background: var(--orange-light);
            border-color: var(--orange);
            color: var(--orange);
        }
        .report-tab.active {
            background: var(--orange);
            border-color: var(--orange);
            color: white;
        }
        .report-summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-4);
            margin-bottom: var(--space-4);
        }
        .report-summary-card {
            background: white;
            padding: var(--space-4);
            border-radius: var(--radius-lg);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
        }
        .report-summary-card .value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--orange);
        }
        .report-summary-card .label {
            font-size: 0.75rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .status-distribution {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: var(--space-2);
        }
        .status-item {
            display: flex;
            justify-content: space-between;
            padding: var(--space-2) var(--space-3);
            background: var(--gray-50);
            border-radius: var(--radius-sm);
        }
        @media (max-width: 768px) {
            .report-summary-grid { grid-template-columns: 1fr 1fr; }
            .status-distribution { grid-template-columns: 1fr; }
        }
        @media (max-width: 480px) {
            .report-summary-grid { grid-template-columns: 1fr; }
            .report-tab { padding: var(--space-1) var(--space-2); font-size: 0.75rem; }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/admin_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="admin-content">
                <div class="container">
                    <div class="page-header">
                        <h1>Reports</h1>
                        <p>Sales, orders, vendor and user analytics</p>
                    </div>

                    <!-- Report Tabs - Matching Mockup 31a.png -->
                    <div class="report-tabs">
                        <a href="?report_type=sales" class="report-tab <?php echo isReportTabActive($reportType, 'sales'); ?>">
                            <i class="fas fa-chart-line"></i> Sales
                        </a>
                        <a href="?report_type=order_summary" class="report-tab <?php echo isReportTabActive($reportType, 'order_summary'); ?>">
                            <i class="fas fa-clipboard-list"></i> Order Summary
                        </a>
                        <a href="?report_type=vendor" class="report-tab <?php echo isReportTabActive($reportType, 'vendor'); ?>">
                            <i class="fas fa-store"></i> Vendor
                        </a>
                        <a href="?report_type=detailed_order" class="report-tab <?php echo isReportTabActive($reportType, 'detailed_order'); ?>">
                            <i class="fas fa-file-alt"></i> Detailed Order
                        </a>
                        <a href="?report_type=user_activity" class="report-tab <?php echo isReportTabActive($reportType, 'user_activity'); ?>">
                            <i class="fas fa-users"></i> User Activity
                        </a>
                    </div>

                    <!-- Summary - Matching Mockup 31.png -->
                    <div class="report-summary-grid">
                        <div class="report-summary-card">
                            <div class="value"><?php echo $summary['total_orders'] ?? 0; ?></div>
                            <div class="label">Total Orders</div>
                        </div>
                        <div class="report-summary-card">
                            <div class="value">R <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></div>
                            <div class="label">Total Revenue</div>
                        </div>
                        <div class="report-summary-card">
                            <div class="value"><?php echo date('Y-m-d'); ?></div>
                            <div class="label">Date</div>
                        </div>
                        <div class="report-summary-card">
                            <div class="value">R <?php echo number_format($summary['total_revenue'] / max(1, $summary['total_orders']), 2); ?></div>
                            <div class="label">Avg Order Value</div>
                        </div>
                    </div>

                    <!-- Order Status Distribution -->
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-chart-pie"></i> Order Status Distribution</h3>
                        </div>
                        <div class="dashboard-card-body">
                            <div class="status-distribution">
                                <div class="status-item">
                                    <span>Pending</span>
                                    <span><strong><?php echo getOrderStatusCount(ORDER_STATUS_PENDING, $orderStatusCounts); ?></strong></span>
                                </div>
                                <div class="status-item">
                                    <span>Accepted</span>
                                    <span><strong><?php echo getOrderStatusCount(ORDER_STATUS_ACCEPTED, $orderStatusCounts); ?></strong></span>
                                </div>
                                <div class="status-item">
                                    <span>Preparing</span>
                                    <span><strong><?php echo getOrderStatusCount(ORDER_STATUS_PREPARING, $orderStatusCounts); ?></strong></span>
                                </div>
                                <div class="status-item">
                                    <span>Ready</span>
                                    <span><strong><?php echo getOrderStatusCount(ORDER_STATUS_READY, $orderStatusCounts); ?></strong></span>
                                </div>
                                <div class="status-item">
                                    <span>Completed</span>
                                    <span><strong><?php echo getOrderStatusCount(ORDER_STATUS_COMPLETED, $orderStatusCounts); ?></strong></span>
                                </div>
                                <div class="status-item">
                                    <span>Cancelled</span>
                                    <span><strong><?php echo getOrderStatusCount(ORDER_STATUS_CANCELLED, $orderStatusCounts); ?></strong></span>
                                </div>
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