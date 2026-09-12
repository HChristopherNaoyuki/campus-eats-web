<?php
/**
 * Campus Eats - Landing Page (Entry Point)
 *
 * Serves as the landing page for unauthenticated users.
 * Displays real API data from the Fake Restaurant API.
 *
 * CORRECTIONS (Version 14.0 - Visual Parity with Mockups):
 * - Updated hero section to match mockup 1.png
 * - Added stats section matching mockup 1.png
 * - Added "How It Works" section matching mockup 2.png
 * - Added features section matching mockup 2.png
 * - Added CTA section matching mockup 3.png
 * - Removed inline styles and moved to public.css
 * - Improved responsive behavior
 *
 * SOURCE: API Documentation - Fake Restaurant API
 * SOURCE: Mockups - 1.png, 2.png, 3.png, 4.png
 *
 * @version 14.0
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

if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true)
{
    $accountType = $_SESSION['account_type'] ?? '';
    writeLog("User already logged in, redirecting to dashboard. Role: $accountType", "AUTH");

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
// Generate CSRF Token for Login/Register Forms
// =============================================================================

if (empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// =============================================================================
// Fetch Data from API
// =============================================================================

$apiService = getApiService();
$restaurants = array();
$featuredRestaurant = null;
$error = '';

try
{
    // Fetch all restaurants from the API
    $restaurants = $apiService->getAllRestaurants();
    
    // Log the number of restaurants fetched
    writeLog("Fetched " . count($restaurants) . " restaurants from API", "API");
    
    // Select a featured restaurant (first one in the list with items)
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
                // Skip restaurants that don't have a menu
                continue;
            }
        }
    }
}
catch (Exception $e)
{
    writeLog("Error fetching restaurants from API: " . $e->getMessage(), "API_ERROR");
    $error = "Unable to load restaurant data. Please try again later.";
}

$pageTitle = 'Skip the line. Pick up on campus.';

function escapeOutput($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Calculate total menu items
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
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title>Campus Eats · Skip the line. Pick up on campus.</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
</head>
<body>
    <?php include_once 'solution/includes/public_header.php'; ?>

    <main>
        <!-- =========================================================================
             Hero Section - Matching Mockup 1.png
             ========================================================================= -->
        <section id="home" class="hero">
            <div class="container">
                <h1>Skip the line.<br><span>Pick up on campus.</span></h1>
                <p>Campus Eats is the on-campus pickup network. Order ahead from your favorite campus vendor, then grab it on the way to class. No delivery fee, no waiting.</p>
                <div class="hero-buttons">
                    <a href="solution/modules/auth/register.php" class="btn btn-primary">Order now</a>
                    <a href="#how-it-works" class="btn btn-outline">Learn more</a>
                </div>
            </div>
        </section>

        <!-- =========================================================================
             Stats Section - Matching Mockup 1.png
             ========================================================================= -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-grid">
                    <div>
                        <div class="stat-number"><?php echo number_format(count($restaurants)); ?></div>
                        <div class="stat-label">Campus Vendors</div>
                    </div>
                    <div>
                        <div class="stat-number"><?php echo number_format($totalItems); ?></div>
                        <div class="stat-label">Menu Items</div>
                    </div>
                    <div>
                        <div class="stat-number">&lt;5 min</div>
                        <div class="stat-label">Avg Pickup</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- =========================================================================
             How It Works Section - Matching Mockup 2.png
             ========================================================================= -->
        <section id="how-it-works" class="how-it-works">
            <div class="container">
                <div class="section-title">
                    <h2>Pickup in three steps</h2>
                    <p>Designed around the campus rhythm — between lectures, before practice, after the library.</p>
                </div>
                <div class="steps">
                    <div class="step">
                        <div class="step-number">1</div>
                        <h3>Browse &amp; order</h3>
                        <p>Pick items from any campus vendor and confirm your order.</p>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <h3>Vendor prepares</h3>
                        <p>Track status as it moves from Pending → Preparing → Completed.</p>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <h3>Pick it up</h3>
                        <p>Walk over to the vendor's stall and grab your bag. Done.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- =========================================================================
             Features Section - Matching Mockup 2.png
             ========================================================================= -->
        <section class="features">
            <div class="container">
                <div class="section-title">
                    <h2>Everything the system manages</h2>
                    <p>Four core modules, exactly as defined in the process spec.</p>
                </div>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-users"></i></div>
                        <h3>User Management</h3>
                        <p>Register &amp; sign in as Student, Vendor, or Admin.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-store"></i></div>
                        <h3>Vendor Management</h3>
                        <p>Onboard campus vendors with location &amp; contact.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-utensils"></i></div>
                        <h3>Menu Management</h3>
                        <p>Add, update, and remove menu items per vendor.</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon"><i class="fas fa-clipboard-list"></i></div>
                        <h3>Order Management</h3>
                        <p>Place orders and track Pending → Preparing → Completed.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- =========================================================================
             Featured Vendor Section
             ========================================================================= -->
        <section id="vendors" class="vendors">
            <div class="container">
                <div class="section-title">
                    <h2>Featured Vendor</h2>
                    <p>Discover our latest campus vendor. Sign up to see all available options.</p>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="alert-content">
                            <div class="alert-title">Error</div>
                            <div class="alert-message"><?php echo escapeOutput($error); ?></div>
                        </div>
                    </div>
                <?php elseif ($featuredRestaurant !== null): ?>
                <div class="featured-vendor">
                    <div class="featured-vendor-header">
                        <h2>
                            <i class="fas fa-store"></i>
                            <?php echo escapeOutput($featuredRestaurant['restaurantName']); ?>
                        </h2>
                    </div>
                    <div class="featured-vendor-body">
                        <p>
                            <i class="fas fa-map-marker-alt"></i>
                            <?php echo escapeOutput($featuredRestaurant['address']); ?>
                        </p>
                        <p>
                            <i class="fas fa-tag"></i>
                            <?php echo escapeOutput($featuredRestaurant['type']); ?>
                        </p>
                        <?php if (!empty($featuredRestaurant['menu'])): ?>
                        <div style="margin-top: var(--space-4);">
                            <h4 style="margin-bottom: var(--space-3); font-weight: 600; color: var(--gray-700);">Popular Items</h4>
                            <div class="menu-preview">
                                <?php foreach ($featuredRestaurant['menu'] as $item): ?>
                                <div class="menu-preview-item">
                                    <div class="item-name"><?php echo escapeOutput($item['itemName']); ?></div>
                                    <div class="item-price">R <?php echo number_format($item['itemPrice'], 2); ?></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div style="margin-top: var(--space-5); text-align: center; padding-top: var(--space-4); border-top: 1px solid var(--gray-200);">
                            <p style="color: var(--gray-600);">
                                <i class="fas fa-info-circle"></i>
                                Sign up or log in to view all vendors and place orders.
                            </p>
                            <div style="display: flex; gap: var(--space-3); justify-content: center; flex-wrap: wrap; margin-top: var(--space-3);">
                                <a href="solution/modules/auth/register.php" class="btn btn-primary">Sign Up to Order</a>
                                <a href="solution/modules/auth/login.php" class="btn btn-outline">Log In</a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-store-slash"></i>
                    <h3>No Vendors Available</h3>
                    <p>No vendors are currently available. Please check back later.</p>
                    <a href="solution/modules/auth/register.php" class="btn btn-primary">Sign Up</a>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- =========================================================================
             CTA Section - Matching Mockup 3.png
             ========================================================================= -->
        <section class="cta vendor-cta">
            <div class="container">
                <h2>Run a stall on campus?</h2>
                <p>List your menu, take pickup orders, and fulfill them with a simple status workflow. Reports for sales, vendor performance, and user activity included.</p>
                <ul>
                    <li><i class="fas fa-check-circle"></i> Per-vendor menu CRUD</li>
                    <li><i class="fas fa-check-circle"></i> Live order queue</li>
                    <li><i class="fas fa-check-circle"></i> Sales &amp; performance reports</li>
                </ul>
                <a href="solution/modules/auth/register.php" class="btn btn-secondary">Become a vendor</a>
            </div>
        </section>
    </main>

    <?php include_once 'solution/includes/footer.php'; ?>
</body>
</html>