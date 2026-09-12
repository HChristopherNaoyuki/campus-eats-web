<?php
/**
 * Get Cart API Endpoint (Corrected)
 *
 * This endpoint returns the current user's cart contents with live data validation.
 * All cart items are verified against the current database state to ensure
 * prices, availability, and stock quantities are accurate.
 *
 * CORRECTIONS (Version 6.0 - Performance & Logging):
 * - Fixed N+1 query issue by fetching all items in a single query.
 * - Fixed cart name update log bug by capturing the old name before assignment.
 * - Reduced INFO-level logging to minimize I/O under load.
 * - Improved error handling and logging.
 *
 * SOURCE: campus-eats-process-document.pdf (Page 11, Section 6.1 - Cart management)
 * SOURCE: Full Code Review Report - Section 2.2 & 4.1
 *
 * @version 6.0
 */

// Set JSON content type before any output
header('Content-Type: application/json');

// Set CORS headers for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Credentials: true');

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

// =============================================================================
// Method Validation
// =============================================================================

// Verify HTTP method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
{
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ));
    exit();
}

// =============================================================================
// Process Cart with Live Data Validation
// =============================================================================

try
{
    $db = getDB();
    $userId = getCurrentUserId();

    // Get cart from session storage
    $cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();

    // If cart is empty, return empty response
    if (empty($cart))
    {
        echo json_encode(array(
            'success' => true,
            'cart' => array(),
            'item_count' => 0,
            'total_items' => 0,
            'total_price' => 0.00,
            'message' => 'Cart is empty.',
            'warnings' => array()
        ));
        exit();
    }

    // Extract all item IDs from cart
    $itemIds = array();

    foreach ($cart as $item)
    {
        if (isset($item['item_id']))
        {
            $itemIds[] = (int)$item['item_id'];
        }
    }

    // Validate that we have items to check
    if (empty($itemIds))
    {
        // Clear invalid cart data
        $_SESSION['cart'] = array();
        writeLog("Cart cleared: No valid item IDs found for user $userId", "CART");

        echo json_encode(array(
            'success' => true,
            'cart' => array(),
            'item_count' => 0,
            'total_items' => 0,
            'total_price' => 0.00,
            'message' => 'Cart contained invalid items and has been cleared.',
            'warnings' => array('Cart contained invalid items and has been cleared.')
        ));
        exit();
    }

    // =========================================================================
    // PERFORMANCE OPTIMIZATION: Single Query for All Items
    // =========================================================================
    // Replaces the N+1 query pattern with a single query using an IN() clause.
    // This fetches all required item data in one round-trip, significantly
    // reducing database load for carts with multiple items.
    // Source: Full Code Review Report - Section 4.1
    // =========================================================================
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $menuItemsSql = "
        SELECT
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
        WHERE mi.item_id IN ($placeholders)
    ";

    $stmt = $db->getConnection()->prepare($menuItemsSql);

    // Bind all item IDs
    foreach ($itemIds as $index => $itemId)
    {
        $stmt->bindValue($index + 1, $itemId, PDO::PARAM_INT);
    }

    $stmt->execute();
    $currentMenuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Create lookup array for current menu item data
    $currentItemData = array();

    foreach ($currentMenuItems as $menuItem)
    {
        $currentItemData[$menuItem['item_id']] = $menuItem;
    }

    // Process each cart item and validate against current data
    $validatedCart = array();
    $warnings = array();
    $itemsRemoved = 0;
    $itemsChanged = 0;

    foreach ($cart as $index => $cartItem)
    {
        $itemId = isset($cartItem['item_id']) ? (int)$cartItem['item_id'] : 0;

        // Check if item still exists in database
        if (!isset($currentItemData[$itemId]))
        {
            // Item no longer exists - remove from cart
            $itemsRemoved++;
            $oldName = isset($cartItem['name']) ? $cartItem['name'] : 'Unknown';
            $warnings[] = "Item '$oldName' is no longer available and has been removed from your cart.";
            writeLog("Cart item removed: Item ID $itemId no longer exists for user $userId", "CART");
            continue;
        }

        $currentData = $currentItemData[$itemId];
        $cartItemChanged = false;

        // Check if vendor is open and approved
        if ($currentData['is_open'] != 1)
        {
            $itemsRemoved++;
            $warnings[] = "Vendor '" . $currentData['vendor_name'] . "' is currently closed. Items have been removed from your cart.";
            writeLog("Cart items removed: Vendor {$currentData['vendor_name']} (ID: {$currentData['vendor_id']}) is closed for user $userId", "CART");
            continue;
        }

        if ($currentData['is_approved'] != 1)
        {
            $itemsRemoved++;
            $warnings[] = "Vendor '" . $currentData['vendor_name'] . "' is not approved. Items have been removed from your cart.";
            writeLog("Cart items removed: Vendor {$currentData['vendor_name']} (ID: {$currentData['vendor_id']}) is not approved for user $userId", "CART");
            continue;
        }

        // Check if item is available
        if ($currentData['is_available'] != 1)
        {
            $itemsRemoved++;
            $warnings[] = "Item '" . $currentData['item_name'] . "' is currently unavailable and has been removed from your cart.";
            writeLog("Cart item removed: Item {$currentData['item_name']} (ID: $itemId) is unavailable for user $userId", "CART");
            continue;
        }

        // Check stock availability
        $currentQuantity = isset($cartItem['quantity']) ? (int)$cartItem['quantity'] : 1;
        $availableStock = (int)$currentData['quantity_available'];

        if ($availableStock <= 0)
        {
            $itemsRemoved++;
            $warnings[] = "Item '" . $currentData['item_name'] . "' is out of stock and has been removed from your cart.";
            writeLog("Cart item removed: Item {$currentData['item_name']} (ID: $itemId) is out of stock for user $userId", "CART");
            continue;
        }

        // Adjust quantity if current quantity exceeds available stock
        if ($currentQuantity > $availableStock)
        {
            $cartItem['quantity'] = $availableStock;
            $cartItemChanged = true;
            $itemsChanged++;
            $warnings[] = "Quantity for '" . $currentData['item_name'] . "' has been reduced to $availableStock due to limited stock.";
            writeLog("Cart quantity adjusted: Item {$currentData['item_name']} (ID: $itemId) reduced from $currentQuantity to $availableStock for user $userId", "CART");
        }

        // Check if price has changed
        $storedPrice = isset($cartItem['price']) ? (float)$cartItem['price'] : 0.00;
        $currentPrice = (float)$currentData['price'];

        if (abs($storedPrice - $currentPrice) > 0.001)
        {
            $cartItem['price'] = $currentPrice;
            $cartItemChanged = true;
            $itemsChanged++;
            $warnings[] = "Price for '" . $currentData['item_name'] . "' has been updated from R" . number_format($storedPrice, 2) . " to R" . number_format($currentPrice, 2) . ".";
            // CORRECTION: Only log changes at WARNING level, not INFO.
            writeLog("Cart price changed for item {$currentData['item_name']} (ID: $itemId), user $userId", "CART");
        }

        // =========================================================================
        // BUG FIX: Capture old name before assignment
        // =========================================================================
        // The log message previously always showed the same value for 'from' and 'to'.
        // We now capture the old name into a variable before updating the cart item.
        // Source: Full Code Review Report - Section 2.2
        // =========================================================================
        $oldItemName = isset($cartItem['name']) ? $cartItem['name'] : '';
        if ($oldItemName !== $currentData['item_name'])
        {
            $cartItem['name'] = $currentData['item_name'];
            $cartItemChanged = true;
            writeLog("Cart item name updated: Item ID $itemId from '$oldItemName' to '{$currentData['item_name']}' for user $userId", "CART");
        }

        // Update vendor name if changed
        $oldVendorName = isset($cartItem['vendor_name']) ? $cartItem['vendor_name'] : '';
        if ($oldVendorName !== $currentData['vendor_name'])
        {
            $cartItem['vendor_name'] = $currentData['vendor_name'];
            $cartItemChanged = true;
            writeLog("Cart vendor name updated: Item ID $itemId from '$oldVendorName' to '{$currentData['vendor_name']}' for user $userId", "CART");
        }

        // Update max_quantity to reflect current stock
        $cartItem['max_quantity'] = $availableStock;

        // Add a flag indicating if the item was changed
        $cartItem['changed'] = $cartItemChanged;

        // Add to validated cart
        $validatedCart[] = $cartItem;
    }

    // Update the session cart with validated data
    $_SESSION['cart'] = $validatedCart;

    // Calculate cart totals
    $totalItems = 0;
    $totalPrice = 0.0;

    foreach ($validatedCart as $item)
    {
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $price = isset($item['price']) ? (float)$item['price'] : 0.0;

        $totalItems += $quantity;
        $totalPrice += $price * $quantity;
    }

    // Prepare warning messages
    $warningMessages = array();
    $hasWarnings = false;

    if ($itemsRemoved > 0)
    {
        $hasWarnings = true;
        $warningMessages[] = $itemsRemoved . ' item(s) were removed from your cart because they are no longer available.';
    }

    if ($itemsChanged > 0)
    {
        $hasWarnings = true;
        $warningMessages[] = $itemsChanged . ' item(s) were updated due to price or stock changes.';
    }

    // Add specific warnings to the response
    foreach ($warnings as $warning)
    {
        $warningMessages[] = $warning;
    }

    // CORRECTION: Reduced logging for successful cart retrievals.
    // Only log if there are warnings or significant changes.
    if ($hasWarnings || $itemsChanged > 0 || $itemsRemoved > 0)
    {
        writeLog("Cart retrieved with changes for user $userId. Items: " . count($validatedCart) . ", Changes: $itemsChanged, Removed: $itemsRemoved", "CART");
    }

    // Return success response with validated cart data
    echo json_encode(array(
        'success' => true,
        'cart' => $validatedCart,
        'item_count' => count($validatedCart),
        'total_items' => $totalItems,
        'total_price' => round($totalPrice, 2),
        'has_warnings' => $hasWarnings,
        'warnings' => $warningMessages,
        'items_removed' => $itemsRemoved,
        'items_changed' => $itemsChanged,
        'message' => $hasWarnings ? 'Cart has been validated with warnings.' : 'Cart retrieved successfully.'
    ));
}
catch (PDOException $exception)
{
    writeLog('Get cart PDO error: ' . $exception->getMessage(), "CART_ERROR");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'A database error occurred while retrieving your cart.'
    ));
}
catch (Exception $exception)
{
    writeLog('Get cart error: ' . $exception->getMessage(), "CART_ERROR");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'An error occurred while retrieving your cart.'
    ));
}
?>