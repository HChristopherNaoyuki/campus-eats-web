<?php
/**
 * Network Helper for Campus Eats
 *
 * Provides two functions used by the cURL callers in the application:
 *
 *   campus_eats_resolve_ca_bundle() - returns the path to a CA bundle
 *   campus_eats_curl_ssl_options()  - returns the cURL options that
 *                                     apply the bundle
 *
 * The bundle is resolved in this order:
 *
 *   1. The curl.cainfo directive in php.ini, when it is set and the
 *      file it points at exists.
 *   2. The openssl.cafile directive in php.ini, when it is set and the
 *      file it points at exists.
 *   3. Common XAMPP, Windows, and Linux locations for a CA bundle.
 *   4. The bundled CA bundle at Solution/config/cacert.pem.
 *   5. The operating system certificate store.
 *
 * The helper does not disable certificate verification. The helper
 * does not modify the firewall, the proxy settings, or any other
 * network configuration. The helper only supplies the path to a
 * bundle that PHP can use to verify the certificate chain presented
 * by the remote server.
 *
 * CORRECTIONS (Version 2.0 - Audit Continuation):
 * - Added the common XAMPP, Windows, and Linux bundle locations. The
 *   previous version only checked php.ini and the bundled file.
 * - Added the operating system certificate store as a final fallback.
 * - The candidate list is checked in order. The first readable file is
 *   returned.
 *
 * SOURCE: Audit continuation, Part 1.
 *
 * @version 2.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

if (!function_exists('campus_eats_ca_bundle_candidates'))
{
    /**
     * Returns the ordered list of candidate CA bundle paths.
     *
     * The list is built once per request. Each candidate is a path
     * that may or may not exist on the host.
     *
     * @return array The candidate paths
     */
    function campus_eats_ca_bundle_candidates()
    {
        $candidates = array();

        // 1. curl.cainfo from php.ini.
        $curlCainfo = ini_get('curl.cainfo');

        if (!empty($curlCainfo))
        {
            $candidates[] = $curlCainfo;
        }

        // 2. openssl.cafile from php.ini.
        $opensslCafile = ini_get('openssl.cafile');

        if (!empty($opensslCafile))
        {
            $candidates[] = $opensslCafile;
        }

        // 3. Common XAMPP and Windows locations.
        $candidates[] = 'C:/xampp/php/extras/ssl/cacert.pem';
        $candidates[] = 'C:/xampp/apache/bin/curl-ca-bundle.crt';
        $candidates[] = 'C:/wamp64/bin/php/php8.3.6/extras/ssl/cacert.pem';
        $candidates[] = 'C:/wamp64/bin/php/php8.2.0/extras/ssl/cacert.pem';
        $candidates[] = 'C:/wamp64/bin/php/php8.1.0/extras/ssl/cacert.pem';
        $candidates[] = 'C:/wamp64/bin/php/php7.4.0/extras/ssl/cacert.pem';

        // 4. Common Linux locations.
        $candidates[] = '/etc/ssl/certs/ca-certificates.crt';
        $candidates[] = '/etc/pki/tls/certs/ca-bundle.crt';
        $candidates[] = '/etc/ssl/ca-bundle.pem';
        $candidates[] = '/usr/local/share/ca-certificates/cacert.pem';

        // 5. The bundled CA bundle.
        $candidates[] = BASE_PATH . '/config/cacert.pem';

        return $candidates;
    }
}

if (!function_exists('campus_eats_resolve_ca_bundle'))
{
    /**
     * Returns the path to a CA bundle that cURL can use.
     *
     * @return string|null The path, or null when no readable bundle is found
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

        $candidates = campus_eats_ca_bundle_candidates();

        foreach ($candidates as $candidate)
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
     * When the helper cannot resolve a bundle, the function returns an
     * empty array. The caller then proceeds with the PHP defaults. The
     * function never disables certificate verification.
     *
     * @return array The cURL options
     */
    function campus_eats_curl_ssl_options()
    {
        $options = array();

        $bundle = campus_eats_resolve_ca_bundle();

        if (!empty($bundle))
        {
            $options[CURLOPT_CAINFO] = $bundle;
            $options[CURLOPT_CAPATH] = dirname($bundle);
        }

        return $options;
    }
}