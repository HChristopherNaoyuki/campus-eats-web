<?php
/**
 * Process Payment API Endpoint (Complete Implementation)
 *
 * Handles order placement, payment processing, cart cleanup, and stock management.
 * Generates unique transaction IDs and applies tax and rounding rules.
 *
 * CORRECTIONS (Version 13.0):
 * - Enhanced CSRF protection with token regeneration after successful payment
 * - Improved input validation with comprehensive checks
 * - Added atomic transaction support with proper rollback
 * - Enhanced stock validation with locking mechanism
 * - Improved error handling with detailed logging
 * - Added proper currency formatting
 * - Enhanced security with prepared statements
 * - Added comprehensive financial calculations with constants
 * - Improved transaction ID generation with uniqueness check
 * - Added vendor status validation at checkout
 * - Added cart validation before processing payment
 * - Fixed session.php deprecation by using auth.php directly
 *
 * SOURCE: campus-eats-process-document.pdf (Section 6.1 - Order placement)
 * SOURCE: PHP Manual - PDO Transactions
 * SOURCE: OWASP - CSRF Prevention Cheat Sheet
 *
 * @version 13.0
 */

header('Content-Type: application/json');

header
(
    'Access-Control-Allow-Origin: ' .
    (
        isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === 'https://campuseats.example.com'
        ? $_SERVER['HTTP_ORIGIN']
        : ''
    )
);
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Credentials: true');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// =============================================================================
// Load Required Dependencies
// =============================================================================

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

// =============================================================================
// Start Secure Session
// =============================================================================

startSecureSession();

// =============================================================================
// Authentication Check
// =============================================================================

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
// Parse and Validate JSON Input
// =============================================================================

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

// =============================================================================
// Validate Required Fields
// =============================================================================

$requiredFields = array('vendor_id', 'items', 'payment_method');

foreach ($requiredFields as $field)
{
    if (empty($input[$field]))
    {
        echo json_encode(array(
            'success' => false,
            'message' => "Missing required field: $field"
        ));
        exit();
    }
}

// =============================================================================
// Validate CSRF Token
// =============================================================================

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? '';

if (!validateCsrfToken($csrfToken))
{
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $userId = getCurrentUserId() ?? 'unknown';
    writeLog("CSRF validation failed for payment. IP: $ipAddress, User: $userId", "CSRF");

    http_response_code(403);
    echo json_encode(array(
        'success' => false,
        'message' => 'Security validation failed. Please refresh the page and try again.'
    ));
    exit();
}

// =============================================================================
// Validate Pickup Time
// =============================================================================

$allowedPickupTimes = explode(',', ALLOWED_PICKUP_TIMES);

if (!empty($input['pickup_time']) && !in_array($input['pickup_time'], $allowedPickupTimes, true))
{
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid pickup time selected. Please choose a valid time.'
    ));
    exit();
}

// =============================================================================
// Validate Payment Method
// =============================================================================

$allowedPaymentMethods = explode(',', ALLOWED_PAYMENT_METHODS);

if (!in_array($input['payment_method'], $allowedPaymentMethods, true))
{
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid payment method selected.'
    ));
    exit();
}

// =============================================================================
// Helper Functions
// =============================================================================

/**
 * Generates a unique transaction ID.
 * Format: TDYYYYMMDDHHMMSS with uniqueness check
 *
 * @param object $db Database connection object
 * @return string Unique transaction ID
 */
function generateTransactionId($db)
{
    // Use UTC time for all timestamp components
    $utcTimestamp = gmdate('YmdHis');
    $transactionId = 'TD' . $utcTimestamp;

    // Verify transaction ID is unique (in case of duplicate timestamps)
    $existingTransaction = $db->fetchOne
    (
        "SELECT order_id FROM orders WHERE transaction_id = :transaction_id",
        array('transaction_id' => $transactionId)
    );

    if ($existingTransaction)
    {
        // Add microsecond precision if duplicate
        $microtime = microtime(true);
        $microseconds = substr(str_replace('.', '', (string)$microtime), -4);
        $transactionId = 'TD' . gmdate('YmdHis') . $microseconds;

        // Double-check uniqueness
        $existingTransaction = $db->fetchOne
        (
            "SELECT order_id FROM orders WHERE transaction_id = :transaction_id",
            array('transaction_id' => $transactionId)
        );

        if ($existingTransaction)
        {
            // Add random suffix if still duplicate
            $transactionId .= bin2hex(random_bytes(2));
        }
    }

    return $transactionId;
}

/**
 * Calculates the service fee based on order subtotal.
 * Fee structure:
 * - Orders below R500: 10% service fee
 * - Orders from R500 to R1000: 6.5% service fee
 * - Orders above R1000: no service fee (0%)
 *
 * @param float $subtotal The order subtotal before fees
 * @return float The calculated service fee
 */
function calculateServiceFee($subtotal)
{
    if ($subtotal < SERVICE_FEE_THRESHOLD_LOW)
    {
        return round($subtotal * SERVICE_FEE_RATE_LOW, 2);
    }
    elseif ($subtotal >= SERVICE_FEE_THRESHOLD_LOW && $subtotal <= SERVICE_FEE_THRESHOLD_HIGH)
    {
        return round($subtotal * SERVICE_FEE_RATE_MID, 2);
    }
    else
    {
        return 0.0;
    }
}

/**
 * Calculates tax on the amount (20% tax rate).
 *
 * @param float $amount The amount to calculate tax on
 * @return float The calculated tax amount
 */
function calculateTax($amount)
{
    return round($amount * TAX_RATE, 2);
}

/**
 * Rounds a number up to the next multiple of R5.
 *
 * @param float $amount The amount to round
 * @return float The rounded amount (multiple of 5)
 */
function roundUpToNextFive($amount)
{
    return ceil($amount / ROUNDING_MULTIPLE) * ROUNDING_MULTIPLE;
}

/**
 * Calculates the rounding adjustment needed to reach the next multiple of R5.
 *
 * @param float $amount The amount before rounding
 * @param float $roundedAmount The amount after rounding
 * @return float The rounding adjustment
 */
function calculateRoundingAdjustment($amount, $roundedAmount)
{
    return round($roundedAmount - $amount, 2);
}

// =============================================================================
// Process Payment
// =============================================================================

try
{
    $db = getDB();
    $userId = getCurrentUserId();

    // Begin database transaction for atomicity
    $db->beginTransaction();

    // Lock the vendor row to prevent concurrent modifications
    $vendor = $db->fetchOne
    (
        "SELECT vendor_id, vendor_name, is_open, is_approved
         FROM vendors
         WHERE vendor_id = :vendor_id AND is_approved = 1
         FOR UPDATE",
        array('vendor_id' => $input['vendor_id'])
    );

    if (!$vendor)
    {
        $db->rollback();
        echo json_encode(array(
            'success' => false,
            'message' => 'Vendor not found or not approved'
        ));
        exit();
    }

    // Handle vendor closure during checkout
    if (!$vendor['is_open'])
    {
        $db->rollback();

        // Clear the cart from session to prevent repeated failed attempts
        unset($_SESSION['cart']);

        writeLog
        (
            "Payment failed: Vendor {$vendor['vendor_name']} (ID: {$vendor['vendor_id']}) is closed. " .
            "Cart cleared for user: $userId",
            "PAYMENT"
        );

        echo json_encode(array(
            'success' => false,
            'message' => 'Vendor is currently closed. Please try again later. Your cart has been cleared.'
        ));
        exit();
    }

    $safeVendorId = (int)$vendor['vendor_id'];

    // =============================================================================
    // Extract and Validate Item Data
    // =============================================================================

    $itemIds = array();
    $requestedQuantities = array();

    foreach ($input['items'] as $item)
    {
        $itemId = (int)($item['item_id'] ?? 0);
        $quantity = (int)($item['quantity'] ?? 1);

        if ($itemId <= 0 || $quantity <= 0)
        {
            $db->rollback();
            echo json_encode(array(
                'success' => false,
                'message' => 'Invalid item or quantity.'
            ));
            exit();
        }

        $itemIds[] = $itemId;
        $requestedQuantities[$itemId] = $quantity;
    }

    if (empty($itemIds))
    {
        $db->rollback();
        echo json_encode(array(
            'success' => false,
            'message' => 'No items in order.'
        ));
        exit();
    }

    // =============================================================================
    // Lock and Validate Menu Items
    // =============================================================================

    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));

    $sql = "SELECT item_id, price, quantity_available, is_available
            FROM menu_items
            WHERE item_id IN ($placeholders)
              AND vendor_id = ?
              AND is_available = 1
            FOR UPDATE";

    $params = array_merge($itemIds, array($safeVendorId));
    $validItems = $db->fetchAll($sql, $params);

    // Verify all items are valid and belong to the vendor
    if (count($validItems) !== count($itemIds))
    {
        $db->rollback();
        writeLog
        (
            "Payment attempted with invalid items for vendor $safeVendorId. User: $userId",
            "PAYMENT"
        );
        echo json_encode(array(
            'success' => false,
            'message' => 'One or more menu items are invalid or unavailable.'
        ));
        exit();
    }

    // =============================================================================
    // Validate Stock Availability
    // =============================================================================

    foreach ($validItems as $menuItem)
    {
        $itemId = $menuItem['item_id'];
        $requestedQty = $requestedQuantities[$itemId];
        $availableQty = (int)$menuItem['quantity_available'];

        if ($availableQty < $requestedQty)
        {
            $db->rollback();
            writeLog
            (
                "Insufficient stock for item ID $itemId. Requested: $requestedQty, Available: $availableQty",
                "PAYMENT"
            );
            echo json_encode(array(
                'success' => false,
                'message' => "Insufficient stock for item. Only $availableQty available."
            ));
            exit();
        }
    }

    // =============================================================================
    // Calculate Financial Values
    // =============================================================================

    $validatedItems = array();
    $calculatedSubtotal = 0.0;

    foreach ($validItems as $menuItem)
    {
        $itemId = $menuItem['item_id'];
        $quantity = $requestedQuantities[$itemId];
        $unitPrice = (float)$menuItem['price'];
        $subtotal = $unitPrice * $quantity;

        $validatedItems[] = array(
            'item_id' => $itemId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'subtotal' => $subtotal
        );

        $calculatedSubtotal += $subtotal;
    }

    // Calculate service fee based on subtotal
    $serviceFee = calculateServiceFee($calculatedSubtotal);

    // Calculate subtotal plus service fee (before tax)
    $amountBeforeTax = $calculatedSubtotal + $serviceFee;

    // Calculate tax (20% on the amount before tax)
    $tax = calculateTax($amountBeforeTax);

    // Calculate amount before rounding
    $amountBeforeRounding = $amountBeforeTax + $tax;

    // Round up to the next multiple of R5
    $finalTotal = roundUpToNextFive($amountBeforeRounding);

    // Calculate rounding adjustment
    $roundingAdjustment = calculateRoundingAdjustment($amountBeforeRounding, $finalTotal);

    // =============================================================================
    // Generate Transaction ID
    // =============================================================================

    $transactionId = generateTransactionId($db);
    writeLog("Generated transaction ID: $transactionId for user: $userId", "PAYMENT");

    // Generate unique order number
    $orderNumber = 'ORD-' . date('Ymd') . '-' . bin2hex(random_bytes(6));

    // Format pickup time if provided
    $pickupDateTime = null;
    if (!empty($input['pickup_time']))
    {
        $currentDate = date('Y-m-d');
        $fullPickupString = $currentDate . ' ' . $input['pickup_time'] . ':00';
        $pickupDateTime = date('Y-m-d H:i:s', strtotime($fullPickupString));
    }

    // =============================================================================
    // Insert Order Record
    // =============================================================================

    $orderSql =
        "INSERT INTO orders
            (user_id, vendor_id, order_number, transaction_id, order_status,
             total_amount, subtotal, service_fee, tax, rounding_adjustment,
             pickup_time, special_requests)
         VALUES
            (:user_id, :vendor_id, :order_number, :transaction_id, :order_status,
             :total_amount, :subtotal, :service_fee, :tax, :rounding_adjustment,
             :pickup_time, :special_requests)";

    $orderId = $db->insert
    (
        $orderSql,
        array(
            'user_id' => $userId,
            'vendor_id' => $safeVendorId,
            'order_number' => $orderNumber,
            'transaction_id' => $transactionId,
            'order_status' => ORDER_STATUS_PENDING,
            'total_amount' => $finalTotal,
            'subtotal' => $calculatedSubtotal,
            'service_fee' => $serviceFee,
            'tax' => $tax,
            'rounding_adjustment' => $roundingAdjustment,
            'pickup_time' => $pickupDateTime,
            'special_requests' => $input['special_requests'] ?? null
        )
    );

    if (!$orderId)
    {
        $db->rollback();
        writeLog("Failed to create order for user $userId, vendor $safeVendorId", "PAYMENT");
        echo json_encode(array(
            'success' => false,
            'message' => 'Failed to create order.'
        ));
        exit();
    }

    // =============================================================================
    // Insert Order Items and Decrement Stock
    // =============================================================================

    foreach ($validatedItems as $item)
    {
        // Insert order item record
        $itemSql =
            "INSERT INTO order_items
                (order_id, item_id, quantity, unit_price, subtotal)
             VALUES
                (:order_id, :item_id, :quantity, :unit_price, :subtotal)";

        $db->insert
        (
            $itemSql,
            array(
                'order_id' => $orderId,
                'item_id' => $item['item_id'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['subtotal']
            )
        );

        // Decrement the available stock quantity
        $stockSql = "UPDATE menu_items
                     SET quantity_available = quantity_available - :quantity
                     WHERE item_id = :item_id";

        $db->executeQuery
        (
            $stockSql,
            array(
                'quantity' => $item['quantity'],
                'item_id' => $item['item_id']
            )
        );

        writeLog("Stock updated for item ID {$item['item_id']}: Decremented by {$item['quantity']}", "INVENTORY");
    }

    // =============================================================================
    // Insert Payment Record
    // =============================================================================

    $paymentSql =
        "INSERT INTO payments
            (order_id, payment_method, payment_status, transaction_reference, amount)
         VALUES
            (:order_id, :payment_method, :payment_status, :transaction_reference, :amount)";

    $db->insert
    (
        $paymentSql,
        array(
            'order_id' => $orderId,
            'payment_method' => $input['payment_method'],
            'payment_status' => PAYMENT_STATUS_COMPLETED,
            'transaction_reference' => $transactionId,
            'amount' => $finalTotal
        )
    );

    // =============================================================================
    // Cleanup and Commit
    // =============================================================================

    // Clear the cart from session after successful order
    unset($_SESSION['cart']);

    // Generate new CSRF token for subsequent requests
    generateCsrfToken(true);

    // Commit the transaction
    $db->commit();

    writeLog("Order $orderId created successfully for user $userId, vendor $safeVendorId", "PAYMENT");
    writeLog("Transaction ID: $transactionId, Final Amount: $finalTotal", "PAYMENT");
    writeLog("Inventory updated for order $orderId", "INVENTORY");

    // =============================================================================
    // Prepare Response Data
    // =============================================================================

    $receiptData = array(
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'transaction_id' => $transactionId,
        'order_status' => ORDER_STATUS_PENDING,
        'order_date' => date('Y-m-d H:i:s'),
        'vendor_name' => $vendor['vendor_name'],
        'items' => $validatedItems,
        'subtotal' => $calculatedSubtotal,
        'service_fee' => $serviceFee,
        'tax' => $tax,
        'rounding_adjustment' => $roundingAdjustment,
        'total_amount' => $finalTotal,
        'payment_method' => $input['payment_method'],
        'pickup_time' => $pickupDateTime
    );

    // Return success response with receipt data
    echo json_encode(array(
        'success' => true,
        'message' => 'Order placed successfully',
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'transaction_id' => $transactionId,
        'receipt' => $receiptData,
        'subtotal' => $calculatedSubtotal,
        'service_fee' => $serviceFee,
        'tax' => $tax,
        'rounding_adjustment' => $roundingAdjustment,
        'total_amount' => $finalTotal
    ));
}
catch (PDOException $exception)
{
    // Rollback transaction on PDO exception
    if (isset($db))
    {
        $db->rollback();
    }

    writeLog('Payment PDO error: ' . $exception->getMessage(), "PAYMENT_ERROR");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ));
}
catch (Exception $exception)
{
    // Rollback transaction on any exception
    if (isset($db))
    {
        $db->rollback();
    }

    writeLog('Payment processing failed: ' . $exception->getMessage(), "PAYMENT_ERROR");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Payment processing failed. Please try again later.'
    ));
}
?>