<?php
/**
 * Network Helper for Campus Eats
 *
 * Provides the CA-bundle resolution and cURL SSL options used by
 * every outbound HTTPS caller.
 *
 * CORRECTIONS (Version 5.0 - CA size guard):
 *
 * - A candidate CA file is accepted only when it is readable and
 *   larger than 1 024 bytes. An empty or one-byte cacert.pem is
 *   skipped so cURL never receives a bad CURLOPT_CAINFO path that
 *   produces CURLE_SSL_CACERT_BADFILE (error 77).
 *
 * SOURCE: Technical Audit Update – Demo Accounts, Coupons, and SSL.
 *
 * @version 5.0
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

        $candidates[] = 'C:/xampp/php/extras/ssl/cacert.pem';
        $candidates[] = 'C:/xampp/apache/bin/curl-ca-bundle.crt';
        $candidates[] = 'C:/wamp64/bin/php/php8.3.14/extras/ssl/cacert.pem';
        $candidates[] = 'C:/wamp64/bin/php/php8.3.6/extras/ssl/cacert.pem';
        $candidates[] = 'C:/wamp64/bin/php/php8.2.0/extras/ssl/cacert.pem';
        $candidates[] = 'C:/wamp64/bin/php/php8.1.0/extras/ssl/cacert.pem';
        $candidates[] = '/etc/ssl/certs/ca-certificates.crt';
        $candidates[] = '/etc/pki/tls/certs/ca-bundle.crt';
        $candidates[] = '/etc/ssl/ca-bundle.pem';
        $candidates[] = '/usr/local/share/ca-certificates/cacert.pem';
        $candidates[] = BASE_PATH . '/config/cacert.pem';

        return $candidates;
    }
}

if (!function_exists('campus_eats_resolve_ca_bundle'))
{
    /**
     * Returns the path to a CA bundle that cURL can use, or null
     * when no usable bundle is found.
     *
     * A file is accepted only when it is readable and larger than
     * 1 024 bytes. This rejects an empty or truncated cacert.pem.
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
            if (empty($candidate) || !is_readable($candidate))
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
     * Returns the cURL options that apply the resolved CA bundle.
     * Returns an empty array when no usable bundle exists so the
     * caller proceeds with the PHP defaults and never receives a
     * bad CURLOPT_CAINFO value.
     *
     * @return array
     */
    function campus_eats_curl_ssl_options()
    {
        $options = array();

        $bundle = campus_eats_resolve_ca_bundle();

        if (!empty($bundle) && is_readable($bundle) && filesize($bundle) >= 1024)
        {
            $options[CURLOPT_CAINFO] = $bundle;
        }

        return $options;
    }
}