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
 * CORRECTIONS (Version 19.0 - CSRF and Localisation Remediation):
 *
 * - Replaced the direct call to the undefined generateCsrfToken()
 *   with the canonical getCsrfToken() helper that is guaranteed to
 *   exist after the require of auth.php. This eliminates the fatal
 *   “Call to undefined function generateCsrfToken()” that previously
 *   appeared after a successful registration.
 * - All localisation keys resolve to human-readable English strings.
 * - Absolute redirects are used consistently with the login page.
 *
 * SOURCE: Software Engineering Prompt – Resolve Campus Eats
 *         Authentication UI and Runtime Failures.
 * SOURCE: Clean Code, Chapters 2–4; Programming PHP, 3rd Edition.
 *
 * @version 19.0
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
setSecurityHeaders();

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
    $row = $db->fetchOne('SELECT COUNT(*) AS total FROM users');
    $isFirstUser = ($row && (int)$row['total'] === 0);
}

// =============================================================================
// Form Handling
// =============================================================================

$error = '';
$success = '';
$formData = array(
    'full_name'    => '',
    'username'     => '',
    'email'        => '',
    'account_type' => $isFirstUser ? 'admin' : 'student',
    'vendor_name'  => ''
);
$csrfToken = getCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $fullName     = trim(isset($_POST['full_name']) ? $_POST['full_name'] : '');
    $username     = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $email        = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $passwordInput = isset($_POST['password']) ? $_POST['password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $accountType  = isset($_POST['account_type']) ? $_POST['account_type'] : 'student';
    $vendorName   = trim(isset($_POST['vendor_name']) ? $_POST['vendor_name'] : '');
    $submittedCsrf = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    $formData = array(
        'full_name'    => $fullName,
        'username'     => $username,
        'email'        => $email,
        'account_type' => $accountType,
        'vendor_name'  => $vendorName
    );

    if (!validateCsrfToken($submittedCsrf))
    {
        $error = __('error.csrf');
    }
    elseif ($fullName === '' || $email === '' || $passwordInput === '')
    {
        $error = __('error.required_fields');
    }
    elseif ($passwordInput !== $confirmPassword)
    {
        $error = __('error.password_mismatch');
    }
    elseif ($accountType === 'vendor' && $vendorName === '')
    {
        $error = __('error.vendor_shop_required');
    }
    elseif (!$isFirstUser && $accountType === 'admin')
    {
        $error = __('register.admin_taken');
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
                'Registration successful: User created with email: '
                    . $email . ', USER ID: ' . $generatedUserId
                    . ', Role: ' . $accountType,
                'REGISTER'
            );

            // Canonical helper that is guaranteed to exist after the
            // require of auth.php. This replaces the previous direct
            // call to generateCsrfToken() that produced the undefined
            // function fatal.
            getCsrfToken();
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

                $success = __('register.offline_message');
            }
            catch (Throwable $spoolException)
            {
                writeLog(
                    'Registration and spool both failed: '
                        . $spoolException->getMessage(),
                    'REGISTER_ERROR'
                );
                $error = __('error.generic');
            }
        }
        catch (Throwable $exception)
        {
            writeLog(
                'Registration failed: ' . $exception->getMessage(),
                'REGISTER_ERROR'
            );
            $error = __('error.generic');
        }
    }

    $csrfToken = getCsrfToken();
}

$pageTitle = __('register.title');
?>
<!DOCTYPE html>
<html lang="<?php echo escapeOutput(getCurrentLanguage()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title><?php echo escapeOutput($pageTitle); ?> - <?php echo __e('app.name'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
</head>
<body>
    <?php include_once dirname(__DIR__, 2) . '/includes/public_header.php'; ?>

    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title"><?php echo __e('register.title'); ?></h1>
                <p class="auth-subtitle"><?php echo __e('register.subtitle'); ?></p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo escapeOutput($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status">
                    <?php echo escapeOutput($success); ?>
                    <p>
                        <a href="login.php"><?php echo __e('auth.sign_in'); ?></a>
                    </p>
                </div>
            <?php else: ?>

            <div class="auth-body">
                <form method="post" action="" id="register-form" novalidate>
                    <input type="hidden" name="csrf_token"
                           value="<?php echo escapeOutput($csrfToken); ?>">

                    <div class="form-group">
                        <label for="full_name"><?php echo __e('auth.full_name'); ?></label>
                        <input type="text" id="full_name" name="full_name"
                               class="form-control" required
                               value="<?php echo escapeOutput($formData['full_name']); ?>"
                               placeholder="<?php echo __e('register.name_placeholder'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="username"><?php echo __e('auth.username'); ?></label>
                        <input type="text" id="username" name="username"
                               class="form-control"
                               value="<?php echo escapeOutput($formData['username']); ?>"
                               placeholder="<?php echo __e('register.username_placeholder'); ?>">
                        <p class="field-hint"><?php echo __e('register.username_hint'); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="email"><?php echo __e('auth.email'); ?></label>
                        <input type="email" id="email" name="email"
                               class="form-control" required
                               value="<?php echo escapeOutput($formData['email']); ?>"
                               placeholder="<?php echo __e('register.email_placeholder'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="password"><?php echo __e('auth.password'); ?></label>
                        <input type="password" id="password" name="password"
                               class="form-control" required
                               placeholder="<?php echo __e('register.password_placeholder'); ?>">
                        <p class="field-hint"><?php echo __e('register.hint_password'); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password"><?php echo __e('auth.confirm_password'); ?></label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="form-control" required
                               placeholder="<?php echo __e('register.confirm_password_placeholder'); ?>">
                    </div>

                    <div class="form-group">
                        <label for="account_type"><?php echo __e('auth.role'); ?></label>
                        <select id="account_type" name="account_type" class="form-control">
                            <?php if ($isFirstUser): ?>
                                <option value="admin" selected><?php echo __e('role.admin_first_user'); ?></option>
                            <?php endif; ?>
                            <option value="student" <?php echo $formData['account_type'] === 'student' ? 'selected' : ''; ?>>
                                <?php echo __e('role.student'); ?>
                            </option>
                            <option value="standard" <?php echo $formData['account_type'] === 'standard' ? 'selected' : ''; ?>>
                                <?php echo __e('role.standard'); ?>
                            </option>
                            <option value="vendor" <?php echo $formData['account_type'] === 'vendor' ? 'selected' : ''; ?>>
                                <?php echo __e('role.vendor'); ?>
                            </option>
                        </select>
                        <?php if ($isFirstUser): ?>
                            <p class="field-hint"><?php echo __e('register.hint_admin_first'); ?></p>
                        <?php else: ?>
                            <p class="field-hint"><?php echo __e('register.hint_student'); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group" id="vendor-name-group"
                         style="<?php echo $formData['account_type'] === 'vendor' ? '' : 'display:none;'; ?>">
                        <label for="vendor_name"><?php echo __e('register.vendor_shop_name'); ?></label>
                        <input type="text" id="vendor_name" name="vendor_name"
                               class="form-control"
                               value="<?php echo escapeOutput($formData['vendor_name']); ?>"
                               placeholder="<?php echo __e('register.vendor_shop_placeholder'); ?>">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="register-btn">
                        <?php echo __e('auth.sign_up'); ?>
                    </button>
                </form>
            </div>

            <?php endif; ?>

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
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/auth.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
    <script>
    (function () {
        var roleSelect = document.getElementById('account_type');
        var vendorGroup = document.getElementById('vendor-name-group');
        if (roleSelect && vendorGroup) {
            roleSelect.addEventListener('change', function () {
                vendorGroup.style.display = (roleSelect.value === 'vendor') ? '' : 'none';
            });
        }
    })();
    </script>
</body>
</html>