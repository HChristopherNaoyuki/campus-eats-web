/**
 * Administrator-Specific JavaScript for Campus Eats (Complete Refactor)
 *
 * Handles admin-specific functionality including:
 * - Vendor approval/rejection
 * - User suspension/activation
 * - Transaction filtering
 * - Report generation
 * - Dashboard statistics updates
 *
 * SOURCE: campus-eats-process-document.pdf (Section 6.4 - Administrator Functional Requirements)
 * SOURCE: Mockups - 26.png through 34.png
 *
 * @version 5.0
 */

(function()
{
    'use strict';

    /**
     * Gets the CSRF token from the meta tag.
     * @returns {string}
     */
    function getCsrfToken()
    {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : '';
    }

    /**
     * Escapes HTML special characters to prevent XSS attacks.
     * @param {string} text
     * @returns {string}
     */
    function escapeHtml(text)
    {
        if (!text)
        {
            return '';
        }

        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Toggles the mobile admin sidebar.
     */
    function toggleAdminSidebar()
    {
        const sidebar = document.querySelector('.admin-sidebar');

        if (sidebar)
        {
            sidebar.classList.toggle('open');
        }
    }

    /**
     * Approves a vendor application.
     * @param {number} vendorId
     */
    function approveVendor(vendorId)
    {
        if (confirm('Are you sure you want to approve this vendor? They will be able to access the vendor portal.'))
        {
            fetch('../api/approve_vendor.php',
            {
                method: 'POST',
                headers:
                {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    vendor_id: vendorId,
                    action: 'approve'
                })
            })
            .then(function(response) { return response.json(); })
            .then(function(data)
            {
                if (data.success)
                {
                    showNotification('Vendor approved successfully.', 'success');
                    location.reload();
                }
                else
                {
                    showNotification(data.message || 'Error approving vendor.', 'error');
                }
            })
            .catch(function(error)
            {
                console.error('Error approving vendor:', error);
                showNotification('Error approving vendor.', 'error');
            });
        }
    }

    /**
     * Suspends a vendor account.
     * @param {number} vendorId
     * @param {string} vendorName
     */
    function suspendVendor(vendorId, vendorName)
    {
        if (confirm('Are you sure you want to suspend "' + vendorName + '"? This will prevent them from accepting new orders.'))
        {
            fetch('../api/approve_vendor.php',
            {
                method: 'POST',
                headers:
                {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    vendor_id: vendorId,
                    action: 'suspend'
                })
            })
            .then(function(response) { return response.json(); })
            .then(function(data)
            {
                if (data.success)
                {
                    showNotification('Vendor suspended successfully.', 'success');
                    location.reload();
                }
                else
                {
                    showNotification(data.message || 'Error suspending vendor.', 'error');
                }
            })
            .catch(function(error)
            {
                console.error('Error suspending vendor:', error);
                showNotification('Error suspending vendor.', 'error');
            });
        }
    }

    /**
     * Activates a suspended vendor account.
     * @param {number} vendorId
     */
    function activateVendor(vendorId)
    {
        fetch('../api/approve_vendor.php',
        {
            method: 'POST',
            headers:
            {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                vendor_id: vendorId,
                action: 'activate'
            })
        })
        .then(function(response) { return response.json(); })
        .then(function(data)
        {
            if (data.success)
            {
                showNotification('Vendor activated successfully.', 'success');
                location.reload();
            }
            else
            {
                showNotification(data.message || 'Error activating vendor.', 'error');
            }
        })
        .catch(function(error)
        {
            console.error('Error activating vendor:', error);
            showNotification('Error activating vendor.', 'error');
        });
    }

    /**
     * Suspends a user account (student).
     * @param {number} userId
     * @param {string} userName
     */
    function suspendUser(userId, userName)
    {
        if (confirm('Are you sure you want to suspend "' + userName + '"? They will not be able to log in or place orders.'))
        {
            fetch('../api/manage_user.php',
            {
                method: 'POST',
                headers:
                {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({
                    user_id: userId,
                    action: 'suspend'
                })
            })
            .then(function(response) { return response.json(); })
            .then(function(data)
            {
                if (data.success)
                {
                    showNotification('User suspended successfully.', 'success');
                    location.reload();
                }
                else
                {
                    showNotification(data.message || 'Error suspending user.', 'error');
                }
            })
            .catch(function(error)
            {
                console.error('Error suspending user:', error);
                showNotification('Error suspending user.', 'error');
            });
        }
    }

    /**
     * Activates a suspended user account.
     * @param {number} userId
     */
    function activateUser(userId)
    {
        fetch('../api/manage_user.php',
        {
            method: 'POST',
            headers:
            {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                user_id: userId,
                action: 'activate'
            })
        })
        .then(function(response) { return response.json(); })
        .then(function(data)
        {
            if (data.success)
            {
                showNotification('User activated successfully.', 'success');
                location.reload();
            }
            else
                {
                showNotification(data.message || 'Error activating user.', 'error');
            }
        })
        .catch(function(error)
        {
            console.error('Error activating user:', error);
            showNotification('Error activating user.', 'error');
        });
    }

    /**
     * Filters transactions by date range and status (X-Safe).
     */
    function filterTransactions()
    {
        const startDate = document.getElementById('start-date')?.value;
        const endDate = document.getElementById('end-date')?.value;
        const status = document.getElementById('payment-status')?.value;

        let url = '../api/get_transactions.php?';

        if (startDate) url += 'start_date=' + encodeURIComponent(startDate) + '&';
        if (endDate) url += 'end_date=' + encodeURIComponent(endDate) + '&';
        if (status) url += 'status=' + encodeURIComponent(status);

        fetch(url)
            .then(function(response) { return response.json(); })
            .then(function(data)
            {
                if (data.success)
                {
                    updateTransactionsTable(data.transactions);
                }
                else
                {
                    showNotification('Error loading transactions.', 'error');
                }
            })
            .catch(function(error)
            {
                console.error('Error loading transactions:', error);
                showNotification('Error loading transactions.', 'error');
            });
    }

    /**
     * Updates the transactions table with new data (XSS-safe implementation).
     * @param {Array} transactions
     */
    function updateTransactionsTable(transactions)
    {
        const tableBody = document.getElementById('transactions-table-body');

        if (!tableBody) return;

        if (!transactions || transactions.length === 0)
        {
            tableBody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No transactions found.</td></tr>';
            return;
        }

        let html = '';

        for (let i = 0; i < transactions.length; i++)
        {
            const transaction = transactions[i];
            let statusClass = '';

            switch (transaction.payment_status)
            {
                case 'completed':
                    statusClass = 'payment-completed';
                    break;
                case 'pending':
                    statusClass = 'payment-pending';
                    break;
                case 'failed':
                    statusClass = 'payment-failed';
                    break;
                default:
                    statusClass = '';
                    break;
            }

            html += '<tr>' +
                '<td>' + escapeHtml(String(transaction.order_id)) + '</td>' +
                '<td>' + escapeHtml(transaction.student_name) + '</td>' +
                '<td>' + escapeHtml(transaction.vendor_name) + '</td>' +
                '<td>R ' + parseFloat(transaction.amount).toFixed(2) + '</td>' +
                '<td class="' + escapeHtml(statusClass) + '">' + escapeHtml(transaction.payment_status) + '</td>' +
                '<td>' + escapeHtml(transaction.payment_date) + '</td>' +
                '</tr>';
        }

        tableBody.innerHTML = html;
    }

    /**
     * Generates a system-wide analytics report.
     */
    function generateSystemReport()
    {
        const reportType = document.getElementById('report-type')?.value;
        const startDate = document.getElementById('start-date')?.value;
        const endDate = document.getElementById('end-date')?.value;

        if (!startDate || !endDate)
        {
            showNotification('Please select both start and end dates.', 'error');
            return;
        }

        window.location.href = '../api/generate_system_report.php?type=' + encodeURIComponent(reportType) +
                               '&start_date=' + encodeURIComponent(startDate) +
                               '&end_date=' + encodeURIComponent(endDate) +
                               '&format=csv';
    }

    /**
     * Loads analytics data for dashboard charts.
     */
    function loadAnalyticsData()
    {
        fetch('../api/get_analytics.php')
            .then(function(response) { return response.json(); })
            .then(function(data)
            {
                if (data.success)
                {
                    updateDashboardStats(data.stats);
                }
            })
            .catch(function(error)
            {
                console.error('Error loading analytics:', error);
            });
    }

    /**
     * Updates dashboard statistics display (XSS-safe implementation).
     * @param {Object} stats
     */
    function updateDashboardStats(stats)
    {
        const statsContainer = document.getElementById('dashboard-stats');

        if (statsContainer)
        {
            const totalStudents = stats.total_students || 0;
            const totalVendors = stats.total_vendors || 0;
            const totalOrders = stats.total_orders || 0;
            const totalRevenue = parseFloat(stats.total_revenue || 0).toFixed(2);

            statsContainer.innerHTML =
                '<div class="quick-stat-card">' +
                    '<i class="fas fa-users"></i>' +
                    '<div class="quick-stat-number">' + escapeHtml(String(totalStudents)) + '</div>' +
                    '<div class="quick-stat-label">Total Students</div>' +
                '</div>' +
                '<div class="quick-stat-card">' +
                    '<i class="fas fa-store"></i>' +
                    '<div class="quick-stat-number">' + escapeHtml(String(totalVendors)) + '</div>' +
                    '<div class="quick-stat-label">Total Vendors</div>' +
                '</div>' +
                '<div class="quick-stat-card">' +
                    '<i class="fas fa-shopping-cart"></i>' +
                    '<div class="quick-stat-number">' + escapeHtml(String(totalOrders)) + '</div>' +
                    '<div class="quick-stat-label">Total Orders</div>' +
                '</div>' +
                '<div class="quick-stat-card">' +
                    '<i class="fas fa-chart-line"></i>' +
                    '<div class="quick-stat-number">R ' + escapeHtml(String(totalRevenue)) + '</div>' +
                    '<div class="quick-stat-label">Total Revenue</div>' +
                '</div>';
        }
    }

    /**
     * Initializes admin-specific event listeners.
     */
    function initAdminEvents()
    {
        var rejectForms = document.querySelectorAll('.reject-vendor-form');
        for (var i = 0; i < rejectForms.length; i++)
        {
            rejectForms[i].removeEventListener('submit', handleRejectSubmit);
            rejectForms[i].addEventListener('submit', handleRejectSubmit);
        }
    }

    /**
     * Handles the submission of a vendor rejection form.
     * @param {Event} event
     */
    function handleRejectSubmit(event)
    {
        if (!confirm('Reject this vendor application? This will permanently delete the vendor account and associated user.'))
        {
            event.preventDefault();
        }
    }

    // Load analytics and initialize events when page loads.
    document.addEventListener('DOMContentLoaded', function()
    {
        if (document.getElementById('dashboard-stats'))
        {
            loadAnalyticsData();
        }
        initAdminEvents();
    });

    // Expose functions globally.
    window.toggleAdminSidebar = toggleAdminSidebar;
    window.approveVendor = approveVendor;
    window.suspendVendor = suspendVendor;
    window.activateVendor = activateVendor;
    window.suspendUser = suspendUser;
    window.activateUser = activateUser;
    window.filterTransactions = filterTransactions;
    window.generateSystemReport = generateSystemReport;
})();