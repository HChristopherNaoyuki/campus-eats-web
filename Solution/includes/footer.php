<?php
// =============================================================================
// CORRECTION: Gate feedback JS on role, not just page basename
// Source: Issue report - item 22
// =============================================================================

$currentPage = basename($_SERVER['PHP_SELF']);
$accountType = isset($_SESSION['account_type']) ? $_SESSION['account_type'] : '';
$isStudentOrStandard = in_array($accountType, array('student', 'standard'));

$loadFeedbackModule = false;

if ($currentPage === 'submit_feedback.php' && $isStudentOrStandard)
{
    $loadFeedbackModule = true;
}
elseif ($currentPage === 'view_feedback.php' && $accountType === 'admin')
{
    $loadFeedbackModule = true;
}
// Note: dashboard.php is intentionally excluded. Feedback submission
// is not part of the dashboard experience.
?>

<?php if ($loadFeedbackModule): ?>
    <script src="<?php echo ASSETS_URL; ?>/js/firebase.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/feedback-firebase.js"></script>
    <script nonce="<?php echo escapeOutput(CSP_NONCE); ?>">
        document.addEventListener('DOMContentLoaded', function()
        {
            if (typeof window.Feedback !== 'undefined' &&
                typeof window.FIREBASE_USER_CONTEXT !== 'undefined')
            {
                window.Feedback.init(window.FIREBASE_USER_CONTEXT);
            }
        });
    </script>
<?php endif; ?>