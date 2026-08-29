<?php
/**
 * Privacy Policy Page
 *
 * Outlines how user data is collected, used, and protected on the Campus Eats platform.
 * Matches mockup designs for the Privacy Policy page.
 *
 * SOURCE: Mockups - Privacy Policy design
 *
 * @version 6.0
 */

session_start();

// Set security headers for this public page
require_once 'solution/includes/auth.php';
setSecurityHeaders();

$pageTitle = 'Privacy Policy';
$csrfToken = isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Privacy Policy - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="solution/assets/css/style.css">
    <link rel="stylesheet" href="solution/assets/css/public.css">
</head>
<body>
    <?php include_once 'solution/includes/public_header.php'; ?>

    <main>
        <div class="container privacy-container">
            <h1 class="page-title">Privacy Policy</h1>

            <div class="privacy-content">
                <p class="effective-date"><strong>Effective Date:</strong> July 1, 2026</p>
                <p class="intro-text">Campus Eats is committed to protecting your privacy. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our platform.</p>

                <section class="privacy-section">
                    <h2 class="section-heading">1. Information We Collect</h2>
                    <p>We collect the following types of information:</p>
                    <ul>
                        <li><strong>Personal Information:</strong> Full name, username, email address, phone number, and your 16-character User ID.</li>
                        <li><strong>Account Information:</strong> Account type (Student, Standard, Vendor, or Admin), registration date, and account status.</li>
                        <li><strong>Order Information:</strong> Order history, items purchased, total amounts, and order statuses.</li>
                        <li><strong>Payment Information:</strong> Payment method and transaction references. We do not store full credit card details.</li>
                        <li><strong>Feedback Information:</strong> Complaints and compliments submitted through the forum.</li>
                        <li><strong>Usage Data:</strong> Log data such as IP address, browser type, and pages visited.</li>
                    </ul>
                </section>

                <section class="privacy-section">
                    <h2 class="section-heading">2. How We Use Your Information</h2>
                    <p>We use the information we collect for the following purposes:</p>
                    <ul>
                        <li>To create and manage your account.</li>
                        <li>To process and fulfill your orders.</li>
                        <li>To communicate with you about your orders and account status.</li>
                        <li>To provide customer support and respond to inquiries.</li>
                        <li>To improve and optimize the platform.</li>
                        <li>To monitor and analyze usage trends.</li>
                        <li>To detect, prevent, and address technical or security issues.</li>
                        <li>To comply with legal obligations.</li>
                    </ul>
                </section>

                <section class="privacy-section">
                    <h2 class="section-heading">3. Data Security</h2>
                    <p>We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. These measures include:</p>
                    <ul>
                        <li>Password hashing using bcrypt for secure password storage.</li>
                        <li>Prepared statements to prevent SQL injection attacks.</li>
                        <li>Session management with secure cookie settings.</li>
                        <li>HTTPS encryption for data transmission (in production environments).</li>
                    </ul>
                    <p>Despite these measures, no method of transmission over the Internet is 100 percent secure. We cannot guarantee absolute security.</p>
                </section>

                <section class="privacy-section">
                    <h2 class="section-heading">4. Data Sharing and Disclosure</h2>
                    <p>We do not sell, trade, or rent your personal information to third parties. We may share your information in the following circumstances:</p>
                    <ul>
                        <li><strong>With Vendors:</strong> When you place an order, your name and order details are shared with the vendor to fulfill your order.</li>
                        <li><strong>With Administrators:</strong> Administrators can view user account information for management and support purposes.</li>
                        <li><strong>Legal Requirements:</strong> We may disclose your information if required to do so by law or in response to valid legal requests.</li>
                    </ul>
                </section>

                <section class="privacy-section">
                    <h2 class="section-heading">5. Data Retention</h2>
                    <p>We retain your personal information for as long as your account is active or as needed to provide services. If you close your account, we will delete your personal information within a reasonable timeframe, except where retention is required for legal, regulatory, or legitimate business purposes.</p>
                </section>

                <section class="privacy-section">
                    <h2 class="section-heading">6. Your Rights</h2>
                    <p>Depending on your location, you may have certain rights regarding your personal information, including:</p>
                    <ul>
                        <li>The right to access and receive a copy of your personal information.</li>
                        <li>The right to correct inaccurate or incomplete information.</li>
                        <li>The right to request deletion of your personal information.</li>
                        <li>The right to withdraw consent where processing is based on consent.</li>
                    </ul>
                    <p>To exercise these rights, please contact us using the information provided in Section 9 of this policy.</p>
                </section>

                <section class="privacy-section">
                    <h2 class="section-heading">7. Cookies and Tracking Technologies</h2>
                    <p>Campus Eats uses session cookies to maintain your login state and cart contents. Session cookies are temporary and expire when you close your browser. We do not use persistent cookies or third-party tracking technologies for advertising purposes.</p>
                </section>

                <section class="privacy-section">
                    <h2 class="section-heading">8. Children's Privacy</h2>
                    <p>Campus Eats is intended for use by university students, faculty, and staff. We do not knowingly collect personal information from individuals under the age of 18. If you become aware that a child has provided us with personal information, please contact us immediately.</p>
                </section>

                <section class="privacy-section">
                    <h2 class="section-heading">9. Contact Us</h2>
                    <p>If you have any questions or concerns about this Privacy Policy or our data practices, please contact us at:</p>
                    <p><strong>Email:</strong> privacy@campuseats.com</p>
                    <p><strong>Address:</strong> Rosebank International, Johannesburg, South Africa</p>
                </section>

                <section class="privacy-section">
                    <h2 class="section-heading">10. Changes to This Privacy Policy</h2>
                    <p>We may update this Privacy Policy from time to time. We will notify you of significant changes by posting a notice on the platform or sending an email to the address associated with your account. Your continued use of the platform after such changes constitutes your acceptance of the updated policy.</p>
                </section>

                <p class="consent-text">By using Campus Eats, you consent to the collection and use of your information as described in this Privacy Policy.</p>
            </div>
        </div>
    </main>

    <?php include_once 'solution/includes/footer.php'; ?>
</body>
</html>