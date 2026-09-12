<?php
/**
 * Sign In Page
 *
 * Authenticates a user by email, username, or 16-character User ID. The
 * page also offers Google SSO when the Google OAuth credentials have
 * been configured. All user-facing strings are translated through the
 * shared __() helper, which reads from Solution/lang/en.php and
 * Solution/lang/af.php.
 *
 * CORRECTIONS (Version 22.0):
 * - Added a Sign in with Google button. The button links to
 *   Solution/includes/oauth_google.php?action=start. When the Google
 *   OAuth credentials are not configured, the button is rendered but
 *   the flow returns a clear error page rather than attempting a
 *   redirect that cannot succeed.
 * - Replaced every hardcoded string with a __() call so the page
 *   renders in English or Afrikaans depending on the active language.
 * - The page no longer reads config/demo_accounts.php. No credentials
 *   are displayed anywhere on the page.
 * - Retains the User ID login branch, the CSRF protection, the
 *   escapeOutput() helper, and the session handling from earlier
 *   versions.
 *
 * SOURCE: NOTES - Make use of SSO. Users should also be able to use
 *         Google SSO. Include multi-language support for at least two
 *         South African languages: English and Afrikaans.
 *
 * @version 22.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();

// =============================================================================
// Redirect authenticated users before rendering the form.
// =============================================================================

if (isLoggedIn())
{
    redirectToDashboardAfterLogin();
}

// =============================================================================
// Helpers
// =============================================================================

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

// =============================================================================
// Google SSO state
// =============================================================================
//
// The Google button is always rendered. When the OAuth credentials are
// not configured, clicking it leads to a page that explains the missing
// configuration instead of a broken redirect. That page lives in
// Solution/includes/oauth_google.php.
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
    $submittedCsrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    $formData['email'] = $identifier;

    if (empty($identifier) || empty($passwordInput))
    {
        $error = __('error.required_fields');
    }
    else
    {
        $result = authenticateUser($identifier, $passwordInput, $submittedCsrfToken);

        if ($result['success'])
        {
            redirectToDashboardAfterLogin();
        }
        else
        {
            $error = $result['message'];
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
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
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

                <form method="POST" action="">
                    <?php echo csrfTokenHtml(); ?>

                    <div class="form-group">
                        <label class="form-label" for="email">
                            <?php echo __e('auth.email_or_user_id'); ?>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="text"
                                   id="email"
                                   name="email"
                                   class="form-control"
                                   required
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
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-control"
                                   required
                                   placeholder="<?php echo __e('auth.password_placeholder'); ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-arrow-right"></i>
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