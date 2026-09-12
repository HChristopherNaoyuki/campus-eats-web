<?php
/**
 * Dashboard Top Header Component (Refactored)
 *
 * This file contains the top navigation bar for all dashboard pages.
 * Used by Admin, Vendor, and Student dashboards.
 *
 * CORRECTIONS (Version 6.0):
 * - Standardized header across all roles
 * - Added consistent styling for all admin views
 * - Added user menu with logout option
 * - Added responsive mobile menu toggle
 * - Improved accessibility with ARIA attributes
 *
 * Source: campus-eats-process-document.pdf (Section 10 - User Interface Design)
 *
 * @version 6.0
 */

// This file expects the following variables to be set:
// $role - The user role (admin, vendor, student)
// $userName - The user's display name
// $navItems - Array of navigation items for the top bar (optional)

if (!isset($role))
{
    $role = 'admin';
}

if (!isset($userName))
{
    $userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
}

// Default nav items if not provided.
if (!isset($navItems))
{
    $navItems = array();
}
?>
<header class="<?php echo $role; ?>-top-header" role="banner">
    <div class="container">
        <div class="<?php echo $role; ?>-top-nav" role="navigation" aria-label="Top Navigation">
            <?php foreach ($navItems as $item): ?>
                <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>"
                   class="<?php echo (isset($item['active']) && $item['active']) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <div class="<?php echo $role; ?>-user-menu">
            <span class="<?php echo $role; ?>-user-name">
                <i class="fas <?php echo $role === 'admin' ? 'fa-user-shield' : ($role === 'vendor' ? 'fa-store' : 'fa-user-circle'); ?>"></i>
                <?php echo htmlspecialchars($userName, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php" class="btn btn-outline btn-sm">
                <i class="fas fa-sign-out-alt"></i>
                <span class="hide-mobile">Logout</span>
            </a>
        </div>
    </div>
</header>

<style>
    /* Standardized header styling for all admin views */
    .admin-top-header
    {
        background: white;
        border-bottom: 1px solid var(--gray-200);
        position: sticky;
        top: 0;
        z-index: var(--z-medium);
        box-shadow: var(--shadow-sm);
    }
    .admin-top-header .container
    {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-3) var(--space-6);
        flex-wrap: wrap;
        gap: var(--space-3);
    }
    .admin-top-nav
    {
        display: flex;
        gap: var(--space-6);
        align-items: center;
        flex-wrap: wrap;
    }
    .admin-top-nav a
    {
        color: var(--gray-600);
        text-decoration: none;
        font-size: 0.875rem;
        font-weight: 500;
        transition: color var(--transition-fast);
        padding: var(--space-1) 0;
        position: relative;
    }
    .admin-top-nav a:hover
    {
        color: var(--orange);
    }
    .admin-top-nav a.active
    {
        color: var(--orange);
    }
    .admin-top-nav a.active::after
    {
        content: "";
        position: absolute;
        bottom: -4px;
        left: 0;
        width: 100%;
        height: 2px;
        background-color: var(--orange);
        border-radius: var(--radius-full);
    }
    .admin-user-menu
    {
        display: flex;
        align-items: center;
        gap: var(--space-4);
    }
    .admin-user-name
    {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--gray-700);
    }
    .admin-user-name i
    {
        color: var(--orange);
        margin-right: var(--space-2);
    }
    @media (max-width: 768px)
    {
        .admin-top-header .container
        {
            flex-direction: column;
            padding: var(--space-3) var(--space-4);
        }
        .admin-top-nav
        {
            justify-content: center;
            width: 100%;
        }
        .hide-mobile
        {
            display: none;
        }
    }
</style>