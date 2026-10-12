<?php
/**
 * Registration Page
 *
 * Creates student, standard, vendor, or (first) admin accounts.
 * MySQL is authoritative. Firebase projection is best-effort and
 * never turns a successful insert into a user-visible failure.
 *
 * CORRECTIONS (Version 20.0 - Post-success stability):
 *
 * - After createAccount() succeeds, CSRF is rotated only via a safe
 *   helper path. A missing generateCsrfToken() no longer fatals after
 *   the account is committed.
 * - Success copy uses explicit English when i18n keys are missing so
 *   the user never sees raw keys like "register.success_heading".
 * - $error is cleared when $success is set so both alerts cannot show.
 * - Firebase skip remains logged only; it does not set $error.
 *
 * SOURCE: Error log – Account created ID 13, then UI error.
 * SOURCE: Clean Code, Robert C. Martin.
 *
 * @version 20.0
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

$googleConfigured = false;

if (file_exists(dirname(__DIR__, 2) . '/includes/oauth_google.php'))
{
    require_once dirname(__DIR__, 2) . '/includes/oauth_google.php';

    if (function_exists('googleIsConfigured'))
    {
        $googleConfigured = googleIsConfigured();
    }
}

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

/**
 * Returns a translated string, or the fallback when the catalogue
 * returns the raw key (missing translation).
 *
 * @param string $key
 * @param string $fallback
 * @return string
 */
function campus_eats_register_msg($key, $fallback)
{
    if (!function_exists('__'))
    {
        return $fallback;
    }

    $value = __($key);

    if ($value === $key || $value === '' || strpos($value, 'register.') === 0)
    {
        return $fallback;
    }

    return $value;
}

/**
 * Rotates the CSRF token without fatalling if helpers differ by version.
 *
 * @return void
 */
function campus_eats_rotate_csrf_after_register()
{
    if (function_exists('generateCsrfToken'))
    {
        generateCsrfToken(true);
        return;
    }

    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
        $error = campus_eats_register_msg(
            'error.csrf',
            'Your session token expired. Please try again.'
        );
    }
    elseif (empty($fullName) || empty($email) || empty($passwordInput))
    {
        $error = campus_eats_register_msg(
            'error.required_fields',
            'Please fill in all required fields.'
        );
    }
    elseif ($passwordInput !== $confirmPassword)
    {
        $error = campus_eats_register_msg(
            'error.password_mismatch',
            'Passwords do not match.'
        );
    }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        $error = campus_eats_register_msg(
            'error.invalid_email',
            'Please enter a valid email address.'
        );
    }
    elseif ($accountType === 'admin' && !$isFirstUserAtPostTime)
    {
        $error = campus_eats_register_msg(
            'register.error_admin_taken',
            'An administrator account already exists.'
        );
    }
    elseif ($accountType === 'vendor' && empty($vendorName))
    {
        $error = campus_eats_register_msg(
            'register.error_vendor_name_required',
            'Vendor name is required for vendor accounts.'
        );
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
                $error = '';
                $success = campus_eats_register_msg(
                    'register.success_heading',
                    'Account created successfully.'
                );
                $success .= ' ' . campus_eats_register_msg(
                    'register.success_body',
                    'You can sign in with your email and password. '
                    . 'Save your User ID below.'
                );

                writeLog(
                    'Registration successful: User created with email: '
                        . $email . ', USER ID: ' . $generatedUserId
                        . ', Role: ' . $accountType,
                    'REGISTER'
                );

                campus_eats_rotate_csrf_after_register();
                $isFirstUser = false;
                $formData = array(
                    'full_name'    => '',
                    'username'     => '',
                    'email'        => '',
                    'account_type' => 'student',
                    'vendor_name'  => ''
                );
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

                    $error = '';
                    $success = campus_eats_register_msg(
                        'register.success_offline',
                        'Your registration was saved and will complete when '
                        . 'the database is available again.'
                    );
                    writeLog(
                        'Registration queued for replay: ' . $email,
                        'REGISTER'
                    );
                }
                catch (Exception $spoolException)
                {
                    writeLog(
                        'Registration failed and the queue is unavailable: '
                            . $spoolException->getMessage(),
                        'REGISTER_ERROR'
                    );
                    $error = campus_eats_register_msg(
                        'error.generic',
                        'Unable to create the account right now. Please try again.'
                    );
                }
            }
            catch (Exception $exception)
            {
                writeLog(
                    'Unexpected registration error: ' . $exception->getMessage(),
                    'REGISTER_ERROR'
                );
                $error = campus_eats_register_msg(
                    'error.generic',
                    'Unable to create the account right now. Please try again.'
                );
            }
        }
    }
}

$csrfToken = function_exists('getCsrfToken')
    ? getCsrfToken()
    : (isset($_SESSION['csrf_token'])
        ? $_SESSION['csrf_token']
        : bin2hex(random_bytes(32)));

$pageTitle = campus_eats_register_msg('register.title', 'Create account');

if (!empty($generatedUserId) && function_exists('formatUserIdForDisplay'))
{
    $displayUserId = formatUserIdForDisplay($generatedUserId);
}
elseif (!empty($generatedUserId))
{
    $displayUserId = $generatedUserId;
}
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('getCurrentLanguage')
    ? escapeOutput(getCurrentLanguage())
    : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title><?php echo escapeOutput($pageTitle); ?> - Campus Eats</title>
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
                <h1 class="auth-title"><?php echo escapeOutput($pageTitle); ?></h1>
                <p class="auth-subtitle">
                    Join Campus Eats to order ahead from campus vendors.
                </p>
            </div>

            <?php if ($error !== '' && $success === ''): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo escapeOutput($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success" role="status">
                    <i class="fas fa-check-circle"></i>
                    <?php echo escapeOutput($success); ?>
                </div>

                <?php if (!empty($generatedUserId)): ?>
                <div class="user-id-section">
                    <label class="user-id-label">Your User ID</label>
                    <div class="user-id-display">
                        <code id="generated-user-id"
                              data-user-id="<?php echo escapeOutput($generatedUserId); ?>">
                            <?php echo escapeOutput($displayUserId); ?>
                        </code>
                        <button class="btn-copy" id="copy-user-id-btn" type="button">
                            <i class="fas fa-copy"></i>
                            Copy
                        </button>
                    </div>
                    <p class="user-id-note">
                        Keep this ID. You can sign in with your email or this User ID.
                    </p>
                </div>
                <p class="auth-footer">
                    <a href="login.php" class="btn btn-primary btn-block">
                        Continue to sign in
                    </a>
                </p>
                <?php endif; ?>
            <?php else: ?>

            <form method="POST" action="" class="auth-form" novalidate>
                <input type="hidden" name="csrf_token"
                       value="<?php echo escapeOutput($csrfToken); ?>">

                <div class="form-group">
                    <label class="form-label" for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name"
                           class="form-control" required
                           value="<?php echo escapeOutput($formData['full_name']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="username">Username (optional)</label>
                    <input type="text" id="username" name="username"
                           class="form-control"
                           value="<?php echo escapeOutput($formData['username']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input type="email" id="email" name="email"
                           class="form-control" required
                           value="<?php echo escapeOutput($formData['email']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="role">Account type</label>
                    <select id="role" name="role" class="form-control">
                        <option value="Student" <?php echo $formData['account_type'] === 'student' ? 'selected' : ''; ?>>Student</option>
                        <option value="Standard" <?php echo $formData['account_type'] === 'standard' ? 'selected' : ''; ?>>Standard</option>
                        <option value="Vendor" <?php echo $formData['account_type'] === 'vendor' ? 'selected' : ''; ?>>Vendor</option>
                        <?php if ($isFirstUser): ?>
                        <option value="Admin" <?php echo $formData['account_type'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group" id="vendor-name-group"
                     style="<?php echo $formData['account_type'] === 'vendor' ? '' : 'display:none'; ?>">
                    <label class="form-label" for="vendor_name">Vendor / stall name</label>
                    <input type="text" id="vendor_name" name="vendor_name"
                           class="form-control"
                           value="<?php echo escapeOutput($formData['vendor_name']); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password"
                           class="form-control" required autocomplete="new-password">
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password"
                           class="form-control" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg">
                    Create account
                </button>
            </form>

            <?php endif; ?>

            <div class="auth-footer">
                <p>
                    Already have an account?
                    <a href="login.php">Sign in</a>
                </p>
                <p class="return-home">
                    <a href="<?php echo escapeOutput(ROOT_URL); ?>/index.php">
                        Return home
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script>
    (function ()
    {
        var role = document.getElementById('role');
        var vendorGroup = document.getElementById('vendor-name-group');
        if (role && vendorGroup)
        {
            role.addEventListener('change', function ()
            {
                vendorGroup.style.display =
                    role.value === 'Vendor' ? '' : 'none';
            });
        }
    })();
    </script>
    <script src="<?php echo ASSETS_URL; ?>/js/auth.js"></script>
</body>
</html>