<?php
/**
 * Google OAuth 2.0 Callback Handler
 *
 * Receives the authorization code from Google, exchanges it for an ID
 * token, verifies the token, resolves the matching MySQL user, sets
 * the application session, and redirects to the role dashboard.
 *
 * The file is the redirect target registered with the Google Cloud
 * project. It must be reachable at the exact path:
 *
 *   BASE_URL . '/modules/auth/google_callback.php'
 *
 * CORRECTIONS (Version 1.0):
 * - Initial implementation.
 * - The file includes oauth_google.php for its helper functions. The
 *   helpers are defined there, so the callback does not duplicate any
 *   of the verification logic.
 * - Errors are rendered with googleRenderError() so the user sees a
 *   readable message instead of a blank page or a stack trace.
 *
 * SOURCE: Google Identity Documentation - OAuth 2.0 for Web Server
 *         Applications
 * SOURCE: NOTES - Make use of single sign-on (SSO). Users should also
 *         be able to use Google SSO.
 *
 * @version 1.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__, 2));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/error_logging.php';
require_once BASE_PATH . '/includes/oauth_google.php';

startSecureSession();

// =============================================================================
// Validate the callback parameters.
// =============================================================================

if (!googleIsConfigured())
{
    googleRenderError(
        'Google SSO is not configured on this server. '
            . 'An administrator must set GOOGLE_CLIENT_ID and '
            . 'GOOGLE_CLIENT_SECRET in Solution/includes/oauth_google.php.'
    );
}

if (isset($_GET['error']))
{
    $errorCode = (string)$_GET['error'];
    $errorDescription = isset($_GET['error_description'])
        ? (string)$_GET['error_description']
        : '';

    writeLog(
        "Google SSO returned an error: $errorCode - $errorDescription",
        "AUTH"
    );

    googleRenderError(
        'Google sign-in was cancelled or refused. '
            . 'You may close this page and try again, or sign in with '
            . 'your email and password.'
    );
}

if (!isset($_GET['code']) || empty($_GET['code']))
{
    googleRenderError('Google did not return an authorization code.');
}

$submittedState = isset($_GET['state']) ? (string)$_GET['state'] : '';

if (!googleVerifyStateToken($submittedState))
{
    writeLog("Google SSO state verification failed.", "AUTH");
    googleRenderError(
        'The sign-in request could not be verified. '
            . 'Please start the sign-in again from the login page.'
    );
}

$authorizationCode = (string)$_GET['code'];

// =============================================================================
// Exchange the authorization code for an ID token.
// =============================================================================

try
{
    $tokenResponse = googleHttpPost(
        GOOGLE_TOKEN_ENDPOINT,
        array(
            'code'          => $authorizationCode,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => googleRedirectUri(),
            'grant_type'    => 'authorization_code'
        )
    );
}
catch (RuntimeException $exception)
{
    writeLog(
        "Google SSO token exchange failed: " . $exception->getMessage(),
        "AUTH"
    );

    googleRenderError(
        'The sign-in service could not be reached. '
            . 'Please try again in a moment.'
    );
}

if ($tokenResponse['status'] !== 200)
{
    writeLog(
        "Google SSO token exchange returned HTTP " . $tokenResponse['status'],
        "AUTH"
    );

    googleRenderError(
        'Google rejected the sign-in request. '
            . 'Please try again from the login page.'
    );
}

$tokenData = json_decode($tokenResponse['body'], true);

if (!is_array($tokenData) || empty($tokenData['id_token']))
{
    writeLog("Google SSO token response did not contain an ID token.", "AUTH");

    googleRenderError(
        'Google did not return an identity token. '
            . 'Please try again from the login page.'
    );
}

// =============================================================================
// Verify the ID token and resolve the user.
// =============================================================================

try
{
    $claims = googleVerifyIdToken($tokenData['id_token']);
    $user = googleFindOrCreateUser($claims);
    googleSetSession($user);
}
catch (RuntimeException $exception)
{
    writeLog(
        "Google SSO user resolution failed: " . $exception->getMessage(),
        "AUTH"
    );

    googleRenderError(
        'Your Google account could not be used to sign in. '
            . $exception->getMessage()
    );
}
catch (Exception $exception)
{
    writeLog(
        "Google SSO unexpected error: " . $exception->getMessage(),
        "AUTH"
    );

    googleRenderError(
        'An unexpected error occurred during sign-in. '
            . 'Please try again or use your email and password.'
    );
}

// =============================================================================
// Redirect to the role dashboard.
// =============================================================================

googleRedirectToDashboard();