<?php
/**
 * Order Status Helper Functions
 *
 * Provides shared functions for rendering order status badges, labels,
 * and icons. These functions were previously duplicated in at least
 * four files: Solution/api/checkout.php, Solution/modules/student/
 * order_history.php, Solution/modules/student/order_tracking.php, and
 * Solution/modules/vendor/orders.php.
 *
 * The functions are guarded by function_exists() so that a second
 * include of this file does not trigger a redeclaration fatal. This is
 * the standard pattern used throughout the codebase for shared
 * helpers.
 *
 * SOURCE: Technical Audit and Fixes Report.
 * SOURCE: Codebase review, duplication findings.
 *
 * @version 1.0
 */

if (!function_exists('getOrderStatusBadgeClass'))
{
    /**
     * Returns the CSS class for an order status badge.
     *
     * @param string $status The order status
     * @return string The CSS class
     */
    function getOrderStatusBadgeClass($status)
    {
        switch ($status)
        {
            case ORDER_STATUS_PENDING:
                return 'status-pending';

            case ORDER_STATUS_ACCEPTED:
                return 'status-accepted';

            case ORDER_STATUS_PREPARING:
                return 'status-preparing';

            case ORDER_STATUS_READY:
                return 'status-ready';

            case ORDER_STATUS_COMPLETED:
                return 'status-completed';

            case ORDER_STATUS_CANCELLED:
                return 'status-cancelled';

            default:
                return 'status-pending';
        }
    }
}

if (!function_exists('getOrderStatusText'))
{
    /**
     * Returns a human-readable label for an order status.
     *
     * @param string $status The order status
     * @return string The label
     */
    function getOrderStatusText($status)
    {
        switch ($status)
        {
            case ORDER_STATUS_PENDING:
                return 'Pending';

            case ORDER_STATUS_ACCEPTED:
                return 'Accepted';

            case ORDER_STATUS_PREPARING:
                return 'Preparing';

            case ORDER_STATUS_READY:
                return 'Ready for Pickup';

            case ORDER_STATUS_COMPLETED:
                return 'Completed';

            case ORDER_STATUS_CANCELLED:
                return 'Cancelled';

            default:
                return ucfirst((string)$status);
        }
    }
}

if (!function_exists('getOrderStatusIcon'))
{
    /**
     * Returns the Font Awesome icon class for an order status.
     *
     * @param string $status The order status
     * @return string The icon class
     */
    function getOrderStatusIcon($status)
    {
        switch ($status)
        {
            case ORDER_STATUS_PENDING:
                return 'fa-clock';

            case ORDER_STATUS_ACCEPTED:
                return 'fa-check';

            case ORDER_STATUS_PREPARING:
                return 'fa-utensils';

            case ORDER_STATUS_READY:
                return 'fa-concierge-bell';

            case ORDER_STATUS_COMPLETED:
                return 'fa-check-double';

            case ORDER_STATUS_CANCELLED:
                return 'fa-ban';

            default:
                return 'fa-question-circle';
        }
    }
}

if (!function_exists('getOrderStatusProgressPercent'))
{
    /**
     * Returns the progress percentage for an order status.
     *
     * Used by the tracking page to render the progress bar.
     *
     * @param string $status The order status
     * @return int The progress percentage
     */
    function getOrderStatusProgressPercent($status)
    {
        switch ($status)
        {
            case ORDER_STATUS_PENDING:
                return 0;

            case ORDER_STATUS_ACCEPTED:
                return 25;

            case ORDER_STATUS_PREPARING:
                return 50;

            case ORDER_STATUS_READY:
                return 75;

            case ORDER_STATUS_COMPLETED:
                return 100;

            case ORDER_STATUS_CANCELLED:
                return 0;

            default:
                return 0;
        }
    }
}

if (!function_exists('getOrderStatusMessage'))
{
    /**
     * Returns a human-readable status message for an order status.
     *
     * @param string $status The order status
     * @return string The status message
     */
    function getOrderStatusMessage($status)
    {
        switch ($status)
        {
            case ORDER_STATUS_PENDING:
                return 'Your order has been received and is awaiting vendor confirmation.';

            case ORDER_STATUS_ACCEPTED:
                return 'Your order has been accepted by the vendor and will be prepared shortly.';

            case ORDER_STATUS_PREPARING:
                return 'The kitchen is preparing your order. It will be ready soon.';

            case ORDER_STATUS_READY:
                return 'Your order is ready for pickup. Please collect it from the vendor.';

            case ORDER_STATUS_COMPLETED:
                return 'Order completed. Thank you for using Campus Eats.';

            case ORDER_STATUS_CANCELLED:
                return 'This order has been cancelled.';

            default:
                return 'Status update pending.';
        }
    }
}