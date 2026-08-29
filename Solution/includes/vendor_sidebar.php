<?php
/**
 * Vendor Sidebar Navigation Component
 *
 * This file contains the left-side vertical sidebar navigation for all vendor pages.
 * Includes: Dashboard, Menu, Orders, Reports.
 *
 * CORRECTIONS (Version 14.0 - Visual Parity with Mockup 25.png):
 * - Refined spacing and typography to match the provided mockups
 * - Uses internal CSS to ensure self-contained styling
 * - Added shop status indicator with color coding
 * - Added active state with orange left border matching mockups
 * - Added pending orders badge
 * - Maintained responsive behavior across all screen sizes
 *
 * SOURCE: Mockup - 25.png
 *
 * @version 14.0
 */

// Ensure constants are loaded
if (!defined('BASE_URL'))
{
    require_once dirname(__DIR__) . '/config/constants.php';
}

// Determine current page for active state highlighting
$currentPage = basename($_SERVER['PHP_SELF']);

// Get vendor name from session - displayed as business name in mockup
$vendorName = $_SESSION['vendor_name'] ?? $_SESSION['full_name'] ?? 'Vendor';

// Get vendor shop status if available
$vendorIsOpen = isset($_SESSION['vendor_is_open']) ? (bool)$_SESSION['vendor_is_open'] : true;

function vendorSidebarEscape($string)
{
    if ($string === null)
    {
        return '';
    }
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function isVendorSidebarActive($page)
{
    global $currentPage;
    return ($currentPage == $page) ? 'active' : '';
}

function getVendorAriaCurrent($page)
{
    global $currentPage;
    return ($currentPage == $page) ? 'page' : 'false';
}
?>
<!-- Vendor Sidebar - Semantic HTML5 aside element -->
<style>
    /* =============================================================================
       Vendor Sidebar Internal Styles - Matches vendor mockup
       SOURCE: Mockup - 25.png
       ============================================================================= */
    .sidebar-vendor
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

    .sidebar-vendor::-webkit-scrollbar
    {
        width: 4px;
    }
    .sidebar-vendor::-webkit-scrollbar-track
    {
        background: rgba(255, 255, 255, 0.05);
    }
    .sidebar-vendor::-webkit-scrollbar-thumb
    {
        background: #FF9500;
        border-radius: 9999px;
    }

    /* Sidebar Header */
    .sidebar-vendor-header
    {
        padding: 24px 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        text-align: center;
        flex-shrink: 0;
    }

    .sidebar-vendor-header .logo a
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

    .sidebar-vendor-header .logo a i
    {
        color: #FF9500;
        font-size: 1.125rem;
    }

    .sidebar-vendor-role
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

    .sidebar-vendor-role i
    {
        font-size: 0.75rem;
        color: #FF9500;
    }

    /* Shop Status - matches vendor mockup */
    .sidebar-vendor-status
    {
        font-size: 0.7rem;
        margin-top: 8px;
        text-align: center;
        color: #AEAEB2;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .status-indicator
    {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
    }

    .status-indicator.open
    {
        background: #34C759;
    }

    .status-indicator.closed
    {
        background: #FF3B30;
    }

    /* Sidebar Navigation */
    .sidebar-vendor-nav
    {
        flex: 1;
        padding: 12px 0;
    }

    .sidebar-vendor-nav a
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

    .sidebar-vendor-nav a i
    {
        width: 20px;
        text-align: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    /* Active State - Orange left border (matches mockups) */
    .sidebar-vendor-nav a::before
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

    .sidebar-vendor-nav a:hover
    {
        background: rgba(255, 149, 0, 0.12);
        color: #FF9500;
    }

    .sidebar-vendor-nav a:hover::before
    {
        height: 60%;
    }

    .sidebar-vendor-nav a.active
    {
        background: rgba(255, 149, 0, 0.2);
        color: white;
    }

    .sidebar-vendor-nav a.active::before
    {
        height: 70%;
        background: #FF9500;
    }

    .sidebar-vendor-nav a.active i
    {
        color: #FF9500;
    }

    /* Orders Badge */
    .orders-badge
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
    .sidebar-vendor-footer
    {
        padding: 12px 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
        flex-shrink: 0;
    }

    .sidebar-vendor-footer .logout-link
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

    .sidebar-vendor-footer .logout-link i
    {
        width: 20px;
        text-align: center;
        font-size: 1rem;
        flex-shrink: 0;
    }

    .sidebar-vendor-footer .logout-link:hover
    {
        background: rgba(220, 53, 69, 0.15);
        color: #FF3B30;
    }

    /* Responsive - Mobile */
    @media (max-width: 768px)
    {
        .sidebar-vendor
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

        .sidebar-vendor.mobile-open
        {
            transform: translateX(0);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .sidebar-vendor.mobile-open::after
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

<aside class="sidebar-vendor" aria-label="Vendor Navigation Sidebar" role="navigation">
    <!-- Sidebar Header -->
    <div class="sidebar-vendor-header">
        <div class="logo">
            <a href="dashboard.php" aria-label="Vendor Dashboard Home">
                <i class="fas fa-utensils" aria-hidden="true"></i>
                <span>Campus Eats</span>
            </a>
        </div>
        <p class="sidebar-vendor-role">
            <i class="fas fa-store" aria-hidden="true"></i>
            <?php echo vendorSidebarEscape($vendorName); ?>
        </p>
        <!-- Shop Status Indicator - matches vendor mockup -->
        <p class="sidebar-vendor-status">
            <span class="status-indicator <?php echo $vendorIsOpen ? 'open' : 'closed'; ?>"></span>
            <?php echo $vendorIsOpen ? 'Shop Open' : 'Shop Closed'; ?>
        </p>
    </div>

    <!-- Sidebar Navigation Menu -->
    <nav class="sidebar-vendor-nav" aria-label="Vendor Menu">
        <a href="dashboard.php"
           class="<?php echo isVendorSidebarActive('dashboard.php'); ?>"
           aria-label="Dashboard"
           aria-current="<?php echo getVendorAriaCurrent('dashboard.php'); ?>">
            <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
            <span>Dashboard</span>
        </a>

        <a href="menu.php"
           class="<?php echo isVendorSidebarActive('menu.php'); ?>"
           aria-label="Menu Management"
           aria-current="<?php echo getVendorAriaCurrent('menu.php'); ?>">
            <i class="fas fa-utensils" aria-hidden="true"></i>
            <span>Menu</span>
        </a>

        <a href="orders.php"
           class="<?php echo isVendorSidebarActive('orders.php'); ?>"
           aria-label="Order Management"
           aria-current="<?php echo getVendorAriaCurrent('orders.php'); ?>">
            <i class="fas fa-shopping-cart" aria-hidden="true"></i>
            <span>Orders</span>
            <!-- Pending orders badge -->
            <span class="orders-badge" id="pending-orders-badge" style="display: none;">
                0
            </span>
        </a>

        <a href="reports.php"
           class="<?php echo isVendorSidebarActive('reports.php'); ?>"
           aria-label="Sales Reports"
           aria-current="<?php echo getVendorAriaCurrent('reports.php'); ?>">
            <i class="fas fa-chart-line" aria-hidden="true"></i>
            <span>Reports</span>
        </a>
    </nav>

    <!-- Sidebar Footer with Logout Button -->
    <div class="sidebar-vendor-footer">
        <a href="<?php echo BASE_URL; ?>/modules/auth/logout.php"
           class="logout-link"
           aria-label="Logout from Vendor Account">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<script>
    /**
     * Update pending orders badge when orders change.
     * This script fetches pending order count and updates the badge.
     */
    document.addEventListener('DOMContentLoaded', function()
    {
        const ordersBadge = document.getElementById('pending-orders-badge');

        // Function to fetch and update pending orders count
        function updatePendingOrdersBadge()
        {
            if (!ordersBadge) return;

            // Fetch pending orders count from API
            fetch('../api/get_pending_orders_count.php', {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(function(data)
            {
                if (data.success && data.count !== undefined)
                {
                    const count = parseInt(data.count, 10);
                    if (count > 0)
                    {
                        ordersBadge.textContent = count;
                        ordersBadge.style.display = 'inline-block';
                    }
                    else
                    {
                        ordersBadge.style.display = 'none';
                    }
                }
            })
            .catch(function(error)
            {
                console.error('Error fetching pending orders:', error);
                // Silently fail - badge will not show
            });
        }

        // Initial update
        updatePendingOrdersBadge();

        // Refresh every 30 seconds
        setInterval(updatePendingOrdersBadge, 30000);
    });
</script>