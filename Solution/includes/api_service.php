<?php
/**
 * Fake Restaurant API Service Layer
 *
 * Unified HTTP client for https://fakerestaurantapi.runasp.net with
 * stale-if-error, bundled fallback (restaurants and menus), failure
 * pause, and CA-bundle verification.
 *
 * CORRECTIONS (Version 9.0 - Menu fallback):
 *
 * - fallbackFor() serves campus_eats_load_fallback_menu() for
 *   GET /api/Restaurant/{id}/menu so home-page menu previews work
 *   during SSL failure pause.
 * - getRestaurantMenu() returns an array (possibly empty) and never
 *   leaves the caller without a defined result when fallback exists.
 * - Logging still labels source as live, stale, or fallback.
 * - SSL verification stays enabled; options come from network.php.
 *
 * SOURCE: Error log – Menu preview unavailable, no fallback available.
 * SOURCE: Software Engineering Task, Issue Three.
 *
 * @version 9.0
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
    private $baseUrl;
    private $apiKey;
    private $defaultHeaders;
    private $timeout = 10;
    private $retryAttempts = 2;
    private $retryDelay = 1;
    private $failurePause = 30;
    private $cache = array();
    private $cacheTtl = 300;
    private $staleDir;
    private $failureFile;

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

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        writeLog('API key set for service', 'API');
    }

    public function getApiKey()
    {
        return $this->apiKey;
    }

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

    private function isPermanentCurlFailure($curlErrno)
    {
        $permanent = array(6, 7, 35, 51, 58, 59, 60, 77, 83);
        return in_array((int)$curlErrno, $permanent, true);
    }

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

    private function staleFilePath($endpoint)
    {
        return $this->staleDir . '/' . md5($endpoint) . '.json';
    }

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

    private function recordFailure()
    {
        @file_put_contents($this->failureFile, (string)time(), LOCK_EX);
    }

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
     * Bundled fallback for GET restaurant catalogue and menu endpoints.
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

        // Exact catalogue endpoint.
        if ($endpoint === '/api/Restaurant'
            || strpos($endpoint, '/api/Restaurant?') === 0)
        {
            return campus_eats_load_fallback_restaurants();
        }

        // Menu: /api/Restaurant/{id}/menu
        if (preg_match('#^/api/Restaurant/(\d+)/menu#', $endpoint, $matches))
        {
            $menu = campus_eats_load_fallback_menu($matches[1]);

            if (!empty($menu))
            {
                return $menu;
            }

            return array();
        }

        // Items catalogue.
        if (strpos($endpoint, '/api/Restaurant/items') === 0)
        {
            return array();
        }

        return null;
    }

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
                        . $endpoint . ' (records: ' . $count
                        . ', source: stale)',
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
                        . $endpoint . ' (records: ' . $count
                        . ', source: fallback)',
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
                writeLog(
                    sprintf(
                        'cURL error %d (%s): %s (url: %s)',
                        $curlErrno,
                        $this->describeCurlError($curlErrno),
                        $curlError,
                        $url
                    ),
                    'API_ERROR'
                );
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
                writeLog(
                    'HTTP ' . $httpCode . ' from ' . $method . ' ' . $endpoint,
                    'API_ERROR'
                );
                $this->recordFailure();
                $lastException = new Exception(
                    'HTTP ' . $httpCode,
                    $httpCode
                );

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
                    'Invalid JSON from ' . $method . ' ' . $endpoint . ': '
                        . json_last_error_msg(),
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
                'API request failed. Serving stale for ' . $endpoint
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
                    . $endpoint . ' (records: ' . $count
                    . ', source: fallback)',
                'API'
            );
            return $fallback;
        }

        writeLog(
            'API request failed after ' . $attempt . ' attempt(s): '
                . ($lastException
                    ? $lastException->getMessage()
                    : 'unknown'),
            'API_ERROR'
        );

        throw $lastException ?: new Exception('API request failed');
    }

    public function getCurlSslDiagnostics()
    {
        $resolved = function_exists('campus_eats_resolve_ca_bundle')
            ? campus_eats_resolve_ca_bundle()
            : null;

        return array(
            'resolved_bundle' => $resolved,
            'resolved_size' => ($resolved && is_readable($resolved))
                ? filesize($resolved)
                : 0
        );
    }

    public function getAllRestaurants()
    {
        $wasInPause = $this->isWithinFailurePause();
        $result = $this->request('/api/Restaurant', 'GET');

        if (!is_array($result))
        {
            $result = array();
        }

        $source = ($wasInPause || $this->isWithinFailurePause())
            ? 'fallback_or_stale'
            : 'live';

        writeLog(
            'Fetched ' . count($result) . ' restaurants (source: ' . $source . ')',
            'API'
        );

        return $result;
    }

    public function getRestaurantById($id)
    {
        return $this->request('/api/Restaurant/' . $id, 'GET');
    }

    /**
     * @param int|string $id
     * @param string|null $sortByPrice
     * @return array
     */
    public function getRestaurantMenu($id, $sortByPrice = null)
    {
        $endpoint = '/api/Restaurant/' . $id . '/menu';

        if ($sortByPrice === 'asc' || $sortByPrice === 'desc')
        {
            $endpoint .= '?sortbyprice=' . $sortByPrice;
        }

        $result = $this->request($endpoint, 'GET');

        return is_array($result) ? $result : array();
    }

    public function getAllItems()
    {
        return $this->request('/api/Restaurant/items', 'GET');
    }

    public function getUsers()
    {
        return $this->request('/api/User', 'GET');
    }

    public function registerUser($userEmail, $password)
    {
        return $this->request(
            '/api/User/register',
            'POST',
            array('userEmail' => $userEmail, 'password' => $password)
        );
    }

    public function getUserCode($userEmail, $password)
    {
        $qs = http_build_query(
            array('UserEmail' => $userEmail, 'Password' => $password)
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

    public function updateUserPassword($apiKey, $newPassword)
    {
        return $this->request('/api/User/' . $apiKey, 'PUT', $newPassword);
    }

    public function deleteUser($apiKey)
    {
        return $this->request('/api/User/' . $apiKey, 'DELETE');
    }

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

    public function getOrderByMasterId($masterId)
    {
        if ($this->apiKey === null)
        {
            throw new Exception('API key required for order operations');
        }

        return $this->request(
            '/api/Order/' . $masterId . '?apikey=' . urlencode($this->apiKey)
        );
    }

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

    public function deleteSingleOrder($orderId)
    {
        if ($this->apiKey === null)
        {
            throw new Exception('API key required for order operations');
        }

        return $this->request(
            '/api/Order/' . $orderId . '?apikey=' . urlencode($this->apiKey),
            'DELETE'
        );
    }
}

if (!function_exists('getApiService'))
{
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