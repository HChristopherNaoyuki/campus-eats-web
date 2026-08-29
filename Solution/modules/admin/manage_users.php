<?php
/**
 * Manage Users Page for Administrator - Matching Mockup 27-27a.png
 *
 * This page allows the administrator to view and manage user accounts.
 *
 * CORRECTIONS (Version 16.0 - Visual Parity):
 * - Updated layout to match mockups 27.png, 27a.png
 * - Added user table with roles
 * - Added user registration form
 * - Improved responsive behavior
 *
 * SOURCE: Mockups - 27.png, 27a.png
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

// Handle user registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_user']))
{
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!validateCsrfToken($submittedToken))
    {
        $error = 'Security validation failed. Please refresh the page.';
        writeLog("Manage users CSRF validation failed.", "ADMIN");
    }
    else
    {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = trim($_POST['role'] ?? 'student');
        $password = $_POST['password'] ?? '';

        if (empty($fullName) || empty($email) || empty($password))
        {
            $error = 'All fields are required.';
        }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            $error = 'Please enter a valid email address.';
        }
        elseif (!in_array($role, array('admin', 'vendor', 'student', 'standard')))
        {
            $error = 'Invalid role selected.';
        }
        else
        {
            // Hash password and create user
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $uniqueId = generateUserId($role);

            try
            {
                $userId = $db->insert(
                    "INSERT INTO users (unique_id, full_name, username, email, password_hash, account_type, is_verified, is_active, created_at, updated_at)
                     VALUES (:unique_id, :full_name, :username, :email, :password_hash, :account_type, 1, 1, NOW(), NOW())",
                    array(
                        'unique_id' => $uniqueId,
                        'full_name' => $fullName,
                        'username' => explode('@', $email)[0],
                        'email' => $email,
                        'password_hash' => $passwordHash,
                        'account_type' => $role
                    )
                );

                if ($userId && $role === 'vendor')
                {
                    $db->insert(
                        "INSERT INTO vendors (vendor_user_id, vendor_name, description, is_open, is_approved, created_at)
                         VALUES (:user_id, :vendor_name, :description, 1, 1, NOW())",
                        array(
                            'user_id' => $userId,
                            'vendor_name' => $fullName,
                            'description' => 'Vendor account created by administrator.'
                        )
                    );
                }

                $message = 'User created successfully.';
                writeLog("Admin created user: $email (Role: $role)", "ADMIN");
                $csrfToken = getCsrfToken();
            }
            catch (Exception $e)
            {
                $error = 'Failed to create user. Please try again.';
                writeLog("User creation error: " . $e->getMessage(), "ADMIN");
            }
        }
    }
}

// Fetch all users
$users = $db->fetchAll(
    "SELECT user_id, full_name, username, email, account_type, is_active, is_verified, created_at
     FROM users
     ORDER BY account_type, full_name"
);

function escapeUserManage($string)
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
    <meta name="csrf-token" content="<?php echo escapeUserManage($csrfToken); ?>">
    <title>User Management · Campus Eats Admin</title>
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
                        <h1>User Management</h1>
                        <p>Register users and manage their accounts</p>
                    </div>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Success</div>
                                <div class="alert-message"><?php echo escapeUserManage($message); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeUserManage($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Register User Form -->
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-user-plus"></i> Register User</h3>
                        </div>
                        <div class="dashboard-card-body">
                            <form method="POST" class="add-item-form" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--space-4);">
                                <input type="hidden" name="csrf_token" value="<?php echo escapeUserManage($csrfToken); ?>">
                                <input type="hidden" name="register_user" value="1">

                                <div class="form-group" style="margin-bottom: 0;">
                                    <input type="text" name="full_name" class="form-control" placeholder="Full Name" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <select name="role" class="form-control" required>
                                        <option value="student">Student</option>
                                        <option value="standard">Standard</option>
                                        <option value="vendor">Vendor</option>
                                        <option value="admin">Admin</option>
                                    </select>
                                </div>
                                <div class="form-group" style="margin-bottom: 0;">
                                    <button type="submit" class="btn btn-primary btn-block">
                                        <i class="fas fa-user-plus"></i> Register User
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- All Users Table - Matching Mockup 27.png -->
                    <div class="dashboard-card">
                        <div class="dashboard-card-header">
                            <h3><i class="fas fa-users"></i> All Users</h3>
                        </div>
                        <div class="dashboard-card-body">
                            <div class="table-container">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td data-label="Name"><?php echo escapeUserManage($user['full_name']); ?></td>
                                            <td data-label="Email"><?php echo escapeUserManage($user['email']); ?></td>
                                            <td data-label="Role">
                                                <span class="badge <?php echo $user['account_type'] === 'admin' ? 'badge-error' : ($user['account_type'] === 'vendor' ? 'badge-warning' : ($user['account_type'] === 'student' ? 'badge-success' : 'badge-info')); ?>">
                                                    <?php echo ucfirst(escapeUserManage($user['account_type'])); ?>
                                                </span>
                                            </td>
                                            <td data-label="Status">
                                                <?php if ($user['is_active'] && $user['is_verified']): ?>
                                                    <span class="badge badge-success"><i class="fas fa-check-circle"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-error"><i class="fas fa-times-circle"></i> Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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