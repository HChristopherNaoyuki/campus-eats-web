<?php
/**
 * Sales Reports Page for Vendors
 *
 * This page provides sales reporting and analytics for vendors.
 *
 * SOURCE: campus-eats-process-document.pdf (Section 10.2 - Vendor Reports)
 * SOURCE: Mockup - Vendor Reports design
 *
 * @version 14.0
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

// Get vendor information
$vendor = $db->fetchOne
(
    "SELECT vendor_id, vendor_name FROM vendors WHERE vendor_user_id = :user_id",
    array('user_id' => $currentUser['user_id'])
);

if (!$vendor)
{
    writeLog("Vendor reports: No vendor profile found for user ID " . $currentUser['user_id'], "VENDOR");
    header('Location: ' . BASE_URL . '/modules/auth/logout.php');
    exit();
}

// Get filter parameters
$period = isset($_GET['period']) ? $_GET['period'] : 'week';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$format = isset($_GET['format']) ? $_GET['format'] : 'html';

// Set date range based on period
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
elseif ($period === 'custom' && (empty($startDate) || empty($endDate)))
{
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-d');
}

// Validate date range
if (!empty($startDate) && !empty($endDate) && strtotime($startDate) > strtotime($endDate))
{
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-d');
}

$error = '';
$dailySales = array();
$topItems = array();
$summary = array();
$isLoading = false;

// Fetch report data
try
{
    $isLoading = true;

    $dailySales = $db->fetchAll
    (
        "SELECT DATE(order_placed_at) as sale_date,
                COUNT(*) as order_count,
                SUM(total_amount) as daily_revenue
         FROM orders
         WHERE vendor_id = :vendor_id
           AND order_status = :status
           AND order_placed_at BETWEEN :start_date AND :end_date
         GROUP BY DATE(order_placed_at)
         ORDER BY sale_date",
        array(
            'vendor_id' => $vendor['vendor_id'],
            'status' => ORDER_STATUS_COMPLETED,
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $endDate . ' 23:59:59'
        )
    );

    $topItems = $db->fetchAll
    (
        "SELECT mi.item_name, mi.category,
                SUM(oi.quantity) as total_sold,
                SUM(oi.subtotal) as total_revenue
         FROM order_items oi
         JOIN menu_items mi ON oi.item_id = mi.item_id
         JOIN orders o ON oi.order_id = o.order_id
         WHERE o.vendor_id = :vendor_id
           AND o.order_status = :status
           AND o.order_placed_at BETWEEN :start_date AND :end_date
         GROUP BY mi.item_id
         ORDER BY total_sold DESC
         LIMIT 10",
        array(
            'vendor_id' => $vendor['vendor_id'],
            'status' => ORDER_STATUS_COMPLETED,
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $endDate . ' 23:59:59'
        )
    );

    $summaryResult = $db->fetchOne
    (
        "SELECT
            COUNT(*) as total_orders,
            SUM(total_amount) as total_revenue,
            AVG(total_amount) as avg_order_value,
            COUNT(DISTINCT user_id) as unique_customers
         FROM orders
         WHERE vendor_id = :vendor_id
           AND order_status = :status
           AND order_placed_at BETWEEN :start_date AND :end_date",
        array(
            'vendor_id' => $vendor['vendor_id'],
            'status' => ORDER_STATUS_COMPLETED,
            'start_date' => $startDate . ' 00:00:00',
            'end_date' => $endDate . ' 23:59:59'
        )
    );

    $summary = array(
        'total_orders' => $summaryResult['total_orders'] ?? 0,
        'total_revenue' => $summaryResult['total_revenue'] ?? 0,
        'avg_order_value' => $summaryResult['avg_order_value'] ?? 0,
        'unique_customers' => $summaryResult['unique_customers'] ?? 0
    );

    if ($dailySales === false) $dailySales = array();
    if ($topItems === false) $topItems = array();

    $isLoading = false;
}
catch (Exception $e)
{
    writeLog("Vendor reports error: " . $e->getMessage(), "VENDOR");
    $error = "Unable to load report data. Please try again later.";
    $dailySales = array();
    $topItems = array();
    $summary = array(
        'total_orders' => 0,
        'total_revenue' => 0,
        'avg_order_value' => 0,
        'unique_customers' => 0
    );
    $isLoading = false;
}

// Handle CSV export
if ($format === 'csv')
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="vendor_sales_report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    fputcsv($output, array('Sales Report for ' . $vendor['vendor_name']));
    fputcsv($output, array('Period:', $startDate . ' to ' . $endDate));
    fputcsv($output, array());
    fputcsv($output, array('Summary Statistics'));
    fputcsv($output, array('Total Orders', $summary['total_orders'] ?? 0));
    fputcsv($output, array('Total Revenue', $summary['total_revenue'] ?? 0));
    fputcsv($output, array('Average Order Value', $summary['avg_order_value'] ?? 0));
    fputcsv($output, array('Unique Customers', $summary['unique_customers'] ?? 0));
    fputcsv($output, array());
    fputcsv($output, array('Daily Sales'));
    fputcsv($output, array('Date', 'Orders', 'Revenue'));

    foreach ($dailySales as $day)
    {
        fputcsv($output, array($day['sale_date'], $day['order_count'], $day['daily_revenue']));
    }

    fputcsv($output, array());
    fputcsv($output, array('Top Selling Items'));
    fputcsv($output, array('Item Name', 'Category', 'Units Sold', 'Revenue'));

    foreach ($topItems as $item)
    {
        fputcsv($output, array($item['item_name'], $item['category'], $item['total_sold'], $item['total_revenue']));
    }

    fclose($output);
    exit();
}

function escapeReportOutput($string)
{
    if ($string === null) return '';
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function isPeriodActive($current, $target)
{
    return $current === $target ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeReportOutput($csrfToken); ?>">
    <title>Sales Reports · Vendor Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <style>
        .report-filters
        {
            background: white;
            padding: var(--space-5);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--gray-100);
        }

        .period-buttons
        {
            display: flex;
            gap: var(--space-2);
            flex-wrap: wrap;
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-4);
            border-bottom: 1px solid var(--gray-200);
        }

        .period-btn
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

        .period-btn:hover
        {
            background: var(--orange-light);
            border-color: var(--orange);
            color: var(--orange);
            transform: translateY(-1px);
        }

        .period-btn.active
        {
            background: var(--orange);
            border-color: var(--orange);
            color: white;
            box-shadow: var(--shadow-sm);
        }

        .filter-row
        {
            display: flex;
            gap: var(--space-4);
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group
        {
            flex: 1;
            min-width: 150px;
        }

        .filter-group label
        {
            display: block;
            margin-bottom: var(--space-2);
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-700);
        }

        .filter-group input
        {
            width: 100%;
            padding: var(--space-2) var(--space-3);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            transition: all var(--transition-fast);
        }

        .filter-group input:focus
        {
            border-color: var(--orange);
            outline: none;
            box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.1);
        }

        .filter-actions
        {
            display: flex;
            gap: var(--space-2);
            align-items: center;
            flex-wrap: wrap;
        }

        .report-summary
        {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: var(--space-4);
            margin-bottom: var(--space-6);
        }

        .summary-card
        {
            background: white;
            padding: var(--space-5);
            border-radius: var(--radius-lg);
            text-align: center;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
            transition: transform var(--transition-base), box-shadow var(--transition-base);
        }

        .summary-card:hover
        {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .summary-card i
        {
            font-size: 2rem;
            color: var(--orange);
            margin-bottom: var(--space-2);
        }

        .summary-card h4
        {
            color: var(--gray-600);
            font-size: 0.75rem;
            margin-bottom: var(--space-2);
            text-transform: uppercase;
            letter-spacing: 0.02em;
            font-weight: 600;
        }

        .summary-card .value
        {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--orange);
        }

        .summary-card .value.currency { color: var(--gray-900); }

        .loading-indicator
        {
            text-align: center;
            padding: var(--space-8);
            color: var(--gray-500);
        }

        .loading-indicator i
        {
            font-size: 2rem;
            color: var(--orange);
            margin-bottom: var(--space-3);
            animation: spin 1s linear infinite;
        }

        @keyframes spin
        {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .report-card
        {
            background: white;
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
        }

        .report-card-header
        {
            background: linear-gradient(135deg, var(--orange), var(--orange-dark));
            color: white;
            padding: var(--space-3) var(--space-5);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-card-header h3
        {
            margin: 0;
            color: white;
            font-size: 0.9375rem;
            font-weight: 500;
        }

        .report-card-header h3 i { margin-right: var(--space-2); }

        .report-card-body { padding: 0; }

        .report-table
        {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td
        {
            padding: var(--space-3) var(--space-4);
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .report-table th
        {
            background: var(--gray-50);
            font-weight: 600;
            font-size: 0.8125rem;
            text-transform: uppercase;
            color: var(--gray-600);
            letter-spacing: 0.02em;
        }

        .report-table tfoot td,
        .report-table tfoot th
        {
            background: var(--gray-50);
            font-weight: 600;
        }

        .report-table tr:hover td { background: var(--gray-50); }

        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .empty-state
        {
            text-align: center;
            padding: var(--space-8) var(--space-4);
            color: var(--gray-500);
        }

        .empty-state i { font-size: 3rem; margin-bottom: var(--space-4); color: var(--gray-300); }
        .empty-state h3 { color: var(--gray-600); margin-bottom: var(--space-2); }
        .empty-state p { margin-bottom: 0; }

        @media (max-width: 1024px)
        {
            .report-summary { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px)
        {
            .period-buttons { justify-content: center; }
            .filter-row { flex-direction: column; }
            .filter-group { width: 100%; }
            .filter-actions { justify-content: flex-start; width: 100%; }
            .report-summary { grid-template-columns: 1fr; }
            .report-table th, .report-table td { padding: var(--space-2) var(--space-3); font-size: 0.75rem; }
        }

        @media (max-width: 480px)
        {
            .period-btn { padding: var(--space-1) var(--space-3); font-size: 0.75rem; }
            .summary-card .value { font-size: 1.25rem; }
            .report-card-header { padding: var(--space-2) var(--space-4); }
            .report-card-header h3 { font-size: 0.875rem; }
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
                        <h1>Sales Reports</h1>
                        <p>View your sales data, revenue trends, and top selling items</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeReportOutput($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="report-filters">
                        <div class="period-buttons">
                            <a href="?period=week" class="period-btn <?php echo isPeriodActive($period, 'week'); ?>">
                                <i class="fas fa-calendar-week"></i> Last 7 Days
                            </a>
                            <a href="?period=month" class="period-btn <?php echo isPeriodActive($period, 'month'); ?>">
                                <i class="fas fa-calendar-alt"></i> This Month
                            </a>
                            <a href="?period=year" class="period-btn <?php echo isPeriodActive($period, 'year'); ?>">
                                <i class="fas fa-calendar-year"></i> This Year
                            </a>
                            <a href="?period=custom&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>"
                               class="period-btn <?php echo isPeriodActive($period, 'custom'); ?>">
                                <i class="fas fa-calendar-day"></i> Custom Range
                            </a>
                        </div>

                        <form method="GET" action="" class="filter-form" id="dateRangeForm">
                            <div class="filter-row">
                                <div class="filter-group" id="custom-date-group" style="display: <?php echo $period === 'custom' ? 'flex' : 'none'; ?>; gap: var(--space-2);">
                                    <div style="flex: 1;">
                                        <label for="start_date">Start Date</label>
                                        <input type="date" id="start_date" name="start_date" value="<?php echo escapeReportOutput($startDate); ?>">
                                    </div>
                                    <div style="flex: 1;">
                                        <label for="end_date">End Date</label>
                                        <input type="date" id="end_date" name="end_date" value="<?php echo escapeReportOutput($endDate); ?>">
                                    </div>
                                </div>
                                <div class="filter-group filter-actions">
                                    <input type="hidden" name="period" value="<?php echo $period; ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-sync-alt"></i> Generate Report
                                    </button>
                                    <a href="?period=<?php echo $period; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>&format=csv" class="btn btn-outline">
                                        <i class="fas fa-download"></i> Export CSV
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="report-summary">
                        <div class="summary-card">
                            <i class="fas fa-shopping-cart"></i>
                            <h4>Total Orders</h4>
                            <div class="value"><?php echo number_format($summary['total_orders'] ?? 0); ?></div>
                        </div>
                        <div class="summary-card">
                            <i class="fas fa-chart-line"></i>
                            <h4>Total Revenue</h4>
                            <div class="value currency">R <?php echo number_format($summary['total_revenue'] ?? 0, 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <i class="fas fa-calculator"></i>
                            <h4>Average Order Value</h4>
                            <div class="value currency">R <?php echo number_format($summary['avg_order_value'] ?? 0, 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <i class="fas fa-users"></i>
                            <h4>Unique Customers</h4>
                            <div class="value"><?php echo number_format($summary['unique_customers'] ?? 0); ?></div>
                        </div>
                    </div>

                    <div class="report-card">
                        <div class="report-card-header">
                            <h3><i class="fas fa-calendar-day"></i> Daily Sales Breakdown</h3>
                            <span class="badge badge-info" style="background: rgba(255,255,255,0.2); color: white;">
                                <?php echo count($dailySales); ?> days
                            </span>
                        </div>
                        <div class="report-card-body">
                            <?php if ($isLoading): ?>
                                <div class="loading-indicator">
                                    <i class="fas fa-spinner"></i>
                                    <p>Loading sales data...</p>
                                </div>
                            <?php elseif (empty($dailySales)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-chart-line"></i>
                                    <h3>No Sales Data</h3>
                                    <p>No sales data available for the selected period.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="report-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th class="text-right">Orders</th>
                                                <th class="text-right">Revenue</th>
                                                <th class="text-right">Average Order Value</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $totalOrders = 0;
                                            $totalRevenue = 0;
                                            foreach ($dailySales as $day):
                                                $totalOrders += $day['order_count'];
                                                $totalRevenue += $day['daily_revenue'];
                                                $avgOrderValue = $day['order_count'] > 0 ? $day['daily_revenue'] / $day['order_count'] : 0;
                                            ?>
                                            <tr>
                                                <td><strong><?php echo date('M j, Y', strtotime($day['sale_date'])); ?></strong></td>
                                                <td class="text-right"><?php echo number_format($day['order_count']); ?></td>
                                                <td class="text-right">R <?php echo number_format($day['daily_revenue'], 2); ?></td>
                                                <td class="text-right">R <?php echo number_format($avgOrderValue, 2); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th><strong>Total</strong></th>
                                                <th class="text-right"><strong><?php echo number_format($totalOrders); ?></strong></th>
                                                <th class="text-right"><strong>R <?php echo number_format($totalRevenue, 2); ?></strong></th>
                                                <th class="text-right"><strong>R <?php echo $totalOrders > 0 ? number_format($totalRevenue / $totalOrders, 2) : '0.00'; ?></strong></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="report-card">
                        <div class="report-card-header">
                            <h3><i class="fas fa-fire"></i> Top Selling Items</h3>
                            <span class="badge badge-info" style="background: rgba(255,255,255,0.2); color: white;">
                                Top <?php echo count($topItems); ?>
                            </span>
                        </div>
                        <div class="report-card-body">
                            <?php if ($isLoading): ?>
                                <div class="loading-indicator">
                                    <i class="fas fa-spinner"></i>
                                    <p>Loading item data...</p>
                                </div>
                            <?php elseif (empty($topItems)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-utensils"></i>
                                    <h3>No Item Sales</h3>
                                    <p>No sales data available for the selected period.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="report-table">
                                        <thead>
                                            <tr>
                                                <th>Item Name</th>
                                                <th>Category</th>
                                                <th class="text-right">Units Sold</th>
                                                <th class="text-right">Revenue</th>
                                                <th class="text-right">Percentage</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $totalSold = array_sum(array_column($topItems, 'total_sold'));
                                            $totalItemRevenue = array_sum(array_column($topItems, 'total_revenue'));
                                            foreach ($topItems as $item):
                                                $percentage = $totalSold > 0 ? ($item['total_sold'] / $totalSold) * 100 : 0;
                                            ?>
                                            <tr>
                                                <td><strong><?php echo escapeReportOutput($item['item_name']); ?></strong></td>
                                                <td><?php echo escapeReportOutput($item['category'] ?: 'Uncategorized'); ?></td>
                                                <td class="text-right"><?php echo number_format($item['total_sold']); ?></td>
                                                <td class="text-right">R <?php echo number_format($item['total_revenue'], 2); ?></td>
                                                <td class="text-right"><?php echo number_format($percentage, 1); ?>%</td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th colspan="2"><strong>Total</strong></th>
                                                <th class="text-right"><strong><?php echo number_format($totalSold); ?></strong></th>
                                                <th class="text-right"><strong>R <?php echo number_format($totalItemRevenue, 2); ?></strong></th>
                                                <th class="text-right"><strong>100%</strong></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
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
    <script>
        document.querySelectorAll('.period-btn[href*="custom"]').forEach(function(btn)
        {
            btn.addEventListener('click', function(e)
            {
                e.preventDefault();
                document.getElementById('custom-date-group').style.display = 'flex';
                document.getElementById('dateRangeForm').querySelector('input[name="period"]').value = 'custom';
            });
        });
    </script>
</body>
</html>