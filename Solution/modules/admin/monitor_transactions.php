<?php
/**
 * Monitor Transactions Page for Administrators
 *
 * This page allows administrators to view all payment transactions in the system.
 *
 * SOURCE: campus-eats-process-document.pdf (Section 10.3 - Transactions Dashboard)
 * SOURCE: Mockups - Transactions design
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

$startDate = isset($_GET['start_date']) && !empty($_GET['start_date'])
    ? $_GET['start_date']
    : date('Y-m-01');
$endDate = isset($_GET['end_date']) && !empty($_GET['end_date'])
    ? $_GET['end_date']
    : date('Y-m-d');

if (strtotime($startDate) > strtotime($endDate))
{
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-d');
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$itemsPerPage = 25;
$offset = ($page - 1) * $itemsPerPage;

$conditions = array();
$params = array();

if (!empty($startDate))
{
    $conditions[] = "p.payment_date >= :start_date";
    $params['start_date'] = $startDate . ' 00:00:00';
}

if (!empty($endDate))
{
    $conditions[] = "p.payment_date <= :end_date";
    $params['end_date'] = $endDate . ' 23:59:59';
}

$whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

try
{
    $totalTransactions = (int)($db->fetchOne(
        "SELECT COUNT(*) as count FROM payments p $whereClause",
        $params
    )['count'] ?? 0);

    $revenueParams = array_merge($params, array('status' => PAYMENT_STATUS_COMPLETED));
    $whereClauseRevenue = $whereClause . ($whereClause ? ' AND ' : 'WHERE ') . "p.payment_status = :status";

    $totalRevenue = (float)($db->fetchOne(
        "SELECT COALESCE(SUM(p.amount), 0) as total
         FROM payments p
         $whereClauseRevenue",
        $revenueParams
    )['total'] ?? 0);

    $pendingParams = array_merge($params, array('status' => PAYMENT_STATUS_PENDING));
    $whereClausePending = $whereClause . ($whereClause ? ' AND ' : 'WHERE ') . "p.payment_status = :status";

    $pendingPayments = (float)($db->fetchOne(
        "SELECT COALESCE(SUM(p.amount), 0) as total
         FROM payments p
         $whereClausePending",
        $pendingParams
    )['total'] ?? 0);

    $avgTransaction = (float)($db->fetchOne(
        "SELECT COALESCE(AVG(p.amount), 0) as avg_value
         FROM payments p
         $whereClauseRevenue",
        $revenueParams
    )['avg_value'] ?? 0);

    $countSql = "SELECT COUNT(*) as count
                 FROM payments p
                 $whereClause";
    $countStmt = $db->getConnection()->prepare($countSql);
    foreach ($params as $key => $value)
    {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    $totalPages = ceil($totalCount / $itemsPerPage);

    $sql = "SELECT p.payment_id, p.order_id, p.payment_method, p.payment_status,
                   p.transaction_reference, p.amount, p.payment_date,
                   o.order_number,
                   v.vendor_name,
                   u.full_name as customer_name, u.email as customer_email
            FROM payments p
            JOIN orders o ON p.order_id = o.order_id
            JOIN vendors v ON o.vendor_id = v.vendor_id
            JOIN users u ON o.user_id = u.user_id
            $whereClause
            ORDER BY p.payment_date DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $db->getConnection()->prepare($sql);
    $stmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    foreach ($params as $key => $value)
    {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
catch (Exception $e)
{
    writeLog("Monitor transactions error: " . $e->getMessage(), "ADMIN");
    $transactions = array();
    $totalTransactions = 0;
    $totalRevenue = 0;
    $pendingPayments = 0;
    $avgTransaction = 0;
    $totalCount = 0;
    $totalPages = 0;
    $error = "Unable to load transaction data. Please try again later.";
}

function getPaymentStatusBadgeClass($status)
{
    switch ($status)
    {
        case PAYMENT_STATUS_COMPLETED: return 'payment-completed';
        case PAYMENT_STATUS_PENDING: return 'payment-pending';
        case PAYMENT_STATUS_FAILED: return 'payment-failed';
        case PAYMENT_STATUS_REFUNDED: return 'payment-refunded';
        default: return '';
    }
}

function getPaymentStatusText($status)
{
    switch ($status)
    {
        case PAYMENT_STATUS_COMPLETED: return 'Completed';
        case PAYMENT_STATUS_PENDING: return 'Pending';
        case PAYMENT_STATUS_FAILED: return 'Failed';
        case PAYMENT_STATUS_REFUNDED: return 'Refunded';
        default: return ucfirst($status);
    }
}

function getPaymentStatusIcon($status)
{
    switch ($status)
    {
        case PAYMENT_STATUS_COMPLETED: return 'fa-check-circle';
        case PAYMENT_STATUS_PENDING: return 'fa-clock';
        case PAYMENT_STATUS_FAILED: return 'fa-times-circle';
        case PAYMENT_STATUS_REFUNDED: return 'fa-undo-alt';
        default: return 'fa-circle';
    }
}

function escapeTransactionOutput($string)
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
    <title>Transactions · Campus Eats Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <style>
        .summary-grid
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

        .summary-card .label
        {
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: var(--space-1);
        }

        .summary-card .value
        {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .summary-card .value.currency { color: var(--orange); }

        .filter-bar
        {
            background: white;
            padding: var(--space-4) var(--space-5);
            border-radius: var(--radius-lg);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-4);
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
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: var(--space-1);
        }

        .filter-group input
        {
            width: 100%;
            padding: var(--space-2) var(--space-3);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
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
            flex: 0 0 auto;
        }

        .table-container
        {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            border-radius: var(--radius-lg);
            background: white;
            box-shadow: var(--shadow-sm);
        }

        .data-table
        {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
            min-width: 700px;
        }

        .data-table th,
        .data-table td
        {
            padding: var(--space-3) var(--space-4);
            text-align: left;
            border-bottom: 1px solid var(--gray-200);
        }

        .data-table th
        {
            background: var(--gray-50);
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--gray-600);
        }

        .data-table tr:hover td { background: var(--gray-50); }

        .payment-completed { color: var(--success); font-weight: 600; }
        .payment-pending { color: var(--warning); font-weight: 600; }
        .payment-failed { color: var(--error); font-weight: 600; }
        .payment-refunded { color: var(--info); font-weight: 600; }

        .empty-state
        {
            text-align: center;
            padding: var(--space-8) var(--space-4);
            color: var(--gray-500);
        }

        .empty-state i { font-size: 3rem; margin-bottom: var(--space-4); color: var(--gray-300); }

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

        @media (max-width: 1024px)
        {
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px)
        {
            .summary-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; }
            .filter-group { width: 100%; }
            .filter-actions { justify-content: flex-start; width: 100%; }
            .data-table { font-size: 0.75rem; min-width: auto; }
            .data-table th, .data-table td { padding: var(--space-2) var(--space-3); }
        }

        @media (max-width: 480px)
        {
            .summary-card .value { font-size: 1.25rem; }
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
                        <h1>Transactions</h1>
                        <p>Monitor all payment transactions across the platform</p>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeTransactionOutput($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="summary-grid">
                        <div class="summary-card">
                            <i class="fas fa-receipt"></i>
                            <div class="label">Total Transactions</div>
                            <div class="value"><?php echo number_format($totalTransactions); ?></div>
                        </div>
                        <div class="summary-card">
                            <i class="fas fa-rand"></i>
                            <div class="label">Total Revenue</div>
                            <div class="value currency">R <?php echo number_format($totalRevenue, 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <i class="fas fa-clock"></i>
                            <div class="label">Pending Payments</div>
                            <div class="value currency">R <?php echo number_format($pendingPayments, 2); ?></div>
                        </div>
                        <div class="summary-card">
                            <i class="fas fa-calculator"></i>
                            <div class="label">Average Transaction</div>
                            <div class="value currency">R <?php echo number_format($avgTransaction, 2); ?></div>
                        </div>
                    </div>

                    <div class="filter-bar">
                        <div class="filter-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?php echo escapeTransactionOutput($startDate); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?php echo escapeTransactionOutput($endDate); ?>">
                        </div>
                        <div class="filter-actions">
                            <button onclick="applyFilters()" class="btn btn-primary">Apply</button>
                            <a href="monitor_transactions.php" class="btn btn-outline">Reset</a>
                        </div>
                    </div>

                    <div class="table-container">
                        <?php if (empty($transactions)): ?>
                            <div class="empty-state">
                                <i class="fas fa-receipt"></i>
                                <p>No transactions found for the selected period.</p>
                            </div>
                        <?php else: ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Order</th>
                                        <th>Customer</th>
                                        <th>Vendor</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y g:i A', strtotime($transaction['payment_date'])); ?></td>
                                        <td>
                                            <a href="order_management.php?order_id=<?php echo $transaction['order_id']; ?>">
                                                <?php echo escapeTransactionOutput($transaction['order_number']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo escapeTransactionOutput($transaction['customer_name']); ?></td>
                                        <td><?php echo escapeTransactionOutput($transaction['vendor_name']); ?></td>
                                        <td><strong>R <?php echo number_format($transaction['amount'], 2); ?></strong></td>
                                        <td>
                                            <?php
                                            $method = $transaction['payment_method'];
                                            if ($method === PAYMENT_METHOD_CAMPUS_WALLET) echo 'Wallet';
                                            elseif ($method === PAYMENT_METHOD_CREDIT_CARD) echo 'Card';
                                            elseif ($method === PAYMENT_METHOD_MEAL_PLAN) echo 'Meal Plan';
                                            else echo ucfirst(str_replace('_', ' ', $method));
                                            ?>
                                        </td>
                                        <td>
                                            <span class="<?php echo getPaymentStatusBadgeClass($transaction['payment_status']); ?>">
                                                <i class="fas <?php echo getPaymentStatusIcon($transaction['payment_status']); ?>"></i>
                                                <?php echo getPaymentStatusText($transaction['payment_status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" class="pagination-item">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            <span class="pagination-item active"><?php echo $page; ?> of <?php echo $totalPages; ?></span>
                            <?php if ($page < $totalPages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>" class="pagination-item">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <button class="sidebar-toggle" id="menuToggleBtn" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <script>
        function applyFilters()
        {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            window.location.href = '?start_date=' + startDate + '&end_date=' + endDate;
        }
    </script>

    <script src="<?php echo ASSETS_URL; ?>/js/dashboard-common.js"></script>
</body>
</html>