<?php
/**
 * Security Log Page for Administrators
 *
 * Displays authentication, account, and order events.
 *
 * CORRECTION: This page was required by process document Section 10.3
 * and Section 12.4 but was missing from the codebase.
 *
 * SOURCE: Issue report - item 4
 *
 * @version 1.0
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

$auditLogPath = defined('AUDIT_LOG_PATH')
    ? AUDIT_LOG_PATH
    : dirname(__DIR__, 3) . '/Issues/audit_log.txt';

$entries = array();

if (file_exists($auditLogPath))
{
    $lines = file($auditLogPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($lines);
    $lines = array_slice($lines, 0, 200);

    foreach ($lines as $line)
    {
        if (preg_match(
            '/^\[(?<time>[^\]]+)\] \[AUDIT\] \[User: (?<user>[^\]]+)\] \[Username: (?<username>[^\]]+)\] \[Activity: (?<activity>[^\]]+)\] \[Result: (?<result>[^\]]+)\] \[IP: (?<ip>[^\]]+)\] \[Session: (?<session>[^\]]+)\] \[URI: (?<uri>[^\]]+)\] (?<detail>.*)$/',
            $line,
            $m
        ))
        {
            $entries[] = array(
                'time' => $m['time'],
                'user' => $m['user'],
                'username' => $m['username'],
                'activity' => $m['activity'],
                'result' => $m['result'],
                'ip' => $m['ip'],
                'session' => $m['session'],
                'uri' => $m['uri'],
                'detail' => $m['detail']
            );
        }
    }
}

function securityLogEscape($value)
{
    return escapeOutput($value);
}

$pageTitle = 'Security Log';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo securityLogEscape($csrfToken); ?>">
    <title>Security Log - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
    <style nonce="<?php echo securityLogEscape(CSP_NONCE); ?>">
        .log-entry
        {
            display: grid;
            grid-template-columns: 160px 140px 100px 1fr;
            gap: var(--space-3);
            padding: var(--space-3);
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.8125rem;
            align-items: center;
        }

        .log-entry:hover
        {
            background: var(--gray-50);
        }

        .log-time
        {
            color: var(--gray-500);
            font-size: 0.75rem;
        }

        .log-activity
        {
            font-weight: 600;
        }

        .log-activity.success
        {
            color: var(--success);
        }

        .log-activity.error
        {
            color: var(--error);
        }

        .log-user
        {
            color: var(--orange);
        }

        .log-detail
        {
            color: var(--gray-700);
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 768px)
        {
            .log-entry
            {
                grid-template-columns: 1fr;
            }

            .log-detail
            {
                white-space: normal;
            }
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
                        <p>Authentication, account, and order events</p>
                    </div>

                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-shield-alt"></i> Recent Events</h3>
                            <span class="badge badge-info">
                                <?php echo count($entries); ?> event(s)
                            </span>
                        </div>
                        <div class="dashboard-card-body">
                            <?php if (empty($entries)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-shield-alt"></i>
                                    <p>No security events recorded yet.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($entries as $entry): ?>
                                    <div class="log-entry">
                                        <span class="log-time">
                                            <?php echo securityLogEscape($entry['time']); ?>
                                        </span>
                                        <span class="log-activity <?php echo $entry['result'] === 'success' ? 'success' : 'error'; ?>">
                                            <?php echo securityLogEscape($entry['activity']); ?>
                                        </span>
                                        <span class="log-user">
                                            <?php echo securityLogEscape($entry['username']); ?>
                                        </span>
                                        <span class="log-detail" title="<?php echo securityLogEscape($entry['detail']); ?>">
                                            <?php echo securityLogEscape($entry['detail']); ?>
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