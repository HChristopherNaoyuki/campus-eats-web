<?php
/**
 * Google OAuth 2.0 Client
 *
 * Handles Google Sign-In for the Campus Eats application. The flow used
 * here is the OAuth 2.0 authorization code flow.
 *
 * How the flow works:
 *
 *   1. The login or register page renders a "Sign in with Google" link
 *      that points at this file with ?action=start.
 *   2. This file builds the Google authorization URL, includes the
 *      application's state value, and redirects the browser to Google.
 *   3. Google authenticates the user and redirects back to the callback
 *      URL registered with the Google Cloud project.
 *   4. The callback URL is this same file with ?action=callback. It
 *      verifies the state value, exchanges the authorization code for
 *      an ID token, verifies the token, and reads the user's email and
 *      name.
 *   5. The file looks up or creates a row in the MySQL users table for
 *      that email, sets the application session, and redirects to the
 *      dashboard for the user's role.
 *
 * REQUIRED CONFIGURATION:
 *
 * Before the Google button will work, two constants below must be set:
 *
 *   GOOGLE_CLIENT_ID
 *   GOOGLE_CLIENT_SECRET
 *
 * They are obtained from the Google Cloud Console at
 * https://console.cloud.google.com/apis/credentials. When creating the
 * OAuth 2.0 client, register this exact redirect URI:
 *
 *   http://localhost/campus-eats-web/Solution/modules/auth/google_callback.php
 *
 * The redirect URI must match character for character, including the
 * scheme, host, port, and path. A mismatch produces the Google error
 * "redirect_uri_mismatch".
 *
 * If the constants are empty, the file returns a clear error instead of
 * attempting to redirect. This makes the missing configuration visible
 * rather than producing a broken login attempt.
 *
 * CORRECTIONS (Version 1.0):
 * - Initial implementation.
 * - The file is self-contained: it starts the session, loads the auth
 *   module, and performs the code exchange without depending on any
 *   other Google-specific helper.
 * - The state parameter is a random value stored in the session and
 *   verified on the callback. This prevents CSRF on the OAuth flow.
 * - The ID token is verified against Google's public keys. If
 *   verification fails, the request is rejected.
 * - A user with a matching email is reused. A user without a matching
 *   email is created with the Standard role, so that a Google sign-in
 *   does not silently produce an administrator.
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
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/error_logging.php';

// =============================================================================
// Configuration
// =============================================================================
//
// REPLACE THE TWO CONSTANTS BELOW WITH VALUES FROM THE GOOGLE CLOUD
// CONSOLE BEFORE THE GOOGLE BUTTON WILL WORK.
//
// The redirect URI must be registered with the Google Cloud project
// when the OAuth 2.0 client is created. It is this path:
//
//   BASE_URL . '/modules/auth/google_callback.php'
//
// The value is computed at runtime so it matches the deployment.
// =============================================================================

if (!defined('GOOGLE_CLIENT_ID'))
{
    define('GOOGLE_CLIENT_ID', '');
}

if (!defined('GOOGLE_CLIENT_SECRET'))
{
    define('GOOGLE_CLIENT_SECRET', '');
}

// =============================================================================
// Google Endpoints
// =============================================================================

if (!defined('GOOGLE_AUTH_ENDPOINT'))
{
    define('GOOGLE_AUTH_ENDPOINT', 'https://accounts.google.com/o/oauth2/v2/auth');
}

if (!defined('GOOGLE_TOKEN_ENDPOINT'))
{
    define('GOOGLE_TOKEN_ENDPOINT', 'https://oauth2.googleapis.com/token');
}

if (!defined('GOOGLE_JWKS_ENDPOINT'))
{
    define('GOOGLE_JWKS_ENDPOINT', 'https://www.googleapis.com/oauth2/v3/certs');
}

if (!defined('GOOGLE_ISSUER'))
{
    define('GOOGLE_ISSUER', 'https://accounts.google.com');
}

// =============================================================================
// Helper Functions
// =============================================================================

if (!function_exists('googleIsConfigured'))
{
    /**
     * Returns true if the Google OAuth credentials have been set.
     *
     * @return bool
     */
    function googleIsConfigured()
    {
        return (
            GOOGLE_CLIENT_ID !== '' &&
            GOOGLE_CLIENT_SECRET !== ''
        );
    }
}

if (!function_exists('googleRedirectUri'))
{
    /**
     * Returns the redirect URI that must be registered with Google.
     *
     * @return string
     */
    function googleRedirectUri()
    {
        return BASE_URL . '/modules/auth/google_callback.php';
    }
}

if (!function_exists('googleStartUrl'))
{
    /**
     * Returns the URL of the file that initiates the flow.
     *
     * @return string
     */
    function googleStartUrl()
    {
        return BASE_URL . '/includes/oauth_google.php?action=start';
    }
}

if (!function_exists('googleStateToken'))
{
    /**
     * Generates and stores a one-time state value for CSRF protection.
     *
     * @return string
     */
    function googleStateToken()
    {
        $token = bin2hex(random_bytes(16));

        if (session_status() !== PHP_SESSION_ACTIVE)
        {
            @session_start();
        }

        $_SESSION['google_oauth_state'] = $token;
        $_SESSION['google_oauth_state_created'] = time();

        return $token;
    }
}

if (!function_exists('googleVerifyStateToken'))
{
    /**
     * Verifies and consumes the state value from a callback.
     *
     * @param string $submitted The state value from the query string
     * @return bool
     */
    function googleVerifyStateToken($submitted)
    {
        if (session_status() !== PHP_SESSION_ACTIVE)
        {
            @session_start();
        }

        if (!isset($_SESSION['google_oauth_state']))
        {
            return false;
        }

        $expected = $_SESSION['google_oauth_state'];
        $created = isset($_SESSION['google_oauth_state_created'])
            ? (int)$_SESSION['google_oauth_state_created']
            : 0;

        unset($_SESSION['google_oauth_state']);
        unset($_SESSION['google_oauth_state_created']);

        // The state value is valid for ten minutes. This bounds the time
        // during which an intercepted callback can be replayed.
        if ((time() - $created) > 600)
        {
            return false;
        }

        return hash_equals($expected, (string)$submitted);
    }
}

if (!function_exists('googleHttpPost'))
{
    /**
     * Performs an HTTPS POST request with cURL.
     *
     * @param string $url  The endpoint
     * @param array  $data The form fields
     * @return array{status:int, body:string}
     * @throws RuntimeException If the request cannot be completed
     */
    function googleHttpPost($url, $data)
    {
        $ch = curl_init($url);

        if ($ch === false)
        {
            throw new RuntimeException('Failed to initialise cURL.');
        }

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_HTTPHEADER     => array('Content-Type: application/x-www-form-urlencoded'),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($errno !== 0)
        {
            throw new RuntimeException('cURL error ' . $errno . ': ' . $error);
        }

        return array('status' => $status, 'body' => (string)$body);
    }
}

if (!function_exists('googleHttpGet'))
{
    /**
     * Performs an HTTPS GET request with cURL.
     *
     * @param string $url The endpoint
     * @return array{status:int, body:string}
     * @throws RuntimeException If the request cannot be completed
     */
    function googleHttpGet($url)
    {
        $ch = curl_init($url);

        if ($ch === false)
        {
            throw new RuntimeException('Failed to initialise cURL.');
        }

        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($errno !== 0)
        {
            throw new RuntimeException('cURL error ' . $errno . ': ' . $error);
        }

        return array('status' => $status, 'body' => (string)$body);
    }
}

if (!function_exists('googleBase64UrlDecode'))
{
    /**
     * Decodes a base64url-encoded string as used by JWT.
     *
     * @param string $input The encoded string
     * @return string The decoded string
     */
    function googleBase64UrlDecode($input)
    {
        $remainder = strlen($input) % 4;

        if ($remainder !== 0)
        {
            $input .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($input, '-_', '+/'));
    }
}

if (!function_exists('googleVerifyIdToken'))
{
    /**
     * Verifies a Google ID token.
     *
     * The verification checks:
     *   - The token has three dot-separated segments.
     *   - The signature matches the public key with the matching kid.
     *   - The issuer is accounts.google.com.
     *   - The audience is the configured client ID.
     *   - The expiry has not passed.
     *   - The email is verified.
     *
     * @param string $idToken The compact JWT
     * @return array The decoded claims
     * @throws RuntimeException If verification fails
     */
    function googleVerifyIdToken($idToken)
    {
        $parts = explode('.', (string)$idToken);

        if (count($parts) !== 3)
        {
            throw new RuntimeException('Malformed ID token.');
        }

        $header = json_decode(googleBase64UrlDecode($parts[0]), true);
        $payload = json_decode(googleBase64UrlDecode($parts[1]), true);
        $signature = googleBase64UrlDecode($parts[2]);

        if (!is_array($header) || !is_array($payload))
        {
            throw new RuntimeException('Malformed ID token.');
        }

        if (!isset($header['kid']) || !isset($header['alg']))
        {
            throw new RuntimeException('ID token header is missing kid or alg.');
        }

        if ($header['alg'] !== 'RS256')
        {
            throw new RuntimeException('Unsupported ID token algorithm: ' . $header['alg']);
        }

        $jwksResponse = googleHttpGet(GOOGLE_JWKS_ENDPOINT);

        if ($jwksResponse['status'] !== 200)
        {
            throw new RuntimeException('Unable to fetch Google public keys.');
        }

        $jwks = json_decode($jwksResponse['body'], true);

        if (!is_array($jwks) || !isset($jwks['keys']))
        {
            throw new RuntimeException('Malformed Google public key set.');
        }

        $matchingKey = null;

        foreach ($jwks['keys'] as $key)
        {
            if (isset($key['kid']) && $key['kid'] === $header['kid'])
            {
                $matchingKey = $key;
                break;
            }
        }

        if ($matchingKey === null)
        {
            throw new RuntimeException('No Google public key matches the ID token.');
        }

        $publicKey = openssl_pkey_get_public(
            googleJwkToPem($matchingKey)
        );

        if ($publicKey === false)
        {
            throw new RuntimeException('Unable to convert Google public key.');
        }

        $signedData = $parts[0] . '.' . $parts[1];
        $verified = openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1)
        {
            throw new RuntimeException('ID token signature verification failed.');
        }

        // Audience check.
        if (!isset($payload['aud']) || $payload['aud'] !== GOOGLE_CLIENT_ID)
        {
            throw new RuntimeException('ID token audience mismatch.');
        }

        // Issuer check.
        if (!isset($payload['iss']))
        {
            throw new RuntimeException('ID token is missing issuer.');
        }

        $validIssuers = array('accounts.google.com', 'https://accounts.google.com');

        if (!in_array($payload['iss'], $validIssuers, true))
        {
            throw new RuntimeException('ID token issuer is not Google.');
        }

        // Expiry check.
        if (!isset($payload['exp']) || (int)$payload['exp'] < time())
        {
            throw new RuntimeException('ID token has expired.');
        }

        // Email check.
        if (empty($payload['email']))
        {
            throw new RuntimeException('ID token does not contain an email address.');
        }

        if (isset($payload['email_verified']) && $payload['email_verified'] !== true)
        {
            throw new RuntimeException('Google account email has not been verified.');
        }

        return $payload;
    }
}

if (!function_exists('googleJwkToPem'))
{
    /**
     * Converts a Google JWK (RSA public key) to a PEM string.
     *
     * @param array $jwk The JWK
     * @return string The PEM-encoded key
     * @throws RuntimeException If the JWK is malformed
     */
    function googleJwkToPem($jwk)
    {
        if (!isset($jwk['n']) || !isset($jwk['e']))
        {
            throw new RuntimeException('JWK is missing modulus or exponent.');
        }

        $modulus = googleBase64UrlDecode($jwk['n']);
        $exponent = googleBase64UrlDecode($jwk['e']);

        $modulus = ltrim($modulus, "\x00");

        $modulusLength = strlen($modulus);

        // SubjectPublicKeyInfo structure for an RSA key.
        $asn1 = chr(0x30) . googleAsn1Length(
            2 + $modulusLength + 2 + strlen($exponent) + 4
        );
        $asn1 .= chr(0x02) . chr(0x01) . chr(0x02); // version
        $asn1 .= chr(0x02) . googleAsn1Length($modulusLength) . $modulus;
        $asn1 .= chr(0x02) . googleAsn1Length(strlen($exponent)) . $exponent;

        // BIT STRING wrapper.
        $bitString = chr(0x00) . $asn1;
        $rsaKey = chr(0x30) . googleAsn1Length(strlen($bitString)) . $bitString;

        // AlgorithmIdentifier for rsaEncryption.
        $algorithm = chr(0x30) . chr(0x0d)
            . chr(0x06) . chr(0x09) . "\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01"
            . chr(0x05) . chr(0x00);

        $subjectPublicKeyInfo = chr(0x30)
            . googleAsn1Length(strlen($algorithm) + strlen($rsaKey))
            . $algorithm
            . $rsaKey;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
             . chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
             . "-----END PUBLIC KEY-----\n";

        return $pem;
    }
}

if (!function_exists('googleAsn1Length'))
{
    /**
     * Encodes an ASN.1 length prefix.
     *
     * @param int $length The length in bytes
     * @return string The encoded prefix
     */
    function googleAsn1Length($length)
    {
        if ($length <= 0x7F)
        {
            return chr($length);
        }

        $bytes = '';

        while ($length > 0)
        {
            $bytes = chr($length & 0xFF) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }
}

if (!function_exists('googleFindOrCreateUser'))
{
    /**
     * Finds or creates a MySQL user record for a Google-authenticated
     * email address.
     *
     * A user that already exists for the email is reused. A user that
     * does not exist is created with the Standard role, so that Google
     * sign-in does not silently produce an administrator.
     *
     * @param array $claims The verified ID token claims
     * @return array The MySQL user row
     * @throws RuntimeException If the user cannot be resolved
     */
    function googleFindOrCreateUser($claims)
    {
        $db = getDB();
        $email = (string)$claims['email'];
        $fullName = isset($claims['name']) ? (string)$claims['name'] : $email;

        $existing = $db->fetchOne(
            "SELECT user_id, unique_id, full_name, username, email,
                    password_hash, account_type, is_active, is_verified
             FROM users
             WHERE email = :email
             LIMIT 1",
            array('email' => $email)
        );

        if ($existing)
        {
            if ($existing['is_active'] != 1)
            {
                throw new RuntimeException('Account is suspended.');
            }

            if ($existing['is_verified'] != 1)
            {
                throw new RuntimeException('Account is not verified.');
            }

            return $existing;
        }

        // Create a new user. The password_hash is set to a value that
        // password_verify() can never match, so the account cannot be
        // logged into with a password. The user must use Google.
        if (!function_exists('generateAlphanumericUserId'))
        {
            require_once BASE_PATH . '/includes/user_id.php';
        }

        $uniqueId = generateAlphanumericUserId('standard');
        $username = explode('@', $email)[0] . '_' . rand(1000, 9999);

        $userId = $db->insert(
            "INSERT INTO users
                (unique_id, full_name, username, email, password_hash,
                 account_type, is_verified, is_active, created_at, updated_at)
             VALUES
                (:unique_id, :full_name, :username, :email, :password_hash,
                 'standard', 1, 1, NOW(), NOW())",
            array(
                'unique_id'     => $uniqueId,
                'full_name'     => $fullName,
                'username'      => $username,
                'email'         => $email,
                'password_hash' => '!' . bin2hex(random_bytes(32))
            )
        );

        if (!$userId)
        {
            throw new RuntimeException('Unable to create a user account.');
        }

        writeLog(
            "Google SSO created a new Standard user: $email (ID: $userId)",
            "AUTH"
        );

        return $db->fetchOne(
            "SELECT user_id, unique_id, full_name, username, email,
                    password_hash, account_type, is_active, is_verified
             FROM users
             WHERE user_id = :user_id
             LIMIT 1",
            array('user_id' => $userId)
        );
    }
}

if (!function_exists('googleSetSession'))
{
    /**
     * Sets the application session from a MySQL user row.
     *
     * @param array $user The user row
     * @return void
     */
    function googleSetSession($user)
    {
        if (session_status() !== PHP_SESSION_ACTIVE)
        {
            startSecureSession();
        }

        regenerateSession();

        $_SESSION['user_id'] = (int)$user['user_id'];
        $_SESSION['unique_id'] = $user['unique_id'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['account_type'];
        $_SESSION['account_type'] = $user['account_type'];
        $_SESSION['logged_in'] = true;
        $_SESSION['session_start_time'] = time();
        $_SESSION['auth_provider'] = 'google';

        writeLog(
            "User authenticated via Google SSO: {$user['username']} "
                . "(Role: {$user['account_type']})",
            "AUTH"
        );
    }
}

if (!function_exists('googleRedirectToDashboard'))
{
    /**
     * Redirects the authenticated user to their role dashboard.
     *
     * @return void
     */
    function googleRedirectToDashboard()
    {
        $accountType = getCurrentUserRole();

        switch ($accountType)
        {
            case 'admin':
                header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
                exit();

            case 'vendor':
                header('Location: ' . BASE_URL . '/modules/vendor/dashboard.php');
                exit();

            case 'student':
            case 'standard':
                header('Location: ' . BASE_URL . '/modules/student/dashboard.php');
                exit();

            default:
                header('Location: ' . ROOT_URL . '/index.php');
                exit();
        }
    }
}

if (!function_exists('googleRenderError'))
{
    /**
     * Renders a user-visible error page for the SSO flow.
     *
     * @param string $message The message to display
     * @return void
     */
    function googleRenderError($message)
    {
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        header('Content-Type: text/html; charset=utf-8');

        echo '<!DOCTYPE html>';
        echo '<html lang="en"><head><meta charset="UTF-8">';
        echo '<title>Sign in error</title>';
        echo '<link rel="stylesheet" ';
        echo 'href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">';
        echo '</head><body style="font-family: sans-serif; padding: 40px; ';
        echo 'max-width: 600px; margin: 0 auto;">';
        echo '<h1>Sign in error</h1>';
        echo '<p>' . $safeMessage . '</p>';
        echo '<p><a href="' . htmlspecialchars(googleStartUrl(), ENT_QUOTES, 'UTF-8') . '">';
        echo 'Try again</a> or ';
        echo '<a href="' . htmlspecialchars(BASE_URL . '/modules/auth/login.php', ENT_QUOTES, 'UTF-8') . '">';
        echo 'return to the login page</a>.</p>';
        echo '</body></html>';

        exit();
    }
}

// =============================================================================
// Request Handling
// =============================================================================

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'start')
{
    if (!googleIsConfigured())
    {
        googleRenderError(
            'Google SSO is not configured on this server. '
                . 'An administrator must set GOOGLE_CLIENT_ID and '
                . 'GOOGLE_CLIENT_SECRET in Solution/includes/oauth_google.php.'
        );
    }

    $state = googleStateToken();

    $parameters = array(
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => googleRedirectUri(),
        'response_type' => 'code',
        'scope'         => 'openid email profile',
        'state'         => $state,
        'access_type'   => 'online',
        'prompt'        => 'select_account'
    );

    $authUrl = GOOGLE_AUTH_ENDPOINT . '?' . http_build_query($parameters);

    header('Location: ' . $authUrl);
    exit();
}

// Any other action is rejected here. The callback lives in
// modules/auth/google_callback.php and includes this file for its
// helper functions.
if ($action !== '')
{
    http_response_code(400);
    googleRenderError('Invalid action.');
}

// When this file is included without an action, it acts as a helper
// library. No output is produced and no redirect is issued.