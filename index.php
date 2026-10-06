<?php
/**
 * Campus Eats - Landing Page (Entry Point)
 *
 * Serves as the landing page for unauthenticated users and for
 * authenticated admin users. Displays real API data from the Fake
 * Restaurant API when reachable; otherwise the bundled fallback.
 *
 * CORRECTIONS (Version 17.0 - Admin Redirect Loop Fix):
 *
 * - Authenticated admin users are no longer redirected away from
 *   the landing page. The previous version sent them to the
 *   non-existent path modules/admin/dashboard.php (or, after an
 *   intermediate change, back to index.php itself), producing an
 *   infinite redirect loop visible in the error log.
 * - Student, standard and vendor users continue to be redirected to
 *   their existing role dashboards.
 * - performLogout() remains the single, complete session-destruction
 *   path for the ?logout= query parameter.
 * - All other landing-page behaviour (API fallback, stats, featured
 *   vendors, accessibility) is retained.
 *
 * SOURCE: Technical Audit Report + ERROR LOG (admin redirect loop).
 * SOURCE: Clean Code, Robert C. Martin, Chapters 2–4.
 *
 * @version 17.0
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
 *
 * Clears every session variable, expires the session cookie,
 * destroys the session, starts a fresh guest session and
 * regenerates the session identifier.
 *
 * @return void
 */
if (!function_exists('performLogout'))
{
    function performLogout()
    {
        $_SESSION = array();

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

        if (session_status() === PHP_SESSION_ACTIVE)
        {
            session_destroy();
        }

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
    exit();
}

// =============================================================================
// Start Session
// =============================================================================

if (session_status() !== PHP_SESSION_ACTIVE)
{
    session_start();
}

// =============================================================================
// Redirect Authenticated Non-Admin Users
// =============================================================================
//
// Admin users stay on the landing page (the admin dashboard file does
// not exist). Student, standard and vendor users are sent to their
// role-specific dashboards. This eliminates the infinite redirect
// loop that previously occurred for the admin role.

if (isset($_SESSION['user_id'])
    && !empty($_SESSION['user_id'])
    && (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true
        || isset($_SESSION['account_type'])))
{
    $accountType = isset($_SESSION['account_type'])
        ? $_SESSION['account_type']
        : '';

    writeLog(
        'User already logged in. Role: ' . $accountType,
        'AUTH'
    );

    if ($accountType === 'admin')
    {
        // Admin stays on the landing page – no redirect.
        // The modules/admin/dashboard.php path does not exist.
        writeLog(
            'Admin user remains on landing page (no admin dashboard file)',
            'AUTH'
        );
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
// CSRF Token
// =============================================================================

if (empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// =============================================================================
// Fetch Data from the API Service
// =============================================================================

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
// Escape Helpers
// =============================================================================

if (!function_exists('escapeOutput'))
{
    /**
     * Escapes a value for safe HTML output.
     *
     * @param mixed $value
     * @return string
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
     * @param mixed $value
     * @return string
     */
    function campusEatsIndexEscape($value)
    {
        return escapeOutput($value);
    }
}

// =============================================================================
// Compute Stats
// =============================================================================

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
        <!-- Hero -->
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

        <!-- Stats -->
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

        <!-- How it works -->
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
                        <p>Pick items from any campus vendor and confirm your order.</p>
                    </div>
                    <div class="step">
                        <div class="step-number" aria-hidden="true">2</div>
                        <h3>Vendor prepares</h3>
                        <p>Track status as it moves from Pending to Preparing to Completed.</p>
                    </div>
                    <div class="step">
                        <div class="step-number" aria-hidden="true">3</div>
                        <h3>Pick it up</h3>
                        <p>Walk over to the vendor stall and grab your bag. Done.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured vendors -->
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

        <!-- Features -->
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