/**
 * Checkout JavaScript for Campus Eats (Complete Refactor)
 *
 * CORRECTIONS:
 * - Added CSRF token validation
 * - Improved error handling
 * - Added form validation before submission
 * - Bug 8 (Null References): Added optional chaining and null checks.
 * - CSRF Header: Added X-CSRF-TOKEN header.
 * - Standardized API endpoint path using BASE_URL.
 *
 * @version 4.0
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
     * Shows a notification.
     * @param {string} message
     * @param {string} type
     */
    function showNotification(message, type)
    {
        alert(message);
    }

    /**
     * Handles the checkout form submission.
     * @param {Event} event
     */
    async function handleCheckout(event)
    {
        event.preventDefault();

        // Bug 8: Added optional chaining and null checks for all elements
        const pickupTimeElement = document.getElementById('pickup_time');
        const paymentMethodElement = document.getElementById('payment_method');
        const vendorIdElement = document.getElementById('vendor-id');
        const cartDataElement = document.getElementById('cart-data');
        const totalAmountElement = document.getElementById('total-amount');

        if (!pickupTimeElement || !paymentMethodElement || !vendorIdElement || !cartDataElement || !totalAmountElement)
        {
            showNotification('Required form elements are missing. Please refresh the page.', 'error');
            return;
        }

        const pickupTime = pickupTimeElement.value;
        const paymentMethod = paymentMethodElement.value;
        const vendorId = vendorIdElement.value;
        let cartItems;
        try
        {
            cartItems = JSON.parse(cartDataElement.value);
        }
        catch (e)
        {
            showNotification('Invalid cart data. Please refresh the page.', 'error');
            return;
        }
        const totalAmount = totalAmountElement.value;

        if (!pickupTime || !paymentMethod)
        {
            showNotification('Please fill in all required fields.', 'error');
            return;
        }

        const orderData = {
            vendor_id: parseInt(vendorId, 10),
            items: cartItems.map(function(item)
            {
                return {
                    item_id: parseInt(item.item_id, 10),
                    quantity: parseInt(item.quantity, 10)
                };
            }),
            total_amount: parseFloat(totalAmount),
            pickup_time: pickupTime,
            payment_method: paymentMethod,
            special_requests: document.getElementById('special_requests')?.value || ''
        };

        const submitBtn = document.querySelector('#checkout-form button[type="submit"]');
        const originalBtnText = submitBtn ? submitBtn.textContent : 'Place Order';

        if (submitBtn)
        {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Processing...';
        }

        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
        const apiUrl = baseUrl + '/api/process_payment.php';

        try
        {
            const response = await fetch(apiUrl,
            {
                method: 'POST',
                headers:
                {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                credentials: 'same-origin',
                body: JSON.stringify(orderData)
            });

            const data = await response.json();

            if (data.success)
            {
                showNotification(data.message, 'success');
                window.location.href = 'order_tracking.php?order_id=' + data.order_id;
            }
            else
            {
                showNotification(data.message, 'error');
                if (submitBtn)
                {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
            }
        }
        catch (error)
        {
            console.error('Error:', error);
            showNotification('An error occurred. Please try again.', 'error');
            if (submitBtn)
            {
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function()
    {
        const form = document.getElementById('checkout-form');
        if (form)
        {
            form.addEventListener('submit', handleCheckout);
        }
    });
})();