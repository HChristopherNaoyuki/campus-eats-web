/**
 * Campus Eats - Dashboard Common JavaScript
 *
 * This file contains shared functionality for all dashboard interfaces
 * including admin, vendor, and student dashboards.
 *
 * Version: 4.0.0
 * Last Modified: June 18, 2026
 *
 * Requirement Compliance: ALL JavaScript is external (no inline scripts)
 *
 * CORRECTIONS (Version 4.0):
 * - Fixed sidebar toggle logic for new .app-layout structure.
 * - Added support for .collapsed class on desktop sidebar.
 * - Added support for .mobile-open class for off-canvas drawer.
 * - Persisted sidebar state using localStorage.
 * - Restored collapsed state after page refresh.
 * - Improved accessibility with aria-expanded attribute.
 */

/**
 * Document Ready Event Handler
 * Initializes all dashboard components when DOM is fully loaded.
 */
document.addEventListener('DOMContentLoaded', function()
{
    initializeMenuToggle();
    initializeCurrentYear();
    initializeModals();
    initializeNotifications();
    initializeFormSubmissions();
});

/**
 * Menu Toggle Functionality
 * Handles responsive sidebar collapse/expand for all dashboards.
 * Supports two modes:
 * - Desktop/Tablet: Adds/removes the 'collapsed' class on the .sidebar element.
 * - Mobile: Adds/removes the 'mobile-open' class on the .sidebar element.
 */
function initializeMenuToggle()
{
    const menuToggle = document.getElementById('menuToggleBtn');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = sidebar ? sidebar.nextElementSibling : null;

    if (!menuToggle || !sidebar)
    {
        return;
    }

    menuToggle.setAttribute('aria-label', 'Toggle navigation menu');
    menuToggle.setAttribute('role', 'button');
    menuToggle.setAttribute('tabindex', '0');

    const isMobile = window.innerWidth <= 768;
    const savedState = localStorage.getItem('sidebarCollapsed');

    if (isMobile)
    {
        sidebar.classList.remove('mobile-open');
        menuToggle.setAttribute('aria-expanded', 'false');
    }
    else
    {
        if (savedState === 'true')
        {
            sidebar.classList.add('collapsed');
            menuToggle.setAttribute('aria-expanded', 'false');
        }
        else
        {
            sidebar.classList.remove('collapsed');
            menuToggle.setAttribute('aria-expanded', 'true');
        }
    }

    menuToggle.removeEventListener('click', handleMenuToggle);
    menuToggle.addEventListener('click', handleMenuToggle);

    menuToggle.addEventListener('keypress', function(event)
    {
        if (event.key === 'Enter' || event.key === ' ')
        {
            event.preventDefault();
            handleMenuToggle();
        }
    });

    window.addEventListener('resize', function()
    {
        const isMobileNow = window.innerWidth <= 768;
        const wasMobile = isMobileNow !== isMobileNow;

        if (isMobileNow)
        {
            sidebar.classList.remove('collapsed');
            const isOpen = sidebar.classList.contains('mobile-open');
            menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
        else
        {
            const savedDesktopState = localStorage.getItem('sidebarCollapsed');
            if (savedDesktopState === 'true')
            {
                sidebar.classList.add('collapsed');
                menuToggle.setAttribute('aria-expanded', 'false');
            }
            else
            {
                sidebar.classList.remove('collapsed');
                menuToggle.setAttribute('aria-expanded', 'true');
            }
            sidebar.classList.remove('mobile-open');
        }
    });

    function handleMenuToggle()
    {
        const isMobile = window.innerWidth <= 768;

        if (isMobile)
        {
            sidebar.classList.toggle('mobile-open');
            const isOpen = sidebar.classList.contains('mobile-open');
            menuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
        else
        {
            sidebar.classList.toggle('collapsed');
            const isCollapsed = sidebar.classList.contains('collapsed');
            menuToggle.setAttribute('aria-expanded', isCollapsed ? 'false' : 'true');
            localStorage.setItem('sidebarCollapsed', isCollapsed ? 'true' : 'false');
        }
    }

    document.addEventListener('click', function(event)
    {
        const isMobile = window.innerWidth <= 768;
        if (!isMobile) return;

        const isClickInsideSidebar = sidebar.contains(event.target);
        const isClickOnToggle = menuToggle.contains(event.target);

        if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('mobile-open'))
        {
            sidebar.classList.remove('mobile-open');
            menuToggle.setAttribute('aria-expanded', 'false');
        }
    });
}

/**
 * Current Year Display
 * Updates all elements with id="current-year" to display the current UTC year.
 */
function initializeCurrentYear()
{
    const yearElement = document.getElementById('current-year');

    if (yearElement)
    {
        const currentYear = new Date().getUTCFullYear();
        yearElement.textContent = currentYear;

        const copyrightElements = document.querySelectorAll('.copyright-year');
        copyrightElements.forEach(function(element)
        {
            element.textContent = currentYear;
        });
    }
}

/**
 * Modal Initialization
 * Sets up modal close handlers for all modal dialogs.
 */
function initializeModals()
{
    const modals = document.querySelectorAll('.modal');

    modals.forEach(function(modal)
    {
        modal.addEventListener('click', function(event)
        {
            if (event.target === modal)
            {
                closeModal(modal.id);
            }
        });

        const closeButtons = modal.querySelectorAll('.modal-close, .close-modal');
        closeButtons.forEach(function(button)
        {
            button.addEventListener('click', function()
            {
                closeModal(modal.id);
            });
        });
    });

    document.addEventListener('keydown', function(event)
    {
        if (event.key === 'Escape')
        {
            const visibleModal = document.querySelector('.modal[style*="display: flex"]');
            if (visibleModal)
            {
                closeModal(visibleModal.id);
            }
        }
    });
}

/**
 * Closes a modal dialog by ID.
 *
 * @param {string} modalId - The ID of the modal element to close.
 */
function closeModal(modalId)
{
    const modal = document.getElementById(modalId);

    if (modal)
    {
        modal.style.display = 'none';
        document.body.style.overflow = '';

        const closeEvent = new CustomEvent('modalClosed', { detail: { modalId: modalId } });
        document.dispatchEvent(closeEvent);
    }
}

/**
 * Opens a modal dialog by ID.
 *
 * @param {string} modalId - The ID of the modal element to open.
 */
function openModal(modalId)
{
    const modal = document.getElementById(modalId);

    if (modal)
    {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        const openEvent = new CustomEvent('modalOpened', { detail: { modalId: modalId } });
        document.dispatchEvent(openEvent);

        const focusable = modal.querySelector('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])');
        if (focusable)
        {
            focusable.focus();
        }
    }
}

/**
 * Notification Display System
 * Shows temporary toast notifications to users.
 *
 * @param {string} message - The notification message to display.
 * @param {string} type - The type of notification (success, error, warning, info).
 * @param {number} duration - Duration in milliseconds to show notification.
 */
function showNotification(message, type, duration)
{
    type = type || 'info';
    duration = duration || 5000;

    let container = document.getElementById('notification-container');

    if (!container)
    {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.style.position = 'fixed';
        container.style.top = '20px';
        container.style.right = '20px';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.setAttribute('role', 'alert');
    notification.setAttribute('aria-live', 'polite');

    notification.style.backgroundColor = getNotificationColor(type);
    notification.style.color = '#FFFFFF';
    notification.style.padding = '12px 20px';
    notification.style.marginBottom = '10px';
    notification.style.borderRadius = '8px';
    notification.style.fontSize = '14px';
    notification.style.fontWeight = '500';
    notification.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
    notification.style.display = 'flex';
    notification.style.alignItems = 'center';
    notification.style.justifyContent = 'space-between';
    notification.style.minWidth = '300px';
    notification.style.maxWidth = '450px';
    notification.style.animation = 'slideInRight 0.3s ease';

    const icon = getNotificationIcon(type);
    notification.innerHTML = `
        <div style="display: flex; align-items: center; gap: 10px;">
            <span>${icon}</span>
            <span>${escapeHtml(message)}</span>
        </div>
        <button class="notification-close" style="background: none; border: none; color: white; cursor: pointer; font-size: 18px;">&times;</button>
    `;

    const closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', function()
    {
        notification.remove();
    });

    container.appendChild(notification);

    setTimeout(function()
    {
        if (notification.parentNode)
        {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(function()
            {
                if (notification.parentNode)
                {
                    notification.remove();
                }
            }, 300);
        }
    }, duration);
}

/**
 * Returns background color based on notification type.
 *
 * @param {string} type - Notification type.
 * @returns {string} CSS color value.
 */
function getNotificationColor(type)
{
    switch (type)
    {
        case 'success': return '#28A745';
        case 'error': return '#DC3545';
        case 'warning': return '#FFC107';
        case 'info':
        default: return '#17A2B8';
    }
}

/**
 * Returns icon HTML based on notification type.
 *
 * @param {string} type - Notification type.
 * @returns {string} HTML icon string.
 */
function getNotificationIcon(type)
{
    switch (type)
    {
        case 'success': return '✓';
        case 'error': return '✗';
        case 'warning': return '⚠';
        case 'info':
        default: return 'ℹ';
    }
}

/**
 * HTML Escape Function
 * Prevents XSS attacks by escaping special characters.
 *
 * @param {string} str - String to escape.
 * @returns {string} Escaped string safe for HTML insertion.
 */
function escapeHtml(str)
{
    if (!str)
    {
        return '';
    }

    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Form Submission Handler
 * Adds CSRF token to all AJAX form submissions.
 */
function initializeFormSubmissions()
{
    const forms = document.querySelectorAll('form[data-ajax="true"]');

    forms.forEach(function(form)
    {
        form.addEventListener('submit', function(event)
        {
            event.preventDefault();

            const formData = new FormData(form);
            const csrfToken = document.querySelector('meta[name="csrf-token"]');

            if (csrfToken)
            {
                formData.append('csrf_token', csrfToken.getAttribute('content'));
            }

            const url = form.getAttribute('action') || window.location.href;
            const method = form.getAttribute('method') || 'POST';

            fetch(url, {
                method: method,
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken ? csrfToken.getAttribute('content') : ''
                }
            })
            .then(function(response)
            {
                return response.json();
            })
            .then(function(data)
            {
                if (data.success)
                {
                    showNotification(data.message || 'Operation completed successfully', 'success');

                    const successEvent = new CustomEvent('formSuccess',
                    {
                        detail: { form: form, data: data }
                    });
                    document.dispatchEvent(successEvent);
                }
                else
                {
                    showNotification(data.message || 'An error occurred', 'error');
                }
            })
            .catch(function(error)
            {
                console.error('Form submission error:', error);
                showNotification('Network error. Please try again.', 'error');
            });
        });
    });
}

/**
 * Confirmation Dialog
 * Shows a confirmation dialog before destructive actions.
 *
 * @param {string} message - Confirmation message.
 * @param {function} onConfirm - Callback function on confirm.
 * @param {function} onCancel - Callback function on cancel.
 */
function confirmAction(message, onConfirm, onCancel)
{
    let confirmModal = document.getElementById('confirm-modal');

    if (!confirmModal)
    {
        confirmModal = document.createElement('div');
        confirmModal.id = 'confirm-modal';
        confirmModal.className = 'modal';
        confirmModal.style.display = 'none';
        confirmModal.style.position = 'fixed';
        confirmModal.style.top = '0';
        confirmModal.style.left = '0';
        confirmModal.style.width = '100%';
        confirmModal.style.height = '100%';
        confirmModal.style.backgroundColor = 'rgba(0, 0, 0, 0.5)';
        confirmModal.style.zIndex = '10000';
        confirmModal.style.alignItems = 'center';
        confirmModal.style.justifyContent = 'center';

        confirmModal.innerHTML = `
            <div class="modal-content" style="background: white; border-radius: 12px; max-width: 400px; width: 90%; padding: 24px;">
                <h3 id="confirm-title" style="margin-bottom: 16px; font-size: 18px;">Confirm Action</h3>
                <p id="confirm-message" style="margin-bottom: 24px; color: #666;"></p>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button id="confirm-cancel" class="btn btn-secondary" style="padding: 8px 16px;">Cancel</button>
                    <button id="confirm-ok" class="btn btn-primary" style="padding: 8px 16px;">Confirm</button>
                </div>
            </div>
        `;

        document.body.appendChild(confirmModal);
    }

    const messageElement = document.getElementById('confirm-message');
    const cancelBtn = document.getElementById('confirm-cancel');
    const okBtn = document.getElementById('confirm-ok');

    messageElement.textContent = message;

    const newCancelBtn = cancelBtn.cloneNode(true);
    const newOkBtn = okBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    okBtn.parentNode.replaceChild(newOkBtn, okBtn);

    newCancelBtn.addEventListener('click', function()
    {
        confirmModal.style.display = 'none';
        if (onCancel)
        {
            onCancel();
        }
    });

    newOkBtn.addEventListener('click', function()
    {
        confirmModal.style.display = 'none';
        if (onConfirm)
        {
            onConfirm();
        }
    });

    confirmModal.style.display = 'flex';
}