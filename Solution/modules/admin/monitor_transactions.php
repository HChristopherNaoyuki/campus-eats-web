<?php
/**
 * Monitor Transactions Page for Administrators
 *
 * Allows administrators to view all payment transactions in the system.
 *
 * CORRECTIONS (Version 15.0):
 * - Removed the redundant duplicate count query. Only one count query
 *   is executed per page load.
 * - Uses the shared escapeOutput() helper
 *
 * SOURCE: Issue report - items 16, 23
 *
 * @version 15.0
 */

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

$error = '';
$transactions = array();
$totalTransactions = 0;
$totalRevenue = 0.0;
$pendingPayments = 0.0;
$avgTransaction = 0.0;
$totalCount = 0;
$totalPages = 0;

try
{
    // CORRECTION: Single count query replaces the previous duplicate.
    $countResult = $db->fetchOne(
        "SELECT COUNT(*) as count FROM payments p $whereClause",
        $params
    );

    $totalCount = (int)(isset($countResult['count']) ? $countResult['count'] : 0);
    $totalTransactions = $totalCount;
    $totalPages = ($itemsPerPage > 0) ? (int)ceil($totalCount / $itemsPerPage) : 0;

    $revenueParams = array_merge($params, array('status' => PAYMENT_STATUS_COMPLETED));
    $whereClauseRevenue = $whereClause
        . ($whereClause ? ' AND ' : 'WHERE ')
        . "p.payment_status = :status";

    $revenueResult = $db->fetchOne(
        "SELECT COALESCE(SUM(p.amount), 0) as total
         FROM payments p
         $whereClauseRevenue",
        $revenueParams
    );

    $totalRevenue = (float)(isset($revenueResult['total']) ? $revenueResult['total'] : 0);

    $pendingParams = array_merge($params, array('status' => PAYMENT_STATUS_PENDING));
    $whereClausePending = $whereClause
        . ($whereClause ? ' AND ' : 'WHERE ')
        . "p.payment_status = :status";

    $pendingResult = $db->fetchOne(
        "SELECT COALESCE(SUM(p.amount), 0) as total
         FROM payments p
         $whereClausePending",
        $pendingParams
    );

    $pendingPayments = (float)(isset($pendingResult['total']) ? $pendingResult['total'] : 0);

    $avgResult = $db->fetchOne(
        "SELECT COALESCE(AVG(p.amount), 0) as avg_value
         FROM payments p
         $whereClauseRevenue",
        $revenueParams
    );

    $avgTransaction = (float)(isset($avgResult['avg_value']) ? $avgResult['avg_value'] : 0);

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

    $pdo = $db->getConnection();
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':limit', $itemsPerPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);

    foreach ($params as $key => $value)
    {
        $stmt->bindValue(':' . $key, $value);
    }

    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
}
catch (Exception $e)
{
    writeLog("Monitor transactions error: " . $e->getMessage(), "ADMIN");
    $error = "Unable to load transaction data. Please try again later.";
}

function getPaymentStatusBadgeClass($status)
{
    switch ($status)
    {
        case PAYMENT_STATUS_COMPLETED: return 'payment-completed';
        case PAYMENT_STATUS_PENDING:   return 'payment-pending';
        case PAYMENT_STATUS_FAILED:    return 'payment-failed';
        case PAYMENT_STATUS_REFUNDED:  return 'payment-refunded';
        default: return '';
    }
}

function getPaymentStatusText($status)
{
    switch ($status)
    {
        case PAYMENT_STATUS_COMPLETED: return 'Completed';
        case PAYMENT_STATUS_PENDING:   return 'Pending';
        case PAYMENT_STATUS_FAILED:    return 'Failed';
        case PAYMENT_STATUS_REFUNDED:  return 'Refunded';
        default: return ucfirst($status);
    }
}

function getPaymentStatusIcon($status)
{
    switch ($status)
    {
        case PAYMENT_STATUS_COMPLETED: return 'fa-check-circle';
        case PAYMENT_STATUS_PENDING:   return 'fa-clock';
        case PAYMENT_STATUS_FAILED:    return 'fa-times-circle';
        case PAYMENT_STATUS_REFUNDED:  return 'fa-undo-alt';
        default: return 'fa-circle';
    }
}

function getPaymentMethodLabel($method)
{
    switch ($method)
    {
        case PAYMENT_METHOD_DEBIT_CARD:    return 'Card';
        case PAYMENT_METHOD_CAMPUS_WALLET: return 'Wallet';
        case PAYMENT_METHOD_COUPONS:       return 'Coupons';
        default: return ucfirst(str_replace('_', ' ', $method));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title>Transactions - Campus Eats Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <style nonce="<?php echo escapeOutput(CSP_NONCE); ?>">
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

        .summary-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-lg); }

        .summary-card i { font-size: 2rem; color: var(--orange); margin-bottom: var(--space-2); }

        .summary-card .label
        {
            font-size: 0.75rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.02em;
            margin-bottom: var(--space-1);
        }

        .summary-card .value { font-size: 1.5rem; font-weight: 600; color: var(--gray-900); }
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

        .filter-group { flex: 1; min-width: 150px; }

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

        .filter-actions { display: flex; gap: var(--space-2); align-items: center; }

        .payment-completed { color: var(--success); font-weight: 600; }
        .payment-pending { color: var(--warning); font-weight: 600; }
        .payment-failed { color: var(--error); font-weight: 600; }
        .payment-refunded { color: var(--info); font-weight: 600; }

        @media (max-width: 1024px) { .summary-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 768px)
        {
            .summary-grid { grid-template-columns: 1fr; }
            .filter-bar { flex-direction: column; }
            .filter-actions { width: 100%; }
        }
        @media (max-width: 480px) { .summary-card .value { font-size: 1.25rem; } }
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
                                <div class="alert-message"><?php echo escapeOutput($error); ?></div>
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
                            <i class="fas fa-coins"></i>
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

                    <form method="GET" action="" class="filter-bar">
                        <div class="filter-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date"
                                   value="<?php echo escapeOutput($startDate); ?>">
                        </div>
                        <div class="filter-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date"
                                   value="<?php echo escapeOutput($endDate); ?>">
                        </div>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Apply</button>
                            <a href="monitor_transactions.php" class="btn btn-outline">Reset</a>
                        </div>
                    </form>

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
                                            <td data-label="Date">
                                                <?php echo date('M j, Y g:i A', strtotime($transaction['payment_date'])); ?>
                                            </td>
                                            <td data-label="Order">
                                                <a href="order_management.php?order_id=<?php echo (int)$transaction['order_id']; ?>">
                                                    <?php echo escapeOutput($transaction['order_number']); ?>
                                                </a>
                                            </td>
                                            <td data-label="Customer">
                                                <?php echo escapeOutput($transaction['customer_name']); ?>
                                            </td>
                                            <td data-label="Vendor">
                                                <?php echo escapeOutput($transaction['vendor_name']); ?>
                                            </td>
                                            <td data-label="Amount">
                                                <strong>R <?php echo number_format($transaction['amount'], 2); ?></strong>
                                            </td>
                                            <td data-label="Method">
                                                <?php echo escapeOutput(getPaymentMethodLabel($transaction['payment_method'])); ?>
                                            </td>
                                            <td data-label="Status">
                                                <span class="<?php echo getPaymentStatusBadgeClass($transaction['payment_status']); ?>">
                                                    <i class="fas <?php echo getPaymentStatusIcon($transaction['payment_status']); ?>"></i>
                                                    <?php echo escapeOutput(getPaymentStatusText($transaction['payment_status'])); ?>
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

    <script src="<?php echo ASSETS_URL; ?>/js/dashboard-common.js"></script>
</body>
</html>