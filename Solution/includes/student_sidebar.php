<?php
/**
 * Student and Standard Sidebar Navigation Component
 *
 * Contains the left-side vertical sidebar navigation for all student
 * and standard user pages.
 *
 * CORRECTIONS (Version 16.0):
 * - Added Browse Menu link to menu_browse.php
 * - Added Track Order link to order_tracking.php
 *   Both pages existed but were not reachable from the sidebar.
 * - Uses the shared escapeOutput() helper
 *
 * SOURCE: Issue report - items 5, 23
 *
 * @version 16.0
 */

if (!defined('BASE_URL'))
{
    require_once dirname(__DIR__) . '/config/constants.php';
}

require_once dirname(__DIR__) . '/includes/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$userName = isset($_SESSION['full_name'])
    ? $_SESSION['full_name']
    : (isset($_SESSION['username']) ? $_SESSION['username'] : 'User');

$userRole = isset($_SESSION['account_type']) ? $_SESSION['account_type'] : 'student';

if ($userRole === 'standard')
{
    $roleDisplay = 'STANDARD';
    $roleIcon = 'fa-user';
}
else
{
    $roleDisplay = 'STUDENT';
    $roleIcon = 'fa-user-graduate';
}

if (!function_exists('isStudentSidebarActive'))
{
    function isStudentSidebarActive($page)
    {
        global $currentPage;
        return ($currentPage == $page) ? 'active' : '';
    }
}

if (!function_exists('getStudentAriaCurrent'))
{
    function getStudentAriaCurrent($page)
    {
        global $currentPage;
        return ($currentPage == $page) ? 'page' : 'false';
    }
}
?>
<style nonce="<?php echo escapeOutput(CSP_NONCE); ?>">
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

    .sidebar-student::-webkit-scrollbar { width: 4px; }
    .sidebar-student::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); }
    .sidebar-student::-webkit-scrollbar-thumb { background: #FF9500; border-radius: 9999px; }

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

    .sidebar-student-header .logo a i { color: #FF9500; font-size: 1.125rem; }

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

    .sidebar-student-role i { font-size: 0.75rem; color: #FF9500; }

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

    .sidebar-student-nav { flex: 1; padding: 12px 0; }

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

    .sidebar-student-nav a i { width: 20px; text-align: center; font-size: 1rem; flex-shrink: 0; }

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

    .sidebar-student-nav a:hover { background: rgba(255, 149, 0, 0.12); color: #FF9500; }
    .sidebar-student-nav a:hover::before { height: 60%; }

    .sidebar-student-nav a.active { background: rgba(255, 149, 0, 0.2); color: white; }
    .sidebar-student-nav a.active::before { height: 70%; background: #FF9500; }
    .sidebar-student-nav a.active i { color: #FF9500; }

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

    .sidebar-student-footer .logout-link i { width: 20px; text-align: center; font-size: 1rem; flex-shrink: 0; }
    .sidebar-student-footer .logout-link:hover { background: rgba(220, 53, 69, 0.15); color: #FF3B30; }

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

        .sidebar-student.mobile-open { transform: translateX(0); box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1); }

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
    <div class="sidebar-student-header">
        <div class="logo">
            <a href="dashboard.php" aria-label="Dashboard Home">
                <i class="fas fa-utensils" aria-hidden="true"></i>
                <span>Campus Eats</span>
            </a>
        </div>
        <p class="sidebar-student-role">
            <i class="fas <?php echo $roleIcon; ?>" aria-hidden="true"></i>
            <?php echo escapeOutput($roleDisplay); ?>
        </p>
        <p class="sidebar-student-user">
            <?php echo escapeOutput($userName); ?>
        </p>
    </div>

    <nav class="sidebar-student-nav" aria-label="Student Menu">
        <a href="dashboard.php"
           class="<?php echo isStudentSidebarActive('dashboard.php'); ?>"
           aria-current="<?php echo getStudentAriaCurrent('dashboard.php'); ?>">
            <i class="fas fa-home" aria-hidden="true"></i>
            <span>Home</span>
        </a>

        <!-- CORRECTION: Browse Menu link to menu_browse.php -->
        <a href="menu_browse.php"
           class="<?php echo isStudentSidebarActive('menu_browse.php'); ?>"
           aria-current="<?php echo getStudentAriaCurrent('menu_browse.php'); ?>">
            <i class="fas fa-utensils" aria-hidden="true"></i>
            <span>Browse Menu</span>
        </a>

        <a href="order_history.php"
           class="<?php echo isStudentSidebarActive('order_history.php'); ?>"
           aria-current="<?php echo getStudentAriaCurrent('order_history.php'); ?>">
            <i class="fas fa-receipt" aria-hidden="true"></i>
            <span>My Orders</span>
        </a>

        <!-- CORRECTION: Track Order link to order_tracking.php -->
        <a href="order_tracking.php"
           class="<?php echo isStudentSidebarActive('order_tracking.php'); ?>"
           aria-current="<?php echo getStudentAriaCurrent('order_tracking.php'); ?>">
            <i class="fas fa-truck" aria-hidden="true"></i>
            <span>Track Order</span>
        </a>

        <a href="cart.php"
           class="<?php echo isStudentSidebarActive('cart.php'); ?>"
           aria-current="<?php echo getStudentAriaCurrent('cart.php'); ?>">
            <i class="fas fa-shopping-cart" aria-hidden="true"></i>
            <span>Cart</span>
            <span class="cart-badge" id="cart-count-badge" style="display: none;">0</span>
        </a>

        <a href="submit_feedback.php"
           class="<?php echo isStudentSidebarActive('submit_feedback.php'); ?>"
           aria-current="<?php echo getStudentAriaCurrent('submit_feedback.php'); ?>">
            <i class="fas fa-comment-dots" aria-hidden="true"></i>
            <span>Feedback</span>
        </a>
    </nav>

    <div class="sidebar-student-footer">
        <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php"
           class="logout-link"
           aria-label="Logout from Account">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<script nonce="<?php echo escapeOutput(CSP_NONCE); ?>">
    document.addEventListener('DOMContentLoaded', function()
    {
        var cartBadge = document.getElementById('cart-count-badge');

        function updateCartBadge()
        {
            if (typeof cart !== 'undefined' && cart.items)
            {
                var count = cart.getTotalItemCount();

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
        document.addEventListener('cartUpdated', updateCartBadge);
    });
</script>