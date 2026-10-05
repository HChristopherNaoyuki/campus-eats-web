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
                exit();

            case 'vendor':
                header('Location: ' . BASE_URL . '/modules/vendor/dashboard.php');
                exit();

            case 'student':
            case 'standard':
                header('Location: ' . BASE_URL . '/modules/student/dashboard.php');
                exit();

            default:
                header('Location: ' . ROOT_URL . '/index.php');
                exit();
        }
    }
}

// Redirect authenticated users before rendering the form.
if (isLoggedIn())
{
    redirectToDashboardAfterLogin();
}

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
// Form Handling
// =============================================================================

$error = '';
$formData = array('email' => '');
$csrfToken = getCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $identifier = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $passwordInput = isset($_POST['password']) ? $_POST['password'] : '';
    $submittedCsrfToken = isset($_POST['csrf_token'])
        ? $_POST['csrf_token']
        : '';

    $formData['email'] = $identifier;

    if (empty($identifier) || empty($passwordInput))
    {
        $error = __('error.required_fields');
    }
    else
    {
        try
        {
            $result = authenticateUser(
                $identifier,
                $passwordInput,
                $submittedCsrfToken
            );

            if ($result['success'])
            {
                redirectToDashboardAfterLogin();
            }

            $error = $result['message'];
        }
        catch (Exception $exception)
        {
            // The authentication path reports a database-unavailable
            // condition as an exception. The message is logged. The
            // user sees a generic message.
            writeLog(
                'Authentication failed: ' . $exception->getMessage(),
                "AUTH_ERROR"
            );
            $error = __('error.generic');
        }
    }

    $csrfToken = getCsrfToken();
}

$pageTitle = __('login.title');
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
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">
                    <i class="fas fa-utensils"></i>
                </div>
                <h1 class="auth-title"><?php echo __e('login.title'); ?></h1>
                <p class="auth-subtitle"><?php echo __e('login.subtitle'); ?></p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo escapeOutput($error); ?>
                </div>
            <?php endif; ?>

            <div class="auth-body">
                <a href="<?php echo escapeOutput(googleStartUrl()); ?>"
                   class="btn-google">
                    <i class="fab fa-google" aria-hidden="true"></i>
                    <span><?php echo __e('auth.sign_in_google'); ?></span>
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

                <form method="POST" action="" id="login-form">
                    <input type="hidden" name="csrf_token"
                           value="<?php echo escapeOutput($csrfToken); ?>">

                    <div class="form-group">
                        <label class="form-label" for="email">
                            <?php echo __e('auth.email_or_user_id'); ?>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"
                               aria-hidden="true"></i>
                            <input type="text"
                                   id="email"
                                   name="email"
                                   class="form-control"
                                   required
                                   autocomplete="username"
                                   value="<?php echo escapeOutput($formData['email']); ?>"
                                   placeholder="<?php echo __e('auth.email_placeholder'); ?>"
                                   autofocus>
                        </div>
                        <span class="form-hint">
                            <?php echo __e('login.hint_identifier'); ?>
                        </span>
                    </div>

                    <div class="form-group">
                        <div class="form-label-row">
                            <label class="form-label" for="password">
                                <?php echo __e('auth.password'); ?>
                            </label>
                            <a href="forgot_password.php" class="forgot-link">
                                <?php echo __e('auth.forgot_password'); ?>
                            </a>
                        </div>
                        <div class="input-wrapper input-wrapper-password">
                            <i class="fas fa-lock input-icon"
                               aria-hidden="true"></i>
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