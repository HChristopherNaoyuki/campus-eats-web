<?php
/**
 * View Feedback Page for Administrators
 *
 * Displays all complaints and compliments submitted through the feedback
 * form. Feedback is stored in the MySQL complaints_compliments table.
 *
 * CORRECTIONS (Version 17.0 - MySQL Only):
 * - Removed the Firebase feedback panel and its JavaScript. The panel
 *   called window.Feedback.getAllFeedback(), which triggered a full read
 *   of the feedback node. The Firebase rules deny that read because the
 *   feedback node itself declares no read permission, and a child rule
 *   does not retroactively grant a parent-level list read.
 * - Removed the resolveFbFeedback() and unresolveFbFeedback() calls.
 *   Those functions were never exported by feedback-firebase.js, so the
 *   buttons would have thrown "not a function" at runtime.
 * - Removed the firebase.js and feedback-firebase.js script tags. They
 *   are not needed for this page.
 * - Retains the MySQL feedback list, the resolve/unresolve actions, and
 *   the shared escapeOutput() helper.
 *
 * SOURCE: Review item 7 - Feedback / Firebase integration is non-functional
 *
 * @version 17.0
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

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!validateCsrfToken($submittedToken))
    {
        $error = 'Security validation failed. Please refresh the page.';
        writeLog("View feedback CSRF validation failed.", "ADMIN");
    }
    else
    {
        $actionType = isset($_POST['action_type']) ? $_POST['action_type'] : '';
        $entryId = (int)(isset($_POST['entry_id']) ? $_POST['entry_id'] : 0);

        if ($actionType === 'resolve' && $entryId > 0)
        {
            $db->executeQuery(
                "UPDATE complaints_compliments SET is_resolved = 1 WHERE entry_id = :entry_id",
                array('entry_id' => $entryId)
            );
            $message = 'Feedback marked as resolved.';
            writeLog("Admin resolved feedback entry ID: $entryId", "ADMIN");
        }
        elseif ($actionType === 'unresolve' && $entryId > 0)
        {
            $db->executeQuery(
                "UPDATE complaints_compliments SET is_resolved = 0 WHERE entry_id = :entry_id",
                array('entry_id' => $entryId)
            );
            $message = 'Feedback marked as unresolved.';
            writeLog("Admin marked feedback entry ID: $entryId as unresolved", "ADMIN");
        }
    }

    $csrfToken = getCsrfToken();
}

$mysqlFeedback = $db->fetchAll(
    "SELECT cc.entry_id, cc.entry_type, cc.subject, cc.message,
            cc.is_resolved, cc.created_at,
            u.full_name as submitter_name, u.username, u.account_type
     FROM complaints_compliments cc
     JOIN users u ON cc.user_id = u.user_id
     ORDER BY cc.created_at DESC
     LIMIT 100"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title>View Feedback - Campus Eats Admin</title>
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
                    <div class="page-header">
                        <h1>Feedback Forum</h1>
                        <p>View and manage student and vendor complaints and compliments</p>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Success</div>
                                <div class="alert-message"><?php echo escapeOutput($message); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeOutput($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-comment-dots"></i> All Feedback</h3>
                            <span class="badge badge-info"><?php echo count($mysqlFeedback); ?> entries</span>
                        </div>
                        <div class="dashboard-card-body">
                            <?php if (empty($mysqlFeedback)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-comment-dots"></i>
                                    <p>No feedback submitted yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($mysqlFeedback as $entry): ?>
                                    <div class="feedback-card"
                                         style="background: white; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-sm); border: 1px solid var(--gray-100); margin-bottom: var(--space-4);<?php echo $entry['is_resolved'] ? '' : ' border-left: 4px solid var(--warning);'; ?>">
                                        <div class="feedback-card-header"
                                             style="background: var(--gray-50); padding: var(--space-3) var(--space-5); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-3); border-bottom: 1px solid var(--gray-200);">
                                            <div class="feedback-type"
                                                 style="display: flex; align-items: center; gap: var(--space-2);">
                                                <i class="fas <?php echo $entry['entry_type'] === 'complaint' ? 'fa-exclamation-triangle' : 'fa-heart'; ?>"
                                                   style="color: <?php echo $entry['entry_type'] === 'complaint' ? 'var(--error)' : 'var(--success)'; ?>; font-size: 1.125rem;"></i>
                                                <span class="badge badge-<?php echo $entry['entry_type'] === 'complaint' ? 'error' : 'success'; ?>">
                                                    <?php echo ucfirst(escapeOutput($entry['entry_type'])); ?>
                                                </span>
                                                <span style="font-weight: 600; font-size: 0.9375rem; color: var(--gray-800);">
                                                    <?php echo escapeOutput($entry['subject']); ?>
                                                </span>
                                            </div>
                                            <div style="display: flex; gap: var(--space-2); align-items: center;">
                                                <span class="badge badge-<?php echo $entry['is_resolved'] ? 'success' : 'warning'; ?>">
                                                    <i class="fas <?php echo $entry['is_resolved'] ? 'fa-check' : 'fa-clock'; ?>"></i>
                                                    <?php echo $entry['is_resolved'] ? 'Resolved' : 'Unresolved'; ?>
                                                </span>
                                                <span style="font-size: 0.75rem; color: var(--gray-500);">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($entry['created_at'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="feedback-card-body"
                                             style="padding: var(--space-4) var(--space-5);">
                                            <div style="color: var(--gray-700); line-height: 1.6; padding: var(--space-3) var(--space-4); background: var(--gray-50); border-radius: var(--radius-md); margin-bottom: var(--space-3); font-size: 0.875rem;">
                                                <?php echo nl2br(escapeOutput($entry['message'])); ?>
                                            </div>
                                            <div style="font-size: 0.8125rem; color: var(--gray-600); border-top: 1px solid var(--gray-200); padding-top: var(--space-3); display: flex; align-items: center; gap: var(--space-2); flex-wrap: wrap;">
                                                <i class="fas fa-user-circle" style="color: var(--orange);"></i>
                                                <strong><?php echo escapeOutput($entry['submitter_name']); ?></strong>
                                                <span>(<?php echo ucfirst(escapeOutput($entry['account_type'])); ?>)</span>
                                                <span style="font-size: 0.75rem; color: var(--gray-500);">
                                                    <i class="fas fa-at"></i> <?php echo escapeOutput($entry['username']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="feedback-card-footer"
                                             style="padding: var(--space-3) var(--space-5); border-top: 1px solid var(--gray-200); background: var(--gray-50);">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="csrf_token"
                                                       value="<?php echo escapeOutput($csrfToken); ?>">
                                                <input type="hidden" name="entry_id"
                                                       value="<?php echo (int)$entry['entry_id']; ?>">
                                                <?php if ($entry['is_resolved']): ?>
                                                    <input type="hidden" name="action_type" value="unresolve">
                                                    <button type="submit" class="btn btn-outline btn-sm">
                                                        <i class="fas fa-undo"></i> Mark Unresolved
                                                    </button>
                                                <?php else: ?>
                                                    <input type="hidden" name="action_type" value="resolve">
                                                    <button type="submit" class="btn btn-success btn-sm">
                                                        <i class="fas fa-check"></i> Mark Resolved
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        </div>
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