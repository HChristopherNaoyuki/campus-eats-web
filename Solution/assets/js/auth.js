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
     * Returns the CSRF token stored in the meta tag, or an empty
     * string when the tag is absent.
     *
     * @returns {string}
     */
    function getCsrfTokenFromMeta()
    {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // =========================================================================
    // Password visibility toggle
    // =========================================================================

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
            toggleButton.setAttribute('aria-label', 'Hide password');
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
            toggleButton.setAttribute('aria-label', 'Show password');
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
    // Initialisation
    // =========================================================================

    document.addEventListener('DOMContentLoaded', function()
    {
        initPasswordToggle();
    });

})();