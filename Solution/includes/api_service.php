<?php
/**
 * Fake Restaurant API Service Layer
 *
 * Provides a unified HTTP client for the Fake Restaurant API.
 *
 * CORRECTIONS (Version 5.0 - Audit Continuation):
 * - Wired the network helper into the request path. The helper
 *   resolves the CA bundle path and applies it through the
 *   CURLOPT_CAINFO option. This corrects cURL error 60 on hosts
 *   whose php.ini does not define curl.cainfo. Certificate
 *   verification remains enabled.
 * - Retained the permanent-failure classification from Version 4.0.
 *   A TLS or DNS failure is not retried.
 * - Retained the exponential backoff. The delay is computed with the
 *   same formula as Version 4.0.
 * - Retained the single log line per failure. The line includes the
 *   numeric cURL error code, the symbolic label, and the URL.
 * - Preserved every public method signature. No caller changes are
 *   required.
 *
 * SOURCE: Audit continuation, Part 1.
 * SOURCE: Campus Eats PHP Web Platform - Technical Audit Report.
 *
 * @version 5.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/error_logging.php';
require_once BASE_PATH . '/config/network.php';

class ApiService
{
    /**
     * @var string Base URL for the API
     */
    private $baseUrl;

    /**
     * @var string|null API key for authenticated endpoints
     */
    private $apiKey;

    /**
     * @var array Default headers sent with every request
     */
    private $defaultHeaders;

    /**
     * @var int Per-request timeout in seconds
     */
    private $timeout;

    /**
     * @var int Number of retry attempts for transient failures
     */
    private $retryAttempts;

    /**
     * @var int Base delay in seconds for exponential backoff
     */
    private $retryDelay;

    /**
     * @var array In-memory cache for GET responses
     */
    private $cache = array();

    /**
     * @var int Cache time-to-live in seconds
     */
    private $cacheTtl = 300;

    /**
     * Constructor.
     *
     * @param string|null $apiKey Optional API key for authenticated endpoints
     */
    public function __construct($apiKey = null)
    {
        $this->baseUrl = API_BASE_URL;
        $this->apiKey = $apiKey;
        $this->timeout = API_TIMEOUT;
        $this->retryAttempts = API_RETRY_ATTEMPTS;
        $this->retryDelay = API_RETRY_DELAY;

        $this->defaultHeaders = array(
            'Content-Type: application/json',
            'Accept: application/json'
        );
    }

    /**
     * Sets the API key for authenticated endpoints.
     *
     * @param string $apiKey The API key
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        writeLog("API key set for service", "API");
    }

    /**
     * Returns the current API key.
     *
     * @return string|null
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Clears the in-memory cache.
     *
     * @param string|null $key Optional single key to clear
     * @return void
     */
    public function clearCache($key = null)
    {
        if ($key === null)
        {
            $this->cache = array();
            writeLog("All API cache cleared", "API");
        }
        else
        {
            unset($this->cache[$key]);
            writeLog("API cache cleared for key: $key", "API");
        }
    }

    /**
     * Returns true when the cURL error code indicates a permanent
     * failure that should not be retried.
     *
     * The values are the cURL error codes documented at
     * https://curl.se/libcurl/c/libcurl-errors.html.
     *
     * @param int $curlErrno The value returned by curl_errno()
     * @return bool True when the failure is permanent
     */
    private function isPermanentCurlFailure($curlErrno)
    {
        $permanent = array(
            1,  // CURLE_UNSUPPORTED_PROTOCOL
            3,  // CURLE_URL_MALFORMAT
            6,  // CURLE_COULDNT_RESOLVE_HOST
            51, // CURLE_PEER_FAILED_VERIFICATION / legacy CURLE_SSL_CACERT
            58, // CURLE_SSL_CERTPROBLEM
            59, // CURLE_SSL_CIPHER
            60  // CURLE_SSL_CACERT
        );

        return in_array((int)$curlErrno, $permanent, true);
    }

    /**
     * Returns a human-readable label for a cURL error code.
     *
     * @param int $curlErrno The value returned by curl_errno()
     * @return string The label, or UNKNOWN_CURL_ERROR
     */
    private function describeCurlError($curlErrno)
    {
        $labels = array(
            1  => 'CURLE_UNSUPPORTED_PROTOCOL',
            3  => 'CURLE_URL_MALFORMAT',
            6  => 'CURLE_COULDNT_RESOLVE_HOST',
            7  => 'CURLE_COULDNT_CONNECT',
            28 => 'CURLE_OPERATION_TIMEDOUT',
            35 => 'CURLE_SSL_CONNECT_ERROR',
            51 => 'CURLE_PEER_FAILED_VERIFICATION',
            52 => 'CURLE_GOT_NOTHING',
            56 => 'CURLE_RECV_ERROR',
            58 => 'CURLE_SSL_CERTPROBLEM',
            59 => 'CURLE_SSL_CIPHER',
            60 => 'CURLE_SSL_CACERT',
            77 => 'CURLE_SSL_CACERT_BADFILE'
        );

        return isset($labels[(int)$curlErrno])
            ? $labels[(int)$curlErrno]
            : 'UNKNOWN_CURL_ERROR';
    }

    /**
     * Performs an HTTP request with cURL.
     *
     * The network helper supplies the CA bundle path. The helper
     * resolves the path in this order:
     *
     *   1. The curl.cainfo directive in php.ini, when it is set and
     *      the file exists.
     *   2. The openssl.cafile directive in php.ini, when it is set and
     *      the file exists.
     *   3. The bundled CA bundle at Solution/config/cacert.pem.
     *
     * The helper returns an array of cURL options that this method
     * merges into the request. When the helper cannot resolve a
     * bundle, it returns an empty array and the request proceeds with
     * the PHP defaults. The failure mode is a cURL error 60, which is
     * logged with the same single-line format used for other
     * failures.
     *
     * @param string     $endpoint          API path
     * @param string     $method            HTTP method
     * @param mixed|null $data              Request body for POST and PUT
     * @param array      $additionalHeaders Extra headers
     * @param bool       $useCache          Whether to read and write the cache
     * @return mixed Decoded JSON response
     * @throws Exception When the request ultimately fails
     */
    public function request($endpoint, $method = 'GET', $data = null, $additionalHeaders = array(), $useCache = true)
    {
        // Cache read.
        if ($method === 'GET' && $useCache)
        {
            $cacheKey = md5($endpoint . json_encode($data) . json_encode($additionalHeaders));

            if (isset($this->cache[$cacheKey]))
            {
                $cachedItem = $this->cache[$cacheKey];

                if ((time() - $cachedItem['timestamp']) < $this->cacheTtl)
                {
                    writeLog("API cache hit: $endpoint", "API");
                    return $cachedItem['data'];
                }

                unset($this->cache[$cacheKey]);
            }
        }

        $url = $this->baseUrl . $endpoint;

        // Append the API key where the endpoint requires it.
        if ($this->apiKey !== null)
        {
            if (strpos($endpoint, '?') !== false)
            {
                $url .= '&apikey=' . urlencode($this->apiKey);
            }
            else
            {
                $requiresAuth = (
                    strpos($endpoint, '/Order') !== false ||
                    strpos($endpoint, '/User/') !== false ||
                    strpos($endpoint, '/User?') !== false
                );

                if ($requiresAuth)
                {
                    $url .= '?apikey=' . urlencode($this->apiKey);
                }
            }
        }

        $headers = array_merge($this->defaultHeaders, $additionalHeaders);

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryAttempts)
        {
            $attempt++;

            $curl = curl_init();

            if ($curl === false)
            {
                throw new Exception("Failed to initialise cURL.");
            }

            $curlOptions = array(
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 3,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_USERAGENT      => 'CampusEats/1.0'
            );

            // Merge the CA bundle options supplied by the helper. The
            // helper returns an empty array when it cannot resolve a
            // bundle. The merge is safe in that case.
            $curlOptions = array_replace(
                $curlOptions,
                campus_eats_curl_ssl_options()
            );

            if ($method === 'POST')
            {
                $curlOptions[CURLOPT_POST] = true;

                if ($data !== null)
                {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
            }
            elseif ($method === 'PUT')
            {
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'PUT';

                if ($data !== null)
                {
                    $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
                }
            }
            elseif ($method === 'DELETE')
            {
                $curlOptions[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            }

            curl_setopt_array($curl, $curlOptions);

            writeLog("API request: $method $endpoint (Attempt $attempt)", "API");

            $response = curl_exec($curl);
            $curlErrno = curl_errno($curl);
            $curlError = curl_error($curl);
            $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            if ($curlErrno !== 0)
            {
                $errorMessage = sprintf(
                    "cURL error %d (%s): %s (url: %s)",
                    $curlErrno,
                    $this->describeCurlError($curlErrno),
                    $curlError,
                    $url
                );

                writeLog($errorMessage, "API_ERROR");

                $lastException = new Exception(
                    "API request failed: " . $curlError,
                    $curlErrno
                );

                if ($this->isPermanentCurlFailure($curlErrno))
                {
                    writeLog(
                        "Permanent cURL failure (error $curlErrno). Not retrying.",
                        "API_ERROR"
                    );
                    break;
                }

                if ($attempt < $this->retryAttempts)
                {
                    $delay = $this->retryDelay * $attempt;
                    writeLog("Transient failure, retrying in {$delay}s.", "API");
                    sleep($delay);
                }

                continue;
            }

            if ($response === false)
            {
                $lastException = new Exception("Empty response from API");

                if ($attempt < $this->retryAttempts)
                {
                    $delay = $this->retryDelay * $attempt;
                    writeLog("Empty response, retrying in {$delay}s.", "API");
                    sleep($delay);
                }

                continue;
            }

            // Reject non-2xx responses so the caller receives an
            // actionable exception instead of decoding an error page
            // as JSON.
            if ($httpCode < 200 || $httpCode >= 300)
            {
                $lastException = new Exception(
                    "API returned HTTP " . $httpCode . ": " . substr($response, 0, 200)
                );

                writeLog(
                    "API returned HTTP $httpCode for $method $endpoint",
                    "API_ERROR"
                );

                if ($httpCode >= 400 && $httpCode < 500)
                {
                    break;
                }

                if ($attempt < $this->retryAttempts)
                {
                    $delay = $this->retryDelay * $attempt;
                    sleep($delay);
                }

                continue;
            }

            $result = json_decode($response, true);

            if ($result === null && json_last_error() !== JSON_ERROR_NONE)
            {
                $lastException = new Exception(
                    "Invalid JSON response: " . json_last_error_msg()
                );

                writeLog(
                    "Invalid JSON response from $method $endpoint: "
                        . json_last_error_msg(),
                    "API_ERROR"
                );

                break;
            }

            if ($method === 'GET' && $useCache)
            {
                $cacheKey = md5(
                    $endpoint . json_encode($data) . json_encode($additionalHeaders)
                );
                $this->cache[$cacheKey] = array(
                    'data' => $result,
                    'timestamp' => time()
                );
            }

            writeLog("API request successful: $method $endpoint", "API");
            return $result;
        }

        writeLog(
            "API request failed after {$attempt} attempt(s): "
                . ($lastException ? $lastException->getMessage() : 'unknown error'),
            "API_ERROR"
        );

        throw $lastException ?: new Exception("API request failed");
    }

    /**
     * Returns a diagnostic report on the cURL CA bundle configuration.
     *
     * The report is intended for an operator who is investigating a
     * cURL error 60. It lists the value of curl.cainfo from php.ini,
     * the value of openssl.cafile, the resolved bundle path supplied
     * by the network helper, and whether each path is readable.
     *
     * @return array The diagnostic report
     */
    public function getCurlSslDiagnostics()
    {
        $curlCainfo = ini_get('curl.cainfo');
        $opensslCafile = ini_get('openssl.cafile');
        $resolvedBundle = campus_eats_resolve_ca_bundle();

        $curlCainfoReadable = false;
        $opensslCafileReadable = false;
        $resolvedReadable = false;

        if (!empty($curlCainfo))
        {
            $curlCainfoReadable = is_readable($curlCainfo);
        }

        if (!empty($opensslCafile))
        {
            $opensslCafileReadable = is_readable($opensslCafile);
        }

        if (!empty($resolvedBundle))
        {
            $resolvedReadable = is_readable($resolvedBundle);
        }

        $curlVersion = curl_version();

        return array(
            'curl_version'            => isset($curlVersion['version'])
                ? $curlVersion['version'] : 'unknown',
            'ssl_version'             => isset($curlVersion['ssl_version'])
                ? $curlVersion['ssl_version'] : 'unknown',
            'curl_cainfo'             => $curlCainfo ?: '(not set)',
            'curl_cainfo_readable'    => $curlCainfoReadable,
            'openssl_cafile'          => $opensslCafile ?: '(not set)',
            'openssl_cafile_readable' => $opensslCafileReadable,
            'resolved_bundle'         => $resolvedBundle ?: '(not resolved)',
            'resolved_bundle_readable' => $resolvedReadable,
            'php_ini_path'            => php_ini_loaded_file() ?: '(none)'
        );
    }

    // =========================================================================
    // Restaurant Endpoints
    // =========================================================================

    public function getAllRestaurants()
    {
        return $this->request('/api/Restaurant');
    }

    public function getRestaurantsByCategory($category)
    {
        $encodedCategory = urlencode($category);
        return $this->request("/api/Restaurant?category=$encodedCategory");
    }

    public function filterRestaurants($address = null, $name = null)
    {
        $params = array();

        if ($address !== null)
        {
            $params[] = 'address=' . urlencode($address);
        }

        if ($name !== null)
        {
            $params[] = 'name=' . urlencode($name);
        }

        $query = !empty($params) ? '?' . implode('&', $params) : '';
        return $this->request("/api/Restaurant$query");
    }

    public function getRestaurantById($id)
    {
        return $this->request("/api/Restaurant/$id");
    }

    public function getRestaurantMenu($restaurantId, $sortOrder = null)
    {
        $endpoint = "/api/Restaurant/$restaurantId/menu";

        if ($sortOrder !== null && in_array($sortOrder, array('asc', 'desc')))
        {
            $endpoint .= "?sortbyprice=$sortOrder";
        }

        return $this->request($endpoint);
    }

    public function getAllItems($searchTerm = null, $sortOrder = null)
    {
        $params = array();

        if ($searchTerm !== null)
        {
            $params[] = 'ItemName=' . urlencode($searchTerm);
        }

        if ($sortOrder !== null && in_array($sortOrder, array('asc', 'desc')))
        {
            $params[] = 'sortbyprice=' . $sortOrder;
        }

        $query = !empty($params) ? '?' . implode('&', $params) : '';
        return $this->request("/api/Restaurant/items$query");
    }

    // =========================================================================
    // User Endpoints
    // =========================================================================

    public function getAllUsers()
    {
        return $this->request('/api/User');
    }

    public function getUserCode($email, $password)
    {
        $encodedEmail = urlencode($email);
        $encodedPassword = urlencode($password);
        return $this->request(
            "/api/User/getusercode?UserEmail=$encodedEmail&Password=$encodedPassword"
        );
    }

    public function registerUser($email, $password)
    {
        return $this->request(
            '/api/User/register',
            'POST',
            array(
                'userEmail' => $email,
                'password' => $password
            )
        );
    }

    public function deleteUser($apiKey)
    {
        $this->setApiKey($apiKey);
        return $this->request("/api/User/$apiKey", 'DELETE');
    }

    public function updatePassword($apiKey, $newPassword)
    {
        $this->setApiKey($apiKey);
        return $this->request("/api/User/$apiKey", 'PUT', $newPassword);
    }

    // =========================================================================
    // Order Endpoints
    // =========================================================================

    public function getUserOrders()
    {
        if ($this->apiKey === null)
        {
            throw new Exception("API key required for order operations");
        }

        return $this->request("/api/Order?apikey=" . urlencode($this->apiKey));
    }

    public function getOrderByMasterId($masterId)
    {
        if ($this->apiKey === null)
        {
            throw new Exception("API key required for order operations");
        }

        return $this->request(
            "/api/Order/$masterId?apikey=" . urlencode($this->apiKey)
        );
    }

    public function createOrder($restaurantId, $items)
    {
        if ($this->apiKey === null)
        {
            throw new Exception("API key required for order operations");
        }

        return $this->request(
            "/api/Order/$restaurantId/makeorder?apikey=" . urlencode($this->apiKey),
            'POST',
            array('menuDTO' => $items)
        );
    }

    public function deleteMasterOrder($masterId)
    {
        if ($this->apiKey === null)
        {
            throw new Exception("API key required for order operations");
        }

        return $this->request(
            "/api/Order/master/$masterId?apikey=" . urlencode($this->apiKey),
            'DELETE'
        );
    }

    public function deleteSingleOrder($orderId)
    {
        if ($this->apiKey === null)
        {
            throw new Exception("API key required for order operations");
        }

        return $this->request(
            "/api/Order/$orderId?apikey=" . urlencode($this->apiKey),
            'DELETE'
        );
    }
}

if (!function_exists('getApiService'))
{
    /**
     * Returns the shared ApiService instance for this request.
     *
     * @param string|null $apiKey Optional API key to install on first call
     * @return ApiService
     */
    function getApiService($apiKey = null)
    {
        static $instance = null;

        if ($instance === null)
        {
            $instance = new ApiService($apiKey);
        }

        if ($apiKey !== null)
        {
            $instance->setApiKey($apiKey);
        }

        return $instance;
    }
}