<?php
/**
 * Manage Vendors Page for Administrator - Matching Mockups 28-28c.png
 *
 * This page allows the administrator to approve, suspend, and manage vendor accounts.
 *
 * CORRECTIONS (Version 16.0 - Visual Parity):
 * - Updated layout to match mockups 28.png, 28a.png, 28b.png, 28c.png
 * - Added vendor cards with details
 * - Added vendor registration form
 * - Improved responsive behavior
 *
 * SOURCE: Mockups - 28.png, 28a.png, 28b.png, 28c.png
 *
 * @version 16.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();
requireAdmin();

$db = getDB();
$currentUser = getCurrentUser();
$csrfToken = getCsrfToken();

$message = '';
$error = '';

// Handle POST actions for vendor management
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($submittedToken))
    {
        $error = 'Security validation failed. Please refresh the page.';
        writeLog("Manage vendors CSRF validation failed.", "ADMIN");
    }
    else
    {
        $actionType = $_POST['action_type'] ?? '';
        $targetVendorId = (int)($_POST['vendor_id'] ?? 0);

        if ($actionType === 'approve' && $targetVendorId > 0)
        {
            $vendor = $db->fetchOne
            (
                "SELECT vendor_user_id FROM vendors WHERE vendor_id = :vendor_id",
                array('vendor_id' => $targetVendorId)
            );
            if ($vendor)
            {
                $db->executeQuery
                (
                    "UPDATE vendors SET is_approved = 1 WHERE vendor_id = :vendor_id",
                    array('vendor_id' => $targetVendorId)
                );
                $db->executeQuery
                (
                    "UPDATE users SET is_verified = 1 WHERE user_id = :user_id",
                    array('user_id' => $vendor['vendor_user_id'])
                );
                $message = 'Vendor approved successfully.';
                writeLog("Admin approved vendor ID: $targetVendorId", "ADMIN");
            }
        }
        elseif ($actionType === 'reject' && $targetVendorId > 0)
        {
            $vendor = $db->fetchOne
            (
                "SELECT vendor_user_id FROM vendors WHERE vendor_id = :vendor_id",
                array('vendor_id' => $targetVendorId)
            );
            if ($vendor)
            {
                $db->executeQuery
                (
                    "DELETE FROM vendors WHERE vendor_id = :vendor_id",
                    array('vendor_id' => $targetVendorId)
                );
                $db->executeQuery
                (
                    "DELETE FROM users WHERE user_id = :user_id",
                    array('user_id' => $vendor['vendor_user_id'])
                );
                $message = 'Vendor application rejected and removed.';
                writeLog("Admin rejected vendor ID: $targetVendorId", "ADMIN");
            }
        }
        elseif ($actionType === 'suspend' && $targetVendorId > 0)
        {
            $vendor = $db->fetchOne
            (
                "SELECT vendor_user_id FROM vendors WHERE vendor_id = :vendor_id",
                array('vendor_id' => $targetVendorId)
            );
            if ($vendor)
            {
                $db->executeQuery
                (
                    "UPDATE users SET is_active = 0 WHERE user_id = :user_id",
                    array('user_id' => $vendor['vendor_user_id'])
                );
                $message = 'Vendor suspended successfully.';
                writeLog("Admin suspended vendor ID: $targetVendorId", "ADMIN");
            }
        }
        elseif ($actionType === 'activate' && $targetVendorId > 0)
        {
            $vendor = $db->fetchOne
            (
                "SELECT vendor_user_id FROM vendors WHERE vendor_id = :vendor_id",
                array('vendor_id' => $targetVendorId)
            );
            if ($vendor)
            {
                $db->executeQuery
                (
                    "UPDATE users SET is_active = 1 WHERE user_id = :user_id",
                    array('user_id' => $vendor['vendor_user_id'])
                );
                $message = 'Vendor activated successfully.';
                writeLog("Admin activated vendor ID: $targetVendorId", "ADMIN");
            }
        }
        elseif ($actionType === 'register_vendor')
        {
            $vendorName = trim($_POST['vendor_name'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $ownerEmail = trim($_POST['owner_email'] ?? '');
            $ownerName = trim($_POST['owner_name'] ?? '');

            if (empty($vendorName) || empty($location) || empty($ownerEmail))
            {
                $error = 'Vendor name, location, and owner email are required.';
            }
            else
            {
                // Create user account for vendor
                $password = bin2hex(random_bytes(8));
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $uniqueId = generateUserId('vendor');

                $userId = $db->insert(
                    "INSERT INTO users (unique_id, full_name, username, email, password_hash, account_type, is_verified, is_active, created_at, updated_at)
                     VALUES (:unique_id, :full_name, :username, :email, :password_hash, 'vendor', 1, 1, NOW(), NOW())",
                    array(
                        'unique_id' => $uniqueId,
                        'full_name' => $ownerName ?: $vendorName,
                        'username' => explode('@', $ownerEmail)[0],
                        'email' => $ownerEmail,
                        'password_hash' => $passwordHash
                    )
                );

                if ($userId)
                {
                    $db->insert(
                        "INSERT INTO vendors (vendor_user_id, vendor_name, address, contact_phone, description, is_open, is_approved, created_at)
                         VALUES (:user_id, :vendor_name, :address, :contact, :description, 1, 1, NOW())",
                        array(
                            'user_id' => $userId,
                            'vendor_name' => $vendorName,
                            'address' => $location,
                            'contact' => $contact,
                            'description' => 'Vendor registered by administrator.'
                        )
                    );

                    $message = 'Vendor registered successfully.';
                    writeLog("Admin registered vendor: $vendorName", "ADMIN");
                    $csrfToken = getCsrfToken();
                }
                else
                {
                    $error = 'Failed to register vendor. Please try again.';
                }
            }
        }
    }
    $csrfToken = getCsrfToken();
}

// Fetch vendors
$vendors = $db->fetchAll(
    "SELECT v.vendor_id, v.vendor_name, v.address, v.contact_phone, v.is_open, v.is_approved, v.created_at,
            u.full_name as owner_name, u.email as owner_email, u.is_active as user_active
     FROM vendors v
     JOIN users u ON v.vendor_user_id = u.user_id
     ORDER BY v.created_at DESC"
);

function escapeVendorManage($string)
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
    <meta name="csrf-token" content="<?php echo escapeVendorManage($csrfToken); ?>">
    <title>Vendor Management · Campus Eats Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/admin.css">
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/admin_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="admin-content">
                <div class="container">
                    <div class="page-header">
                        <h1>Vendor Management</h1>
                        <p>Vendors are synced live from the Restaurant API</p>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Success</div>
                                <div class="alert-message"><?php echo escapeVendorManage($message); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeVendorManage($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Register Vendor Form -->
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-store"></i> Register Vendor</h3>
                        </div>
                        <div class="dashboard-card-body">
                            <form method="POST" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4);">
                                <input type="hidden" name="csrf_token" value="<?php echo escapeVendorManage($csrfToken); ?>">
                                <input type="hidden" name="action_type" value="register_vendor">

                                <div class="form-group" style="margin-bottom: 0;">
                                    <input type="text" name="vendor_name" class="form-control" placeholder="Vendor Name" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <input type="text" name="location" class="form-control" placeholder="Location" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <input type="text" name="contact" class="form-control" placeholder="Contact Number">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <input type="email" name="owner_email" class="form-control" placeholder="Owner Email" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <input type="text" name="owner_name" class="form-control" placeholder="Owner Name">
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-store"></i> Register Vendor
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- All Vendors - Matching Mockup 28.png -->
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-store"></i> All Vendors</h3>
                        </div>
                        <div class="dashboard-card-body">
                            <?php if (empty($vendors)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-store-slash"></i>
                                    <p>No vendors found.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-container">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Name</th>
                                                <th>Location</th>
                                                <th>Contact</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vendors as $vendor): ?>
                                            <tr>
                                                <td data-label="ID"><?php echo $vendor['vendor_id']; ?></td>
                                                <td data-label="Name"><?php echo escapeVendorManage($vendor['vendor_name']); ?></td>
                                                <td data-label="Location"><?php echo escapeVendorManage($vendor['address'] ?? 'N/A'); ?></td>
                                                <td data-label="Contact"><?php echo escapeVendorManage($vendor['contact_phone'] ?? 'N/A'); ?></td>
                                                <td data-label="Status">
                                                    <?php if (!$vendor['is_approved']): ?>
                                                        <span class="badge badge-warning">Pending</span>
                                                    <?php elseif (!$vendor['user_active']): ?>
                                                        <span class="badge badge-error">Suspended</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-success">Active</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Actions">
                                                    <?php if (!$vendor['is_approved']): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo escapeVendorManage($csrfToken); ?>">
                                                            <input type="hidden" name="vendor_id" value="<?php echo $vendor['vendor_id']; ?>">
                                                            <input type="hidden" name="action_type" value="approve">
                                                            <button type="submit" class="btn btn-success btn-sm">Approve</button>
                                                        </form>
                                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Reject this vendor?');">
                                                            <input type="hidden" name="csrf_token" value="<?php echo escapeVendorManage($csrfToken); ?>">
                                                            <input type="hidden" name="vendor_id" value="<?php echo $vendor['vendor_id']; ?>">
                                                            <input type="hidden" name="action_type" value="reject">
                                                            <button type="submit" class="btn btn-danger btn-sm">Reject</button>
                                                        </form>
                                                    <?php elseif ($vendor['user_active']): ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo escapeVendorManage($csrfToken); ?>">
                                                            <input type="hidden" name="vendor_id" value="<?php echo $vendor['vendor_id']; ?>">
                                                            <input type="hidden" name="action_type" value="suspend">
                                                            <button type="submit" class="btn btn-warning btn-sm">Suspend</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="csrf_token" value="<?php echo escapeVendorManage($csrfToken); ?>">
                                                            <input type="hidden" name="vendor_id" value="<?php echo $vendor['vendor_id']; ?>">
                                                            <input type="hidden" name="action_type" value="activate">
                                                            <button type="submit" class="btn btn-success btn-sm">Activate</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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