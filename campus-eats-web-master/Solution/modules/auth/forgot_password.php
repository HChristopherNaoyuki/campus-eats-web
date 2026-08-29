<?php
/**
 * Forgot Password Page - Matching Mockup 20.png
 *
 * Handles password reset requests with rate limiting and session invalidation.
 *
 * CORRECTIONS (Version 9.0 - Visual Parity):
 * - Updated layout to match mockup 20.png
 * - Improved responsive behavior
 * - Removed inline styles and moved to public.css
 *
 * SOURCE: Mockup - 20.png
 *
 * @version 9.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/password_validation.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();

$error = '';
$success = '';
$formData = array('email' => '', 'user_id' => '');
$csrfToken = getCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $email = trim($_POST['email'] ?? '');
    $userIdInput = trim($_POST['user_id'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';

    $formData = array('email' => $email, 'user_id' => $userIdInput);
    $ipAddress = getClientIpAddress();

    if (!validateCsrfToken($submittedCsrfToken))
    {
        $error = 'Security validation failed. Please refresh the page.';
    }
    elseif (empty($email) || empty($userIdInput) || empty($newPassword))
    {
        $error = 'Please fill in all required fields.';
    }
    elseif (strlen($userIdInput) !== 16 || !ctype_digit($userIdInput))
    {
        $error = 'USER ID must be exactly 16 characters and contain only digits.';
    }
    elseif ($newPassword !== $confirmPassword)
    {
        $error = 'Passwords do not match.';
    }
    else
    {
        // Rate limiting check for password reset attempts.
        $resetAttemptCount = getResetAttemptCount($ipAddress, $email);

        if ($resetAttemptCount >= MAX_RESET_ATTEMPTS)
        {
            $error = 'Too many password reset attempts. Please wait ' . RESET_ATTEMPT_WINDOW_MINUTES . ' minutes before trying again.';
            writeLog("Password reset blocked for IP $ipAddress: Too many attempts ($resetAttemptCount).", "AUTH");
        }
        else
        {
            $passwordValidation = validatePasswordPolicy($newPassword);

            if (!$passwordValidation['valid'])
            {
                $error = $passwordValidation['message'];
            }
            else
            {
                $db = getDB();
                $user = $db->fetchOne
                (
                    "SELECT user_id, full_name, is_active, is_verified
                     FROM users
                     WHERE email = :email AND unique_id = :user_id",
                    array('email' => $email, 'user_id' => $userIdInput)
                );

                if (!$user)
                {
                    $error = 'Email and USER ID combination not found. Please check your details.';
                    recordResetAttempt($ipAddress, $email);
                }
                elseif ($user['is_active'] != 1)
                {
                    $error = 'Your account has been suspended. Please contact an administrator.';
                    recordResetAttempt($ipAddress, $email);
                }
                elseif ($user['is_verified'] != 1)
                {
                    $error = 'Your account has not been verified yet. Please wait for administrator approval.';
                    recordResetAttempt($ipAddress, $email);
                }
                else
                {
                    $newPasswordHash = hashPassword($newPassword);
                    $db->executeQuery
                    (
                        "UPDATE users SET password_hash = :password_hash, updated_at = NOW()
                         WHERE email = :email AND unique_id = :user_id",
                        array('password_hash' => $newPasswordHash, 'email' => $email, 'user_id' => $userIdInput)
                    );

                    // Invalidate any existing sessions for this user.
                    if (!empty($_SESSION['user_id']) && $_SESSION['user_id'] == $user['user_id'])
                    {
                        regenerateSession();
                    }

                    clearResetAttempts($ipAddress, $email);

                    $success = 'Your password has been reset successfully. You can now log in with your new password.';
                    $formData = array('email' => '', 'user_id' => '');
                    generateCsrfToken();

                    writeLog("Password reset successful for user ID: {$user['user_id']}", "AUTH");
                }
            }
        }
    }
}

$csrfToken = getCsrfToken();
$pageTitle = 'Reset password';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Reset password · Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-key"></i>
                </div>
                <h1 class="auth-title">Recover account</h1>
                <p class="auth-subtitle">Use your 16-character User ID (or email) to set a new password</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <div class="auth-body">
                    <a href="login.php" class="btn btn-primary btn-block btn-lg">Go to Login</a>
                </div>
            <?php else: ?>
                <div class="auth-body">
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p>Enter your email address and your 16-character USER ID to reset your password. Your USER ID was provided during registration.</p>
                    </div>

                    <form method="POST" action="" id="reset-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email" id="email" name="email" class="form-control" required
                                       value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="you@campus.edu">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="user_id">USER ID (16 digits)</label>
                            <div class="input-wrapper">
                                <i class="fas fa-id-card input-icon"></i>
                                <input type="text" id="user_id" name="user_id" class="form-control" required
                                       maxlength="16" minlength="16"
                                       value="<?php echo htmlspecialchars($formData['user_id'], ENT_QUOTES, 'UTF-8'); ?>"
                                       placeholder="Enter your 16-character USER ID">
                            </div>
                            <span class="form-hint">Format: 16 digits (e.g., 2024121514302503)</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="new_password">New password</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" id="new_password" name="new_password" class="form-control" required
                                       placeholder="Enter new password">
                            </div>
                            <div class="password-strength">
                                <div class="strength-fill" id="strength-fill"></div>
                            </div>
                            <span class="form-hint">Minimum 8 characters, includes uppercase, number, and special character</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm new password</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                                       placeholder="Confirm new password">
                            </div>
                        </div>

                        <button type="submit" id="reset-btn" class="btn btn-primary btn-block btn-lg">
                            <i class="fas fa-redo-alt"></i> Reset password
                        </button>
                    </form>
                </div>
                <div class="auth-footer">
                    <p><a href="login.php"><i class="fas fa-arrow-left"></i> Back to Sign in</a></p>
                    <p><a href="register.php"><i class="fas fa-user-plus"></i> Create new account</a></p>
                    <p><a href="<?php echo ROOT_URL; ?>/index.php"><i class="fas fa-home"></i> Return Home</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/auth.js"></script>
</body>
</html>