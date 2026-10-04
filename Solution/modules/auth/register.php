<?php
/**
 * Registration Page
 *
 * Handles new user registration. The page offers the Admin role only
 * for the first account, when the users table is empty. Every other
 * registration is limited to Student, Standard, or Vendor. The page
 * also offers Google SSO when the Google OAuth credentials have been
 * configured. All user-facing strings are translated through the
 * shared __() helper.
 *
 * CORRECTIONS (Version 18.0 - REPORT.txt Alignment):
 *
 * - Fix 1 (username field). The form collects an optional username.
 *   When the field is empty, the account service derives the username
 *   from the email local part.
 *
 * - Fix 2 (confirm password). The form collects a confirm-password
 *   value. The mismatch check runs before the account service is
 *   called.
 *
 * - Fix 3 (vendor shop name). The form collects the shop name when the
 *   role is Vendor. The shop name is required for the vendor account.
 *
 * - Fix 4 (account service and spool). The form uses the
 *   AccountService for transactional account creation and the
 *   OutageSpool when the database is unreachable.
 *
 * - Fix 5 (safe error display). The page never echoes a raw database
 *   error to the browser. The error is logged. The user sees a
 *   generic message or a validation message.
 *
 * - Retained the first-user-only Admin rule, the User ID display with
 *   the copy button, the CSRF protection, the password policy check,
 *   and the auto-verification of new accounts.
 *
 * SOURCE: REPORT.txt, Registration Form Fields.
 * SOURCE: Audit continuation, Part 2.
 *
 * @version 18.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/includes/password_validation.php';
require_once dirname(__DIR__, 2) . '/includes/user_id.php';
require_once dirname(__DIR__, 2) . '/includes/account_service.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();

$db = getDB();

// =============================================================================
// Google SSO state
// =============================================================================

$googleConfigured = false;

if (file_exists(dirname(__DIR__, 2) . '/includes/oauth_google.php'))
{
    require_once dirname(__DIR__, 2) . '/includes/oauth_google.php';

    if (function_exists('googleIsConfigured'))
    {
        $googleConfigured = googleIsConfigured();
    }
}

// =============================================================================
// Determine whether this visitor is the first user.
// =============================================================================

$isFirstUser = false;

if ($db->isAvailable())
{
    $isFirstUser = ($db->userCount() === 0);
}

$error = '';
$success = '';
$generatedUserId = '';
$displayUserId = '';
$formData = array(
    'full_name'    => '',
    'username'     => '',
    'email'        => '',
    'account_type' => 'student',
    'vendor_name'  => ''
);

$csrfToken = getCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $fullName = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $passwordInput = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password'])
        ? $_POST['confirm_password']
        : '';
    $accountType = trim(isset($_POST['role']) ? $_POST['role'] : 'Student');
    $vendorName = trim(isset($_POST['vendor_name']) ? $_POST['vendor_name'] : '');
    $submittedCsrfToken = isset($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '';

    $roleMap = array(
        'Student'  => 'student',
        'student'  => 'student',
        'Vendor'   => 'vendor',
        'vendor'   => 'vendor',
        'Standard' => 'standard',
        'standard' => 'standard',
        'Admin'    => 'admin',
        'admin'    => 'admin'
    );

    $accountType = isset($roleMap[$accountType])
        ? $roleMap[$accountType]
        : 'student';

    $isFirstUserAtPostTime = $db->isAvailable()
        ? ($db->userCount() === 0)
        : false;

    $formData = array(
        'full_name'    => $fullName,
        'username'     => $username,
        'email'        => $email,
        'account_type' => $accountType,
        'vendor_name'  => $vendorName
    );

    if (!validateCsrfToken($submittedCsrfToken))
    {
        $error = __('error.csrf');
    }
    elseif (empty($fullName) || empty($email) || empty($passwordInput))
    {
        $error = __('error.required_fields');
    }
    elseif ($passwordInput !== $confirmPassword)
    {
        $error = __('error.password_mismatch');
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        $error = __('error.invalid_email');
    }
    elseif ($accountType === 'admin' && !$isFirstUserAtPostTime)
    {
        $error = __('register.error_admin_taken');
    }
    elseif ($accountType === 'vendor' && empty($vendorName))
    {
        $error = __('register.error_vendor_name_required');
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
                $accountService = new AccountService();

                $result = $accountService->createAccount(array(
                    'full_name'    => $fullName,
                    'username'     => $username,
                    'email'        => $email,
                    'password'     => $passwordInput,
                    'account_type' => $accountType,
                    'vendor_name'  => $vendorName
                ));

                $generatedUserId = $result['unique_id'];
                $success = __('register.success_heading') . ' '
                         . __('register.success_body');

                writeLog(
                    "Registration successful: User created with email: "
                        . $email . ", USER ID: " . $generatedUserId
                        . ", Role: " . $accountType,
                    "REGISTER"
                );

                generateCsrfToken();
                $isFirstUser = false;
            }
            catch (InvalidArgumentException $exception)
            {
                $error = $exception->getMessage();
            }
            catch (RuntimeException $exception)
            {
                try
                {
                    if (!class_exists('OutageSpool'))
                    {
                        require_once dirname(__DIR__, 2)
                            . '/includes/outage_spool.php';
                    }

                    $spool = new OutageSpool();
                    $spool->enqueue('register', array(
                        'full_name'    => $fullName,
                        'username'     => $username,
                        'email'        => $email,
                        'password'     => $passwordInput,
                        'account_type' => $accountType,
                        'vendor_name'  => $vendorName
                    ));

                    $success = __('register.success_offline');
                    writeLog(
                        "Registration queued for replay: $email",
                        "REGISTER"
                    );
                }
                catch (Exception $spoolException)
                {
                    writeLog(
                        "Registration failed and the queue is unavailable: "
                            . $spoolException->getMessage(),
                        "REGISTER_ERROR"
                    );
                    $error = __('error.generic');
                }
            }
        }
    }
}

$csrfToken = getCsrfToken();
$pageTitle = __('register.title');

if (!empty($generatedUserId))
{
    $displayUserId = formatUserIdForDisplay($generatedUserId);
}
?>
<!DOCTYPE html>
<html lang="<?php echo escapeOutput(getCurrentLanguage()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title><?php echo escapeOutput($pageTitle); ?> - <?php echo __e('app.name'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
    <script src="<?php echo ASSETS_URL; ?>/js/toast.js" defer></script>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-user-plus"></i>
                </div>
                <h1 class="auth-title"><?php echo __e('register.title'); ?></h1>
                <p class="auth-subtitle"><?php echo __e('register.subtitle'); ?></p>
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

                <?php if (!empty($generatedUserId)): ?>
                <div class="user-id-section">
                    <label class="user-id-label">
                        <?php echo __e('register.user_id_label'); ?>
                    </label>
                    <div class="user-id-display">
                        <code id="generated-user-id"
                              data-user-id="<?php echo escapeOutput($generatedUserId); ?>">
                            <?php echo escapeOutput($displayUserId); ?>
                        </code>
                        <button class="btn-copy" id="copy-user-id-btn" type="button">
                            <i class="fas fa-copy"></i>
                            <?php echo __e('register.copy_id'); ?>
                        </button>
                    </div>
                    <p class="user-id-note">
                        <?php echo __e('register.user_id_note'); ?>
                    </p>
                </div>
                <?php endif; ?>

                <div class="auth-body">
                    <a href="login.php" class="btn btn-primary btn-block">
                        <?php echo __e('register.go_to_login'); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="auth-body">
                    <?php if ($isFirstUser): ?>
                        <div class="info-box first-user-box">
                            <i class="fas fa-crown" aria-hidden="true"></i>
                            <div>
                                <strong><?php echo __e('register.first_user_title'); ?></strong>
                                <p><?php echo __e('register.first_user_body'); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <a href="<?php echo escapeOutput(googleStartUrl()); ?>"
                       class="btn-google">
                        <i class="fab fa-google" aria-hidden="true"></i>
                        <span><?php echo __e('auth.sign_up_google'); ?></span>
                    </a>

                    <?php if (!$googleConfigured): ?>
                        <p class="sso-note">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            <?php echo __e('auth.sso_not_configured'); ?>
                        </p>
                    <?php endif; ?>

                    <div class="auth-separator">
                        <span><?php echo __e('common.or'); ?></span>
                    </div>

                    <form method="POST" action="" id="register-form">
                        <input type="hidden" name="csrf_token"
                               value="<?php echo escapeOutput($csrfToken); ?>">

                        <div class="form-group">
                            <label class="form-label" for="full_name">
                                <?php echo __e('auth.full_name'); ?>
                            </label>
                            <div class="input-wrapper">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text"
                                       id="full_name"
                                       name="full_name"
                                       class="form-control"
                                       required
                                       value="<?php echo escapeOutput($formData['full_name']); ?>"
                                       placeholder="<?php echo __e('register.name_placeholder'); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="username">
                                <?php echo __e('auth.username'); ?>
                            </label>
                            <div class="input-wrapper">
                                <i class="fas fa-at input-icon"></i>
                                <input type="text"
                                       id="username"
                                       name="username"
                                       class="form-control"
                                       value="<?php echo escapeOutput($formData['username']); ?>"
                                       placeholder="<?php echo __e('register.username_placeholder'); ?>">
                            </div>
                            <span class="form-hint">
                                <?php echo __e('register.username_hint'); ?>
                            </span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">
                                <?php echo __e('auth.email'); ?>
                            </label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope input-icon"></i>
                                <input type="email"
                                       id="email"
                                       name="email"
                                       class="form-control"
                                       required
                                       value="<?php echo escapeOutput($formData['email']); ?>"
                                       placeholder="<?php echo __e('register.email_placeholder'); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="password">
                                <?php echo __e('auth.password'); ?>
                            </label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password"
                                       id="password"
                                       name="password"
                                       class="form-control"
                                       required
                                       placeholder="<?php echo __e('register.password_placeholder'); ?>">
                            </div>
                            <div class="password-strength">
                                <div class="strength-fill" id="strength-fill"></div>
                            </div>
                            <span class="form-hint">
                                <?php echo __e('register.hint_password'); ?>
                            </span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">
                                <?php echo __e('auth.confirm_password'); ?>
                            </label>
                            <div class="input-wrapper">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password"
                                       id="confirm_password"
                                       name="confirm_password"
                                       class="form-control"
                                       required
                                       placeholder="<?php echo __e('register.confirm_password_placeholder'); ?>">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="role">
                                <?php echo __e('auth.role'); ?>
                            </label>
                            <div class="input-wrapper">
                                <i class="fas fa-briefcase input-icon"></i>
                                <select id="role" name="role" class="form-control" required>
                                    <option value="Student"
                                        <?php echo $formData['account_type'] === 'student' ? 'selected' : ''; ?>>
                                        <?php echo __e('role.student'); ?>
                                    </option>
                                    <option value="Standard"
                                        <?php echo $formData['account_type'] === 'standard' ? 'selected' : ''; ?>>
                                        <?php echo __e('role.standard'); ?>
                                    </option>
                                    <option value="Vendor"
                                        <?php echo $formData['account_type'] === 'vendor' ? 'selected' : ''; ?>>
                                        <?php echo __e('role.vendor'); ?>
                                    </option>
                                    <?php if ($isFirstUser): ?>
                                        <option value="Admin"
                                            <?php echo $formData['account_type'] === 'admin' ? 'selected' : ''; ?>>
                                            <?php echo __e('role.admin_first_user'); ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <span class="form-hint">
                                <?php echo __e('register.hint_student'); ?>
                                <?php if ($isFirstUser): ?>
                                    <br><?php echo __e('register.hint_admin_first'); ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="form-group" id="vendor-name-group"
                             style="display: <?php echo $formData['account_type'] === 'vendor' ? 'block' : 'none'; ?>;">
                            <label class="form-label" for="vendor_name">
                                <?php echo __e('register.vendor_name_label'); ?>
                            </label>
                            <div class="input-wrapper">
                                <i class="fas fa-store input-icon"></i>
                                <input type="text"
                                       id="vendor_name"
                                       name="vendor_name"
                                       class="form-control"
                                       value="<?php echo escapeOutput($formData['vendor_name']); ?>"
                                       placeholder="<?php echo __e('register.vendor_name_placeholder'); ?>">
                            </div>
                            <span class="form-hint">
                                <?php echo __e('register.vendor_name_hint'); ?>
                            </span>
                        </div>

                        <button type="submit" id="register-btn"
                                class="btn btn-primary btn-block btn-lg">
                            <i class="fas fa-user-plus"></i>
                            <?php echo __e('auth.sign_up'); ?>
                        </button>
                    </form>
                </div>
                <div class="auth-footer">
                    <p>
                        <?php echo __e('auth.already_have_account'); ?>
                        <a href="login.php"><?php echo __e('auth.sign_in'); ?></a>
                    </p>
                    <p class="return-home">
                        <a href="<?php echo escapeOutput(ROOT_URL); ?>/index.php">
                            <?php echo __e('auth.return_home'); ?>
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/auth.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function()
        {
            var roleSelect = document.getElementById('role');
            var vendorGroup = document.getElementById('vendor-name-group');

            if (roleSelect && vendorGroup)
            {
                roleSelect.addEventListener('change', function()
                {
                    if (this.value === 'Vendor')
                    {
                        vendorGroup.style.display = 'block';
                    }
                    else
                    {
                        vendorGroup.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>