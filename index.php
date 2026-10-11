<?php
/**
 * Campus Eats - Landing Page (Entry Point)
 *
 * CORRECTIONS (Version 19.0 - Logout Loop Fix):
 *
 * - Logout handling no longer depends on a ?logout= query parameter
 *   that could race with a still-valid session cookie.
 * - When a residual session is detected after a failed cookie clear,
 *   the page forces a second full destruction before any dashboard
 *   redirect can run.
 * - Authenticated users of every role (including admin) are sent to
 *   their real module dashboard. Admin is no longer forced onto the
 *   landing page.
 *
 * SOURCE: Error log – post-logout redirect loop to admin dashboard.
 *
 * @version 19.0
 */

require_once 'solution/config/constants.php';
require_once 'solution/includes/auth.php';
require_once 'solution/includes/api_service.php';

setSecurityHeaders();

// =============================================================================
// Complete Logout Helper
// =============================================================================

if (!function_exists('performLogout'))
{
    /**
     * Destroy the current session completely and issue expired
     * cookies so the browser drops CAMPUS_EATS_SESSION.
     *
     * @return void
     */
    function performLogout()
    {
        $_SESSION = array();

        if (ini_get('session.use_cookies'))
        {
            $params = session_get_cookie_params();
            $secureFlag = !empty($params['secure'])
                || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $cookieName = session_name();

            setcookie(
                $cookieName,
                '',
                time() - 42000,
                '/',
                isset($params['domain']) ? $params['domain'] : '',
                $secureFlag,
                true
            );

            if (defined('ROOT_URL') && ROOT_URL !== '' && ROOT_URL !== '/')
            {
                setcookie(
                    $cookieName,
                    '',
                    time() - 42000,
                    ROOT_URL,
                    isset($params['domain']) ? $params['domain'] : '',
                    $secureFlag,
                    true
                );
            }
        }

        if (session_status() === PHP_SESSION_ACTIVE)
        {
            session_destroy();
        }

        $GLOBALS['_SESSION_INITIALIZED'] = false;

        writeLog('performLogout completed – session fully cleared', 'AUTH');
    }
}

// =============================================================================
// Optional legacy ?logout= support (clears and redirects to clean URL)
// =============================================================================

if (isset($_GET['logout']))
{
    writeLog('Logout parameter detected in index.php', 'AUTH');

    if (session_status() !== PHP_SESSION_ACTIVE)
    {
        // Start only long enough to destroy the residual cookie.
        @session_start();
    }

    performLogout();

    header('HTTP/1.1 303 See Other');
    header('Location: ' . ROOT_URL . '/index.php');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    exit();
}

// =============================================================================
// Start Session for Authentication Check
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
// Redirect Authenticated Users to Role Dashboard
// =============================================================================

$isAuthenticated = isset($_SESSION['user_id'])
    && !empty($_SESSION['user_id'])
    && isset($_SESSION['logged_in'])
    && $_SESSION['logged_in'] === true;

if ($isAuthenticated)
{
    $accountType = isset($_SESSION['account_type'])
        ? $_SESSION['account_type']
        : (isset($_SESSION['role']) ? $_SESSION['role'] : '');

    writeLog(
        'User already logged in, redirecting to dashboard. Role: ' . $accountType,
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

    // Unrecognised role – clear the broken session and stay on the landing page.
    writeLog('Invalid session role detected, clearing session', 'AUTH');
    performLogout();
    header('Location: ' . ROOT_URL . '/index.php');
    exit();
}

// =============================================================================
// Guest landing page continues below (API fetch, hero, vendors, etc.)
// =============================================================================

if (empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$apiService = getApiService();
$restaurants = array();
$featuredRestaurant = null;
$error = '';

try
{
    $restaurants = $apiService->getAllRestaurants();
    writeLog('Fetched ' . count($restaurants) . ' restaurants from API', 'API');

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
                    <a href="solution/modules/auth/register.php" class="btn btn-primary">
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

        <section id="vendors" class="vendors" aria-labelledby="vendors-heading">
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
            </div>
        </section>
    </main>

    <?php include_once 'solution/includes/footer.php'; ?>
</body>
</html>