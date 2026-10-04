<?php
/**
 * Fallback Data Loader
 *
 * Provides a small, bundled dataset that the application serves when
 * the Fake Restaurant API is unreachable and no stale response is
 * available. The fallback exists so the UI remains functional during
 * an outage. The fallback is clearly marked as demonstration data.
 *
 * The fallback is read once per request and cached in memory. The
 * file is JSON. When the file is missing or malformed, the loader
 * returns an empty array so the caller can render an empty state
 * instead of an error page.
 *
 * SOURCE: REPORT.txt, Known Issue to Resolve.
 *
 * @version 1.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

if (!function_exists('campus_eats_load_fallback_restaurants'))
{
    /**
     * Returns the fallback restaurant list.
     *
     * @return array The fallback list
     */
    function campus_eats_load_fallback_restaurants()
    {
        static $cached = null;

        if ($cached !== null)
        {
            return $cached;
        }

        $path = BASE_PATH . '/data/fallback_restaurants.json';

        if (!is_readable($path))
        {
            $cached = array();
            return $cached;
        }

        $content = file_get_contents($path);

        if ($content === false)
        {
            $cached = array();
            return $cached;
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded))
        {
            $cached = array();
            return $cached;
        }

        $cached = $decoded;
        return $cached;
    }
}