<?php
/**
 * Student/Standard Dashboard Page - Matching Mockups 21-23.png
 *
 * This page displays available vendors for students and standard users.
 * Now fetches real data from the Fake Restaurant API.
 *
 * CORRECTIONS (Version 18.0 - Visual Parity):
 * - Updated layout to match mockups 21.png, 23.png
 * - Added welcome header with user greeting
 * - Added search functionality
 * - Added vendor cards with menu items
 * - Improved responsive behavior
 * - Removed inline styles and moved to student.css
 *
 * SOURCE: API Documentation - Fake Restaurant API
 * SOURCE: Mockups - 21.png, 22.png, 23.png, 24.png
 *
 * @version 18.0
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

writeLog("Student/Standard dashboard accessed by user ID: " . getCurrentUserId() . " (Role: " . getCurrentUserRole() . ")", "DASHBOARD");

// =============================================================================
// Fetch Data from API
// =============================================================================

$apiService = getApiService();
$restaurants = array();
$restaurantsWithMenus = array();
$error = '';

try
{
    // Fetch all restaurants from the API
    $restaurants = $apiService->getAllRestaurants();
    writeLog("Found " . count($restaurants) . " restaurants from API", "DASHBOARD");
    
    // For each restaurant, fetch its menu
    foreach ($restaurants as $restaurant)
    {
        try
        {
            $menu = $apiService->getRestaurantMenu($restaurant['restaurantID']);
            
            // Only include restaurants that have menu items
            if (!empty($menu))
            {
                $restaurantsWithMenus[] = array(
                    'restaurant' => $restaurant,
                    'menu' => array_slice($menu, 0, 4) // Show first 4 items
                );
            }
        }
        catch (Exception $e)
        {
            // Skip restaurants that don't have a menu
            continue;
        }
    }
    
    writeLog("Found " . count($restaurantsWithMenus) . " restaurants with menus", "DASHBOARD");
}
catch (Exception $e)
{
    writeLog("Error fetching restaurants from API: " . $e->getMessage(), "API_ERROR");
    $error = "Unable to load restaurants. Please try again later.";
}

$cartCount = isset($_SESSION['cart']) ? count($_SESSION['cart']) : 0;
writeLog("Cart count for user ID " . getCurrentUserId() . ": $cartCount items", "DASHBOARD");

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
    if ($string === null) return '';
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
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/student_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="student-content">
                <div class="container">
                    <!-- Welcome Header - Matching Mockup 21.png -->
                    <div class="welcome-header">
                        <h1>Hey <?php echo htmlspecialchars($currentUser['full_name'], ENT_QUOTES, 'UTF-8'); ?> <i class="fas fa-hand-peace"></i></h1>
                        <p>What are you eating today? Browse our campus vendors and order ahead.</p>
                        <?php if (isStudent()): ?>
                            <p class="text-small" style="opacity: 0.8; margin-top: var(--space-3);">
                                <i class="fas fa-graduation-cap"></i> You are eligible for a 2.5% student discount on all orders!
                            </p>
                        <?php endif; ?>
                    </div>

                    <!-- Search Bar - Matching Mockup 21.png -->
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
                            $isOpen = true; // API doesn't provide open status, assume open
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
                                            <?php if ($isOpen): ?>
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
                                                    <i class="fas fa-ban"></i> Unavailable
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