<?php
/**
 * Network Helper for Campus Eats
 *
 * Resolves a CA bundle for outbound HTTPS and returns cURL SSL options.
 * Certificate verification is never disabled.
 *
 * CORRECTIONS (Version 6.0 - SSL Remediation):
 *
 * - A candidate file is accepted only when it is readable and larger
 *   than 1 024 bytes. An empty or truncated cacert.pem is skipped.
 * - CURLOPT_CAPATH is not set. Pointing CAPATH at the directory that
 *   holds cacert.pem (without OpenSSL hashed names) causes verification
 *   failures on many Windows / WAMP hosts even when CAINFO is valid.
 * - Resolution order: php.ini curl.cainfo, php.ini openssl.cafile,
 *   common WAMP / XAMPP / Linux paths, then Solution/config/cacert.pem.
 * - When no usable file is found, an empty options array is returned
 *   so cURL uses the PHP / OS defaults. Peer verification stays on.
 *
 * SOURCE: Software Engineering Report, section 2.1 SSL Certificate.
 * SOURCE: Programming PHP, 3rd Edition (cURL and OpenSSL).
 * SOURCE: Clean Code, Robert C. Martin (clear names, single purpose).
 *
 * @version 6.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

if (!function_exists('campus_eats_ca_bundle_candidates'))
{
    /**
     * Ordered list of possible CA-bundle paths on this host.
     *
     * @return array
     */
    function campus_eats_ca_bundle_candidates()
    {
        $candidates = array();

        $curlCainfo = ini_get('curl.cainfo');

        if (!empty($curlCainfo))
        {
            $candidates[] = $curlCainfo;
        }

        $opensslCafile = ini_get('openssl.cafile');

        if (!empty($opensslCafile))
        {
            $candidates[] = $opensslCafile;
        }

        // Common WAMP / XAMPP locations (Windows).
        $candidates[] = 'C:/wamp64/bin/php/php8.3.14/extras/ssl/cacert.pem';
        $candidates[] = 'C:/wamp64/bin/php/php8.3.6/extras/ssl/cacert.pem';
        $candidates[] = 'C:/wamp64/bin/php/php8.2.0/extras/ssl/cacert.pem';
        $candidates[] = 'C:/wamp64/bin/php/php8.1.0/extras/ssl/cacert.pem';
        $candidates[] = 'C:/xampp/php/extras/ssl/cacert.pem';
        $candidates[] = 'C:/xampp/apache/bin/curl-ca-bundle.crt';

        // Common Linux locations.
        $candidates[] = '/etc/ssl/certs/ca-certificates.crt';
        $candidates[] = '/etc/pki/tls/certs/ca-bundle.crt';
        $candidates[] = '/etc/ssl/ca-bundle.pem';
        $candidates[] = '/usr/local/share/ca-certificates/cacert.pem';

        // Bundled project copy (must be a real Mozilla CA file, not empty).
        $candidates[] = BASE_PATH . '/config/cacert.pem';

        return $candidates;
    }
}

if (!function_exists('campus_eats_resolve_ca_bundle'))
{
    /**
     * Returns the path to a usable CA bundle, or null.
     *
     * A file is usable only when it is readable and larger than 1 024
     * bytes. This rejects an empty or one-byte cacert.pem.
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
            if ($candidate === null || $candidate === '')
            {
                continue;
            }

            if (!is_readable($candidate))
            {
                continue;
            }

            $size = @filesize($candidate);

            if ($size === false || $size < 1024)
            {
                continue;
            }

            $cachedPath = $candidate;
            return $cachedPath;
        }

        $cachedPath = null;
        return $cachedPath;
    }
}

if (!function_exists('campus_eats_curl_ssl_options'))
{
    /**
     * cURL options that apply the resolved CA bundle.
     *
     * Only CURLOPT_CAINFO is set. CURLOPT_CAPATH is intentionally
     * omitted. Peer and host verification remain the caller's
     * responsibility (they must stay enabled).
     *
     * @return array
     */
    function campus_eats_curl_ssl_options()
    {
        $options = array();

        $bundle = campus_eats_resolve_ca_bundle();

        if (!empty($bundle)
            && is_readable($bundle)
            && filesize($bundle) >= 1024)
        {
            $options[CURLOPT_CAINFO] = $bundle;
        }

        return $options;
    }
}