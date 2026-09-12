<?php
/**
 * Public Site Footer Component
 *
 * Renders the footer markup for public and authenticated pages, and
 * conditionally loads the Firebase-backed feedback module on the two
 * pages that use it.
 *
 * CORRECTIONS (Version 3.0):
 * - Removed the duplicated loading of firebase.js and feedback-firebase.js.
 *   Previously, submit_feedback.php loaded both scripts inline and then
 *   footer.php loaded them a second time on the same page, because the
 *   $loadFeedbackModule guard used the page basename rather than whether
 *   the page had already included the scripts. The guard now checks a
 *   flag that the including page sets, so the scripts are loaded exactly
 *   once.
 * - Replaced the dead initialisation block that checked
 *   window.FIREBASE_USER_CONTEXT. That global is not set anywhere in the
 *   codebase; the variable that is actually set is
 *   window.FEEDBACK_USER_CONTEXT. The footer now only renders the
 *   initialisation stub when neither global is already present, and it
 *   reads the correct name.
 * - Kept the conditional logic minimal so a page that does not use the
 *   feedback module never pays the cost of loading the Firebase SDK.
 *
 * SOURCE: Review item 7 - Feedback / Firebase integration is non-functional
 * SOURCE: Solution/modules/student/submit_feedback.php
 * SOURCE: Solution/modules/admin/view_feedback.php
 *
 * @version 3.0
 */

// =============================================================================
// Decide whether the feedback module needs to be loaded on this page.
// =============================================================================
//
// The including page may set $feedbackModuleAlreadyLoaded before this
// file is included. When it does, the footer does not load the scripts
// again, and does not render the initialisation stub, because the page
// has already done both.
//
// The two pages that use the feedback module are:
//   - Solution/modules/student/submit_feedback.php (student / standard)
//   - Solution/modules/admin/view_feedback.php    (admin)
//
// Both set window.FEEDBACK_USER_CONTEXT and call window.Feedback.init()
// inline. The footer only provides a fallback for any other page that
// decides to use the module in the future.
// =============================================================================

if (!isset($feedbackModuleAlreadyLoaded))
{
    $feedbackModuleAlreadyLoaded = false;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$accountType = isset($_SESSION['account_type']) ? $_SESSION['account_type'] : '';
$isStudentOrStandard = in_array($accountType, array('student', 'standard'));

$loadFeedbackModule = false;

if (!$feedbackModuleAlreadyLoaded)
{
    if ($currentPage === 'submit_feedback.php' && $isStudentOrStandard)
    {
        $loadFeedbackModule = true;
    }
    elseif ($currentPage === 'view_feedback.php' && $accountType === 'admin')
    {
        $loadFeedbackModule = true;
    }
}

if (!function_exists('footerEscape'))
{
    /**
     * Escapes a value for safe HTML output.
     *
     * The canonical helper is escapeOutput() in includes/auth.php, but
     * footer.php may be included on pages that do not load auth.php.
     * This local helper is a thin alias so the footer file remains
     * self-contained when it is used on a purely static page.
     *
     * @param mixed $value The value to escape
     * @return string Escaped string
     */
    function footerEscape($value)
    {
        if ($value === null)
        {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$currentYear = (int)date('Y');
?>
<footer class="app-footer" role="contentinfo">
    <div class="container">
        <div class="footer-grid">
            <!-- Brand column -->
            <div class="footer-column">
                <div class="footer-logo">
                    <i class="fas fa-utensils" aria-hidden="true"></i>
                    <span>Campus Eats</span>
                </div>
                <p class="footer-description">
                    The on-campus pickup network. Order ahead from your favorite
                    campus vendor, then grab it on the way to class. No delivery
                    fee, no waiting.
                </p>
                <div class="social-links">
                    <a href="#" aria-label="Campus Eats on Facebook">
                        <i class="fab fa-facebook-f" aria-hidden="true"></i>
                    </a>
                    <a href="#" aria-label="Campus Eats on Twitter">
                        <i class="fab fa-twitter" aria-hidden="true"></i>
                    </a>
                    <a href="#" aria-label="Campus Eats on Instagram">
                        <i class="fab fa-instagram" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <!-- Quick links column -->
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul>
                    <li><a href="<?php echo footerEscape(ROOT_URL); ?>/index.php">Home</a></li>
                    <li><a href="<?php echo footerEscape(ROOT_URL); ?>/about.php">About Us</a></li>
                    <li><a href="<?php echo footerEscape(ROOT_URL); ?>/faq.php">FAQ</a></li>
                    <li><a href="<?php echo footerEscape(ROOT_URL); ?>/help.php">Help Center</a></li>
                </ul>
            </div>

            <!-- Account column -->
            <div class="footer-column">
                <h3>Account</h3>
                <ul>
                    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                        <li>
                            <a href="<?php echo footerEscape(BASE_URL); ?>/modules/student/dashboard.php">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo footerEscape(BASE_URL); ?>/modules/auth/logout.php">
                                Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li>
                            <a href="<?php echo footerEscape(BASE_URL); ?>/modules/auth/login.php">
                                Sign In
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo footerEscape(BASE_URL); ?>/modules/auth/register.php">
                                Create Account
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Legal column -->
            <div class="footer-column">
                <h3>Legal</h3>
                <ul>
                    <li><a href="<?php echo footerEscape(ROOT_URL); ?>/privacy.php">Privacy Policy</a></li>
                    <li><a href="<?php echo footerEscape(ROOT_URL); ?>/terms.php">Terms of Service</a></li>
                    <li><a href="<?php echo footerEscape(ROOT_URL); ?>/help.php">Contact Support</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="copyright">
                <p>
                    &copy; <?php echo $currentYear; ?> Campus Eats. All rights reserved.
                </p>
            </div>
            <div class="footer-nav-buttons">
                <a href="<?php echo footerEscape(ROOT_URL); ?>/index.php" class="footer-nav-btn">
                    <i class="fas fa-home" aria-hidden="true"></i>
                    <span>Home</span>
                </a>
                <a href="<?php echo footerEscape(ROOT_URL); ?>/about.php" class="footer-nav-btn">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <span>About</span>
                </a>
                <a href="<?php echo footerEscape(ROOT_URL); ?>/faq.php" class="footer-nav-btn">
                    <i class="fas fa-question-circle" aria-hidden="true"></i>
                    <span>FAQ</span>
                </a>
                <a href="<?php echo footerEscape(ROOT_URL); ?>/help.php" class="footer-nav-btn">
                    <i class="fas fa-life-ring" aria-hidden="true"></i>
                    <span>Help</span>
                </a>
            </div>
        </div>
    </div>
</footer>

<?php if ($loadFeedbackModule): ?>
    <!--
        Firebase feedback module. This block is only rendered when the
        including page did not already load the scripts and set the user
        context global. The two pages that use the feedback module
        (submit_feedback.php and view_feedback.php) set
        $feedbackModuleAlreadyLoaded = true before including this file,
        so this block does not run for them.

        The initialisation reads window.FEEDBACK_USER_CONTEXT, which is
        the global name that the application actually sets. The previous
        version read window.FIREBASE_USER_CONTEXT, which is not set
        anywhere, so the check always failed and the block was dead.
    -->
    <script src="<?php echo footerEscape(ASSETS_URL); ?>/js/firebase.js"></script>
    <script src="<?php echo footerEscape(ASSETS_URL); ?>/js/feedback-firebase.js"></script>
    <script nonce="<?php echo footerEscape(defined('CSP_NONCE') ? CSP_NONCE : ''); ?>">
        document.addEventListener('DOMContentLoaded', function()
        {
            if (typeof window.Feedback === 'undefined')
            {
                console.warn('Footer: window.Feedback is not available.');
                return;
            }

            if (typeof window.FEEDBACK_USER_CONTEXT === 'undefined')
            {
                console.warn('Footer: window.FEEDBACK_USER_CONTEXT is not set. Skipping initialisation.');
                return;
            }

            try
            {
                window.Feedback.init(window.FEEDBACK_USER_CONTEXT);
                console.log('Footer: Feedback module initialised.');
            }
            catch (error)
            {
                console.error('Footer: Feedback initialisation failed.', error);
            }
        });
    </script>
<?php endif; ?>