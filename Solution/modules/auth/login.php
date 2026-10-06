<?php
/**
 * Sign In Page
 *
 * Authenticates a user by email, username, or 16-character User ID.
 * The page also offers Google SSO when the Google OAuth credentials
 * have been configured. All user-facing strings are translated
 * through the shared __() helper.
 *
 * CORRECTIONS (Version 25.0 - Authentication UI and Redirect Remediation):
 *
 * - Every localisation key now resolves to a human-readable English
 *   string. No residual keys reach the browser.
 * - The post-authentication redirect always issues a single absolute
 *   Location header built from ROOT_URL. Relative or self-referential
 *   redirects that produced the Firefox “page isn’t redirecting
 *   properly” loop have been removed.
 * - The final destination URL is logged at INFO level for auditability.
 * - Session cookie flags (HttpOnly, Secure when HTTPS, path, domain)
 *   are established before the redirect occurs.
 * - CSRF protection continues to use the canonical generateCsrfToken /
 *   getCsrfToken helpers defined in auth.php.
 *
 * SOURCE: Software Engineering Prompt – Resolve Campus Eats
 *         Authentication UI and Runtime Failures.
 * SOURCE: Clean Code, Chapters 2–4; Programming PHP, 3rd Edition
 *         (session handling and header management).
 *
 * @version 25.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();
setSecurityHeaders();

// =============================================================================
// Absolute Redirect Helper
// =============================================================================
//
// Defined before every call site and guarded by function_exists so that
// a second include does not produce a redeclaration fatal. The helper
// always constructs an absolute URL from ROOT_URL, never a relative
// path, eliminating the Firefox redirect loop.

if (!function_exists('redirectToDashboardAfterLogin'))
{
    /**
     * Issues a single absolute redirect to the role-appropriate
     * dashboard and terminates the request.
     *
     * @return void
     */
    function redirectToDashboardAfterLogin()
    {
        $accountType = getCurrentUserRole();
        $destination = ROOT_URL . '/index.php';

        switch ($accountType)
        {
            case 'admin':
                $destination = ROOT_URL . '/modules/admin/dashboard.php';
                break;

            case 'vendor':
                $destination = ROOT_URL . '/modules/vendor/dashboard.php';
                break;

            case 'student':
            case 'standard':
                $destination = ROOT_URL . '/modules/student/dashboard.php';
                break;
        }

        writeLog(
            'Post-authentication redirect to absolute URL: ' . $destination,
            'AUTH'
        );

        header('HTTP/1.1 303 See Other');
        header('Location: ' . $destination);
        exit();
    }
}

// Already authenticated users are sent to their dashboard once.
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

    if ($identifier === '' || $passwordInput === '')
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
        catch (Throwable $exception)
        {
            writeLog(
                'Authentication failed: ' . $exception->getMessage(),
                'AUTH_ERROR'
            );
            $error = __('error.generic');
        }
    }

    // Refresh the token after a failed attempt so the form stays protected.
    $csrfToken = getCsrfToken();
}

$pageTitle = __('login.title');
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
                <h1 class="auth-title"><?php echo __e('login.title'); ?></h1>
                <p class="auth-subtitle"><?php echo __e('login.subtitle'); ?></p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo escapeOutput($error); ?>
                </div>
            <?php endif; ?>

            <div class="auth-body">
                <?php if ($googleConfigured): ?>
                    <a href="<?php echo ROOT_URL; ?>/modules/auth/google_callback.php?action=start"
                       class="btn btn-google btn-block">
                        <i class="fab fa-google" aria-hidden="true"></i>
                        <span><?php echo __e('auth.sign_in_google'); ?></span>
                    </a>
                <?php else: ?>
                    <p class="sso-disabled">
                        <?php echo __e('auth.sso_not_configured'); ?>
                    </p>
                <?php endif; ?>

                <div class="auth-divider">
                    <span><?php echo __e('common.or'); ?></span>
                </div>

                <form method="post" action="" id="login-form" novalidate>
                    <input type="hidden" name="csrf_token"
                           value="<?php echo escapeOutput($csrfToken); ?>">

                    <div class="form-group">
                        <label for="email">
                            <?php echo __e('auth.email_or_user_id'); ?>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon" aria-hidden="true"></i>
                            <input type="text"
                                   id="email"
                                   name="email"
                                   class="form-control"
                                   required
                                   autocomplete="username"
                                   placeholder="<?php echo __e('auth.email_placeholder'); ?>"
                                   value="<?php echo escapeOutput($formData['email']); ?>">
                        </div>
                        <p class="field-hint">
                            <?php echo __e('login.hint_identifier'); ?>
                        </p>
                    </div>

                    <div class="form-group">
                        <div class="label-row">
                            <label for="password">
                                <?php echo __e('auth.password'); ?>
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