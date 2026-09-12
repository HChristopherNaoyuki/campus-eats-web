<?php
/**
 * Forgot Password Page
 *
 * Handles password reset requests with rate limiting and session
 * invalidation.
 *
 * CORRECTIONS (Version 12.0):
 * - Accepts both the documented alphanumeric USER ID (XXXX-XXXX-XXXX-XXXX)
 *   and the legacy 16-digit numeric format
 * - Uses the shared validateUserIdFormat() helper
 * - Uses the shared escapeOutput() helper
 * - Placeholder text aligned with the documented format
 *
 * SOURCE: Issue report - items 8, 9, 23
 * SOURCE: campus-eats-process-document.pdf Section 11.1
 *
 * @version 12.0
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
$formData = array('email' => '', 'user_id' => '');
$csrfToken = getCsrfToken();

if (!defined('MAX_RESET_ATTEMPTS'))
{
    define('MAX_RESET_ATTEMPTS', 3);
}

if (!defined('RESET_ATTEMPT_WINDOW'))
{
    define('RESET_ATTEMPT_WINDOW', 3600);
}

if (!defined('RESET_ATTEMPT_WINDOW_MINUTES'))
{
    define('RESET_ATTEMPT_WINDOW_MINUTES', 60);
}

if (!function_exists('getResetAttemptCount'))
{
    function getResetAttemptCount($ipAddress, $email)
    {
        $db = getDB();

        $result = $db->fetchOne(
            "SELECT COUNT(*) as count
             FROM password_reset_attempts
             WHERE ip_address = :ip_address
               AND email = :email
               AND attempted_at > DATE_SUB(NOW(), INTERVAL :window SECOND)",
            array(
                'ip_address' => $ipAddress,
                'email' => $email,
                'window' => RESET_ATTEMPT_WINDOW
            )
        );

        return (int)(isset($result['count']) ? $result['count'] : 0);
    }
}

if (!function_exists('recordResetAttempt'))
{
    function recordResetAttempt($ipAddress, $email)
    {
        $db = getDB();

        $db->insert(
            "INSERT INTO password_reset_attempts (ip_address, email, attempted_at)
             VALUES (:ip_address, :email, NOW())",
            array(
                'ip_address' => $ipAddress,
                'email' => $email
            )
        );
    }
}

if (!function_exists('clearResetAttempts'))
{
    function clearResetAttempts($ipAddress, $email)
    {
        $db = getDB();

        $db->executeQuery(
            "DELETE FROM password_reset_attempts
             WHERE ip_address = :ip_address AND email = :email",
            array(
                'ip_address' => $ipAddress,
                'email' => $email
            )
        );
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $userIdInput = trim(isset($_POST['user_id']) ? $_POST['user_id'] : '');
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $submittedCsrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

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
    elseif ($newPassword !== $confirmPassword)
    {
        $error = 'Passwords do not match.';
    }
    else
    {
        // Normalize: accept "XXXX-XXXX-XXXX-XXXX" or 16 continuous chars
        $cleanUserId = str_replace('-', '', $userIdInput);

        if (!validateUserIdFormat($cleanUserId))
        {
            $error = 'USER ID must be 16 alphanumeric characters (XXXX-XXXX-XXXX-XXXX) or 16 digits.';
        }
        else
        {
            $userIdInput = $cleanUserId;
            $resetAttemptCount = getResetAttemptCount($ipAddress, $email);

            if ($resetAttemptCount >= MAX_RESET_ATTEMPTS)
            {
                $error = 'Too many password reset attempts. Please wait '
                    . RESET_ATTEMPT_WINDOW_MINUTES
                    . ' minutes before trying again.';

                writeLog(
                    "Password reset blocked for IP $ipAddress: Too many attempts ($resetAttemptCount).",
                    "AUTH"
                );
            }
            else
            {
                try
                {
                    $passwordHash = validateAndHashPassword($newPassword);

                    $db = getDB();
                    $user = $db->fetchOne(
                        "SELECT user_id, full_name, is_active, is_verified
                         FROM users
                         WHERE email = :email AND unique_id = :user_id
                         LIMIT 1",
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
                        $db->executeQuery(
                            "UPDATE users SET password_hash = :password_hash, updated_at = NOW()
                             WHERE email = :email AND unique_id = :user_id",
                            array(
                                'password_hash' => $passwordHash,
                                'email' => $email,
                                'user_id' => $userIdInput
                            )
                        );

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
                catch (InvalidArgumentException $e)
                {
                    $error = $e->getMessage();
                    recordResetAttempt($ipAddress, $email);
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
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title>Reset password - Campus Eats</title>
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
                <p class="auth-subtitle">Use your USER ID and email to set a new password</p>
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
                <div class="auth-body">
                    <a href="login.php" class="btn btn-primary btn-block btn-lg">Go to Login</a>
                </div>
            <?php else: ?>
                <div class="auth-body">
                    <div class="info-box">
                        <i class="fas fa-info-circle"></i>
                        <p>
                            Enter the email address and USER ID you registered with.
                            The USER ID is the 16-character value shown at registration.
                        </p>
                    </div>

                    <form method="POST" action="" id="reset-form">
                        <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrfToken); ?>">

                        <div class="form-group">
                            <label class="form-label" for="email">Email</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email"
                                       id="email"
                                       name="email"
                                       class="form-control"
                                       required
                                       value="<?php echo escapeOutput($formData['email']); ?>"
                                       placeholder="you@campus.edu">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="user_id">USER ID</label>
                            <div class="input-wrapper">
                                <i class="fas fa-id-card input-icon"></i>
                                <input type="text"
                                       id="user_id"
                                       name="user_id"
                                       class="form-control"
                                       required
                                       maxlength="19"
                                       minlength="16"
                                       value="<?php echo escapeOutput($formData['user_id']); ?>"
                                       placeholder="XXXX-XXXX-XXXX-XXXX or 16 digits">
                            </div>
                            <span class="form-hint">
                                Format: XXXX-XXXX-XXXX-XXXX (alphanumeric) or 16 digits (legacy)
                            </span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="new_password">New password</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password"
                                       id="new_password"
                                       name="new_password"
                                       class="form-control"
                                       required
                                       placeholder="Enter new password">
                            </div>
                            <span class="form-hint">
                                Minimum 8 characters, includes uppercase, number, and special character
                            </span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm new password</label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password"
                                       id="confirm_password"
                                       name="confirm_password"
                                       class="form-control"
                                       required
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