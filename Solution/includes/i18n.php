<?php
/**
 * Internationalisation Helper
 *
 * Provides a small translation layer for the application. It loads a
 * language file into memory and exposes two functions:
 *
 *   __($key, $default)     - returns the translated string for a key
 *   getCurrentLanguage()   - returns the active language code
 *
 * The language is chosen in this order:
 *
 *   1. The "lang" query parameter, if it is a supported code. Selecting
 *      a language through the query parameter also stores it in the
 *      session so subsequent requests keep the same language.
 *
 *   2. The "lang" key in the session, if it is a supported code.
 *
 *   3. The default language, which is English.
 *
 * Supported languages are listed in $GLOBALS['_SUPPORTED_LANGUAGES'].
 * The language files live in Solution/lang/ and return an associative
 * array of key to string.
 *
 * CORRECTIONS (Version 1.0):
 * - Initial implementation. This file does not exist in the earlier
 *   versions of the project.
 * - The fallback for a missing key is the key itself, so an untranslated
 *   string is visible in the interface rather than blank.
 * - The file never throws. A missing or malformed language file results
 *   in an empty translation table, and every __() call returns its
 *   default or its key.
 *
 * SOURCE: NOTES - Include multi-language support for at least two
 *         South African languages: English and Afrikaans.
 *
 * @version 1.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

// =============================================================================
// Supported Languages
// =============================================================================

if (!isset($GLOBALS['_SUPPORTED_LANGUAGES']))
{
    // The keys are the ISO 639-1 codes. The values are the labels shown
    // in the language switcher. The order determines the order in the
    // switcher. English is first because it is the default.
    $GLOBALS['_SUPPORTED_LANGUAGES'] = array(
        'en' => 'English',
        'af' => 'Afrikaans'
    );
}

if (!isset($GLOBALS['_DEFAULT_LANGUAGE']))
{
    $GLOBALS['_DEFAULT_LANGUAGE'] = 'en';
}

if (!isset($GLOBALS['_TRANSLATIONS']))
{
    $GLOBALS['_TRANSLATIONS'] = array();
}

if (!isset($GLOBALS['_CURRENT_LANGUAGE']))
{
    $GLOBALS['_CURRENT_LANGUAGE'] = null;
}

// =============================================================================
// Session Safety
// =============================================================================
//
// The helper may be included from a page that has not yet started a
// session. The selected language is stored in the session, so this
// include attempts to start one only if none is active. If the session
// cannot be started, the language selection falls back to the query
// parameter and the default, and nothing is stored between requests.
// =============================================================================

if (session_status() !== PHP_SESSION_ACTIVE)
{
    // Suppress the warning if headers have already been sent. In that
    // case the session cannot be started, and the language selection
    // remains per-request only.
    @session_start();
}

// =============================================================================
// Language Resolution
// =============================================================================

if (!function_exists('resolveCurrentLanguage'))
{
    /**
     * Resolves the active language code.
     *
     * @return string The active language code, always a supported value
     */
    function resolveCurrentLanguage()
    {
        if ($GLOBALS['_CURRENT_LANGUAGE'] !== null)
        {
            return $GLOBALS['_CURRENT_LANGUAGE'];
        }

        $supported = $GLOBALS['_SUPPORTED_LANGUAGES'];

        // 1. Query parameter.
        if (isset($_GET['lang']))
        {
            $candidate = strtolower(trim((string)$_GET['lang']));

            if (isset($supported[$candidate]))
            {
                if (session_status() === PHP_SESSION_ACTIVE)
                {
                    $_SESSION['lang'] = $candidate;
                }

                $GLOBALS['_CURRENT_LANGUAGE'] = $candidate;
                return $candidate;
            }
        }

        // 2. Session value.
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['lang']))
        {
            $candidate = strtolower(trim((string)$_SESSION['lang']));

            if (isset($supported[$candidate]))
            {
                $GLOBALS['_CURRENT_LANGUAGE'] = $candidate;
                return $candidate;
            }
        }

        // 3. Default.
        $GLOBALS['_CURRENT_LANGUAGE'] = $GLOBALS['_DEFAULT_LANGUAGE'];
        return $GLOBALS['_DEFAULT_LANGUAGE'];
    }
}

if (!function_exists('getCurrentLanguage'))
{
    /**
     * Returns the active language code.
     *
     * @return string The active language code
     */
    function getCurrentLanguage()
    {
        return resolveCurrentLanguage();
    }
}

if (!function_exists('getSupportedLanguages'))
{
    /**
     * Returns the map of supported language codes to display labels.
     *
     * @return array Map of code to label
     */
    function getSupportedLanguages()
    {
        return $GLOBALS['_SUPPORTED_LANGUAGES'];
    }
}

if (!function_exists('setCurrentLanguage'))
{
    /**
     * Sets the active language for the current session.
     *
     * @param string $code The language code
     * @return bool True if the code is supported and was stored
     */
    function setCurrentLanguage($code)
    {
        $code = strtolower(trim((string)$code));

        if (!isset($GLOBALS['_SUPPORTED_LANGUAGES'][$code]))
        {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE)
        {
            $_SESSION['lang'] = $code;
        }

        $GLOBALS['_CURRENT_LANGUAGE'] = $code;

        return true;
    }
}

// =============================================================================
// Translation Loading
// =============================================================================

if (!function_exists('loadTranslations'))
{
    /**
     * Loads the translation table for the active language.
     *
     * The file at Solution/lang/<code>.php is included once. It must
     * return an associative array of key to string. A missing or
     * malformed file results in an empty table, and every subsequent
     * __() call falls back to the default or the key.
     *
     * @return array The loaded translation table
     */
    function loadTranslations()
    {
        $language = getCurrentLanguage();

        if (isset($GLOBALS['_TRANSLATIONS'][$language]))
        {
            return $GLOBALS['_TRANSLATIONS'][$language];
        }

        $path = BASE_PATH . '/lang/' . $language . '.php';

        if (!file_exists($path))
        {
            $GLOBALS['_TRANSLATIONS'][$language] = array();
            return $GLOBALS['_TRANSLATIONS'][$language];
        }

        try
        {
            $table = include $path;

            if (!is_array($table))
            {
                $table = array();
            }

            $GLOBALS['_TRANSLATIONS'][$language] = $table;
            return $table;
        }
        catch (Throwable $t)
        {
            // The language file must never crash the request.
            $GLOBALS['_TRANSLATIONS'][$language] = array();
            return $GLOBALS['_TRANSLATIONS'][$language];
        }
    }
}

if (!function_exists('__'))
{
    /**
     * Returns the translated string for a key.
     *
     * Lookup order:
     *   1. The active language table.
     *   2. The English table, if the active language is not English.
     *   3. The provided default.
     *   4. The key itself.
     *
     * The function never returns null and never throws. A missing
     * translation is visible in the interface rather than hidden.
     *
     * @param string      $key     The translation key
     * @param string|null $default Optional default string
     * @return string The translated or fallback string
     */
    function __($key, $default = null)
    {
        $key = (string)$key;
        $table = loadTranslations();

        if (isset($table[$key]))
        {
            return (string)$table[$key];
        }

        // Fall back to English if the active language is not English.
        $language = getCurrentLanguage();

        if ($language !== 'en')
        {
            $englishPath = BASE_PATH . '/lang/en.php';

            if (file_exists($englishPath) && !isset($GLOBALS['_TRANSLATIONS']['en']))
            {
                try
                {
                    $english = include $englishPath;

                    if (is_array($english))
                    {
                        $GLOBALS['_TRANSLATIONS']['en'] = $english;
                    }
                    else
                    {
                        $GLOBALS['_TRANSLATIONS']['en'] = array();
                    }
                }
                catch (Throwable $t)
                {
                    $GLOBALS['_TRANSLATIONS']['en'] = array();
                }
            }

            if (isset($GLOBALS['_TRANSLATIONS']['en'][$key]))
            {
                return (string)$GLOBALS['_TRANSLATIONS']['en'][$key];
            }
        }

        if ($default !== null)
        {
            return (string)$default;
        }

        return $key;
    }
}

if (!function_exists('__e'))
{
    /**
     * Returns the escaped translation for a key, safe for HTML output.
     *
     * This is the recommended form for any translation that will be
     * echoed directly into HTML.
     *
     * @param string      $key     The translation key
     * @param string|null $default Optional default string
     * @return string The escaped translated string
     */
    function __e($key, $default = null)
    {
        return htmlspecialchars(__($key, $default), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('getLanguageSwitcherHtml'))
{
    /**
     * Returns the HTML for a language switcher.
     *
     * The switcher is a set of links. Each link preserves the current
     * path and query string and adds a lang parameter.
     *
     * @return string The HTML for the switcher
     */
    function getLanguageSwitcherHtml()
    {
        $supported = $GLOBALS['_SUPPORTED_LANGUAGES'];
        $current = getCurrentLanguage();
        $currentPath = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

        // Strip any existing lang parameter from the path.
        $currentPath = preg_replace('/([?&])lang=[^&]*(&|$)/', '$1', $currentPath);
        $currentPath = rtrim($currentPath, '?&');

        $separator = (strpos($currentPath, '?') === false) ? '?' : '&';

        $html = '<div class="language-switcher" aria-label="Language selection">';

        foreach ($supported as $code => $label)
        {
            $active = ($code === $current) ? ' active' : '';
            $url = $currentPath . $separator . 'lang=' . urlencode($code);

            $html .= '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"';
            $html .= ' class="lang-link' . $active . '"';
            $html .= ' hreflang="' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '"';

            if ($active)
            {
                $html .= ' aria-current="true"';
            }

            $html .= '>';
            $html .= htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
            $html .= '</a>';
        }

        $html .= '</div>';

        return $html;
    }
}

// =============================================================================
// Automatic Load
// =============================================================================
//
// Loading the translation table here makes __() ready on first call
// without any additional include. If the file does not exist, the
// loader returns an empty table and every __() call falls back to its
// default or its key.
// =============================================================================

loadTranslations();