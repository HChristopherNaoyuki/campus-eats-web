/**
 * Campus Eats - Payment Modal Handler
 * 
 * Handles payment processing modals for order checkout.
 * 
 * Version: 4.0.0
 * Last Modified: June 26, 2026
 * 
 * Requirement Compliance: ALL JavaScript is external (no inline scripts)
 * 
 * CORRECTIONS (Version 4.0 - UX & Accessibility):
 * - Bug 7: Added X-CSRF-TOKEN header to the payment fetch call for consistency.
 * - Standardized API endpoint path using BASE_URL.
 * - Fixed hardcoded redirect path to use BASE_URL constant.
 * - Added ARIA roles and attributes for accessibility.
 * - Fixed focus management (return focus to triggering element on close).
 * - Fixed window.paymentModalManager export.
 *
 * Source: Full Code Review Report - Section 1.7, 2.6, 2.7
 */

/**
 * Payment Modal Manager
 * 
 * Manages the payment modal lifecycle including opening, closing,
 * form submission, and order confirmation.
 */
class PaymentModalManager
{
    constructor()
    {
        this.modal = null;
        this.currentOrderId = null;
        this.currentTotal = 0;
        this.isProcessing = false;
        this.triggerElement = null; // Track element that opened the modal
        
        this.initialize();
    }
    
    /**
     * Initializes the payment modal and event listeners
     */
    initialize()
    {
        // Wait for DOM to be ready
        if (document.readyState === 'loading')
        {
            document.addEventListener('DOMContentLoaded', () => this.setupModal());
        }
        else
        {
            this.setupModal();
        }
    }
    
    /**
     * Sets up the payment modal element and event handlers
     */
    setupModal()
    {
        // Check if modal already exists
        this.modal = document.getElementById('payment-modal');
        
        if (!this.modal)
        {
            this.createModal();
        }
        else
        {
            this.attachEventListeners();
        }
    }
    
    /**
     * Creates the payment modal dynamically if not present
     */
    createModal()
    {
        this.modal = document.createElement('div');
        this.modal.id = 'payment-modal';
        this.modal.className = 'modal';
        // =========================================================================
        // ACCESSIBILITY FIX: Added proper ARIA roles and attributes
        // =========================================================================
        this.modal.setAttribute('role', 'dialog');
        this.modal.setAttribute('aria-modal', 'true');
        this.modal.setAttribute('aria-labelledby', 'payment-modal-title');
        this.modal.setAttribute('aria-hidden', 'true');
        
        this.modal.innerHTML = `
            <div class="modal-content" style="max-width: 500px;">
                <div class="modal-header">
                    <h3 id="payment-modal-title">Complete Your Payment</h3>
                    <button type="button" class="modal-close" aria-label="Close modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div id="payment-order-summary" class="payment-summary">
                        <!-- Order summary will be inserted here -->
                    </div>
                    
                    <form id="payment-form" method="POST" action="/campus-eats-web/Solution/api/process_payment.php">
                        <input type="hidden" name="csrf_token" id="payment-csrf-token" value="">
                        <input type="hidden" name="order_id" id="payment-order-id" value="">
                        
                        <div class="form-group">
                            <label for="payment-method" class="form-label">Select Payment Method</label>
                            <select name="payment_method" id="payment-method" class="form-control" required>
                                <option value="">-- Select Payment Method --</option>
                                <option value="cash">Cash (Pay at Vendor)</option>
                                <option value="card">Card (Credit/Debit)</option>
                                <option value="mobile">Mobile Payment</option>
                            </select>
                            <div class="invalid-feedback" id="payment-method-error"></div>
                        </div>
                        
                        <div class="form-group">
                            <label for="pickup-time" class="form-label">Preferred Pickup Time</label>
                            <select name="pickup_time" id="pickup-time" class="form-control" required>
                                <option value="">-- Select Pickup Time --</option>
                                <option value="ASAP">ASAP (Immediately)</option>
                                <option value="15min">15 Minutes</option>
                                <option value="30min">30 Minutes</option>
                                <option value="45min">45 Minutes</option>
                                <option value="60min">60 Minutes</option>
                            </select>
                        </div>
                        
                        <div id="payment-loading" class="text-center" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i> Processing payment...
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancel-payment-btn">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirm-payment-btn">Confirm Payment</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(this.modal);
        this.attachEventListeners();
    }
    
    /**
     * Attaches event listeners to modal elements
     */
    attachEventListeners()
    {
        // Close button
        const closeBtn = this.modal.querySelector('.modal-close');
        if (closeBtn)
        {
            closeBtn.addEventListener('click', () => this.closeModal());
        }
        
        // Cancel button
        const cancelBtn = document.getElementById('cancel-payment-btn');
        if (cancelBtn)
        {
            cancelBtn.addEventListener('click', () => this.closeModal());
        }
        
        // Confirm button
        const confirmBtn = document.getElementById('confirm-payment-btn');
        if (confirmBtn)
        {
            confirmBtn.addEventListener('click', () => this.submitPayment());
        }
        
        // Close on background click
        this.modal.addEventListener('click', (event) =>
        {
            if (event.target === this.modal)
            {
                this.closeModal();
            }
        });
        
        // Close on Escape key
        document.addEventListener('keydown', (event) =>
        {
            if (event.key === 'Escape' && this.modal.style.display === 'flex')
            {
                this.closeModal();
            }
        });
        
        // Validate payment method selection
        const paymentMethod = document.getElementById('payment-method');
        if (paymentMethod)
        {
            paymentMethod.addEventListener('change', () => this.validatePaymentMethod());
        }
    }
    
    /**
     * Opens the payment modal for a specific order
     * 
     * @param {number} orderId - The order ID to process payment for
     * @param {number} totalAmount - The total amount to be paid
     * @param {Object} items - Array of order items
     * @param {HTMLElement} triggerElement - The element that triggered the modal
     */
    openModal(orderId, totalAmount, items, triggerElement)
    {
        if (!orderId || totalAmount === undefined)
        {
            console.error('Invalid payment data: orderId and totalAmount are required');
            return;
        }
        
        this.currentOrderId = orderId;
        this.currentTotal = totalAmount;
        this.triggerElement = triggerElement || document.activeElement; // Store the trigger
        
        // Update modal with order details
        this.updateOrderSummary(totalAmount, items);
        
        // Set form values
        const orderIdField = document.getElementById('payment-order-id');
        if (orderIdField)
        {
            orderIdField.value = orderId;
        }
        
        // Get CSRF token from meta tag
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrfTokenField = document.getElementById('payment-csrf-token');
        
        if (csrfMeta && csrfTokenField)
        {
            csrfTokenField.value = csrfMeta.getAttribute('content');
        }
        
        // Reset form state
        this.resetForm();
        
        // Show modal
        this.modal.style.display = 'flex';
        this.modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        
        // Focus on first input
        const firstInput = this.modal.querySelector('select, input');
        if (firstInput)
        {
            firstInput.focus();
        }
    }
    
    /**
     * Updates the order summary section of the modal
     * 
     * @param {number} totalAmount - Total order amount
     * @param {Object} items - Array of order items
     */
    updateOrderSummary(totalAmount, items)
    {
        const summaryContainer = document.getElementById('payment-order-summary');
        
        if (!summaryContainer)
        {
            return;
        }
        
        let itemsHtml = '<div class="order-items-list">';
        
        if (items && items.length > 0)
        {
            items.forEach(item =>
            {
                // Safe numeric operations for price and quantity
                const itemPrice = Number(item.price) || 0;
                const itemQty = Number(item.quantity) || 1;
                const itemTotal = itemPrice * itemQty;
                itemsHtml += `
                    <div class="order-item">
                        <span>${this.escapeHtml(item.name || 'Item')} x ${itemQty}</span>
                        <span>R ${itemTotal.toFixed(2)}</span>
                    </div>
                `;
            });
        }
        
        itemsHtml += '</div>';
        itemsHtml += `
            <div class="order-total">
                <strong>Total Amount:</strong>
                <strong>R ${totalAmount.toFixed(2)}</strong>
            </div>
        `;
        
        summaryContainer.innerHTML = itemsHtml;
    }
    
    /**
     * Validates payment method selection
     */
    validatePaymentMethod()
    {
        const methodSelect = document.getElementById('payment-method');
        const errorElement = document.getElementById('payment-method-error');
        
        if (methodSelect && errorElement)
        {
            if (!methodSelect.value)
            {
                errorElement.textContent = 'Please select a payment method';
                methodSelect.classList.add('is-invalid');
                return false;
            }
            else
            {
                errorElement.textContent = '';
                methodSelect.classList.remove('is-invalid');
                return true;
            }
        }
        
        return true;
    }
    
    /**
     * Validates the entire form before submission
     * 
     * @returns {boolean} True if form is valid
     */
    validateForm()
    {
        let isValid = true;
        
        // Validate payment method
        if (!this.validatePaymentMethod())
        {
            isValid = false;
        }
        
        // Validate pickup time
        const pickupTime = document.getElementById('pickup-time');
        if (pickupTime && !pickupTime.value)
        {
            isValid = false;
        }
        
        return isValid;
    }
    
    /**
     * Submits the payment to the server
     */
    async submitPayment()
    {
        // Prevent multiple submissions
        if (this.isProcessing)
        {
            return;
        }
        
        // Validate form
        if (!this.validateForm())
        {
            this.showNotification('Please complete all required fields', 'error');
            return;
        }
        
        this.isProcessing = true;
        this.showLoading(true);
        
        // Collect form data
        const form = document.getElementById('payment-form');
        const formData = new FormData(form);
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
        const apiUrl = baseUrl + '/api/process_payment.php';
        
        try
        {
            const response = await fetch(apiUrl,
            {
                method: 'POST',
                body: formData,
                headers:
                {
                    'X-Requested-With': 'XMLHttpRequest',
                    // Bug 7: Added CSRF header for consistency
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
                }
            });
            
            const data = await response.json();
            
            if (data.success)
            {
                this.handlePaymentSuccess(data);
            }
            else
            {
                this.handlePaymentError(data.message || 'Payment processing failed');
            }
        }
        catch (error)
        {
            console.error('Payment error:', error);
            this.handlePaymentError('Network error. Please try again.');
        }
        finally
        {
            this.isProcessing = false;
            this.showLoading(false);
        }
    }
    
    /**
     * Handles successful payment response
     * 
     * @param {Object} data - Response data from server
     */
    handlePaymentSuccess(data)
    {
        this.closeModal();
        this.showNotification('Payment successful! Your order has been placed.', 'success');
        
        // =========================================================================
        // UX FIX: Use BASE_URL for redirect instead of hardcoded path
        // =========================================================================
        // The previous code used a hardcoded absolute path that would break if the
        // application was deployed to a subdirectory or a different domain.
        // This now uses the BASE_URL constant for portability.
        // Source: Full Code Review Report - Section 1.7
        // =========================================================================
        const baseUrl = typeof BASE_URL !== 'undefined' ? BASE_URL : '';
        const redirectUrl = baseUrl + '/modules/student/order_tracking.php?order_id=' + this.currentOrderId;
        
        setTimeout(() =>
        {
            window.location.href = redirectUrl;
        }, 2000);
    }
    
    /**
     * Handles payment error response
     * 
     * @param {string} message - Error message to display
     */
    handlePaymentError(message)
    {
        this.showNotification(message, 'error');
        
        // Re-enable submit button
        const confirmBtn = document.getElementById('confirm-payment-btn');
        if (confirmBtn)
        {
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirm Payment';
        }
    }
    
    /**
     * Shows or hides loading state
     * 
     * @param {boolean} show - True to show loading, false to hide
     */
    showLoading(show)
    {
        const loadingElement = document.getElementById('payment-loading');
        const confirmBtn = document.getElementById('confirm-payment-btn');
        const cancelBtn = document.getElementById('cancel-payment-btn');
        
        if (loadingElement)
        {
            loadingElement.style.display = show ? 'block' : 'none';
        }
        
        if (confirmBtn)
        {
            confirmBtn.disabled = show;
            confirmBtn.textContent = show ? 'Processing...' : 'Confirm Payment';
        }
        
        if (cancelBtn)
        {
            cancelBtn.disabled = show;
        }
    }
    
    /**
     * Resets the form to default state
     */
    resetForm()
    {
        const form = document.getElementById('payment-form');
        if (form)
        {
            form.reset();
        }
        
        const errorElement = document.getElementById('payment-method-error');
        if (errorElement)
        {
            errorElement.textContent = '';
        }
        
        const methodSelect = document.getElementById('payment-method');
        if (methodSelect)
        {
            methodSelect.classList.remove('is-invalid');
        }
    }
    
    /**
     * Closes the payment modal
     */
    closeModal()
    {
        this.modal.style.display = 'none';
        this.modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        this.resetForm();
        
        // =========================================================================
        // ACCESSIBILITY FIX: Return focus to the triggering element
        // =========================================================================
        // WCAG 2.1 Success Criterion 2.4.3: Focus Order
        // When a modal is closed, focus should return to the element that opened it.
        // Source: Full Code Review Report - Section 2.7
        // =========================================================================
        if (this.triggerElement && typeof this.triggerElement.focus === 'function')
        {
            this.triggerElement.focus();
            this.triggerElement = null; // Clear after use
        }
        else
        {
            // Fallback: focus on the body or a known safe element
            document.body.focus();
        }
    }
    
    /**
     * Displays a notification to the user
     * 
     * @param {string} message - Notification message
     * @param {string} type - Notification type (success, error, warning, info)
     */
    showNotification(message, type)
    {
        // Use global showNotification if available, otherwise create temporary
        if (typeof window.showNotification === 'function')
        {
            window.showNotification(message, type);
        }
        else
        {
            // Fallback temporary notification
            const notification = document.createElement('div');
            notification.style.position = 'fixed';
            notification.style.bottom = '20px';
            notification.style.right = '20px';
            notification.style.backgroundColor = type === 'success' ? '#28A745' : '#DC3545';
            notification.style.color = 'white';
            notification.style.padding = '12px 20px';
            notification.style.borderRadius = '8px';
            notification.style.zIndex = '10000';
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => notification.remove(), 3000);
        }
    }
    
    /**
     * HTML escape function to prevent XSS
     * 
     * @param {string} str - String to escape
     * @returns {string} Escaped string
     */
    escapeHtml(str)
    {
        if (!str) return '';
        
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
}

/**
 * Initialize the payment modal manager when the page loads
 */
let paymentModalManager = null;

function initPaymentModal()
{
    if (!paymentModalManager)
    {
        paymentModalManager = new PaymentModalManager();
    }
}

// Auto-initialize if script is loaded directly
if (document.readyState === 'loading')
{
    document.addEventListener('DOMContentLoaded', initPaymentModal);
}
else
{
    initPaymentModal();
}

// =========================================================================
// EXPORT FIX: Ensure the manager is exported after initialization
// =========================================================================
// The previous code exported paymentModalManager while it was still null.
// This ensures the export happens after the manager is created.
// Source: Full Code Review Report - Section 2.6
// =========================================================================
window.PaymentModalManager = PaymentModalManager;
// The following line now uses a getter to ensure the latest instance is returned.
Object.defineProperty(window, 'paymentModalManager', {
    get: function() {
        return paymentModalManager;
    },
    set: function(value) {
        paymentModalManager = value;
    },
    configurable: true,
    enumerable: true
});