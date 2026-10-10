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
 * CORRECTIONS (Version 3.0 - Footer and Login i18n):
 *
 * - Added the complete footer section: description, social aria
 *   labels (Facebook, Twitter, Instagram). Existing footer keys
 *   (quick_links, account, legal, privacy, terms, contact,
 *   copyright) are retained.
 * - Ensures every key referenced by includes/footer.php resolves
 *   to human-readable English so literal keys never appear in the
 *   rendered page.
 *
 * SOURCE: GUI Assessment and UI/UX Improvement Report.
 * SOURCE: Clean Code, Robert C. Martin, Chapters 2–4.
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
    'nav.orders'                    => 'Orders',
    'nav.menu'                      => 'Menu',
    'nav.reports'                   => 'Reports',
    'nav.users'                     => 'Users',
    'nav.vendors'                   => 'Vendors',
    'nav.feedback'                  => 'Feedback',
    'nav.settings'                  => 'Settings',
    'nav.logout'                    => 'Logout',

    // -------------------------------------------------------------------------
    // Roles
    // -------------------------------------------------------------------------

    'role.admin'                    => 'Administrator',
    'role.vendor'                   => 'Vendor',
    'role.student'                  => 'Student',
    'role.standard'                 => 'Standard',

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    'auth.sign_in'                  => 'Sign in',
    'auth.sign_up'                  => 'Create account',
    'auth.sign_out'                 => 'Logout',
    'auth.sign_in_google'           => 'Sign in with Google',
    'auth.sso_not_configured'       => 'Google SSO is not configured on this server.',
    'auth.email_or_user_id'         => 'Email or student number',
    'auth.email_placeholder'        => 'e.g. name@campus.edu',
    'auth.password'                 => 'Password',
    'auth.password_placeholder'     => 'Enter your password',
    'auth.show_password'            => 'Show password',
    'auth.hide_password'            => 'Hide password',
    'auth.forgot_password'          => 'Recover account',
    'auth.no_account'               => 'New here?',
    'auth.return_home'              => 'Return Home',
    'auth.create_account'           => 'Create account',
    'auth.full_name'                => 'Full name',
    'auth.username'                 => 'Username',
    'auth.confirm_password'         => 'Confirm password',
    'auth.role'                     => 'Role',
    'auth.vendor_shop_name'         => 'Shop name',

    // -------------------------------------------------------------------------
    // Login page
    // -------------------------------------------------------------------------

    'login.title'                   => 'Sign in to Campus Eats',
    'login.subtitle'                => 'Enter your campus credentials to continue',
    'login.hint_identifier'         => 'You may use your email, username, or 16-character User ID.',

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    'register.title'                => 'Create your Campus Eats account',
    'register.subtitle'             => 'Choose a role and complete the form below.',
    'register.first_admin'          => 'You are the first user. The Admin role is available.',
    'register.admin_taken'          => 'An administrator already exists. Choose another role.',
    'register.offline'              => 'The database is temporarily unavailable. Your registration has been queued and will be processed when connectivity returns.',

    // -------------------------------------------------------------------------
    // Help
    // -------------------------------------------------------------------------

    'help.title'                    => 'Help Center',
    'help.subtitle'                 => 'Find answers to your questions and learn how to use Campus Eats.',
    'help.getting_started'          => 'Getting Started',
    'help.student_help'             => 'Student Help',
    'help.vendor_help'              => 'Vendor Help',
    'help.troubleshooting'          => 'Troubleshooting',
    'help.contact'                  => 'Contact Support',

    // -------------------------------------------------------------------------
    // Footer
    // -------------------------------------------------------------------------

    'footer.description'            => 'Order ahead from campus vendors and collect when it suits you. No delivery fee, no waiting.',
    'footer.quick_links'            => 'Quick Links',
    'footer.account'                => 'Account',
    'footer.legal'                  => 'Legal',
    'footer.privacy'                => 'Privacy Policy',
    'footer.terms'                  => 'Terms of Service',
    'footer.contact'                => 'Contact Support',
    'footer.copyright'              => 'Campus Eats. All rights reserved.',
    'footer.social_facebook'        => 'Campus Eats on Facebook',
    'footer.social_twitter'         => 'Campus Eats on Twitter',
    'footer.social_instagram'       => 'Campus Eats on Instagram',

    // -------------------------------------------------------------------------
    // Common
    // -------------------------------------------------------------------------

    'common.loading'                => 'Loading...',
    'common.save'                   => 'Save',
    'common.cancel'                 => 'Cancel',
    'common.close'                  => 'Close',
    'common.confirm'                => 'Confirm',
    'common.delete'                 => 'Delete',
    'common.edit'                   => 'Edit',
    'common.back'                   => 'Back',
    'common.next'                   => 'Next',
    'common.search'                 => 'Search',
    'common.submit'                 => 'Submit',
    'common.or'                     => 'or',

    // -------------------------------------------------------------------------
    // Errors
    // -------------------------------------------------------------------------

    'error.generic'                 => 'An error occurred. Please try again later.',
    'error.csrf'                    => 'Security validation failed. Please refresh the page and try again.',
    'error.required_fields'         => 'Please fill in all required fields.',
    'error.invalid_credentials'     => 'Invalid credentials. Please try again.',
    'error.account_suspended'       => 'Your account has been suspended. Contact support.',
    'error.account_unverified'      => 'Your account has not been verified yet. Please wait for administrator approval.',
    'error.vendor_pending'          => 'Your vendor account is pending administrative approval.',
    'error.rate_limited'            => 'Too many failed login attempts. Please wait before trying again.',
    'error.password_mismatch'       => 'Passwords do not match.',
    'error.password_policy'         => 'Password must be at least 8 characters long and contain at least one uppercase letter, one digit, and one special symbol.',
    'error.email_exists'            => 'An account with this email already exists. Please log in.',

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