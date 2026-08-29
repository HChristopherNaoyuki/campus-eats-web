/**
 * Authentication JavaScript for Campus Eats (Complete Refactor)
 *
 * Handles password visibility toggle, password strength indicator,
 * form validation, and clipboard copy functionality.
 *
 * SOURCE: campus-eats-process-document.pdf (Authentication Requirements)
 * SOURCE: Mockups - 18.png, 18a.png, 19.png, 20.png
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
     * Toggles password visibility for a specific input field.
     * @param {string} inputId
     * @param {string} iconId
     */
    function togglePasswordVisibility(inputId, iconId)
    {
        const passwordInput = document.getElementById(inputId);
        const toggleIcon = document.getElementById(iconId);

        if (passwordInput && toggleIcon)
        {
            if (passwordInput.type === 'password')
            {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
            else
            {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    }

    /**
     * Updates the password strength indicator.
     * Called on input event for password fields.
     */
    function updatePasswordStrength()
    {
        const passwordField = document.getElementById('password') || document.getElementById('new_password');
        const strengthFill = document.getElementById('strength-fill');

        if (!passwordField || !strengthFill)
        {
            return;
        }

        const password = passwordField.value;
        let score = 0;

        if (password.length >= 8) score++;
        if (password.length >= 12) score++;
        if (/[A-Z]/.test(password)) score++;
        if (/[a-z]/.test(password)) score++;
        if (/[0-9]/.test(password)) score++;
        if (/[^a-zA-Z0-9]/.test(password)) score++;

        strengthFill.className = 'strength-fill';

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

    /**
     * Copies text to the clipboard.
     * @param {string} text
     * @param {HTMLElement} buttonElement
     */
    function copyToClipboard(text, buttonElement)
    {
        if (navigator.clipboard && navigator.clipboard.writeText)
        {
            navigator.clipboard.writeText(text)
                .then(function()
                {
                    const originalText = buttonElement.innerHTML;
                    buttonElement.innerHTML = '<i class="fas fa-check"></i> Copied!';

                    setTimeout(function()
                    {
                        buttonElement.innerHTML = originalText;
                    }, 2000);
                })
                .catch(function()
                {
                    alert('Failed to copy. Please copy manually.');
                });
        }
        else
        {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            alert('Copied to clipboard!');
        }
    }

    // Initialize password toggles.
    document.addEventListener('DOMContentLoaded', function()
    {
        // Login page toggle.
        const togglePasswordBtn = document.getElementById('toggle-password-btn');
        if (togglePasswordBtn)
        {
            togglePasswordBtn.addEventListener('click', function()
            {
                togglePasswordVisibility('password', 'toggle-icon');
            });
        }

        // Registration page toggles.
        const togglePasswordPassword = document.getElementById('toggle-password-password');
        if (togglePasswordPassword)
        {
            togglePasswordPassword.addEventListener('click', function()
            {
                togglePasswordVisibility('password', 'toggle-icon-password');
            });
        }

        const togglePasswordConfirm = document.getElementById('toggle-password-confirm');
        if (togglePasswordConfirm)
        {
            togglePasswordConfirm.addEventListener('click', function()
            {
                togglePasswordVisibility('confirm_password', 'toggle-icon-confirm');
            });
        }

        // Forgot password page toggles.
        const togglePasswordNew = document.getElementById('toggle-password-new');
        if (togglePasswordNew)
        {
            togglePasswordNew.addEventListener('click', function()
            {
                togglePasswordVisibility('new_password', 'toggle-icon-new');
            });
        }

        const togglePasswordForgotConfirm = document.getElementById('toggle-password-confirm-forgot');
        if (togglePasswordForgotConfirm && document.getElementById('confirm_password'))
        {
            togglePasswordForgotConfirm.addEventListener('click', function()
            {
                togglePasswordVisibility('confirm_password', 'toggle-icon-confirm-forgot');
            });
        }

        // Password strength monitoring.
        const passwordField = document.getElementById('password') || document.getElementById('new_password');
        if (passwordField)
        {
            passwordField.addEventListener('input', updatePasswordStrength);
        }

        // Copy User ID functionality.
        const copyBtn = document.querySelector('.btn-copy');
        if (copyBtn)
        {
            const userIdElement = document.getElementById('generated-user-id');
            if (userIdElement)
            {
                copyBtn.addEventListener('click', function(event)
                {
                    event.preventDefault();
                    copyToClipboard(userIdElement.textContent, copyBtn);
                });
            }
        }

        // Login form submission handler.
        const loginForm = document.getElementById('login-form');
        if (loginForm)
        {
            const loginBtn = document.getElementById('login-btn');
            loginForm.addEventListener('submit', function()
            {
                if (loginBtn)
                {
                    loginBtn.disabled = true;
                    loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Logging in...';
                }
            });
        }

        // Registration form submission handler with validation.
        const registerForm = document.getElementById('register-form');
        if (registerForm)
        {
            const registerBtn = document.getElementById('register-btn');
            registerForm.addEventListener('submit', function(event)
            {
                const password = document.getElementById('password').value;
                const confirm = document.getElementById('confirm_password').value;
                const hasUpper = /[A-Z]/.test(password);
                const hasDigit = /[0-9]/.test(password);
                const hasSpecial = /[^a-zA-Z0-9]/.test(password);

                if (password !== confirm)
                {
                    event.preventDefault();
                    alert('Passwords do not match.');
                    return false;
                }

                if (password.length < 8 || !hasUpper || !hasDigit || !hasSpecial)
                {
                    event.preventDefault();
                    alert('Password must be at least 8 characters long and contain at least 1 uppercase letter, 1 digit, and 1 special symbol.');
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

        // Forgot password form submission handler.
        const resetForm = document.getElementById('reset-form');
        if (resetForm)
        {
            const resetBtn = document.getElementById('reset-btn');
            resetForm.addEventListener('submit', function(event)
            {
                const newPassword = document.getElementById('new_password').value;
                const confirmPassword = document.getElementById('confirm_password').value;
                const userIdElement = document.getElementById('user_id');
                const userId = userIdElement ? userIdElement.value : '';
                const hasUpper = /[A-Z]/.test(newPassword);
                const hasDigit = /[0-9]/.test(newPassword);
                const hasSpecial = /[^a-zA-Z0-9]/.test(newPassword);

                if (newPassword !== confirmPassword)
                {
                    event.preventDefault();
                    alert('Passwords do not match.');
                    return false;
                }

                if (newPassword.length < 8 || !hasUpper || !hasDigit || !hasSpecial)
                {
                    event.preventDefault();
                    alert('Password must be at least 8 characters long and contain at least 1 uppercase letter, 1 digit, and 1 special symbol.');
                    return false;
                }

                if (userId.length !== 16 || !/^\d+$/.test(userId))
                {
                    event.preventDefault();
                    alert('User ID must be exactly 16 characters and contain only digits.');
                    return false;
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