<?php
/**
 * Campus Eats - Landing Page (Entry Point)
 *
 * Serves as the landing page for unauthenticated users. Displays real
 * API data from the Fake Restaurant API when the API is reachable.
 * Displays the bundled fallback dataset when the API is unreachable.
 *
 * CORRECTIONS (Version 16.0 - Complete Logout):
 *
 * - performLogout() now fully clears $_SESSION, expires the session
 *   cookie, destroys the session, starts a fresh guest session and
 *   regenerates the session ID. This eliminates the residual
 *   “User: 1 / old session ID” entries that previously appeared after
 *   a logout redirect.
 * - The ?logout= handler calls performLogout() and then redirects to a
 *   clean URL without the query parameter.
 * - All other landing-page behaviour (API fallback, featured vendors,
 *   stats, accessibility) is retained.
 *
 * SOURCE: SOFTWARE ENGINEER PROMPT – Fix Header Links and Logout.
 * SOURCE: Clean Code, Robert C. Martin, Chapters 2–4.
 *
 * @version 16.0
 */

require_once 'solution/config/constants.php';
require_once 'solution/includes/auth.php';
require_once 'solution/includes/api_service.php';

setSecurityHeaders();

// =============================================================================
// Complete Logout Helper
// =============================================================================

/**
 * Perform a complete logout.
 * Clears session data, regenerates ID, expires auth cookies,
 * then leaves the request in a clean guest state.
 *
 * @return void
 */
if (!function_exists('performLogout'))
{
    function performLogout()
    {
        // Unset all session variables
        $_SESSION = array();

        // Destroy the session cookie
        if (ini_get('session.use_cookies'))
        {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        // Destroy the session
        if (session_status() === PHP_SESSION_ACTIVE)
        {
            session_destroy();
        }

        // Start a fresh guest session and regenerate the ID
        session_start();
        session_regenerate_id(true);

        writeLog('performLogout completed – session fully cleared', 'AUTH');
    }
}

// =============================================================================
// Logout Query Parameter
// =============================================================================

if (isset($_GET['logout']))
{
    writeLog('Logout parameter detected in index.php', 'AUTH');
    performLogout();
    header('Location: ' . ROOT_URL . '/index.php');
    exit;
}

// =============================================================================
// Authenticated users are sent to their role dashboard
// =============================================================================

if (function_exists('isLoggedIn') && isLoggedIn())
{
    $role = function_exists('getCurrentUserRole') ? getCurrentUserRole() : '';

    switch ($role)
    {
        case 'admin':
            header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
            exit;
        case 'vendor':
            header('Location: ' . BASE_URL . '/modules/vendor/dashboard.php');
            exit;
        case 'student':
        case 'standard':
            header('Location: ' . BASE_URL . '/modules/student/dashboard.php');
            exit;
    }
}

// =============================================================================
// CSRF token and page data
// =============================================================================

$csrfToken = function_exists('getCsrfToken') ? getCsrfToken() : '';
$pageTitle = 'Home';

// Load restaurant data (live API or bundled fallback)
$apiService = getApiService();
$restaurants = array();
$error = '';

try
{
    $restaurants = $apiService->getAllRestaurants();
}
catch (Throwable $t)
{
    writeLog('Index page API failure: ' . $t->getMessage(), 'API_ERROR');
    $error = 'Unable to load restaurant data at this time.';
}

// Include the shared public header
require_once 'solution/includes/public_header.php';
?>

<section class="hero" id="home">
    <div class="container">
        <h1>Skip the line. Pick up on campus.</h1>
        <p>Order ahead from campus vendors and collect when it suits you.</p>
        <div class="hero-actions">
            <a href="<?php echo BASE_URL; ?>/modules/auth/register.php"
               class="btn btn-primary">Get started</a>
            <a href="#vendors" class="btn btn-outline">Browse vendors</a>
        </div>
    </div>
</section>

<section class="vendors" id="vendors" aria-labelledby="vendors-heading">
    <div class="container">
        <h2 id="vendors-heading">Featured vendors</h2>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error" role="alert">
                <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($restaurants)): ?>
            <div class="empty-state">
                <p>No vendors are available right now. Please try again later.</p>
            </div>
        <?php else: ?>
            <div class="vendor-grid">
                <?php foreach ($restaurants as $restaurant): ?>
                    <article class="featured-vendor">
                        <div class="featured-vendor-header">
                            <h3><?php echo htmlspecialchars($restaurant['restaurantName'] ?? 'Vendor', ENT_QUOTES, 'UTF-8'); ?></h3>
                        </div>
                        <div class="featured-vendor-body">
                            <p><?php echo htmlspecialchars($restaurant['address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="vendor-type"><?php echo htmlspecialchars($restaurant['type'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php
require_once 'solution/includes/footer.php';
?>