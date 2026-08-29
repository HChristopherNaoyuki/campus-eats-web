<?php
/**
 * Global Footer Include File - With Firebase Integration
 *
 * This file contains the HTML footer, copyright information, and JavaScript includes.
 * It must be included at the bottom of every page.
 *
 * CORRECTIONS (Version 11.0 - Firebase Integration):
 * - Added Firebase JavaScript modules
 * - Added Feedback module for pages that need it
 * - Added user context for Firebase operations
 *
 * @version 11.0
 */

// Load required dependencies to define constants
require_once dirname(__DIR__) . '/config/constants.php';

$nonceAttribute = '';
if (defined('CSP_NONCE') && CSP_NONCE)
{
    $nonceAttribute = ' nonce="' . CSP_NONCE . '"';
}

// Determine if Firebase should be loaded on this page
$loadFirebase = true; // Default: load on all pages

// Check for user context for Firebase
$firebaseUserContext = array();
if (isset($_SESSION['user_id']))
{
    $firebaseUserContext = array(
        'userId' => $_SESSION['user_id'] ?? null,
        'role' => $_SESSION['account_type'] ?? null,
        'fullName' => $_SESSION['full_name'] ?? $_SESSION['username'] ?? null,
        'email' => $_SESSION['email'] ?? null
    );
}
?>
    </main>

    <!-- =============================================================================
         Footer - 4-Column Layout Matching Mockup 4.png
         ============================================================================= -->
    <footer class="app-footer">
        <div class="container">
            <!-- Footer Grid - 4 Columns -->
            <div class="footer-grid">
                <!-- Column 1: Brand -->
                <div class="footer-column">
                    <div class="footer-logo">
                        <i class="fas fa-utensils"></i>
                        <span>Campus Eats</span>
                    </div>
                    <p class="footer-description">
                        The student pickup network — order ahead from campus vendors and skip the queue.
                    </p>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
                        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" aria-label="GitHub"><i class="fab fa-github"></i></a>
                    </div>
                </div>

                <!-- Column 2: Explore -->
                <div class="footer-column">
                    <h3>Explore</h3>
                    <ul>
                        <li><a href="<?php echo ROOT_URL; ?>/index.php#home">Home</a></li>
                        <li><a href="<?php echo ROOT_URL; ?>/about.php">About</a></li>
                        <li><a href="<?php echo ROOT_URL; ?>/index.php#vendors">Services</a></li>
                        <li><a href="<?php echo ROOT_URL; ?>/faq.php">FAQ</a></li>
                        <li><a href="<?php echo ROOT_URL; ?>/help.php">Help Center</a></li>
                    </ul>
                </div>

                <!-- Column 3: Application -->
                <div class="footer-column">
                    <h3>Application</h3>
                    <ul>
                        <li><a href="<?php echo BASE_URL; ?>/modules/auth/login.php">Sign in</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/modules/auth/register.php">Create account</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/modules/student/dashboard.php">Student dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/modules/vendor/dashboard.php">Vendor dashboard</a></li>
                        <li><a href="<?php echo BASE_URL; ?>/modules/admin/dashboard.php">Admin dashboard</a></li>
                    </ul>
                </div>

                <!-- Column 4: Legal -->
                <div class="footer-column">
                    <h3>Legal</h3>
                    <ul>
                        <li><a href="<?php echo ROOT_URL; ?>/privacy.php">Privacy Policy</a></li>
                        <li><a href="<?php echo ROOT_URL; ?>/terms.php">Terms &amp; Conditions</a></li>
                        <li><a href="https://github.com/HChristopherNaoyuki/campus-eats-web" target="_blank" rel="noopener noreferrer">Website repository</a></li>
                        <li><a href="#" target="_blank" rel="noopener noreferrer">Mobile app repository</a></li>
                    </ul>
                </div>
            </div>

            <!-- Footer Bottom - Button-Style Navigation -->
            <div class="footer-bottom">
                <div class="copyright">
                    <p>&copy; <span id="current-year"></span> Campus Eats — student pickup network. All prices in South African Rand (R).</p>
                </div>
                <div class="footer-nav-buttons">
                    <a href="<?php echo ROOT_URL; ?>/terms.php" class="footer-nav-btn">
                        <i class="fas fa-file-contract"></i>
                        <span>Terms</span>
                    </a>
                    <a href="<?php echo ROOT_URL; ?>/privacy.php" class="footer-nav-btn">
                        <i class="fas fa-shield-alt"></i>
                        <span>Privacy</span>
                    </a>
                    <a href="<?php echo ROOT_URL; ?>/help.php" class="footer-nav-btn">
                        <i class="fas fa-life-ring"></i>
                        <span>Support</span>
                    </a>
                    <a href="https://github.com/HChristopherNaoyuki" target="_blank" rel="noopener noreferrer" class="footer-nav-btn">
                        <i class="fab fa-github"></i>
                        <span>GitHub</span>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- =============================================================================
         Firebase Integration - Loaded on pages that need it
         ============================================================================= -->
    <?php if ($loadFirebase): ?>
        <script>
            // PHP user context for Firebase operations
            window.FIREBASE_USER_CONTEXT = <?php echo json_encode($firebaseUserContext); ?>;
        </script>
        <script src="<?php echo ASSETS_URL; ?>/js/firebase.js"></script>
        
        <!-- Load Feedback module on relevant pages -->
        <?php
        $currentPage = basename($_SERVER['PHP_SELF']);
        $feedbackPages = array('submit_feedback.php', 'view_feedback.php', 'dashboard.php');
        $isFeedbackPage = in_array($currentPage, $feedbackPages);
        ?>
        <?php if ($isFeedbackPage): ?>
            <script src="<?php echo ASSETS_URL; ?>/js/feedback-firebase.js"></script>
            <script>
                // Initialize Feedback module with user context
                document.addEventListener('DOMContentLoaded', function()
                {
                    if (typeof window.Feedback !== 'undefined' && window.FIREBASE_USER_CONTEXT)
                    {
                        window.Feedback.init(window.FIREBASE_USER_CONTEXT);
                    }
                });
            </script>
        <?php endif; ?>
    <?php endif; ?>

    <!-- External JavaScript files -->
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>

    <!-- Role-specific JavaScript files -->
    <?php
    if (isset($_SESSION['account_type']))
    {
        $accountType = $_SESSION['account_type'];

        if ($accountType === 'student' || $accountType === 'standard')
        {
            echo '<script src="' . ASSETS_URL . '/js/student.js"></script>';
            echo '<script src="' . ASSETS_URL . '/js/cart.js"></script>';
        }
        elseif ($accountType === 'vendor')
        {
            echo '<script src="' . ASSETS_URL . '/js/vendor.js"></script>';
        }
        elseif ($accountType === 'admin')
        {
            echo '<script src="' . ASSETS_URL . '/js/admin.js"></script>';
        }
    }
    ?>
</body>
</html>