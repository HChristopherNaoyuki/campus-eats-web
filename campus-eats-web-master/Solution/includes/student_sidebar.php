<?php
/**
 * Student/Standard Sidebar Navigation Component
 *
 * This file contains the left-side vertical sidebar navigation for both
 * Student and Standard user pages.
 *
 * CORRECTIONS (Version 15.0 - Visual Parity with Mockups 21-24.png):
 * - Refined spacing and typography to match the provided mockups
 * - Uses internal CSS to ensure self-contained styling
 * - Ensured consistent padding, margins, and alignment for all elements
 * - Added active state with orange left border matching mockups
 * - Added cart badge for shopping cart
 * - Maintained responsive behavior across all screen sizes
 *
 * SOURCE: Mockups - 21.png, 22.png, 23.png, 24.png
 *
 * @version 15.0
 */

// Ensure constants are loaded
if (!defined('BASE_URL'))
{
    require_once dirname(__DIR__) . '/config/constants.php';
}

// Determine current page for active state highlighting
$currentPage = basename($_SERVER['PHP_SELF']);

// Get user name from session
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';

// Determine user role for display
$userRole = $_SESSION['account_type'] ?? 'student';
$roleDisplay = 'STUDENT';
$roleIcon = 'fa-user-graduate';

function studentSidebarEscape($string)
{
    if ($string === null)
    {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function isStudentSidebarActive($page)
{
    global $currentPage;
    return ($currentPage == $page) ? 'active' : '';
}

function getStudentAriaCurrent($page)
{
    global $currentPage;
    return ($currentPage == $page) ? 'page' : 'false';
}
?>
<!-- Student Sidebar - Semantic HTML5 aside element -->
<style>
    /* =============================================================================
       Student Sidebar Internal Styles - Matches student mockups
       SOURCE: Mockups - 21.png, 22.png, 23.png, 24.png
       ============================================================================= */
    .sidebar-student
    {
        width: 260px;
        flex-shrink: 0;
        background: linear-gradient(180deg, #1C1C1E 0%, #1a1a2e 100%);
        color: #AEAEB2;
        height: 100vh;
        position: sticky;
        top: 0;
        overflow-y: auto;
        overflow-x: hidden;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', Arial, sans-serif;
    }

    .sidebar-student::-webkit-scrollbar
    {
        width: 4px;
    }
    .sidebar-student::-webkit-scrollbar-track
    {
        background: rgba(255, 255, 255, 0.05);
    }
    .sidebar-student::-webkit-scrollbar-thumb
    {
        background: #FF9500;
        border-radius: 9999px;
    }

    /* Sidebar Header */
    .sidebar-student-header
    {
        padding: 24px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        text-align: center;
        flex-shrink: 0;
    }

    .sidebar-student-header .logo a
    {
        color: white;
        text-decoration: none;
        font-size: 1.125rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .sidebar-student-header .logo a i
    {
        color: #FF9500;
        font-size: 1.125rem;
    }

    .sidebar-student-role
    {
        font-size: 0.625rem;
        color: #AEAEB2;
        margin-top: 8px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .sidebar-student-role i
    {
        font-size: 0.75rem;
        color: #FF9500;
    }

    .sidebar-student-user
    {
        font-size: 0.75rem;
        color: #AEAEB2;
        margin-top: 4px;
        text-align: center;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    /* Sidebar Navigation */
    .sidebar-student-nav
    {
        flex: 1;
        padding: 12px 0;
    }

    .sidebar-student-nav a
    {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        color: #AEAEB2;
        text-decoration: none;
        transition: all 0.15s ease;
        font-size: 0.8125rem;
        font-weight: 500;
        border-radius: 12px;
        margin: 0 8px 4px 8px;
        position: relative;
        min-height: 44px;
    }

    .sidebar-student-nav a i
    {
        width: 20px;
        text-align: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    /* Active State - Orange left border (matches mockups) */
    .sidebar-student-nav a::before
    {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 3px;
        height: 0;
        background: #FF9500;
        border-radius: 0 9999px 9999px 0;
        transition: height 0.15s ease;
    }

    .sidebar-student-nav a:hover
    {
        background: rgba(255, 149, 0, 0.12);
        color: #FF9500;
    }

    .sidebar-student-nav a:hover::before
    {
        height: 60%;
    }

    .sidebar-student-nav a.active
    {
        background: rgba(255, 149, 0, 0.2);
        color: white;
    }

    .sidebar-student-nav a.active::before
    {
        height: 70%;
        background: #FF9500;
    }

    .sidebar-student-nav a.active i
    {
        color: #FF9500;
    }

    /* Cart Badge */
    .cart-badge
    {
        display: none;
        background: #FF9500;
        color: white;
        border-radius: 9999px;
        padding: 0.1rem 0.5rem;
        font-size: 0.65rem;
        margin-left: auto;
        min-width: 20px;
        text-align: center;
    }

    /* Sidebar Footer */
    .sidebar-student-footer
    {
        padding: 12px 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        flex-shrink: 0;
    }

    .sidebar-student-footer .logout-link
    {
        display: flex;
        align-items: center;
        gap: 12px;
        color: #AEAEB2;
        text-decoration: none;
        padding: 12px 16px;
        transition: all 0.15s ease;
        border-radius: 12px;
        font-size: 0.8125rem;
        font-weight: 500;
        width: 100%;
        box-sizing: border-box;
        min-height: 44px;
    }

    .sidebar-student-footer .logout-link i
    {
        width: 20px;
        text-align: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .sidebar-student-footer .logout-link:hover
    {
        background: rgba(220, 53, 69, 0.15);
        color: #FF3B30;
    }

    /* Responsive - Mobile */
    @media (max-width: 768px)
    {
        .sidebar-student
        {
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            height: 100vh;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
            box-shadow: none;
            z-index: 1010;
        }

        .sidebar-student.mobile-open
        {
            transform: translateX(0);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-student.mobile-open::after
        {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: 999;
            pointer-events: auto;
        }
    }
</style>

<aside class="sidebar-student" aria-label="Student Navigation Sidebar" role="navigation">
    <!-- Sidebar Header -->
    <div class="sidebar-student-header">
        <div class="logo">
            <a href="dashboard.php" aria-label="Dashboard Home">
                <i class="fas fa-utensils" aria-hidden="true"></i>
                <span>Campus Eats</span>
            </a>
        </div>
        <p class="sidebar-student-role">
            <i class="fas <?php echo $roleIcon; ?>" aria-hidden="true"></i>
            <?php echo studentSidebarEscape($roleDisplay); ?>
        </p>
        <p class="sidebar-student-user">
            <?php echo studentSidebarEscape($userName); ?>
        </p>
    </div>

    <!-- Sidebar Navigation Menu -->
    <nav class="sidebar-student-nav" aria-label="Student Menu">
        <a href="dashboard.php"
           class="<?php echo isStudentSidebarActive('dashboard.php'); ?>"
           aria-label="Home Dashboard"
           aria-current="<?php echo getStudentAriaCurrent('dashboard.php'); ?>">
            <i class="fas fa-home" aria-hidden="true"></i>
            <span>Home</span>
        </a>

        <a href="order_history.php"
           class="<?php echo isStudentSidebarActive('order_history.php'); ?>"
           aria-label="My Orders History"
           aria-current="<?php echo getStudentAriaCurrent('order_history.php'); ?>">
            <i class="fas fa-receipt" aria-hidden="true"></i>
            <span>My Orders</span>
        </a>

        <a href="cart.php"
           class="<?php echo isStudentSidebarActive('cart.php'); ?>"
           aria-label="Shopping Cart"
           aria-current="<?php echo getStudentAriaCurrent('cart.php'); ?>">
            <i class="fas fa-shopping-cart" aria-hidden="true"></i>
            <span>Cart</span>
            <span class="cart-badge" id="cart-count-badge" style="display: none;">
                0
            </span>
        </a>

        <a href="submit_feedback.php"
           class="<?php echo isStudentSidebarActive('submit_feedback.php'); ?>"
           aria-label="Submit Feedback"
           aria-current="<?php echo getStudentAriaCurrent('submit_feedback.php'); ?>">
            <i class="fas fa-comment-dots" aria-hidden="true"></i>
            <span>Feedback</span>
        </a>
    </nav>

    <!-- Sidebar Footer with Logout Button -->
    <div class="sidebar-student-footer">
        <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php"
           class="logout-link"
           aria-label="Logout from Account">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<script>
    document.addEventListener('DOMContentLoaded', function()
    {
        const cartBadge = document.getElementById('cart-count-badge');

        function updateCartBadge()
        {
            if (typeof cart !== 'undefined' && cart.items)
            {
                const count = cart.getTotalItemCount();
                if (cartBadge)
                {
                    if (count > 0)
                    {
                        cartBadge.textContent = count;
                        cartBadge.style.display = 'inline-block';
                    }
                    else
                    {
                        cartBadge.style.display = 'none';
                    }
                }
            }
        }

        updateCartBadge();

        document.addEventListener('cartUpdated', function()
        {
            updateCartBadge();
        });
    });
</script>