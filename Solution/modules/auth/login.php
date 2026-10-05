<?php
/**
 * Sign In Page
 *
 * Authenticates a user by email, username, or 16-character User ID.
 * The page also offers Google SSO when the Google OAuth credentials
 * have been configured. All user-facing strings are translated
 * through the shared __() helper.
 *
 * CORRECTIONS (Version 24.0 - Password Visibility):
 *
 * - Added the password visibility toggle. The toggle is a small
 *   button inside the password field. The button changes the input
 *   type between password and text. The button carries an aria-label
 *   and an aria-pressed attribute. The button is reachable by
 *   keyboard and announced by screen readers. The button does not
 *   change the entered value. The button does not submit the form.
 *
 * - The redirect helper redirectToDashboardAfterLogin() is defined
 *   above every call site. The previous version defined it after the
 *   call site, which produced a fatal error when a signed-in user
 *   opened the login page.
 *
 * - The login handler catches database exceptions and logs them. The
 *   user sees a generic message. The handler does not echo a raw
 *   database error to the browser.
 *
 * - The paths use the Solution directory with a capital S. The
 *   directory is case-sensitive on Linux.
 *
 * SOURCE: Campus Eats process document, section 15.
 * SOURCE: Technical Audit Report - Password Visibility Toggle.
 *
 * @version 24.0
 */

session_start();

require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';

setSecurityHeaders();
initSession();

/**
 * Redirects an already-authenticated user to the role-appropriate
 * dashboard. Defined before any call site so that early returns do
 * not produce a fatal error.
 *
 * @return void
 */
function redirectToDashboardAfterLogin()
{
    $role = getCurrentAccountType();

    switch ($role)
    {
        case 'admin':
            header('Location: ' . ROOT_URL . '/modules/admin/dashboard.php');
            break;
        case 'vendor':
            header('Location: ' . ROOT_URL . '/modules/vendor/dashboard.php');
            break;
        case 'student':
        case 'standard':
        default:
            header('Location: ' . ROOT_URL . '/modules/student/dashboard.php');
            break;
    }
    exit();
}

// Already signed in: send the user to the correct dashboard.
if (isAuthenticated())
{
    redirectToDashboardAfterLogin();
}

$errorMessage = '';
$csrfToken = getCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!validateCsrfToken($submittedToken))
    {
        $errorMessage = __('Invalid security token. Please try again.');
    }
    else
    {
        $identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';
        $password   = isset($_POST['password']) ? $_POST['password'] : '';

        if ($identifier === '' || $password === '')
        {
            $errorMessage = __('Please enter both identifier and password.');
        }
        else
        {
            try
            {
                $result = authenticateUser($identifier, $password);

                if ($result['success'])
                {
                    redirectToDashboardAfterLogin();
                }
                else
                {
                    $errorMessage = __($result['message']);
                }
            }
            catch (Throwable $t)
            {
                writeLog(
                    'Login handler exception: ' . $t->getMessage(),
                    'AUTH'
                );
                $errorMessage = __('Authentication service temporarily unavailable.');
            }
        }
    }
}

$pageTitle = __('Sign In');
?>
<!DOCTYPE html>
<html lang="<?php echo escapeOutput(getCurrentLanguage()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title><?php echo escapeOutput($pageTitle); ?> - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ROOT_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo ROOT_URL; ?>/assets/css/public.css">
</head>
<body>
    <?php include_once dirname(__DIR__, 2) . '/includes/public_header.php'; ?>

    <main class="auth-main">
        <div class="auth-container">
            <h1><?php echo escapeOutput(__('Sign in to Campus Eats')); ?></h1>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo escapeOutput($errorMessage); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="" id="login-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo escapeOutput($csrfToken); ?>">

                <div class="form-group">
                    <label for="identifier"><?php echo escapeOutput(__('Email, username or User ID')); ?></label>
                    <input
                        type="text"
                        id="identifier"
                        name="identifier"
                        autocomplete="username"
                        required
                        value="<?php echo isset($_POST['identifier']) ? escapeOutput($_POST['identifier']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password"><?php echo escapeOutput(__('Password')); ?></label>
                    <div class="input-wrapper-password">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            autocomplete="current-password"
                            required
                        >
                        <button
                            type="button"
                            id="password-toggle"
                            class="password-toggle"
                            aria-label="<?php echo escapeOutput(__('Show password')); ?>"
                            aria-pressed="false"
                        >
                            <i id="password-toggle-icon" class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <?php echo escapeOutput(__('Sign In')); ?>
                </button>
            </form>

            <p class="auth-links">
                <a href="<?php echo ROOT_URL; ?>/modules/auth/forgot_password.php">
                    <?php echo escapeOutput(__('Forgot password?')); ?>
                </a>
                <span aria-hidden="true"> · </span>
                <a href="<?php echo ROOT_URL; ?>/modules/auth/register.php">
                    <?php echo escapeOutput(__('Create an account')); ?>
                </a>
            </p>

            <?php if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== ''): ?>
                <div class="auth-divider">
                    <span><?php echo escapeOutput(__('or')); ?></span>
                </div>
                <div id="google-signin-button"></div>
            <?php endif; ?>
        </div>
    </main>

    <?php include_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>

    <script src="<?php echo ROOT_URL; ?>/assets/js/auth.js"></script>
</body>
</html>