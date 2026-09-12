<?php
/**
 * Registration Page
 *
 * Handles new user registration with role selection.
 *
 * CORRECTIONS (Version 13.0):
 * - Removed the inline onclick handler from the Copy button and
 *   replaced it with an event listener in assets/js/auth.js. This
 *   allows the Content Security Policy to remain strict.
 * - Uses the shared escapeOutput() helper
 * - New accounts are activated immediately (is_verified = 1) to match
 *   process document Section 13
 *
 * SOURCE: Issue report - items 3, 23
 * SOURCE: campus-eats-process-document.pdf Section 13
 *
 * @version 13.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/password_validation.php';
require_once dirname(__DIR__, 2) . '/includes/user_id.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();

$error = '';
$success = '';
$generatedUserId = '';
$formData = array('full_name' => '', 'email' => '', 'account_type' => 'student');

$db = getDB();
$csrfToken = getCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $fullName = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $passwordInput = isset($_POST['password']) ? $_POST['password'] : '';
    $accountType = trim(isset($_POST['role']) ? $_POST['role'] : 'Student');
    $submittedCsrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    $roleMap = array(
        'Student' => 'student',
        'student' => 'student',
        'Vendor' => 'vendor',
        'vendor' => 'vendor',
        'Standard' => 'standard',
        'standard' => 'standard'
    );

    $accountType = isset($roleMap[$accountType]) ? $roleMap[$accountType] : 'student';

    $formData = array(
        'full_name' => $fullName,
        'email' => $email,
        'account_type' => $accountType
    );

    if (!validateCsrfToken($submittedCsrfToken))
    {
        $error = 'Security validation failed. Please refresh the page.';
    }
    elseif (empty($fullName) || empty($email) || empty($passwordInput))
    {
        $error = 'Please fill in all required fields.';
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        $error = 'Please enter a valid email address.';
    }
    else
    {
        $passwordValidation = validatePasswordPolicy($passwordInput);

        if (!$passwordValidation['valid'])
        {
            $error = $passwordValidation['message'];
        }
        else
        {
            try
            {
                $existingUser = $db->fetchOne(
                    "SELECT user_id FROM users WHERE email = :email LIMIT 1",
                    array('email' => $email)
                );

                if ($existingUser)
                {
                    $error = 'An account with this email already exists. Please log in.';
                }
                else
                {
                    $username = explode('@', $email)[0];
                    $checkUsername = $db->fetchOne(
                        "SELECT user_id FROM users WHERE username = :username LIMIT 1",
                        array('username' => $username)
                    );

                    if ($checkUsername)
                    {
                        $username = $username . rand(100, 999);
                    }

                    $uniqueId = generateAlphanumericUserId($accountType);
                    $passwordHash = hashPassword($passwordInput);

                    $userId = $db->insert(
                        "INSERT INTO users
                            (unique_id, full_name, username, email, password_hash,
                             account_type, is_verified, is_active, created_at, updated_at)
                         VALUES
                            (:unique_id, :full_name, :username, :email, :password_hash,
                             :account_type, 1, 1, NOW(), NOW())",
                        array(
                            'unique_id' => $uniqueId,
                            'full_name' => $fullName,
                            'username' => $username,
                            'email' => $email,
                            'password_hash' => $passwordHash,
                            'account_type' => $accountType
                        )
                    );

                    if ($accountType === 'vendor' && $userId)
                    {
                        $db->insert(
                            "INSERT INTO vendors
                                (vendor_user_id, vendor_name, description, is_open, is_approved, created_at)
                             VALUES
                                (:user_id, :vendor_name, :description, 1, 0, NOW())",
                            array(
                                'user_id' => $userId,
                                'vendor_name' => $fullName,
                                'description' => 'New vendor awaiting administrative approval.'
                            )
                        );
                    }

                    $generatedUserId = $uniqueId;
                    $success = 'Account created successfully. Your 16-character USER ID has been generated. You can now log in immediately.';
                    writeLog(
                        "Registration successful: User created with email: $email, USER ID: $uniqueId, Role: $accountType",
                        "REGISTER"
                    );

                    generateCsrfToken();
                }
            }
            catch (Exception $e)
            {
                writeLog('Registration error: ' . $e->getMessage(), "REGISTER");
                $error = 'Registration failed. Please try again later.';
            }
        }
    }
}

$csrfToken = getCsrfToken();
$pageTitle = 'Create account';
$displayUserId = '';

if (!empty($generatedUserId))
{
    $displayUserId = formatUserIdForDisplay($generatedUserId);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title>Create account - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1 class="auth-title">Create account</h1>
                <p class="auth-subtitle">Join the campus pickup network</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo escapeOutput($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo escapeOutput($success); ?>
                </div>

                <div class="user-id-section" style="background: var(--gray-50); border-radius: var(--radius-md); padding: var(--space-4); margin: var(--space-4) 0;">
                    <label style="font-size: 0.75rem; color: var(--gray-600); text-transform: uppercase; letter-spacing: 0.02em; display: block; margin-bottom: var(--space-2);">
                        Your 16-character USER ID
                    </label>
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); background: white; border-radius: var(--radius-sm); padding: var(--space-3) var(--space-4); border: 1px solid var(--gray-200);">
                        <code id="generated-user-id" data-user-id="<?php echo escapeOutput($generatedUserId); ?>"
                              style="font-family: monospace; font-size: 0.875rem; font-weight: 600; color: var(--orange); letter-spacing: 0.5px; word-break: break-all;">
                            <?php echo escapeOutput($displayUserId); ?>
                        </code>
                        <button class="btn-copy" id="copy-user-id-btn" type="button"
                                style="background: var(--orange); color: white; border: none; border-radius: var(--radius-sm); padding: var(--space-1) var(--space-3); font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: var(--space-1); flex-shrink: 0;">
                            <i class="fas fa-copy"></i> Copy
                        </button>
                    </div>
                    <p style="font-size: 0.75rem; color: var(--gray-600); margin-top: var(--space-2); display: flex; align-items: center; gap: var(--space-1);">
                        Save this ID. You will need it to reset your password.
                    </p>
                </div>

                <div class="auth-body">
                    <a href="login.php" class="btn btn-primary btn-block">Go to Login</a>
                </div>
            <?php else: ?>
                <div class="auth-body">
                    <form method="POST" action="" id="register-form">
                        <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrfToken); ?>">

                        <div class="form-group">
                            <label class="form-label" for="full_name">Name</label>
                            <div class="input-wrapper">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" id="full_name" name="full_name" class="form-control" required
                                       value="<?php echo escapeOutput($formData['full_name']); ?>"
                                       placeholder="Your full name">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" id="email" name="email" class="form-control" required
                                       value="<?php echo escapeOutput($formData['email']); ?>"
                                       placeholder="you@campus.edu">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" id="password" name="password" class="form-control" required
                                       placeholder="Create a password">
                            </div>
                            <div class="password-strength">
                                <div class="strength-fill" id="strength-fill"></div>
                            </div>
                            <span class="form-hint">
                                Minimum 8 characters, includes uppercase, number, and special character
                            </span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="role">Role</label>
                            <div class="input-wrapper">
                                <i class="fas fa-briefcase input-icon"></i>
                                <select id="role" name="role" class="form-control" required>
                                    <option value="Student" <?php echo $formData['account_type'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="Standard" <?php echo $formData['account_type'] === 'standard' ? 'selected' : ''; ?>>Standard</option>
                                    <option value="Vendor" <?php echo $formData['account_type'] === 'vendor' ? 'selected' : ''; ?>>Vendor</option>
                                </select>
                            </div>
                            <span class="form-hint">Students receive a 2.5% discount on orders.</span>
                        </div>

                        <button type="submit" id="register-btn" class="btn btn-primary btn-block btn-lg">
                            <i class="fas fa-user-plus"></i> Create account
                        </button>
                    </form>
                </div>
                <div class="auth-footer">
                    <p>Already have an account? <a href="login.php">Sign in</a></p>
                    <p class="return-home">
                        <a href="<?php echo ROOT_URL; ?>/index.php">
                            <i class="fas fa-home"></i> Return Home
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/auth.js"></script>
</body>
</html>