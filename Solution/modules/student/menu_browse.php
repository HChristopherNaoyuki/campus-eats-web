<?php
/**
 * Menu Browse Page for Students and Standard Users
 *
 * This page displays menu items for a selected vendor.
 * Now fetches real data from the Fake Restaurant API.
 *
 * CORRECTIONS (Version 11.0 - Standard User Access Fix):
 * - Replaced requireStudent() with requireStudentOrStandard() (HIGH-01)
 * - Standard users can now browse vendor menus
 * - Fixes FUNC-01 from the scope note
 *
 * SOURCE: API Documentation - Fake Restaurant API
 * SOURCE: campus-eats-process-document.pdf (Section 6.1)
 * SOURCE: Mockups - 21.png, 23.png
 * SOURCE: Scope Note - FUNC-01
 *
 * @version 11.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/api_service.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

// Start secure session
startSecureSession();

// =============================================================================
// CORRECTION: HIGH-01 - Allow Standard users to browse menus
// Previous code called requireStudent() which blocked Standard users.
// Standard users now have full access to menu browsing.
// Source: Scope Note - FUNC-01
// =============================================================================
requireStudentOrStandard();

// Get database connection, current user, and CSRF token
$db = getDB();
$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();

// Get vendor ID from query parameter
$vendorId = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;

if ($vendorId <= 0)
{
    header('Location: dashboard.php');
    exit();
}

// =============================================================================
// Fetch Data from API
// =============================================================================

$apiService = getApiService();
$vendor = null;
$menuItems = array();
$categorizedItems = array();
$error = '';

try
{
    // Fetch restaurant details
    $vendorData = $apiService->getRestaurantById($vendorId);
    
    if (empty($vendorData))
    {
        writeLog("Vendor not found: $vendorId", "API");
        header('Location: dashboard.php');
        exit();
    }
    
    // The API returns the restaurant as a list with one item
    $vendor = $vendorData[0] ?? null;
    
    if (!$vendor)
    {
        writeLog("Invalid vendor data for ID: $vendorId", "API");
        header('Location: dashboard.php');
        exit();
    }
    
    // Fetch menu items
    $menuItems = $apiService->getRestaurantMenu($vendorId);
    
    // Categorize items by type (using restaurant type as category)
    $category = $vendor['type'] ?? 'General';
    $categorizedItems[$category] = $menuItems;
    
    writeLog("Found " . count($menuItems) . " menu items for vendor $vendorId", "API");
}
catch (Exception $e)
{
    writeLog("Error fetching vendor/menu data: " . $e->getMessage(), "API_ERROR");
    $error = "Unable to load vendor data. Please try again later.";
    
    if (empty($vendor))
    {
        header('Location: dashboard.php');
        exit();
    }
}

function escapeMenuBrowseOutput($string)
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
    <title><?php echo htmlspecialchars($vendor['restaurantName'] ?? 'Vendor', ENT_QUOTES, 'UTF-8'); ?> · Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <style>
        /* =============================================================================
           Menu Browse Styles - Matching Mockups
           ============================================================================= */

        .menu-browse-content
        {
            padding: var(--space-8) 0;
        }

        .vendor-info-card
        {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--space-5) var(--space-6);
            margin-bottom: var(--space-6);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
        }

        .vendor-info-card h1
        {
            margin-bottom: var(--space-2);
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .vendor-info-card p
        {
            color: var(--gray-600);
            margin-bottom: var(--space-2);
        }

        .vendor-info-card p i
        {
            color: var(--orange);
            width: 20px;
        }

        .menu-category
        {
            margin-bottom: var(--space-8);
        }

        .menu-category h3
        {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-2);
            border-bottom: 2px solid var(--orange);
            display: inline-block;
            color: var(--gray-800);
        }

        .menu-grid
        {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: var(--space-5);
            margin-top: var(--space-4);
        }

        .menu-item-card
        {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            transition: transform var(--transition-base), box-shadow var(--transition-base);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-100);
        }

        .menu-item-card:hover
        {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .menu-item-card.unavailable
        {
            opacity: 0.6;
        }

        .menu-item-image
        {
            height: 140px;
            background: linear-gradient(135deg, var(--orange-light), var(--gray-100));
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .menu-item-image img
        {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .menu-item-image i
        {
            font-size: 2.5rem;
            color: var(--orange);
        }

        .menu-item-details
        {
            padding: var(--space-4);
        }

        .menu-item-name
        {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: var(--space-2);
            color: var(--gray-800);
        }

        .menu-item-description
        {
            font-size: 0.8125rem;
            color: var(--gray-600);
            margin-bottom: var(--space-3);
            line-height: 1.4;
        }

        .menu-item-price
        {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--orange);
            margin-bottom: var(--space-3);
            display: block;
        }

        .unavailable-badge
        {
            display: inline-block;
            background: var(--error-bg);
            color: var(--error-text);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: var(--space-3);
        }

        .empty-state
        {
            text-align: center;
            padding: var(--space-8) var(--space-4);
            color: var(--gray-500);
        }

        .empty-state i
        {
            font-size: 3rem;
            margin-bottom: var(--space-4);
            color: var(--gray-300);
        }

        .empty-state h3
        {
            color: var(--gray-600);
            margin-bottom: var(--space-2);
        }

        .empty-state p
        {
            margin-bottom: 0;
        }

        @media (max-width: 768px)
        {
            .menu-grid
            {
                grid-template-columns: 1fr;
            }

            .menu-item-image
            {
                height: 120px;
            }

            .menu-browse-content
            {
                padding: var(--space-4) 0;
            }

            .vendor-info-card
            {
                padding: var(--space-3) var(--space-4);
            }

            .vendor-info-card h1
            {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 480px)
        {
            .menu-item-image
            {
                height: 100px;
            }

            .menu-item-details
            {
                padding: var(--space-3);
            }

            .menu-item-name
            {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/student_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="student-content">
                <div class="container">
                    <!-- Vendor Information -->
                    <?php if ($vendor): ?>
                    <div class="vendor-info-card">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-4);">
                            <div>
                                <h1><?php echo escapeMenuBrowseOutput($vendor['restaurantName']); ?></h1>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo escapeMenuBrowseOutput($vendor['address'] ?? 'Campus Location'); ?></p>
                                <p><i class="fas fa-tag"></i> <?php echo escapeMenuBrowseOutput($vendor['type'] ?? 'Various Cuisines'); ?></p>
                            </div>
                            <a href="dashboard.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Vendors</a>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeMenuBrowseOutput($error); ?></div>
                            </div>
                        </div>
                    <?php elseif (empty($menuItems)): ?>
                        <div class="empty-state">
                            <i class="fas fa-utensils"></i>
                            <h3>No Menu Items</h3>
                            <p>This vendor has not added any menu items yet.</p>
                        </div>
                    <?php else: ?>
                        <!-- Menu Categories -->
                        <?php foreach ($categorizedItems as $category => $items): ?>
                            <div class="menu-category">
                                <h3><?php echo escapeMenuBrowseOutput($category); ?></h3>
                                <div class="menu-grid">
                                    <?php foreach ($items as $item): ?>
                                        <div class="menu-item-card" data-item-id="<?php echo $item['itemID']; ?>">
                                            <div class="menu-item-image">
                                                <?php if (!empty($item['imageUrl'])): ?>
                                                    <img src="<?php echo escapeMenuBrowseOutput($item['imageUrl']); ?>" alt="<?php echo escapeMenuBrowseOutput($item['itemName']); ?>">
                                                <?php else: ?>
                                                    <i class="fas fa-utensils"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="menu-item-details">
                                                <h4 class="menu-item-name"><?php echo escapeMenuBrowseOutput($item['itemName']); ?></h4>
                                                <p class="menu-item-description"><?php echo escapeMenuBrowseOutput($item['itemDescription'] ?: 'No description available'); ?></p>
                                                <span class="menu-item-price">R <?php echo number_format($item['itemPrice'], 2); ?></span>
                                                <button class="btn btn-primary btn-sm add-to-cart-btn"
                                                        data-item-id="<?php echo $item['itemID']; ?>"
                                                        data-item-name="<?php echo escapeMenuBrowseOutput($item['itemName']); ?>"
                                                        data-item-price="<?php echo $item['itemPrice']; ?>"
                                                        data-vendor-id="<?php echo $vendorId; ?>"
                                                        data-vendor-name="<?php echo escapeMenuBrowseOutput($vendor['restaurantName'] ?? 'Vendor'); ?>">
                                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
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

    <script>
        document.querySelectorAll('.add-to-cart-btn').forEach(function(button)
        {
            button.addEventListener('click', function()
            {
                const itemId = this.getAttribute('data-item-id');
                const itemName = this.getAttribute('data-item-name');
                const itemPrice = parseFloat(this.getAttribute('data-item-price'));
                const vendorId = <?php echo json_encode($vendorId); ?>;
                const vendorName = <?php echo json_encode($vendor['restaurantName'] ?? 'Vendor'); ?>;
                const newItem = new CartItem(itemId, itemName, itemPrice, vendorId, vendorName, 1, 999);
                cart.addItem(newItem);
                showNotification(itemName + ' added to cart!', 'success');
            });
        });
    </script>

    <script src="<?php echo BASE_URL; ?>/assets/js/cart.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dashboard-common.js"></script>
</body>
</html>