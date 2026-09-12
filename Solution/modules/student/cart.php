<?php
/**
 * Shopping Cart Page for Students and Standard Users
 *
 * This page displays the user's shopping cart and allows modification.
 * Matches mockup designs for the cart page.
 *
 * SOURCE: Mockups - Cart design
 *
 * @version 26.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

// Start secure session and require student or standard role
startSecureSession();
requireStudentOrStandard();

// Log the cart page access for debugging
$userId = getCurrentUserId();
$userRole = getCurrentUserRole();
writeLog("Cart page accessed by user ID: $userId (Role: $userRole)", "CART");

// Get database connection and current user
$db = getDB();
$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();

// =============================================================================
// Get Cart from Session
// =============================================================================

$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
writeLog("Cart loaded with " . count($cart) . " items for user ID: " . $userId, "CART");

// =============================================================================
// Validate Cart Items Against Database
// =============================================================================

$validatedCart = array();
$subtotal = 0.0;
$validationErrors = array();

if (!empty($cart))
{
    try
    {
        $itemIds = array();
        foreach ($cart as $item)
        {
            if (isset($item['item_id']))
            {
                $itemIds[] = (int)$item['item_id'];
            }
        }

        if (!empty($itemIds))
        {
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            $sql = "SELECT item_id, item_name, price, is_available, quantity_available, vendor_id
                    FROM menu_items
                    WHERE item_id IN ($placeholders)";

            $stmt = $db->getConnection()->prepare($sql);
            foreach ($itemIds as $index => $itemId)
            {
                $stmt->bindValue($index + 1, $itemId, PDO::PARAM_INT);
            }
            $stmt->execute();
            $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $itemData = array();
            foreach ($menuItems as $menuItem)
            {
                $itemData[$menuItem['item_id']] = $menuItem;
            }

            foreach ($cart as $index => $cartItem)
            {
                $itemId = isset($cartItem['item_id']) ? (int)$cartItem['item_id'] : 0;

                if ($itemId > 0 && isset($itemData[$itemId]))
                {
                    $currentData = $itemData[$itemId];

                    if ($currentData['is_available'] != 1)
                    {
                        $validationErrors[] = "Item '" . $currentData['item_name'] . "' is no longer available and has been removed from your cart.";
                        continue;
                    }

                    $requestedQuantity = isset($cartItem['quantity']) ? (int)$cartItem['quantity'] : 1;
                    $availableStock = (int)$currentData['quantity_available'];

                    if ($availableStock <= 0)
                    {
                        $validationErrors[] = "Item '" . $currentData['item_name'] . "' is out of stock and has been removed from your cart.";
                        continue;
                    }

                    if ($requestedQuantity > $availableStock)
                    {
                        $cartItem['quantity'] = $availableStock;
                        $validationErrors[] = "Quantity for '" . $currentData['item_name'] . "' has been reduced to $availableStock due to limited stock.";
                    }

                    $validatedItem = $cartItem;
                    $validatedItem['name'] = $currentData['item_name'];
                    $validatedItem['price'] = (float)$currentData['price'];
                    $validatedItem['max_quantity'] = $availableStock;
                    $validatedItem['vendor_id'] = (int)$currentData['vendor_id'];

                    $validatedCart[] = $validatedItem;
                    $itemSubtotal = $validatedItem['price'] * $validatedItem['quantity'];
                    $subtotal += $itemSubtotal;
                }
                else
                {
                    $validationErrors[] = "An item in your cart is no longer available and has been removed.";
                }
            }

            if (count($validatedCart) !== count($cart))
            {
                $_SESSION['cart'] = $validatedCart;
                writeLog("Cart updated after validation: " . count($validatedCart) . " items", "CART");
            }
        }
        else
        {
            $_SESSION['cart'] = array();
            writeLog("Cart cleared: No valid item IDs found", "CART");
        }
    }
    catch (Exception $e)
    {
        writeLog("Cart validation error: " . $e->getMessage(), "CART");
        $validatedCart = $cart;
        foreach ($validatedCart as $item)
        {
            $price = isset($item['price']) ? (float)$item['price'] : 0.0;
            $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
            $subtotal += $price * $quantity;
        }
    }
}
else
{
    $validatedCart = array();
}

$cart = $validatedCart;

// Calculate service fee
$serviceFee = 0.0;
if ($subtotal < 500)
{
    $serviceFee = round($subtotal * 0.10, 2);
}
elseif ($subtotal >= 500 && $subtotal <= 1000)
{
    $serviceFee = round($subtotal * 0.065, 2);
}

$amountBeforeTax = $subtotal + $serviceFee;
$tax = round($amountBeforeTax * 0.20, 2);
$amountBeforeRounding = $amountBeforeTax + $tax;
$finalTotal = ceil($amountBeforeRounding / 5) * 5;
$roundingAdjustment = round($finalTotal - $amountBeforeRounding, 2);

$transactionId = 'TD' . gmdate('YmdHis');

// Helper function
function cartEscape($string)
{
    if ($string === null) return '';
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

$isCartEmpty = empty($cart);
$isStudent = isStudent();

// Checkout handling
$checkoutError = null;
if (isset($_POST['checkout']))
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($submittedToken, true))
    {
        $checkoutError = 'Security validation failed. Please refresh the page and try again.';
    }
    elseif ($isCartEmpty)
    {
        $checkoutError = 'Your cart is empty. Please add items before checking out.';
    }
    else
    {
        $vendorId = isset($cart[0]['vendor_id']) ? (int)$cart[0]['vendor_id'] : 0;
        if ($vendorId > 0)
        {
            $vendor = $db->fetchOne(
                "SELECT vendor_id, vendor_name, is_open, is_approved
                 FROM vendors WHERE vendor_id = :vendor_id AND is_approved = 1",
                array('vendor_id' => $vendorId)
            );

            if (!$vendor)
            {
                $checkoutError = 'Vendor not found or not approved.';
            }
            elseif ($vendor['is_open'] != 1)
            {
                $checkoutError = 'Vendor is currently closed. Please try again later.';
            }
            else
            {
                $_SESSION['checkout_data'] = array(
                    'vendor' => $vendor,
                    'items' => $cart,
                    'subtotal' => $subtotal
                );
                header('Location: checkout.php');
                exit();
            }
        }
        else
        {
            $checkoutError = 'Invalid vendor in cart. Please refresh your cart.';
        }
    }
    $csrfToken = getCsrfToken();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo cartEscape($csrfToken); ?>">
    <title>My Cart · Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/cart.css">
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/student_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="student-content">
                <div class="container">
                    <div class="page-header">
                        <h1>My Cart</h1>
                        <p>Review your items before checkout</p>
                    </div>

                    <?php if ($checkoutError !== null): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Checkout Error</div>
                                <div class="alert-message"><?php echo cartEscape($checkoutError); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($validationErrors)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Cart Updated</div>
                                <div class="alert-message"><?php echo implode(' ', $validationErrors); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="cart-container" id="cart-container">
                        <?php if ($isCartEmpty): ?>
                            <div class="empty-state">
                                <i class="fas fa-shopping-cart"></i>
                                <h3>Your Cart is Empty</h3>
                                <p>Add some delicious items from our vendors!</p>
                                <a href="dashboard.php" class="btn btn-primary">Browse Vendors</a>
                            </div>
                        <?php else: ?>
                            <div class="cart-header">
                                <span>Item</span>
                                <span>Price</span>
                                <span>Quantity</span>
                                <span>Subtotal</span>
                                <span></span>
                            </div>

                            <?php foreach ($cart as $index => $item): ?>
                                <?php
                                $itemName = isset($item['name']) ? $item['name'] : (isset($item['item_name']) ? $item['item_name'] : 'Item');
                                $quantity = isset($item['quantity']) ? (int)$item['quantity'] : 1;
                                $price = isset($item['price']) ? (float)$item['price'] : 0.0;
                                $itemSubtotal = $price * $quantity;
                                $maxQuantity = isset($item['max_quantity']) ? (int)$item['max_quantity'] : 999;
                                $vendorName = isset($item['vendor_name']) ? $item['vendor_name'] : 'Vendor';
                                ?>
                                <div class="cart-item-row" data-index="<?php echo $index; ?>">
                                    <div class="cart-item-name">
                                        <strong><?php echo cartEscape($itemName); ?></strong>
                                        <span class="vendor-name"><?php echo cartEscape($vendorName); ?></span>
                                    </div>
                                    <div class="cart-item-price">R <?php echo number_format($price, 2); ?></div>
                                    <div class="cart-item-quantity">
                                        <button class="btn btn-outline btn-sm decrement-btn" data-index="<?php echo $index; ?>" type="button">
                                            <i class="fas fa-minus"></i>
                                        </button>
                                        <input type="number" class="quantity-input" data-index="<?php echo $index; ?>"
                                               value="<?php echo $quantity; ?>" min="1" max="<?php echo $maxQuantity; ?>"
                                               aria-label="Quantity for <?php echo cartEscape($itemName); ?>" step="1">
                                        <button class="btn btn-outline btn-sm increment-btn" data-index="<?php echo $index; ?>" type="button">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <div class="cart-item-subtotal" id="subtotal-<?php echo $index; ?>">R <?php echo number_format($itemSubtotal, 2); ?></div>
                                    <div>
                                        <button class="delete-btn remove-btn" data-index="<?php echo $index; ?>" type="button">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>

                            <div class="cart-summary" id="cart-summary">
                                <div class="summary-row">
                                    <span>Subtotal:</span>
                                    <span id="summary-subtotal">R <?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <?php if ($serviceFee > 0): ?>
                                    <div class="summary-row service-fee-row">
                                        <span>Service Fee (<?php echo $subtotal < 500 ? '10%' : '6.5%'; ?>):</span>
                                        <span id="summary-service-fee">R <?php echo number_format($serviceFee, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($isStudent && $subtotal > 0): ?>
                                    <div class="summary-row" style="color: #34C759;">
                                        <span><i class="fas fa-graduation-cap"></i> Student Discount (2.5%):</span>
                                        <span id="summary-discount">- R <?php echo number_format(($subtotal + $serviceFee) * 0.025, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="summary-row tax-row">
                                    <span>Tax (20%):</span>
                                    <span id="summary-tax">R <?php echo number_format($tax, 2); ?></span>
                                </div>
                                <?php if ($roundingAdjustment > 0): ?>
                                    <div class="summary-row rounding-row">
                                        <span>Rounding Adjustment:</span>
                                        <span id="summary-rounding">R <?php echo number_format($roundingAdjustment, 2); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div class="summary-row summary-total">
                                    <span>Total:</span>
                                    <span id="summary-total">R <?php echo number_format($finalTotal, 2); ?></span>
                                </div>
                                <?php if ($isStudent && $subtotal > 0): ?>
                                    <div class="fee-note" style="color: #34C759;">
                                        <i class="fas fa-graduation-cap"></i>
                                        Student discount of 2.5% will be applied at checkout!
                                    </div>
                                <?php else: ?>
                                    <div class="fee-note">
                                        <i class="fas fa-info-circle"></i>
                                        <?php echo $subtotal < 500 ? '10% service fee applied to orders under R500' : ($subtotal >= 500 && $subtotal <= 1000 ? '6.5% service fee applied to orders between R500 and R1000' : 'No service fee for orders over R1000'); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="transaction-id-display">
                                    <i class="fas fa-hashtag"></i>
                                    Transaction ID: <?php echo cartEscape($transactionId); ?>
                                </div>
                                <div class="checkout-area">
                                    <form method="POST" action="" id="checkout-form" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo cartEscape($csrfToken); ?>">
                                        <input type="hidden" name="checkout" value="1">
                                        <button type="submit" class="checkout-btn" id="proceed-to-checkout-btn">
                                            <i class="fas fa-credit-card"></i>
                                            Proceed to Checkout (R <?php echo number_format($finalTotal, 2); ?>)
                                        </button>
                                    </form>
                                    <button class="clear-cart-btn" type="button">
                                        <i class="fas fa-trash-alt"></i> Clear Cart
                                    </button>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <button class="sidebar-toggle" id="menuToggleBtn" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <script src="<?php echo ASSETS_URL; ?>/js/cart.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dashboard-common.js"></script>
</body>
</html>