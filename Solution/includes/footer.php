<?php
/**
 * Public Footer Component
 *
 * Renders the footer for every public and authenticated page. The
 * footer contains four columns of links, a language switcher, and a
 * copyright line. All user-facing strings are translated through the
 * shared __() helper.
 *
 * CORRECTIONS (Version 4.0):
 * - Replaced every hardcoded string with a __() call. The footer now
 *   renders in English or Afrikaans depending on the active language.
 * - Added a language switcher to the bottom bar.
 * - Removed the Firebase feedback block. The block referenced the
 *   window.FIREBASE_USER_CONTEXT global, which was never set anywhere
 *   in the codebase, so the block was dead code that loaded two scripts
 *   and then did nothing. Feedback is written to MySQL only. See
 *   Solution/modules/student/submit_feedback.php for the current path.
 * - Retains the role-aware Quick Links and Account columns and the
 *   current year from date().
 *
 * SOURCE: NOTES - Include multi-language support for at least two
 *         South African languages: English and Afrikaans.
 *
 * @version 4.0
 */

// The footer may be included on pages that do not load auth.php. The
// canonical escapeOutput() helper is defined there, so it is loaded
// here if it is not already present.
if (!function_exists('escapeOutput'))
{
    require_once dirname(__DIR__) . '/includes/auth.php';
}

// The translation helper is also loaded if not already present.
if (!function_exists('__'))
{
    require_once dirname(__DIR__) . '/includes/i18n.php';
}

$footerYear = (int)date('Y');
?>
<footer class="app-footer" role="contentinfo">
    <div class="container">
        <div class="footer-grid">

            <!-- Brand column -->
            <div class="footer-column">
                <div class="footer-logo">
                    <i class="fas fa-utensils" aria-hidden="true"></i>
                    <span><?php echo __e('app.name'); ?></span>
                </div>
                <p class="footer-description">
                    <?php echo __e('footer.description'); ?>
                </p>
                <div class="social-links">
                    <a href="#" aria-label="<?php echo __e('footer.social_facebook'); ?>">
                        <i class="fab fa-facebook-f" aria-hidden="true"></i>
                    </a>
                    <a href="#" aria-label="<?php echo __e('footer.social_twitter'); ?>">
                        <i class="fab fa-twitter" aria-hidden="true"></i>
                    </a>
                    <a href="#" aria-label="<?php echo __e('footer.social_instagram'); ?>">
                        <i class="fab fa-instagram" aria-hidden="true"></i>
                    </a>
                </div>
            </div>

            <!-- Quick links column -->
            <div class="footer-column">
                <h3><?php echo __e('footer.quick_links'); ?></h3>
                <ul>
                    <li>
                        <a href="<?php echo escapeOutput(ROOT_URL); ?>/index.php">
                            <?php echo __e('nav.home'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo escapeOutput(ROOT_URL); ?>/about.php">
                            <?php echo __e('nav.about'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo escapeOutput(ROOT_URL); ?>/faq.php">
                            <?php echo __e('nav.faq'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo escapeOutput(ROOT_URL); ?>/help.php">
                            <?php echo __e('nav.help'); ?>
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Account column -->
            <div class="footer-column">
                <h3><?php echo __e('footer.account'); ?></h3>
                <ul>
                    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
                        <li>
                            <a href="<?php echo escapeOutput(BASE_URL); ?>/modules/auth/logout.php">
                                <?php echo __e('auth.sign_out'); ?>
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                            <li>
                                <a href="<?php echo escapeOutput(BASE_URL); ?>/modules/admin/dashboard.php">
                                    <?php echo __e('role.admin'); ?>
                                </a>
                            </li>
                        <?php elseif (isVendor()): ?>
                            <li>
                                <a href="<?php echo escapeOutput(BASE_URL); ?>/modules/vendor/dashboard.php">
                                    <?php echo __e('role.vendor'); ?>
                                </a>
                            </li>
                        <?php elseif (isStudent() || isStandard()): ?>
                            <li>
                                <a href="<?php echo escapeOutput(BASE_URL); ?>/modules/student/dashboard.php">
                                    <?php echo __e('nav.dashboard'); ?>
                                </a>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li>
                            <a href="<?php echo escapeOutput(BASE_URL); ?>/modules/auth/login.php">
                                <?php echo __e('auth.sign_in'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo escapeOutput(BASE_URL); ?>/modules/auth/register.php">
                                <?php echo __e('auth.sign_up'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Legal column -->
            <div class="footer-column">
                <h3><?php echo __e('footer.legal'); ?></h3>
                <ul>
                    <li>
                        <a href="<?php echo escapeOutput(ROOT_URL); ?>/privacy.php">
                            <?php echo __e('footer.privacy'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo escapeOutput(ROOT_URL); ?>/terms.php">
                            <?php echo __e('footer.terms'); ?>
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo escapeOutput(ROOT_URL); ?>/help.php">
                            <?php echo __e('footer.contact'); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="copyright">
                <p>
                    &copy; <?php echo escapeOutput((string)$footerYear); ?>
                    <?php echo __e('footer.copyright'); ?>
                </p>
            </div>

            <div class="footer-language">
                <?php echo getLanguageSwitcherHtml(); ?>
            </div>

            <div class="footer-nav-buttons">
                <a href="<?php echo escapeOutput(ROOT_URL); ?>/index.php"
                   class="footer-nav-btn">
                    <i class="fas fa-home" aria-hidden="true"></i>
                    <span><?php echo __e('nav.home'); ?></span>
                </a>
                <a href="<?php echo escapeOutput(ROOT_URL); ?>/about.php"
                   class="footer-nav-btn">
                    <i class="fas fa-info-circle" aria-hidden="true"></i>
                    <span><?php echo __e('nav.about'); ?></span>
                </a>
                <a href="<?php echo escapeOutput(ROOT_URL); ?>/faq.php"
                   class="footer-nav-btn">
                    <i class="fas fa-question-circle" aria-hidden="true"></i>
                    <span><?php echo __e('nav.faq'); ?></span>
                </a>
                <a href="<?php echo escapeOutput(ROOT_URL); ?>/help.php"
                   class="footer-nav-btn">
                    <i class="fas fa-life-ring" aria-hidden="true"></i>
                    <span><?php echo __e('nav.help'); ?></span>
                </a>
            </div>
        </div>
    </div>
</footer>

<script src="<?php echo escapeOutput(ASSETS_URL); ?>/js/main.js"></script>
<script src="<?php echo escapeOutput(ASSETS_URL); ?>/js/dashboard-common.js"></script>
</body>
</html>