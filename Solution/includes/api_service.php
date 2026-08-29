<?php
/**
 * Fake Restaurant API Service Layer
 *
 * This file provides a unified interface for all API communication.
 *
 * SOURCE: API Documentation - Fake Restaurant API
 *
 * @version 2.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/error_logging.php';

class ApiService
{
    private $baseUrl;
    private $apiKey;
    private $defaultHeaders;
    private $timeout;
    private $retryAttempts;
    private $retryDelay;
    private $cache = array();
    private $cacheTtl = 300;

    public function __construct($apiKey = null)
    {
        $this->baseUrl = API_BASE_URL;
        $this->apiKey = $apiKey;
        $this->timeout = API_TIMEOUT;
        $this->retryAttempts = API_RETRY_ATTEMPTS;
        $this->retryDelay = API_RETRY_DELAY;

        $this->defaultHeaders = array(
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        );
    }

    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
        writeLog("API key set for service", "API");
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
            writeLog("All API cache cleared", "API");
        }
        else
        {
            unset($this->cache[$key]);
            writeLog("API cache cleared for key: $key", "API");
        }
    }

    public function request($endpoint, $method = 'GET', $data = null, $additionalHeaders = array(), $useCache = true)
    {
        // For GET requests, check cache first
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
                else
                {
                    unset($this->cache[$cacheKey]);
                }
            }
        }

        $url = $this->baseUrl . $endpoint;
        
        // Add API key to query parameters for authenticated endpoints
        if ($this->apiKey !== null && strpos($endpoint, '?') !== false)
        {
            $url .= '&apikey=' . urlencode($this->apiKey);
        }
        elseif ($this->apiKey !== null && strpos($endpoint, '?') === false)
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

        $headers = array_merge($this->defaultHeaders, $additionalHeaders);
        
        $options = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => $this->timeout,
            'ignore_errors' => true
        );

        if ($data !== null && ($method === 'POST' || $method === 'PUT'))
        {
            $options['content'] = json_encode($data);
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryAttempts)
        {
            try
            {
                writeLog("API request: $method $endpoint (Attempt " . ($attempt + 1) . ")", "API");
                
                $context = stream_context_create(array(
                    'http' => $options
                ));

                $response = file_get_contents($url, false, $context);
                
                if ($response === false)
                {
                    throw new Exception("Failed to fetch API response");
                }

                $result = json_decode($response, true);
                
                if ($result === null)
                {
                    throw new Exception("Invalid JSON response from API");
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
            catch (Exception $e)
            {
                $lastException = $e;
                $attempt++;
                
                if ($attempt < $this->retryAttempts)
                {
                    writeLog("API request failed, retrying in {$this->retryDelay}s: " . $e->getMessage(), "API");
                    sleep($this->retryDelay);
                }
            }
        }

        writeLog("API request failed after {$this->retryAttempts} attempts: " . $lastException->getMessage(), "API_ERROR");
        throw $lastException;
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
    // Order Endpoints (Require Authentication)
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