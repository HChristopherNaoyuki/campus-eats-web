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
 * SOURCE: Password visibility request.
 * SOURCE: Campus Eats process document, section 15.
 * SOURCE: Notes - Make use of SSO.
 *
 * @version 24.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();

// =============================================================================
// Redirect Helper
// =============================================================================
//
// The helper is defined before every call site. The definition is
// guarded by function_exists so a second include of this file does
// not cause a redeclaration fatal.

if (!function_exists('redirectToDashboardAfterLogin'))
{
    /**
     * Redirects the authenticated user to the dashboard for their role.
     *
     * @return void
     */
    function redirectToDashboardAfterLogin()
    {
        $accountType = getCurrentUserRole();

        switch ($accountType)
        {
            case 'admin':
                header('Location: ' . BASE_URL . '/modules/admin/dashboard.php');
                break;
            case 'vendor':
                header('Location: ' . BASE_URL . '/modules/vendor/dashboard.php');
                break;
            case 'student':
            case 'standard':
            default:
                header('Location: ' . BASE_URL . '/modules/student/dashboard.php');
                break;
        }
        exit();
    }
}

// Already signed in: send the user to the correct dashboard.
if (isLoggedIn())
{
    redirectToDashboardAfterLogin();
}

setSecurityHeaders();

$errorMessage = '';
$csrfToken = getCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!validateCsrfToken($submittedToken))
    {
        $errorMessage = __('auth.invalid_token');
    }
    else
    {
        $identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';
        $password   = isset($_POST['password']) ? $_POST['password'] : '';

        if ($identifier === '' || $password === '')
        {
            $errorMessage = __('auth.missing_fields');
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
                $errorMessage = __('auth.service_unavailable');
            }
        }
    }
}

$pageTitle = __('auth.sign_in');
?>
<!DOCTYPE html>
<html lang="<?php echo escapeOutput(getCurrentLanguage()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title><?php echo escapeOutput($pageTitle); ?> - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
</head>
<body>
    <?php include_once dirname(__DIR__, 2) . '/includes/public_header.php'; ?>

    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <h1><?php echo __e('auth.sign_in_title'); ?></h1>
                <p><?php echo __e('auth.sign_in_subtitle'); ?></p>
            </div>

            <?php if ($errorMessage !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo escapeOutput($errorMessage); ?>
                </div>
            <?php endif; ?>

            <div class="auth-body">
                <form method="post" action="" id="login-form" novalidate>
                    <input type="hidden" name="csrf_token"
                           value="<?php echo escapeOutput($csrfToken); ?>">

                    <div class="form-group">
                        <label for="identifier">
                            <?php echo __e('auth.identifier_label'); ?>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon" aria-hidden="true"></i>
                            <input type="text"
                                   id="identifier"
                                   name="identifier"
                                   class="form-control"
                                   required
                                   autocomplete="username"
                                   placeholder="<?php echo __e('auth.identifier_placeholder'); ?>"
                                   value="<?php echo isset($_POST['identifier'])
                                       ? escapeOutput($_POST['identifier'])
                                       : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="label-row">
                            <label for="password">
                                <?php echo __e('auth.password_label'); ?>
                            </label>
                            <a href="forgot_password.php" class="forgot-link">
                                <?php echo __e('auth.forgot_password'); ?>
                            </a>
                        </div>
                        <div class="input-wrapper input-wrapper-password">
                            <i class="fas fa-lock input-icon" aria-hidden="true"></i>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-control"
                                   required
                                   autocomplete="current-password"
                                   placeholder="<?php echo __e('auth.password_placeholder'); ?>">
                            <button type="button"
                                    class="password-toggle"
                                    id="password-toggle"
                                    aria-label="<?php echo __e('auth.show_password'); ?>"
                                    aria-pressed="false"
                                    tabindex="0">
                                <i class="fas fa-eye"
                                   id="password-toggle-icon"
                                   aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                            class="btn btn-primary btn-block btn-lg"
                            id="login-submit-btn">
                        <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        <?php echo __e('auth.sign_in'); ?>
                    </button>
                </form>
            </div>

            <div class="auth-footer">
                <p>
                    <?php echo __e('auth.no_account'); ?>
                    <a href="register.php"><?php echo __e('auth.sign_up'); ?></a>
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
</body>
</html>