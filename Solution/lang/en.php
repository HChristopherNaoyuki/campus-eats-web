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
 * Keys are grouped by area with a dot separator. The prefix is not
 * enforced; it is a naming convention. A key that is not present here
 * falls back to the key itself, or to the default argument passed to
 * __(). See Solution/includes/i18n.php for the resolver.
 *
 * The table below contains every user-facing string that appears in the
 * application's public pages and authentication flow. Strings that are
 * only used in one page may be added here as well; duplication is
 * avoided by giving each string one key.
 *
 * CORRECTIONS (Version 1.0):
 * - Initial English translation table for the multi-language feature.
 * - This file is the source of truth for the Afrikaans table. Every key
 *   present here should also be present in af.php, so that a missing
 *   translation is not silently hidden by the English fallback.
 *
 * SOURCE: NOTES - Include multi-language support for at least two
 *         South African languages: English and Afrikaans.
 *
 * @version 1.0
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
    // Authentication
    // -------------------------------------------------------------------------

    'auth.sign_in'                  => 'Sign in',
    'auth.sign_out'                 => 'Logout',
    'auth.sign_up'                  => 'Create account',
    'auth.email'                    => 'Email',
    'auth.email_or_user_id'         => 'User ID, Username, or Email',
    'auth.email_placeholder'        => '16-character User ID, username, or email',
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

    'register.title'                => 'Create account',
    'register.subtitle'             => 'Join the campus pickup network',
    'register.name_placeholder'     => 'Your full name',
    'register.email_placeholder'    => 'you@campus.edu',
    'register.password_placeholder' => 'Create a password',
    'register.hint_password'        => 'Minimum 8 characters, includes uppercase, number, and special character.',
    'register.hint_student'         => 'Students receive a 2.5 percent discount on orders.',
    'register.hint_admin_first'     => 'The Admin role is available only for this first registration.',
    'register.first_user_title'     => 'You are the first user.',
    'register.first_user_body'      => 'No accounts exist yet. You may register this first account as an administrator. Once any account exists, the Admin role will no longer be offered.',
    'register.success_heading'      => 'Account created successfully.',
    'register.success_body'         => 'Your 16-character USER ID has been generated. You can now log in.',
    'register.user_id_label'        => 'Your 16-character USER ID',
    'register.user_id_note'         => 'Save this ID. You will need it to reset your password.',
    'register.copy_id'              => 'Copy',
    'register.go_to_login'          => 'Go to Login',

    // -------------------------------------------------------------------------
    // Login
    // -------------------------------------------------------------------------

    'login.title'                   => 'Sign in',
    'login.subtitle'                => 'Welcome back to Campus Eats',
    'login.hint_identifier'         => 'You may sign in with the email address, the username, or the 16-character User ID that was shown when you registered.',

    // -------------------------------------------------------------------------
    // Home page
    // -------------------------------------------------------------------------

    'home.hero_title'               => 'Skip the line.',
    'home.hero_subtitle'            => 'Pick up on campus.',
    'home.hero_body'                => 'Campus Eats is the on-campus pickup network. Order ahead from your favorite campus vendor, then grab it on the way to class. No delivery fee, no waiting.',
    'home.order_now'                => 'Order now',
    'home.learn_more'               => 'Learn more',
    'home.stat_vendors'             => 'Campus Vendors',
    'home.stat_items'               => 'Menu Items',
    'home.stat_pickup'              => 'Avg Pickup',
    'home.how_it_works_title'       => 'Pickup in three steps',
    'home.how_it_works_body'        => 'Designed around the campus rhythm, between lectures, before practice, after the library.',
    'home.step_one_title'           => 'Browse and order',
    'home.step_one_body'            => 'Pick items from any campus vendor and confirm your order.',
    'home.step_two_title'           => 'Vendor prepares',
    'home.step_two_body'            => 'Track status as it moves from Pending to Preparing to Completed.',
    'home.step_three_title'         => 'Pick it up',
    'home.step_three_body'          => 'Walk over to the vendor stall and grab your bag. Done.',
    'home.features_title'           => 'Everything the system manages',
    'home.features_body'            => 'Four core modules, as defined in the process specification.',
    'home.feature_user_title'       => 'User Management',
    'home.feature_user_body'        => 'Register and sign in as Student, Standard, Vendor, or Administrator.',
    'home.feature_vendor_title'     => 'Vendor Management',
    'home.feature_vendor_body'      => 'Onboard campus vendors with location and contact details.',
    'home.feature_menu_title'       => 'Menu Management',
    'home.feature_menu_body'        => 'Add, update, and remove menu items per vendor.',
    'home.feature_order_title'      => 'Order Management',
    'home.feature_order_body'       => 'Place orders and track Pending to Preparing to Completed.',
    'home.featured_vendor_title'    => 'Featured Vendor',
    'home.featured_vendor_body'     => 'Discover a campus vendor. Sign up to see all available options.',
    'home.popular_items'            => 'Popular Items',
    'home.vendor_signup_cta'        => 'Run a stall on campus?',
    'home.vendor_signup_body'       => 'List your menu, take pickup orders, and fulfill them with a simple status workflow. Reports for sales, vendor performance, and user activity included.',
    'home.become_vendor'            => 'Become a vendor',

    // -------------------------------------------------------------------------
    // About page
    // -------------------------------------------------------------------------

    'about.title'                   => 'The team behind Campus Eats',
    'about.subtitle'                => 'Campus Eats is a student pickup platform sharing one product language and one order workflow.',
    'about.story_heading'           => 'Our Story',
    'about.offer_heading'           => 'What We Offer',
    'about.students_heading'        => 'For Students',
    'about.vendors_heading'         => 'For Vendors',
    'about.technology_heading'      => 'Our Technology',

    // -------------------------------------------------------------------------
    // FAQ page
    // -------------------------------------------------------------------------

    'faq.title'                     => 'Frequently asked questions',
    'faq.subtitle'                  => 'Answers to the questions students and vendors ask most often.',

    // -------------------------------------------------------------------------
    // Help page
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

    'footer.quick_links'            => 'Quick Links',
    'footer.account'                => 'Account',
    'footer.legal'                  => 'Legal',
    'footer.privacy'                => 'Privacy Policy',
    'footer.terms'                  => 'Terms of Service',
    'footer.contact'                => 'Contact Support',
    'footer.copyright'              => 'Campus Eats. All rights reserved.',

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