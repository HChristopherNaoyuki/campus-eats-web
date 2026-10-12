<?php
/**
 * Fake Restaurant API Service Layer
 *
 * Provides a unified HTTP client for the Fake Restaurant API at
 * https://fakerestaurantapi.runasp.net. Supports stale-if-error,
 * bundled fallback, failure pause, and CA-bundle verification.
 *
 * CORRECTIONS (Version 8.0 - Source-aware logging):
 *
 * - getAllRestaurants() and fallback / stale return paths log the
 *   data source explicitly as "live", "stale", or "fallback" so the
 *   error log never implies a live API success when fallback data
 *   was served.
 * - SSL options come only from campus_eats_curl_ssl_options()
 *   (CAINFO only, never CAPATH, never SSL_VERIFYPEER = false).
 * - All Version 7.0 behaviour is retained: stale-if-error, bundled
 *   fallback for GET /api/Restaurant, 30-second failure pause,
 *   permanent TLS/DNS failure classification, two retries, 10 s
 *   timeout.
 *
 * SOURCE: Software Engineering Report, sections 2.1 and 2.4.
 * SOURCE: TypeScript restaurantApi.ts (parity for endpoint shape).
 * SOURCE: Clean Code, Robert C. Martin.
 *
 * @version 8.0
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
     * @var string Base URL of the Fake Restaurant API
     */
    private $baseUrl;

    /**
     * @var string|null Optional API key for authenticated endpoints
     */
    private $apiKey;

    /**
     * @var array Default HTTP headers
     */
    private $defaultHeaders;

    /**
     * @var int Request timeout in seconds
     */
    private $timeout = 10;

    /**
     * @var int Maximum number of attempts (initial + retries)
     */
    private $retryAttempts = 2;

    /**
     * @var int Base delay in seconds between retries
     */
    private $retryDelay = 1;

    /**
     * @var int Seconds to wait after a failure before contacting the host
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
     * @var string Directory for stale response files
     */
    private $staleDir;

    /**
     * @var string File that records the last failure time
     */
    private $failureFile;

    /**
     * @param string|null $apiKey Optional API key for authenticated endpoints
     */
    public function __construct($apiKey = null)
    {
        $this->baseUrl = defined('API_BASE_URL')
            ? API_BASE_URL
            : 'https://fakerestaurantapi.runasp.net';
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
     * @param string $apiKey
     * @return void
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        writeLog('API key set for service', 'API');
    }

    /**
     * @return string|null
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * @param string|null $key
     * @return void
     */
    public function clearCache($key = null)
    {
        if ($key === null)
        {
            $this->cache = array();
            writeLog('All API cache cleared', 'API');
        }
        else
        {
            unset($this->cache[$key]);
            writeLog('API cache cleared for key: ' . $key, 'API');
        }
    }

    /**
     * True when the cURL error must not be retried (TLS, DNS, cert).
     *
     * @param int $curlErrno
     * @return bool
     */
    private function isPermanentCurlFailure($curlErrno)
    {
        $permanent = array(
            6,  // CURLE_COULDNT_RESOLVE_HOST
            7,  // CURLE_COULDNT_CONNECT (treated as permanent after attempts)
            35, // CURLE_SSL_CONNECT_ERROR
            51, // CURLE_PEER_FAILED_VERIFICATION
            58, // CURLE_SSL_CERTPROBLEM
            59, // CURLE_SSL_CIPHER
            60, // CURLE_SSL_CACERT
            77, // CURLE_SSL_CACERT_BADFILE
            83  // CURLE_SSL_ISSUER_ERROR
        );

        return in_array((int)$curlErrno, $permanent, true);
    }

    /**
     * @param int $curlErrno
     * @return string
     */
    private function describeCurlError($curlErrno)
    {
        $labels = array(
            6  => 'CURLE_COULDNT_RESOLVE_HOST',
            7  => 'CURLE_COULDNT_CONNECT',
            28 => 'CURLE_OPERATION_TIMEDOUT',
            35 => 'CURLE_SSL_CONNECT_ERROR',
            51 => 'CURLE_PEER_FAILED_VERIFICATION',
            58 => 'CURLE_SSL_CERTPROBLEM',
            59 => 'CURLE_SSL_CIPHER',
            60 => 'CURLE_SSL_CACERT',
            77 => 'CURLE_SSL_CACERT_BADFILE',
            83 => 'CURLE_SSL_ISSUER_ERROR'
        );

        return isset($labels[$curlErrno])
            ? $labels[$curlErrno]
            : 'CURLE_UNKNOWN';
    }

    /**
     * @param string $endpoint
     * @return string
     */
    private function staleFilePath($endpoint)
    {
        return $this->staleDir . '/' . md5($endpoint) . '.json';
    }

    /**
     * @param string $endpoint
     * @return mixed|null
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
     * @param string $endpoint
     * @param mixed  $response
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
     * @return void
     */
    private function recordFailure()
    {
        @file_put_contents($this->failureFile, (string)time(), LOCK_EX);
    }

    /**
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
     * Bundled fallback for GET restaurant catalogue endpoints only.
     *
     * @param string $endpoint
     * @param string $method
     * @return mixed|null
     */
    private function fallbackFor($endpoint, $method)
    {
        if ($method !== 'GET')
        {
            return null;
        }

        if (!function_exists('campus_eats_load_fallback_restaurants'))
        {
            require_once BASE_PATH . '/config/fallback_data.php';
        }

        $path = parse_url($endpoint, PHP_URL_PATH);

        if ($path === '/api/Restaurant' || $path === null)
        {
            if (strpos($endpoint, '/api/Restaurant') === 0
                && strpos($endpoint, '/menu') === false
                && strpos($endpoint, '/items') === false
                && !preg_match('#/api/Restaurant/\d+#', $endpoint))
            {
                return campus_eats_load_fallback_restaurants();
            }
        }

        if ($path === '/api/Restaurant' || $endpoint === '/api/Restaurant')
        {
            return campus_eats_load_fallback_restaurants();
        }

        if (strpos($endpoint, '/api/Restaurant/items') === 0)
        {
            return array();
        }

        return null;
    }

    /**
     * Performs an HTTP request with cURL.
     *
     * @param string     $endpoint
     * @param string     $method
     * @param mixed|null $data
     * @param array      $additionalHeaders
     * @param bool       $useCache
     * @return mixed
     * @throws Exception
     */
    public function request(
        $endpoint,
        $method = 'GET',
        $data = null,
        $additionalHeaders = array(),
        $useCache = true
    )
    {
        $cacheKey = md5(
            $endpoint . json_encode($data) . json_encode($additionalHeaders)
        );

        if ($method === 'GET' && $useCache && isset($this->cache[$cacheKey]))
        {
            $cachedItem = $this->cache[$cacheKey];

            if ((time() - $cachedItem['timestamp']) < $this->cacheTtl)
            {
                writeLog('API cache hit: ' . $endpoint, 'API');
                return $cachedItem['data'];
            }

            unset($this->cache[$cacheKey]);
        }

        if ($this->isWithinFailurePause())
        {
            $stale = $this->readStale($endpoint);

            if ($stale !== null)
            {
                $count = is_array($stale) ? count($stale) : 0;
                writeLog(
                    'API in failure pause. Serving stale response for '
                        . $endpoint . ' (records: ' . $count . ', source: stale)',
                    'API'
                );
                return $stale;
            }

            $fallback = $this->fallbackFor($endpoint, $method);

            if ($fallback !== null)
            {
                $count = is_array($fallback) ? count($fallback) : 0;
                writeLog(
                    'API in failure pause. Serving bundled fallback for '
                        . $endpoint
                        . ' (records: ' . $count . ', source: fallback)',
                    'API'
                );
                return $fallback;
            }

            throw new Exception(
                'API is in failure pause and no fallback is available.'
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
                    strpos($endpoint, '/Order') !== false
                    || strpos($endpoint, '/User/') !== false
                    || strpos($endpoint, '/User?') !== false
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
                throw new Exception('Failed to initialise cURL.');
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
                'API request: ' . $method . ' ' . $endpoint
                    . ' (Attempt ' . $attempt . ')',
                'API'
            );

            $response = curl_exec($curl);
            $curlErrno = curl_errno($curl);
            $curlError = curl_error($curl);
            $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            if ($curlErrno !== 0)
            {
                $errorMessage = sprintf(
                    'cURL error %d (%s): %s (url: %s)',
                    $curlErrno,
                    $this->describeCurlError($curlErrno),
                    $curlError,
                    $url
                );

                writeLog($errorMessage, 'API_ERROR');
                $this->recordFailure();

                $lastException = new Exception(
                    'API request failed: ' . $curlError,
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

            if ($httpCode < 200 || $httpCode >= 300)
            {
                $errorMessage = sprintf(
                    'HTTP %d from %s %s',
                    $httpCode,
                    $method,
                    $endpoint
                );

                writeLog($errorMessage, 'API_ERROR');
                $this->recordFailure();

                $lastException = new Exception($errorMessage, $httpCode);

                if ($attempt < $this->retryAttempts)
                {
                    sleep($this->retryDelay * $attempt);
                }

                continue;
            }

            $result = json_decode($response, true);

            if ($result === null && json_last_error() !== JSON_ERROR_NONE)
            {
                writeLog(
                    'Invalid JSON response from ' . $method . ' '
                        . $endpoint . ': ' . json_last_error_msg(),
                    'API_ERROR'
                );

                $lastException = new Exception(
                    'Invalid JSON: ' . json_last_error_msg()
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

            writeLog(
                'API request successful: ' . $method . ' ' . $endpoint
                    . ' (source: live)',
                'API'
            );

            return $result;
        }

        $stale = $this->readStale($endpoint);

        if ($stale !== null)
        {
            $count = is_array($stale) ? count($stale) : 0;
            writeLog(
                'API request failed. Serving stale response for '
                    . $endpoint
                    . ' (records: ' . $count . ', source: stale)',
                'API'
            );
            return $stale;
        }

        $fallback = $this->fallbackFor($endpoint, $method);

        if ($fallback !== null)
        {
            $count = is_array($fallback) ? count($fallback) : 0;
            writeLog(
                'API request failed. Serving bundled fallback for '
                    . $endpoint
                    . ' (records: ' . $count . ', source: fallback)',
                'API'
            );
            return $fallback;
        }

        writeLog(
            'API request failed after ' . $attempt . ' attempt(s): '
                . ($lastException
                    ? $lastException->getMessage()
                    : 'unknown error'),
            'API_ERROR'
        );

        throw $lastException ?: new Exception('API request failed');
    }

    /**
     * @return array
     */
    public function getCurlSslDiagnostics()
    {
        $curlCainfo = ini_get('curl.cainfo');
        $opensslCafile = ini_get('openssl.cafile');
        $resolvedBundle = function_exists('campus_eats_resolve_ca_bundle')
            ? campus_eats_resolve_ca_bundle()
            : null;

        return array(
            'curl.cainfo' => $curlCainfo,
            'curl.cainfo_readable' => !empty($curlCainfo) && is_readable($curlCainfo),
            'openssl.cafile' => $opensslCafile,
            'openssl.cafile_readable' => !empty($opensslCafile) && is_readable($opensslCafile),
            'resolved_bundle' => $resolvedBundle,
            'resolved_readable' => !empty($resolvedBundle) && is_readable($resolvedBundle),
            'resolved_size' => (!empty($resolvedBundle) && is_readable($resolvedBundle))
                ? filesize($resolvedBundle)
                : 0
        );
    }

    // =========================================================================
    // Restaurant endpoints
    // =========================================================================

    /**
     * Fetches all restaurants. Logs the data source so live and
     * fallback results are never confused in the error log.
     *
     * @return array
     */
    public function getAllRestaurants()
    {
        $wasInPause = $this->isWithinFailurePause();
        $result = $this->request('/api/Restaurant', 'GET');

        if (!is_array($result))
        {
            $result = array();
        }

        $source = $wasInPause || $this->isWithinFailurePause()
            ? 'fallback_or_stale'
            : 'live';

        writeLog(
            'Fetched ' . count($result)
                . ' restaurants (source: ' . $source . ')',
            'API'
        );

        return $result;
    }

    /**
     * @param int|string $id
     * @return mixed
     */
    public function getRestaurantById($id)
    {
        return $this->request('/api/Restaurant/' . $id, 'GET');
    }

    /**
     * @param int|string $id
     * @param string|null $sortByPrice asc|desc
     * @return mixed
     */
    public function getRestaurantMenu($id, $sortByPrice = null)
    {
        $endpoint = '/api/Restaurant/' . $id . '/menu';

        if ($sortByPrice === 'asc' || $sortByPrice === 'desc')
        {
            $endpoint .= '?sortbyprice=' . $sortByPrice;
        }

        return $this->request($endpoint, 'GET');
    }

    /**
     * @return mixed
     */
    public function getAllItems()
    {
        return $this->request('/api/Restaurant/items', 'GET');
    }

    // =========================================================================
    // User endpoints
    // =========================================================================

    /**
     * @return mixed
     */
    public function getUsers()
    {
        return $this->request('/api/User', 'GET');
    }

    /**
     * @param string $userEmail
     * @param string $password
     * @return mixed
     */
    public function registerUser($userEmail, $password)
    {
        return $this->request(
            '/api/User/register',
            'POST',
            array(
                'userEmail' => $userEmail,
                'password' => $password
            )
        );
    }

    /**
     * @param string $userEmail
     * @param string $password
     * @return string|null
     */
    public function getUserCode($userEmail, $password)
    {
        $qs = http_build_query(
            array(
                'UserEmail' => $userEmail,
                'Password' => $password
            )
        );

        try
        {
            $data = $this->request('/api/User/getusercode?' . $qs, 'GET');

            return isset($data['usercode']) ? $data['usercode'] : null;
        }
        catch (Exception $e)
        {
            return null;
        }
    }

    /**
     * @param string $apiKey
     * @param string $newPassword
     * @return mixed
     */
    public function updateUserPassword($apiKey, $newPassword)
    {
        return $this->request('/api/User/' . $apiKey, 'PUT', $newPassword);
    }

    /**
     * @param string $apiKey
     * @return mixed
     */
    public function deleteUser($apiKey)
    {
        return $this->request('/api/User/' . $apiKey, 'DELETE');
    }

    // =========================================================================
    // Order endpoints
    // =========================================================================

    /**
     * @return mixed
     * @throws Exception
     */
    public function getUserOrders()
    {
        if ($this->apiKey === null)
        {
            throw new Exception('API key required for order operations');
        }

        return $this->request(
            '/api/Order?apikey=' . urlencode($this->apiKey)
        );
    }

    /**
     * @param int|string $masterId
     * @return mixed
     * @throws Exception
     */
    public function getOrderByMasterId($masterId)
    {
        if ($this->apiKey === null)
        {
            throw new Exception('API key required for order operations');
        }

        return $this->request(
            '/api/Order/' . $masterId
                . '?apikey=' . urlencode($this->apiKey)
        );
    }

    /**
     * @param int|string $restaurantId
     * @param array      $items
     * @return mixed
     * @throws Exception
     */
    public function createOrder($restaurantId, $items)
    {
        if ($this->apiKey === null)
        {
            throw new Exception('API key required for order operations');
        }

        return $this->request(
            '/api/Order/' . $restaurantId
                . '/makeorder?apikey=' . urlencode($this->apiKey),
            'POST',
            array('menuDTO' => $items)
        );
    }

    /**
     * @param int|string $masterId
     * @return mixed
     * @throws Exception
     */
    public function deleteMasterOrder($masterId)
    {
        if ($this->apiKey === null)
        {
            throw new Exception('API key required for order operations');
        }

        return $this->request(
            '/api/Order/master/' . $masterId
                . '?apikey=' . urlencode($this->apiKey),
            'DELETE'
        );
    }

    /**
     * @param int|string $orderId
     * @return mixed
     * @throws Exception
     */
    public function deleteSingleOrder($orderId)
    {
        if ($this->apiKey === null)
        {
            throw new Exception('API key required for order operations');
        }

        return $this->request(
            '/api/Order/' . $orderId
                . '?apikey=' . urlencode($this->apiKey),
            'DELETE'
        );
    }
}

if (!function_exists('getApiService'))
{
    /**
     * Returns the shared ApiService instance for this request.
     *
     * @param string|null $apiKey
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