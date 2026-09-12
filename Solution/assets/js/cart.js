/**
 * Shopping Cart JavaScript for Campus Eats
 *
 * This file handles all shopping cart functionality including:
 * - Adding items to cart
 * - Removing items from cart
 * - Direct quantity input editing with +/- buttons
 * - Calculating subtotals and totals
 * - Persisting cart data across page refreshes
 * - Checkout button event handling and order submission
 *
 * SOURCE: campus-eats-process-document.pdf (Section 6.1 - Cart management)
 * SOURCE: Mockups - Cart design
 *
 * @version 41.0
 */

(function()
{
    'use strict';

    // =========================================================================
    // Constants
    // =========================================================================
    const CART_STORAGE_KEY = 'campus_eats_cart';
    const DEBOUNCE_DELAY = 300;
    const MAX_RETRY_ATTEMPTS = 3;
    const RETRY_DELAY = 500;

    // Track pending operations
    let pendingOperation = null;
    let operationQueue = [];
    let debounceTimers = {};
    let retryCount = 0;

    // Track CSRF token version for debugging
    let csrfTokenVersion = 0;
    let lastCsrfRefreshTime = 0;
    const CSRF_REFRESH_INTERVAL = 60000;

    // =========================================================================
    // Utility Functions
    // =========================================================================

    /**
     * Escapes HTML special characters to prevent XSS attacks.
     *
     * @param {string} text The text to escape
     * @returns {string} The escaped text
     */
    function escapeHtml(text)
    {
        if (!text) return '';
        const str = String(text);
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Safely formats a number for display.
     *
     * @param {*} value The value to format
     * @param {number} defaultValue The default value if parsing fails
     * @returns {number} A valid number
     */
    function safeNumber(value, defaultValue)
    {
        const num = parseFloat(value);
        return isNaN(num) ? (defaultValue || 0) : num;
    }

    /**
     * Formats a number as currency (South African Rand).
     *
     * @param {number} amount The amount to format
     * @returns {string} Formatted currency string
     */
    function formatCurrency(amount)
    {
        const num = safeNumber(amount, 0);
        return 'R ' + num.toFixed(2);
    }

    /**
     * Gets the CSRF token from the meta tag.
     * Also checks session storage as fallback.
     *
     * @returns {string} The CSRF token
     */
    function getCsrfToken()
    {
        // Primary source: meta tag
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag && metaTag.getAttribute('content'))
        {
            return metaTag.getAttribute('content');
        }

        // Fallback: session storage
        const storedToken = sessionStorage.getItem('csrf_token');
        if (storedToken)
        {
            console.log('CSRF token loaded from session storage');
            return storedToken;
        }

        console.warn('No CSRF token found');
        return '';
    }

    /**
     * Updates the CSRF token in the meta tag, session storage, and all forms.
     *
     * @param {string} newToken The new CSRF token
     * @param {number} version The token version (for debugging)
     */
    function updateCsrfToken(newToken, version)
    {
        if (!newToken) return;

        // Update meta tag
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag)
        {
            metaTag.setAttribute('content', newToken);
        }

        // Update session storage
        sessionStorage.setItem('csrf_token', newToken);

        // Update all hidden inputs
        const inputs = document.querySelectorAll('input[name="csrf_token"]');
        for (let i = 0; i < inputs.length; i++)
        {
            inputs[i].value = newToken;
        }

        // Update token version
        if (version !== undefined)
        {
            csrfTokenVersion = version;
        }
        else
        {
            csrfTokenVersion++;
        }

        lastCsrfRefreshTime = Date.now();

        console.log('CSRF token updated (version: ' + csrfTokenVersion + ')');
    }

    /**
     * Shows a notification message with animation.
     *
     * @param {string} message The message to display
     * @param {string} type The notification type
     */
    function showNotification(message, type)
    {
        // Remove existing notification
        const existingNotification = document.querySelector('.notification');
        if (existingNotification) existingNotification.remove();

        // Create notification element
        const notification = document.createElement('div');
        notification.className = 'notification notification-' + type;
        notification.setAttribute('role', 'alert');
        notification.setAttribute('aria-live', 'polite');

        notification.textContent = message;

        // Apply styles
        notification.style.position = 'fixed';
        notification.style.bottom = '20px';
        notification.style.right = '20px';
        notification.style.padding = '15px 25px';
        notification.style.borderRadius = '8px';
        notification.style.zIndex = '9999';
        notification.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
        notification.style.animation = 'slideIn 0.3s ease';

        // Set color based on type
        const colors = {
            'success': '#28a745',
            'error': '#dc3545',
            'warning': '#ffc107',
            'info': '#17a2b8'
        };

        notification.style.backgroundColor = colors[type] || colors.info;
        notification.style.color = type === 'warning' ? '#212529' : '#fff';

        // Add to DOM
        document.body.appendChild(notification);

        // Auto-remove after 3 seconds
        setTimeout(function()
        {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(function() { notification.remove(); }, 300);
        }, 3000);
    }

    /**
     * Processes the next operation in the queue.
     */
    function processQueue()
    {
        if (pendingOperation === null && operationQueue.length > 0)
        {
            const nextOperation = operationQueue.shift();
            pendingOperation = nextOperation;
            nextOperation();
        }
    }

    /**
     * Enqueues an operation for sequential execution.
     *
     * @param {Function} operation The operation to enqueue
     */
    function enqueueOperation(operation)
    {
        operationQueue.push(operation);
        processQueue();
    }

    /**
     * Completes the current operation and processes the queue.
     */
    function completeOperation()
    {
        pendingOperation = null;
        processQueue();
    }

    /**
     * Validates a quantity value against constraints.
     *
     * @param {*} value The quantity value
     * @param {number} maxQuantity The maximum allowed quantity
     * @returns {Object} Validation result
     */
    function validateQuantity(value, maxQuantity)
    {
        let parsedValue = parseInt(value, 10);

        if (isNaN(parsedValue))
        {
            return { valid: false, quantity: 1, error: 'Please enter a valid number.' };
        }

        if (parsedValue < 1)
        {
            return { valid: false, quantity: 1, error: 'Quantity must be at least 1.' };
        }

        if (parsedValue > maxQuantity)
        {
            return { valid: false, quantity: maxQuantity, error: 'Only ' + maxQuantity + ' available in stock.' };
        }

        return { valid: true, quantity: parsedValue, error: null };
    }

    // =========================================================================
    // CartItem Class
    // =========================================================================

    class CartItem
    {
        constructor(itemId, name, price, vendorId, vendorName, quantity, maxQuantity)
        {
            this.item_id = parseInt(itemId, 10) || 0;
            this.name = name || 'Item';
            this.price = safeNumber(price, 0);
            this.vendor_id = parseInt(vendorId, 10) || 0;
            this.vendor_name = vendorName || 'Vendor';
            this.quantity = Math.max(1, parseInt(quantity, 10) || 1);
            this.max_quantity = parseInt(maxQuantity, 10) || 999;
        }

        get subtotal()
        {
            return Number((this.price * this.quantity).toFixed(2));
        }

        toJSON()
        {
            return {
                item_id: this.item_id,
                name: this.name,
                price: this.price,
                vendor_id: this.vendor_id,
                vendor_name: this.vendor_name,
                quantity: this.quantity,
                max_quantity: this.max_quantity
            };
        }
    }

    // =========================================================================
    // ShoppingCart Class - CORRECTED with CSRF Token Management
    // =========================================================================

    class ShoppingCart
    {
        constructor()
        {
            this.items = [];
            this.isUpdating = false;
            this.isInitialized = false;
            this.csrfToken = getCsrfToken();

            // Initialize token if not present
            if (!this.csrfToken)
            {
                this.csrfToken = this.generateInitialCsrfToken();
                updateCsrfToken(this.csrfToken);
            }

            this.loadFromStorage();

            console.log('Cart initialized with', this.items.length, 'items');
            console.log('CSRF token version:', csrfTokenVersion);

            this.cartContainer = document.getElementById('cart-container');

            // Use a single event listener with proper delegation
            if (this.cartContainer)
            {
                this.cartContainer.removeEventListener('click', this.handleContainerClick.bind(this));
                this.cartContainer.addEventListener('click', this.handleContainerClick.bind(this));

                this.cartContainer.removeEventListener('change', this.handleContainerChange.bind(this));
                this.cartContainer.addEventListener('change', this.handleContainerChange.bind(this));
            }

            // Listen for cart updates
            document.addEventListener('cartUpdated', function(event)
            {
                console.log('Cart updated event received:', event.detail);
                if (event.detail && event.detail.items)
                {
                    this.updateCartDisplay();
                }
            }.bind(this));

            this.isInitialized = true;
        }

        /**
         * Generates an initial CSRF token if none exists.
         *
         * @returns {string} A generated CSRF token
         */
        generateInitialCsrfToken()
        {
            const array = new Uint8Array(32);
            crypto.getRandomValues(array);
            return Array.from(array, function(byte)
            {
                return byte.toString(16).padStart(2, '0');
            }).join('');
        }

        /**
         * Handles click events on the cart container using event delegation.
         *
         * @param {Event} event The click event
         */
        handleContainerClick(event)
        {
            const target = event.target;

            const incrementBtn = target.closest('.increment-btn');
            if (incrementBtn)
            {
                event.preventDefault();
                event.stopPropagation();

                const index = parseInt(incrementBtn.getAttribute('data-index'), 10);
                console.log('Increment button clicked for item index:', index);

                if (!isNaN(index) && index >= 0 && index < this.items.length)
                {
                    this.refreshCsrfToken(function()
                    {
                        this.incrementQuantity(index);
                    }.bind(this));
                }
                else
                {
                    console.warn('Invalid index for increment:', index);
                }
                return;
            }

            const decrementBtn = target.closest('.decrement-btn');
            if (decrementBtn)
            {
                event.preventDefault();
                event.stopPropagation();

                const index = parseInt(decrementBtn.getAttribute('data-index'), 10);
                console.log('Decrement button clicked for item index:', index);

                if (!isNaN(index) && index >= 0 && index < this.items.length)
                {
                    this.refreshCsrfToken(function()
                    {
                        this.decrementQuantity(index);
                    }.bind(this));
                }
                else
                {
                    console.warn('Invalid index for decrement:', index);
                }
                return;
            }

            const removeBtn = target.closest('.remove-btn');
            if (removeBtn)
            {
                event.preventDefault();
                event.stopPropagation();

                const index = parseInt(removeBtn.getAttribute('data-index'), 10);
                console.log('Remove button clicked for item index:', index);

                if (!isNaN(index) && index >= 0 && index < this.items.length)
                {
                    this.refreshCsrfToken(function()
                    {
                        this.removeItem(index);
                    }.bind(this));
                }
                else
                {
                    console.warn('Invalid index for removal:', index);
                }
                return;
            }

            const checkoutBtn = target.closest('#proceed-to-checkout-btn') || target.closest('.checkout-btn');
            if (checkoutBtn)
            {
                event.preventDefault();
                console.log('Checkout button clicked');
                this.refreshCsrfToken(function()
                {
                    this.proceedToCheckout();
                }.bind(this));
                return;
            }

            const clearCartBtn = target.closest('.clear-cart-btn');
            if (clearCartBtn)
            {
                event.preventDefault();
                if (confirm('Are you sure you want to clear your entire cart?'))
                {
                    this.refreshCsrfToken(function()
                    {
                        this.clearCart();
                    }.bind(this));
                }
                return;
            }
        }

        /**
         * Handles change events on the cart container.
         *
         * @param {Event} event The change event
         */
        handleContainerChange(event)
        {
            const target = event.target;

            if (target.classList && target.classList.contains('quantity-input'))
            {
                const index = parseInt(target.getAttribute('data-index'), 10);
                const value = parseInt(target.value, 10);

                console.log('Quantity input changed for item index:', index, 'New value:', value);

                if (!isNaN(index) && index >= 0 && index < this.items.length)
                {
                    if (isNaN(value) || value < 1)
                    {
                        this.refreshCsrfToken(function()
                        {
                            this.removeItem(index);
                        }.bind(this));
                    }
                    else
                    {
                        const item = this.items[index];
                        const maxQty = item.max_quantity || 999;

                        if (value > maxQty)
                        {
                            showNotification('Only ' + maxQty + ' available in stock.', 'warning');
                            target.value = item.quantity;
                            return;
                        }

                        this.refreshCsrfToken(function()
                        {
                            this.updateQuantity(index, value);
                        }.bind(this));
                    }
                }
            }
        }

        /**
         * Refreshes the CSRF token by fetching a new one from the server.
         *
         * @param {Function} callback Optional callback after refresh
         */
        refreshCsrfToken(callback)
        {
            const baseUrl = getBaseUrl();

            const now = Date.now();
            if (this.csrfToken && (now - lastCsrfRefreshTime) < CSRF_REFRESH_INTERVAL)
            {
                console.log('CSRF token is still fresh (age: ' + ((now - lastCsrfRefreshTime) / 1000) + 's)');
                if (callback) callback();
                return;
            }

            console.log('Refreshing CSRF token...');

            const url = baseUrl + '/api/get_csrf_token.php?refresh=true&t=' + now;

            fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                credentials: 'same-origin'
            })
            .then(function(response)
            {
                if (!response.ok)
                {
                    throw new Error('Failed to refresh CSRF token: ' + response.status);
                }
                return response.json();
            })
            .then(function(data)
            {
                if (data.success && data.csrf_token)
                {
                    this.csrfToken = data.csrf_token;
                    updateCsrfToken(this.csrfToken, data.version);
                    console.log('CSRF token refreshed successfully (version: ' + data.version + ')');
                }
                else
                {
                    console.warn('CSRF token refresh returned no token, using existing');
                }

                if (callback)
                {
                    callback();
                }
            }.bind(this))
            .catch(function(error)
            {
                console.error('Error refreshing CSRF token:', error);
                if (callback)
                {
                    callback();
                }
            }.bind(this));
        }

        /**
         * Proceeds to checkout.
         */
        proceedToCheckout()
        {
            const form = document.getElementById('checkout-form');
            if (form)
            {
                this.csrfToken = getCsrfToken();
                const tokenInput = form.querySelector('input[name="csrf_token"]');
                if (tokenInput)
                {
                    tokenInput.value = this.csrfToken;
                }
                console.log('Submitting checkout form with CSRF token (version: ' + csrfTokenVersion + ')');
                form.submit();
            }
            else
            {
                console.error('Checkout form not found');
                showNotification('Checkout form not found. Please refresh the page.', 'error');
            }
        }

        /**
         * Loads cart data from session storage or server.
         */
        loadFromStorage()
        {
            const stored = sessionStorage.getItem(CART_STORAGE_KEY);
            if (stored)
            {
                try
                {
                    const parsed = JSON.parse(stored);
                    this.items = parsed.map(function(item)
                    {
                        return new CartItem(
                            item.item_id,
                            item.name,
                            item.price,
                            item.vendor_id,
                            item.vendor_name,
                            item.quantity,
                            item.max_quantity
                        );
                    });
                    console.log('Cart loaded from sessionStorage:', this.items.length, 'items');
                    return;
                }
                catch (e)
                {
                    console.error('Error loading cart from sessionStorage:', e);
                }
            }

            this.fetchCartFromServer();
        }

        /**
         * Fetches cart data from the server.
         */
        fetchCartFromServer()
        {
            console.log('Fetching cart from server...');
            const baseUrl = getBaseUrl();

            this.csrfToken = getCsrfToken();

            const url = baseUrl + '/api/get_cart.php';

            fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                credentials: 'same-origin'
            })
            .then(function(response)
            {
                if (!response.ok)
                {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(function(data)
            {
                if (data.csrf_token)
                {
                    this.csrfToken = data.csrf_token;
                    updateCsrfToken(this.csrfToken);
                }

                if (data.success && data.cart)
                {
                    this.items = data.cart.map(function(item)
                    {
                        return new CartItem(
                            item.item_id,
                            item.name || item.item_name || 'Item',
                            item.price || 0,
                            item.vendor_id,
                            item.vendor_name,
                            item.quantity,
                            item.max_quantity || 999
                        );
                    });
                    console.log('Cart loaded from server:', this.items.length, 'items');
                    this.saveToStorage();
                    this.updateCartDisplay();
                    this.updateCartBadge(this.items.length);
                }
                else
                {
                    console.warn('No cart data from server:', data.message);
                    this.items = [];
                    this.updateCartDisplay();
                }
            }.bind(this))
            .catch(function(error)
            {
                console.error('Error fetching cart from server:', error);
                if (!this.items || this.items.length === 0)
                {
                    this.items = [];
                    this.updateCartDisplay();
                }
            }.bind(this));
        }

        /**
         * Saves cart data to session storage.
         */
        saveToStorage()
        {
            try
            {
                sessionStorage.setItem(CART_STORAGE_KEY, JSON.stringify(this.items));
                console.log('Cart saved to sessionStorage:', this.items.length, 'items');
            }
            catch (e)
            {
                console.error('Error saving cart to storage:', e);
                showNotification('Unable to save cart. Storage may be full.', 'warning');
            }
        }

        /**
         * Makes an API call to update_cart.php with proper CSRF handling.
         *
         * @param {string} action The action to perform (add, remove, update, clear)
         * @param {Object} data The request data
         * @param {Function} onSuccess Callback on success
         * @param {Function} onError Callback on error
         * @param {number} attempt The current attempt number
         */
        callCartApi(action, data, onSuccess, onError, attempt)
        {
            attempt = attempt || 0;
            const baseUrl = getBaseUrl();

            this.csrfToken = getCsrfToken();

            const requestData = {
                action: action,
                csrf_token: this.csrfToken
            };

            for (var key in data)
            {
                if (data.hasOwnProperty(key))
                {
                    requestData[key] = data[key];
                }
            }

            console.log('Cart API request:', action, 'Token version:', csrfTokenVersion, 'Attempt:', attempt + 1);

            const url = baseUrl + '/api/update_cart.php';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                credentials: 'same-origin',
                body: JSON.stringify(requestData)
            })
            .then(function(response)
            {
                console.log('Cart API response status:', response.status);
                return response.json();
            })
            .then(function(result)
            {
                console.log('Cart API response data:', result);

                if (result.csrf_token)
                {
                    this.csrfToken = result.csrf_token;
                    updateCsrfToken(this.csrfToken, result.csrf_version);
                    console.log('CSRF token updated from response (version: ' + csrfTokenVersion + ')');
                }

                if (result.success === false &&
                    (result.message && (
                        result.message.indexOf('CSRF') !== -1 ||
                        result.message.indexOf('Security') !== -1 ||
                        result.message.indexOf('validation') !== -1
                    )))
                {
                    console.warn('CSRF validation failed, refreshing token and retrying');
                    this.refreshCsrfToken(function()
                    {
                        if (attempt < MAX_RETRY_ATTEMPTS)
                        {
                            console.log('Retrying API call, attempt ' + (attempt + 2) + ' of ' + MAX_RETRY_ATTEMPTS);
                            setTimeout(function()
                            {
                                this.callCartApi(action, data, onSuccess, onError, attempt + 1);
                            }.bind(this), RETRY_DELAY * (attempt + 1));
                        }
                        else
                        {
                            showNotification('Security validation failed after multiple attempts. Please refresh the page.', 'error');
                            if (onError) onError(result);
                        }
                    }.bind(this));
                    return;
                }

                var isSuccess = result.success === true ||
                               result.cart !== undefined ||
                               result.cart_count !== undefined ||
                               result.items !== undefined;

                if (isSuccess)
                {
                    if (onSuccess)
                    {
                        onSuccess(result);
                    }
                }
                else
                {
                    console.warn('Cart API call failed:', result.message);
                    if (onError)
                    {
                        onError(result);
                    }
                    else
                    {
                        showNotification(result.message || 'An error occurred.', 'error');
                    }
                }
            }.bind(this))
            .catch(function(error)
            {
                console.error('Cart API network error:', error);

                if (attempt < MAX_RETRY_ATTEMPTS)
                {
                    console.log('Retrying API call, attempt ' + (attempt + 2) + ' of ' + MAX_RETRY_ATTEMPTS);
                    this.refreshCsrfToken(function()
                    {
                        setTimeout(function()
                        {
                            this.callCartApi(action, data, onSuccess, onError, attempt + 1);
                        }.bind(this), RETRY_DELAY * (attempt + 1));
                    }.bind(this));
                }
                else
                {
                    showNotification('Network error. Please check your connection and try again.', 'error');
                    if (onError)
                    {
                        onError({ success: false, message: 'Network error. Please try again.' });
                    }
                }
            }.bind(this));
        }

        /**
         * Adds an item to the cart.
         *
         * @param {Object|CartItem} item The item to add
         */
        addItem(item)
        {
            if (this.isUpdating) return;

            const self = this;
            enqueueOperation(function()
            {
                self.isUpdating = true;

                console.log('Adding item to cart:', item);

                self.callCartApi(
                    'add',
                    {
                        item_id: item.item_id,
                        quantity: item.quantity || 1
                    },
                    function(result)
                    {
                        console.log('Add item success:', result);
                        self.fetchCartFromServer();
                        self.updateCartDisplay();
                        self.updateCartBadge(self.items.length);
                        self.notifyCartUpdated();
                        showNotification(item.name + ' added to cart!', 'success');
                        self.isUpdating = false;
                        completeOperation();
                    },
                    function(result)
                    {
                        console.warn('Add item failed:', result);
                        showNotification(result.message || 'Error adding item to cart.', 'error');
                        self.isUpdating = false;
                        completeOperation();
                    }
                );
            });
        }

        /**
         * Removes an item from the cart.
         *
         * @param {number} index The index of the item to remove
         */
        removeItem(index)
        {
            if (this.isUpdating) return;

            const self = this;
            enqueueOperation(function()
            {
                self.isUpdating = true;

                const item = self.items[index];
                if (!item)
                {
                    console.warn('Item not found at index:', index);
                    self.isUpdating = false;
                    completeOperation();
                    return;
                }

                console.log('Removing item at index:', index, item.name);

                self.callCartApi(
                    'remove',
                    { index: index },
                    function(result)
                    {
                        console.log('Remove item success:', result);
                        self.items.splice(index, 1);
                        self.saveToStorage();
                        self.updateCartDisplay();
                        self.updateCartBadge(self.items.length);
                        showNotification(item.name + ' removed from cart.', 'success');
                        self.notifyCartUpdated();
                        self.isUpdating = false;
                        completeOperation();
                    },
                    function(result)
                    {
                        console.warn('Remove item failed:', result);
                        showNotification(result.message || 'Error removing item.', 'error');
                        self.isUpdating = false;
                        completeOperation();
                    }
                );
            });
        }

        /**
         * Updates the quantity of an item in the cart.
         *
         * @param {number} index The index of the item in the cart
         * @param {number} quantity The new quantity
         */
        updateQuantity(index, quantity)
        {
            if (this.isUpdating) return;

            const self = this;
            enqueueOperation(function()
            {
                self.isUpdating = true;

                console.log('Updating quantity: index=' + index + ', quantity=' + quantity);

                if (index < 0 || index >= self.items.length)
                {
                    console.error('Invalid index:', index);
                    showNotification('Item not found in cart.', 'error');
                    self.isUpdating = false;
                    completeOperation();
                    return;
                }

                const item = self.items[index];
                const maxQty = item.max_quantity || 999;

                if (quantity < 1)
                {
                    self.removeItem(index);
                    self.isUpdating = false;
                    completeOperation();
                    return;
                }

                if (quantity > maxQty)
                {
                    showNotification('Only ' + maxQty + ' available in stock.', 'warning');
                    self.isUpdating = false;
                    completeOperation();
                    return;
                }

                console.log('Calling API to update quantity for:', item.name, 'to', quantity);

                self.callCartApi(
                    'update',
                    {
                        index: index,
                        quantity: quantity
                    },
                    function(result)
                    {
                        console.log('Update quantity success:', result);
                        if (self.items[index])
                        {
                            self.items[index].quantity = quantity;
                            if (result.max_quantity !== undefined)
                            {
                                self.items[index].max_quantity = result.max_quantity;
                            }
                            self.saveToStorage();
                        }
                        self.fetchCartFromServer();
                        self.updateCartDisplay();
                        self.updateCartBadge(self.items.length);
                        self.notifyCartUpdated();
                        self.isUpdating = false;
                        completeOperation();
                    },
                    function(result)
                    {
                        console.warn('Update quantity failed:', result);
                        showNotification(result.message || 'Error updating quantity. Please try again.', 'error');
                        self.isUpdating = false;
                        completeOperation();
                    }
                );
            });
        }

        /**
         * Increases the quantity of an item by 1.
         *
         * @param {number} index The index of the item
         */
        incrementQuantity(index)
        {
            console.log('Increment quantity called for index:', index);

            if (this.isUpdating)
            {
                console.log('Cart is updating, increment deferred');
                return;
            }

            if (index < 0 || index >= this.items.length)
            {
                console.error('Invalid index for increment:', index);
                return;
            }

            const item = this.items[index];
            const newQuantity = item.quantity + 1;
            const maxQty = item.max_quantity || 999;

            console.log('Incrementing quantity for:', item.name, 'from', item.quantity, 'to', newQuantity, 'max:', maxQty);

            if (newQuantity > maxQty)
            {
                showNotification('Only ' + maxQty + ' available in stock.', 'warning');
                return;
            }

            const quantityInput = document.querySelector('.quantity-input[data-index="' + index + '"]');
            if (quantityInput)
            {
                quantityInput.value = newQuantity;
            }

            this.updateQuantity(index, newQuantity);
        }

        /**
         * Decreases the quantity of an item by 1.
         *
         * @param {number} index The index of the item
         */
        decrementQuantity(index)
        {
            console.log('Decrement quantity called for index:', index);

            if (this.isUpdating)
            {
                console.log('Cart is updating, decrement deferred');
                return;
            }

            if (index < 0 || index >= this.items.length)
            {
                console.error('Invalid index for decrement:', index);
                return;
            }

            const item = this.items[index];
            const newQuantity = item.quantity - 1;

            console.log('Decrementing quantity for:', item.name, 'from', item.quantity, 'to', newQuantity);

            if (newQuantity < 1)
            {
                this.removeItem(index);
                return;
            }

            const quantityInput = document.querySelector('.quantity-input[data-index="' + index + '"]');
            if (quantityInput)
            {
                quantityInput.value = newQuantity;
            }

            this.updateQuantity(index, newQuantity);
        }

        /**
         * Clears all items from the cart.
         */
        clearCart()
        {
            if (this.isUpdating) return;

            const self = this;
            enqueueOperation(function()
            {
                self.isUpdating = true;

                console.log('Clearing cart');

                self.callCartApi(
                    'clear',
                    {},
                    function(result)
                    {
                        console.log('Clear cart success:', result);
                        self.items = [];
                        self.saveToStorage();
                        self.updateCartDisplay();
                        self.updateCartBadge(0);
                        showNotification('Cart cleared successfully.', 'success');
                        self.notifyCartUpdated();
                        self.isUpdating = false;
                        completeOperation();
                    },
                    function(result)
                    {
                        console.warn('Clear cart failed:', result);
                        showNotification(result.message || 'Error clearing cart.', 'error');
                        self.isUpdating = false;
                        completeOperation();
                    }
                );
            });
        }

        /**
         * Returns the total number of items in the cart.
         *
         * @returns {number} Total item count
         */
        getTotalItemCount()
        {
            return this.items.reduce(function(total, item)
            {
                return total + item.quantity;
            }, 0);
        }

        /**
         * Returns the total price of all items in the cart.
         *
         * @returns {number} Total price
         */
        getTotalPrice()
        {
            const total = this.items.reduce(function(total, item)
            {
                return total + item.subtotal;
            }, 0);
            return Number(total.toFixed(2));
        }

        /**
         * Returns the vendor ID of the cart.
         *
         * @returns {number|null} Vendor ID or null if empty
         */
        getCartVendorId()
        {
            if (this.items.length === 0) return null;
            return this.items[0].vendor_id;
        }

        /**
         * Checks if all items are from the same vendor.
         *
         * @returns {boolean} True if all items are from same vendor
         */
        isSingleVendor()
        {
            if (this.items.length <= 1) return true;
            const firstVendor = this.items[0].vendor_id;
            return this.items.every(function(item)
            {
                return item.vendor_id === firstVendor;
            });
        }

        /**
         * Dispatches a cart updated event.
         */
        notifyCartUpdated()
        {
            const event = new CustomEvent('cartUpdated',
            {
                detail: {
                    itemCount: this.getTotalItemCount(),
                    totalPrice: this.getTotalPrice(),
                    items: this.items
                }
            });
            document.dispatchEvent(event);
            this.updateCartDisplay();
        }

        /**
         * Updates the cart display with proper +/- buttons and subtotal calculation.
         */
        updateCartDisplay()
        {
            const cartContainer = document.getElementById('cart-container');
            if (!cartContainer)
            {
                console.warn('Cart container not found');
                return;
            }

            console.log('Updating cart display with', this.items.length, 'items');

            if (this.items.length === 0)
            {
                cartContainer.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your Cart is Empty</h3>
                        <p>Add some delicious items from our vendors!</p>
                        <a href="dashboard.php" class="btn btn-primary">Browse Vendors</a>
                    </div>
                `;
                this.updateCartBadge(0);
                return;
            }

            let html = `
                <div class="cart-header">
                    <span>ITEM</span>
                    <span>PRICE</span>
                    <span>QUANTITY</span>
                    <span>SUBTOTAL</span>
                    <span></span>
                </div>
            `;

            for (let i = 0; i < this.items.length; i++)
            {
                const item = this.items[i];
                const price = safeNumber(item.price, 0);
                const subtotal = safeNumber(item.subtotal, 0);
                const quantity = safeNumber(item.quantity, 1);
                const maxQty = safeNumber(item.max_quantity, 999);

                const formattedPrice = price.toFixed(2);
                const formattedSubtotal = subtotal.toFixed(2);

                const escapedItemName = escapeHtml(item.name);
                const escapedVendorName = escapeHtml(item.vendor_name);

                html += `
                    <div class="cart-item-row" data-index="${i}">
                        <div class="cart-item-name">
                            <strong>${escapedItemName}</strong>
                            <small class="vendor-name">${escapedVendorName}</small>
                        </div>
                        <div class="cart-item-price">R ${formattedPrice}</div>
                        <div class="cart-item-quantity">
                            <button class="btn btn-outline btn-sm decrement-btn" data-index="${i}" aria-label="Decrease quantity" type="button">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="quantity-input" data-index="${i}"
                                   value="${quantity}" min="1" max="${escapeHtml(maxQty)}"
                                   aria-label="Quantity for ${escapedItemName}" step="1">
                            <button class="btn btn-outline btn-sm increment-btn" data-index="${i}" aria-label="Increase quantity" type="button">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <div class="cart-item-subtotal">R ${formattedSubtotal}</div>
                        <div>
                            <button class="action-btn delete-btn remove-btn" data-index="${i}" aria-label="Remove ${escapedItemName} from cart" type="button">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                `;
            }

            const subtotal = this.getTotalPrice();
            const safeSubtotal = safeNumber(subtotal, 0);

            let serviceFee = 0;
            if (safeSubtotal < 500)
            {
                serviceFee = Number((safeSubtotal * 0.10).toFixed(2));
            }
            else if (safeSubtotal >= 500 && safeSubtotal <= 1000)
            {
                serviceFee = Number((safeSubtotal * 0.065).toFixed(2));
            }

            const amountBeforeTax = safeSubtotal + serviceFee;
            const tax = Number((amountBeforeTax * 0.20).toFixed(2));
            const amountBeforeRounding = amountBeforeTax + tax;
            const roundedTotal = Math.ceil(amountBeforeRounding / 5) * 5;
            const roundingAdjustment = Number((roundedTotal - amountBeforeRounding).toFixed(2));

            const formattedSubtotal = safeSubtotal.toFixed(2);
            const formattedServiceFee = serviceFee.toFixed(2);
            const formattedTax = tax.toFixed(2);
            const formattedRounding = roundingAdjustment.toFixed(2);
            const formattedTotal = roundedTotal.toFixed(2);

            const currentToken = getCsrfToken();

            html += `
                <div class="cart-summary">
                    <div class="summary-row">
                        <span>Subtotal:</span>
                        <span>R ${formattedSubtotal}</span>
                    </div>
            `;

            if (serviceFee > 0)
            {
                const feePercentage = safeSubtotal < 500 ? '10%' : '6.5%';
                html += `
                    <div class="summary-row service-fee-row">
                        <span>Service Fee (${feePercentage}):</span>
                        <span>R ${formattedServiceFee}</span>
                    </div>
                `;
            }

            html += `
                    <div class="summary-row tax-row">
                        <span>Tax (20%):</span>
                        <span>R ${formattedTax}</span>
                    </div>
            `;

            if (roundingAdjustment > 0)
            {
                html += `
                    <div class="summary-row rounding-row">
                        <span>Rounding Adjustment:</span>
                        <span>R ${formattedRounding}</span>
                    </div>
                `;
            }

            html += `
                    <div class="summary-row summary-total">
                        <span>Total:</span>
                        <span>R ${formattedTotal}</span>
                    </div>
                    <div class="transaction-id-display">
                        <i class="fas fa-hashtag"></i>
                        Transaction ID: ${escapeHtml(this.generateTransactionId())}
                    </div>
                    <form method="POST" action="" id="checkout-form">
                        <input type="hidden" name="csrf_token" value="${escapeHtml(currentToken)}">
                        <input type="hidden" name="checkout" value="1">
                        <button type="submit" class="btn btn-primary checkout-btn" id="proceed-to-checkout-btn" aria-label="Proceed to checkout">
                            <i class="fas fa-credit-card"></i>
                            Proceed to Checkout (R ${formattedTotal})
                        </button>
                    </form>
                    <button class="btn btn-outline clear-cart-btn" aria-label="Clear entire cart" type="button">
                        <i class="fas fa-trash-alt"></i> Clear Cart
                    </button>
                </div>
            `;

            cartContainer.innerHTML = html;
            this.updateCartBadge(this.items.length);

            console.log('Cart display updated with', this.items.length, 'items');
        }

        /**
         * Updates the cart badge display.
         *
         * @param {number} count The number of items in the cart
         */
        updateCartBadge(count)
        {
            console.log('Updating cart badge to:', count);

            const cartBadge = document.getElementById('cart-count-badge');
            if (cartBadge)
            {
                if (count > 0)
                {
                    cartBadge.textContent = count;
                    cartBadge.style.display = 'inline-block';
                }
                else
                {
                    cartBadge.textContent = '0';
                    cartBadge.style.display = 'none';
                }
            }

            const cartCountElement = document.getElementById('cart-count');
            if (cartCountElement)
            {
                cartCountElement.textContent = count;
                cartCountElement.style.display = count > 0 ? 'inline-block' : 'none';
            }

            const headerBadge = document.getElementById('header-cart-badge');
            if (headerBadge)
            {
                if (count > 0)
                {
                    headerBadge.textContent = count;
                    headerBadge.style.display = 'inline-block';
                }
                else
                {
                    headerBadge.textContent = '0';
                    headerBadge.style.display = 'none';
                }
            }

            const cartCountElements = document.querySelectorAll('.cart-item-count, .cart-total-items');
            for (let i = 0; i < cartCountElements.length; i++)
            {
                cartCountElements[i].textContent = count;
                cartCountElements[i].style.display = count > 0 ? 'inline-block' : 'none';
            }
        }

        /**
         * Generates a unique transaction ID.
         *
         * @returns {string} Transaction ID
         */
        generateTransactionId()
        {
            const now = new Date();
            const year = now.getUTCFullYear();
            const month = String(now.getUTCMonth() + 1).padStart(2, '0');
            const day = String(now.getUTCDate()).padStart(2, '0');
            const hours = String(now.getUTCHours()).padStart(2, '0');
            const minutes = String(now.getUTCMinutes()).padStart(2, '0');
            const seconds = String(now.getUTCSeconds()).padStart(2, '0');

            return 'TD' + year + month + day + hours + minutes + seconds;
        }
    }

    // =========================================================================
    // Helper Functions
    // =========================================================================

    /**
     * Gets the base URL for API calls.
     *
     * @returns {string} The base URL
     */
    function getBaseUrl()
    {
        if (typeof window.BASE_URL !== 'undefined' && window.BASE_URL)
        {
            return window.BASE_URL;
        }

        const pathParts = window.location.pathname.split('/');
        const solutionIndex = pathParts.indexOf('Solution');
        if (solutionIndex !== -1)
        {
            return pathParts.slice(0, solutionIndex + 1).join('/');
        }

        return '';
    }

    // =========================================================================
    // Initialize Global Cart Instance
    // =========================================================================

    let cartInstance = null;

    function getCartInstance()
    {
        if (!cartInstance)
        {
            cartInstance = new ShoppingCart();
        }
        return cartInstance;
    }

    const cart = getCartInstance();

    // Set up event listeners
    document.addEventListener('cartUpdated', function(event)
    {
        console.log('Cart updated. Item count:', event.detail.itemCount);
        const cartCountElement = document.getElementById('cart-count');
        if (cartCountElement)
        {
            const count = event.detail.itemCount;
            cartCountElement.textContent = count;
            cartCountElement.setAttribute('aria-label', count + ' items in cart');
            cartCountElement.style.display = count > 0 ? 'inline-block' : 'none';
        }
    });

    document.addEventListener('DOMContentLoaded', function()
    {
        console.log('DOM fully loaded. Initializing cart display...');
        if (document.getElementById('cart-container'))
        {
            cart.updateCartDisplay();
            console.log('Cart display initialized.');
        }
    });

    window.addEventListener('beforeunload', function()
    {
        cart.saveToStorage();
    });

    // Set global variables
    if (typeof window.BASE_URL === 'undefined' && typeof BASE_URL !== 'undefined')
    {
        window.BASE_URL = BASE_URL;
    }

    window.cart = cart;
    window.CartItem = CartItem;
    window.showNotification = showNotification;
    window.getCsrfToken = getCsrfToken;
    window.safeNumber = safeNumber;
    window.formatCurrency = formatCurrency;
    window.updateCsrfToken = updateCsrfToken;

    console.log('Cart.js loaded successfully.');
})();