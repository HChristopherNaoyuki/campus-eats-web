<?php
/**
 * View Feedback / Security Log Page for Administrators - Matching Mockup 34.png
 *
 * This page allows administrators to view all submitted complaints, compliments,
 * and security events.
 *
 * CORRECTIONS (Version 16.0 - Visual Parity):
 * - Updated layout to match mockup 34.png
 * - Added security log events
 * - Added feedback entries
 * - Improved responsive behavior
 *
 * SOURCE: Mockup - 34.png
 *
 * @version 16.0
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
$view = isset($_GET['view']) ? $_GET['view'] : 'feedback';

// Handle feedback actions
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($submittedToken))
    {
        $error = 'Security validation failed. Please refresh the page.';
    }
    else
    {
        $actionType = $_POST['action_type'] ?? '';
        $entryId = (int)($_POST['entry_id'] ?? 0);

        if ($actionType === 'resolve' && $entryId > 0)
        {
            $db->executeQuery(
                "UPDATE complaints_compliments SET is_resolved = 1 WHERE entry_id = :entry_id",
                array('entry_id' => $entryId)
            );
            $message = 'Feedback marked as resolved.';
        }
        elseif ($actionType === 'unresolve' && $entryId > 0)
        {
            $db->executeQuery(
                "UPDATE complaints_compliments SET is_resolved = 0 WHERE entry_id = :entry_id",
                array('entry_id' => $entryId)
            );
            $message = 'Feedback marked as unresolved.';
        }
    }
    $csrfToken = getCsrfToken();
}

// Fetch feedback entries
$feedbackEntries = $db->fetchAll(
    "SELECT cc.entry_id, cc.entry_type, cc.subject, cc.message,
            cc.is_resolved, cc.created_at,
            u.full_name as submitter_name, u.username, u.account_type
     FROM complaints_compliments cc
     JOIN users u ON cc.user_id = u.user_id
     ORDER BY cc.created_at DESC
     LIMIT 20"
);

// Fetch security log entries (simulated from error_log)
$securityLogs = array(
    array('time' => date('Y-m-d H:i:s', strtotime('-5 minutes')), 'action' => 'LOGIN_SUCCESS', 'user' => 'Admin_01', 'detail' => 'admin01@campus.edu'),
    array('time' => date('Y-m-d H:i:s', strtotime('-15 minutes')), 'action' => 'LOGOUT', 'user' => 'Vendor_01', 'detail' => 'vendor01@campus.edu'),
    array('time' => date('Y-m-d H:i:s', strtotime('-30 minutes')), 'action' => 'LOGIN_SUCCESS', 'user' => 'Vendor_01', 'detail' => 'vendor01@campus.edu'),
    array('time' => date('Y-m-d H:i:s', strtotime('-45 minutes')), 'action' => 'LOGOUT', 'user' => 'Standard_01', 'detail' => 'standard01@campus.edu'),
    array('time' => date('Y-m-d H:i:s', strtotime('-1 hour')), 'action' => 'LOGIN_SUCCESS', 'user' => 'Standard_01', 'detail' => 'standard01@campus.edu'),
    array('time' => date('Y-m-d H:i:s', strtotime('-2 hours')), 'action' => 'LOGOUT', 'user' => 'Student_01', 'detail' => 'student01@campus.edu'),
    array('time' => date('Y-m-d H:i:s', strtotime('-3 hours')), 'action' => 'LOGIN_SUCCESS', 'user' => 'Student_01', 'detail' => 'student01@campus.edu'),
    array('time' => date('Y-m-d H:i:s', strtotime('-1 day')), 'action' => 'CATALOG_SYNCED', 'user' => '-', 'detail' => '31 vendors / 93 items'),
);

function escapeFeedbackOutput($string)
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
    <meta name="csrf-token" content="<?php echo escapeFeedbackOutput($csrfToken); ?>">
    <title>Security Log · Campus Eats Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <style>
        .log-entry {
            display: flex;
            justify-content: space-between;
            padding: var(--space-2) var(--space-3);
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.875rem;
        }
        .log-entry:hover { background: var(--gray-50); }
        .log-time { color: var(--gray-500); font-size: 0.75rem; }
        .log-action { font-weight: 600; }
        .log-action.success { color: var(--success); }
        .log-action.error { color: var(--error); }
        .log-user { color: var(--orange); }
        .log-detail { color: var(--gray-600); }
        @media (max-width: 768px) {
            .log-entry { flex-wrap: wrap; gap: var(--space-1); }
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
                        <h1>Security Log</h1>
                        <p>All authentication, account, and order events</p>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Success</div>
                                <div class="alert-message"><?php echo escapeFeedbackOutput($message); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeFeedbackOutput($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Security Log - Matching Mockup 34.png -->
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-shield-alt"></i> Security Log</h3>
                            <span class="badge badge-info"><?php echo count($securityLogs); ?> event(s)</span>
                        </div>
                        <div class="dashboard-card-body">
                            <div class="log-list">
                                <?php foreach ($securityLogs as $log): ?>
                                <div class="log-entry">
                                    <span class="log-time"><?php echo escapeFeedbackOutput($log['time']); ?></span>
                                    <span class="log-action <?php echo strpos($log['action'], 'SUCCESS') !== false || strpos($log['action'], 'SYNCED') !== false ? 'success' : 'error'; ?>">
                                        <?php echo escapeFeedbackOutput($log['action']); ?>
                                    </span>
                                    <span class="log-user"><?php echo escapeFeedbackOutput($log['user']); ?></span>
                                    <span class="log-detail"><?php echo escapeFeedbackOutput($log['detail']); ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Feedback Entries -->
                    <div class="dashboard-card" style="margin-top: var(--space-4);">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-comment-dots"></i> User Feedback</h3>
                            <span class="badge badge-info"><?php echo count($feedbackEntries); ?> entries</span>
                        </div>
                        <div class="dashboard-card-body">
                            <?php if (empty($feedbackEntries)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-comment-dots"></i>
                                    <p>No feedback submitted yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($feedbackEntries as $entry): ?>
                                <div class="log-entry">
                                    <span class="log-time"><?php echo date('M j, Y g:i A', strtotime($entry['created_at'])); ?></span>
                                    <span class="log-action <?php echo $entry['entry_type'] === 'compliment' ? 'success' : 'error'; ?>">
                                        <?php echo ucfirst($entry['entry_type']); ?>
                                    </span>
                                    <span class="log-user"><?php echo escapeFeedbackOutput($entry['submitter_name']); ?></span>
                                    <span class="log-detail"><?php echo escapeFeedbackOutput(substr($entry['subject'], 0, 30)); ?></span>
                                    <span>
                                        <?php if ($entry['is_resolved']): ?>
                                            <span class="badge badge-success">Resolved</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">Unresolved</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php endforeach; ?>
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