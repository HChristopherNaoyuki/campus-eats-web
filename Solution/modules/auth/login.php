<?php
/**
 * Login Page
 *
 * CORRECTIONS (Version 18.0):
 * - Demo accounts section now lists real credentials from
 *   config/demo_accounts.php, including passwords, instead of fake
 *   addresses that do not exist
 * - Placeholder text and recovery hint now reflect the documented
 *   alphanumeric USER ID format
 *
 * SOURCE: Issue report - items 7, 9
 *
 * @version 18.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();

$error = '';
$formData = array('email' => '');
$csrfToken = getCsrfToken();

if (isLoggedIn())
{
    redirectToDashboard();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $identifier = trim($_POST['email'] ?? '');
    $passwordInput = $_POST['password'] ?? '';
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';

    $formData['email'] = $identifier;

    if (empty($identifier) || empty($passwordInput))
    {
        $error = 'Please enter both email/username and password.';
    }
    else
    {
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

$pageTitle = 'Sign in';

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

// Load demo accounts from the single source of truth
$demoAccountsFile = dirname(__DIR__, 2) . '/config/demo_accounts.php';
$demoAccounts = file_exists($demoAccountsFile)
    ? require $demoAccountsFile
    : array();

// Show at most one demo account per role
$demoDisplay = array();
$seenRoles = array();

foreach ($demoAccounts as $account)
{
    if (!in_array($account['account_type'], $seenRoles))
    {
        $demoDisplay[] = $account;
        $seenRoles[] = $account['account_type'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title>Sign in · Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/public.css">
    <style nonce="<?php echo escapeOutput(CSP_NONCE); ?>">
        .demo-accounts
        {
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: var(--space-4);
            padding-top: var(--space-4);
            border-top: 1px solid var(--gray-200);
            text-align: left;
        }

        .demo-accounts strong
        {
            display: block;
            margin-bottom: var(--space-2);
            color: var(--gray-700);
        }

        .demo-accounts table
        {
            width: 100%;
            border-collapse: collapse;
        }

        .demo-accounts th,
        .demo-accounts td
        {
            padding: var(--space-1) var(--space-2);
            text-align: left;
            font-size: 0.7rem;
        }

        .demo-accounts th
        {
            color: var(--gray-500);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .demo-accounts td
        {
            font-family: var(--font-mono);
            color: var(--gray-700);
        }
    </style>
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
                        <label class="form-label" for="email">User ID or Email</label>
                        <div class="input-wrapper">
                            <i class="fas fa-envelope input-icon"></i>
                            <input type="text"
                                   id="email"
                                   name="email"
                                   class="form-control"
                                   required
                                   value="<?php echo escapeOutput($formData['email']); ?>"
                                   placeholder="16-character User ID or you@campus.edu"
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

                    <button type="submit" class="btn btn-primary btn-block btn-lg">
                        <i class="fas fa-arrow-right"></i> Sign in
                    </button>
                </form>

                <?php if (!empty($demoDisplay)): ?>
                    <div class="demo-accounts">
                        <strong>Demo accounts (real credentials)</strong>
                        <table>
                            <thead>
                                <tr>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Password</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($demoDisplay as $account): ?>
                                    <tr>
                                        <td><?php echo escapeOutput(ucfirst($account['account_type'])); ?></td>
                                        <td><?php echo escapeOutput($account['email']); ?></td>
                                        <td><?php echo escapeOutput($account['password']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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