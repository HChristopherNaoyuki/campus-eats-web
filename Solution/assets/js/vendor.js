/**
 * Vendor-Specific JavaScript for Campus Eats (Complete Refactor)
 *
 * This file handles vendor-specific functionality including:
 * - Menu item management (add, edit, delete, toggle)
 * - Order management (filter, update status)
 * - Report generation
 *
 * SOURCE: campus-eats-process-document.pdf (Section 6.2 - Vendor Functional Requirements)
 * SOURCE: Mockup - 25.png
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
        const notification = document.createElement('div');
        notification.className = 'notification notification-' + type;
        notification.textContent = message;
        notification.style.position = 'fixed';
        notification.style.bottom = '20px';
        notification.style.right = '20px';
        notification.style.padding = '15px 25px';
        notification.style.borderRadius = '8px';
        notification.style.zIndex = '9999';
        notification.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';

        switch (type)
        {
            case 'success':
                notification.style.backgroundColor = '#28a745';
                notification.style.color = '#fff';
                break;
            case 'error':
                notification.style.backgroundColor = '#dc3545';
                notification.style.color = '#fff';
                break;
            case 'warning':
                notification.style.backgroundColor = '#ffc107';
                notification.style.color = '#212529';
                break;
            default:
                notification.style.backgroundColor = '#17a2b8';
                notification.style.color = '#fff';
                break;
        }

        document.body.appendChild(notification);
        setTimeout(function() { notification.remove(); }, 3000);
    }

    /**
     * Shows a modal.
     * @param {HTMLElement} modal
     */
    function showModal(modal)
    {
        if (!modal) return;
        modal.classList.add('active');
    }

    /**
     * Hides a modal.
     */
    function hideModal()
    {
        const modal = document.querySelector('.modal.active');
        if (modal) modal.classList.remove('active');
    }

    /**
     * Opens the modal for adding a new menu item.
     */
    function openAddItemModal()
    {
        const modal = document.getElementById('add-item-modal');
        if (modal)
        {
            const form = document.getElementById('add-item-form');
            if (form) form.reset();

            const title = document.getElementById('modal-title');
            if (title) title.textContent = 'Add New Menu Item';

            const itemIdField = document.getElementById('item-id');
            if (itemIdField) itemIdField.value = '';

            showModal(modal);
        }
    }

    /**
     * Opens the modal for editing an existing menu item.
     * @param {number} itemId
     */
    function openEditItemModal(itemId)
    {
        fetch('../api/get_menu_item.php?item_id=' + itemId,
        {
            headers: { 'X-CSRF-TOKEN': getCsrfToken() }
        })
        .then(function(response) { return response.json(); })
        .then(function(data)
        {
            if (data.success)
            {
                const item = data.menu_item;
                const title = document.getElementById('modal-title');
                if (title) title.textContent = 'Edit Menu Item';

                const itemIdField = document.getElementById('item-id');
                if (itemIdField) itemIdField.value = item.item_id;

                const itemNameField = document.getElementById('item-name');
                if (itemNameField) itemNameField.value = item.item_name;

                const descriptionField = document.getElementById('item-description');
                if (descriptionField) descriptionField.value = item.description || '';

                const priceField = document.getElementById('item-price');
                if (priceField) priceField.value = item.price;

                const categoryField = document.getElementById('item-category');
                if (categoryField) categoryField.value = item.category || '';

                const availableField = document.getElementById('item-available');
                if (availableField) availableField.checked = item.is_available == 1;

                const modal = document.getElementById('add-item-modal');
                if (modal) showModal(modal);
            }
            else
            {
                showNotification('Error loading item details.', 'error');
            }
        })
        .catch(function(error)
        {
            console.error('Error loading item:', error);
            showNotification('Error loading item details.', 'error');
        });
    }

    /**
     * Saves a menu item (add or edit).
     */
    function saveMenuItem()
    {
        const itemIdField = document.getElementById('item-id');
        const itemNameField = document.getElementById('item-name');
        const descriptionField = document.getElementById('item-description');
        const priceField = document.getElementById('item-price');
        const categoryField = document.getElementById('item-category');
        const availableField = document.getElementById('item-available');

        const itemId = itemIdField ? itemIdField.value : '';
        const itemName = itemNameField ? itemNameField.value : '';
        const description = descriptionField ? descriptionField.value : '';
        const price = priceField ? parseFloat(priceField.value) : 0;
        const category = categoryField ? categoryField.value : '';
        const isAvailable = availableField ? (availableField.checked ? 1 : 0) : 1;

        const formData = {
            item_id: itemId,
            item_name: itemName,
            description: description,
            price: price,
            category: category,
            is_available: isAvailable
        };

        if (!formData.item_name || !formData.price || formData.price <= 0)
        {
            showNotification('Please fill in all required fields correctly.', 'error');
            return;
        }

        const url = itemId ? '../api/update_menu_item.php' : '../api/add_menu_item.php';

        fetch(url,
        {
            method: 'POST',
            headers:
            {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify(formData)
        })
        .then(function(response) { return response.json(); })
        .then(function(data)
        {
            if (data.success)
            {
                showNotification(data.message, 'success');
                hideModal();
                location.reload();
            }
            else
            {
                showNotification(data.message || 'Error saving menu item.', 'error');
            }
        })
        .catch(function(error)
        {
            console.error('Error saving menu item:', error);
            showNotification('Error saving menu item.', 'error');
        });
    }

    /**
     * Deletes a menu item after confirmation.
     * @param {number} itemId
     * @param {string} itemName
     */
    function deleteMenuItem(itemId, itemName)
    {
        if (confirm('Are you sure you want to delete "' + itemName + '"? This action cannot be undone.'))
        {
            fetch('../api/delete_menu_item.php',
            {
                method: 'POST',
                headers:
                {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken()
                },
                body: JSON.stringify({ item_id: itemId })
            })
            .then(function(response) { return response.json(); })
            .then(function(data)
            {
                if (data.success)
                {
                    showNotification(data.message, 'success');
                    location.reload();
                }
                else
                {
                    showNotification(data.message || 'Error deleting menu item.', 'error');
                }
            })
            .catch(function(error)
            {
                console.error('Error deleting menu item:', error);
                showNotification('Error deleting menu item.', 'error');
            });
        }
    }

    /**
     * Filters orders by status with null guard.
     * @param {string} status
     */
    function filterOrders(status)
    {
        const buttons = document.querySelectorAll('.filter-btn');
        for (let i = 0; i < buttons.length; i++)
        {
            buttons[i].classList.remove('active');
        }

        const activeButton = document.querySelector('.filter-btn[data-status="' + status + '"]');
        if (activeButton)
        {
            activeButton.classList.add('active');
        }

        const orderCards = document.querySelectorAll('.order-card');
        for (let i = 0; i < orderCards.length; i++)
        {
            const card = orderCards[i];
            if (status === 'all' || card.getAttribute('data-status') === status)
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
     * Updates the status of an order.
     * @param {number} orderId
     * @param {string} newStatus
     */
    function updateOrderStatus(orderId, newStatus)
    {
        fetch('../api/vendor_respond_order.php',
        {
            method: 'POST',
            headers:
            {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                order_id: orderId,
                status: newStatus
            })
        })
        .then(function(response) { return response.json(); })
        .then(function(data)
        {
            if (data.success)
            {
                showNotification(data.message, 'success');
                location.reload();
            }
            else
            {
                showNotification(data.message || 'Error updating order status.', 'error');
            }
        })
        .catch(function(error)
        {
            console.error('Error updating order:', error);
            showNotification('Error updating order status.', 'error');
        });
    }

    /**
     * Generates and downloads a sales report.
     */
    function generateReport()
    {
        const startDateField = document.getElementById('start-date');
        const endDateField = document.getElementById('end-date');

        const startDate = startDateField ? startDateField.value : '';
        const endDate = endDateField ? endDateField.value : '';

        if (!startDate || !endDate)
        {
            showNotification('Please select both start and end dates.', 'error');
            return;
        }

        window.location.href = '../api/generate_sales_report.php?start_date=' + encodeURIComponent(startDate) +
                               '&end_date=' + encodeURIComponent(endDate) +
                               '&format=csv';
    }

    /**
     * Toggles the availability status of a menu item.
     * @param {number} itemId
     * @param {boolean} currentStatus
     */
    function toggleItemAvailability(itemId, currentStatus)
    {
        const newStatus = currentStatus ? 0 : 1;

        fetch('../api/update_menu_item.php',
        {
            method: 'POST',
            headers:
            {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            body: JSON.stringify({
                item_id: itemId,
                is_available: newStatus
            })
        })
        .then(function(response) { return response.json(); })
        .then(function(data)
        {
            if (data.success)
            {
                showNotification('Item ' + (newStatus ? 'available' : 'unavailable'), 'success');
                location.reload();
            }
            else
            {
                showNotification('Error updating item availability.', 'error');
            }
        })
        .catch(function(error)
        {
            console.error('Error updating item:', error);
            showNotification('Error updating item availability.', 'error');
        });
    }

    // Expose functions globally.
    window.openAddItemModal = openAddItemModal;
    window.openEditItemModal = openEditItemModal;
    window.saveMenuItem = saveMenuItem;
    window.deleteMenuItem = deleteMenuItem;
    window.filterOrders = filterOrders;
    window.updateOrderStatus = updateOrderStatus;
    window.generateReport = generateReport;
    window.toggleItemAvailability = toggleItemAvailability;
})();