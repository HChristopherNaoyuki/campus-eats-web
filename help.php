<?php
/**
 * Help Center Page - Matching Mockups
 *
 * Provides user support information, guides, and troubleshooting tips.
 *
 * CORRECTIONS (Version 8.0 - Visual Parity):
 * - Updated layout to match mockup designs
 * - Added help cards with icons
 * - Added troubleshooting section
 * - Added contact support section
 * - Removed inline styles and moved to public.css
 *
 * SOURCE: Mockups - Help Center design
 *
 * @version 8.0
 */

session_start();

// Set security headers for this public page
require_once 'solution/includes/auth.php';
setSecurityHeaders();

$pageTitle = 'Help Center';
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Help Center - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="solution/assets/css/style.css">
    <link rel="stylesheet" href="solution/assets/css/public.css">
</head>
<body>
    <?php include_once 'solution/includes/public_header.php'; ?>

    <main>
        <!-- Hero Section -->
        <div class="help-hero">
            <div class="container">
                <h1>Help Center</h1>
                <p>Find answers to your questions and learn how to use Campus Eats.</p>
            </div>
        </div>

        <div class="container help-container">
            <!-- Getting Started Section -->
            <section class="help-section">
                <h2 class="section-heading">
                    <i class="fas fa-rocket"></i> Getting Started
                </h2>
                <div class="help-grid">
                    <div class="help-card">
                        <i class="fas fa-user-plus"></i>
                        <h3>Creating an Account</h3>
                        <p>Click the "Sign Up" button. Fill in your full name, username, email, and choose a password. Your account will be pending until an administrator verifies it.</p>
                    </div>
                    <div class="help-card">
                        <i class="fas fa-sign-in-alt"></i>
                        <h3>Logging In</h3>
                        <p>Use your username and password to log in. Only verified accounts can access the system.</p>
                    </div>
                    <div class="help-card">
                        <i class="fas fa-key"></i>
                        <h3>Resetting Your Password</h3>
                        <p>Click "Forgot Password" on the login page. Enter your username and 16-character User ID to set a new password.</p>
                    </div>
                </div>
            </section>

            <!-- For Students Section -->
            <section class="help-section">
                <h2 class="section-heading">
                    <i class="fas fa-user-graduate"></i> Student Help
                </h2>
                <div class="help-grid">
                    <div class="help-card">
                        <i class="fas fa-store"></i>
                        <h3>Browsing Vendors</h3>
                        <p>From your dashboard, click "Browse Vendors" to see all available campus vendors. Click on any vendor to view their menu.</p>
                    </div>
                    <div class="help-card">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Adding Items to Cart</h3>
                        <p>On a vendor's menu page, click "Add to Cart" for any item. You can adjust quantities before proceeding to checkout.</p>
                    </div>
                    <div class="help-card">
                        <i class="fas fa-credit-card"></i>
                        <h3>Placing an Order</h3>
                        <p>Review your cart, select a pickup time, choose a payment method, and confirm your order. You will receive an order number for tracking.</p>
                    </div>
                    <div class="help-card">
                        <i class="fas fa-map-marker-alt"></i>
                        <h3>Tracking Your Order</h3>
                        <p>Go to "My Orders" and click "Track Order" to see real-time status updates: Pending, Accepted, Preparing, Ready for Pickup, or Completed.</p>
                    </div>
                </div>
            </section>

            <!-- For Vendors Section -->
            <section class="help-section">
                <h2 class="section-heading">
                    <i class="fas fa-store"></i> Vendor Help
                </h2>
                <div class="help-grid">
                    <div class="help-card">
                        <i class="fas fa-utensils"></i>
                        <h3>Managing Your Menu</h3>
                        <p>From your vendor dashboard, click "Menu Management" to add, edit, or remove food items. Each item requires a name, description, price, and size.</p>
                    </div>
                    <div class="help-card">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>Processing Orders</h3>
                        <p>View all orders in the "Orders" section. You can accept or reject pending orders, update status, and mark orders as ready for pickup.</p>
                    </div>
                    <div class="help-card">
                        <i class="fas fa-chart-line"></i>
                        <h3>Sales Reports</h3>
                        <p>Access "Sales Reports" to view daily sales breakdowns, top selling items, and revenue summaries. Reports can be exported as CSV files.</p>
                    </div>
                    <div class="help-card">
                        <i class="fas fa-toggle-on"></i>
                        <h3>Opening and Closing</h3>
                        <p>Use the "Open Shop" or "Close Shop" button on your dashboard to control whether students can place orders from your business.</p>
                    </div>
                </div>
            </section>

            <!-- Troubleshooting Section -->
            <section class="help-section">
                <h2 class="section-heading">
                    <i class="fas fa-wrench"></i> Troubleshooting
                </h2>
                <div class="troubleshooting-list">
                    <div class="trouble-item">
                        <p><i class="fas fa-exclamation-triangle"></i> Cannot log in after registration</p>
                        <p>Your account must be verified by an administrator before you can log in. This process typically takes 24 to 48 hours. Check your email for confirmation.</p>
                    </div>
                    <div class="trouble-item">
                        <p><i class="fas fa-exclamation-triangle"></i> Forgot my User ID</p>
                        <p>Your User ID was provided during registration. Contact your campus administrator or IT support to retrieve it.</p>
                    </div>
                    <div class="trouble-item">
                        <p><i class="fas fa-exclamation-triangle"></i> Vendor cannot accept orders</p>
                        <p>Ensure your shop is set to "Open" on your dashboard. Also check that your account is verified and active.</p>
                    </div>
                    <div class="trouble-item">
                        <p><i class="fas fa-exclamation-triangle"></i> Payment processing error</p>
                        <p>Verify your payment method details. If the issue persists, contact the finance department or try an alternative payment method.</p>
                    </div>
                    <div class="trouble-item">
                        <p><i class="fas fa-exclamation-triangle"></i> API data not loading</p>
                        <p>Ensure you have a stable internet connection. If the issue persists, the Fake Restaurant API may be temporarily unavailable.</p>
                    </div>
                </div>
            </section>

            <!-- Contact Support Section -->
            <section class="help-section">
                <h2 class="section-heading">
                    <i class="fas fa-headset"></i> Contact Support
                </h2>
                <div class="contact-support-box">
                    <i class="fas fa-envelope"></i>
                    <h3>Still need help?</h3>
                    <p>Our support team is available Monday through Friday, 9:00 AM to 5:00 PM.</p>
                    <p><strong>Email:</strong> <a href="mailto:support@campuseats.com">support@campuseats.com</a></p>
                    <p><strong>Phone:</strong> +27 12 345 6789</p>
                    <p><strong>Location:</strong> Rosebank International</p>
                </div>
            </section>
        </div>
    </main>

    <?php include_once 'solution/includes/footer.php'; ?>
</body>
</html>