<?php
/**
 * About Us Page - Matching Mockup 5.png
 *
 * Provides information about Campus Eats with clearly defined sections.
 * Matches mockup designs for the About page.
 *
 * CORRECTIONS (Version 6.0 - Visual Parity):
 * - Updated layout to match mockup 5.png
 * - Added "Our story" section
 * - Added feature cards with icons
 * - Improved responsive behavior
 * - Removed inline styles and moved to public.css
 *
 * SOURCE: Mockups - 5.png
 *
 * @version 6.0
 */

session_start();

// Set security headers for this public page
require_once 'solution/includes/auth.php';
setSecurityHeaders();

$pageTitle = 'About Us';
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>About Us - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="solution/assets/css/style.css">
    <link rel="stylesheet" href="solution/assets/css/public.css">
</head>
<body>
    <?php include_once 'solution/includes/public_header.php'; ?>

    <main>
        <!-- Hero Section -->
        <div class="about-hero">
            <div class="container">
                <h1>The team behind Campus Eats</h1>
                <p>Campus Eats is a student pickup platform: a Kotlin mobile application and a matching responsive web application, sharing one product language and one order workflow.</p>
            </div>
        </div>

        <div class="container about-container">
            <!-- Our Story Section -->
            <section class="about-section">
                <h2 class="section-heading">
                    <i class="fas fa-book-open"></i> Our Story
                </h2>
                <p class="section-text">
                    Campus Eats started as a process-modelling project: map how a campus food order actually moves from a hungry student to a vendor stall and back again. That model became four core modules — user management, vendor management, menu management, and order management — and those modules became the product you are looking at.
                </p>
                <p class="section-text">
                    The platform is developed in the open. The web application and the Kotlin mobile application are maintained side by side so that a change in one is reflected in the other.
                </p>
                <div style="margin-top: var(--space-4);">
                    <a href="#" class="btn btn-primary">
                        <i class="fas fa-play"></i> Watch video
                    </a>
                </div>
            </section>

            <!-- About Grid - Features -->
            <div class="about-grid">
                <div class="about-card">
                    <div class="icon"><i class="fas fa-clock"></i></div>
                    <h3>Built around the campus rhythm</h3>
                    <p>Order between lectures, collect on the way past the stall — no delivery fleet, no delivery fees.</p>
                </div>
                <div class="about-card">
                    <div class="icon"><i class="fas fa-graduation-cap"></i></div>
                    <h3>Made with students</h3>
                    <p>Every screen started as a mobile mockup tested against real campus ordering behaviour.</p>
                </div>
                <div class="about-card">
                    <div class="icon"><i class="fas fa-store"></i></div>
                    <h3>Fair to vendors</h3>
                    <p>Vendors keep their own shop, their own inventory, and full control over their menu and order queue.</p>
                </div>
                <div class="about-card">
                    <div class="icon"><i class="fas fa-code"></i></div>
                    <h3>Open Development</h3>
                    <p>The platform is developed in the open. The web application and the Kotlin mobile application are maintained side by side.</p>
                </div>
            </div>

            <!-- What We Offer Section -->
            <section class="about-section">
                <h2 class="section-heading">
                    <i class="fas fa-concierge-bell"></i> What We Offer
                </h2>
                <ul class="feature-list">
                    <li><i class="fas fa-check-circle"></i> Browse menus from all campus vendors</li>
                    <li><i class="fas fa-check-circle"></i> Place take-away orders in advance</li>
                    <li><i class="fas fa-check-circle"></i> Schedule pickup times around your classes</li>
                    <li><i class="fas fa-check-circle"></i> Track your order status in real-time</li>
                </ul>
            </section>

            <!-- For Students Section -->
            <section class="about-section">
                <h2 class="section-heading">
                    <i class="fas fa-user-graduate"></i> For Students
                </h2>
                <p class="section-text">
                    Students can browse vendor menus, customize orders, and schedule pickups that fit their class schedule. No more rushing between lectures or waiting in long cafeteria lines. Simply order ahead, receive a notification when your food is ready, and collect it at your convenience.
                </p>
            </section>

            <!-- For Vendors Section -->
            <section class="about-section">
                <h2 class="section-heading">
                    <i class="fas fa-store"></i> For Vendors
                </h2>
                <p class="section-text">
                    Campus Eats empowers vendors with a digital storefront. Manage your menu, update item availability, process orders efficiently, and access sales reports. Focus on food preparation while we handle the ordering logistics.
                </p>
            </section>

            <!-- Our Technology Section -->
            <section class="about-section">
                <h2 class="section-heading">
                    <i class="fas fa-microchip"></i> Our Technology
                </h2>
                <p class="section-text">
                    Built with modern web technologies including PHP, MySQL, HTML5, CSS3, and JavaScript. Campus Eats prioritizes security, reliability, and a responsive design that works seamlessly on desktops, tablets, and smartphones. The platform integrates with the Fake Restaurant API for real-time vendor and menu data.
                </p>
            </section>
        </div>
    </main>

    <?php include_once 'solution/includes/footer.php'; ?>
</body>
</html>