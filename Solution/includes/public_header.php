<?php
/**
 * Public Header Include
 *
 * Renders the common navigation header used by every public-facing
 * page (index, about, faq, help, login, register, privacy, terms).
 * The header adapts its menu items according to the visitor’s
 * authentication state and role.
 *
 * CORRECTIONS (Version 7.0 - Resilience Fix):
 *
 * - Defined a pure, defensive isStandard() helper (and the related
 *   role helpers) when they are not already present. The previous
 *   version called isStandard() without a function_exists guard,
 *   which produced the fatal “Call to undefined function isStandard()”
 *   on line 101 whenever auth.php had not been loaded. The new
 *   definitions are pure boolean functions that return false when
 *   no session exists, satisfying Clean Code single-responsibility
 *   and naming rules.
 * - isStudentOrStandardUser() now safely composes the guarded helpers.
 * - No other behavioural change; the rendered markup remains identical.
 *
 * SOURCE: SOFTWARE ENGINEER PROMPT – Campus Eats Resilience Fixes.
 * SOURCE: Clean Code, Robert C. Martin, Chapters 2–4.
 *
 * @version 7.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

// Ensure the authentication helpers are available. When auth.php has
// already been required the functions below are skipped.
if (file_exists(BASE_PATH . '/includes/auth.php'))
{
    require_once BASE_PATH . '/includes/auth.php';
}

// =============================================================================
// Defensive Role Helpers
// =============================================================================
//
// These pure functions return a boolean that indicates the current
// visitor’s role. They are defined only when the primary definitions
// in auth.php are absent, guaranteeing that public_header.php never
// throws an undefined-function error.

if (!function_exists('isLoggedIn'))
{
    /**
     * Returns true when a valid authenticated session exists.
     *
     * @return bool
     */
    function isLoggedIn()
    {
        return isset($_SESSION['user_id'])
            && !empty($_SESSION['user_id'])
            && isset($_SESSION['account_type']);
    }
}

if (!function_exists('getCurrentUserRole'))
{
    /**
     * Returns the account_type stored in the session, or an empty
     * string when the visitor is not authenticated.
     *
     * @return string
     */
    function getCurrentUserRole()
    {
        return isset($_SESSION['account_type'])
            ? (string)$_SESSION['account_type']
            : '';
    }
}

if (!function_exists('isStudent'))
{
    /**
     * Returns true when the current visitor has the student role.
     *
     * @return bool
     */
    function isStudent()
    {
        return getCurrentUserRole() === 'student';
    }
}

if (!function_exists('isStandard'))
{
    /**
     * Returns true when the current visitor has the standard role.
     *
     * This pure function exists solely to answer a single question:
     * “Is the visitor a standard-role user?” It performs no side
     * effects and never throws. When no session exists the function
     * returns false.
     *
     * @return bool True when the session role is exactly “standard”
     */
    function isStandard()
    {
        return getCurrentUserRole() === 'standard';
    }
}

if (!function_exists('isVendor'))
{
    /**
     * Returns true when the current visitor has the vendor role.
     *
     * @return bool
     */
    function isVendor()
    {
        return getCurrentUserRole() === 'vendor';
    }
}

if (!function_exists('isAdmin'))
{
    /**
     * Returns true when the current visitor has the admin role.
     *
     * @return bool
     */
    function isAdmin()
    {
        return getCurrentUserRole() === 'admin';
    }
}

if (!function_exists('isStudentOrStandardUser'))
{
    /**
     * Returns true when a student or standard user is logged in.
     *
     * @return bool
     */
    function isStudentOrStandardUser()
    {
        return isLoggedIn()
            && (isStudent() || isStandard());
    }
}

// =============================================================================
// Active-page helpers (unchanged)
// =============================================================================

if (!function_exists('isActivePublicPage'))
{
    /**
     * Returns the CSS class “active” when the supplied page matches
     * the current script name.
     *
     * @param string      $page        The page filename to test
     * @param string|null $currentPage Optional override of the current page
     * @return string “active” or an empty string
     */
    function isActivePublicPage($page, $currentPage = null)
    {
        if ($currentPage === null)
        {
            $currentPage = basename($_SERVER['PHP_SELF']);
        }

        return ($currentPage === $page) ? 'active' : '';
    }
}

if (!function_exists('isPublicPageActive'))
{
    /**
     * Alias kept for backward compatibility.
     *
     * @param string      $page
     * @param string|null $currentPage
     * @return string
     */
    function isPublicPageActive($page, $currentPage = null)
    {
        return isActivePublicPage($page, $currentPage);
    }
}

// =============================================================================
// Current page and CSRF token for the meta tag
// =============================================================================

$currentPage = basename($_SERVER['PHP_SELF']);

$csrfToken = '';

if (session_status() === PHP_SESSION_ACTIVE && function_exists('getCsrfToken'))
{
    $csrfToken = getCsrfToken();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if ($csrfToken !== ''): ?>
        <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo defined('ASSETS_URL') ? ASSETS_URL : '/Solution/assets'; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo defined('ASSETS_URL') ? ASSETS_URL : '/Solution/assets'; ?>/css/public.css">
</head>
<body>
<header class="public-header">
    <div class="container header-inner">
        <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/index.php" class="logo">
            <img src="<?php echo defined('ASSETS_URL') ? ASSETS_URL : '/Solution/assets'; ?>/images/logo.png"
                 alt="Campus Eats" height="40">
            <span>Campus Eats</span>
        </a>

        <nav class="public-nav" aria-label="Main navigation">
            <ul>
                <li>
                    <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/index.php"
                       class="<?php echo isActivePublicPage('index.php', $currentPage); ?>">
                        Home
                    </a>
                </li>
                <li>
                    <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/about.php"
                       class="<?php echo isActivePublicPage('about.php', $currentPage); ?>">
                        About
                    </a>
                </li>
                <li>
                    <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/faq.php"
                       class="<?php echo isActivePublicPage('faq.php', $currentPage); ?>">
                        FAQ
                    </a>
                </li>
                <li>
                    <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/help.php"
                       class="<?php echo isActivePublicPage('help.php', $currentPage); ?>">
                        Help
                    </a>
                </li>

                <?php if (isStudentOrStandardUser()): ?>
                    <li>
                        <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/modules/student/dashboard.php">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/modules/student/cart.php">
                            Cart
                        </a>
                    </li>
                <?php elseif (isVendor()): ?>
                    <li>
                        <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/modules/vendor/dashboard.php">
                            Vendor Dashboard
                        </a>
                    </li>
                <?php elseif (isAdmin()): ?>
                    <li>
                        <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/modules/admin/dashboard.php">
                            Admin Dashboard
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="header-actions">
            <?php if (isLoggedIn()): ?>
                <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/modules/auth/logout.php"
                   class="btn btn-outline">
                    Logout
                </a>
            <?php else: ?>
                <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/modules/auth/login.php"
                   class="btn btn-outline">
                    Sign in
                </a>
                <a href="<?php echo defined('ROOT_URL') ? ROOT_URL : ''; ?>/modules/auth/register.php"
                   class="btn btn-primary">
                    Create account
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>