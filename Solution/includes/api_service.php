<?php
/**
 * Fake Restaurant API Service Layer
 *
 * Provides a unified HTTP client for the Fake Restaurant API.
 *
 * CORRECTIONS (Version 6.0 - Audit Continuation):
 *
 * - Fix 1 (stale-if-error). When a request fails and a previously
 *   successful response is available on disk, the stored response is
 *   returned. This lets the site render the last known restaurant
 *   list during a transient outage instead of an error page.
 *
 * - Fix 2 (failure pause). When a request fails, the failure
 *   timestamp is recorded. Subsequent requests within 30 seconds
 *   return the stale response immediately, without contacting the
 *   remote host. This reduces the load on the remote host during an
 *   outage and returns control to the user quickly.
 *
 * - Fix 3 (shorter timeout and fewer retries). The per-request
 *   timeout is 10 seconds. The number of retry attempts is 2. The
 *   total worst-case wait is 20 seconds plus the inter-attempt
 *   delay. This is shorter than the 93 seconds of the previous
 *   version.
 *
 * - Fix 4 (CA bundle). The network helper supplies the CA bundle
 *   path. Certificate verification remains enabled.
 *
 * - Retained the permanent-failure classification, the exponential
 *   backoff, and the single log line per failure.
 *
 * SOURCE: Audit continuation, Part 1.
 *
 * @version 6.0
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
    private $timeout = 10;

    /**
     * @var int Number of retry attempts for transient failures
     */
    private $retryAttempts = 2;

    /**
     * @var int Base delay in seconds for exponential backoff
     */
    private $retryDelay = 1;

    /**
     * @var int Seconds to wait after a failure before retrying the host
     */
    private $failurePause = 30;

    /**
     * @var array In-memory cache for GET responses
     */
    private $cache = array();

    /**
     * @var int Cache time-to-live in seconds
     */
    private $cacheTtl = 300;

    /**
     * @var string The directory for the stale response files
     */
    private $staleDir;

    /**
     * @var string The file that records the last failure time
     */
    private $failureFile;

    /**
     * Constructor.
     *
     * @param string|null $apiKey Optional API key for authenticated endpoints
     */
    public function __construct($apiKey = null)
    {
        $this->baseUrl = API_BASE_URL;
        $this->apiKey = $apiKey;

        $this->defaultHeaders = array(
            'Content-Type: application/json',
            'Accept: application/json'
        );

        $this->staleDir = BASE_PATH . '/data/api_stale';

        if (!is_dir($this->staleDir))
        {
            @mkdir($this->staleDir, 0700, true);
        }

        $this->failureFile = $this->staleDir . '/last_failure.txt';
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
     * @param int $curlErrno The value returned by curl_errno()
     * @return bool True when the failure is permanent
     */
    private function isPermanentCurlFailure($curlErrno)
    {
        $permanent = array(1, 3, 6, 51, 58, 59, 60);

        return in_array((int)$curlErrno, $permanent, true);
    }

    /**
     * Returns a human-readable label for a cURL error code.
     *
     * @param int $curlErrno The value returned by curl_errno()
     * @return string The label
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
     * Returns the path to the stale file for a request.
     *
     * @param string $endpoint The endpoint
     * @return string The path
     */
    private function staleFilePath($endpoint)
    {
        return $this->staleDir . '/' . md5($endpoint) . '.json';
    }

    /**
     * Reads the stale response for a request, or null.
     *
     * @param string $endpoint The endpoint
     * @return mixed The decoded response, or null
     */
    private function readStale($endpoint)
    {
        $path = $this->staleFilePath($endpoint);

        if (!is_readable($path))
        {
            return null;
        }

        $content = @file_get_contents($path);

        if ($content === false)
        {
            return null;
        }

        $decoded = json_decode($content, true);

        return $decoded === null && json_last_error() !== JSON_ERROR_NONE
            ? null
            : $decoded;
    }

    /**
     * Writes a response to the stale file.
     *
     * @param string $endpoint The endpoint
     * @param mixed  $response The response
     * @return void
     */
    private function writeStale($endpoint, $response)
    {
        $path = $this->staleFilePath($endpoint);

        $encoded = json_encode(
            $response,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($encoded !== false)
        {
            @file_put_contents($path, $encoded, LOCK_EX);
            @chmod($path, 0600);
        }
    }

    /**
     * Records the current time as the last failure time.
     *
     * @return void
     */
    private function recordFailure()
    {
        @file_put_contents($this->failureFile, (string)time(), LOCK_EX);
    }

    /**
     * Returns true when a failure was recorded within the pause
     * window.
     *
     * @return bool
     */
    private function isWithinFailurePause()
    {
        if (!is_readable($this->failureFile))
        {
            return false;
        }

        $lastFailure = (int)@file_get_contents($this->failureFile);

        return (time() - $lastFailure) < $this->failurePause;
    }

    /**
     * Performs an HTTP request with cURL.
     *
     * @param string     $endpoint          API path
     * @param string     $method            HTTP method
     * @param mixed|null $data              Request body
     * @param array      $additionalHeaders Extra headers
     * @param bool       $useCache          Whether to read and write the cache
     * @return mixed Decoded JSON response
     * @throws Exception When the request ultimately fails
     */
    public function request($endpoint, $method = 'GET', $data = null, $additionalHeaders = array(), $useCache = true)
    {
        $cacheKey = md5($endpoint . json_encode($data) . json_encode($additionalHeaders));

        // In-memory cache read.
        if ($method === 'GET' && $useCache && isset($this->cache[$cacheKey]))
        {
            $cachedItem = $this->cache[$cacheKey];

            if ((time() - $cachedItem['timestamp']) < $this->cacheTtl)
            {
                writeLog("API cache hit: $endpoint", "API");
                return $cachedItem['data'];
            }

            unset($this->cache[$cacheKey]);
        }

        // Failure pause. When a failure was recorded within the pause
        // window, the stale response is returned immediately. When no
        // stale response is available, an exception is thrown.
        if ($this->isWithinFailurePause())
        {
            $stale = $this->readStale($endpoint);

            if ($stale !== null)
            {
                writeLog(
                    "API in failure pause. Serving stale response for $endpoint",
                    "API"
                );
                return $stale;
            }

            throw new Exception(
                "API is in failure pause and no stale response is available."
            );
        }

        $url = $this->baseUrl . $endpoint;

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

            $options = array(
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

            if (function_exists('campus_eats_curl_ssl_options'))
            {
                $options = array_replace(
                    $options,
                    campus_eats_curl_ssl_options()
                );
            }

            if ($method === 'POST')
            {
                $options[CURLOPT_POST] = true;

                if ($data !== null)
                {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
            }
            elseif ($method === 'PUT')
            {
                $options[CURLOPT_CUSTOMREQUEST] = 'PUT';

                if ($data !== null)
                {
                    $options[CURLOPT_POSTFIELDS] = json_encode($data);
                }
            }
            elseif ($method === 'DELETE')
            {
                $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
            }

            curl_setopt_array($curl, $options);

            writeLog(
                "API request: $method $endpoint (Attempt $attempt)",
                "API"
            );

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
                $this->recordFailure();

                $lastException = new Exception(
                    "API request failed: " . $curlError,
                    $curlErrno
                );

                if ($this->isPermanentCurlFailure($curlErrno))
                {
                    break;
                }

                if ($attempt < $this->retryAttempts)
                {
                    sleep($this->retryDelay * $attempt);
                }

                continue;
            }

            if ($response === false)
            {
                $lastException = new Exception("Empty response from API");

                if ($attempt < $this->retryAttempts)
                {
                    sleep($this->retryDelay * $attempt);
                }

                continue;
            }

            if ($httpCode < 200 || $httpCode >= 300)
            {
                $lastException = new Exception(
                    "API returned HTTP " . $httpCode . ": "
                        . substr($response, 0, 200)
                );

                writeLog(
                    "API returned HTTP $httpCode for $method $endpoint",
                    "API_ERROR"
                );

                $this->recordFailure();

                if ($httpCode >= 400 && $httpCode < 500)
                {
                    break;
                }

                if ($attempt < $this->retryAttempts)
                {
                    sleep($this->retryDelay * $attempt);
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
                $this->cache[$cacheKey] = array(
                    'data' => $result,
                    'timestamp' => time()
                );

                $this->writeStale($endpoint, $result);
            }

            writeLog("API request successful: $method $endpoint", "API");
            return $result;
        }

        // All attempts failed. When a stale response exists, it is
        // returned. Otherwise the exception propagates.
        $stale = $this->readStale($endpoint);

        if ($stale !== null)
        {
            writeLog(
                "API request failed. Serving stale response for $endpoint",
                "API"
            );
            return $stale;
        }

        writeLog(
            "API request failed after {$attempt} attempt(s): "
                . ($lastException ? $lastException->getMessage() : 'unknown error'),
            "API_ERROR"
        );

        throw $lastException ?: new Exception("API request failed");
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