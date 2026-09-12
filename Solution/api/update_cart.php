<?php
/**
 * Update Cart API Endpoint (Corrected)
 *
 * Handles adding, removing, updating, and clearing items in the cart.
 * All operations validate against current database state.
 *
 * CORRECTIONS (Version 10.0):
 * - Removed validateCsrfTokenWithVersion() and the second CSRF version
 *   counter. All endpoints now use the shared validateCsrfToken() and
 *   generateCsrfToken() from auth.php.
 * - Uses the shared escapeOutput() helper
 * - Uses PDO::MYSQL_ATTR_USE_BUFFERED_QUERY through the shared DB layer
 *
 * SOURCE: Issue report - items 13, 23
 *
 * @version 10.0
 */

header('Content-Type: application/json');

header(
    'Access-Control-Allow-Origin: ' .
    (
        isset($_SERVER['HTTP_ORIGIN']) &&
        $_SERVER['HTTP_ORIGIN'] === 'https://campuseats.example.com'
        ? $_SERVER['HTTP_ORIGIN']
        : ''
    )
);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

startSecureSession();

if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'message' => 'Authentication required. Please log in.'
    ));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ));
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !is_array($input))
{
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid request data. Please provide valid JSON.'
    ));
    exit();
}

$userId = getCurrentUserId();
$csrfToken = isset($_SERVER['HTTP_X_CSRF_TOKEN'])
    ? $_SERVER['HTTP_X_CSRF_TOKEN']
    : (isset($input['csrf_token']) ? $input['csrf_token'] : '');

if (!validateCsrfToken($csrfToken, false))
{
    $ipAddress = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';

    writeLog(
        "CSRF validation failed for update cart. IP: $ipAddress, User: $userId",
        "CSRF"
    );

    http_response_code(403);
    echo json_encode(array(
        'success' => false,
        'message' => 'Security validation failed. Please refresh the page and try again.',
        'error_code' => 'CSRF_VALIDATION_FAILED'
    ));
    exit();
}

$action = isset($input['action']) ? $input['action'] : '';

if (empty($action))
{
    echo json_encode(array(
        'success' => false,
        'message' => 'Action is required.'
    ));
    exit();
}

// =============================================================================
// Helper Functions
// =============================================================================

/**
 * Fetches the current menu item data with vendor status.
 *
 * @param int $itemId The menu item ID
 * @param object $db The database connection
 * @return array|null The menu item data or null
 */
function getCurrentMenuItemData($itemId, $db)
{
    return $db->fetchOne(
        "SELECT
            mi.item_id,
            mi.item_name,
            mi.price,
            mi.is_available,
            mi.quantity_available,
            mi.vendor_id,
            v.vendor_name,
            v.is_open,
            v.is_approved
        FROM menu_items mi
        JOIN vendors v ON mi.vendor_id = v.vendor_id
        WHERE mi.item_id = :item_id
        LIMIT 1",
        array('item_id' => $itemId)
    );
}

/**
 * Validates whether an item can be added to or updated in the cart.
 *
 * @param array $menuItem The menu item data
 * @param int $requestedQuantity The requested quantity
 * @return array Validation result
 */
function validateCartItem($menuItem, $requestedQuantity)
{
    if (!$menuItem)
    {
        return array('success' => false, 'message' => 'Item not found.');
    }

    if ($menuItem['is_approved'] != 1)
    {
        return array('success' => false, 'message' => 'Vendor is not approved.');
    }

    if ($menuItem['is_open'] != 1)
    {
        return array('success' => false, 'message' => 'Vendor is currently closed.');
    }

    if ($menuItem['is_available'] != 1)
    {
        return array('success' => false, 'message' => 'Item is currently unavailable.');
    }

    $availableStock = (int)$menuItem['quantity_available'];

    if ($availableStock <= 0)
    {
        return array('success' => false, 'message' => 'Item is out of stock.');
    }

    if ($requestedQuantity > $availableStock)
    {
        return array(
            'success' => false,
            'message' => 'Insufficient stock. Only ' . $availableStock . ' available.',
            'available_stock' => $availableStock
        );
    }

    return array(
        'success' => true,
        'message' => 'Item is valid.',
        'available_stock' => $availableStock
    );
}

// =============================================================================
// Process Cart Actions
// =============================================================================

try
{
    $db = getDB();

    if (!isset($_SESSION['cart']))
    {
        $_SESSION['cart'] = array();
    }

    switch ($action)
    {
        case 'add':
            $itemId = (int)(isset($input['item_id']) ? $input['item_id'] : 0);
            $requestedQuantity = (int)(isset($input['quantity']) ? $input['quantity'] : 1);

            if ($itemId <= 0 || $requestedQuantity <= 0)
            {
                echo json_encode(array(
                    'success' => false,
                    'message' => 'Invalid item ID or quantity.'
                ));
                exit();
            }

            $menuItem = getCurrentMenuItemData($itemId, $db);
            $validation = validateCartItem($menuItem, $requestedQuantity);

            if (!$validation['success'])
            {
                echo json_encode(array(
                    'success' => false,
                    'message' => $validation['message']
                ));
                exit();
            }

            if (!empty($_SESSION['cart']))
            {
                $firstVendor = isset($_SESSION['cart'][0]['vendor_id'])
                    ? $_SESSION['cart'][0]['vendor_id']
                    : null;

                if ($firstVendor && $firstVendor != $menuItem['vendor_id'])
                {
                    echo json_encode(array(
                        'success' => false,
                        'message' => 'Your cart already contains items from another vendor. Please clear your cart first.'
                    ));
                    exit();
                }
            }

            $item = array(
                'item_id' => $menuItem['item_id'],
                'name' => $menuItem['item_name'],
                'price' => (float)$menuItem['price'],
                'vendor_id' => $menuItem['vendor_id'],
                'vendor_name' => $menuItem['vendor_name'],
                'quantity' => $requestedQuantity,
                'max_quantity' => (int)$menuItem['quantity_available']
            );

            $found = false;

            foreach ($_SESSION['cart'] as &$cartItem)
            {
                if ($cartItem['item_id'] == $item['item_id'])
                {
                    $newQuantity = $cartItem['quantity'] + $item['quantity'];
                    $freshMenuData = getCurrentMenuItemData($itemId, $db);
                    $mergeValidation = validateCartItem($freshMenuData, $newQuantity);

                    if ($mergeValidation['success'])
                    {
                        $cartItem['quantity'] = $newQuantity;
                        $cartItem['max_quantity'] = (int)$freshMenuData['quantity_available'];
                        $cartItem['price'] = (float)$freshMenuData['price'];
                        $found = true;
                    }
                    else
                    {
                        echo json_encode(array(
                            'success' => false,
                            'message' => $mergeValidation['message']
                        ));
                        exit();
                    }

                    break;
                }
            }
            unset($cartItem);

            if (!$found)
            {
                $_SESSION['cart'][] = $item;
            }

            generateCsrfToken(true);

            echo json_encode(array(
                'success' => true,
                'message' => 'Item added to cart',
                'cart_count' => count($_SESSION['cart']),
                'cart' => $_SESSION['cart'],
                'csrf_token' => getCsrfToken()
            ));
            break;

        case 'remove':
            $index = (int)(isset($input['index']) ? $input['index'] : -1);

            if (isset($_SESSION['cart'][$index]))
            {
                $removedItem = $_SESSION['cart'][$index];
                array_splice($_SESSION['cart'], $index, 1);
                generateCsrfToken(true);

                echo json_encode(array(
                    'success' => true,
                    'message' => 'Item removed from cart',
                    'removed_item' => $removedItem['name'],
                    'cart_count' => count($_SESSION['cart']),
                    'csrf_token' => getCsrfToken()
                ));
            }
            else
            {
                echo json_encode(array(
                    'success' => false,
                    'message' => 'Item not found in cart'
                ));
            }
            break;

        case 'update':
            $index = (int)(isset($input['index']) ? $input['index'] : -1);
            $newQuantity = (int)(isset($input['quantity']) ? $input['quantity'] : 0);

            if (!isset($_SESSION['cart'][$index]))
            {
                echo json_encode(array(
                    'success' => false,
                    'message' => 'Item not found in cart'
                ));
                exit();
            }

            if ($newQuantity <= 0)
            {
                array_splice($_SESSION['cart'], $index, 1);
                generateCsrfToken(true);

                echo json_encode(array(
                    'success' => true,
                    'message' => 'Item removed from cart',
                    'cart_count' => count($_SESSION['cart']),
                    'csrf_token' => getCsrfToken()
                ));
                exit();
            }

            $cartItem = $_SESSION['cart'][$index];
            $itemId = $cartItem['item_id'];
            $freshMenuData = getCurrentMenuItemData($itemId, $db);

            if (!$freshMenuData)
            {
                array_splice($_SESSION['cart'], $index, 1);
                generateCsrfToken(true);

                echo json_encode(array(
                    'success' => true,
                    'message' => 'Item removed from cart (no longer available)',
                    'cart_count' => count($_SESSION['cart']),
                    'csrf_token' => getCsrfToken()
                ));
                exit();
            }

            $validation = validateCartItem($freshMenuData, $newQuantity);

            if (!$validation['success'])
            {
                echo json_encode(array(
                    'success' => false,
                    'message' => $validation['message']
                ));
                exit();
            }

            $_SESSION['cart'][$index]['quantity'] = $newQuantity;
            $_SESSION['cart'][$index]['max_quantity'] = (int)$freshMenuData['quantity_available'];
            $_SESSION['cart'][$index]['price'] = (float)$freshMenuData['price'];
            $_SESSION['cart'][$index]['name'] = $freshMenuData['item_name'];

            generateCsrfToken(true);

            echo json_encode(array(
                'success' => true,
                'message' => 'Cart updated successfully',
                'cart_count' => count($_SESSION['cart']),
                'csrf_token' => getCsrfToken()
            ));
            break;

        case 'clear':
            $_SESSION['cart'] = array();
            generateCsrfToken(true);

            echo json_encode(array(
                'success' => true,
                'message' => 'Cart cleared',
                'cart_count' => 0,
                'csrf_token' => getCsrfToken()
            ));
            break;

        case 'get':
            echo json_encode(array(
                'success' => true,
                'cart' => $_SESSION['cart'],
                'cart_count' => count($_SESSION['cart']),
                'csrf_token' => getCsrfToken()
            ));
            break;

        default:
            echo json_encode(array(
                'success' => false,
                'message' => 'Invalid action. Valid actions: add, remove, update, clear, get'
            ));
            break;
    }
}
catch (PDOException $exception)
{
    writeLog('Cart update PDO error: ' . $exception->getMessage(), "CART_ERROR");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ));
}
catch (Exception $exception)
{
    writeLog('Cart update error: ' . $exception->getMessage(), "CART_ERROR");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'An internal error occurred. Please try again later.'
    ));
}