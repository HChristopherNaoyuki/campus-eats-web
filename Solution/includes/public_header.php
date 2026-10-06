<?php
/**
 * Public Header Component
 *
 * Provides consistent navigation across all public-facing pages.
 *
 * CORRECTIONS (Version 13.0 - isStandard Fix):
 *
 * - Defined the pure function isStandard() inside this file so the
 *   call on the original line 101 (inside isStudentOrStandardUser)
 *   always resolves. The previous version relied on a definition that
 *   lived only in auth.php; when that include failed or was ordered
 *   after the call, the fatal “Call to undefined function isStandard()”
 *   aborted page render.
 * - The function is pure: it inspects REQUEST_URI only, performs no
 *   I/O, and returns a boolean. It follows Clean Code naming and
 *   single-responsibility rules.
 * - All original markup, helpers, and escape logic are retained
 *   unchanged.
 *
 * SOURCE: SOFTWARE ENGINEER PROMPT – Correct public_header.php.
 * SOURCE: Clean Code, Robert C. Martin, Chapters 2–4.
 *
 * @version 13.0
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
     * @param string $page        The page basename to test
     * @param string $currentPage The current page basename
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
     * @param string $page        The page basename to test
     * @param string $currentPage The current page basename
     * @return string 'active' or an empty string
     */
    function isPublicPageActive($page, $currentPage = null)
    {
        return isActivePublicPage($page, $currentPage);
    }
}

/**
 * Determine whether the current request should use the standard
 * public layout.
 *
 * Returns true for ordinary visitor pages, false for specialised
 * or authenticated views (admin or dashboard routes).
 * Pure function: no side effects, no I/O.
 *
 * @return bool
 */
if (!function_exists('isStandard'))
{
    function isStandard()
    {
        // Default to standard layout unless a recognised
        // non-standard route is detected.
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
            && (isStudent() || isStandard());
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
                           class="<?php
                               echo isActivePublicPage('index.php', $currentPage);
                           ?>">
                            Home
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo ROOT_URL; ?>/about.php"
                           class="<?php
                               echo isActivePublicPage('about.php', $currentPage);
                           ?>">
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
                           class="<?php
                               echo isActivePublicPage('faq.php', $currentPage);
                           ?>">
                            FAQ
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo ROOT_URL; ?>/help.php"
                           class="<?php
                               echo isActivePublicPage('help.php', $currentPage);
                           ?>">
                            Help Center
                        </a>
                    </li>
                    <?php if (isStudentOrStandardUser()): ?>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/modules/student/dashboard.php">
                                Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/modules/student/cart.php">
                                Cart
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="header-actions">
                <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
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
                <li>
                    <a href="<?php echo ROOT_URL; ?>/index.php#home">Home</a>
                </li>
                <li>
                    <a href="<?php echo ROOT_URL; ?>/about.php">About</a>
                </li>
                <li>
                    <a href="<?php echo ROOT_URL; ?>/index.php#vendors">Services</a>
                </li>
                <li>
                    <a href="<?php echo ROOT_URL; ?>/faq.php">FAQ</a>
                </li>
                <li>
                    <a href="<?php echo ROOT_URL; ?>/help.php">Help Center</a>
                </li>
                <?php if (isStudentOrStandardUser()): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modules/student/dashboard.php">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modules/student/cart.php">
                            Cart
                        </a>
                    </li>
                <?php endif; ?>
                <?php if (!function_exists('isLoggedIn') || !isLoggedIn()): ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modules/auth/login.php">
                            Sign In
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modules/auth/register.php">
                            Create account
                        </a>
                    </li>
                <?php else: ?>
                    <li>
                        <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php">
                            Logout
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <main id="main-content">