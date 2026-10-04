<?php
/**
 * Campus Eats - Landing Page (Entry Point)
 *
 * Serves as the landing page for unauthenticated users. Displays real
 * API data from the Fake Restaurant API when the API is reachable.
 * Displays the bundled fallback dataset when the API is unreachable.
 *
 * CORRECTIONS (Version 15.0 - Index Page Polish):
 *
 * - Fix 1 (shared card styling). The featured-vendor section now uses
 *   the shared `.vendors`, `.featured-vendor`, `.featured-vendor-header`,
 *   and `.featured-vendor-body` classes. The inline styles that were
 *   present in the previous version are removed. The section renders
 *   with the same card treatment as the rest of the public pages.
 *
 * - Fix 2 (shared menu preview styling). The menu preview now uses the
 *   shared `.menu-preview` and `.menu-preview-item` classes. The
 *   inline styles that were present in the previous version are
 *   removed.
 *
 * - Fix 3 (fallback dataset). The page relies on the API service to
 *   supply the fallback dataset when the live API is unreachable and
 *   no stale response exists. The error block is only rendered when
 *   every data source fails.
 *
 * - Fix 4 (empty state). The empty state for the vendors section is
 *   refined to use the shared `.empty-state` treatment. The state is
 *   shown when the restaurant list is empty after the fallback is
 *   applied.
 *
 * - Fix 5 (semantics and accessibility). The hero buttons use the
 *   shared `.btn`, `.btn-primary`, and `.btn-outline` classes. The
 *   featured-vendor section uses a semantic `<section>` element with
 *   an `aria-labelledby` attribute. The menu preview uses a
 *   definition list to associate each item name with its price.
 *
 * - Fix 6 (deterministic stats). The stats section shows the number
 *   of restaurants, the total number of menu items, and a static
 *   average pickup time. The counts are computed from the data that
 *   the page actually has. When the data is the fallback, the counts
 *   reflect the fallback.
 *
 * - Preserved all existing functionality: the logout query parameter
 *   handling, the authenticated-user redirect, the CSRF token
 *   generation, the shared header and footer includes, and the
 *   `public.css` stylesheet.
 *
 * SOURCE: Index page polish request.
 * SOURCE: API Documentation - Fake Restaurant API.
 *
 * @version 15.0
 */

// Load required dependencies
require_once 'solution/config/constants.php';
require_once 'solution/includes/auth.php';
require_once 'solution/includes/api_service.php';

// Set security headers for this public page
setSecurityHeaders();

// =============================================================================
// Check for Logout Parameter (Prevents Auto-Redirect Loop)
// =============================================================================
//
// A user who has just logged out is redirected to this page with a
// logout query parameter. The session is destroyed and the user is
// redirected to a clean URL. Without this step, the auto-redirect
// below would send the user back to the dashboard because the session
// would still be active at the moment of the check.

if (isset($_GET['logout']))
{
    writeLog("Logout parameter detected in index.php", "AUTH");

    if (session_status() === PHP_SESSION_ACTIVE)
    {
        destroySession();
        $_SESSION = array();
        writeLog("Session destroyed due to logout parameter", "AUTH");
    }

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
    && isset($_SESSION['logged_in'])
    && $_SESSION['logged_in'] === true)
{
    $accountType = isset($_SESSION['account_type'])
        ? $_SESSION['account_type']
        : '';

    writeLog(
        "User already logged in, redirecting to dashboard. "
            . "Role: $accountType",
        "AUTH"
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
        writeLog("Invalid session data detected, clearing session", "AUTH");
        destroySession();
        $_SESSION = array();
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
        "Fetched " . count($restaurants) . " restaurants from API",
        "API"
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
        "Error fetching restaurants from API: " . $e->getMessage(),
        "API_ERROR"
    );
    $error = "Unable to load restaurant data. Please try again later.";
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
     * Local alias for the escape helper. The canonical helper is
     * escapeOutput() in includes/auth.php. The local alias is provided
     * so the page reads consistently.
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
                    <a href="#how-it-works" class="btn btn-outline">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        Learn more
                    </a>
                </div>
            </div>
        </section>

        <!-- =====================================================================
             Stats Section
             ===================================================================== -->
        <section class="stats-section"
                 aria-labelledby="stats-heading">
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
                        <div class="stat-number">&lt;5 min</div>
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
             Features Section
             ===================================================================== -->
        <section class="features"
                 aria-labelledby="features-heading">
            <div class="container">
                <div class="section-title">
                    <h2 id="features-heading">Everything the system manages</h2>
                    <p>Four core modules, as defined in the process specification.</p>
                </div>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-users" aria-hidden="true"></i>
                        </div>
                        <h3>User Management</h3>
                        <p>
                            Register and sign in as Student, Standard,
                            Vendor, or Administrator.
                        </p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-store" aria-hidden="true"></i>
                        </div>
                        <h3>Vendor Management</h3>
                        <p>
                            Onboard campus vendors with location and
                            contact details.
                        </p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-utensils" aria-hidden="true"></i>
                        </div>
                        <h3>Menu Management</h3>
                        <p>
                            Add, update, and remove menu items per vendor.
                        </p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                        </div>
                        <h3>Order Management</h3>
                        <p>
                            Place orders and track Pending to Preparing
                            to Completed.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- =====================================================================
             Featured Vendor Section
             ===================================================================== -->
        <section id="vendors"
                 class="vendors"
                 aria-labelledby="vendors-heading">
            <div class="container">
                <div class="section-title">
                    <h2 id="vendors-heading">Featured Vendor</h2>
                    <p>
                        Discover a campus vendor. Sign up to see all
                        available options.
                    </p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error" role="alert">
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                        <div class="alert-content">
                            <div class="alert-title">Service Temporarily Unavailable</div>
                            <div class="alert-message">
                                <?php echo campusEatsIndexEscape($error); ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($featuredRestaurant !== null): ?>
                    <article class="featured-vendor">
                        <header class="featured-vendor-header">
                            <h3>
                                <i class="fas fa-store" aria-hidden="true"></i>
                                <?php
                                echo campusEatsIndexEscape(
                                    $featuredRestaurant['restaurantName']
                                );
                                ?>
                            </h3>
                        </header>
                        <div class="featured-vendor-body">
                            <p class="featured-vendor-meta">
                                <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                                <?php
                                echo campusEatsIndexEscape(
                                    $featuredRestaurant['address']
                                        ?? 'Campus Location'
                                );
                                ?>
                            </p>
                            <p class="featured-vendor-meta">
                                <i class="fas fa-tag" aria-hidden="true"></i>
                                <?php
                                echo campusEatsIndexEscape(
                                    $featuredRestaurant['type']
                                        ?? 'Various Cuisines'
                                );
                                ?>
                            </p>

                            <?php if (!empty($featuredRestaurant['menu'])): ?>
                                <h4 class="menu-preview-title">Popular Items</h4>
                                <dl class="menu-preview">
                                    <?php foreach ($featuredRestaurant['menu'] as $item): ?>
                                        <div class="menu-preview-item">
                                            <dt class="item-name">
                                                <?php
                                                echo campusEatsIndexEscape(
                                                    $item['itemName']
                                                );
                                                ?>
                                            </dt>
                                            <dd class="item-price">
                                                R <?php
                                                echo number_format(
                                                    (float)$item['itemPrice'],
                                                    2
                                                );
                                                ?>
                                            </dd>
                                        </div>
                                    <?php endforeach; ?>
                                </dl>
                            <?php endif; ?>

                            <div class="featured-vendor-cta">
                                <p class="featured-vendor-note">
                                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                                    Sign up or log in to view all vendors and
                                    place orders.
                                </p>
                                <div class="featured-vendor-actions">
                                    <a href="solution/modules/auth/register.php"
                                       class="btn btn-primary">
                                        <i class="fas fa-user-plus" aria-hidden="true"></i>
                                        Sign Up to Order
                                    </a>
                                    <a href="solution/modules/auth/login.php"
                                       class="btn btn-outline">
                                        <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                                        Log In
                                    </a>
                                </div>
                            </div>
                        </div>
                    </article>

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

        <!-- =====================================================================
             CTA Section
             ===================================================================== -->
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