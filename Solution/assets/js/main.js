/**
 * Global JavaScript for Campus Eats (Complete Refactor)
 *
 * This file contains global JavaScript functions used across the entire application.
 * It handles common functionality such as modal management, form validation,
 * AJAX request utilities, mobile menu toggle, and notification system.
 *
 * SOURCE: campus-eats-process-document.pdf (Section 10 - User Interface Design)
 * SOURCE: Mockups - All
 *
 * @version 7.0
 */

(function()
{
    'use strict';

    // Global modal reference.
    let currentModal = null;

    // Global notification timeout reference.
    let currentNotificationTimeout = null;

    /**
     * Gets the CSRF token from the meta tag.
     * This token is required for all state-changing AJAX requests.
     *
     * @returns {string} The CSRF token or empty string if not found.
     */
    function getCsrfToken()
    {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : '';
    }

    /**
     * Escapes HTML special characters to prevent XSS attacks.
     * This function is used before inserting any user-supplied data into the DOM.
     *
     * @param {string} text - The text to escape.
     * @returns {string} The escaped text.
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
     * Displays a modal dialog.
     * Sets the modal as active, stores reference, and adds escape key listener.
     *
     * @param {HTMLElement} modalElement - The modal element to display.
     */
    function showModal(modalElement)
    {
        if (!modalElement)
        {
            console.warn('showModal called with null modalElement');
            return;
        }

        // Hide any currently open modal.
        if (currentModal)
        {
            currentModal.classList.remove('active');
        }

        modalElement.classList.add('active');
        currentModal = modalElement;

        // Add escape key listener.
        document.addEventListener('keydown', handleEscapeKey);
    }

    /**
     * Hides the currently displayed modal.
     * Removes active class and cleans up event listeners.
     */
    function hideModal()
    {
        if (currentModal)
        {
            currentModal.classList.remove('active');
            currentModal = null;
            document.removeEventListener('keydown', handleEscapeKey);
        }
    }

    /**
     * Handles escape key press to close modals.
     *
     * @param {KeyboardEvent} event - The keyboard event.
     */
    function handleEscapeKey(event)
    {
        if (event.key === 'Escape')
        {
            hideModal();
        }
    }

    /**
     * Displays a notification message to the user.
     * Creates a temporary toast-style notification that auto-dismisses.
     *
     * @param {string} message - The message to display.
     * @param {string} type - The type of notification (success, error, warning, info).
     */
    function showNotification(message, type)
    {
        // Clear any existing notification timeout.
        if (currentNotificationTimeout)
        {
            clearTimeout(currentNotificationTimeout);
        }

        // Remove any existing notification element.
        const existingNotification = document.querySelector('.notification');
        if (existingNotification)
        {
            existingNotification.remove();
        }

        // Create notification element.
        const notification = document.createElement('div');
        notification.className = 'notification notification-' + type;
        notification.textContent = message;

        // Apply inline styles for positioning and appearance.
        notification.style.position = 'fixed';
        notification.style.bottom = '20px';
        notification.style.right = '20px';
        notification.style.padding = '15px 25px';
        notification.style.borderRadius = '8px';
        notification.style.zIndex = '9999';
        notification.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.15)';
        notification.style.animation = 'slideIn 0.3s ease';

        // Set background color based on notification type.
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

        // Add notification to the DOM.
        document.body.appendChild(notification);

        // Set timeout to remove notification after 3 seconds.
        currentNotificationTimeout = setTimeout(function()
        {
            notification.style.animation = 'slideOut 0.3s ease';

            setTimeout(function()
            {
                notification.remove();
                currentNotificationTimeout = null;
            }, 300);
        }, 3000);
    }

    /**
     * Validates an email address format.
     * Uses regular expression to check standard email pattern.
     *
     * @param {string} email - The email address to validate.
     * @returns {boolean} True if valid, false otherwise.
     */
    function validateEmail(email)
    {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Validates that a string is not empty or contains only whitespace.
     *
     * @param {string} value - The value to check.
     * @returns {boolean} True if not empty, false otherwise.
     */
    function isNotEmpty(value)
    {
        return value !== null && value.trim() !== '';
    }

    /**
     * Formats a number as currency (South African Rand).
     *
     * @param {number} amount - The amount to format.
     * @returns {string} Formatted currency string.
     */
    function formatCurrency(amount)
    {
        return 'R ' + amount.toFixed(2);
    }

    /**
     * Makes an AJAX request to the server with CSRF protection.
     * Automatically includes the CSRF token in headers for POST/PUT/DELETE.
     *
     * @param {string} url - The request URL.
     * @param {string} method - HTTP method (GET, POST, PUT, DELETE).
     * @param {Object|null} data - Request data (for POST/PUT).
     * @returns {Promise} Promise that resolves with the response.
     */
    function ajaxRequest(url, method, data)
    {
        const safeMethod = method || 'GET';
        const options = {
            method: safeMethod,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': getCsrfToken()
            },
            credentials: 'same-origin'
        };

        if (data && (safeMethod === 'POST' || safeMethod === 'PUT'))
        {
            options.body = JSON.stringify(data);
        }

        return fetch(url, options)
            .then(function(response)
            {
                if (!response.ok)
                {
                    throw new Error('HTTP error ' + response.status);
                }
                return response.json();
            })
            .catch(function(error)
            {
                console.error('AJAX request failed:', error);
                showNotification('Network error. Please try again.', 'error');
                throw error;
            });
    }

    /**
     * Initializes the mobile menu toggle functionality.
     * Adds click handler to toggle button and closes menu when clicking outside.
     * Required for responsive design per mockup requirements.
     */
    function initMobileMenu()
    {
        const toggleBtn = document.querySelector('.mobile-menu-toggle');
        const mobileMenu = document.querySelector('.mobile-menu');

        if (!toggleBtn || !mobileMenu)
        {
            return;
        }

        // Toggle menu on button click.
        toggleBtn.addEventListener('click', function(event)
        {
            event.stopPropagation();
            const isExpanded = toggleBtn.getAttribute('aria-expanded') === 'true';
            toggleBtn.setAttribute('aria-expanded', !isExpanded);
            mobileMenu.setAttribute('aria-hidden', isExpanded);

            if (mobileMenu.classList)
            {
                mobileMenu.classList.toggle('active');
            }
        });

        // Close menu when clicking outside.
        document.addEventListener('click', function(event)
        {
            const isMenuVisible = mobileMenu.classList
                ? mobileMenu.classList.contains('active')
                : mobileMenu.getAttribute('aria-hidden') === 'false';

            if (isMenuVisible)
            {
                const isClickInside = mobileMenu.contains(event.target) || toggleBtn.contains(event.target);

                if (!isClickInside)
                {
                    toggleBtn.setAttribute('aria-expanded', 'false');
                    mobileMenu.setAttribute('aria-hidden', 'true');
                    if (mobileMenu.classList)
                    {
                        mobileMenu.classList.remove('active');
                    }
                }
            }
        });

        // Close menu on escape key.
        document.addEventListener('keydown', function(event)
        {
            if (event.key === 'Escape')
            {
                const isMenuVisible = mobileMenu.classList
                    ? mobileMenu.classList.contains('active')
                    : mobileMenu.getAttribute('aria-hidden') === 'false';

                if (isMenuVisible)
                {
                    toggleBtn.setAttribute('aria-expanded', 'false');
                    mobileMenu.setAttribute('aria-hidden', 'true');
                    if (mobileMenu.classList)
                    {
                        mobileMenu.classList.remove('active');
                    }
                }
            }
        });
    }

    /**
     * Initializes all modal dialogs on the page.
     * Adds click handlers to close buttons and background overlays.
     */
    function initModals()
    {
        const modals = document.querySelectorAll('.modal');

        for (let i = 0; i < modals.length; i++)
        {
            const modal = modals[i];

            // Close modal when clicking on the overlay background.
            modal.addEventListener('click', function(event)
            {
                if (event.target === modal)
                {
                    hideModal();
                }
            });

            // Close modal when clicking the close button.
            const closeBtn = modal.querySelector('.modal-close');
            if (closeBtn)
            {
                closeBtn.addEventListener('click', function()
                {
                    hideModal();
                });
            }
        }
    }

    /**
     * Initializes the password strength indicator on registration and forgot password pages.
     * Monitors input events and updates the strength bar accordingly.
     */
    function initPasswordStrength()
    {
        const passwordField = document.getElementById('password') || document.getElementById('new_password');
        const strengthFill = document.getElementById('strength-fill');

        if (!passwordField || !strengthFill)
        {
            return;
        }

        function updateStrength()
        {
            const password = passwordField.value;
            let score = 0;

            // Length check.
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;

            // Complexity checks.
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^a-zA-Z0-9]/.test(password)) score++;

            // Reset classes.
            strengthFill.className = 'strength-fill';

            // Apply appropriate strength class.
            if (score <= 2)
            {
                strengthFill.classList.add('strength-weak');
            }
            else if (score <= 4)
            {
                strengthFill.classList.add('strength-fair');
            }
            else if (score <= 6)
            {
                strengthFill.classList.add('strength-good');
            }
            else
            {
                strengthFill.classList.add('strength-strong');
            }
        }

        passwordField.addEventListener('input', updateStrength);
        updateStrength();
    }

    /**
     * Initializes the copy to clipboard functionality for User ID display.
     */
    function initCopyToClipboard()
    {
        const copyBtns = document.querySelectorAll('.btn-copy');

        for (let i = 0; i < copyBtns.length; i++)
        {
            const btn = copyBtns[i];

            btn.addEventListener('click', function(event)
            {
                event.preventDefault();

                const userIdElement = document.getElementById('generated-user-id');
                if (!userIdElement)
                {
                    return;
                }

                const text = userIdElement.textContent;

                if (navigator.clipboard && navigator.clipboard.writeText)
                {
                    navigator.clipboard.writeText(text)
                        .then(function()
                        {
                            const originalText = btn.innerHTML;
                            btn.innerHTML = '<i class="fas fa-check"></i> Copied!';

                            setTimeout(function()
                            {
                                btn.innerHTML = originalText;
                            }, 2000);
                        })
                        .catch(function()
                        {
                            alert('Failed to copy. Please copy manually.');
                        });
                }
                else
                {
                    // Fallback for older browsers.
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    document.body.appendChild(textarea);
                    textarea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textarea);
                    alert('Copied to clipboard!');
                }
            });
        }
    }

    /**
     * Initializes all form validation enhancements.
     * Adds real-time validation feedback where appropriate.
     */
    function initFormValidation()
    {
        // Add real-time email validation.
        const emailFields = document.querySelectorAll('input[type="email"]');

        for (let i = 0; i < emailFields.length; i++)
        {
            const field = emailFields[i];

            field.addEventListener('blur', function()
            {
                const value = this.value;
                let errorSpan = this.parentElement.parentElement.querySelector('.form-error');

                if (!errorSpan)
                {
                    errorSpan = document.createElement('span');
                    errorSpan.className = 'form-error';
                    this.parentElement.parentElement.appendChild(errorSpan);
                }

                if (value && !validateEmail(value))
                {
                    errorSpan.textContent = 'Please enter a valid email address.';
                    this.classList.add('error');
                }
                else
                {
                    errorSpan.textContent = '';
                    this.classList.remove('error');
                }
            });
        }
    }

    /**
     * Updates the current year in the footer copyright.
     * Uses UTC to ensure consistency across time zones.
     */
    function updateCopyrightYear()
    {
        const yearElement = document.getElementById('current-year');
        if (yearElement)
        {
            const now = new Date();
            const utcYear = now.getUTCFullYear();
            yearElement.textContent = utcYear;
        }
    }

    // Initialize all components when DOM is fully loaded.
    document.addEventListener('DOMContentLoaded', function()
    {
        initMobileMenu();
        initModals();
        initPasswordStrength();
        initCopyToClipboard();
        initFormValidation();
        updateCopyrightYear();

        console.log('Campus Eats: Global JavaScript initialized.');
    });

    // Expose global functions for use across the application.
    window.getCsrfToken = getCsrfToken;
    window.escapeHtml = escapeHtml;
    window.showModal = showModal;
    window.hideModal = hideModal;
    window.showNotification = showNotification;
    window.validateEmail = validateEmail;
    window.isNotEmpty = isNotEmpty;
    window.formatCurrency = formatCurrency;
    window.ajaxRequest = ajaxRequest;
})();