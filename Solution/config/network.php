<?php
/**
 * Network and SSL Helpers
 *
 * Supplies the ordered list of CA-bundle candidates and the pure
 * functions that resolve a readable bundle and build the corresponding
 * cURL options. Certificate verification is never disabled.
 *
 * CORRECTIONS (Version 4.0 - SSL Path Fix):
 *
 * - The candidate list now prefers a readable file under the Solution
 *   directory and silently skips any path that is not readable.
 * - campus_eats_curl_ssl_options() returns an empty array when no
 *   readable bundle exists, so cURL falls back to the system store
 *   and never receives a bad CURLOPT_CAINFO value that produces
 *   CURLE_SSL_CACERT_BADFILE.
 * - The external restaurant API therefore continues to use the
 *   bundled fallback data when the certificate store is unavailable,
 *   never blocking the user.
 *
 * SOURCE: SOFTWARE ENGINEER PROMPT – Campus Eats Resilience Fixes.
 *
 * @version 4.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

if (!function_exists('campus_eats_ca_bundle_candidates'))
{
    /**
     * Returns an ordered list of possible CA-bundle paths.
     *
     * @return array
     */
    function campus_eats_ca_bundle_candidates()
    {
        $candidates = array();

        // Prefer the application-bundled file when it is present and readable.
        $candidates[] = BASE_PATH . '/config/cacert.pem';

        // Common Windows development locations.
        $candidates[] = 'C:/wamp64/bin/php/php8.3.14/extras/ssl/cacert.pem';
        $candidates[] = 'C:/wamp64/bin/php/php8.3.6/extras/ssl/cacert.pem';
        $candidates[] = 'C:/wamp64/bin/php/php8.2.0/extras/ssl/cacert.pem';
        $candidates[] = 'C:/xampp/php/extras/ssl/cacert.pem';

        // Common Linux / macOS system locations.
        $candidates[] = '/etc/ssl/certs/ca-certificates.crt';
        $candidates[] = '/etc/pki/tls/certs/ca-bundle.crt';
        $candidates[] = '/usr/local/share/ca-certificates/cacert.pem';
        $candidates[] = '/usr/local/etc/openssl/cert.pem';

        return $candidates;
    }
}

if (!function_exists('campus_eats_resolve_ca_bundle'))
{
    /**
     * Returns the path to a CA bundle that cURL can use, or null
     * when no readable bundle is found.
     *
     * @return string|null
     */
    function campus_eats_resolve_ca_bundle()
    {
        static $resolved = false;
        static $cachedPath = null;

        if ($resolved)
        {
            return $cachedPath;
        }

        $resolved = true;

        foreach (campus_eats_ca_bundle_candidates() as $candidate)
        {
            if (!empty($candidate) && is_readable($candidate))
            {
                $cachedPath = $candidate;
                return $cachedPath;
            }
        }

        $cachedPath = null;
        return $cachedPath;
    }
}

if (!function_exists('campus_eats_curl_ssl_options'))
{
    /**
     * Returns the cURL options that apply the resolved CA bundle.
     *
     * When no readable bundle exists the function returns an empty
     * array. The caller then proceeds with the PHP defaults and never
     * receives a bad CURLOPT_CAINFO value.
     *
     * @return array
     */
    function campus_eats_curl_ssl_options()
    {
        $options = array();

        $bundle = campus_eats_resolve_ca_bundle();

        if (!empty($bundle) && is_readable($bundle))
        {
            $options[CURLOPT_CAINFO] = $bundle;
        }

        return $options;
    }
}