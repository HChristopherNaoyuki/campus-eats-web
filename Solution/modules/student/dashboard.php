<?php
/**
 * Student and Standard Dashboard Page
 *
 * This page displays available vendors for students and standard
 * users. The vendor list is fetched from the Fake Restaurant API. When
 * the API is unreachable, the bundled fallback dataset is used so the
 * page remains functional.
 *
 * CORRECTIONS (Version 20.0 - requireStudentOrStandard Fix):
 *
 * - Defined requireStudentOrStandard() inside this file so the call
 *   on the original line 41 always succeeds. The function starts the
 *   session if necessary, verifies a non-empty user_id, and redirects
 *   unauthenticated visitors to the login page.
 * - All other dashboard behaviour (API fallback, degraded mode, toast,
 *   cart badge) is retained.
 *
 * SOURCE: SOFTWARE ENGINEER PROMPT – Fix Header Links and Logout.
 * SOURCE: Clean Code, Robert C. Martin, Chapters 2–4.
 *
 * @version 20.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/api_service.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

/**
 * Ensure the current user is authenticated (student or standard).
 * Redirects to login if the check fails.
 * Defined here so the call on the original line 41 succeeds.
 *
 * @return void
 */
if (!function_exists('requireStudentOrStandard'))
{
    function requireStudentOrStandard()
    {
        if (session_status() === PHP_SESSION_NONE)
        {
            session_start();
        }

        if (!isset($_SESSION['user_id'])
            || $_SESSION['user_id'] === ''
            || $_SESSION['user_id'] === null)
        {
            header('Location: ' . BASE_URL . '/modules/auth/login.php');
            exit;
        }

        // Role check – accept student or standard
        $role = isset($_SESSION['account_type'])
            ? $_SESSION['account_type']
            : '';

        if ($role !== 'student' && $role !== 'standard')
        {
            // Non-student/standard users are sent to their own area
            if ($role === 'admin')
            {
                header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
                exit;
            }
            if ($role === 'vendor')
            {
                header('Location: ' . BASE_URL . '/modules/vendor/dashboard.php');
                exit;
            }

            header('Location: ' . BASE_URL . '/modules/auth/login.php');
            exit;
        }
    }
}

// Start secure session and enforce the guard
if (function_exists('startSecureSession'))
{
    startSecureSession();
}
else
{
    if (session_status() === PHP_SESSION_NONE)
    {
        session_start();
    }
}

requireStudentOrStandard();

$db = getDB();
$currentUser = function_exists('getCurrentUser') ? getCurrentUser() : null;
$csrfToken = function_exists('getCsrfToken') ? getCsrfToken() : '';

$databaseAvailable = $db->isAvailable();

if (!$databaseAvailable)
{
    writeLog(
        'Dashboard rendered in degraded mode: ' . $db->getLastError(),
        'RESILIENCE'
    );
}

// =============================================================================
// Fetch Data from API
// =============================================================================

$apiService = getApiService();
$restaurants = array();
$error = '';

try
{
    $restaurants = $apiService->getAllRestaurants();
}
catch (Throwable $t)
{
    writeLog('Dashboard API failure: ' . $t->getMessage(), 'API_ERROR');
    $error = 'Unable to load vendor data at this time.';
}

$pageTitle = 'Dashboard';
require_once dirname(__DIR__, 2) . '/includes/dashboard_header.php';
?>

<div class="dashboard-content">
    <h1>Welcome<?php
        if ($currentUser && isset($currentUser['full_name']))
        {
            echo ', ' . htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8');
        }
    ?></h1>

    <?php if (!$databaseAvailable): ?>
        <div class="alert alert-warning" role="status">
            The database is temporarily unavailable. Showing cached vendor data.
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert">
            <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <section class="vendor-list" aria-labelledby="vendors-heading">
        <h2 id="vendors-heading">Available vendors</h2>

        <?php if (empty($restaurants)): ?>
            <div class="empty-state">
                <p>No vendors are available right now.</p>
            </div>
        <?php else: ?>
            <div class="vendor-grid">
                <?php foreach ($restaurants as $restaurant): ?>
                    <article class="vendor-card">
                        <h3><?php echo htmlspecialchars($restaurant['restaurantName'] ?? 'Vendor', ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?php echo htmlspecialchars($restaurant['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="vendor-type"><?php echo htmlspecialchars($restaurant['type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        <a href="<?php echo BASE_URL; ?>/modules/student/menu_browse.php?id=<?php
                            echo (int)($restaurant['restaurantID'] ?? 0);
                        ?>" class="btn btn-primary">View menu</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php
require_once dirname(__DIR__, 2) . '/includes/footer.php';
?>