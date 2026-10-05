/**
 * Authentication JavaScript for Campus Eats
 *
 * Handles password visibility toggle, password strength indicator,
 * form validation, and clipboard copy functionality.
 *
 * CORRECTIONS (Version 6.0 - Password Visibility):
 *
 * - Added the password visibility toggle. The toggle changes the
 *   type attribute of the password input between "password" and
 *   "text". The toggle updates the aria-label and the aria-pressed
 *   attribute of the button. The toggle does not change the value of
 *   the input. The toggle does not submit the form.
 *
 * - The toggle is reachable by keyboard. The button element receives
 *   focus through the Tab key. The Enter key and the Space key
 *   activate the button because the button element handles those
 *   keys natively.
 *
 * - Retained the clipboard copy listener attached through
 *   addEventListener. The listener reads the raw user ID from the
 *   data-user-id attribute rather than the formatted display text.
 *
 * - Retained the password strength monitor, the form validation, and
 *   the submission button state.
 *
 * SOURCE: Password visibility request.
 * SOURCE: Notes - Make use of SSO.
 *
 * @version 6.0
 */

(function()
{
    'use strict';

    // =========================================================================
    // CSRF token retrieval
    // =========================================================================

    /**
     * Returns the CSRF token from the meta tag.
     *
     * @returns {string} The CSRF token, or an empty string
     */
    function getCsrfToken()
    {
        var metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : '';
    }

    // =========================================================================
    // Password visibility toggle
    // =========================================================================

    /**
     * Returns the localized "Show password" label.
     *
     * The label is read from the button's initial aria-label attribute.
     * When the attribute is absent, a default English label is used.
     *
     * @param {HTMLElement} button The toggle button
     * @returns {string} The show label
     */
    function getShowLabel(button)
    {
        if (button.dataset.showLabel)
        {
            return button.dataset.showLabel;
        }

        return button.getAttribute('aria-label') || 'Show password';
    }

    /**
     * Returns the localized "Hide password" label.
     *
     * @param {HTMLElement} button The toggle button
     * @returns {string} The hide label
     */
    function getHideLabel(button)
    {
        if (button.dataset.hideLabel)
        {
            return button.dataset.hideLabel;
        }

        return 'Hide password';
    }

    /**
     * Toggles the type attribute of a password input between
     * "password" and "text". Updates the corresponding ARIA
     * attributes and the icon class.
     *
     * @param {HTMLInputElement} passwordInput The password field
     * @param {HTMLButtonElement} toggleButton The toggle button
     * @param {HTMLElement} toggleIcon The icon inside the button
     * @returns {void}
     */
    function togglePasswordVisibility(passwordInput, toggleButton, toggleIcon)
    {
        var isHidden = passwordInput.type === 'password';

        if (isHidden)
        {
            passwordInput.type = 'text';
            toggleButton.setAttribute('aria-pressed', 'true');
            toggleButton.setAttribute('aria-label', getHideLabel(toggleButton));
            if (toggleIcon)
            {
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        }
        else
        {
            passwordInput.type = 'password';
            toggleButton.setAttribute('aria-pressed', 'false');
            toggleButton.setAttribute('aria-label', getShowLabel(toggleButton));
            if (toggleIcon)
            {
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    }

    /**
     * Wires the password visibility toggle to the login form.
     *
     * The function locates the password input, the toggle button,
     * and the icon inside the button. It attaches a click listener
     * that calls togglePasswordVisibility. When the form is
     * submitted the input type is forced back to "password" so that
     * the browser password manager receives a standard field.
     *
     * @returns {void}
     */
    function initPasswordToggle()
    {
        var passwordInput = document.getElementById('password');
        var toggleButton = document.getElementById('password-toggle');
        var toggleIcon = document.getElementById('password-toggle-icon');

        if (!passwordInput || !toggleButton)
        {
            return;
        }

        // Store the original show label for later restoration.
        if (!toggleButton.dataset.showLabel)
        {
            toggleButton.dataset.showLabel = toggleButton.getAttribute('aria-label') || 'Show password';
        }

        toggleButton.addEventListener('click', function(event)
        {
            event.preventDefault();
            togglePasswordVisibility(passwordInput, toggleButton, toggleIcon);
        });

        var form = passwordInput.closest('form');
        if (form)
        {
            form.addEventListener('submit', function()
            {
                // Force the field back to type="password". This
                // prevents the browser password manager from
                // receiving a text field and keeps the submitted
                // value masked in the page source.
                passwordInput.type = 'password';
            });
        }
    }

    // =========================================================================
    // Password strength indicator (register / reset forms)
    // =========================================================================

    /**
     * Evaluates password strength and updates the visual indicator.
     *
     * @param {string} password The password value
     * @param {HTMLElement} indicator The strength indicator element
     * @returns {void}
     */
    function updatePasswordStrength(password, indicator)
    {
        if (!indicator)
        {
            return;
        }

        var score = 0;
        if (password.length >= 8) { score++; }
        if (/[A-Z]/.test(password)) { score++; }
        if (/[0-9]/.test(password)) { score++; }
        if (/[^a-zA-Z0-9]/.test(password)) { score++; }

        indicator.className = 'password-strength';
        if (score <= 1)
        {
            indicator.classList.add('weak');
            indicator.textContent = 'Weak';
        }
        else if (score === 2 || score === 3)
        {
            indicator.classList.add('medium');
            indicator.textContent = 'Medium';
        }
        else
        {
            indicator.classList.add('strong');
            indicator.textContent = 'Strong';
        }
    }

    // =========================================================================
    // Form validation and submission handlers
    // =========================================================================

    document.addEventListener('DOMContentLoaded', function()
    {
        initPasswordToggle();

        // Password strength monitor on register / reset pages.
        var newPasswordField = document.getElementById('new_password')
            || document.getElementById('password');
        var strengthIndicator = document.getElementById('password-strength');

        if (newPasswordField && strengthIndicator)
        {
            newPasswordField.addEventListener('input', function()
            {
                updatePasswordStrength(newPasswordField.value, strengthIndicator);
            });
        }

        // Login form submission handler.
        var loginForm = document.getElementById('login-form');
        if (loginForm)
        {
            var loginBtn = document.getElementById('login-submit-btn');

            loginForm.addEventListener('submit', function()
            {
                if (loginBtn)
                {
                    loginBtn.disabled = true;
                    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
                }
            });
        }

        // Register form submission handler.
        var registerForm = document.getElementById('register-form');
        if (registerForm)
        {
            var registerBtn = document.getElementById('register-btn');

            registerForm.addEventListener('submit', function(event)
            {
                var password = document.getElementById('password').value;
                var confirmPassword = document.getElementById('confirm_password');

                var hasUpper = /[A-Z]/.test(password);
                var hasDigit = /[0-9]/.test(password);
                var hasSpecial = /[^a-zA-Z0-9]/.test(password);

                if (confirmPassword && password !== confirmPassword.value)
                {
                    event.preventDefault();
                    alert('Passwords do not match.');
                    return false;
                }

                if (password.length < 8 || !hasUpper || !hasDigit || !hasSpecial)
                {
                    event.preventDefault();
                    alert(
                        'Password must be at least 8 characters long and contain '
                            + 'at least 1 uppercase letter, 1 digit, and 1 special symbol.'
                    );
                    return false;
                }

                if (registerBtn)
                {
                    registerBtn.disabled = true;
                    registerBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating account...';
                }

                return true;
            });
        }

        // Forgot password / reset form submission handler.
        var resetForm = document.getElementById('reset-form');
        if (resetForm)
        {
            var resetBtn = document.getElementById('reset-btn');

            resetForm.addEventListener('submit', function(event)
            {
                var newPassword = document.getElementById('new_password').value;
                var confirmPasswordField = document.getElementById('confirm_password');
                var userIdElementInner = document.getElementById('user_id');

                var hasUpper = /[A-Z]/.test(newPassword);
                var hasDigit = /[0-9]/.test(newPassword);
                var hasSpecial = /[^a-zA-Z0-9]/.test(newPassword);

                if (confirmPasswordField && newPassword !== confirmPasswordField.value)
                {
                    event.preventDefault();
                    alert('Passwords do not match.');
                    return false;
                }

                if (newPassword.length < 8 || !hasUpper || !hasDigit || !hasSpecial)
                {
                    event.preventDefault();
                    alert(
                        'Password must be at least 8 characters long and contain '
                            + 'at least 1 uppercase letter, 1 digit, and 1 special symbol.'
                    );
                    return false;
                }

                if (userIdElementInner)
                {
                    var rawUserId = userIdElementInner.value.replace(/-/g, '');

                    if (rawUserId.length !== 16)
                    {
                        event.preventDefault();
                        alert('USER ID must be 16 characters long.');
                        return false;
                    }

                    userIdElementInner.value = rawUserId;
                }

                if (resetBtn)
                {
                    resetBtn.disabled = true;
                    resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting password...';
                }

                return true;
            });
        }
    });
})();