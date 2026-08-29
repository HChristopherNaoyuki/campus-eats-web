<?php
/**
 * Checkout Page for Students and Standard Users
 *
 * This page handles the checkout process for student and standard users.
 * Includes service fee calculation, tax calculation, rounding rules,
 * and student discount (2.5%) for Student role users only.
 *
 * CORRECTIONS (Version 31.0 - Pricing Order Fix):
 * - Reordered financial calculations to match the process document:
 *   1. Subtotal → Service Fee → Tax → Rounding → Student Discount (last)
 * - Student discount is now applied after rounding, not before tax
 * - Fixes FUNC-03 from the scope note
 *
 * SOURCE: campus-eats-process-document.pdf (Section 10.1 - Rule Table)
 * SOURCE: Scope Note - FUNC-03
 * SOURCE: Mockups - Checkout design
 *
 * @version 31.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

// Start secure session and require student or standard role
startSecureSession();
requireStudentOrStandard();

$db = getDB();
$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();

$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
$checkoutData = isset($_SESSION['checkout_data']) ? $_SESSION['checkout_data'] : null;

// =============================================================================
// Helper Functions for Financial Calculations
// CORRECTION: FUNC-03 - Reordered to: fee → tax → round → discount
// =============================================================================

function calculateServiceFee($subtotal)
{
    if ($subtotal < 500)
    {
        return round($subtotal * 0.10, 2);
    }
    elseif ($subtotal >= 500 && $subtotal <= 1000)
    {
        return round($subtotal * 0.065, 2);
    }
    else
    {
        return 0.0;
    }
}

function calculateTax($amount)
{
    return round($amount * 0.20, 2);
}

function roundUpToNextFive($amount)
{
    return ceil($amount / 5) * 5;
}

function calculateRoundingAdjustment($amount, $roundedAmount)
{
    return round($roundedAmount - $amount, 2);
}

function calculateStudentDiscount($amount, $isStudent)
{
    if ($isStudent === true)
    {
        return round($amount * 0.025, 2);
    }
    return 0.0;
}

function getTableColumns($db, $tableName)
{
    $allowedTables = array('orders', 'payments', 'users', 'vendors', 'menu_items', 'order_items');

    if (!in_array($tableName, $allowedTables, true))
    {
        writeLog("Attempted to access non-allowed table: $tableName", "SECURITY");
        return array();
    }

    try
    {
        $columns = $db->fetchAll("SHOW COLUMNS FROM `$tableName`");
        $columnNames = array();

        foreach ($columns as $column)
        {
            $columnNames[] = $column['Field'];
        }

        return $columnNames;
    }
    catch (Exception $e)
    {
        writeLog("Failed to get table columns for $tableName: " . $e->getMessage(), "CHECKOUT");
        return array();
    }
}

// =============================================================================
// CORRECTION: FUNC-03 - Calculate cart totals in the correct order
// Previous order: subtotal → fee → discount → tax → round
// Correct order (per process document): subtotal → fee → tax → round → discount
// Source: Scope Note - FUNC-03, Section 10.1 Rule Table
// =============================================================================

function calculateCartTotals($cart, $isStudent)
{
    $subtotal = 0.0;
    foreach ($cart as $item)
    {
        $price = isset($item['price']) ? (float)$item['price'] : 0.0;
        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
        $subtotal += $price * $quantity;
    }

    // Step 1: Calculate service fee based on subtotal
    $serviceFee = calculateServiceFee($subtotal);
    $amountBeforeTax = $subtotal + $serviceFee;

    // Step 2: Calculate tax on the fee-inclusive amount
    $tax = calculateTax($amountBeforeTax);
    $amountBeforeRounding = $amountBeforeTax + $tax;

    // Step 3: Round up to the nearest R5
    $roundedTotal = roundUpToNextFive($amountBeforeRounding);
    $roundingAdjustment = calculateRoundingAdjustment($amountBeforeRounding, $roundedTotal);

    // Step 4: Apply student discount LAST (against the rounded total)
    $studentDiscount = calculateStudentDiscount($roundedTotal, $isStudent);
    $finalTotal = $roundedTotal - $studentDiscount;

    // Ensure final total is not negative
    if ($finalTotal < 0)
    {
        $finalTotal = 0.0;
    }

    return array(
        'subtotal' => $subtotal,
        'service_fee' => $serviceFee,
        'tax' => $tax,
        'rounding_adjustment' => $roundingAdjustment,
        'student_discount' => $studentDiscount,
        'final_total' => $finalTotal,
        'amount_before_rounding' => $amountBeforeRounding,
        'rounded_total' => $roundedTotal,
        'is_student' => $isStudent
    );
}

// =============================================================================
// Main Checkout Logic
// =============================================================================

$orderPlaced = false;
$orderId = null;
$orderNumber = null;
$errorMessage = null;

// Redirect if cart is empty
if (empty($cart) && $checkoutData === null)
{
    $_SESSION['flash_message'] = 'Your cart is empty. Please add items before checking out.';
    $_SESSION['flash_type'] = 'warning';
    header('Location: cart.php');
    exit();
}

// If checkout data exists, use it
if ($checkoutData !== null)
{
    $vendorId = $checkoutData['vendor']['vendor_id'];
    $vendorName = $checkoutData['vendor']['vendor_name'];
    $vendorIsOpen = $checkoutData['vendor']['is_open'];
    $items = $checkoutData['items'];
    $totals = calculateCartTotals($items, isStudent());
    $subtotal = $totals['subtotal'];
    $serviceFee = $totals['service_fee'];
    $studentDiscount = $totals['student_discount'];
    $tax = $totals['tax'];
    $roundingAdjustment = $totals['rounding_adjustment'];
    $finalTotal = $totals['final_total'];
    $isStudent = $totals['is_student'];
}
else
{
    $vendorId = isset($cart[0]['vendor_id']) ? (int)$cart[0]['vendor_id'] : 0;

    $vendor = $db->fetchOne
    (
        "SELECT vendor_id, vendor_name, is_open, is_approved
         FROM vendors
         WHERE vendor_id = :vendor_id AND is_approved = 1",
        array('vendor_id' => $vendorId)
    );

    if (!$vendor)
    {
        $_SESSION['flash_message'] = 'Vendor not found or not approved.';
        $_SESSION['flash_type'] = 'error';
        $_SESSION['cart'] = array();
        header('Location: dashboard.php');
        exit();
    }

    if ($vendor['is_open'] != 1)
    {
        $_SESSION['flash_message'] = 'Vendor is currently closed. Please try again later.';
        $_SESSION['flash_type'] = 'error';
        $_SESSION['cart'] = array();
        header('Location: dashboard.php');
        exit();
    }

    $vendorName = $vendor['vendor_name'];
    $vendorIsOpen = $vendor['is_open'];
    $items = $cart;
    $totals = calculateCartTotals($cart, isStudent());
    $subtotal = $totals['subtotal'];
    $serviceFee = $totals['service_fee'];
    $studentDiscount = $totals['student_discount'];
    $tax = $totals['tax'];
    $roundingAdjustment = $totals['rounding_adjustment'];
    $finalTotal = $totals['final_total'];
    $isStudent = $totals['is_student'];
}

$feeMessage = '';
if ($subtotal < 500)
{
    $feeMessage = '10% service fee applied to orders under R500';
}
elseif ($subtotal >= 500 && $subtotal <= 1000)
{
    $feeMessage = '6.5% service fee applied to orders between R500 and R1000';
}
elseif ($subtotal > 1000)
{
    $feeMessage = 'No service fee for orders over R1000';
}

// Generate Pickup Time Options
$pickupTimes = array();
for ($hour = 10; $hour <= 16; $hour++)
{
    for ($minute = 0; $minute < 60; $minute += 15)
    {
        if ($hour == 16 && $minute > 0)
        {
            continue;
        }
        $timeString = sprintf('%02d:%02d', $hour, $minute);
        $displayString = date('g:i A', strtotime($timeString));
        $pickupTimes[] = array(
            'value' => $timeString,
            'label' => $displayString
        );
    }
}

// Handle Order Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order']))
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($submittedToken, true))
    {
        $errorMessage = 'Security validation failed. Please refresh the page and try again.';
        writeLog("Checkout CSRF validation failed for user ID: " . getCurrentUserId(), "CHECKOUT");
    }
    else
    {
        $pickupTime = trim($_POST['pickup_time'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? '');
        $specialRequests = trim($_POST['special_requests'] ?? '');

        if (empty($pickupTime))
        {
            $errorMessage = 'Please select a pickup time.';
        }
        elseif (empty($paymentMethod))
        {
            $errorMessage = 'Please select a payment method.';
        }
        else
        {
            $pickupTimeParts = explode(':', $pickupTime);
            if (count($pickupTimeParts) !== 2)
            {
                $errorMessage = 'Invalid pickup time format.';
            }
            else
            {
                $hour = (int)$pickupTimeParts[0];
                $minute = (int)$pickupTimeParts[1];

                if ($hour < 10 || $hour > 16 || ($hour == 16 && $minute > 0))
                {
                    $errorMessage = 'Please select a pickup time between 10:00 and 16:00.';
                }
                elseif ($minute % 15 !== 0)
                {
                    $errorMessage = 'Please select a valid pickup time (15-minute intervals).';
                }
                else
                {
                    $allowedPaymentMethods = array('debit_card', 'campus_wallet', 'coupons');

                    if (!in_array($paymentMethod, $allowedPaymentMethods, true))
                    {
                        $errorMessage = 'Invalid payment method selected.';
                    }
                    else
                    {
                        $totals = calculateCartTotals($items, isStudent());
                        $subtotal = $totals['subtotal'];
                        $serviceFee = $totals['service_fee'];
                        $studentDiscount = $totals['student_discount'];
                        $tax = $totals['tax'];
                        $roundingAdjustment = $totals['rounding_adjustment'];
                        $finalTotal = $totals['final_total'];

                        writeLog(
                            "Checkout: Order totals - Subtotal: $subtotal, Service Fee: $serviceFee, " .
                            "Student Discount: $studentDiscount, Tax: $tax, Rounding: $roundingAdjustment, Final: $finalTotal",
                            "CHECKOUT"
                        );

                        try
                        {
                            $db->executeQuery("SET TRANSACTION ISOLATION LEVEL READ COMMITTED");
                            $db->beginTransaction();

                            $vendorLock = $db->fetchOne
                            (
                                "SELECT vendor_id, vendor_name, is_open, is_approved
                                 FROM vendors
                                 WHERE vendor_id = :vendor_id AND is_approved = 1
                                 FOR UPDATE",
                                array('vendor_id' => $vendorId)
                            );

                            if (!$vendorLock || $vendorLock['is_open'] != 1)
                            {
                                $db->rollback();
                                $errorMessage = 'Vendor is currently closed. Please try again later.';
                                writeLog("Checkout failed: Vendor closed during order placement", "CHECKOUT");
                            }
                            else
                            {
                                // =========================================================================
                                // CORRECTION: FUNC-05 - Generate 8-character uppercase alphanumeric suffix
                                // Previous: bin2hex(random_bytes(6)) produced 12-char lowercase hex
                                // Correct: 8-char uppercase alphanumeric per Section 11.2
                                // Source: Scope Note - FUNC-05
                                // =========================================================================
                                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                                $suffix = '';
                                for ($i = 0; $i < 8; $i++)
                                {
                                    $suffix .= $chars[random_int(0, strlen($chars) - 1)];
                                }
                                $orderNumber = 'ORD-' . date('Ymd') . '-' . $suffix;

                                $orderColumns = getTableColumns($db, 'orders');

                                $orderFields = array(
                                    'user_id' => $currentUser['user_id'],
                                    'vendor_id' => $vendorId,
                                    'order_number' => $orderNumber,
                                    'order_status' => ORDER_STATUS_PENDING,
                                    'total_amount' => $finalTotal
                                );

                                if (in_array('subtotal', $orderColumns))
                                {
                                    $orderFields['subtotal'] = $subtotal;
                                }

                                if (in_array('service_fee', $orderColumns))
                                {
                                    $orderFields['service_fee'] = $serviceFee;
                                }

                                if ($isStudent && in_array('student_discount', $orderColumns))
                                {
                                    $orderFields['student_discount'] = $studentDiscount;
                                }

                                if (in_array('tax', $orderColumns))
                                {
                                    $orderFields['tax'] = $tax;
                                }

                                if (in_array('rounding_adjustment', $orderColumns))
                                {
                                    $orderFields['rounding_adjustment'] = $roundingAdjustment;
                                }

                                if (in_array('transaction_id', $orderColumns))
                                {
                                    $transactionId = 'TD' . gmdate('YmdHis');
                                    $orderFields['transaction_id'] = $transactionId;
                                }

                                if (in_array('pickup_time', $orderColumns))
                                {
                                    $orderFields['pickup_time'] = $pickupTime;
                                }

                                if (in_array('special_requests', $orderColumns))
                                {
                                    $orderFields['special_requests'] = $specialRequests;
                                }

                                $fieldNames = array_keys($orderFields);
                                $fieldPlaceholders = array();
                                foreach ($fieldNames as $fieldName)
                                {
                                    $fieldPlaceholders[] = ':' . $fieldName;
                                }

                                $orderSql = "INSERT INTO orders (" . implode(', ', $fieldNames) . ")
                                             VALUES (" . implode(', ', $fieldPlaceholders) . ")";

                                $orderId = $db->insert($orderSql, $orderFields);

                                if (!$orderId)
                                {
                                    $db->rollback();
                                    $errorMessage = 'Failed to create order. Please try again.';
                                    writeLog("Checkout failed: Order insertion failed for user ID: " . getCurrentUserId(), "CHECKOUT");
                                }
                                else
                                {
                                    foreach ($items as $item)
                                    {
                                        $itemId = isset($item['item_id']) ? (int)$item['item_id'] : 0;
                                        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                                        $unitPrice = isset($item['price']) ? (float)$item['price'] : 0.0;
                                        $subtotalItem = $unitPrice * $quantity;

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
                                                'item_id' => $itemId,
                                                'quantity' => $quantity,
                                                'unit_price' => $unitPrice,
                                                'subtotal' => $subtotalItem
                                            )
                                        );

                                        $stockSql = "UPDATE menu_items
                                                     SET quantity_available = quantity_available - :quantity
                                                     WHERE item_id = :item_id";

                                        $db->executeQuery
                                        (
                                            $stockSql,
                                            array(
                                                'quantity' => $quantity,
                                                'item_id' => $itemId
                                            )
                                        );

                                        writeLog("Stock updated for item ID $itemId: Decremented by $quantity", "INVENTORY");
                                    }

                                    $paymentColumns = getTableColumns($db, 'payments');
                                    $transactionReference = 'PAY-' . date('Ymd') . '-' . bin2hex(random_bytes(6));

                                    $hasTransactionRef = in_array('transaction_reference', $paymentColumns);

                                    if ($hasTransactionRef)
                                    {
                                        $paymentSql =
                                            "INSERT INTO payments
                                                (order_id, payment_method, payment_status, amount, transaction_reference)
                                             VALUES
                                                (:order_id, :payment_method, :payment_status, :amount, :transaction_reference)";

                                        $db->insert
                                        (
                                            $paymentSql,
                                            array(
                                                'order_id' => $orderId,
                                                'payment_method' => $paymentMethod,
                                                'payment_status' => PAYMENT_STATUS_COMPLETED,
                                                'amount' => $finalTotal,
                                                'transaction_reference' => $transactionReference
                                            )
                                        );

                                        writeLog("Payment record created with transaction_reference: $transactionReference", "CHECKOUT");
                                    }
                                    else
                                    {
                                        $paymentSql =
                                            "INSERT INTO payments
                                                (order_id, payment_method, payment_status, amount)
                                             VALUES
                                                (:order_id, :payment_method, :payment_status, :amount)";

                                        $db->insert
                                        (
                                            $paymentSql,
                                            array(
                                                'order_id' => $orderId,
                                                'payment_method' => $paymentMethod,
                                                'payment_status' => PAYMENT_STATUS_COMPLETED,
                                                'amount' => $finalTotal
                                            )
                                        );

                                        writeLog("Payment record created without transaction_reference", "CHECKOUT");
                                    }

                                    unset($_SESSION['cart']);
                                    unset($_SESSION['checkout_data']);

                                    writeLog("Cart cleared from session after successful checkout for user ID: " . getCurrentUserId(), "CHECKOUT");

                                    generateCsrfToken(true);

                                    $db->commit();

                                    $orderPlaced = true;

                                    $discountMessage = $isStudent ? ", Student Discount: R$studentDiscount" : "";
                                    writeLog
                                    (
                                        "Order placed successfully. Order ID: $orderId, Order Number: $orderNumber, " .
                                        "User ID: " . getCurrentUserId() . ", Role: " . getCurrentUserRole() . ", Total: R$finalTotal" .
                                        $discountMessage,
                                        "CHECKOUT"
                                    );
                                }
                            }
                        }
                        catch (PDOException $e)
                        {
                            if (isset($db))
                            {
                                $db->rollback();
                            }
                            $errorMessage = 'Unable to place your order at this time. Please try again later.';
                            writeLog("Checkout PDO error: " . $e->getMessage(), "CHECKOUT_ERROR");
                        }
                        catch (Exception $e)
                        {
                            if (isset($db))
                            {
                                $db->rollback();
                            }
                            $errorMessage = 'An error occurred. Please try again later.';
                            writeLog("Checkout error: " . $e->getMessage(), "CHECKOUT_ERROR");
                        }
                    }
                }
            }
        }
    }
}

// Fetch Order Details for Receipt
if ($orderPlaced)
{
    $orderColumns = getTableColumns($db, 'orders');

    $selectFields = array(
        'o.order_id',
        'o.order_number',
        'o.order_status',
        'o.total_amount',
        'o.pickup_time',
        'o.special_requests',
        'o.order_placed_at',
        'v.vendor_name'
    );

    if (in_array('subtotal', $orderColumns))
    {
        $selectFields[] = 'o.subtotal';
    }
    else
    {
        $selectFields[] = '0 as subtotal';
    }

    if (in_array('service_fee', $orderColumns))
    {
        $selectFields[] = 'o.service_fee';
    }
    else
    {
        $selectFields[] = '0 as service_fee';
    }

    if ($isStudent && in_array('student_discount', $orderColumns))
    {
        $selectFields[] = 'o.student_discount';
    }
    else
    {
        $selectFields[] = '0 as student_discount';
    }

    if (in_array('tax', $orderColumns))
    {
        $selectFields[] = 'o.tax';
    }
    else
    {
        $selectFields[] = '0 as tax';
    }

    if (in_array('rounding_adjustment', $orderColumns))
    {
        $selectFields[] = 'o.rounding_adjustment';
    }
    else
    {
        $selectFields[] = '0 as rounding_adjustment';
    }

    $selectClause = implode(', ', $selectFields);

    $orderReceipt = $db->fetchOne
    (
        "SELECT $selectClause
         FROM orders o
         JOIN vendors v ON o.vendor_id = v.vendor_id
         WHERE o.order_id = :order_id",
        array('order_id' => $orderId)
    );

    $orderItems = $db->fetchAll
    (
        "SELECT oi.quantity, oi.unit_price, oi.subtotal, mi.item_name
         FROM order_items oi
         JOIN menu_items mi ON oi.item_id = mi.item_id
         WHERE oi.order_id = :order_id",
        array('order_id' => $orderId)
    );
}

function checkoutEscape($string)
{
    if ($string === null) return '';
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo $orderPlaced ? 'Order Confirmation' : 'Checkout'; ?> · Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <style>
        .checkout-grid
        {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--space-6);
        }

        .order-summary
        {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid var(--gray-100);
        }

        .order-summary-header
        {
            background: linear-gradient(135deg, var(--orange), var(--orange-dark));
            color: white;
            padding: var(--space-4) var(--space-6);
        }

        .order-summary-header h3
        {
            margin: 0;
            color: white;
        }

        .order-summary-body
        {
            padding: var(--space-5) var(--space-6);
        }

        .order-item-row
        {
            display: flex;
            justify-content: space-between;
            padding: var(--space-2) 0;
            border-bottom: 1px solid var(--gray-200);
        }

        .order-item-row:last-child
        {
            border-bottom: none;
        }

        .summary-breakdown
        {
            margin-top: var(--space-4);
            padding-top: var(--space-4);
            border-top: 2px solid var(--gray-200);
        }

        .summary-row
        {
            display: flex;
            justify-content: space-between;
            padding: var(--space-2) 0;
        }

        .service-fee-row { color: var(--gray-700); font-size: 0.875rem; }
        .discount-row { color: var(--success); font-size: 0.875rem; font-weight: 500; }
        .tax-row { color: var(--gray-700); font-size: 0.875rem; border-top: 1px dashed var(--gray-300); margin-top: var(--space-1); padding-top: var(--space-2); }
        .rounding-row { color: var(--gray-600); font-size: 0.8125rem; font-style: italic; }
        .total-row { font-weight: 700; font-size: 1.25rem; color: var(--orange); border-top: 2px solid var(--orange-light); margin-top: var(--space-2); padding-top: var(--space-3); }

        .fee-note
        {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: var(--space-2);
            padding-top: var(--space-2);
            text-align: right;
            border-top: 1px solid var(--gray-200);
        }

        .card
        {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            border: 1px solid var(--gray-100);
        }

        .card-header
        {
            background: linear-gradient(135deg, var(--orange), var(--orange-dark));
            padding: var(--space-4) var(--space-6);
        }

        .card-header h3
        {
            margin: 0;
            color: white;
        }

        .card-body
        {
            padding: var(--space-5) var(--space-6);
        }

        .required { color: var(--error); }

        .receipt-container
        {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            border: 1px solid var(--gray-100);
            max-width: 700px;
            margin: 0 auto;
        }

        .receipt-header
        {
            background: linear-gradient(135deg, var(--success), var(--success-text));
            color: white;
            padding: var(--space-6);
            text-align: center;
        }

        .receipt-header i { font-size: 3rem; margin-bottom: var(--space-3); }
        .receipt-header h1 { color: white; margin-bottom: var(--space-2); }
        .receipt-header p { opacity: 0.9; margin: 0; }

        .receipt-body { padding: var(--space-6); }

        .receipt-item-row
        {
            display: flex;
            justify-content: space-between;
            padding: var(--space-2) 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .receipt-item-row:last-child { border-bottom: none; }

        .receipt-total
        {
            font-weight: 700;
            font-size: 1.125rem;
            color: var(--orange);
            border-top: 2px solid var(--orange-light);
            margin-top: var(--space-3);
            padding-top: var(--space-3);
        }

        .receipt-actions
        {
            padding: var(--space-5) var(--space-6);
            border-top: 1px solid var(--gray-200);
            display: flex;
            gap: var(--space-3);
            justify-content: center;
            flex-wrap: wrap;
        }

        .discount-badge
        {
            display: inline-block;
            background: var(--success-bg);
            color: var(--success-text);
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: var(--space-2);
        }

        @media (max-width: 768px)
        {
            .checkout-grid
            {
                grid-template-columns: 1fr;
                gap: var(--space-4);
            }

            .receipt-container { max-width: 100%; }
        }

        @media (max-width: 480px)
        {
            .order-summary-header { padding: var(--space-3) var(--space-4); }
            .card-header { padding: var(--space-3) var(--space-4); }
            .summary-row { font-size: 0.875rem; }
            .total-row { font-size: 1.125rem; }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/student_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="student-content">
                <div class="container">
                    <?php if ($orderPlaced && $orderReceipt): ?>
                        <div class="receipt-container">
                            <div class="receipt-header">
                                <i class="fas fa-check-circle"></i>
                                <h1>Order Confirmed!</h1>
                                <p>Thank you for your order, <?php echo checkoutEscape($currentUser['full_name']); ?></p>
                            </div>
                            <div class="receipt-body">
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-4); margin-bottom: var(--space-4);">
                                    <div>
                                        <span style="font-size: 0.75rem; color: var(--gray-500);">Order Number</span>
                                        <p style="font-weight: 600;"><?php echo checkoutEscape($orderReceipt['order_number']); ?></p>
                                    </div>
                                    <div>
                                        <span style="font-size: 0.75rem; color: var(--gray-500);">Order Status</span>
                                        <p style="font-weight: 600;"><span class="badge status-pending">Pending</span></p>
                                    </div>
                                    <div>
                                        <span style="font-size: 0.75rem; color: var(--gray-500);">Vendor</span>
                                        <p style="font-weight: 600;"><?php echo checkoutEscape($orderReceipt['vendor_name']); ?></p>
                                    </div>
                                    <div>
                                        <span style="font-size: 0.75rem; color: var(--gray-500);">Pickup Time</span>
                                        <p style="font-weight: 600;"><?php echo checkoutEscape($orderReceipt['pickup_time']); ?></p>
                                    </div>
                                    <div>
                                        <span style="font-size: 0.75rem; color: var(--gray-500);">Order Placed</span>
                                        <p style="font-weight: 600;"><?php echo date('M j, Y g:i A', strtotime($orderReceipt['order_placed_at'])); ?></p>
                                    </div>
                                    <?php if ($isStudent && $studentDiscount > 0): ?>
                                    <div>
                                        <span style="font-size: 0.75rem; color: var(--gray-500);">Discount Applied</span>
                                        <p style="font-weight: 600;"><span class="discount-badge"><i class="fas fa-graduation-cap"></i> Student Discount (2.5%)</span></p>
                                    </div>
                                    <?php endif; ?>
                                </div>

                                <h4 style="margin-bottom: var(--space-3);">Order Items</h4>
                                <?php foreach ($orderItems as $item): ?>
                                    <div class="receipt-item-row">
                                        <span><?php echo $item['quantity']; ?>x <?php echo checkoutEscape($item['item_name']); ?></span>
                                        <span>R <?php echo number_format($item['subtotal'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>

                                <div style="margin-top: var(--space-4); padding-top: var(--space-4); border-top: 1px solid var(--gray-200);">
                                    <div class="summary-row">
                                        <span>Subtotal:</span>
                                        <span>R <?php echo number_format($orderReceipt['subtotal'] ?? 0, 2); ?></span>
                                    </div>
                                    <?php if (isset($orderReceipt['service_fee']) && $orderReceipt['service_fee'] > 0): ?>
                                        <div class="summary-row service-fee-row">
                                            <span>Service Fee:</span>
                                            <span>R <?php echo number_format($orderReceipt['service_fee'], 2); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($orderReceipt['tax']) && $orderReceipt['tax'] > 0): ?>
                                        <div class="summary-row tax-row">
                                            <span>Tax (20%):</span>
                                            <span>R <?php echo number_format($orderReceipt['tax'], 2); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (isset($orderReceipt['rounding_adjustment']) && $orderReceipt['rounding_adjustment'] != 0): ?>
                                        <div class="summary-row rounding-row">
                                            <span>Rounding Adjustment:</span>
                                            <span>R <?php echo number_format($orderReceipt['rounding_adjustment'], 2); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($isStudent && ($studentDiscount > 0)): ?>
                                        <div class="summary-row discount-row">
                                            <span><i class="fas fa-graduation-cap"></i> Student Discount (2.5%):</span>
                                            <span>- R <?php echo number_format($studentDiscount, 2); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="summary-row receipt-total">
                                        <span>Total Paid:</span>
                                        <span>R <?php echo number_format($orderReceipt['total_amount'], 2); ?></span>
                                    </div>
                                    <?php if ($isStudent && $studentDiscount > 0): ?>
                                        <div style="margin-top: var(--space-2); padding: var(--space-2) var(--space-3); background: var(--success-bg); border-radius: var(--radius-md); text-align: center;">
                                            <span style="font-size: 0.75rem; color: var(--success-text);">
                                                <i class="fas fa-graduation-cap"></i> You saved R <?php echo number_format($studentDiscount, 2); ?> with your student discount!
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($orderReceipt['special_requests'])): ?>
                                    <div style="margin-top: var(--space-4); padding: var(--space-3); background: var(--gray-50); border-radius: var(--radius-md);">
                                        <strong><i class="fas fa-comment-dots"></i> Special Requests:</strong>
                                        <p style="margin-top: var(--space-2);"><?php echo nl2br(checkoutEscape($orderReceipt['special_requests'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="receipt-actions">
                                <a href="order_tracking.php?order_id=<?php echo $orderReceipt['order_id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-truck"></i> Track Order
                                </a>
                                <a href="dashboard.php" class="btn btn-outline">
                                    <i class="fas fa-store"></i> Browse More Vendors
                                </a>
                            </div>
                        </div>

                    <?php elseif ($errorMessage): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo checkoutEscape($errorMessage); ?></div>
                            </div>
                        </div>
                        <div style="text-align: center; margin-top: var(--space-4);">
                            <a href="cart.php" class="btn btn-primary"><i class="fas fa-arrow-left"></i> Return to Cart</a>
                        </div>

                    <?php else: ?>
                        <div class="page-header">
                            <h1>Checkout</h1>
                            <p>Complete your order</p>
                        </div>

                        <div class="checkout-grid">
                            <div class="order-summary">
                                <div class="order-summary-header">
                                    <h3><i class="fas fa-receipt"></i> Order Summary</h3>
                                </div>
                                <div class="order-summary-body">
                                    <p style="color: var(--gray-600); margin-bottom: var(--space-4);">
                                        <i class="fas fa-store"></i>
                                        <?php echo checkoutEscape($vendorName); ?>
                                    </p>

                                    <?php foreach ($items as $index => $item): ?>
                                        <?php
                                        $itemName = isset($item['name']) ? $item['name'] : (isset($item['item_name']) ? $item['item_name'] : 'Item');
                                        $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                                        $price = isset($item['price']) ? (float)$item['price'] : 0.0;
                                        $itemSubtotal = $price * $quantity;
                                        ?>
                                        <div class="order-item-row">
                                            <span><?php echo checkoutEscape($itemName); ?> × <?php echo $quantity; ?></span>
                                            <span>R <?php echo number_format($itemSubtotal, 2); ?></span>
                                        </div>
                                    <?php endforeach; ?>

                                    <div class="summary-breakdown">
                                        <div class="summary-row">
                                            <span>Subtotal:</span>
                                            <span>R <?php echo number_format($subtotal, 2); ?></span>
                                        </div>
                                        <?php if ($serviceFee > 0): ?>
                                            <div class="summary-row service-fee-row">
                                                <span>Service Fee (<?php echo $subtotal < 500 ? '10%' : '6.5%'; ?>):</span>
                                                <span>R <?php echo number_format($serviceFee, 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="summary-row tax-row">
                                            <span>Tax (20%):</span>
                                            <span>R <?php echo number_format($tax, 2); ?></span>
                                        </div>
                                        <?php if ($roundingAdjustment > 0): ?>
                                            <div class="summary-row rounding-row">
                                                <span>Rounding Adjustment:</span>
                                                <span>R <?php echo number_format($roundingAdjustment, 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($isStudent && $studentDiscount > 0): ?>
                                            <div class="summary-row discount-row">
                                                <span><i class="fas fa-graduation-cap"></i> Student Discount (2.5%):</span>
                                                <span>- R <?php echo number_format($studentDiscount, 2); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="summary-row total-row">
                                            <span>Total:</span>
                                            <span>R <?php echo number_format($finalTotal, 2); ?></span>
                                        </div>
                                        <?php if ($isStudent): ?>
                                            <div class="fee-note" style="color: var(--success); border-top-color: var(--success);">
                                                <i class="fas fa-graduation-cap"></i>
                                                Student discount of 2.5% applied to your order!
                                            </div>
                                        <?php else: ?>
                                            <div class="fee-note">
                                                <i class="fas fa-info-circle"></i>
                                                <?php echo $feeMessage; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-header">
                                    <h3><i class="fas fa-credit-card"></i> Payment Details</h3>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="" id="checkout-form">
                                        <input type="hidden" name="csrf_token" value="<?php echo checkoutEscape($csrfToken); ?>">
                                        <input type="hidden" name="place_order" value="1">

                                        <div class="form-group">
                                            <label class="form-label" for="pickup_time">Preferred Pickup Time <span class="required">*</span></label>
                                            <select id="pickup_time" name="pickup_time" class="form-control" required>
                                                <option value="">Select a time</option>
                                                <?php foreach ($pickupTimes as $time): ?>
                                                    <option value="<?php echo $time['value']; ?>">
                                                        <?php echo $time['label']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <span class="form-hint">Pickup available from 10:00 to 16:00 (15-minute intervals)</span>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="payment_method">Payment Method <span class="required">*</span></label>
                                            <select id="payment_method" name="payment_method" class="form-control" required>
                                                <option value="">Select payment method</option>
                                                <option value="debit_card">Debit Card</option>
                                                <option value="campus_wallet">Campus Wallet</option>
                                                <option value="coupons">Coupons</option>
                                            </select>
                                        </div>

                                        <div class="form-group">
                                            <label class="form-label" for="special_requests">Special Requests (Optional)</label>
                                            <textarea id="special_requests" name="special_requests" class="form-control" rows="3" placeholder="e.g., no onions, extra sauce"></textarea>
                                        </div>

                                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                                            <i class="fas fa-credit-card"></i> Place Order (R <?php echo number_format($finalTotal, 2); ?>)
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <button class="sidebar-toggle" id="menuToggleBtn" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <script src="<?php echo ASSETS_URL; ?>/js/dashboard-common.js"></script>
</body>
</html>