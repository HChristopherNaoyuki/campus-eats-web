<?php
/**
 * Login Page
 *
 * Authenticates a user by email, username, or 16-character User ID.
 *
 * CORRECTIONS (Version 20.0 - Demo Account Removal):
 * - Removed the demo accounts table that displayed plaintext emails and
 *   passwords on the login page. The requirement is that the application
 *   must not provide default login credentials, sample accounts, or test
 *   accounts. No such table is rendered.
 * - Removed the require statement that loaded config/demo_accounts.php
 *   for the purpose of displaying those credentials. The file is still
 *   present on disk and is still included by config/database.php, but it
 *   returns an empty array and contributes nothing to this page.
 * - The login field label now reads "User ID, Username, or Email" and the
 *   placeholder reads "16-character User ID, username, or email". This
 *   reflects the actual authentication logic in includes/auth.php, which
 *   accepts all three forms after the User ID branch was added.
 * - Retained all prior corrections: CSRF token in the form, escapeOutput()
 *   for all dynamic output, and session-based redirect for authenticated
 *   users.
 *
 * SOURCE: DEMO ACCOUNT REQUIREMENT (Interpretation C)
 * SOURCE: Solution/includes/auth.php authenticateUser()
 *
 * @version 20.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();

// =============================================================================
// Redirect authenticated users before rendering the login form.
// =============================================================================

if (isLoggedIn())
{
    redirectToDashboard();
}

// =============================================================================
// Handle form submission.
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
        $error = 'Please enter both your identifier and password.';
    }
    else
    {
        // authenticateUser() accepts an email address, a username, or a
        // 16-character User ID. It classifies the identifier internally
        // and queries the matching column.
        $result = authenticateUser($identifier, $passwordInput, $submittedCsrfToken);

        if ($result['success'])
        {
            redirectToDashboard();
        }
        else
        {
            $error = $result['message'];
        }
    }

    $csrfToken = getCsrfToken();
}

// =============================================================================
// Helpers.
// =============================================================================

$pageTitle = 'Sign in';

if (!function_exists('redirectToDashboard'))
{
    /**
     * Redirects the authenticated user to the dashboard for their role.
     *
     * The switch covers all four roles. If the session contains an
     * unrecognised role, the user is sent to the landing page rather
     * than to a dashboard they are not permitted to view.
     *
     * @return void
     */
    function redirectToDashboard()
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title>Sign in - Campus Eats</title>
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
                <h1 class="auth-title">Sign in</h1>
                <p class="auth-subtitle">Welcome back to Campus Eats</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo escapeOutput($error); ?>
                </div>
            <?php endif; ?>

            <div class="auth-body">
                <form method="POST" action="">
                    <?php echo csrfTokenHtml(); ?>

                    <div class="form-group">
                        <label class="form-label" for="email">User ID, Username, or Email</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="text"
                                   id="email"
                                   name="email"
                                   class="form-control"
                                   required
                                   value="<?php echo escapeOutput($formData['email']); ?>"
                                   placeholder="16-character User ID, username, or email"
                                   autofocus>
                        </div>
                        <span class="form-hint">
                            You may sign in with the email address, the username, or the
                            16-character User ID that was shown when you registered.
                        </span>
                    </div>

                    <div class="form-group">
                        <div class="form-label-row">
                            <label class="form-label" for="password">Password</label>
                            <a href="forgot_password.php" class="forgot-link">Recover account</a>
                        </div>
                        <div class="input-wrapper">
                            <i class="fas fa-lock input-icon"></i>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   class="form-control"
                                   required
                                   placeholder="Enter your password">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-arrow-right"></i> Sign in
                    </button>
                </form>
            </div>

            <div class="auth-footer">
                <p>New here? <a href="register.php">Create account</a></p>
                <p class="return-home">
                    <a href="<?php echo ROOT_URL; ?>/index.php">Return Home</a>
                </p>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/auth.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/main.js"></script>
</body>
</html>