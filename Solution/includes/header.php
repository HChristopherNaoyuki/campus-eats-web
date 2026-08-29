<?php
/**
 * Global Header Include File
 *
 * This file contains the HTML header and navigation for all pages.
 * It must be included at the top of every page after session start.
 *
 * CORRECTIONS (Version 4.0):
 * - Added constants.php inclusion to fix undefined constant errors
 * - Uses getCsrfToken() instead of generateCsrfToken()
 * - Prevents token regeneration on every page load
 * - All output is properly escaped
 *
 * @version 4.0
 */

// Load required dependencies to define constants
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php';

// Get CSRF token without regenerating on every page load
$csrfToken = getCsrfToken();

// Helper function for safe output escaping
function escapeOutput($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title>Campus Eats - Campus Food Ordering System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <?php
    if (isset($_SESSION['account_type']))
    {
        if ($_SESSION['account_type'] === 'student')
        {
            echo '<link rel="stylesheet" href="' . ASSETS_URL . '/css/student.css">';
        }
        elseif ($_SESSION['account_type'] === 'vendor')
        {
            echo '<link rel="stylesheet" href="' . ASSETS_URL . '/css/vendor.css">';
        }
        elseif ($_SESSION['account_type'] === 'admin')
        {
            echo '<link rel="stylesheet" href="' . ASSETS_URL . '/css/admin.css">';
        }
    }
    ?>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo-container">
                    <a href="<?php echo ROOT_URL; ?>/index.php" class="logo-text">
                        <i class="fas fa-utensils"></i>
                        Campus Eats
                    </a>
                </div>
                <nav aria-label="Main navigation">
                    <ul>
                        <li><a href="<?php echo ROOT_URL; ?>/index.php#home">Home</a></li>
                        <li><a href="<?php echo ROOT_URL; ?>/index.php#vendors">Vendors</a></li>
                        <li><a href="<?php echo ROOT_URL; ?>/about.php">About</a></li>
                        <li><a href="<?php echo ROOT_URL; ?>/faq.php">FAQ</a></li>
                        <li><a href="<?php echo ROOT_URL; ?>/help.php">Help</a></li>

                        <?php if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                            <?php if ($_SESSION['account_type'] === 'student'): ?>
                                <li><a href="<?php echo BASE_URL; ?>/modules/student/dashboard.php">Dashboard</a></li>
                                <li><a href="<?php echo BASE_URL; ?>/modules/student/cart.php">Cart</a></li>
                            <?php elseif ($_SESSION['account_type'] === 'vendor'): ?>
                                <li><a href="<?php echo BASE_URL; ?>/modules/vendor/dashboard.php">Vendor Panel</a></li>
                            <?php elseif ($_SESSION['account_type'] === 'admin'): ?>
                                <li><a href="<?php echo BASE_URL; ?>/modules/admin/dashboard.php">Admin Panel</a></li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="auth-buttons">
                    <?php if (isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                        <span class="welcome-text">
                            <i class="fas fa-user-circle"></i>
                            <?php echo escapeOutput($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User'); ?>
                        </span>
                        <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php" class="btn btn-outline">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    <?php else: ?>
                        <a href="<?php echo BASE_URL; ?>/modules/auth/login.php" class="btn btn-outline">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <a href="<?php echo BASE_URL; ?>/modules/auth/register.php" class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Sign Up
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
    <main>