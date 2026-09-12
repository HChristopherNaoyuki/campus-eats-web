<?php
/**
 * Admin Sidebar Navigation Component
 *
 * Contains the left-side vertical sidebar navigation for all admin pages.
 *
 * CORRECTIONS (Version 17.0):
 * - Added Security Log navigation link required by process document
 *   Sections 10.3 and 12.4
 * - Uses the shared escapeOutput() helper
 *
 * SOURCE: Issue report - items 4, 23
 *
 * @version 17.0
 */

if (!defined('BASE_URL'))
{
    require_once dirname(__DIR__) . '/config/constants.php';
}

require_once dirname(__DIR__) . '/includes/auth.php';

$currentPage = basename($_SERVER['PHP_SELF']);
$adminName = isset($_SESSION['full_name'])
    ? $_SESSION['full_name']
    : (isset($_SESSION['username']) ? $_SESSION['username'] : 'Administrator');

if (!function_exists('isAdminSidebarActive'))
{
    function isAdminSidebarActive($page)
    {
        global $currentPage;
        return ($currentPage == $page) ? 'active' : '';
    }
}

if (!function_exists('getAdminAriaCurrent'))
{
    function getAdminAriaCurrent($page)
    {
        global $currentPage;
        return ($currentPage == $page) ? 'page' : 'false';
    }
}
?>
<style nonce="<?php echo escapeOutput(CSP_NONCE); ?>">
    .sidebar-admin
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

    .sidebar-admin::-webkit-scrollbar { width: 4px; }
    .sidebar-admin::-webkit-scrollbar-track { background: rgba(255, 255, 255, 0.05); }
    .sidebar-admin::-webkit-scrollbar-thumb { background: #FF9500; border-radius: 9999px; }

    .sidebar-admin-header
    {
        padding: 24px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        text-align: center;
        flex-shrink: 0;
    }

    .sidebar-admin-header .logo a
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

    .sidebar-admin-header .logo a i { color: #FF9500; font-size: 1.125rem; }

    .sidebar-admin-role
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

    .sidebar-admin-role i { font-size: 0.75rem; color: #FF9500; }

    .sidebar-admin-user
    {
        font-size: 0.75rem;
        color: #AEAEB2;
        margin-top: 4px;
        text-align: center;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .sidebar-admin-nav
    {
        flex: 1;
        padding: 12px 0;
    }

    .sidebar-admin-nav a
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

    .sidebar-admin-nav a i { width: 20px; text-align: center; font-size: 1rem; flex-shrink: 0; }

    .sidebar-admin-nav a::before
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

    .sidebar-admin-nav a:hover { background: rgba(255, 149, 0, 0.12); color: #FF9500; }
    .sidebar-admin-nav a:hover::before { height: 60%; }

    .sidebar-admin-nav a.active { background: rgba(255, 149, 0, 0.2); color: white; }
    .sidebar-admin-nav a.active::before { height: 70%; background: #FF9500; }
    .sidebar-admin-nav a.active i { color: #FF9500; }

    .sidebar-admin-footer
    {
        padding: 12px 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        flex-shrink: 0;
    }

    .sidebar-admin-footer .logout-link
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

    .sidebar-admin-footer .logout-link i { width: 20px; text-align: center; font-size: 1rem; flex-shrink: 0; }
    .sidebar-admin-footer .logout-link:hover { background: rgba(220, 53, 69, 0.15); color: #FF3B30; }

    @media (max-width: 768px)
    {
        .sidebar-admin
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

        .sidebar-admin.mobile-open
        {
            transform: translateX(0);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-admin.mobile-open::after
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

<aside class="sidebar-admin" aria-label="Administrator Navigation Sidebar" role="navigation">
    <div class="sidebar-admin-header">
        <div class="logo">
            <a href="dashboard.php" aria-label="Campus Eats Dashboard Home">
                <i class="fas fa-utensils" aria-hidden="true"></i>
                <span>Campus Eats</span>
            </a>
        </div>
        <p class="sidebar-admin-role">
            <i class="fas fa-user-shield" aria-hidden="true"></i>
            ADMINISTRATOR
        </p>
        <p class="sidebar-admin-user">
            <?php echo escapeOutput($adminName); ?>
        </p>
    </div>

    <nav class="sidebar-admin-nav" aria-label="Administrator Menu">
        <a href="dashboard.php"
           class="<?php echo isAdminSidebarActive('dashboard.php'); ?>"
           aria-current="<?php echo getAdminAriaCurrent('dashboard.php'); ?>">
            <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
            <span>Dashboard</span>
        </a>

        <a href="manage_users.php"
           class="<?php echo isAdminSidebarActive('manage_users.php'); ?>"
           aria-current="<?php echo getAdminAriaCurrent('manage_users.php'); ?>">
            <i class="fas fa-users" aria-hidden="true"></i>
            <span>User Management</span>
        </a>

        <a href="manage_vendors.php"
           class="<?php echo isAdminSidebarActive('manage_vendors.php'); ?>"
           aria-current="<?php echo getAdminAriaCurrent('manage_vendors.php'); ?>">
            <i class="fas fa-store" aria-hidden="true"></i>
            <span>Vendor Management</span>
        </a>

        <a href="order_management.php"
           class="<?php echo isAdminSidebarActive('order_management.php'); ?>"
           aria-current="<?php echo getAdminAriaCurrent('order_management.php'); ?>">
            <i class="fas fa-shopping-cart" aria-hidden="true"></i>
            <span>Order Management</span>
        </a>

        <a href="monitor_transactions.php"
           class="<?php echo isAdminSidebarActive('monitor_transactions.php'); ?>"
           aria-current="<?php echo getAdminAriaCurrent('monitor_transactions.php'); ?>">
            <i class="fas fa-receipt" aria-hidden="true"></i>
            <span>Transactions</span>
        </a>

        <a href="reports.php"
           class="<?php echo isAdminSidebarActive('reports.php'); ?>"
           aria-current="<?php echo getAdminAriaCurrent('reports.php'); ?>">
            <i class="fas fa-chart-line" aria-hidden="true"></i>
            <span>Reports</span>
        </a>

        <a href="view_feedback.php"
           class="<?php echo isAdminSidebarActive('view_feedback.php'); ?>"
           aria-current="<?php echo getAdminAriaCurrent('view_feedback.php'); ?>">
            <i class="fas fa-comment-dots" aria-hidden="true"></i>
            <span>Feedback</span>
        </a>

        <!-- CORRECTION: Security Log link required by process document -->
        <a href="security_log.php"
           class="<?php echo isAdminSidebarActive('security_log.php'); ?>"
           aria-current="<?php echo getAdminAriaCurrent('security_log.php'); ?>">
            <i class="fas fa-shield-alt" aria-hidden="true"></i>
            <span>Security Log</span>
        </a>
    </nav>

    <div class="sidebar-admin-footer">
        <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php"
           class="logout-link"
           aria-label="Logout from Administrator Account">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>