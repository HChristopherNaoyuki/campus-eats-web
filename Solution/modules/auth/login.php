<?php
/**
 * Sign In Page
 *
 * Authenticates a user by email, username, or 16-character User ID.
 * The page also offers Google SSO when the Google OAuth credentials
 * have been configured. All user-facing strings are translated
 * through the shared __() helper.
 *
 * CORRECTIONS (Version 27.0 - Post-Login Role Dashboard Redirect):
 *
 * - Admin users are redirected to the real admin dashboard at
 *   BASE_URL/modules/admin/dashboard.php. The previous version
 *   incorrectly sent them to the public landing page because it
 *   assumed the admin dashboard file did not exist.
 * - Vendor, student, and standard destinations are unchanged.
 * - Unknown roles fall back to the landing page only.
 * - SSO presentation, form-label classes, and password-row layout
 *   from Version 26.0 are retained.
 *
 * SOURCE: Fix – Post-Login Role Dashboard Redirect.
 * SOURCE: Clean Code, Robert C. Martin, Chapters 2–4.
 *
 * @version 27.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/includes/i18n.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();
setSecurityHeaders();

// =============================================================================
// Redirect Helper
// =============================================================================

if (!function_exists('redirectToDashboardAfterLogin'))
{
    /**
     * Redirects the authenticated user to the dashboard for their role.
     *
     * Every known role is sent to an existing module dashboard.
     * Admin is no longer forced onto the public landing page.
     *
     * @return void
     */
    function redirectToDashboardAfterLogin()
    {
        $accountType = function_exists('getCurrentUserRole')
            ? getCurrentUserRole()
            : (isset($_SESSION['account_type']) ? $_SESSION['account_type'] : '');

        $destination = ROOT_URL . '/index.php';

        switch ($accountType)
        {
            case 'admin':
                $destination = BASE_URL . '/modules/admin/dashboard.php';
                break;

            case 'vendor':
                $destination = BASE_URL . '/modules/vendor/dashboard.php';
                break;

            case 'student':
            case 'standard':
                $destination = BASE_URL . '/modules/student/dashboard.php';
                break;

            default:
                $destination = ROOT_URL . '/index.php';
                break;
        }

        writeLog(
            'Post-authentication redirect to absolute URL: ' . $destination
                . ' (role: ' . $accountType . ')',
            'AUTH'
        );

        header('HTTP/1.1 303 See Other');
        header('Location: ' . $destination);
        exit();
    }
}

if (function_exists('isLoggedIn') && isLoggedIn())
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
$csrfToken = function_exists('getCsrfToken') ? getCsrfToken() : '';

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
        $error = function_exists('__')
            ? __('error.required_fields')
            : 'Please fill in all required fields.';
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

            $error = isset($result['message'])
                ? $result['message']
                : (function_exists('__')
                    ? __('error.invalid_credentials')
                    : 'Invalid credentials.');
        }
        catch (Throwable $exception)
        {
            writeLog(
                'Authentication failed: ' . $exception->getMessage(),
                'AUTH_ERROR'
            );
            $error = function_exists('__')
                ? __('error.generic')
                : 'An error occurred. Please try again later.';
        }
    }

    $csrfToken = function_exists('getCsrfToken') ? getCsrfToken() : '';
}

$pageTitle = function_exists('__') ? __('login.title') : 'Sign in to Campus Eats';
?>
<!DOCTYPE html>
<html lang="<?php echo function_exists('getCurrentLanguage')
    ? htmlspecialchars(getCurrentLanguage(), ENT_QUOTES, 'UTF-8')
    : 'en'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/style.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
</head>
<body>
    <?php include_once dirname(__DIR__, 2) . '/includes/public_header.php'; ?>

    <div class="auth-page">
        <div class="auth-card">
            <div class="auth-header">
                <h1 class="auth-title"><?php
                    echo function_exists('__e')
                        ? __e('login.title')
                        : 'Sign in to Campus Eats';
                ?></h1>
                <p class="auth-subtitle"><?php
                    echo function_exists('__e')
                        ? __e('login.subtitle')
                        : 'Enter your campus credentials to continue';
                ?></p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <div class="auth-body">
                <?php if ($googleConfigured): ?>
                    <a href="<?php echo ROOT_URL; ?>/modules/auth/google_callback.php?action=start"
                       class="btn btn-google btn-block">
                        <i class="fab fa-google" aria-hidden="true"></i>
                        <span><?php
                            echo function_exists('__e')
                                ? __e('auth.sign_in_google')
                                : 'Sign in with Google';
                        ?></span>
                    </a>

                    <div class="auth-divider">
                        <span><?php
                            echo function_exists('__e') ? __e('common.or') : 'or';
                        ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="" id="login-form" novalidate>
                    <input type="hidden" name="csrf_token"
                           value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-group">
                        <label class="form-label" for="email">
                            <?php
                                echo function_exists('__e')
                                    ? __e('auth.email_or_user_id')
                                    : 'Email or student number';
                            ?>
                        </label>
                        <div class="input-wrapper">
                            <i class="fas fa-user input-icon" aria-hidden="true"></i>
                            <input type="text"
                                   id="email"
                                   name="email"
                                   class="form-control"
                                   required
                                   autocomplete="username"
                                   placeholder="<?php
                                       echo function_exists('__e')
                                           ? __e('auth.email_placeholder')
                                           : 'e.g. name@campus.edu';
                                   ?>"
                                   value="<?php echo htmlspecialchars($formData['email'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <p class="field-hint">
                            <?php
                                echo function_exists('__e')
                                    ? __e('login.hint_identifier')
                                    : 'You may use your email, username, or 16-character User ID.';
                            ?>
                        </p>
                    </div>

                    <div class="form-group">
                        <div class="form-label-row">
                            <label class="form-label" for="password">
                                <?php
                                    echo function_exists('__e')
                                        ? __e('auth.password')
                                        : 'Password';
                                ?>
                            </label>
                            <a href="forgot_password.php" class="forgot-link">
                                <?php
                                    echo function_exists('__e')
                                        ? __e('auth.forgot_password')
                                        : 'Recover account';
                                ?>
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
                                   placeholder="<?php
                                       echo function_exists('__e')
                                           ? __e('auth.password_placeholder')
                                           : 'Enter your password';
                                   ?>">
                            <button type="button"
                                    class="password-toggle"
                                    id="password-toggle"
                                    aria-label="<?php
                                        echo function_exists('__e')
                                            ? __e('auth.show_password')
                                            : 'Show password';
                                    ?>"
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
                        <?php
                            echo function_exists('__e')
                                ? __e('auth.sign_in')
                                : 'Sign in';
                        ?>
                    </button>
                </form>
            </div>

            <div class="auth-footer">
                <p>
                    <?php
                        echo function_exists('__e')
                            ? __e('auth.no_account')
                            : 'New here?';
                    ?>
                    <a href="register.php">
                        <?php
                            echo function_exists('__e')
                                ? __e('auth.sign_up')
                                : 'Create account';
                        ?>
                    </a>
                </p>
                <p class="return-home">
                    <a href="<?php echo htmlspecialchars(ROOT_URL, ENT_QUOTES, 'UTF-8'); ?>/index.php">
                        <?php
                            echo function_exists('__e')
                                ? __e('auth.return_home')
                                : 'Return Home';
                        ?>
                    </a>
                </p>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/auth.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
</body>
</html>