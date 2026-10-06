<?php
/**
 * English Language File
 *
 * Returns the English translation table for the application.
 *
 * Usage:
 *   echo __('nav.home');            // Home
 *   echo __e('auth.sign_in');       // Sign in (HTML escaped)
 *
 * Keys are grouped by area with a dot separator. A key that is not
 * present here falls back to the key itself, or to the default
 * argument passed to __().
 *
 * CORRECTIONS (Version 3.0 - Authentication UI Remediation):
 * - Added the missing sign-in page keys that previously rendered
 *   as literal strings (login.title, login.subtitle,
 *   login.hint_identifier, auth.show_password, auth.hide_password,
 *   common.or).
 * - Replaced every residual key with a clear, intention-revealing
 *   English string following Clean Code Chapter 2 (“Meaningful Names”).
 * - Ensured the same catalogue is used by both the sign-in and
 *   registration views so that no key ever reaches the browser.
 *
 * SOURCE: Software Engineering Prompt – Resolve Campus Eats
 *         Authentication UI and Runtime Failures.
 * SOURCE: Clean Code, Robert C. Martin, Chapter 2.
 *
 * @version 3.0
 */

return array(

    // -------------------------------------------------------------------------
    // Application
    // -------------------------------------------------------------------------

    'app.name'                      => 'Campus Eats',
    'app.tagline'                   => 'Skip the line. Pick up on campus.',

    // -------------------------------------------------------------------------
    // Navigation
    // -------------------------------------------------------------------------

    'nav.home'                      => 'Home',
    'nav.about'                     => 'About',
    'nav.services'                  => 'Services',
    'nav.faq'                       => 'FAQ',
    'nav.help'                      => 'Help Center',
    'nav.dashboard'                 => 'Dashboard',
    'nav.cart'                      => 'Cart',
    'nav.language'                  => 'Language',

    // -------------------------------------------------------------------------
    // Authentication (shared)
    // -------------------------------------------------------------------------

    'auth.sign_in'                  => 'Sign in',
    'auth.sign_out'                 => 'Logout',
    'auth.sign_up'                  => 'Create account',
    'auth.email'                    => 'Email',
    'auth.email_or_user_id'         => 'Email or student number',
    'auth.email_placeholder'        => 'e.g. name@campus.edu',
    'auth.password'                 => 'Password',
    'auth.password_placeholder'     => 'Enter your password',
    'auth.confirm_password'         => 'Confirm password',
    'auth.full_name'                => 'Full name',
    'auth.username'                 => 'Username',
    'auth.role'                     => 'Role',
    'auth.forgot_password'          => 'Recover account',
    'auth.new_password'             => 'New password',
    'auth.reset_password'           => 'Reset password',
    'auth.back_to_sign_in'          => 'Back to Sign in',
    'auth.return_home'              => 'Return Home',
    'auth.already_have_account'     => 'Already have an account?',
    'auth.no_account'               => 'New here?',
    'auth.sign_in_google'           => 'Sign in with Google',
    'auth.sign_up_google'           => 'Sign up with Google',
    'auth.sso_not_configured'       => 'Google SSO is not configured on this server.',
    'auth.show_password'            => 'Show password',
    'auth.hide_password'            => 'Hide password',

    // -------------------------------------------------------------------------
    // Sign-in page (specific)
    // -------------------------------------------------------------------------

    'login.title'                   => 'Sign in to Campus Eats',
    'login.subtitle'                => 'Enter your campus credentials to continue',
    'login.hint_identifier'         => 'You may use your email, username, or 16-character User ID.',

    // -------------------------------------------------------------------------
    // Common
    // -------------------------------------------------------------------------

    'common.or'                     => 'or',

    // -------------------------------------------------------------------------
    // Roles
    // -------------------------------------------------------------------------

    'role.student'                  => 'Student',
    'role.standard'                 => 'Standard',
    'role.vendor'                   => 'Vendor',
    'role.admin'                    => 'Administrator',
    'role.admin_first_user'         => 'Admin (first user only)',

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    'register.title'                        => 'Create account',
    'register.subtitle'                     => 'Join the campus pickup network',
    'register.name_placeholder'             => 'Your full name',
    'register.username_placeholder'         => 'Choose a username',
    'register.username_hint'                => 'Optional. If left empty, the username is derived from your email.',
    'register.email_placeholder'            => 'you@campus.edu',
    'register.password_placeholder'         => 'Create a password',
    'register.confirm_password_placeholder' => 'Re-enter your password',
    'register.hint_password'                => 'Minimum 8 characters, includes uppercase, number, and special character.',
    'register.hint_student'                 => 'Students receive a 2.5 percent discount on orders.',
    'register.hint_admin_first'             => 'The Admin role is available only for this first registration.',
    'register.vendor_shop_name'             => 'Shop name',
    'register.vendor_shop_placeholder'      => 'Name of your campus stall',
    'register.success_heading'              => 'Account created successfully.',
    'register.success_body'                 => 'You can now sign in with your credentials.',
    'register.offline_message'              => 'The database is temporarily unavailable. Your registration has been queued and will be processed when the service recovers.',
    'register.admin_taken'                  => 'The Administrator role is available only for the first account.',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------

    'error.generic'                 => 'An error occurred. Please try again later.',
    'error.csrf'                    => 'Security validation failed. Please refresh the page and try again.',
    'error.required_fields'         => 'Please fill in all required fields.',
    'error.invalid_email'           => 'Please enter a valid email address.',
    'error.invalid_credentials'     => 'Invalid email, username, or password.',
    'error.account_suspended'       => 'Your account has been suspended. Please contact an administrator.',
    'error.account_unverified'      => 'Your account has not been verified yet. Please wait for administrator approval.',
    'error.vendor_pending'          => 'Your vendor account is pending administrative approval.',
    'error.rate_limited'            => 'Too many failed login attempts. Please wait before trying again.',
    'error.password_mismatch'       => 'Passwords do not match.',
    'error.password_policy'         => 'Password must be at least 8 characters long and contain at least one uppercase letter, one digit, and one special symbol.',
    'error.email_exists'            => 'An account with this email already exists. Please log in.',
    'error.vendor_shop_required'    => 'A shop name is required for vendor accounts.',

    // -------------------------------------------------------------------------
    // Success
    // -------------------------------------------------------------------------

    'success.registered'            => 'Account created successfully. You can now log in.',
    'success.password_reset'        => 'Your password has been reset successfully. You can now log in with your new password.',

    // -------------------------------------------------------------------------
    // Feedback
    // -------------------------------------------------------------------------

    'feedback.title'                => 'Submit Feedback',
    'feedback.subtitle'             => 'Share your experience with us.',
    'feedback.type'                 => 'Feedback Type',
    'feedback.type_complaint'       => 'Complaint: report an issue',
    'feedback.type_compliment'      => 'Compliment: share something positive',
    'feedback.subject'              => 'Subject',
    'feedback.message'              => 'Message',
    'feedback.submit'               => 'Submit Feedback',
    'feedback.success'              => 'Your feedback has been submitted successfully. An administrator will review it.',

);