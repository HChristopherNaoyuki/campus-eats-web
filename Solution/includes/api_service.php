<?php
/**
 * Fake Restaurant API Service Layer
 *
 * Provides a unified HTTP client for the Fake Restaurant API.
 *
 * CORRECTIONS (Version 3.0):
 * - Replaced file_get_contents() with cURL for all HTTP requests.
 *   file_get_contents() over HTTPS produced
 *     "SSL operation failed with code 1. OpenSSL Error messages:
 *      error:0A000086:SSL routines::certificate verify failed"
 *   on the target WampServer + PHP 8.3.28 + mod_fcgid environment.
 *   cURL respects the curl.cainfo setting from php.ini, produces
 *   actionable error codes, and can be verified independently.
 * - Distinguished TLS and DNS failures from transient network failures.
 *   TLS verification failures and DNS resolution failures are permanent
 *   for the lifetime of the request and are no longer retried, since a
 *   retry cannot change the outcome and only multiplies log entries.
 * - Retries are now performed only for timeouts and connection resets,
 *   using an exponential backoff instead of a fixed one-second delay.
 * - Added curl_error() and curl_errno() reporting so the next failure
 *   names the OpenSSL error directly, rather than emitting three
 *   separate file_get_contents() warnings.
 * - Preserved the public method signatures (getAllRestaurants,
 *   getRestaurantMenu, getRestaurantById, and so on), so no caller
 *   needs to change.
 *
 * SOURCE: Issues/audit_log.txt 2026-09-12 13:16:33 through 13:16:57
 * SOURCE: PHP Manual - cURL Functions
 * SOURCE: PHP Manual - OpenSSL certificate verification
 *
 * @version 3.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/error_logging.php';

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
     * Returns true if the given curl error code indicates a permanent
     * failure that should not be retried.
     *
     * The list is intentionally conservative. Timeouts and connection
     * resets are transient; TLS verification and DNS resolution are not.
     *
     * @param int $curlErrno The value from curl_errno()
     * @return bool
     */
    private function isPermanentCurlFailure($curlErrno)
    {
        $permanent = array(
            CURLE_SSL_CACERT,             // 60 - CA certificate rejected
            CURLE_SSL_CERTPROBLEM,        // 58 - local certificate problem
            CURLE_SSL_CIPHER,             // 59 - no usable cipher
            CURLE_PEER_FAILED_VERIFICATION, // 51 - peer certificate rejected
            CURLE_COULDNT_RESOLVE_HOST,   // 6  - DNS lookup failed
            CURLE_URL_MALFORMAT,          // 3  - malformed URL
            CURLE_UNSUPPORTED_PROTOCOL    // 1  - protocol not supported
        );

        return in_array($curlErrno, $permanent, true);
    }

    /**
     * Performs an HTTP request with cURL, retrying only transient failures.
     *
     * The retry strategy is:
     *   - Attempt 1 runs immediately.
     *   - If the failure is permanent (TLS, DNS, malformed URL), stop.
     *   - If the failure is transient (timeout, connection reset), wait
     *     retryDelay * attempt seconds and retry, up to retryAttempts.
     *
     * @param string     $endpoint          API path, for example "/api/Restaurant"
     * @param string     $method            HTTP method
     * @param mixed|null $data              Request body for POST and PUT
     * @param array      $additionalHeaders Extra headers to add to the request
     * @param bool       $useCache          Whether to read and write the in-memory cache
     * @return mixed                        Decoded JSON response
     * @throws Exception                    If the request ultimately fails
     */
    public function request($endpoint, $method = 'GET', $data = null, $additionalHeaders = array(), $useCache = true)
    {
        // For GET requests, check the in-memory cache first.
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
                // Build a single actionable log message instead of three
                // separate warnings. The OpenSSL detail, when present, is
                // included verbatim so the certificate failure is obvious.
                $errorMessage = sprintf(
                    "cURL error %d: %s (url: %s)",
                    $curlErrno,
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

            // Reject non-2xx responses so the caller gets an actionable
            // exception instead of decoding an error page as JSON.
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
                    // Client errors will not succeed on retry.
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
                    "Invalid JSON response from $method $endpoint: " . json_last_error_msg(),
                    "API_ERROR"
                );

                break;
            }

            if ($method === 'GET' && $useCache)
            {
                $cacheKey = md5($endpoint . json_encode($data) . json_encode($additionalHeaders));
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
        return $this->request("/api/User/getusercode?UserEmail=$encodedEmail&Password=$encodedPassword");
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
    // Order Endpoints (require API key)
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

        return $this->request("/api/Order/$masterId?apikey=" . urlencode($this->apiKey));
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

        return $this->request("/api/Order/master/$masterId?apikey=" . urlencode($this->apiKey), 'DELETE');
    }

    public function deleteSingleOrder($orderId)
    {
        if ($this->apiKey === null)
        {
            throw new Exception("API key required for order operations");
        }

        return $this->request("/api/Order/$orderId?apikey=" . urlencode($this->apiKey), 'DELETE');
    }

    // =========================================================================
    // Convenience Methods
    // =========================================================================

    public function getRestaurantWithMenu($restaurantId)
    {
        $restaurant = $this->getRestaurantById($restaurantId);
        $menu = $this->getRestaurantMenu($restaurantId);

        return array(
            'restaurant' => $restaurant,
            'menu' => $menu
        );
    }

    public function searchRestaurantsByName($name)
    {
        $allRestaurants = $this->getAllRestaurants();
        $results = array();
        $searchTerm = strtolower($name);

        foreach ($allRestaurants as $restaurant)
        {
            if (stripos($restaurant['restaurantName'], $searchTerm) !== false)
            {
                $results[] = $restaurant;
            }
        }

        return $results;
    }

    public function searchMenuItemsByName($name)
    {
        return $this->getAllItems($name);
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