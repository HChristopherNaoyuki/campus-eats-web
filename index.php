<?php
/**
 * Campus Eats - Landing Page (Entry Point)
 *
 * CORRECTIONS (Version 17.0):
 * - Caches each restaurant menu in $menuByRestaurantId so featured
 *   preview and stats share one fetch per restaurant (log showed
 *   restaurant 1/menu requested twice).
 * - Renders full vendor grid from $restaurants; empty state only when
 *   the list is empty.
 * - Menu fallback remains in ApiService / fallback_data.php.
 *
 * @version 17.0
 */

require_once 'solution/config/constants.php';
require_once 'solution/includes/auth.php';
require_once 'solution/includes/api_service.php';

setSecurityHeaders();

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
    }

    header('Location: ' . ROOT_URL . '/index.php');
    exit();
}

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

    if (function_exists('destroySession'))
    {
        destroySession();
    }

    $_SESSION = array();
    header('Location: ' . ROOT_URL . '/index.php');
    exit();
}

if (empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];
$apiService = getApiService();
$restaurants = array();
$menuByRestaurantId = array();
$featuredRestaurant = null;
$error = '';

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

    // One menu fetch per restaurant; reused for featured + stats.
    foreach ($restaurants as $rest)
    {
        if (!isset($rest['restaurantID']))
        {
            continue;
        }

        $rid = (int)$rest['restaurantID'];

        try
        {
            $menu = $apiService->getRestaurantMenu($rid);
            $menuByRestaurantId[$rid] = is_array($menu) ? $menu : array();
        }
        catch (Exception $menuException)
        {
            $menuByRestaurantId[$rid] = array();
            writeLog(
                'Menu unavailable for restaurant ' . $rid . ': '
                    . $menuException->getMessage(),
                'API'
            );
        }

        if ($featuredRestaurant === null
            && !empty($menuByRestaurantId[$rid]))
        {
            $featuredRestaurant = $rest;
            $featuredRestaurant['menu'] = array_slice(
                $menuByRestaurantId[$rid],
                0,
                4
            );
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

if (!function_exists('escapeOutput'))
{
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
    function campusEatsIndexEscape($value)
    {
        return escapeOutput($value);
    }
}

$totalRestaurants = count($restaurants);
$totalItems = 0;

foreach ($menuByRestaurantId as $menuItems)
{
    $totalItems += count($menuItems);
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

        <section id="how-it-works" class="how-it-works"
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
                        <p>Track status from Pending to Preparing to Completed.</p>
                    </div>
                    <div class="step">
                        <div class="step-number" aria-hidden="true">3</div>
                        <h3>Pick it up</h3>
                        <p>Walk to the vendor stall and grab your bag.</p>
                    </div>
                </div>
            </div>
        </section>

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
                        <p>Students, standard users, vendors and administrators each have a dedicated workspace.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-store" aria-hidden="true"></i>
                        <h3>Vendor management</h3>
                        <p>Onboard campus vendors with location, menu control, and approvals.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-utensils" aria-hidden="true"></i>
                        <h3>Menu management</h3>
                        <p>Items, prices and descriptions stay in sync across clients.</p>
                    </div>
                    <div class="feature-card">
                        <i class="fas fa-clipboard-list" aria-hidden="true"></i>
                        <h3>Order management</h3>
                        <p>From placement through preparation to pickup, every status is tracked.</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="vendors" class="vendors" aria-labelledby="vendors-heading">
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
                                            <i class="fas fa-map-marker-alt" aria-hidden="true"></i>
                                            <?php echo campusEatsIndexEscape($restaurant['address']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($restaurant['type'])): ?>
                                        <p class="vendor-type">
                                            <?php echo campusEatsIndexEscape($restaurant['type']); ?>
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
                                                    ? number_format((float)$item['itemPrice'], 2)
                                                    : '0.00'
                                            );
                                        ?></dd>
                                    </div>
                                <?php endforeach; ?>
                            </dl>
                        </div>
                    <?php endif; ?>

                    <p class="vendors-cta">
                        <a href="solution/modules/auth/register.php" class="btn btn-primary">
                            <i class="fas fa-user-plus" aria-hidden="true"></i>
                            Sign up or log in to place an order
                        </a>
                    </p>
                <?php else: ?>
                    <div class="empty-state" role="status">
                        <i class="fas fa-store-slash" aria-hidden="true"></i>
                        <h3>No Vendors Available</h3>
                        <p>No vendors are currently available. Please check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="cta vendor-cta" aria-labelledby="vendor-cta-heading">
            <div class="container">
                <h2 id="vendor-cta-heading">Run a stall on campus?</h2>
                <p>
                    List your menu, take pickup orders, and fulfill them
                    with a simple status workflow.
                </p>
                <a href="solution/modules/auth/register.php" class="btn btn-secondary">
                    <i class="fas fa-store" aria-hidden="true"></i>
                    Become a vendor
                </a>
            </div>
        </section>
    </main>

    <?php include_once 'solution/includes/footer.php'; ?>
</body>
</html>