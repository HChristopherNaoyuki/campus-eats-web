<?php
/**
 * View Feedback Page for Administrators - With Firebase Integration
 *
 * This page allows administrators to view all submitted complaints and compliments
 * from both MySQL and Firebase Realtime Database.
 *
 * CORRECTIONS (Version 16.0 - Firebase Integration):
 * - Added Firebase feedback viewing
 * - Merged feedback from both sources
 * - Added Firebase admin authorization
 * - Improved responsive behavior
 *
 * SOURCE: campus-eats-process-document.pdf (Section 6.4 - View feedback)
 * SOURCE: Mockups - Feedback design
 * SOURCE: Firebase Realtime Database Documentation
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

// Handle feedback actions
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($submittedToken))
    {
        $error = 'Security validation failed. Please refresh the page.';
        writeLog("View feedback CSRF validation failed.", "ADMIN");
    }
    else
    {
        $actionType = $_POST['action_type'] ?? '';
        $entryId = (int)($_POST['entry_id'] ?? 0);

        if ($actionType === 'resolve' && $entryId > 0)
        {
            $db->executeQuery
            (
                "UPDATE complaints_compliments SET is_resolved = 1 WHERE entry_id = :entry_id",
                array('entry_id' => $entryId)
            );
            $message = 'Feedback marked as resolved.';
            writeLog("Admin resolved feedback entry ID: $entryId", "ADMIN");
        }
        elseif ($actionType === 'unresolve' && $entryId > 0)
        {
            $db->executeQuery
            (
                "UPDATE complaints_compliments SET is_resolved = 0 WHERE entry_id = :entry_id",
                array('entry_id' => $entryId)
            );
            $message = 'Feedback marked as unresolved.';
            writeLog("Admin marked feedback entry ID: $entryId as unresolved", "ADMIN");
        }
    }
    $csrfToken = getCsrfToken();
}

// Fetch MySQL feedback entries
$mysqlFeedback = $db->fetchAll(
    "SELECT cc.entry_id, cc.entry_type, cc.subject, cc.message,
            cc.is_resolved, cc.created_at,
            u.full_name as submitter_name, u.username, u.account_type
     FROM complaints_compliments cc
     JOIN users u ON cc.user_id = u.user_id
     ORDER BY cc.created_at DESC
     LIMIT 50"
);

// Mark MySQL entries with source
foreach ($mysqlFeedback as &$entry)
{
    $entry['source'] = 'mysql';
    $entry['entry_id_display'] = 'MYSQL-' . $entry['entry_id'];
}

// Firebase feedback will be loaded via JavaScript
// We'll display a placeholder section for Firebase feedback

function escapeFeedbackAdmin($string)
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
    <meta name="csrf-token" content="<?php echo escapeFeedbackAdmin($csrfToken); ?>">
    <title>View Feedback · Campus Eats Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <style>
        .feedback-grid
        {
            display: flex;
            flex-direction: column;
            gap: var(--space-4);
        }

        .feedback-card
        {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: box-shadow var(--transition-base);
            border: 1px solid var(--gray-100);
        }

        .feedback-card:hover
        {
            box-shadow: var(--shadow-lg);
        }

        .feedback-card.unresolved
        {
            border-left: 4px solid var(--warning);
        }

        .feedback-card-header
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

        .feedback-type
        {
            display: flex;
            align-items: center;
            gap: var(--space-2);
        }

        .feedback-type i { font-size: 1.125rem; }
        .feedback-type.complaint i { color: var(--error); }
        .feedback-type.compliment i { color: var(--success); }

        .feedback-subject
        {
            font-weight: 600;
            font-size: 0.9375rem;
            color: var(--gray-800);
        }

        .feedback-card-body { padding: var(--space-4) var(--space-5); }

        .feedback-message
        {
            color: var(--gray-700);
            line-height: 1.6;
            margin-bottom: var(--space-3);
            padding: var(--space-3) var(--space-4);
            background: var(--gray-50);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
        }

        .feedback-author
        {
            font-size: 0.8125rem;
            color: var(--gray-600);
            border-top: 1px solid var(--gray-200);
            padding-top: var(--space-3);
            margin-top: var(--space-2);
            display: flex;
            align-items: center;
            gap: var(--space-2);
            flex-wrap: wrap;
        }

        .feedback-author i { color: var(--orange); }

        .feedback-source
        {
            font-size: 0.6875rem;
            color: var(--gray-400);
            padding: var(--space-1) var(--space-2);
            background: var(--gray-100);
            border-radius: var(--radius-sm);
            margin-left: auto;
        }

        .badge-resolved { background: var(--success-bg); color: var(--success-text); }
        .badge-unresolved { background: var(--warning-bg); color: var(--warning-text); }
        .badge-complaint { background: var(--error-bg); color: var(--error-text); }
        .badge-compliment { background: var(--success-bg); color: var(--success-text); }

        .feedback-card-footer
        {
            padding: var(--space-3) var(--space-5);
            border-top: 1px solid var(--gray-200);
            background: var(--gray-50);
            display: flex;
            gap: var(--space-3);
            flex-wrap: wrap;
        }

        .fb-feedback-container
        {
            min-height: 100px;
        }

        .fb-loading
        {
            text-align: center;
            padding: var(--space-4);
            color: var(--gray-500);
        }

        .fb-loading i
        {
            animation: spin 1s linear infinite;
            font-size: 1.5rem;
            color: var(--orange);
            margin-right: var(--space-2);
        }

        @keyframes spin
        {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .admin-badge
        {
            background: var(--orange);
            color: white;
            padding: var(--space-1) var(--space-2);
            border-radius: var(--radius-sm);
            font-size: 0.625rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        @media (max-width: 768px)
        {
            .feedback-card-header { flex-direction: column; text-align: center; }
            .feedback-card-body { padding: var(--space-3) var(--space-4); }
            .feedback-message { padding: var(--space-2) var(--space-3); font-size: 0.8125rem; }
            .feedback-author { flex-direction: column; align-items: center; text-align: center; }
            .feedback-source { margin-left: 0; }
        }

        @media (max-width: 480px)
        {
            .feedback-card-header { padding: var(--space-2) var(--space-3); }
            .feedback-card-footer { padding: var(--space-2) var(--space-3); }
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
                        <h1>Feedback Forum</h1>
                        <p>View and manage student and vendor complaints and compliments</p>
                        <p class="text-small" style="margin-top: var(--space-2);">
                            <i class="fas fa-database"></i> 
                            <span class="badge badge-info">MySQL</span> Local database feedback
                            <span class="badge badge-info" style="margin-left: var(--space-2);">Firebase</span> Cloud database feedback
                        </p>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Success</div>
                                <div class="alert-message"><?php echo escapeFeedbackAdmin($message); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeFeedbackAdmin($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- MySQL Feedback -->
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-database"></i> MySQL Feedback</h3>
                            <span class="badge badge-info"><?php echo count($mysqlFeedback); ?> entries</span>
                        </div>
                        <div class="dashboard-card-body">
                            <?php if (empty($mysqlFeedback)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-comment-dots"></i>
                                    <p>No feedback submitted yet.</p>
                                </div>
                            <?php else: ?>
                                <div class="feedback-grid">
                                    <?php foreach ($mysqlFeedback as $entry): ?>
                                        <div class="feedback-card <?php echo $entry['is_resolved'] ? '' : 'unresolved'; ?>">
                                            <div class="feedback-card-header">
                                                <div class="feedback-type <?php echo $entry['entry_type']; ?>">
                                                    <i class="fas <?php echo $entry['entry_type'] === 'complaint' ? 'fa-exclamation-triangle' : 'fa-heart'; ?>"></i>
                                                    <span class="badge badge-<?php echo $entry['entry_type']; ?>"><?php echo ucfirst($entry['entry_type']); ?></span>
                                                    <span class="feedback-subject"><?php echo escapeFeedbackAdmin($entry['subject']); ?></span>
                                                    <?php if (!$entry['is_resolved']): ?>
                                                        <span class="badge badge-unresolved" style="font-size: 0.65rem;">
                                                            <i class="fas fa-circle" style="font-size: 0.5rem; color: var(--warning);"></i>
                                                            New
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="feedback-meta">
                                                    <span class="badge badge-<?php echo $entry['is_resolved'] ? 'resolved' : 'unresolved'; ?>">
                                                        <i class="fas <?php echo $entry['is_resolved'] ? 'fa-check' : 'fa-clock'; ?>"></i>
                                                        <?php echo $entry['is_resolved'] ? 'Resolved' : 'Unresolved'; ?>
                                                    </span>
                                                    <span class="feedback-date" style="font-size: 0.75rem; color: var(--gray-500);">
                                                        <i class="fas fa-calendar-alt"></i>
                                                        <?php echo date('M j, Y g:i A', strtotime($entry['created_at'])); ?>
                                                    </span>
                                                    <span class="feedback-source">
                                                        <i class="fas fa-database"></i> MySQL
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="feedback-card-body">
                                                <div class="feedback-message">
                                                    <?php echo nl2br(escapeFeedbackAdmin($entry['message'])); ?>
                                                </div>
                                                <div class="feedback-author">
                                                    <i class="fas fa-user-circle"></i>
                                                    <strong><?php echo escapeFeedbackAdmin($entry['submitter_name']); ?></strong>
                                                    <span class="author-role">(<?php echo ucfirst(escapeFeedbackAdmin($entry['account_type'])); ?>)</span>
                                                    <span style="font-size: 0.75rem; color: var(--gray-500);">
                                                        <i class="fas fa-at"></i> <?php echo escapeFeedbackAdmin($entry['username']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="feedback-card-footer">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="csrf_token" value="<?php echo escapeFeedbackAdmin($csrfToken); ?>">
                                                    <input type="hidden" name="entry_id" value="<?php echo $entry['entry_id']; ?>">
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
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Firebase Feedback -->
                    <div class="dashboard-card" style="margin-top: var(--space-4);">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-cloud"></i> Firebase Feedback</h3>
                            <span class="badge badge-info" id="fb-feedback-count">Loading...</span>
                        </div>
                        <div class="dashboard-card-body">
                            <div id="fb-feedback-container" class="fb-feedback-container">
                                <div class="fb-loading">
                                    <i class="fas fa-spinner"></i>
                                    Loading Firebase feedback...
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

    <!-- Firebase Integration -->
    <script src="<?php echo ASSETS_URL; ?>/js/firebase.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/feedback-firebase.js"></script>
    <script>
        /**
         * Renders Firebase feedback for admin view.
         */
        function renderFirebaseFeedback(feedbackList)
        {
            const container = document.getElementById('fb-feedback-container');
            const countBadge = document.getElementById('fb-feedback-count');

            if (!container) return;

            if (!feedbackList || feedbackList.length === 0)
            {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comment-dots"></i>
                        <p>No Firebase feedback submissions yet.</p>
                    </div>
                `;

                if (countBadge) countBadge.textContent = '0 entries';
                return;
            }

            if (countBadge) countBadge.textContent = feedbackList.length + ' entries';

            let html = '<div class="feedback-grid">';

            for (let i = 0; i < feedbackList.length; i++)
            {
                const entry = feedbackList[i];
                const isResolved = entry.status === 'resolved';
                const type = entry.type || 'compliment';
                const isComplaint = type === 'complaint';
                const isCompliment = type === 'compliment';

                // Escape HTML
                const subject = escapeHtml(entry.subject || 'No subject');
                const message = escapeHtml(entry.message || 'No message');
                const userName = escapeHtml(entry.userName || 'Unknown User');
                const userEmail = escapeHtml(entry.userEmail || '');
                const userRole = escapeHtml(entry.phpUserRole || 'user');
                const createdAt = entry.createdAt ? new Date(entry.createdAt) : new Date();

                html += `
                    <div class="feedback-card ${isResolved ? '' : 'unresolved'}">
                        <div class="feedback-card-header">
                            <div class="feedback-type ${type}">
                                <i class="fas ${isComplaint ? 'fa-exclamation-triangle' : 'fa-heart'}"></i>
                                <span class="badge badge-${type}">${isComplaint ? 'Complaint' : 'Compliment'}</span>
                                <span class="feedback-subject">${subject}</span>
                                ${!isResolved ? `
                                    <span class="badge badge-unresolved" style="font-size: 0.65rem;">
                                        <i class="fas fa-circle" style="font-size: 0.5rem; color: var(--warning);"></i>
                                        New
                                    </span>
                                ` : ''}
                            </div>
                            <div class="feedback-meta">
                                <span class="badge badge-${isResolved ? 'resolved' : 'unresolved'}">
                                    <i class="fas ${isResolved ? 'fa-check' : 'fa-clock'}"></i>
                                    ${isResolved ? 'Resolved' : 'Unresolved'}
                                </span>
                                <span class="feedback-date" style="font-size: 0.75rem; color: var(--gray-500);">
                                    <i class="fas fa-calendar-alt"></i>
                                    ${formatDate(createdAt)}
                                </span>
                                <span class="feedback-source">
                                    <i class="fas fa-cloud"></i> Firebase
                                </span>
                            </div>
                        </div>
                        <div class="feedback-card-body">
                            <div class="feedback-message">
                                ${message}
                            </div>
                            <div class="feedback-author">
                                <i class="fas fa-user-circle"></i>
                                <strong>${userName}</strong>
                                <span class="author-role">(${userRole})</span>
                                ${userEmail ? `<span style="font-size: 0.75rem; color: var(--gray-500);">
                                    <i class="fas fa-envelope"></i> ${userEmail}
                                </span>` : ''}
                                ${entry._id ? `<span style="font-size: 0.65rem; color: var(--gray-400); margin-left: auto;">
                                    ID: ${escapeHtml(entry._id.substring(0, 8))}...
                                </span>` : ''}
                            </div>
                        </div>
                        <div class="feedback-card-footer">
                            ${!isResolved ? `
                                <button class="btn btn-success btn-sm" onclick="resolveFbFeedback('${entry._id}')">
                                    <i class="fas fa-check"></i> Mark Resolved
                                </button>
                            ` : `
                                <button class="btn btn-outline btn-sm" onclick="unresolveFbFeedback('${entry._id}')">
                                    <i class="fas fa-undo"></i> Mark Unresolved
                                </button>
                            `}
                        </div>
                    </div>
                `;
            }

            html += '</div>';
            container.innerHTML = html;
        }

        /**
         * Escapes HTML special characters.
         */
        function escapeHtml(text)
        {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Formats a date for display.
         */
        function formatDate(date)
        {
            if (!date || isNaN(date.getTime())) return 'Unknown date';
            return date.toLocaleString('en-ZA', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        /**
         * Resolves Firebase feedback (admin only).
         */
        function resolveFbFeedback(feedbackId)
        {
            if (!feedbackId) return;

            if (!confirm('Mark this feedback as resolved?')) return;

            if (typeof window.Feedback !== 'undefined')
            {
                window.Feedback.resolveFeedback(feedbackId)
                    .then(function()
                    {
                        alert('Feedback marked as resolved.');
                        loadFirebaseFeedback();
                    })
                    .catch(function(error)
                    {
                        alert('Failed to resolve feedback: ' + error.message);
                    });
            }
            else
            {
                alert('Firebase module not available.');
            }
        }

        /**
         * Unresolves Firebase feedback (admin only).
         */
        function unresolveFbFeedback(feedbackId)
        {
            if (!feedbackId) return;

            if (!confirm('Mark this feedback as unresolved?')) return;

            if (typeof window.Feedback !== 'undefined')
            {
                window.Feedback.unresolveFeedback(feedbackId)
                    .then(function()
                    {
                        alert('Feedback marked as unresolved.');
                        loadFirebaseFeedback();
                    })
                    .catch(function(error)
                    {
                        alert('Failed to unresolve feedback: ' + error.message);
                    });
            }
            else
            {
                alert('Firebase module not available.');
            }
        }

        /**
         * Loads Firebase feedback from the database.
         */
        function loadFirebaseFeedback()
        {
            const container = document.getElementById('fb-feedback-container');

            if (!container) return;

            container.innerHTML = `
                <div class="fb-loading">
                    <i class="fas fa-spinner"></i>
                    Loading Firebase feedback...
                </div>
            `;

            if (typeof window.Feedback === 'undefined')
            {
                container.innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="alert-content">
                            <div class="alert-title">Error</div>
                            <div class="alert-message">Firebase module not loaded. Please refresh the page.</div>
                        </div>
                    </div>
                `;
                return;
            }

            window.Feedback.getAllFeedback()
                .then(function(feedbackList)
                {
                    renderFirebaseFeedback(feedbackList);
                })
                .catch(function(error)
                {
                    console.error('Failed to load Firebase feedback:', error);

                    if (error.message && error.message.includes('admin'))
                    {
                        container.innerHTML = `
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i>
                                <div class="alert-content">
                                    <div class="alert-title">Admin Access Required</div>
                                    <div class="alert-message">Your Firebase account does not have admin privileges. Please ensure your UID is added to admin_claims in Firebase.</div>
                                </div>
                            </div>
                        `;
                    }
                    else
                    {
                        container.innerHTML = `
                            <div class="alert alert-error">
                                <i class="fas fa-exclamation-circle"></i>
                                <div class="alert-content">
                                    <div class="alert-title">Error Loading Feedback</div>
                                    <div class="alert-message">${escapeHtml(error.message || 'Unknown error')}</div>
                                </div>
                            </div>
                        `;
                    }
                });
        }

        // Load Firebase feedback when page is ready
        document.addEventListener('DOMContentLoaded', function()
        {
            // Wait a moment for Firebase to initialize
            setTimeout(function()
            {
                loadFirebaseFeedback();
            }, 1000);

            // Also listen for Firebase auth changes
            document.addEventListener('firebase-auth-changed', function(event)
            {
                if (event.detail && event.detail.authenticated)
                {
                    console.log('Firebase authenticated, reloading feedback');
                    loadFirebaseFeedback();
                }
            });
        });
    </script>

    <script src="<?php echo ASSETS_URL; ?>/js/dashboard-common.js"></script>
</body>
</html>