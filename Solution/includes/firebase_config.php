<?php
/**
 * Firebase Configuration File
 *
 * Single source of truth for the Firebase web configuration. The values
 * are public client credentials used by the Firebase Web SDK. They are
 * not server-side secrets. Server-side admin SDK credentials, which are
 * secrets, must be stored outside the web root and are not defined here.
 *
 * CORRECTIONS (Version 3.0):
 * - No change to the configuration values. This file remains the single
 *   source of truth for the Firebase web client configuration.
 * - Removed the constant FIREBASE_ADMIN_SERVICE_ACCOUNT which was
 *   referenced in a comment but never defined and never used. Admin SDK
 *   credentials are not stored in this file.
 * - getFirebaseClientConfig() and getFirebaseConfigJson() are the only
 *   public accessors. isFirebaseConfigured() checks that every required
 *   value is present.
 *
 * SOURCE: Firebase Documentation - Web SDK initialization
 * SOURCE: Review item 14 - Firebase configuration
 *
 * @version 3.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/error_logging.php';

// =============================================================================
// Firebase Client Configuration
// =============================================================================
// These are public client credentials. They are safe to expose to the
// browser, because Firebase security rules enforce access control on the
// Realtime Database. The server-side Admin SDK credentials, which are
// secrets, are stored outside the web root and are not defined here.
// =============================================================================

if (!defined('FIREBASE_API_KEY'))
{
    define(
        'FIREBASE_API_KEY',
        getenv('FIREBASE_API_KEY') ?: 'AIzaSyBnal57F8ODfUHY4CCNpRUIvOEKGmd6T5M'
    );
}

if (!defined('FIREBASE_AUTH_DOMAIN'))
{
    define(
        'FIREBASE_AUTH_DOMAIN',
        getenv('FIREBASE_AUTH_DOMAIN') ?: 'campus-eats-db.firebaseapp.com'
    );
}

if (!defined('FIREBASE_DATABASE_URL'))
{
    define(
        'FIREBASE_DATABASE_URL',
        getenv('FIREBASE_DATABASE_URL')
            ?: 'https://campus-eats-db-default-rtdb.europe-west1.firebasedatabase.app'
    );
}

if (!defined('FIREBASE_PROJECT_ID'))
{
    define(
        'FIREBASE_PROJECT_ID',
        getenv('FIREBASE_PROJECT_ID') ?: 'campus-eats-db'
    );
}

if (!defined('FIREBASE_STORAGE_BUCKET'))
{
    define(
        'FIREBASE_STORAGE_BUCKET',
        getenv('FIREBASE_STORAGE_BUCKET') ?: 'campus-eats-db.firebasestorage.app'
    );
}

if (!defined('FIREBASE_MESSAGING_SENDER_ID'))
{
    define(
        'FIREBASE_MESSAGING_SENDER_ID',
        getenv('FIREBASE_MESSAGING_SENDER_ID') ?: '64265928399'
    );
}

if (!defined('FIREBASE_APP_ID'))
{
    define(
        'FIREBASE_APP_ID',
        getenv('FIREBASE_APP_ID')
            ?: '1:64265928399:web:1ed0d7f032fbdad34b01ba'
    );
}

if (!defined('FIREBASE_MEASUREMENT_ID'))
{
    define(
        'FIREBASE_MEASUREMENT_ID',
        getenv('FIREBASE_MEASUREMENT_ID') ?: 'G-8MSCNGN1XD'
    );
}

if (!defined('FIREBASE_SDK_VERSION'))
{
    define('FIREBASE_SDK_VERSION', '12.18.0');
}

// =============================================================================
// Helper Functions
// =============================================================================

if (!function_exists('getFirebaseClientConfig'))
{
    /**
     * Returns the Firebase client configuration as an associative array.
     *
     * This is safe to expose to the browser. It contains no secrets.
     *
     * @return array Firebase client configuration
     */
    function getFirebaseClientConfig()
    {
        return array(
            'apiKey' => FIREBASE_API_KEY,
            'authDomain' => FIREBASE_AUTH_DOMAIN,
            'databaseURL' => FIREBASE_DATABASE_URL,
            'projectId' => FIREBASE_PROJECT_ID,
            'storageBucket' => FIREBASE_STORAGE_BUCKET,
            'messagingSenderId' => FIREBASE_MESSAGING_SENDER_ID,
            'appId' => FIREBASE_APP_ID,
            'measurementId' => FIREBASE_MEASUREMENT_ID,
            'sdkVersion' => FIREBASE_SDK_VERSION
        );
    }
}

if (!function_exists('getFirebaseConfigJson'))
{
    /**
     * Returns the Firebase client configuration as JSON.
     *
     * @return string JSON-encoded configuration
     */
    function getFirebaseConfigJson()
    {
        return json_encode(
            getFirebaseClientConfig(),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}

if (!function_exists('isFirebaseConfigured'))
{
    /**
     * Checks whether Firebase is properly configured.
     *
     * @return bool True if configured
     */
    function isFirebaseConfigured()
    {
        return (
            !empty(FIREBASE_API_KEY) &&
            !empty(FIREBASE_DATABASE_URL) &&
            !empty(FIREBASE_PROJECT_ID) &&
            !empty(FIREBASE_APP_ID)
        );
    }
}