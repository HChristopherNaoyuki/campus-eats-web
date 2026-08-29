<?php
/**
 * Update Cart API Endpoint (Corrected)
 *
 * This endpoint handles adding, removing, and updating items in the shopping cart.
 * All operations validate against current database state to ensure stock accuracy.
 *
 * CORRECTIONS (Version 9.0 - CSRF Token Fix):
 * - Fixed CSRF token validation to prevent validation failures.
 * - Added token version tracking for debugging.
 * - Improved error responses for CSRF failures.
 * - Added CSRF token synchronization after successful operations.
 * - Added automatic retry mechanism with new token on failure.
 *
 * SOURCE: campus-eats-process-document.pdf (Page 11, Section 6.1 - Modify cart contents)
 * SOURCE: Bug Report - Issue 1: Quantity Adjustment Controls Non-Functional (2026-06-25)
 *
 * @version 9.0
 */

// Set JSON content type before any output
header('Content-Type: application/json');

// Set CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
    http_response_code(200);
    exit();
}

// Load required dependencies
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

// Start secure session
startSecureSession();

// =============================================================================
// Authentication Check
// =============================================================================

// Verify user is authenticated
if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'message' => 'Authentication required. Please log in.'
    ));
    exit();
}

$userId = getCurrentUserId();
writeLog("Cart API accessed by user ID: $userId", "CART");

// =============================================================================
// Method Validation
// =============================================================================

// Verify HTTP method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ));
    exit();
}

// =============================================================================
// Input Validation
// =============================================================================

// Parse and validate JSON input
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

// Validate CSRF token - CORRECTION: Enhanced validation with version tracking
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';

// CORRECTION: Validate token and regenerate on success
$validationResult = validateCsrfTokenWithVersion($csrfToken);

if (!$validationResult['success'])
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    writeLog("CSRF validation failed for update cart. IP: $ipAddress, User: $userId, Reason: {$validationResult['reason']}", "CSRF");

    http_response_code(403);
    echo json_encode(array(
        'success' => false,
        'message' => 'Security validation failed. Please refresh the page and try again.',
        'error_code' => 'CSRF_VALIDATION_FAILED',
        // CORRECTION: Include new CSRF token for client synchronization
        'csrf_token' => generateCsrfToken(true),
        'csrf_version' => $_SESSION['csrf_token_version'] ?? 0
    ));
    exit();
}

// Validate action
$action = $input['action'] ?? '';

if (empty($action))
{
    echo json_encode(array(
        'success' => false,
        'message' => 'Action is required.'
    ));
    exit();
}

writeLog("Cart API action: $action for user ID: $userId", "CART");

// =============================================================================
// CSRF Token Validation with Version Tracking - CORRECTION
// =============================================================================

/**
 * Validates a CSRF token with version tracking for debugging.
 * CORRECTION: Added version tracking to identify token mismatches.
 *
 * @param string $token The token to validate
 * @return array Validation result with success flag and reason
 */
function validateCsrfTokenWithVersion($token)
{
    if (!isset($_SESSION['csrf_token']))
    {
        return array(
            'success' => false,
            'reason' => 'No token in session'
        );
    }

    $storedToken = $_SESSION['csrf_token'];
    $isValid = hash_equals($storedToken, $token);

    if (!$isValid)
    {
        $sessionVersion = $_SESSION['csrf_token_version'] ?? 0;
        return array(
            'success' => false,
            'reason' => "Token mismatch (Session version: $sessionVersion)"
        );
    }

    // Regenerate token after successful validation
    generateCsrfToken(true);
    $_SESSION['csrf_token_version'] = ($_SESSION['csrf_token_version'] ?? 0) + 1;

    return array(
        'success' => true,
        'reason' => 'Valid token'
    );
}

// =============================================================================
// Helper Functions
// =============================================================================

/**
 * Fetches current menu item data with vendor status.
 *
 * @param int $itemId The menu item ID
 * @param object $db The database connection object
 * @return array|null Menu item data or null if not found
 */
function getCurrentMenuItemData($itemId, $db)
{
    return $db->fetchOne
    (
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
        WHERE mi.item_id = :item_id",
        array('item_id' => $itemId)
    );
}

/**
 * Validates if an item can be added to or updated in the cart.
 *
 * @param array $menuItem The menu item data from database
 * @param int $requestedQuantity The quantity being requested
 * @return array Validation result with success flag and message
 */
function validateCartItem($menuItem, $requestedQuantity)
{
    // Check if item exists
    if (!$menuItem)
    {
        return array(
            'success' => false,
            'message' => 'Item not found.'
        );
    }

    // Check if vendor is approved
    if ($menuItem['is_approved'] != 1)
    {
        return array(
            'success' => false,
            'message' => 'Vendor is not approved.'
        );
    }

    // Check if vendor is open
    if ($menuItem['is_open'] != 1)
    {
        return array(
            'success' => false,
            'message' => 'Vendor is currently closed.'
        );
    }

    // Check if item is available
    if ($menuItem['is_available'] != 1)
    {
        return array(
            'success' => false,
            'message' => 'Item is currently unavailable.'
        );
    }

    // Validate stock availability
    $availableStock = (int)$menuItem['quantity_available'];

    if ($availableStock <= 0)
    {
        return array(
            'success' => false,
            'message' => 'Item is out of stock.'
        );
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

    // Initialize cart if not exists
    if (!isset($_SESSION['cart']))
    {
        $_SESSION['cart'] = array();
        writeLog("Cart initialized for user ID: $userId", "CART");
    }

    // Log current cart state before action
    writeLog("Cart before action: " . json_encode($_SESSION['cart']), "CART");

    switch ($action)
    {
        case 'add':
            // Validate item data
            $itemId = (int)($input['item_id'] ?? 0);
            $requestedQuantity = (int)($input['quantity'] ?? 1);

            if ($itemId <= 0 || $requestedQuantity <= 0)
            {
                echo json_encode(array(
                    'success' => false,
                    'message' => 'Invalid item ID or quantity.'
                ));
                exit();
            }

            writeLog("Add to cart: Item ID $itemId, Quantity $requestedQuantity for user $userId", "CART");

            // Fetch current menu item data with vendor status
            $menuItem = getCurrentMenuItemData($itemId, $db);

            // Validate the item
            $validation = validateCartItem($menuItem, $requestedQuantity);

            if (!$validation['success'])
            {
                writeLog("Add to cart validation failed: " . $validation['message'], "CART");
                echo json_encode(array(
                    'success' => false,
                    'message' => $validation['message']
                ));
                exit();
            }

            // Check if cart already has items from a different vendor
            if (!empty($_SESSION['cart']))
            {
                $firstVendor = $_SESSION['cart'][0]['vendor_id'] ?? null;

                if ($firstVendor && $firstVendor != $menuItem['vendor_id'])
                {
                    writeLog("Add to cart failed: Multiple vendors detected", "CART");
                    echo json_encode(array(
                        'success' => false,
                        'message' => 'Your cart already contains items from another vendor. Please clear your cart first.'
                    ));
                    exit();
                }
            }

            // Build cart item with live data
            $item = array(
                'item_id' => $menuItem['item_id'],
                'name' => $menuItem['item_name'],
                'price' => (float)$menuItem['price'],
                'vendor_id' => $menuItem['vendor_id'],
                'vendor_name' => $menuItem['vendor_name'],
                'quantity' => $requestedQuantity,
                'max_quantity' => (int)$menuItem['quantity_available']
            );

            // Check if item already exists in cart
            $found = false;

            foreach ($_SESSION['cart'] as &$cartItem)
            {
                if ($cartItem['item_id'] == $item['item_id'])
                {
                    // Use fresh stock data for merge comparison
                    $newQuantity = $cartItem['quantity'] + $item['quantity'];

                    // Re-validate against current stock
                    $freshMenuData = getCurrentMenuItemData($itemId, $db);
                    $mergeValidation = validateCartItem($freshMenuData, $newQuantity);

                    if ($mergeValidation['success'])
                    {
                        $cartItem['quantity'] = $newQuantity;
                        $cartItem['max_quantity'] = (int)$freshMenuData['quantity_available'];
                        $cartItem['price'] = (float)$freshMenuData['price'];

                        $found = true;
                        writeLog("Cart merge: Item ID $itemId quantity increased to $newQuantity for user $userId", "CART");
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

            if (!$found)
            {
                $_SESSION['cart'][] = $item;
                writeLog("Cart add: Item ID $itemId added (x$requestedQuantity) for user $userId", "CART");
            }

            // CORRECTION: Generate new CSRF token for subsequent requests
            $newToken = generateCsrfToken(true);
            $newVersion = ($_SESSION['csrf_token_version'] ?? 0) + 1;
            $_SESSION['csrf_token_version'] = $newVersion;

            // Log cart state after action
            writeLog("Cart after add: " . json_encode($_SESSION['cart']), "CART");

            echo json_encode(array(
                'success' => true,
                'message' => 'Item added to cart',
                'cart_count' => count($_SESSION['cart']),
                'cart' => $_SESSION['cart'],
                // CORRECTION: Include new CSRF token and version for client synchronization
                'csrf_token' => $newToken,
                'csrf_version' => $newVersion
            ));
            break;

        case 'remove':
            $index = (int)($input['index'] ?? -1);

            if (isset($_SESSION['cart'][$index]))
            {
                $removedItem = $_SESSION['cart'][$index];
                array_splice($_SESSION['cart'], $index, 1);
                $newToken = generateCsrfToken(true);
                $newVersion = ($_SESSION['csrf_token_version'] ?? 0) + 1;
                $_SESSION['csrf_token_version'] = $newVersion;

                writeLog("Cart remove: Item '{$removedItem['name']}' removed for user $userId", "CART");
                writeLog("Cart after remove: " . json_encode($_SESSION['cart']), "CART");

                echo json_encode(array(
                    'success' => true,
                    'message' => 'Item removed from cart',
                    'removed_item' => $removedItem['name'],
                    'cart_count' => count($_SESSION['cart']),
                    'csrf_token' => $newToken,
                    'csrf_version' => $newVersion
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
            $index = (int)($input['index'] ?? -1);
            $newQuantity = (int)($input['quantity'] ?? 0);

            if (!isset($_SESSION['cart'][$index]))
            {
                echo json_encode(array(
                    'success' => false,
                    'message' => 'Item not found in cart'
                ));
                exit();
            }

            writeLog("Cart update: Item index $index, New quantity $newQuantity for user $userId", "CART");

            if ($newQuantity <= 0)
            {
                array_splice($_SESSION['cart'], $index, 1);
                $newToken = generateCsrfToken(true);
                $newVersion = ($_SESSION['csrf_token_version'] ?? 0) + 1;
                $_SESSION['csrf_token_version'] = $newVersion;

                writeLog("Cart update: Item removed (quantity <= 0) for user $userId", "CART");
                writeLog("Cart after update: " . json_encode($_SESSION['cart']), "CART");

                echo json_encode(array(
                    'success' => true,
                    'message' => 'Item removed from cart',
                    'cart_count' => count($_SESSION['cart']),
                    'csrf_token' => $newToken,
                    'csrf_version' => $newVersion
                ));
                exit();
            }

            // Validate against current stock
            $cartItem = $_SESSION['cart'][$index];
            $itemId = $cartItem['item_id'];

            // Fetch fresh menu item data
            $freshMenuData = getCurrentMenuItemData($itemId, $db);

            if (!$freshMenuData)
            {
                // Item no longer exists - remove from cart
                array_splice($_SESSION['cart'], $index, 1);
                $newToken = generateCsrfToken(true);
                $newVersion = ($_SESSION['csrf_token_version'] ?? 0) + 1;
                $_SESSION['csrf_token_version'] = $newVersion;

                writeLog("Cart update: Item ID $itemId removed (no longer exists) for user $userId", "CART");

                echo json_encode(array(
                    'success' => true,
                    'message' => 'Item removed from cart (no longer available)',
                    'cart_count' => count($_SESSION['cart']),
                    'csrf_token' => $newToken,
                    'csrf_version' => $newVersion
                ));
                exit();
            }

            // Validate the new quantity against current stock
            $validation = validateCartItem($freshMenuData, $newQuantity);

            if (!$validation['success'])
            {
                echo json_encode(array(
                    'success' => false,
                    'message' => $validation['message']
                ));
                exit();
            }

            // Update cart item with fresh data
            $_SESSION['cart'][$index]['quantity'] = $newQuantity;
            $_SESSION['cart'][$index]['max_quantity'] = (int)$freshMenuData['quantity_available'];
            $_SESSION['cart'][$index]['price'] = (float)$freshMenuData['price'];
            $_SESSION['cart'][$index]['name'] = $freshMenuData['item_name'];

            $newToken = generateCsrfToken(true);
            $newVersion = ($_SESSION['csrf_token_version'] ?? 0) + 1;
            $_SESSION['csrf_token_version'] = $newVersion;

            writeLog("Cart update: Item ID $itemId quantity set to $newQuantity for user $userId", "CART");
            writeLog("Cart after update: " . json_encode($_SESSION['cart']), "CART");

            echo json_encode(array(
                'success' => true,
                'message' => 'Cart updated successfully',
                'cart_count' => count($_SESSION['cart']),
                'csrf_token' => $newToken,
                'csrf_version' => $newVersion
            ));
            break;

        case 'clear':
            $_SESSION['cart'] = array();
            $newToken = generateCsrfToken(true);
            $newVersion = ($_SESSION['csrf_token_version'] ?? 0) + 1;
            $_SESSION['csrf_token_version'] = $newVersion;

            writeLog("Cart cleared for user $userId", "CART");
            writeLog("Cart after clear: " . json_encode($_SESSION['cart']), "CART");

            echo json_encode(array(
                'success' => true,
                'message' => 'Cart cleared',
                'cart_count' => 0,
                'csrf_token' => $newToken,
                'csrf_version' => $newVersion
            ));
            break;

        case 'get':
            // Return current cart contents
            echo json_encode(array(
                'success' => true,
                'cart' => $_SESSION['cart'],
                'cart_count' => count($_SESSION['cart']),
                'csrf_token' => getCsrfToken(),
                'csrf_version' => $_SESSION['csrf_token_version'] ?? 0
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