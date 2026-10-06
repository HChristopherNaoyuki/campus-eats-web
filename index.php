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
 * - performLogout() fully clears $_SESSION, expires the session
 *   cookie, destroys the session, starts a fresh guest session and
 *   regenerates the session ID. This eliminates the residual
 *   “User: 1 / old session ID” entries that previously appeared after
 *   a logout redirect.
 * - The ?logout= handler calls performLogout() and then redirects to a
 *   clean URL without the query parameter.
 * - All other landing-page behaviour (API fallback, featured vendors,
 *   stats, accessibility, shared header/footer) is retained.
 *
 * SOURCE: SOFTWARE ENGINEER PROMPT – Fix Header Links and Logout.
 * SOURCE: Clean Code, Robert C. Martin, Chapters 2–4.
 *
 * @version 16.0
 */

// Load required dependencies
require_once 'solution/config/constants.php';
require_once 'solution/includes/auth.php';
require_once 'solution/includes/api_service.php';

// Set security headers for this public page
setSecurityHeaders();

// =============================================================================
// Complete Logout Helper
// =============================================================================

/**
 * Perform a complete logout.
 *
 * Clears every session variable, expires the session cookie,
 * destroys the session, starts a fresh guest session and
 * regenerates the session identifier. After this function returns
 * the request is in a clean guest state.
 *
 * @return void
 */
if (!function_exists('performLogout'))
{
    function performLogout()
    {
        // Unset all session variables
        $_SESSION = array();

        // Expire the session cookie
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

        // Destroy the session if it is still active
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
// Check for Logout Parameter (Prevents Auto-Redirect Loop)
// =============================================================================
//
// A user who has just logged out is redirected to this page with a
// logout query parameter. The session is destroyed completely and the
// user is redirected to a clean URL. Without this step the auto-redirect
// below would send the user back to the dashboard because residual
// session data would still be present.

if (isset($_GET['logout']))
{
    writeLog('Logout parameter detected in index.php', 'AUTH');
    performLogout();
    header('Location: ' . ROOT_URL . '/index.php');
    exit();
}

// =============================================================================
// Start Session for Authentication Check
// =============================================================================

if (session_status() !== PHP_SESSION_ACTIVE)
{
    session_start();
}

// =============================================================================
// Redirect Authenticated Users to Dashboard
// =============================================================================
//
// An authenticated user who reaches the landing page is redirected to
// the dashboard for their role. The redirect is role-aware.

if (isset($_SESSION['user_id'])
    && !empty($_SESSION['user_id'])
    && (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
        || isset($_SESSION['account_type'])))
{
    $accountType = isset($_SESSION['account_type'])
        ? $_SESSION['account_type']
        : '';

    writeLog(
        'User already logged in, redirecting to dashboard. Role: ' . $accountType,
        'AUTH'
    );

    if ($accountType === 'admin')
    {
        header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
        exit();
    }
    elseif ($accountType === 'vendor')
    {
        header('Location: ' . BASE_URL . '/modules/vendor/dashboard.php');
        exit();
    }
    elseif ($accountType === 'student' || $accountType === 'standard')
    {
        header('Location: ' . BASE_URL . '/modules/student/dashboard.php');
        exit();
    }
    else
    {
        writeLog('Invalid session data detected, clearing session', 'AUTH');
        performLogout();
        header('Location: ' . ROOT_URL . '/index.php');
        exit();
    }
}

// =============================================================================
// Generate CSRF Token for Login and Register Forms
// =============================================================================

if (empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// =============================================================================
// Fetch Data from the API Service
// =============================================================================
//
// The API service returns the live catalogue when the API is reachable
// and a bundled fallback catalogue when the API is unreachable and no
// stale response exists. The page does not distinguish between the
// live data and the fallback data. Both are rendered the same way.
// The error block is only shown when the API service throws and no
// fallback is available.

$apiService = getApiService();
$restaurants = array();
$featuredRestaurant = null;
$error = '';

try
{
    $restaurants = $apiService->getAllRestaurants();

    writeLog(
        'Fetched ' . count($restaurants) . ' restaurants from API',
        'API'
    );

    if (!empty($restaurants))
    {
        foreach ($restaurants as $rest)
        {
            try
            {
                $menu = $apiService->getRestaurantMenu($rest['restaurantID']);

                if (!empty($menu))
                {
                    $featuredRestaurant = $rest;
                    $featuredRestaurant['menu'] = array_slice($menu, 0, 4);
                    break;
                }
            }
            catch (Exception $e)
            {
                continue;
            }
        }
    }
}
catch (Exception $e)
{
    writeLog(
        'Error fetching restaurants from API: ' . $e->getMessage(),
        'API_ERROR'
    );
    $error = 'Unable to load restaurant data. Please try again later.';
}

$pageTitle = 'Skip the line. Pick up on campus.';

// =============================================================================
// Helper Functions
// =============================================================================

if (!function_exists('escapeOutput'))
{
    /**
     * Escapes a value for safe HTML output.
     *
     * @param mixed $value The value to escape
     * @return string The escaped value
     */
    function escapeOutput($value)
    {
        if ($value === null)
        {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('campusEatsIndexEscape'))
{
    /**
     * Local alias for the escape helper.
     *
     * @param mixed $value The value to escape
     * @return string The escaped value
     */
    function campusEatsIndexEscape($value)
    {
        return escapeOutput($value);
    }
}

// =============================================================================
// Compute Stats
// =============================================================================
//
// The stats are derived from the data the page has. When the data is
// the fallback dataset, the stats reflect the fallback dataset. The
// counts are always non-negative integers.

$totalRestaurants = count($restaurants);
$totalItems = 0;

if (!empty($restaurants))
{
    foreach ($restaurants as $rest)
    {
        try
        {
            $menu = $apiService->getRestaurantMenu($rest['restaurantID']);
            $totalItems += count($menu);
        }
        catch (Exception $e)
        {
            continue;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo campusEatsIndexEscape($csrfToken); ?>">
    <title>Campus Eats &middot; Skip the line. Pick up on campus.</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
</head>
<body>
    <?php include_once 'solution/includes/public_header.php'; ?>

    <main id="main-content">
        <!-- =====================================================================
             Hero Section
             ===================================================================== -->
        <section id="home" class="hero" aria-labelledby="hero-heading">
            <div class="container">
                <h1 id="hero-heading">
                    Skip the line.<br>
                    <span>Pick up on campus.</span>
                </h1>
                <p>
                    Campus Eats is the on-campus pickup network. Order ahead
                    from your favorite campus vendor, then grab it on the way
                    to class. No delivery fee, no waiting.
                </p>
                <div class="hero-buttons">
                    <a href="solution/modules/auth/register.php"
                       class="btn btn-primary">
                        <i class="fas fa-user-plus" aria-hidden="true"></i>
                        Order now
                    </a>
                    <a href="#vendors" class="btn btn-outline">
                        <i class="fas fa-store" aria-hidden="true"></i>
                        Browse vendors
                    </a>
                </div>
            </div>
        </section>

        <!-- =====================================================================
             Stats Section
             ===================================================================== -->
        <section class="stats" aria-label="Platform statistics">
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo (int)$totalRestaurants; ?></div>
                        <div class="stat-label">Vendors</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo (int)$totalItems; ?></div>
                        <div class="stat-label">Menu items</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">~8 min</div>
                        <div class="stat-label">Avg Pickup</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- =====================================================================
             How It Works Section
             ===================================================================== -->
        <section id="how-it-works"
                 class="how-it-works"
                 aria-labelledby="how-it-works-heading">
            <div class="container">
                <div class="section-title">
                    <h2 id="how-it-works-heading">Pickup in three steps</h2>
                    <p>
                        Designed around the campus rhythm, between lectures,
                        before practice, after the library.
                    </p>
                </div>
                <div class="steps">
                    <div class="step">
                        <div class="step-number" aria-hidden="true">1</div>
                        <h3>Browse and order</h3>
                        <p>
                            Pick items from any campus vendor and confirm
                            your order.
                        </p>
                    </div>
                    <div class="step">
                        <div class="step-number" aria-hidden="true">2</div>
                        <h3>Vendor prepares</h3>
                        <p>
                            Track status as it moves from Pending to
                            Preparing to Completed.
                        </p>
                    </div>
                    <div class="step">
                        <div class="step-number" aria-hidden="true">3</div>
                        <h3>Pick it up</h3>
                        <p>
                            Walk over to the vendor stall and grab your bag.
                            Done.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- =====================================================================
             Featured Vendors Section
             ===================================================================== -->
        <section id="vendors"
                 class="vendors"
                 aria-labelledby="vendors-heading">
            <div class="container">
                <div class="section-title">
                    <h2 id="vendors-heading">Featured vendors</h2>
                    <p>Order ahead from these campus stalls.</p>
                </div>

                <?php if ($error !== ''): ?>
                    <div class="alert alert-error" role="alert">
                        <?php echo campusEatsIndexEscape($error); ?>
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
                                    <h3><?php
                                        echo campusEatsIndexEscape(
                                            $restaurant['restaurantName'] ?? 'Vendor'
                                        );
                                    ?></h3>
                                </div>
                                <div class="featured-vendor-body">
                                    <p><?php
                                        echo campusEatsIndexEscape(
                                            $restaurant['address'] ?? ''
                                        );
                                    ?></p>
                                    <p class="vendor-type"><?php
                                        echo campusEatsIndexEscape(
                                            $restaurant['type'] ?? ''
                                        );
                                    ?></p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if ($featuredRestaurant !== null && !empty($featuredRestaurant['menu'])): ?>
                    <div class="menu-preview">
                        <h3>Sample menu – <?php
                            echo campusEatsIndexEscape(
                                $featuredRestaurant['restaurantName'] ?? ''
                            );
                        ?></h3>
                        <dl>
                            <?php foreach ($featuredRestaurant['menu'] as $item): ?>
                                <div class="menu-preview-item">
                                    <dt><?php
                                        echo campusEatsIndexEscape(
                                            $item['itemName'] ?? ''
                                        );
                                    ?></dt>
                                    <dd>R <?php
                                        echo campusEatsIndexEscape(
                                            isset($item['itemPrice'])
                                                ? number_format((float)$item['itemPrice'], 2)
                                                : '0.00'
                                        );
                                    ?></dd>
                                </div>
                            <?php endforeach; ?>
                        </dl>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- =====================================================================
             Features Section
             ===================================================================== -->
        <section class="features" aria-labelledby="features-heading">
            <div class="container">
                <div class="section-title">
                    <h2 id="features-heading">Everything the system manages</h2>
                </div>
                <div class="features-grid">
                    <div class="feature-card">
                        <i class="fas fa-users" aria-hidden="true"></i>
                        <h3>User management</h3>
                        <p>Students, standard users, vendors and administrators each have a dedicated workspace.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-store" aria-hidden="true"></i>
                        <h3>Vendor management</h3>
                        <p>Vendors control their own menu, availability and order queue.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-utensils" aria-hidden="true"></i>
                        <h3>Menu management</h3>
                        <p>Items, prices and descriptions stay in sync across web and mobile.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                        <h3>Order management</h3>
                        <p>From placement through preparation to pickup, every status is tracked.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once 'solution/includes/footer.php'; ?>
</body>
</html>