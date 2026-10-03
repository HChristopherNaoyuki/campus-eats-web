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
 *   3. The bundled CA bundle at Solution/config/cacert.pem.
 *
 * The helper does not disable certificate verification. The helper
 * does not modify the firewall, the proxy settings, or any other
 * network configuration. The helper only supplies the path to a
 * bundle that PHP can use to verify the certificate chain presented
 * by the remote server.
 *
 * SOURCE: Audit continuation, Part 1.
 *
 * @version 1.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
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

        // 1. curl.cainfo from php.ini.
        $curlCainfo = ini_get('curl.cainfo');

        if (!empty($curlCainfo) && is_readable($curlCainfo))
        {
            $cachedPath = $curlCainfo;
            return $cachedPath;
        }

        // 2. openssl.cafile from php.ini.
        $opensslCafile = ini_get('openssl.cafile');

        if (!empty($opensslCafile) && is_readable($opensslCafile))
        {
            $cachedPath = $opensslCafile;
            return $cachedPath;
        }

        // 3. The bundled CA bundle.
        $bundled = BASE_PATH . '/config/cacert.pem';

        if (is_readable($bundled))
        {
            $cachedPath = $bundled;
            return $cachedPath;
        }

        // No readable bundle is available. The caller receives null.
        // The request proceeds with the PHP defaults. The failure mode
        // is a cURL error 60, which is logged by the caller.
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