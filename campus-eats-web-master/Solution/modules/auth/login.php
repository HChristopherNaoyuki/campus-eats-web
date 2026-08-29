<?php
/**
 * Login Page - Matching Mockup 19.png
 *
 * Handles user authentication and redirects to role-specific dashboards.
 *
 * CORRECTIONS (Version 16.0 - Visual Parity):
 * - Updated layout to match mockup 19.png
 * - Added demo accounts display matching mockup
 * - Improved responsive behavior
 * - Removed inline styles and moved to public.css
 *
 * SOURCE: Mockup - 19.png
 *
 * @version 16.0
 */

// Load required dependencies
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

// Start secure session
startSecureSession();

// =============================================================================
// Initialization
// =============================================================================

$error = '';
$formData = array('email' => '');
$csrfToken = getCsrfToken();

// =============================================================================
// Redirect Already Logged In Users
// =============================================================================

if (isLoggedIn())
{
    writeLog("User already logged in, redirecting to dashboard", "AUTH");
    redirectToDashboard();
}

// =============================================================================
// Handle Login Form Submission
// =============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $identifier = trim($_POST['email'] ?? '');
    $passwordInput = $_POST['password'] ?? '';
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';

    $formData['email'] = $identifier;

    if (empty($identifier) || empty($passwordInput))
    {
        $error = 'Please enter both email/username and password.';
        writeLog("Login validation failed: Missing credentials", "AUTH");
    }
    else
    {
        $result = authenticateUser($identifier, $passwordInput, $submittedCsrfToken);

        if ($result['success'])
        {
            $role = getCurrentUserRole();
            writeLog("Login successful for user: $identifier (Role: $role)", "AUTH");
            redirectToDashboard();
        }
        else
        {
            $error = $result['message'];
            writeLog("Login failed for user: $identifier - " . $result['message'], "AUTH");
        }
    }

    $csrfToken = getCsrfToken();
}

// =============================================================================
// Page Template Variables
// =============================================================================

$pageTitle = 'Sign in';

function escapeLoginOutput($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

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
            header('Location: ' . BASE_URL . '/modules/student/dashboard.php');
            exit();
        case 'standard':
            header('Location: ' . BASE_URL . '/modules/student/dashboard.php');
            exit();
        default:
            writeLog("Unknown user role: $accountType, redirecting to index", "AUTH");
            header('Location: ' . ROOT_URL . '/index.php');
            exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeLoginOutput($csrfToken); ?>">
    <title>Sign in · Campus Eats</title>
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
                    <?php echo escapeLoginOutput($error); ?>
                </div>
            <?php endif; ?>

            <div class="auth-body">
                <form method="POST" action="">
                    <?php echo csrfTokenHtml(); ?>

                    <div class="form-group">
                        <label class="form-label" for="email">User ID or Email</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="text"
                                   id="email"
                                   name="email"
                                   class="form-control"
                                   required
                                   value="<?php echo escapeLoginOutput($formData['email']); ?>"
                                   placeholder="16-char User ID or you@campus.edu"
                                   autofocus>
                        </div>
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

                    <button type="submit" class="btn btn-primary btn-block btn-lg" id="login-btn">
                        <i class="fas fa-arrow-right"></i> Sign in
                    </button>
                </form>

                <!-- Demo Accounts - Matching Mockup 19.png -->
                <div class="demo-accounts">
                    <strong>Demo accounts (all 8 work)</strong>
                    <div class="demo-grid">
                        <div class="demo-item">student@campus.edu</div>
                        <div class="demo-item">student@campus.edu</div>
                        <div class="demo-item">standard@campus.edu</div>
                        <div class="demo-item">standard@campus.edu</div>
                        <div class="demo-item">vendor@campus.edu</div>
                        <div class="demo-item">vendor@campus.edu</div>
                        <div class="demo-item">admin@campus.edu</div>
                        <div class="demo-item">admin@campus.edu</div>
                    </div>
                </div>
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