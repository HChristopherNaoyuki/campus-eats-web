/**
 * Student-Specific JavaScript for Campus Eats (Complete Refactor)
 *
 * This file handles student-specific functionality including:
 * - Vendor browsing
 * - Menu item viewing
 * - Add to cart operations
 * - Vendor filtering
 * - Cart count badge updates
 *
 * SOURCE: campus-eats-process-document.pdf (Section 6.1 - Student Functional Requirements)
 * SOURCE: Mockups - 21.png, 22.png, 23.png, 24.png
 *
 * @version 9.0
 */

(function()
{
    'use strict';

    // =========================================================================
    // Constants
    // =========================================================================

    var INITIAL_CART_COUNT = window.INITIAL_CART_COUNT || 0;
    var BASE_URL = window.BASE_URL || '';
    var CSRF_TOKEN = window.CSRF_TOKEN || '';

    // =========================================================================
    // Utility Functions
    // =========================================================================

    /**
     * Gets the CSRF token from the meta tag or window variable.
     *
     * @returns {string} The CSRF token
     */
    function getCsrfToken()
    {
        if (CSRF_TOKEN)
        {
            return CSRF_TOKEN;
        }

        var metaTag = document.querySelector('meta[name="csrf-token"]');

        return metaTag ? metaTag.getAttribute('content') : '';
    }

    /**
     * Gets the base URL for API calls.
     *
     * @returns {string} The base URL
     */
    function getBaseUrl()
    {
        if (BASE_URL)
        {
            return BASE_URL;
        }

        if (typeof window.BASE_URL !== 'undefined' && window.BASE_URL)
        {
            return window.BASE_URL;
        }

        var pathParts = window.location.pathname.split('/');
        var solutionIndex = pathParts.indexOf('Solution');

        if (solutionIndex !== -1)
        {
            return pathParts.slice(0, solutionIndex + 1).join('/');
        }

        return '';
    }

    /**
     * Escapes HTML special characters.
     *
     * @param {string} text The text to escape
     * @returns {string} The escaped text
     */
    function escapeHtml(text)
    {
        if (!text) return '';

        var div = document.createElement('div');
        div.textContent = text;

        return div.innerHTML;
    }

    /**
     * Shows a notification message.
     *
     * @param {string} message The message to display
     * @param {string} type The notification type
     */
    function showNotification(message, type)
    {
        if (typeof window.showNotification === 'function')
        {
            window.showNotification(message, type);
            return;
        }

        var notification = document.createElement('div');
        notification.className = 'notification notification-' + type;
        notification.textContent = message;

        notification.style.position = 'fixed';
        notification.style.bottom = '20px';
        notification.style.right = '20px';
        notification.style.padding = '15px 25px';
        notification.style.borderRadius = '8px';
        notification.style.zIndex = '9999';
        notification.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';

        var colors = {
            'success': '#28a745',
            'error': '#dc3545',
            'warning': '#ffc107',
            'info': '#17a2b8'
        };

        notification.style.backgroundColor = colors[type] || colors.info;
        notification.style.color = type === 'warning' ? '#212529' : '#fff';

        document.body.appendChild(notification);

        setTimeout(function()
        {
            notification.style.animation = 'slideOut 0.3s ease';
            setTimeout(function() { notification.remove(); }, 300);
        }, 3000);
    }

    /**
     * Updates the CSRF token in the meta tag.
     *
     * @param {string} newToken The new CSRF token
     */
    function updateCsrfToken(newToken)
    {
        if (!newToken) return;

        var metaTag = document.querySelector('meta[name="csrf-token"]');

        if (metaTag)
        {
            metaTag.setAttribute('content', newToken);
            console.log('Student: CSRF token updated');
        }

        var inputs = document.querySelectorAll('input[name="csrf_token"]');

        for (var i = 0; i < inputs.length; i++)
        {
            inputs[i].value = newToken;
        }
    }

    /**
     * Determines if an API operation was successful.
     *
     * @param {Object} response The API response
     * @returns {boolean} True if the operation succeeded
     */
    function isOperationSuccess(response)
    {
        if (!response) return false;
        if (response.success === true) return true;
        if (response.cart !== undefined) return true;
        if (response.cart_count !== undefined) return true;
        if (response.items !== undefined) return true;

        return false;
    }

    /**
     * Updates the cart count display.
     *
     * @param {number} count The new cart count
     */
    function updateCartCount(count)
    {
        console.log('Student: Updating cart count to', count);

        var cartBadge = document.getElementById('cart-count-badge');

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

        var cartCountElement = document.getElementById('cart-count');

        if (cartCountElement)
        {
            cartCountElement.textContent = count;
            cartCountElement.style.display = count > 0 ? 'inline-block' : 'none';
        }

        var navCartCount = document.querySelector('.cart-count-nav');

        if (navCartCount)
        {
            navCartCount.textContent = count;
            navCartCount.style.display = count > 0 ? 'inline-block' : 'none';
        }
    }

    // =========================================================================
    // Add to Cart Functionality
    // =========================================================================

    /**
     * Adds an item to the shopping cart.
     *
     * @param {number} itemId The item ID
     * @param {string} itemName The item name
     * @param {number} itemPrice The item price
     * @param {number} vendorId The vendor ID
     * @param {string} vendorName The vendor name
     */
    function addToCart(itemId, itemName, itemPrice, vendorId, vendorName)
    {
        console.log('Student: Adding to cart - Item:', itemId, itemName, 'Price:', itemPrice);

        var button = document.querySelector(
            '.add-to-cart-btn[data-item-id="' + itemId + '"]'
        );

        if (button)
        {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
        }

        var baseUrl = getBaseUrl();
        var requestData = {
            action: 'add',
            item_id: parseInt(itemId, 10),
            quantity: 1,
            csrf_token: getCsrfToken()
        };

        console.log('Student: Sending add to cart request', requestData);

        fetch(baseUrl + '/api/update_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin',
            body: JSON.stringify(requestData)
        })
        .then(function(response)
        {
            console.log('Student: Add to cart response status:', response.status);
            return response.json();
        })
        .then(function(data)
        {
            console.log('Student: Add to cart response data:', data);

            var isSuccess = isOperationSuccess(data);

            if (isSuccess)
            {
                console.log('Student: Add to cart succeeded');

                if (data.csrf_token)
                {
                    updateCsrfToken(data.csrf_token);
                }

                var count = data.cart_count ||
                           (data.cart ? data.cart.length : 0) ||
                           (data.items ? data.items.length : 0);

                if (data.cart && Array.isArray(data.cart))
                {
                    count = data.cart.length;
                }

                updateCartCount(count);

                if (typeof cart !== 'undefined' && cart)
                {
                    cart.fetchCartFromServer();
                }

                showNotification(itemName + ' added to cart! (' + count + ' items)', 'success');
            }
            else
            {
                console.warn('Student: Add to cart failed:', data.message);
                showNotification(data.message || 'Error adding item to cart.', 'error');
            }
        })
        .catch(function(error)
        {
            console.error('Student: Add to cart network error:', error);

            if (error.message && (
                error.message.includes('NetworkError') ||
                error.message.includes('Failed to fetch')
            ))
            {
                showNotification('Network error. Please check your connection.', 'error');
            }
            else
            {
                showNotification('An error occurred. Please try again.', 'error');
            }
        })
        .finally(function()
        {
            if (button)
            {
                button.disabled = false;
                button.innerHTML = 'Add to Cart';
            }
        });
    }

    // =========================================================================
    // Vendor Loading Functions
    // =========================================================================

    /**
     * Loads menu items for a specific vendor.
     *
     * @param {number} vendorId The vendor ID
     * @param {string} vendorName The vendor name
     */
    function loadVendorMenu(vendorId, vendorName)
    {
        console.log('Student: Loading menu for vendor', vendorId, vendorName);

        var menuContainer = document.getElementById('menu-container');

        if (!menuContainer)
        {
            console.warn('Student: Menu container not found');
            return;
        }

        menuContainer.innerHTML =
            '<div class="loading"><i class="fas fa-spinner fa-pulse"></i> Loading menu...</div>';

        var baseUrl = getBaseUrl();

        fetch(baseUrl + '/api/get_menu_items.php?vendor_id=' + vendorId, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            credentials: 'same-origin'
        })
        .then(function(response)
        {
            console.log('Student: Menu fetch response status', response.status);
            return response.json();
        })
        .then(function(data)
        {
            if (data.success)
            {
                displayMenuItems(data.menu_items, vendorName);
                console.log('Student: Menu loaded successfully');
            }
            else
            {
                console.warn('Student: Menu load failed', data.message);
                menuContainer.innerHTML =
                    '<p class="error">Error loading menu. Please try again.</p>';
            }
        })
        .catch(function(error)
        {
            console.error('Student: Error loading menu:', error);
            menuContainer.innerHTML =
                '<p class="error">Error loading menu. Please try again.</p>';
        });
    }

    /**
     * Displays menu items in a categorized grid.
     *
     * @param {Array} items The menu items
     * @param {string} vendorName The vendor name
     */
    function displayMenuItems(items, vendorName)
    {
        var menuContainer = document.getElementById('menu-container');

        if (!menuContainer) return;

        if (!items || items.length === 0)
        {
            menuContainer.innerHTML =
                '<div class="empty-state">' +
                    '<i class="fas fa-utensils"></i>' +
                    '<h3>No Menu Items</h3>' +
                    '<p>This vendor has not added any menu items yet.</p>' +
                '</div>';
            return;
        }

        var categorizedItems = {};

        for (var i = 0; i < items.length; i++)
        {
            var item = items[i];
            var category = item.category || 'General';

            if (!categorizedItems[category])
            {
                categorizedItems[category] = [];
            }

            categorizedItems[category].push(item);
        }

        var html = '<h2>' + escapeHtml(vendorName) + ' Menu</h2>';
        var categories = Object.keys(categorizedItems);

        for (var c = 0; c < categories.length; c++)
        {
            var category = categories[c];
            var categoryItems = categorizedItems[category];

            html += '<div class="menu-category">' +
                        '<h3>' + escapeHtml(category) + '</h3>' +
                        '<div class="menu-grid">';

            for (var j = 0; j < categoryItems.length; j++)
            {
                var item = categoryItems[j];
                var availabilityClass = item.is_available ? '' : 'unavailable';
                var availabilityText = item.is_available ?
                    '' :
                    '<span class="unavailable-badge">Currently Unavailable</span>';

                html += '<div class="menu-item-card ' + availabilityClass + '" data-item-id="' + item.item_id + '">' +
                            '<div class="menu-item-image">' +
                                '<i class="fas fa-utensils"></i>' +
                            '</div>' +
                            '<div class="menu-item-details">' +
                                '<h3 class="menu-item-name">' + escapeHtml(item.item_name) + '</h3>' +
                                '<p class="menu-item-description">' + escapeHtml(item.description || 'No description available') + '</p>' +
                                '<span class="menu-item-price">R ' + parseFloat(item.price).toFixed(2) + '</span>' +
                                availabilityText +
                                (item.is_available ?
                                    '<button class="btn btn-primary add-to-cart-btn" ' +
                                        'data-item-id="' + item.item_id + '" ' +
                                        'data-item-name="' + escapeHtml(item.item_name) + '" ' +
                                        'data-item-price="' + item.price + '" ' +
                                        'data-vendor-id="' + (item.vendor_id || '') + '" ' +
                                        'data-vendor-name="' + escapeHtml(vendorName) + '">' +
                                        'Add to Cart' +
                                    '</button>' :
                                    '') +
                            '</div>' +
                        '</div>';
            }

            html += '</div></div>';
        }

        menuContainer.innerHTML = html;

        var addButtons = menuContainer.querySelectorAll('.add-to-cart-btn');

        for (var k = 0; k < addButtons.length; k++)
        {
            addButtons[k].addEventListener('click', function()
            {
                var itemId = this.getAttribute('data-item-id');
                var itemName = this.getAttribute('data-item-name');
                var itemPrice = this.getAttribute('data-item-price');
                var vendorId = this.getAttribute('data-vendor-id');
                var vendorName = this.getAttribute('data-vendor-name');

                addToCart(itemId, itemName, itemPrice, vendorId, vendorName);
            });
        }
    }

    // =========================================================================
    // Vendor Filtering
    // =========================================================================

    /**
     * Filters vendors by search query.
     *
     * @param {string} query The search query
     */
    function filterVendors(query)
    {
        var vendorCards = document.querySelectorAll('.vendor-card');
        var searchTerm = query.toLowerCase();

        for (var i = 0; i < vendorCards.length; i++)
        {
            var card = vendorCards[i];
            var vendorName = card.querySelector('h3') ?
                card.querySelector('h3').textContent.toLowerCase() :
                '';
            var vendorCuisine = card.querySelector('p') ?
                card.querySelector('p').textContent.toLowerCase() :
                '';

            if (vendorName.includes(searchTerm) || vendorCuisine.includes(searchTerm))
            {
                card.style.display = '';
            }
            else
            {
                card.style.display = 'none';
            }
        }
    }

    /**
     * Initializes the vendor search functionality.
     */
    function initVendorSearch()
    {
        var searchInput = document.getElementById('searchInput');

        if (!searchInput) return;

        searchInput.addEventListener('input', function()
        {
            filterVendors(this.value);
        });

        console.log('Student: Vendor search initialized');
    }

    // =========================================================================
    // Proceed to Checkout
    // =========================================================================

    /**
     * Proceeds to the checkout page.
     */
    function proceedToCheckout()
    {
        console.log('Student: Proceeding to checkout');

        if (typeof cart !== 'undefined' && cart)
        {
            if (cart.items && cart.items.length === 0)
            {
                showNotification('Your cart is empty. Please add items first.', 'warning');
                return;
            }
        }

        window.location.href = 'checkout.php';
    }

    // =========================================================================
    // Initialization
    // =========================================================================

    /**
     * Initializes all student page functionality.
     */
    function initStudentPage()
    {
        console.log('Student: Initializing student page');

        if (INITIAL_CART_COUNT > 0)
        {
            console.log('Student: Setting initial cart count to', INITIAL_CART_COUNT);
            updateCartCount(INITIAL_CART_COUNT);
        }

        initVendorSearch();

        var addButtons = document.querySelectorAll('.add-to-cart-btn');

        for (var i = 0; i < addButtons.length; i++)
        {
            var button = addButtons[i];

            var newButton = button.cloneNode(true);
            button.parentNode.replaceChild(newButton, button);

            newButton.addEventListener('click', function()
            {
                var itemId = this.getAttribute('data-item-id');
                var itemName = this.getAttribute('data-item-name');
                var itemPrice = this.getAttribute('data-item-price');
                var vendorId = this.getAttribute('data-vendor-id');
                var vendorName = this.getAttribute('data-vendor-name');

                addToCart(itemId, itemName, itemPrice, vendorId, vendorName);
            });
        }

        console.log('Student: Found', addButtons.length, 'add to cart buttons');

        if (typeof cart !== 'undefined' && cart)
        {
            var cartCount = cart.getTotalItemCount ? cart.getTotalItemCount() : 0;
            if (cartCount !== INITIAL_CART_COUNT)
            {
                console.log('Student: Cart count mismatch - updating to', INITIAL_CART_COUNT);
                updateCartCount(INITIAL_CART_COUNT);
            }
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading')
    {
        document.addEventListener('DOMContentLoaded', initStudentPage);
    }
    else
    {
        initStudentPage();
    }

    // =========================================================================
    // Expose Functions Globally
    // =========================================================================

    window.loadVendorMenu = loadVendorMenu;
    window.addToCart = addToCart;
    window.filterVendors = filterVendors;
    window.updateCartCount = updateCartCount;
    window.proceedToCheckout = proceedToCheckout;
    window.isOperationSuccess = isOperationSuccess;

    console.log('Student: JavaScript loaded successfully');
})();