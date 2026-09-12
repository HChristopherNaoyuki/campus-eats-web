/**
 * Authentication JavaScript for Campus Eats
 *
 * Handles password visibility toggle, password strength indicator,
 * form validation, and clipboard copy functionality.
 *
 * CORRECTIONS (Version 5.0):
 * - The Copy button listener is now attached with addEventListener
 *   instead of an inline onclick handler. This allows the Content
 *   Security Policy to remain strict (no unsafe-inline).
 * - Reads the raw USER ID from the data-user-id attribute rather than
 *   from the formatted display text.
 * - Adds error handling for the Clipboard API.
 *
 * SOURCE: Issue report - items 3, 23
 *
 * @version 5.0
 */

(function()
{
    'use strict';

    function getCsrfToken()
    {
        var metaTag = document.querySelector('meta[name="csrf-token"]');
        return metaTag ? metaTag.getAttribute('content') : '';
    }

    function togglePasswordVisibility(inputId, iconId)
    {
        var passwordInput = document.getElementById(inputId);
        var toggleIcon = document.getElementById(iconId);

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

    function updatePasswordStrength()
    {
        var passwordField = document.getElementById('password') ||
                            document.getElementById('new_password');
        var strengthFill = document.getElementById('strength-fill');

        if (!passwordField || !strengthFill)
        {
            return;
        }

        var password = passwordField.value;
        var score = 0;

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
     *
     * @param {string} text The text to copy
     * @param {HTMLElement} buttonElement The button to update visually
     */
    function copyToClipboard(text, buttonElement)
    {
        if (!text)
        {
            return;
        }

        var originalText = buttonElement.innerHTML;

        function showSuccess()
        {
            buttonElement.innerHTML = '<i class="fas fa-check"></i> Copied';
            setTimeout(function()
            {
                buttonElement.innerHTML = originalText;
            }, 2000);
        }

        function showFailure()
        {
            buttonElement.innerHTML = '<i class="fas fa-times"></i> Failed';
            setTimeout(function()
            {
                buttonElement.innerHTML = originalText;
            }, 2000);
        }

        if (navigator.clipboard && navigator.clipboard.writeText)
        {
            navigator.clipboard.writeText(text)
                .then(showSuccess)
                .catch(showFailure);
        }
        else
        {
            try
            {
                var textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.focus();
                textarea.select();

                var successful = document.execCommand('copy');
                document.body.removeChild(textarea);

                if (successful)
                {
                    showSuccess();
                }
                else
                {
                    showFailure();
                }
            }
            catch (error)
            {
                showFailure();
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function()
    {
        // Login page toggle.
        var togglePasswordBtn = document.getElementById('toggle-password-btn');
        if (togglePasswordBtn)
        {
            togglePasswordBtn.addEventListener('click', function()
            {
                togglePasswordVisibility('password', 'toggle-icon');
            });
        }

        // Registration page toggles.
        var togglePasswordPassword = document.getElementById('toggle-password-password');
        if (togglePasswordPassword)
        {
            togglePasswordPassword.addEventListener('click', function()
            {
                togglePasswordVisibility('password', 'toggle-icon-password');
            });
        }

        var togglePasswordConfirm = document.getElementById('toggle-password-confirm');
        if (togglePasswordConfirm)
        {
            togglePasswordConfirm.addEventListener('click', function()
            {
                togglePasswordVisibility('confirm_password', 'toggle-icon-confirm');
            });
        }

        // Forgot password page toggles.
        var togglePasswordNew = document.getElementById('toggle-password-new');
        if (togglePasswordNew)
        {
            togglePasswordNew.addEventListener('click', function()
            {
                togglePasswordVisibility('new_password', 'toggle-icon-new');
            });
        }

        var togglePasswordForgotConfirm = document.getElementById('toggle-password-confirm-forgot');
        if (togglePasswordForgotConfirm && document.getElementById('confirm_password'))
        {
            togglePasswordForgotConfirm.addEventListener('click', function()
            {
                togglePasswordVisibility('confirm_password', 'toggle-icon-confirm-forgot');
            });
        }

        // Password strength monitoring.
        var passwordField = document.getElementById('password') ||
                            document.getElementById('new_password');
        if (passwordField)
        {
            passwordField.addEventListener('input', updatePasswordStrength);
        }

        // CORRECTION: Copy USER ID button attached via addEventListener
        // rather than an inline onclick handler.
        var copyButton = document.getElementById('copy-user-id-btn');
        var userIdElement = document.getElementById('generated-user-id');

        if (copyButton && userIdElement)
        {
            copyButton.addEventListener('click', function(event)
            {
                event.preventDefault();

                var userId = userIdElement.getAttribute('data-user-id');

                if (!userId)
                {
                    userId = userIdElement.textContent.trim().replace(/-/g, '');
                }

                copyToClipboard(userId, copyButton);
            });
        }

        // Login form submission handler.
        var loginForm = document.getElementById('login-form');
        if (loginForm)
        {
            var loginBtn = document.getElementById('login-btn');
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
        var registerForm = document.getElementById('register-form');
        if (registerForm)
        {
            var registerBtn = document.getElementById('register-btn');

            registerForm.addEventListener('submit', function(event)
            {
                var password = document.getElementById('password').value;
                var confirm = document.getElementById('confirm_password');

                var hasUpper = /[A-Z]/.test(password);
                var hasDigit = /[0-9]/.test(password);
                var hasSpecial = /[^a-zA-Z0-9]/.test(password);

                if (confirm && password !== confirm.value)
                {
                    event.preventDefault();
                    alert('Passwords do not match.');
                    return false;
                }

                if (password.length < 8 || !hasUpper || !hasDigit || !hasSpecial)
                {
                    event.preventDefault();
                    alert(
                        'Password must be at least 8 characters long and contain ' +
                        'at least 1 uppercase letter, 1 digit, and 1 special symbol.'
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

        // Forgot password form submission handler.
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
                        'Password must be at least 8 characters long and contain ' +
                        'at least 1 uppercase letter, 1 digit, and 1 special symbol.'
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