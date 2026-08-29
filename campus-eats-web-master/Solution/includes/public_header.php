<?php
/**
 * Public Header Component - Matching Mockup Navigation
 *
 * Provides consistent navigation across all public-facing pages.
 *
 * CORRECTIONS (Version 10.0 - Visual Parity):
 * - Updated navigation to match mockup 4.png and 5.png
 * - Added active state styling
 * - Improved mobile menu behavior
 * - Added Standard user support in authentication checks
 *
 * @version 10.0
 */

// Load required dependencies
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Set security headers for this public page
setSecurityHeaders();

// Determine current page for active state highlighting
$currentPage = basename($_SERVER['PHP_SELF']);

// Get CSRF token for meta tag if session is started
$csrfToken = '';
if (session_status() === PHP_SESSION_ACTIVE)
{
    $csrfToken = getCsrfToken();
}

/**
 * Helper function for safe output escaping
 */
function publicHeaderEscape($string)
{
    if ($string === null)
    {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Determine if user is logged in and has access to student/standard features
 */
function isStudentOrStandardUser()
{
    return isLoggedIn() && (isStudent() || isStandard());
}

/**
 * Get the current page name for active state detection
 */
function isPublicPageActive($page, $currentPage)
{
    return ($page === $currentPage) ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo publicHeaderEscape($csrfToken); ?>">
    <title>Campus Eats · <?php echo isset($pageTitle) ? publicHeaderEscape($pageTitle) : 'Campus Food Ordering System'; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
</head>
<body>
    <!-- Skip to content link for accessibility -->
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <header class="public-header" role="banner">
        <div class="container">
            <!-- Logo / Brand -->
            <div class="logo">
                <a href="<?php echo ROOT_URL; ?>/index.php" aria-label="Campus Eats Home">
                    <i class="fas fa-utensils" aria-hidden="true"></i>
                    <span>Campus Eats</span>
                </a>
            </div>

            <!-- Main Navigation Menu (Desktop) -->
            <nav class="public-nav" aria-label="Main navigation">
                <ul>
                    <li>
                        <a href="<?php echo ROOT_URL; ?>/index.php#home"
                           class="<?php echo isPublicPageActive('index.php', $currentPage); ?>">
                            Home
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo ROOT_URL; ?>/about.php"
                           class="<?php echo isPublicPageActive('about.php', $currentPage); ?>">
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
                           class="<?php echo isPublicPageActive('faq.php', $currentPage); ?>">
                            FAQ
                        </a>
                    </li>
                    <li>
                        <a href="<?php echo ROOT_URL; ?>/help.php"
                           class="<?php echo isPublicPageActive('help.php', $currentPage); ?>">
                            Help Center
                        </a>
                    </li>
                    <!-- Show dashboard links when logged in -->
                    <?php if (isLoggedIn() && (isStudent() || isStandard())): ?>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/modules/student/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo BASE_URL; ?>/modules/student/cart.php">
                                <i class="fas fa-shopping-cart"></i> Cart
                                <span id="header-cart-badge" class="cart-badge" style="display: none; background: var(--orange); color: white; border-radius: var(--radius-full); padding: 0.1rem 0.5rem; font-size: 0.65rem; margin-left: var(--space-1);">
                                    0
                                </span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <!-- Authentication Buttons -->
            <div class="auth-buttons">
                <?php if (isLoggedIn()): ?>
                    <span class="welcome-text">
                        <i class="fas fa-user-circle" aria-hidden="true"></i>
                        <?php echo publicHeaderEscape($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?>
                        <?php if (isStudent()): ?>
                            <span class="badge badge-success" style="font-size: 0.65rem; margin-left: var(--space-1);">
                                <i class="fas fa-graduation-cap"></i> Student
                            </span>
                        <?php elseif (isStandard()): ?>
                            <span class="badge badge-info" style="font-size: 0.65rem; margin-left: var(--space-1);">
                                <i class="fas fa-user"></i> Standard
                            </span>
                        <?php endif; ?>
                    </span>
                    <?php if (isAdmin()): ?>
                        <a href="<?php echo BASE_URL; ?>/modules/admin/dashboard.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-user-shield"></i> Admin
                        </a>
                    <?php elseif (isVendor()): ?>
                        <a href="<?php echo BASE_URL; ?>/modules/vendor/dashboard.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-store"></i> Vendor
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php" class="btn btn-outline btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                <?php else: ?>
                    <a href="<?php echo BASE_URL; ?>/modules/auth/login.php" class="btn btn-outline">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </a>
                    <a href="<?php echo BASE_URL; ?>/modules/auth/register.php" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Create account
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile Menu Toggle Button -->
            <button class="mobile-menu-toggle" aria-label="Menu" aria-expanded="false">
                <i class="fas fa-bars" aria-hidden="true"></i>
            </button>
        </div>
    </header>

    <!-- Mobile Navigation Menu -->
    <div class="mobile-menu" aria-hidden="true">
        <nav aria-label="Mobile navigation">
            <ul>
                <li><a href="<?php echo ROOT_URL; ?>/index.php#home">Home</a></li>
                <li><a href="<?php echo ROOT_URL; ?>/about.php">About</a></li>
                <li><a href="<?php echo ROOT_URL; ?>/index.php#vendors">Services</a></li>
                <li><a href="<?php echo ROOT_URL; ?>/faq.php">FAQ</a></li>
                <li><a href="<?php echo ROOT_URL; ?>/help.php">Help Center</a></li>
                <?php if (isLoggedIn() && (isStudent() || isStandard())): ?>
                    <li><a href="<?php echo BASE_URL; ?>/modules/student/dashboard.php">Dashboard</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/modules/student/cart.php">Cart</a></li>
                <?php endif; ?>
                <?php if (!isLoggedIn()): ?>
                    <li><a href="<?php echo BASE_URL; ?>/modules/auth/login.php">Sign In</a></li>
                    <li><a href="<?php echo BASE_URL; ?>/modules/auth/register.php">Create account</a></li>
                <?php else: ?>
                    <li><a href="<?php echo BASE_URL; ?>/modules/auth/logout.php">Logout</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <main id="main-content">