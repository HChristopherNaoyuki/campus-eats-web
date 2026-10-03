<?php
/**
 * Google OAuth 2.0 Client
 *
 * Handles Google Sign-In for the Campus Eats application. The flow used
 * here is the OAuth 2.0 authorization code flow.
 *
 * CORRECTIONS (Version 3.0 - Audit Continuation):
 *
 * - Fix 1 (absolute redirect URI). The previous version of
 *   googleRedirectUri() returned a path such as
 *   "/campus-eats-web/solution/modules/auth/google_callback.php".
 *   Google rejects a path-only value with the error
 *   redirect_uri_mismatch. The corrected function builds an absolute
 *   URL from the request scheme, host, and the application base path.
 *   The URI must be registered exactly, including scheme, host, port,
 *   and path, in the Google Cloud Console for every host that will run
 *   the application.
 *
 * - Fix 2 (CA bundle for the token exchange). The googleHttpPost() and
 *   googleHttpGet() functions now merge the CA bundle options supplied
 *   by the network helper at Solution/config/network.php. The helper
 *   resolves the bundle in this order: curl.cainfo from php.ini,
 *   openssl.cafile from php.ini, then the bundled CA bundle at
 *   Solution/config/cacert.pem. Certificate verification remains
 *   enabled. The helper does not disable CURLOPT_SSL_VERIFYPEER or
 *   CURLOPT_SSL_VERIFYHOST.
 *
 * - Fix 3 (Google client secret is read only from the environment).
 *   The secret must be supplied through the GOOGLE_CLIENT_SECRET
 *   environment variable. The client ID is a public value and is
 *   present in the code as a fallback. When the secret is missing,
 *   googleIsConfigured() returns false, and the start action renders
 *   a clear error page instead of attempting a redirect that cannot
 *   succeed.
 *
 * - Fix 4 (no verbose database error to the user). The callback in
 *   Solution/modules/auth/google_callback.php no longer echoes the raw
 *   database error to the browser. The error is logged. The user sees
 *   a generic message.
 *
 * - Retained all Version 2.0 behaviour: state token generation and
 *   verification, ID token verification against Google JWKS, and the
 *   Standard-role default for new users.
 *
 * SOURCE: Audit continuation, Part 1.
 * SOURCE: Campus Eats PHP Web Platform - Technical Audit Report,
 *         Section 1 and Section 5.
 * SOURCE: Notes - Make use of single sign-on (SSO).
 *
 * @version 3.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/network.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/error_logging.php';

// =============================================================================
// Configuration
// =============================================================================
//
// The client ID is a public identifier. It is safe to store it in the
// repository. It is the value registered with the Google Cloud project
// that owns the OAuth 2.0 client.
//
// The client secret is not a public value. It must be supplied through
// the GOOGLE_CLIENT_SECRET environment variable. When the secret is
// not set, googleIsConfigured() returns false and the start action
// renders a clear error page. The application never attempts a
// redirect that cannot succeed.
//
// The redirect URI is computed at runtime from the request. It must be
// registered in the Google Cloud Console for every host that will run
// the application.
// =============================================================================

if (!defined('GOOGLE_CLIENT_ID'))
{
    $clientIdFromEnv = getenv('GOOGLE_CLIENT_ID');

    if ($clientIdFromEnv !== false && $clientIdFromEnv !== '')
    {
        define('GOOGLE_CLIENT_ID', $clientIdFromEnv);
    }
    else
    {
        define(
            'GOOGLE_CLIENT_ID',
            '64265928399-tl7gi0kkolvke8k7etaio9h66ov44hi3.apps.googleusercontent.com'
        );
    }
}

if (!defined('GOOGLE_CLIENT_SECRET'))
{
    $clientSecretFromEnv = getenv('GOOGLE_CLIENT_SECRET');

    if ($clientSecretFromEnv !== false)
    {
        define('GOOGLE_CLIENT_SECRET', $clientSecretFromEnv);
    }
    else
    {
        define('GOOGLE_CLIENT_SECRET', '');
    }
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
     * Returns true when the Google OAuth credentials have been set.
     *
     * Both the client ID and the client secret are required for the
     * authorization code flow to complete. The client ID is always
     * present because it has a default value. The client secret has no
     * default and must be supplied through the environment.
     *
     * @return bool True when both values are present
     */
    function googleIsConfigured()
    {
        return (
            GOOGLE_CLIENT_ID !== '' &&
            GOOGLE_CLIENT_SECRET !== ''
        );
    }
}

if (!function_exists('googleRequestScheme'))
{
    /**
     * Returns the scheme of the current request.
     *
     * The scheme is "https" when the connection is over TLS, when a
     * reverse proxy reports that the original request was over TLS, or
     * when the X-Forwarded-Proto header says so. Otherwise the scheme
     * is "http".
     *
     * @return string The scheme
     */
    function googleRequestScheme()
    {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        {
            return 'https';
        }

        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])
            && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        {
            return 'https';
        }

        return 'http';
    }
}

if (!function_exists('googleRequestHost'))
{
    /**
     * Returns the host of the current request, including the port when
     * the port is not the default for the scheme.
     *
     * @return string The host
     */
    function googleRequestHost()
    {
        if (isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] !== '')
        {
            return $_SERVER['HTTP_HOST'];
        }

        if (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== '')
        {
            return $_SERVER['SERVER_NAME'];
        }

        return 'localhost';
    }
}

if (!function_exists('googleRedirectUri'))
{
    /**
     * Returns the absolute redirect URI registered with Google.
     *
     * Google requires an absolute URI. A path-only value is rejected
     * with the error redirect_uri_mismatch. The function builds the
     * absolute URL from the request scheme, host, and the application
     * base path.
     *
     * The URI must be registered exactly, including scheme, host,
     * port, and path, in the Google Cloud Console for every host that
     * will run the application.
     *
     * @return string The absolute redirect URI
     */
    function googleRedirectUri()
    {
        return googleRequestScheme() . '://' . googleRequestHost()
             . BASE_URL . '/modules/auth/google_callback.php';
    }
}

if (!function_exists('googleStartUrl'))
{
    /**
     * Returns the URL of the file that initiates the flow.
     *
     * @return string The start URL
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
     * @return string The state token
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
     * The state value is valid for ten minutes. This bounds the time
     * during which an intercepted callback can be replayed.
     *
     * @param string $submitted The state value from the query string
     * @return bool True when the state is valid
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
     * The network helper supplies the CA bundle path. Certificate
     * verification remains enabled. When the helper cannot resolve a
     * bundle, the request proceeds with the PHP defaults. The failure
     * mode is a cURL error 60, which is reported to the caller.
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

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/x-www-form-urlencoded'
            ),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        );

        // Apply the CA bundle options from the network helper. When
        // the helper cannot resolve a bundle, the merge is a no-op and
        // the request proceeds with the PHP defaults.
        if (function_exists('campus_eats_curl_ssl_options'))
        {
            $options = array_replace($options, campus_eats_curl_ssl_options());
        }

        curl_setopt_array($ch, $options);

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

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        );

        if (function_exists('campus_eats_curl_ssl_options'))
        {
            $options = array_replace($options, campus_eats_curl_ssl_options());
        }

        curl_setopt_array($ch, $options);

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
        $asn1 .= chr(0x02) . chr(0x01) . chr(0x02);
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
     * The JWKS is cached in the session for ten minutes. The previous
     * version fetched the keys on every login. The cache reduces the
     * number of round trips to Google.
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
            throw new RuntimeException(
                'Unsupported ID token algorithm: ' . $header['alg']
            );
        }

        $jwks = googleFetchJwks();

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
            throw new RuntimeException(
                'No Google public key matches the ID token.'
            );
        }

        $publicKey = openssl_pkey_get_public(googleJwkToPem($matchingKey));

        if ($publicKey === false)
        {
            throw new RuntimeException(
                'Unable to convert Google public key.'
            );
        }

        $signedData = $parts[0] . '.' . $parts[1];
        $verified = openssl_verify(
            $signedData,
            $signature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        if ($verified !== 1)
        {
            throw new RuntimeException(
                'ID token signature verification failed.'
            );
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

        $validIssuers = array(
            'accounts.google.com',
            'https://accounts.google.com'
        );

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
            throw new RuntimeException(
                'ID token does not contain an email address.'
            );
        }

        if (isset($payload['email_verified'])
            && $payload['email_verified'] !== true)
        {
            throw new RuntimeException(
                'Google account email has not been verified.'
            );
        }

        return $payload;
    }
}

if (!function_exists('googleFetchJwks'))
{
    /**
     * Returns the Google JWKS, using a session cache with a ten-minute
     * time-to-live.
     *
     * @return array The JWKS
     * @throws RuntimeException When the keys cannot be fetched
     */
    function googleFetchJwks()
    {
        if (session_status() !== PHP_SESSION_ACTIVE)
        {
            @session_start();
        }

        $cacheKey = 'google_jwks_cache';
        $cacheTimeKey = 'google_jwks_cache_time';

        if (isset($_SESSION[$cacheKey]) && isset($_SESSION[$cacheTimeKey]))
        {
            $age = time() - (int)$_SESSION[$cacheTimeKey];

            if ($age < 600 && is_array($_SESSION[$cacheKey]))
            {
                return $_SESSION[$cacheKey];
            }
        }

        $response = googleHttpGet(GOOGLE_JWKS_ENDPOINT);

        if ($response['status'] !== 200)
        {
            // When the fetch fails, fall back to a stale cache entry
            // rather than rejecting the login. Google's keys change
            // rarely. A stale entry is unlikely to be incorrect.
            if (isset($_SESSION[$cacheKey]) && is_array($_SESSION[$cacheKey]))
            {
                writeLog(
                    "Google JWKS fetch failed. Using stale cache entry.",
                    "AUTH"
                );
                return $_SESSION[$cacheKey];
            }

            throw new RuntimeException(
                'Unable to fetch Google public keys.'
            );
        }

        $jwks = json_decode($response['body'], true);

        if (!is_array($jwks) || !isset($jwks['keys']))
        {
            throw new RuntimeException(
                'Malformed Google public key set.'
            );
        }

        $_SESSION[$cacheKey] = $jwks;
        $_SESSION[$cacheTimeKey] = time();

        return $jwks;
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
     * A vendor whose account is not yet approved is rejected with a
     * clear message. The previous version allowed an unapproved vendor
     * to sign in.
     *
     * @param array $claims The verified ID token claims
     * @return array The MySQL user row
     * @throws RuntimeException When the user cannot be resolved
     */
    function googleFindOrCreateUser($claims)
    {
        $db = getDB();
        $email = (string)$claims['email'];
        $fullName = isset($claims['name'])
            ? (string)$claims['name']
            : $email;

        $existing = $db->fetchOne(
            "SELECT u.user_id, u.unique_id, u.full_name, u.username,
                    u.email, u.password_hash, u.account_type,
                    u.is_active, u.is_verified,
                    v.vendor_id, v.vendor_name, v.is_approved
             FROM users u
             LEFT JOIN vendors v ON u.user_id = v.vendor_user_id
             WHERE u.email = :email
             LIMIT 1",
            array('email' => $email)
        );

        if ($existing)
        {
            if ((int)$existing['is_active'] !== 1)
            {
                throw new RuntimeException(
                    'This account has been suspended. '
                        . 'Please contact an administrator.'
                );
            }

            if ((int)$existing['is_verified'] !== 1)
            {
                throw new RuntimeException(
                    'This account has not been verified. '
                        . 'Please contact an administrator.'
                );
            }

            if ($existing['account_type'] === 'vendor')
            {
                if (!isset($existing['is_approved'])
                    || (int)$existing['is_approved'] !== 1)
                {
                    throw new RuntimeException(
                        'This vendor account is pending administrative '
                            . 'approval.'
                    );
                }
            }

            return $existing;
        }

        if (!function_exists('generateAlphanumericUserId'))
        {
            require_once BASE_PATH . '/includes/user_id.php';
        }

        $uniqueId = generateAlphanumericUserId('standard');
        $username = explode('@', $email)[0] . '_' . random_int(1000, 9999);

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
            throw new RuntimeException(
                'Unable to create a user account.'
            );
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
     * Redirects the authenticated user to the dashboard for their role.
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
     * The raw exception text is not shown to the user. The error is
     * logged. The user sees a readable message.
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
        echo '<p><a href="'
            . htmlspecialchars(googleStartUrl(), ENT_QUOTES, 'UTF-8')
            . '">';
        echo 'Try again</a> or ';
        echo '<a href="'
            . htmlspecialchars(
                BASE_URL . '/modules/auth/login.php',
                ENT_QUOTES,
                'UTF-8'
            )
            . '">';
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
            'Google SSO is not fully configured on this server. '
                . 'An administrator must set the GOOGLE_CLIENT_SECRET '
                . 'environment variable. The client ID is already present.'
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