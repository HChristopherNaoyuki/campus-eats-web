<?php
/**
 * Submit Feedback Page
 *
 * Allows a logged-in student, standard user, or vendor to submit a
 * complaint or compliment. The record is written to MySQL, which is the
 * authoritative store. A projection is then written to Firebase so the
 * client-side feedback view can read it in real time.
 *
 * COMPLIANCE WITH FIREBASE RULES
 *
 * The Firebase Realtime Database rules for the feedback node require
 * the following fields to be present when a record is written:
 *
 *   userId, type, subject, message, userName, userEmail, status,
 *   createdAt, updatedAt
 *
 * The type field must be the lowercase string complaint or compliment.
 * The status field must be the lowercase string pending or resolved.
 * The createdAt and updatedAt fields must be non-empty strings.
 *
 * This page constructs the Firebase payload through the
 * FirebaseSyncHelper class, which enforces those constraints before
 * the write is attempted. If the payload cannot satisfy the rules, the
 * helper throws, and this page records the failure without affecting
 * the MySQL record, which has already been committed.
 *
 * The existing database rules are not modified by this page.
 *
 * SOURCE: Existing Firebase Realtime Database rules, feedback node.
 * SOURCE: Campus Eats Technical Audit Report, Section 3.3.
 * SOURCE: Review item 7.
 *
 * @version 17.0
 */

require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/auth.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/error_logging.php';
require_once dirname(__DIR__, 2) . '/config/firebase_sync_helper.php';

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
                // Step one: write the authoritative record to MySQL.
                $mysqlEntryId = $db->insert(
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

                if (!$mysqlEntryId)
                {
                    $error = 'Failed to submit feedback. Please try again later.';
                }
                else
                {
                    $typeLabel = ($entryType === 'complaint') ? 'Complaint' : 'Compliment';
                    $success = "Your $typeLabel has been submitted successfully. "
                             . "An administrator will review it.";
                    $formData = array('entry_type' => 'compliment', 'subject' => '', 'message' => '');
                    $csrfToken = getCsrfToken();

                    writeLog(
                        "Feedback submitted by user $userId (Role: $accountType) "
                            . "to MySQL with entry ID $mysqlEntryId",
                        "FEEDBACK"
                    );

                    // Step two: project the record to Firebase so the
                    // client-side view can read it in real time. The
                    // projection is best-effort. A failure here does not
                    // undo the MySQL write, which remains authoritative.
                    //
                    // The Firebase write requires an ID token. The token
                    // is supplied by the client through the JavaScript
                    // layer and stored in the session by the client-side
                    // authentication flow. When the token is not present,
                    // the projection is skipped and a log entry records
                    // the reason.
                    $firebaseIdToken = isset($_SESSION['firebase_id_token'])
                        ? $_SESSION['firebase_id_token']
                        : null;

                    if ($firebaseIdToken === null || $firebaseIdToken === '')
                    {
                        writeLog(
                            "Firebase feedback projection skipped: "
                                . "no Firebase ID token in session.",
                            "FIREBASE_SYNC"
                        );
                    }
                    else
                    {
                        try
                        {
                            $syncHelper = new FirebaseSyncHelper($firebaseIdToken);
                            $syncHelper->projectFeedback($mysqlEntryId);

                            writeLog(
                                "Firebase feedback projection succeeded for "
                                    . "MySQL entry ID $mysqlEntryId",
                                "FIREBASE_SYNC"
                            );
                        }
                        catch (Exception $projectionError)
                        {
                            writeLog(
                                "Firebase feedback projection failed for "
                                    . "MySQL entry ID $mysqlEntryId: "
                                    . $projectionError->getMessage(),
                                "FIREBASE_SYNC_ERROR"
                            );
                        }
                    }
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