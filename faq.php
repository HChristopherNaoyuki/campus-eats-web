<?php
/**
 * FAQ Page - Matching Mockups 7-14.png
 *
 * Provides answers to frequently asked questions about Campus Eats.
 * Matches mockup designs for the FAQ page.
 *
 * CORRECTIONS (Version 6.0 - Visual Parity):
 * - Updated layout to match mockups 7-14.png
 * - Added accordion-style FAQ items
 * - Improved responsive behavior
 * - Removed inline styles and moved to public.css
 *
 * SOURCE: Mockups - 7.png, 8.png, 9.png, 10.png, 11.png, 12.png, 13.png, 14.png
 *
 * @version 6.0
 */

session_start();

// Set security headers for this public page
require_once 'solution/includes/auth.php';
setSecurityHeaders();

$pageTitle = 'FAQ';
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>FAQ - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="solution/assets/css/style.css">
    <link rel="stylesheet" href="solution/assets/css/public.css">
</head>
<body>
    <?php include_once 'solution/includes/public_header.php'; ?>

    <main>
        <!-- Hero Section -->
        <div class="faq-hero">
            <div class="container">
                <h1>Frequently asked questions</h1>
                <p>Answers to the questions students and vendors ask most often.</p>
            </div>
        </div>

        <div class="container faq-container">
            <div class="faq-list">
                <!-- FAQ Item 1 -->
                <div class="faq-item">
                    <h3 class="faq-question">
                        <i class="fas fa-question-circle"></i>
                        What is Campus Eats?
                    </h3>
                    <p class="faq-answer">Campus Eats is a pickup-only ordering platform for campus food vendors. You order ahead from your phone or the web, the vendor prepares your order, and you collect it at the stall.</p>
                </div>

                <!-- FAQ Item 2 -->
                <div class="faq-item">
                    <h3 class="faq-question">
                        <i class="fas fa-truck"></i>
                        Is there a delivery fee?
                    </h3>
                    <p class="faq-answer">No. Campus Eats is pick-up only, so there are no delivery fees and no delivery orders.</p>
                </div>

                <!-- FAQ Item 3 -->
                <div class="faq-item">
                    <h3 class="faq-question">
                        <i class="fas fa-calculator"></i>
                        How is my total calculated?
                    </h3>
                    <p class="faq-answer">Your subtotal has 20% tax added, then rounded up to the nearest R5, and students then receive a 2.5% discount. All prices are in South African Rand (R).</p>
                </div>

                <!-- FAQ Item 4 -->
                <div class="faq-item">
                    <h3 class="faq-question">
                        <i class="fas fa-id-card"></i>
                        What is a User ID?
                    </h3>
                    <p class="faq-answer">When you register, the platform issues a 16-character User ID that acts as your recovery key. You can sign in with either your User ID or your email address together with your password.</p>
                </div>

                <!-- FAQ Item 5 -->
                <div class="faq-item">
                    <h3 class="faq-question">
                        <i class="fas fa-tasks"></i>
                        How do I track my order?
                    </h3>
                    <p class="faq-answer">Orders move through Pending, Accepted or Rejected, Preparing, Ready, and Completed. The status updates live on your dashboard as the vendor works through the queue.</p>
                </div>

                <!-- FAQ Item 6 -->
                <div class="faq-item">
                    <h3 class="faq-question">
                        <i class="fas fa-store"></i>
                        How do I become a vendor?
                    </h3>
                    <p class="faq-answer">Create an account and contact us to have your shop onboarded. Vendors get a unique shop name, their own inventory, and full credit, refund, update, and delete control over their menu.</p>
                </div>

                <!-- FAQ Item 7 -->
                <div class="faq-item">
                    <h3 class="faq-question">
                        <i class="fas fa-mobile-alt"></i>
                        Is there a mobile application?
                    </h3>
                    <p class="faq-answer">Yes. The Kotlin mobile application and this website share the same design language and the same order workflow, so you can move between them freely.</p>
                </div>

                <!-- FAQ Item 8 -->
                <div class="faq-item">
                    <h3 class="faq-question">
                        <i class="fas fa-heart"></i>
                        How can I support the project?
                    </h3>
                    <p class="faq-answer">Campus Eats accepts Bitcoin and Ethereum donations, which fund continued development and production of the website and the mobile application.</p>
                </div>
            </div>
        </div>
    </main>

    <?php include_once 'solution/includes/footer.php'; ?>
</body>
</html>