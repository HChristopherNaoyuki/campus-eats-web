<?php
/**
 * Terms of Service Page
 *
 * Outlines the terms and conditions for using the Campus Eats platform.
 * Matches mockup designs for the Terms of Service page.
 *
 * SOURCE: Mockups - Terms of Service design
 *
 * @version 4.0
 */

session_start();

// Set security headers for this public page
require_once 'solution/includes/auth.php';
setSecurityHeaders();

$pageTitle = 'Terms of Service';
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Terms of Service - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="solution/assets/css/style.css">
    <link rel="stylesheet" href="solution/assets/css/public.css">
</head>
<body>
    <?php include_once 'solution/includes/public_header.php'; ?>

    <main>
        <div class="container terms-container">
            <h1 class="page-title">Terms of Service</h1>

            <div class="terms-content">
                <p class="effective-date"><strong>Effective Date:</strong> January 1, 2025</p>
                <p class="intro-text">Welcome to Campus Eats. By accessing or using our platform, you agree to be bound by these Terms of Service. Please read them carefully.</p>

                <section class="terms-section">
                    <h2 class="section-heading">1. Acceptance of Terms</h2>
                    <p>By registering for, accessing, or using the Campus Eats platform, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service. If you do not agree to these terms, you may not use the platform.</p>
                </section>

                <section class="terms-section">
                    <h2 class="section-heading">2. User Accounts</h2>
                    <p>To use certain features of Campus Eats, you must register for an account. You agree to provide accurate, current, and complete information during the registration process. You are responsible for maintaining the confidentiality of your login credentials. You are fully responsible for all activities that occur under your account. You agree to notify us immediately of any unauthorized use of your account.</p>
                    <p>All new registrations require administrator verification before account activation. Campus Eats reserves the right to suspend or terminate any account that violates these terms.</p>
                </section>

                <section class="terms-section">
                    <h2 class="section-heading">3. User ID and Password Security</h2>
                    <p>Your 16-character User ID is unique to your account. You must not share your password with any third party. You are responsible for all actions taken using your account credentials. Campus Eats will not be liable for any loss or damage arising from your failure to protect your login information.</p>
                </section>

                <section class="terms-section">
                    <h2 class="section-heading">4. Orders and Payments</h2>
                    <p>When you place an order through Campus Eats, you agree to pay the specified total amount. All payments are processed through our secure payment system. Once an order is confirmed, it cannot be modified. Cancellation requests must be submitted before the vendor accepts the order. Refunds are issued at the sole discretion of Campus Eats and the individual vendor.</p>
                </section>

                <section class="terms-section">
                    <h2 class="section-heading">5. Vendor Responsibilities</h2>
                    <p>Vendors agree to maintain accurate menu information, including prices and item availability. Vendors must process orders in a timely manner and update order statuses accurately. Campus Eats reserves the right to suspend or remove vendors who fail to meet these obligations or receive excessive customer complaints.</p>
                </section>

                <section class="terms-section">
                    <h2 class="section-heading">6. Prohibited Conduct</h2>
                    <p>You agree not to:</p>
                    <ul>
                        <li>Use the platform for any illegal purpose or in violation of any applicable laws.</li>
                        <li>Attempt to gain unauthorized access to any part of the platform.</li>
                        <li>Interfere with or disrupt the integrity or performance of the platform.</li>
                        <li>Submit false, inaccurate, or misleading information.</li>
                        <li>Harass, abuse, or harm another person using the platform.</li>
                        <li>Post inappropriate, defamatory, or offensive content in the feedback forum.</li>
                    </ul>
                </section>

                <section class="terms-section">
                    <h2 class="section-heading">7. Feedback Forum</h2>
                    <p>The complaints and compliments forum allows users to submit feedback about their experience. All submissions are reviewed by administrators. Campus Eats reserves the right to remove any feedback that is abusive, false, or violates these terms. Only administrators can view forum submissions.</p>
                </section>

                <section class="terms-section">
                    <h2 class="section-heading">8. Account Suspension and Termination</h2>
                    <p>Campus Eats reserves the right to suspend or terminate your account at any time for any reason, including but not limited to violation of these Terms of Service, fraudulent activity, or misuse of the platform. Admin users may suspend or activate accounts as needed.</p>
                </section>

                <section class="terms-section">
                    <h2 class="section-heading">9. Limitation of Liability</h2>
                    <p>To the maximum extent permitted by law, Campus Eats shall not be liable for any indirect, incidental, special, consequential, or punitive damages arising out of or relating to your use of the platform. This includes but is not limited to damages for loss of profits, goodwill, use, data, or other intangible losses.</p>
                </section>

                <section class="terms-section">
                    <h2 class="section-heading">10. Changes to Terms</h2>
                    <p>Campus Eats may modify these Terms of Service at any time. We will notify users of significant changes by posting a notice on the platform. Your continued use of the platform after such modifications constitutes your acceptance of the updated terms.</p>
                </section>

                <section class="terms-section">
                    <h2 class="section-heading">11. Governing Law</h2>
                    <p>These Terms of Service shall be governed by and construed in accordance with the laws of the Republic of South Africa. Any disputes arising under these terms shall be resolved exclusively in the courts of South Africa.</p>
                </section>

                <section class="terms-section">
                    <h2 class="section-heading">12. Contact Information</h2>
                    <p>If you have any questions about these Terms of Service, please contact us at:</p>
                    <p><strong>Email:</strong> legal@campuseats.com</p>
                    <p><strong>Address:</strong> Rosebank College Campus, IT Department, Johannesburg, South Africa</p>
                </section>

                <p class="consent-text">By using Campus Eats, you acknowledge that you have read and understood these Terms of Service.</p>
            </div>
        </div>
    </main>

    <?php include_once 'solution/includes/footer.php'; ?>
</body>
</html>