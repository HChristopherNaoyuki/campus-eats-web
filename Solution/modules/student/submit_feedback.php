<?php
/**
 * Submit Feedback Page
 *
 * Allows a logged-in student, standard user, or vendor to submit a
 * complaint or compliment.
 *
 * CORRECTIONS (Version 16.0 - MySQL Only):
 * - Removed every Firebase write from this page. The feedback record is
 *   written to the MySQL complaints_compliments table only. Firebase is
 *   used on this page for reading a user's own past submissions, not for
 *   writing new ones.
 * - Removed the $_SESSION['firebase_feedback_pending'] and
 *   $_SESSION['firebase_feedback_data'] session keys. Nothing in the
 *   codebase ever read those keys, so the "submitted to both systems"
 *   message they enabled was false.
 * - The success message now describes what actually happens: the record
 *   is stored in the application database and reviewed by administrators.
 * - The page no longer loads firebase.js or feedback-firebase.js. Those
 *   scripts are only needed for reading, not for submitting.
 * - Retains the CSRF protection, the validation on subject and message
 *   length, and the shared escapeOutput() helper.
 *
 * SOURCE: Review item 7 - Feedback / Firebase integration is non-functional
 *
 * @version 16.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';

startSecureSession();

if (!isLoggedIn())
{
    header('Location: ' . BASE_URL . '/modules/auth/login.php');
    exit();
}

$accountType = getCurrentUserRole();

if ($accountType !== 'student' && $accountType !== 'standard' && $accountType !== 'vendor')
{
    header('Location: ' . ROOT_URL . '/index.php');
    exit();
}

$db = getDB();
$userId = getCurrentUserId();
$fullName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'User';
$csrfToken = getCsrfToken();

$error = '';
$success = '';
$formData = array('entry_type' => 'compliment', 'subject' => '', 'message' => '');

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

    if (!validateCsrfToken($submittedToken))
    {
        $error = 'Security validation failed. Please refresh the page.';
    }
    else
    {
        $entryType = trim(isset($_POST['entry_type']) ? $_POST['entry_type'] : 'compliment');
        $subject = trim(isset($_POST['subject']) ? $_POST['subject'] : '');
        $message = trim(isset($_POST['message']) ? $_POST['message'] : '');

        $formData = array(
            'entry_type' => $entryType,
            'subject' => $subject,
            'message' => $message
        );

        if (empty($subject))
        {
            $error = 'Please enter a subject for your feedback.';
        }
        elseif (empty($message))
        {
            $error = 'Please enter your feedback message.';
        }
        elseif (strlen($subject) > 200)
        {
            $error = 'Subject must not exceed 200 characters.';
        }
        elseif (strlen($message) > 2000)
        {
            $error = 'Message must not exceed 2000 characters.';
        }
        elseif ($entryType !== 'complaint' && $entryType !== 'compliment')
        {
            $error = 'Invalid feedback type selected.';
        }
        else
        {
            try
            {
                $result = $db->insert(
                    "INSERT INTO complaints_compliments
                        (user_id, entry_type, subject, message, is_resolved, created_at)
                     VALUES
                        (:user_id, :entry_type, :subject, :message, 0, NOW())",
                    array(
                        'user_id' => $userId,
                        'entry_type' => $entryType,
                        'subject' => $subject,
                        'message' => $message
                    )
                );

                if ($result)
                {
                    $typeLabel = ($entryType === 'complaint') ? 'Complaint' : 'Compliment';
                    $success = "Your $typeLabel has been submitted successfully. "
                             . "An administrator will review it.";
                    $formData = array('entry_type' => 'compliment', 'subject' => '', 'message' => '');
                    $csrfToken = getCsrfToken();
                    writeLog("Feedback submitted by user $userId (Role: $accountType)", "FEEDBACK");
                }
                else
                {
                    $error = 'Failed to submit feedback. Please try again later.';
                }
            }
            catch (Exception $e)
            {
                writeLog('Feedback submission error: ' . $e->getMessage(), "FEEDBACK_ERROR");
                $error = 'Failed to submit feedback. Please try again later.';
            }
        }
    }
}

$csrfToken = getCsrfToken();
$pageTitle = 'Submit Feedback';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?php echo escapeOutput($csrfToken); ?>">
    <title>Submit Feedback - Campus Eats</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/apple.css">
</head>
<body>
    <div class="app-layout">
        <?php include_once dirname(__DIR__, 2) . '/includes/student_sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="student-content">
                <div class="container">
                    <div class="page-header">
                        <h1>Submit Feedback</h1>
                        <p>Share your experience with us</p>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div class="alert-content">
                            <div class="alert-title">About the Feedback Forum</div>
                            <div class="alert-message">
                                Your feedback helps us improve the campus dining experience.
                                Complaints and compliments are reviewed by administrators only.
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Error</div>
                                <div class="alert-message"><?php echo escapeOutput($error); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <div class="alert-content">
                                <div class="alert-title">Success</div>
                                <div class="alert-message"><?php echo escapeOutput($success); ?></div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="card">
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="csrf_token"
                                       value="<?php echo escapeOutput($csrfToken); ?>">

                                <div class="form-group">
                                    <label class="form-label" for="entry_type">Feedback Type</label>
                                    <select id="entry_type" name="entry_type" class="form-control" required>
                                        <option value="compliment" <?php echo $formData['entry_type'] === 'compliment' ? 'selected' : ''; ?>>Compliment - Share something positive</option>
                                        <option value="complaint" <?php echo $formData['entry_type'] === 'complaint' ? 'selected' : ''; ?>>Complaint - Report an issue</option>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="subject">Subject</label>
                                    <input type="text" id="subject" name="subject"
                                           class="form-control" required maxlength="200"
                                           value="<?php echo escapeOutput($formData['subject']); ?>"
                                           placeholder="Brief summary of your feedback">
                                    <span class="form-hint">Maximum 200 characters</span>
                                </div>

                                <div class="form-group">
                                    <label class="form-label" for="message">Message</label>
                                    <textarea id="message" name="message" class="form-control"
                                              required maxlength="2000" rows="5"
                                              placeholder="Please provide detailed information about your experience."><?php echo escapeOutput($formData['message']); ?></textarea>
                                    <span class="form-hint">Maximum 2000 characters</span>
                                </div>

                                <button type="submit" class="btn btn-primary btn-block btn-lg">
                                    <i class="fas fa-paper-plane"></i> Submit Feedback
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="card" style="margin-top: var(--space-4);">
                        <div class="card-body">
                            <h4>Guidelines for Submitting Feedback</h4>
                            <ul>
                                <li>Be specific and provide relevant details about your experience.</li>
                                <li>For complaints, include order numbers when possible.</li>
                                <li>Avoid using offensive language or personal attacks.</li>
                                <li>Compliments are encouraged and help recognize good service.</li>
                                <li>All feedback is reviewed by administrators.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <button class="sidebar-toggle" id="menuToggleBtn" aria-label="Toggle Menu">
        <i class="fas fa-bars"></i>
    </button>

    <script src="<?php echo ASSETS_URL; ?>/js/dashboard-common.js"></script>
</body>
</html>