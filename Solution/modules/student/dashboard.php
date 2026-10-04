<?php
/**
 * Student and Standard Dashboard Page
 *
 * This page displays available vendors for students and standard
 * users. The vendor list is fetched from the Fake Restaurant API. When
 * the API is unreachable, the bundled fallback dataset is used so the
 * page remains functional.
 *
 * CORRECTIONS (Version 19.0 - REPORT.txt Alignment):
 *
 * - Fix 1 (degraded mode). When the database is unreachable, the page
 *   renders in degraded mode. The API fallback supplies the vendor
 *   list. The page shows a toast that the database is temporarily
 *   unavailable.
 *
 * - Fix 2 (fallback data). The vendor list is loaded through the API *   service. The service returns the bundled fallback dataset when the
 *   live API is unreachable and no stale response exists.
 *
 * - Fix 3 (toast module). The dashboard header loads the toast module
 *   so the degraded-mode message can be shown.
 *
 * - Retained the vendor card layout, the search bar, the cart badge
 *   update, and the menu preview.
 *
 * SOURCE: REPORT.txt, Database Fault Tolerance.
 * SOURCE: API Documentation - Fake Restaurant API.
 *
 * @version 19.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/api_service.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

// Start secure session and require student or standard role
startSecureSession();
requireStudentOrStandard();

$db = getDB();
$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();

// The database may be unreachable. The page renders in degraded mode
// in that case. The vendor list comes from the API fallback. The
// database-only features are disabled.
$databaseAvailable = $db->isAvailable();

if (!$databaseAvailable)
{
    writeLog(
        "Dashboard rendered in degraded mode: " . $db->getLastError(),
        "RESILIENCE"
    );
}

// =============================================================================
// Fetch Data from API
// =============================================================================

$apiService = getApiService();
$restaurants = array();
$restaurantsWithMenus = array();
$error = '';

try
{
    $restaurants = $apiService->getAllRestaurants();

    foreach ($restaurants as $restaurant)
    {
        try
        {
            $menu = $apiService->getRestaurantMenu(
                $restaurant['restaurantID']
            );

            if (!empty($menu))
            {
                $restaurantsWithMenus[] = array(
                    'restaurant' => $restaurant,
                    'menu' => array_slice($menu, 0, 4)
                );
            }
        }
        catch (Exception $e)
        {
            continue;
        }
    }
}
catch (Exception $e)
{
    writeLog(
        "Error fetching restaurants from API: " . $e->getMessage(),
        "API_ERROR"
    );
    $error = "Unable to load restaurants. Please try again later.";
}

$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;

function getBaseUrlForJs()
{
    return BASE_URL;
}

function getCsrfTokenForJs()
{
    return getCsrfToken();
}

function getCartCountForJs()
{
    return isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
}

function escapeDashboardOutput($string)
{
    if ($string === null)
    {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo ucfirst(getCurrentUserRole()); ?> Dashboard · Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/student.css">
    <script src="<?php echo ASSETS_URL; ?>/js/toast.js" defer></script>
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/student_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="student-content">
                <div class="container">
                    <div class="welcome-header">
                        <h1>Hey <?php echo htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-hand-peace"></i></h1>
                        <p>What are you eating today? Browse our campus vendors and order ahead.</p>
                        <?php if (isStudent()): ?>
                            <p class="text-small" style="opacity: 0.8; margin-top: var(--space-3);">
                                <i class="fas fa-graduation-cap"></i> You are eligible for a 2.5% student discount on all orders!
                            </p>
                        <?php endif; ?>
                    </div>

                    <?php if (!$databaseAvailable): ?>
                        <div class="alert alert-warning" role="status">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Limited Mode</div>
                                <div class="alert-message">
                                    The application database is temporarily
                                    unavailable. You can browse vendors, but
                                    ordering and order history are not
                                    available right now.
                                </div>
                            </div>
                        </div>
                        <script>
                            document.addEventListener('DOMContentLoaded', function()
                            {
                                if (typeof window.showToast === 'function')
                                {
                                    window.showToast(
                                        'Database is temporarily unavailable. Browsing only.',
                                        'warning'
                                    );
                                }
                            });
                        </script>
                    <?php endif; ?>

                    <div class="search-wrapper">
                        <div class="input-wrapper">
                            <i class="fas fa-search input-icon"></i>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search menu items...">
                        </div>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                            </div>
                        </div>
                    <?php elseif (empty($restaurantsWithMenus)): ?>
                        <div class="empty-state">
                            <i class="fas fa-store-slash"></i>
                            <h3>No Vendors Available</h3>
                            <p>Please check back later for food options.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($restaurantsWithMenus as $vendorData): ?>
                            <?php
                            $vendor = $vendorData['restaurant'];
                            $menuItems = $vendorData['menu'];
                            $isOpen = true;
                            ?>
                            <div class="vendor-section" data-vendor-name="<?php echo strtolower($vendor['restaurantName']); ?>">
                                <div class="vendor-section-header">
                                    <div>
                                        <h2><?php echo escapeDashboardOutput($vendor['restaurantName']); ?></h2>
                                        <p class="vendor-location">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo escapeDashboardOutput($vendor['address'] ?? 'Campus Location'); ?>
                                        </p>
                                    </div>
                                    <span class="badge badge-open">
                                        <i class="fas fa-clock"></i>
                                        Open Now
                                    </span>
                                </div>

                                <div class="menu-grid">
                                    <?php foreach ($menuItems as $item): ?>
                                        <div class="vendor-menu-item" data-item-name="<?php echo strtolower($item['itemName']); ?>">
                                            <div class="menu-item-info">
                                                <h4><?php echo escapeDashboardOutput($item['itemName']); ?></h4>
                                                <p class="menu-item-description"><?php echo escapeDashboardOutput(substr($item['itemDescription'] ?? '', 0, 60)); ?></p>
                                                <p class="menu-item-price">R <?php echo number_format($item['itemPrice'], 2); ?></p>
                                            </div>
                                            <?php if ($databaseAvailable && $isOpen): ?>
                                                <button class="btn btn-primary btn-sm add-to-cart-btn"
                                                        data-item-id="<?php echo $item['itemID']; ?>"
                                                        data-item-name="<?php echo escapeDashboardOutput($item['itemName']); ?>"
                                                        data-item-price="<?php echo $item['itemPrice']; ?>"
                                                        data-vendor-id="<?php echo $vendor['restaurantID']; ?>"
                                                        data-vendor-name="<?php echo escapeDashboardOutput($vendor['restaurantName']); ?>"
                                                        data-max-quantity="999">
                                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm" disabled>
                                                    <i class="fas fa-ban"></i>
                                                    <?php echo $databaseAvailable ? 'Unavailable' : 'Limited Mode'; ?>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="view-full-menu">
                                    <a href="menu_browse.php?vendor_id=<?php echo $vendor['restaurantID']; ?>" class="btn btn-outline btn-sm">
                                        View Full Menu <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <button class="sidebar-toggle" id="menuToggleBtn" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <div class="toast-container" id="toastContainer"></div>

    <script>
        window.INITIAL_CART_COUNT = <?php echo json_encode($cartCount); ?>;
        window.BASE_URL = <?php echo json_encode(getBaseUrlForJs()); ?>;
        window.CSRF_TOKEN = <?php echo json_encode(getCsrfTokenForJs()); ?>;
    </script>

    <script src="<?php echo ASSETS_URL; ?>/js/cart.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dashboard-common.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/student.js"></script>
</body>
</html>