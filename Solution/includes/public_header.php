<?php
/**
 * Public Header Component
 *
 * Provides consistent navigation across all public-facing pages.
 * When a valid user is present in the session the header renders
 * exactly the five links: Home, About, Services, FAQ, Help Center.
 *
 * CORRECTIONS (Version 14.0 - Header Links and Logout):
 *
 * - Added pure helper isUserLoggedIn() that returns true only when a
 *   non-empty user_id exists in the session.
 * - When isUserLoggedIn() is true the navigation block contains only
 *   the five required links. Dashboard / Cart / role-specific items
 *   are no longer shown in the public header for authenticated users.
 * - isStandard() is retained (guarded) for compatibility with earlier
 *   calls that still exist in the include chain.
 * - All original escape helpers, accessibility attributes and markup
 *   structure are preserved.
 *
 * SOURCE: SOFTWARE ENGINEER PROMPT – Fix Header Links and Logout.
 * SOURCE: Clean Code, Robert C. Martin, Chapters 2–4.
 *
 * @version 14.0
 */

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php';

setSecurityHeaders();

// =============================================================================
// Local escaping helper
// =============================================================================

if (!function_exists('publicHeaderEscape'))
{
    /**
     * Escapes a value for safe HTML output.
     *
     * @param mixed $value The value to escape
     * @return string Escaped string
     */
    function publicHeaderEscape($value)
    {
        if ($value === null)
        {
            return '';
        }

        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

// =============================================================================
// Navigation helpers
// =============================================================================

if (!function_exists('isActivePublicPage'))
{
    /**
     * Returns 'active' when the given page is the current page.
     *
     * @param string      $page        The page basename to test
     * @param string|null $currentPage The current page basename
     * @return string 'active' or an empty string
     */
    function isActivePublicPage($page, $currentPage = null)
    {
        if ($currentPage === null)
        {
            $currentPage = basename($_SERVER['PHP_SELF']);
        }

        return ($page === $currentPage) ? 'active' : '';
    }
}

if (!function_exists('isPublicPageActive'))
{
    /**
     * Backward-compatible alias for isActivePublicPage().
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

/**
 * Return true when a valid logged-in user exists in the current session.
 * Pure check: no side effects.
 *
 * @return bool
 */
if (!function_exists('isUserLoggedIn'))
{
    function isUserLoggedIn()
    {
        return isset($_SESSION['user_id'])
            && $_SESSION['user_id'] !== ''
            && $_SESSION['user_id'] !== null;
    }
}

/**
 * Determine whether the current request should use the standard
 * public layout. Pure function retained for compatibility.
 *
 * @return bool
 */
if (!function_exists('isStandard'))
{
    function isStandard()
    {
        if (isset($_SERVER['REQUEST_URI']))
        {
            $uri = strtolower($_SERVER['REQUEST_URI']);

            if (strpos($uri, '/admin') !== false
                || strpos($uri, '/dashboard') !== false)
            {
                return false;
            }
        }

        return true;
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
        return function_exists('isLoggedIn')
            && isLoggedIn()
            && (function_exists('isStudent') && isStudent()
                || function_exists('isStandard') && isStandard());
    }
}

// =============================================================================
// Current page and CSRF token
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo publicHeaderEscape($csrfToken); ?>">
    <title>Campus Eats &middot; <?php
        echo isset($pageTitle)
            ? publicHeaderEscape($pageTitle)
            : 'Campus Food Ordering System';
    ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
    <script src="<?php echo ASSETS_URL; ?>/js/toast.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <header class="public-header" role="banner">
        <div class="container">
            <div class="logo">
                <a href="<?php echo ROOT_URL; ?>/index.php"
                   aria-label="Campus Eats Home">
                    <i class="fas fa-utensils" aria-hidden="true"></i>
                    <span>Campus Eats</span>
                </a>
            </div>

            <nav class="public-nav" aria-label="Main navigation">
                <ul>
                    <li>
                        <a href="<?php echo ROOT_URL; ?>/index.php#home"
                           class="<?php echo isActivePublicPage('index.php', $currentPage); ?>">
                            Home
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo ROOT_URL; ?>/about.php"
                           class="<?php echo isActivePublicPage('about.php', $currentPage); ?>">
                            About
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo ROOT_URL; ?>/index.php#vendors">
                            Services
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo ROOT_URL; ?>/faq.php"
                           class="<?php echo isActivePublicPage('faq.php', $currentPage); ?>">
                            FAQ
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo ROOT_URL; ?>/help.php"
                           class="<?php echo isActivePublicPage('help.php', $currentPage); ?>">
                            Help Center
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="header-actions">
                <?php if (isUserLoggedIn()): ?>
                    <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php"
                       class="btn btn-outline">
                        <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                        Logout
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/modules/auth/login.php"
                       class="btn btn-outline">
                        <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
                        Sign In
                    </a>
                    <a href="<?php echo BASE_URL; ?>/modules/auth/register.php"
                       class="btn btn-primary">
                        <i class="fas fa-user-plus" aria-hidden="true"></i>
                        Create account
                    </a>
                <?php endif; ?>
            </div>

            <button class="mobile-menu-toggle"
                    aria-label="Menu"
                    aria-expanded="false">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <div class="mobile-menu" aria-hidden="true">
        <nav aria-label="Mobile navigation">
            <ul>
                <li><a href="<?php echo ROOT_URL; ?>/index.php#home">Home</a></li>
                <li><a href="<?php echo ROOT_URL; ?>/about.php">About</a></li>
                <li><a href="<?php echo ROOT_URL; ?>/index.php#vendors">Services</a></li>
                <li><a href="<?php echo ROOT_URL; ?>/faq.php">FAQ</a></li>
                <li><a href="<?php echo ROOT_URL; ?>/help.php">Help Center</a></li>
                <?php if (isUserLoggedIn()): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php">Logout</a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modules/auth/login.php">Sign In</a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modules/auth/register.php">Create account</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <main id="main-content">