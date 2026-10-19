<?php
/**
 * Services Page
 *
 * Mirrors the TypeScript marketing/Services page: hero, six platform
 * modules, and a live/fallback vendor catalogue with menu counts.
 *
 * CORRECTIONS (Version 2.0 - Timeout fix):
 *
 * - Removed the loop that called getRestaurantMenu() for every
 *   restaurant. With 31 live venues that produced 20+ sequential
 *   HTTPS calls and hung the Services navigation the same way the
 *   old home page exceeded PHP's 120 s limit.
 * - Loads the vendor list once via getAllRestaurants().
 * - Derives per-vendor menu counts from a single getAllItems() call
 *   when available, grouping by restaurantID. No per-restaurant
 *   menu HTTP requests on this page.
 *
 * SOURCE: Technical audit 19 October 2026 – Services sequential menu
 *         loop after home/admin were already capped.
 * SOURCE: Clean Code – do not do unnecessary work in a request.
 *
 * @version 2.0
 */

require_once 'solution/config/constants.php';
require_once 'solution/includes/auth.php';
require_once 'solution/includes/api_service.php';

setSecurityHeaders();

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

$apiService = getApiService();
$restaurants = array();
$menuCounts = array();

try
{
    $restaurants = $apiService->getAllRestaurants();

    if (!is_array($restaurants))
    {
        $restaurants = array();
    }

    // Single items catalogue call – group counts by restaurant id.
    // Do not call getRestaurantMenu() in a loop on this page.
    if (method_exists($apiService, 'getAllItems'))
    {
        try
        {
            $allItems = $apiService->getAllItems();

            if (is_array($allItems))
            {
                foreach ($allItems as $item)
                {
                    $rid = 0;

                    if (isset($item['restaurantID']))
                    {
                        $rid = (int)$item['restaurantID'];
                    }
                    elseif (isset($item['restaurantId']))
                    {
                        $rid = (int)$item['restaurantId'];
                    }

                    if ($rid <= 0)
                    {
                        continue;
                    }

                    if (!isset($menuCounts[$rid]))
                    {
                        $menuCounts[$rid] = 0;
                    }

                    $menuCounts[$rid]++;
                }
            }
        }
        catch (Exception $itemsException)
        {
            writeLog(
                'Services page getAllItems skipped: '
                    . $itemsException->getMessage(),
                'API'
            );
        }
    }

    writeLog(
        'Services page loaded '
            . count($restaurants)
            . ' restaurants, menu count keys='
            . count($menuCounts),
        'API'
    );
}
catch (Exception $e)
{
    writeLog(
        'Services page restaurant load failed: ' . $e->getMessage(),
        'API_ERROR'
    );
    $restaurants = array();
}

if (!function_exists('escapeOutput'))
{
    /**
     * @param mixed $value
     * @return string
     */
    function escapeOutput($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$modules = array(
    array(
        'icon'  => 'fa-users',
        'title' => 'User management',
        'desc'  => 'Register and sign in as Student, Standard user, Vendor, or Admin, each with their own dashboard.'
    ),
    array(
        'icon'  => 'fa-store',
        'title' => 'Vendor management',
        'desc'  => 'Onboard campus vendors with a unique shop name, location, and contact details.'
    ),
    array(
        'icon'  => 'fa-utensils',
        'title' => 'Menu management',
        'desc'  => 'Vendors create, read, update, and delete their own menu items and stock levels.'
    ),
    array(
        'icon'  => 'fa-clipboard-list',
        'title' => 'Order management',
        'desc'  => 'Pending to Accepted to Preparing to Ready to Completed, tracked live for both sides.'
    ),
    array(
        'icon'  => 'fa-chart-bar',
        'title' => 'Reporting',
        'desc'  => 'Sales, order summary, vendor performance, detailed order, and user activity reports.'
    ),
    array(
        'icon'  => 'fa-comments',
        'title' => 'Feedback',
        'desc'  => 'Students log compliments and complaints; admins review them alongside the security log.'
    )
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
</head>
<body>
    <?php include_once 'solution/includes/public_header.php'; ?>

    <main id="main-content">
        <section class="page-hero services-hero">
            <div class="container">
                <p class="eyebrow">Services</p>
                <h1>What Campus Eats does</h1>
                <p>
                    One platform covering the full campus pickup workflow
                    for students, for vendors, and for the administrators
                    who keep it running.
                </p>
            </div>
        </section>

        <section class="features services-modules">
            <div class="container">
                <h2>Platform modules</h2>
                <div class="features-grid">
                    <?php foreach ($modules as $module): ?>
                        <div class="feature-card">
                            <i class="fas <?php echo escapeOutput($module['icon']); ?>"
                               aria-hidden="true"></i>
                            <h3><?php echo escapeOutput($module['title']); ?></h3>
                            <p><?php echo escapeOutput($module['desc']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <section class="vendors services-vendors">
            <div class="container">
                <div class="section-title">
                    <h2>Campus vendors</h2>
                    <p>Every vendor runs their own shop with their own inventory.</p>
                </div>

                <?php if (!empty($restaurants)): ?>
                    <div class="vendor-grid">
                        <?php foreach ($restaurants as $restaurant): ?>
                            <?php
                                $rid = isset($restaurant['restaurantID'])
                                    ? (int)$restaurant['restaurantID']
                                    : 0;
                                $count = isset($menuCounts[$rid])
                                    ? $menuCounts[$rid]
                                    : 0;
                            ?>
                            <article class="featured-vendor">
                                <div class="featured-vendor-header">
                                    <h3><?php
                                        echo escapeOutput(
                                            isset($restaurant['restaurantName'])
                                                ? $restaurant['restaurantName']
                                                : 'Vendor'
                                        );
                                    ?></h3>
                                </div>
                                <div class="featured-vendor-body">
                                    <?php if (!empty($restaurant['address'])): ?>
                                        <p class="text-muted">
                                            <?php echo escapeOutput($restaurant['address']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p>
                                        <?php
                                            echo (int)$count;
                                            echo $count === 1
                                                ? ' item on the menu'
                                                : ' items on the menu';
                                        ?>
                                    </p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <p class="vendors-cta">
                        <a href="solution/modules/auth/register.php"
                           class="btn btn-primary">
                            Browse and order
                        </a>
                    </p>
                <?php else: ?>
                    <div class="empty-state" role="status">
                        <p>Vendor list is temporarily unavailable.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include_once 'solution/includes/footer.php'; ?>
</body>
</html>