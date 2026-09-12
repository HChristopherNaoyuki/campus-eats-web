<?php
/**
 * Menu Management Page for Vendors
 *
 * This page allows vendors to manage their menu items.
 *
 * SOURCE: campus-eats-process-document.pdf (Section 6.2 - Menu management)
 * SOURCE: Mockup - 25.png
 *
 * @version 16.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

// Start secure session and require verified vendor
startSecureSession();
requireVendorVerified();

// Get database connection and current user
$db = getDB();
$currentUser = getCurrentUser();

// Get vendor information
$vendor = $db->fetchOne
(
    "SELECT vendor_id, vendor_name, is_open, is_approved
     FROM vendors WHERE vendor_user_id = :user_id",
    array('user_id' => $currentUser['user_id'])
);

if (!$vendor)
{
    writeLog("Vendor menu: No vendor profile found for user ID " . $currentUser['user_id'], "VENDOR");
    header('Location: ' . BASE_URL . '/modules/auth/logout.php');
    exit();
}

$_SESSION['vendor_id'] = $vendor['vendor_id'];

$message = '';
$error = '';
$csrfToken = getCsrfToken();

// Handle POST form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($submittedToken))
    {
        $error = 'Security validation failed. Please refresh the page and try again.';
        writeLog("Vendor menu CSRF validation failed for vendor ID: {$vendor['vendor_id']}", "VENDOR");
    }
    else
    {
        $action = $_POST['action'] ?? '';

        // Add Menu Item
        if ($action === 'add')
        {
            $itemName = trim($_POST['item_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $quantityAvailable = (int)($_POST['quantity_available'] ?? 0);
            $category = trim($_POST['category'] ?? 'General');
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;

            if (empty($itemName))
            {
                $error = 'Item name is required.';
            }
            elseif ($price <= 0)
            {
                $error = 'Price must be greater than 0.';
            }
            elseif ($quantityAvailable < 0)
            {
                $error = 'Quantity available cannot be negative.';
            }
            else
            {
                try
                {
                    $sql = "INSERT INTO menu_items
                            (vendor_id, item_name, description, price, quantity_available, category, is_available)
                            VALUES
                            (:vendor_id, :item_name, :description, :price, :quantity_available, :category, :is_available)";

                    $result = $db->insert
                    (
                        $sql,
                        array(
                            'vendor_id' => $vendor['vendor_id'],
                            'item_name' => $itemName,
                            'description' => $description,
                            'price' => $price,
                            'quantity_available' => $quantityAvailable,
                            'category' => $category,
                            'is_available' => $isAvailable
                        )
                    );

                    if ($result)
                    {
                        writeLog("Vendor ID {$vendor['vendor_id']} added menu item: $itemName (ID: $result)", "VENDOR");
                        $_SESSION['flash_message'] = 'Menu item added successfully.';
                        $_SESSION['flash_type'] = 'success';
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit();
                    }
                    else
                    {
                        $error = 'Failed to add menu item. Please try again.';
                    }
                }
                catch (Exception $e)
                {
                    $error = 'Database error occurred. Please try again.';
                    writeLog("Add menu item exception: " . $e->getMessage(), "VENDOR");
                }
            }
        }
        // Edit Menu Item
        elseif ($action === 'edit')
        {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $itemName = trim($_POST['item_name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = (float)($_POST['price'] ?? 0);
            $quantityAvailable = (int)($_POST['quantity_available'] ?? 0);
            $category = trim($_POST['category'] ?? 'General');
            $isAvailable = isset($_POST['is_available']) ? 1 : 0;

            if ($itemId <= 0)
            {
                $error = 'Invalid item ID.';
            }
            elseif (empty($itemName))
            {
                $error = 'Item name is required.';
            }
            elseif ($price <= 0)
            {
                $error = 'Price must be greater than 0.';
            }
            elseif ($quantityAvailable < 0)
            {
                $error = 'Quantity available cannot be negative.';
            }
            else
            {
                $verify = $db->fetchOne
                (
                    "SELECT item_id FROM menu_items WHERE item_id = :item_id AND vendor_id = :vendor_id",
                    array('item_id' => $itemId, 'vendor_id' => $vendor['vendor_id'])
                );

                if (!$verify)
                {
                    $error = 'Invalid menu item or access denied.';
                }
                else
                {
                    $sql = "UPDATE menu_items
                            SET item_name = :item_name, description = :description,
                                price = :price, quantity_available = :quantity_available,
                                category = :category, is_available = :is_available
                            WHERE item_id = :item_id AND vendor_id = :vendor_id";

                    $db->executeQuery
                    (
                        $sql,
                        array(
                            'item_id' => $itemId,
                            'vendor_id' => $vendor['vendor_id'],
                            'item_name' => $itemName,
                            'description' => $description,
                            'price' => $price,
                            'quantity_available' => $quantityAvailable,
                            'category' => $category,
                            'is_available' => $isAvailable
                        )
                    );

                    writeLog("Vendor ID {$vendor['vendor_id']} updated menu item ID: $itemId", "VENDOR");
                    $_SESSION['flash_message'] = 'Menu item updated successfully.';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
            }
        }
        // Delete Menu Item
        elseif ($action === 'delete')
        {
            $itemId = (int)($_POST['item_id'] ?? 0);

            if ($itemId <= 0)
            {
                $error = 'Invalid item ID.';
            }
            else
            {
                $hasOrders = $db->fetchOne
                (
                    "SELECT COUNT(*) as count FROM order_items WHERE item_id = :item_id",
                    array('item_id' => $itemId)
                );

                if ($hasOrders && $hasOrders['count'] > 0)
                {
                    $error = 'Cannot delete item that has been ordered. Mark as unavailable instead.';
                }
                else
                {
                    $verify = $db->fetchOne
                    (
                        "SELECT item_id FROM menu_items WHERE item_id = :item_id AND vendor_id = :vendor_id",
                        array('item_id' => $itemId, 'vendor_id' => $vendor['vendor_id'])
                    );

                    if (!$verify)
                    {
                        $error = 'Invalid menu item or access denied.';
                    }
                    else
                    {
                        $sql = "DELETE FROM menu_items WHERE item_id = :item_id AND vendor_id = :vendor_id";
                        $db->executeQuery
                        (
                            $sql,
                            array(
                                'item_id' => $itemId,
                                'vendor_id' => $vendor['vendor_id']
                            )
                        );

                        writeLog("Vendor ID {$vendor['vendor_id']} deleted menu item ID: $itemId", "VENDOR");
                        $_SESSION['flash_message'] = 'Menu item deleted successfully.';
                        $_SESSION['flash_type'] = 'success';
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit();
                    }
                }
            }
        }
        // Toggle Availability
        elseif ($action === 'toggle')
        {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $currentStatus = (int)($_POST['current_status'] ?? 1);
            $newStatus = $currentStatus ? 0 : 1;

            if ($itemId <= 0)
            {
                $error = 'Invalid item ID.';
            }
            else
            {
                $verify = $db->fetchOne
                (
                    "SELECT item_id FROM menu_items WHERE item_id = :item_id AND vendor_id = :vendor_id",
                    array('item_id' => $itemId, 'vendor_id' => $vendor['vendor_id'])
                );

                if (!$verify)
                {
                    $error = 'Invalid menu item or access denied.';
                }
                else
                {
                    $sql = "UPDATE menu_items SET is_available = :is_available
                            WHERE item_id = :item_id AND vendor_id = :vendor_id";

                    $db->executeQuery
                    (
                        $sql,
                        array(
                            'is_available' => $newStatus,
                            'item_id' => $itemId,
                            'vendor_id' => $vendor['vendor_id']
                        )
                    );

                    writeLog("Vendor ID {$vendor['vendor_id']} toggled item ID $itemId to " . ($newStatus ? 'available' : 'unavailable'), "VENDOR");
                    $_SESSION['flash_message'] = 'Item availability updated.';
                    $_SESSION['flash_type'] = 'success';
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit();
                }
            }
        }
    }

    $csrfToken = getCsrfToken();
}

// Check for flash messages
if (isset($_SESSION['flash_message']))
{
    $message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']);
}
if (isset($_SESSION['flash_type']))
{
    $messageType = $_SESSION['flash_type'];
    unset($_SESSION['flash_type']);
}

// Fetch menu items
try
{
    $menuItems = $db->fetchAll
    (
        "SELECT item_id, item_name, description, price, quantity_available, is_available, category, created_at
         FROM menu_items
         WHERE vendor_id = :vendor_id
         ORDER BY category, item_name",
        array('vendor_id' => $vendor['vendor_id'])
    );
}
catch (Exception $e)
{
    writeLog("Error fetching menu items: " . $e->getMessage(), "VENDOR");
    $error = "Unable to load menu items. Please contact support.";
    $menuItems = array();
}

// Group items by category
$categorizedItems = array();
foreach ($menuItems as $item)
{
    $category = $item['category'] ?: 'Uncategorized';
    if (!isset($categorizedItems[$category]))
    {
        $categorizedItems[$category] = array();
    }
    $categorizedItems[$category][] = $item;
}

// Check if editing an existing item
$editItem = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit']))
{
    try
    {
        $editItem = $db->fetchOne
        (
            "SELECT * FROM menu_items WHERE item_id = :item_id AND vendor_id = :vendor_id",
            array('item_id' => (int)$_GET['edit'], 'vendor_id' => $vendor['vendor_id'])
        );
    }
    catch (Exception $e)
    {
        writeLog("Error fetching edit item: " . $e->getMessage(), "VENDOR");
    }
}

function escapeVendorMenu($string)
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
    <title>Manage Menu · Vendor Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <style>
        .add-item-form
        {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: var(--space-5);
            align-items: start;
        }

        .add-item-form .form-group.full-width { grid-column: 1 / -1; }
        .add-item-form .form-group { margin-bottom: 0; }

        .add-item-form .form-control
        {
            width: 100%;
            padding: var(--space-3) var(--space-4);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            transition: all var(--transition-fast);
        }

        .add-item-form .form-control:focus
        {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px rgba(255, 149, 0, 0.1);
            outline: none;
        }

        .add-item-form textarea.form-control { resize: vertical; min-height: 80px; }

        .checkbox-label
        {
            display: flex;
            align-items: center;
            gap: var(--space-2);
            cursor: pointer;
            padding: var(--space-2) 0;
        }

        .menu-category-section { margin-bottom: var(--space-8); }

        .category-title
        {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: var(--space-4);
            padding-bottom: var(--space-2);
            border-bottom: 2px solid var(--orange);
            display: inline-block;
            color: var(--gray-800);
        }

        .category-title i
        {
            color: var(--orange);
            margin-right: var(--space-2);
        }

        .table-container
        {
            background: white;
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            overflow-x: auto;
        }

        .menu-items-table
        {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        .menu-items-table th
        {
            background: var(--gray-50);
            padding: var(--space-3) var(--space-4);
            text-align: left;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            color: var(--gray-600);
            border-bottom: 1px solid var(--gray-200);
        }

        .menu-items-table td
        {
            padding: var(--space-3) var(--space-4);
            border-bottom: 1px solid var(--gray-100);
            vertical-align: middle;
        }

        .menu-items-table tr:last-child td { border-bottom: none; }
        .menu-items-table tr:hover td { background: var(--gray-50); }

        .quantity-available
        {
            font-size: 0.8125rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: var(--space-2);
        }

        .quantity-available.in-stock { color: var(--success); }
        .quantity-available.low-stock { color: var(--warning); }
        .quantity-available.out-of-stock { color: var(--error); }

        .status-toggle-btn
        {
            border: none;
            padding: var(--space-1) var(--space-3);
            border-radius: var(--radius-full);
            cursor: pointer;
            font-size: 0.6875rem;
            font-weight: 500;
            transition: all var(--transition-fast);
        }

        .status-available { background: var(--success-bg); color: var(--success-text); }
        .status-available:hover { background: var(--success); color: white; }

        .status-unavailable { background: var(--error-bg); color: var(--error-text); }
        .status-unavailable:hover { background: var(--error); color: white; }

        .action-buttons
        {
            display: flex;
            gap: var(--space-2);
            flex-wrap: wrap;
        }

        .action-btn
        {
            background: none;
            border: none;
            cursor: pointer;
            padding: var(--space-2);
            border-radius: var(--radius-sm);
            transition: all var(--transition-fast);
            font-size: 1rem;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .edit-btn { color: var(--orange); }
        .edit-btn:hover { background: var(--orange-light); transform: scale(1.05); }

        .delete-btn { color: var(--error); }
        .delete-btn:hover { background: var(--error-bg); transform: scale(1.05); }

        .modal
        {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: var(--z-modal);
            align-items: center;
            justify-content: center;
            padding: var(--space-4);
        }

        .modal.active { display: flex; }

        .modal-content
        {
            background: white;
            border-radius: var(--radius-xl);
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
        }

        .modal-header
        {
            padding: var(--space-4) var(--space-6);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--orange);
            color: white;
        }

        .modal-header h3 { margin: 0; color: white; }

        .modal-close
        {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: white;
            transition: opacity var(--transition-fast);
        }

        .modal-close:hover { opacity: 0.8; }

        .modal-body { padding: var(--space-5) var(--space-6); }
        .modal-footer { padding: var(--space-3) var(--space-6); border-top: 1px solid var(--gray-200); display: flex; justify-content: flex-end; gap: var(--space-3); }

        .required { color: var(--error); }

        @media (max-width: 1024px)
        {
            .add-item-form { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px)
        {
            .add-item-form { grid-template-columns: 1fr; }

            .menu-items-table,
            .menu-items-table tbody,
            .menu-items-table tr,
            .menu-items-table td
            {
                display: block;
            }

            .menu-items-table thead { display: none; }

            .menu-items-table tr
            {
                margin-bottom: var(--space-4);
                padding: var(--space-3);
                border: 1px solid var(--gray-200);
                border-radius: var(--radius-lg);
            }

            .menu-items-table td
            {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: var(--space-2) var(--space-3);
                border-bottom: 1px solid var(--gray-100);
            }

            .menu-items-table td:last-child { border-bottom: none; }

            .menu-items-table td::before
            {
                content: attr(data-label);
                font-weight: 600;
                font-size: 0.75rem;
                color: var(--gray-500);
                margin-right: var(--space-3);
            }

            .modal-content { max-width: 95%; }
        }

        @media (max-width: 480px)
        {
            .category-title { font-size: 1rem; }
            .action-buttons { justify-content: flex-end; }
            .modal-body { padding: var(--space-3); }
        }
    </style>
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/vendor_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="vendor-content">
                <div class="container">
                    <div class="page-header">
                        <h1>Menu Management</h1>
                        <p>Add, edit, or remove items from your menu</p>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo isset($messageType) && $messageType === 'error' ? 'error' : 'success'; ?>">
                            <i class="fas <?php echo isset($messageType) && $messageType === 'error' ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                            <div class="alert-content">
                                <div class="alert-title"><?php echo isset($messageType) && $messageType === 'error' ? 'Error' : 'Success'; ?></div>
                                <div class="alert-message"><?php echo escapeVendorMenu($message); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeVendorMenu($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-plus-circle"></i> Add New Menu Item</h3>
                        </div>
                        <div class="dashboard-card-body">
                            <form method="POST" action="" class="add-item-form">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="add">

                                <div class="form-group full-width">
                                    <input type="text" name="item_name" class="form-control" placeholder="Item name" required maxlength="100">
                                </div>
                                <div class="form-group full-width">
                                    <textarea name="description" class="form-control" rows="2" placeholder="Item description (e.g., ingredients, preparation style)" maxlength="500"></textarea>
                                </div>
                                <div class="form-group">
                                    <input type="number" name="price" class="form-control" step="0.01" min="0.01" placeholder="Price (R)" required>
                                </div>
                                <div class="form-group">
                                    <input type="number" name="quantity_available" class="form-control" min="0" placeholder="Quantity available" value="0">
                                </div>
                                <div class="form-group">
                                    <select name="category" class="form-control">
                                        <option value="General">Select Category</option>
                                        <option value="Burgers">Burgers</option>
                                        <option value="Sandwiches">Sandwiches</option>
                                        <option value="Pizza">Pizza</option>
                                        <option value="Pasta">Pasta</option>
                                        <option value="Salads">Salads</option>
                                        <option value="Sides">Sides</option>
                                        <option value="Beverages">Beverages</option>
                                        <option value="Desserts">Desserts</option>
                                        <option value="Breakfast">Breakfast</option>
                                        <option value="Lunch">Lunch</option>
                                        <option value="Dinner">Dinner</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="is_available" checked> Available for ordering
                                    </label>
                                </div>
                                <div class="form-group">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-plus"></i> Add Menu Item
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if (empty($menuItems)): ?>
                        <div class="empty-state">
                            <i class="fas fa-utensils"></i>
                            <h3>No Menu Items Yet</h3>
                            <p>You haven't added any menu items. Use the form above to create your first item.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($categorizedItems as $category => $items): ?>
                            <div class="menu-category-section">
                                <h3 class="category-title">
                                    <i class="fas fa-tag"></i>
                                    <?php echo escapeVendorMenu($category); ?>
                                </h3>
                                <div class="table-container">
                                    <table class="menu-items-table">
                                        <thead>
                                            <tr>
                                                <th>Item Name</th>
                                                <th>Description</th>
                                                <th>Price</th>
                                                <th>Stock</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($items as $item): ?>
                                                <?php
                                                $stockClass = '';
                                                $stockIcon = 'fa-boxes';
                                                $stockText = $item['quantity_available'] . ' left';

                                                if ($item['quantity_available'] <= 0)
                                                {
                                                    $stockClass = 'out-of-stock';
                                                    $stockIcon = 'fa-times-circle';
                                                    $stockText = 'Out of stock';
                                                }
                                                elseif ($item['quantity_available'] <= 5)
                                                {
                                                    $stockClass = 'low-stock';
                                                    $stockIcon = 'fa-exclamation-triangle';
                                                    $stockText = $item['quantity_available'] . ' left (low stock)';
                                                }
                                                else
                                                {
                                                    $stockClass = 'in-stock';
                                                    $stockIcon = 'fa-check-circle';
                                                }
                                                ?>
                                                <tr>
                                                    <td data-label="Item Name">
                                                        <strong><?php echo escapeVendorMenu($item['item_name']); ?></strong>
                                                        <br><small class="text-caption">ID: <?php echo $item['item_id']; ?></small>
                                                    </td>
                                                    <td data-label="Description">
                                                        <?php echo escapeVendorMenu(substr($item['description'] ?? '', 0, 80)); ?>
                                                        <?php if (strlen($item['description'] ?? '') > 80): ?>...<?php endif; ?>
                                                        <?php if (empty($item['description'])): ?>
                                                            <span class="text-caption">No description</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td data-label="Price">
                                                        <strong>R <?php echo number_format($item['price'], 2); ?></strong>
                                                    </td>
                                                    <td data-label="Stock">
                                                        <span class="quantity-available <?php echo $stockClass; ?>">
                                                            <i class="fas <?php echo $stockIcon; ?>"></i>
                                                            <?php echo $stockText; ?>
                                                        </span>
                                                    </td>
                                                    <td data-label="Status">
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="action" value="toggle">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                            <input type="hidden" name="current_status" value="<?php echo $item['is_available']; ?>">
                                                            <button type="submit" class="status-toggle-btn <?php echo $item['is_available'] ? 'status-available' : 'status-unavailable'; ?>">
                                                                <i class="fas <?php echo $item['is_available'] ? 'fa-check' : 'fa-ban'; ?>"></i>
                                                                <?php echo $item['is_available'] ? 'Available' : 'Unavailable'; ?>
                                                            </button>
                                                        </form>
                                                    </td>
                                                    <td data-label="Actions" class="action-buttons">
                                                        <a href="?edit=<?php echo $item['item_id']; ?>" class="action-btn edit-btn" title="Edit Item">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirmDelete(this, '<?php echo escapeVendorMenu(addslashes($item['item_name'])); ?>');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                                            <button type="submit" class="action-btn delete-btn" title="Delete Item">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div class="modal" id="edit-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Menu Item</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form method="POST" action="" id="edit-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="item_id" id="edit-item-id" value="">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label" for="edit-item-name">Item Name <span class="required">*</span></label>
                        <input type="text" id="edit-item-name" name="item_name" class="form-control" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-description">Description</label>
                        <textarea id="edit-description" name="description" class="form-control" rows="3" placeholder="Brief description of the item" maxlength="500"></textarea>
                        <span class="form-hint">Maximum 500 characters</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-price">Price (R) <span class="required">*</span></label>
                        <input type="number" id="edit-price" name="price" class="form-control" step="0.01" min="0.01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-quantity">Quantity Available</label>
                        <input type="number" id="edit-quantity" name="quantity_available" class="form-control" min="0" value="0">
                        <span class="form-hint">Set to 0 to mark as out of stock</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="edit-category">Category</label>
                        <select id="edit-category" name="category" class="form-control">
                            <option value="General">General</option>
                            <option value="Burgers">Burgers</option>
                            <option value="Sandwiches">Sandwiches</option>
                            <option value="Pizza">Pizza</option>
                            <option value="Pasta">Pasta</option>
                            <option value="Salads">Salads</option>
                            <option value="Sides">Sides</option>
                            <option value="Beverages">Beverages</option>
                            <option value="Desserts">Desserts</option>
                            <option value="Breakfast">Breakfast</option>
                            <option value="Lunch">Lunch</option>
                            <option value="Dinner">Dinner</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" id="edit-is-available" name="is_available" value="1"> Available for ordering
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <button class="sidebar-toggle" id="menuToggleBtn" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <script src="<?php echo ASSETS_URL; ?>/js/dashboard-common.js"></script>
    <script>
        function showModal()
        {
            const modal = document.getElementById('edit-modal');
            if (modal) modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeModal()
        {
            const modal = document.getElementById('edit-modal');
            if (modal) modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        window.addEventListener('click', function(event)
        {
            const modal = document.getElementById('edit-modal');
            if (modal && modal.classList.contains('active') && event.target === modal)
            {
                closeModal();
            }
        });

        function confirmDelete(form, itemName)
        {
            return confirm('Are you sure you want to delete "' + itemName + '"?\n\nThis action cannot be undone if the item has never been ordered. If the item has been ordered, it cannot be deleted. You can mark it as unavailable instead.');
        }

        <?php if ($editItem): ?>
        document.addEventListener('DOMContentLoaded', function()
        {
            document.getElementById('edit-item-id').value = '<?php echo $editItem['item_id']; ?>';
            document.getElementById('edit-item-name').value = '<?php echo addslashes(htmlspecialchars($editItem['item_name'], ENT_QUOTES, 'UTF-8')); ?>';
            document.getElementById('edit-description').value = '<?php echo addslashes(htmlspecialchars($editItem['description'] ?? '', ENT_QUOTES, 'UTF-8')); ?>';
            document.getElementById('edit-price').value = '<?php echo $editItem['price']; ?>';
            document.getElementById('edit-quantity').value = '<?php echo $editItem['quantity_available']; ?>';
            document.getElementById('edit-category').value = '<?php echo htmlspecialchars($editItem['category'] ?? 'General', ENT_QUOTES, 'UTF-8'); ?>';
            document.getElementById('edit-is-available').checked = <?php echo $editItem['is_available'] ? 'true' : 'false'; ?>;
            showModal();
        });
        <?php endif; ?>
    </script>
</body>
</html>