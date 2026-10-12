<?php
/**
 * Campus Eats - Landing Page (Entry Point)
 *
 * Serves the public home page for unauthenticated users. Displays
 * restaurant data from the Fake Restaurant API when reachable, or the
 * bundled fallback catalogue when the API is unreachable.
 *
 * CORRECTIONS (Version 16.0 - Complete vendor list display):
 *
 * - Root cause: the vendors section rendered only $featuredRestaurant,
 *   which is set only when a per-restaurant menu fetch succeeds. Under
 *   SSL failure-pause, getRestaurantMenu() fails, $featuredRestaurant
 *   stayed null, and the page showed "No Vendors Available" even though
 *   getAllRestaurants() had returned three fallback records.
 * - Fix: render a grid of every restaurant in $restaurants. Optionally
 *   show a menu preview for the first restaurant that has a menu.
 * - Stats use the restaurant count always. Menu-item totals are summed
 *   only when menu fetches succeed; failed menu calls do not hide vendors.
 * - Empty state appears only when $restaurants is empty after all sources.
 * - Authenticated role redirects and logout handling are retained.
 *
 * SOURCE: Home page missing-information report and error log.
 * SOURCE: Campus Eats process document v2.4 (landing content).
 * SOURCE: Clean Code, Robert C. Martin.
 *
 * @version 16.0
 */

require_once 'solution/config/constants.php';
require_once 'solution/includes/auth.php';
require_once 'solution/includes/api_service.php';

setSecurityHeaders();

// =============================================================================
// Logout query parameter
// =============================================================================

if (isset($_GET['logout']))
{
    writeLog('Logout parameter detected in index.php', 'AUTH');

    if (session_status() === PHP_SESSION_ACTIVE)
    {
        if (function_exists('destroySession'))
        {
            destroySession();
        }
        else
        {
            $_SESSION = array();
            session_destroy();
        }

        writeLog('Session destroyed due to logout parameter', 'AUTH');
    }

    header('Location: ' . ROOT_URL . '/index.php');
    exit();
}

// =============================================================================
// Session
// =============================================================================

if (session_status() !== PHP_SESSION_ACTIVE)
{
    if (function_exists('startSecureSession'))
    {
        startSecureSession();
    }
    else
    {
        session_start();
    }
}

// =============================================================================
// Redirect authenticated users to their role dashboard
// =============================================================================

if (isset($_SESSION['user_id'])
    && isset($_SESSION['logged_in'])
    && $_SESSION['logged_in'] === true)
{
    $accountType = isset($_SESSION['account_type'])
        ? $_SESSION['account_type']
        : (isset($_SESSION['role']) ? $_SESSION['role'] : '');

    writeLog(
        'User already logged in, redirecting to dashboard. Role: '
            . $accountType,
        'AUTH'
    );

    if ($accountType === 'admin')
    {
        header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
        exit();
    }

    if ($accountType === 'vendor')
    {
        header('Location: ' . BASE_URL . '/modules/vendor/dashboard.php');
        exit();
    }

    if ($accountType === 'student' || $accountType === 'standard')
    {
        header('Location: ' . BASE_URL . '/modules/student/dashboard.php');
        exit();
    }

    writeLog('Invalid session data detected, clearing session', 'AUTH');

    if (function_exists('destroySession'))
    {
        destroySession();
    }

    $_SESSION = array();
    header('Location: ' . ROOT_URL . '/index.php');
    exit();
}

// =============================================================================
// CSRF token
// =============================================================================

if (empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

// =============================================================================
// Fetch restaurant catalogue (live or fallback via ApiService)
// =============================================================================

$apiService = getApiService();
$restaurants = array();
$featuredRestaurant = null;
$error = '';
$dataSource = 'unknown';

try
{
    $restaurants = $apiService->getAllRestaurants();

    if (!is_array($restaurants))
    {
        $restaurants = array();
    }

    writeLog(
        'Home page loaded ' . count($restaurants) . ' restaurants',
        'API'
    );

    // Attempt a menu preview for the first restaurant that has items.
    // Failures are non-fatal: the vendor grid still renders.
    foreach ($restaurants as $rest)
    {
        if (!isset($rest['restaurantID']))
        {
            continue;
        }

        try
        {
            $menu = $apiService->getRestaurantMenu($rest['restaurantID']);

            if (is_array($menu) && !empty($menu))
            {
                $featuredRestaurant = $rest;
                $featuredRestaurant['menu'] = array_slice($menu, 0, 4);
                break;
            }
        }
        catch (Exception $menuException)
        {
            writeLog(
                'Menu preview unavailable for restaurant '
                    . $rest['restaurantID'] . ': '
                    . $menuException->getMessage(),
                'API'
            );
            continue;
        }
    }
}
catch (Exception $e)
{
    writeLog(
        'Error fetching restaurants for home page: ' . $e->getMessage(),
        'API_ERROR'
    );
    $error = 'Unable to load restaurant data. Please try again later.';
    $restaurants = array();
}

// =============================================================================
// Escape helpers
// =============================================================================

if (!function_exists('escapeOutput'))
{
    /**
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
     * @param mixed $value
     * @return string
     */
    function campusEatsIndexEscape($value)
    {
        return escapeOutput($value);
    }
}

// =============================================================================
// Stats (restaurant count always; menu totals when menus are available)
// =============================================================================

$totalRestaurants = count($restaurants);
$totalItems = 0;

if ($totalRestaurants > 0)
{
    foreach ($restaurants as $rest)
    {
        if (!isset($rest['restaurantID']))
        {
            continue;
        }

        try
        {
            $menu = $apiService->getRestaurantMenu($rest['restaurantID']);

            if (is_array($menu))
            {
                $totalItems += count($menu);
            }
        }
        catch (Exception $e)
        {
            // Menu unavailable under outage; do not inflate or hide stats.
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
        <section class="stats-section" aria-labelledby="stats-heading">
            <h2 id="stats-heading" class="sr-only">Campus Eats at a glance</h2>
            <div class="container">
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php echo number_format($totalRestaurants); ?>
                        </div>
                        <div class="stat-label">Campus Vendors</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php echo number_format($totalItems); ?>
                        </div>
                        <div class="stat-label">Menu Items</div>
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

        <!-- Features -->
        <section class="features" aria-labelledby="features-heading">
            <div class="container">
                <div class="section-title">
                    <h2 id="features-heading">Everything the system manages</h2>
                    <p>Four core modules, as defined in the process specification.</p>
                </div>
                <div class="features-grid">
                    <div class="feature-card">
                        <i class="fas fa-users" aria-hidden="true"></i>
                        <h3>User management</h3>
                        <p>
                            Students, standard users, vendors and administrators
                            each have a dedicated workspace.
                        </p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-store" aria-hidden="true"></i>
                        <h3>Vendor management</h3>
                        <p>
                            Onboard campus vendors with location and
                            menu control, open or closed status, and approvals.
                        </p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-utensils" aria-hidden="true"></i>
                        <h3>Menu management</h3>
                        <p>
                            Items, prices and descriptions stay in sync across
                            web and mobile clients.
                        </p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                        <h3>Order management</h3>
                        <p>
                            From placement through preparation to pickup,
                            every status is tracked.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Vendors: full catalogue -->
        <section id="vendors"
                 class="vendors"
                 aria-labelledby="vendors-heading">
            <div class="container">
                <div class="section-title">
                    <h2 id="vendors-heading">Featured vendors</h2>
                    <p>Order ahead from these campus stalls.</p>
                </div>

                <?php if ($error !== '' && empty($restaurants)): ?>
                    <div class="alert alert-error" role="alert">
                        <?php echo campusEatsIndexEscape($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($restaurants)): ?>
                    <div class="vendor-grid">
                        <?php foreach ($restaurants as $restaurant): ?>
                            <article class="featured-vendor">
                                <div class="featured-vendor-header">
                                    <h3><?php
                                        echo campusEatsIndexEscape(
                                            isset($restaurant['restaurantName'])
                                                ? $restaurant['restaurantName']
                                                : 'Vendor'
                                        );
                                    ?></h3>
                                </div>
                                <div class="featured-vendor-body">
                                    <?php if (!empty($restaurant['address'])): ?>
                                        <p>
                                            <i class="fas fa-map-marker-alt"
                                               aria-hidden="true"></i>
                                            <?php
                                                echo campusEatsIndexEscape(
                                                    $restaurant['address']
                                                );
                                            ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (!empty($restaurant['type'])): ?>
                                        <p class="vendor-type">
                                            <?php
                                                echo campusEatsIndexEscape(
                                                    $restaurant['type']
                                                );
                                            ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (isset($restaurant['parkingLot'])): ?>
                                        <p class="vendor-parking">
                                            <?php
                                                echo !empty($restaurant['parkingLot'])
                                                    ? 'Parking available'
                                                    : 'No parking lot listed';
                                            ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($featuredRestaurant !== null
                        && !empty($featuredRestaurant['menu'])): ?>
                        <div class="menu-preview-block">
                            <h3 class="menu-preview-title">
                                Sample menu –
                                <?php
                                    echo campusEatsIndexEscape(
                                        isset($featuredRestaurant['restaurantName'])
                                            ? $featuredRestaurant['restaurantName']
                                            : ''
                                    );
                                ?>
                            </h3>
                            <dl class="menu-preview">
                                <?php foreach ($featuredRestaurant['menu'] as $item): ?>
                                    <div class="menu-preview-item">
                                        <dt><?php
                                            echo campusEatsIndexEscape(
                                                isset($item['itemName'])
                                                    ? $item['itemName']
                                                    : ''
                                            );
                                        ?></dt>
                                        <dd>R <?php
                                            echo campusEatsIndexEscape(
                                                isset($item['itemPrice'])
                                                    ? number_format(
                                                        (float)$item['itemPrice'],
                                                        2
                                                    )
                                                    : '0.00'
                                            );
                                        ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        </div>
                    <?php endif; ?>

                    <p class="vendors-cta">
                        <a href="solution/modules/auth/register.php"
                           class="btn btn-primary">
                            <i class="fas fa-user-plus" aria-hidden="true"></i>
                            Sign up or log in to place an order
                        </a>
                    </p>

                <?php else: ?>
                    <div class="empty-state" role="status">
                        <i class="fas fa-store-slash" aria-hidden="true"></i>
                        <h3>No Vendors Available</h3>
                        <p>
                            No vendors are currently available. Please check
                            back later.
                        </p>
                        <a href="solution/modules/auth/register.php"
                           class="btn btn-primary">
                            <i class="fas fa-user-plus" aria-hidden="true"></i>
                            Sign Up
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Vendor CTA -->
        <section class="cta vendor-cta"
                 aria-labelledby="vendor-cta-heading">
            <div class="container">
                <h2 id="vendor-cta-heading">Run a stall on campus?</h2>
                <p>
                    List your menu, take pickup orders, and fulfill them
                    with a simple status workflow. Reports for sales, vendor
                    performance, and user activity included.
                </p>
                <ul>
                    <li>
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        Per-vendor menu CRUD
                    </li>
                    <li>
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        Live order queue
                    </li>
                    <li>
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        Sales and performance reports
                    </li>
                </ul>
                <a href="solution/modules/auth/register.php"
                   class="btn btn-secondary">
                    <i class="fas fa-store" aria-hidden="true"></i>
                    Become a vendor
                </a>
            </div>
        </section>
    </main>

    <?php include_once 'solution/includes/footer.php'; ?>
</body>
</html>