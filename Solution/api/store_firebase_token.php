<?php
/**
 * Store Firebase ID Token Endpoint
 *
 * Receives a Firebase ID token from the client-side authentication
 * bridge and stores it in the PHP session. The token is later used by
 * the FirebaseSyncHelper when the application performs a server-side
 * write on behalf of the current user.
 *
 * The endpoint does not modify, bypass, weaken, or replace the
 * Firebase Realtime Database rules. It supplies the token that the
 * rules require when a write is performed.
 *
 * SECURITY
 *
 * The endpoint requires an authenticated application session. The
 * token is stored in the session only. It is never written to a file,
 * a database, or a log. The token is short-lived. The client refreshes
 * it periodically.
 *
 * SOURCE: Existing Firebase Realtime Database rules.
 * SOURCE: Campus Eats Technical Audit Report, Section 4.
 *
 * @version 1.0
 */

header('Content-Type: application/json');

header(
    'Access-Control-Allow-Origin: ' .
    (
        isset($_SERVER['HTTP_ORIGIN']) &&
        $_SERVER['HTTP_ORIGIN'] === 'https://campuseats.example.com'
        ? $_SERVER['HTTP_ORIGIN']
        : ''
    )
);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
    http_response_code(200);
    exit();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

startSecureSession();

if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'message' => 'Authentication required.'
    ));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST')
{
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Method not allowed. Use POST.'
    ));
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input))
{
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'Invalid request body.'
    ));
    exit();
}

$submittedToken = isset($_SERVER['HTTP_X_CSRF_TOKEN'])
    ? $_SERVER['HTTP_X_CSRF_TOKEN']
    : (isset($input['csrf_token']) ? $input['csrf_token'] : '');

if (!validateCsrfToken($submittedToken, false))
{
    http_response_code(403);
    echo json_encode(array(
        'success' => false,
        'message' => 'Security validation failed.'
    ));
    exit();
}

$idToken = isset($input['idToken']) ? trim((string)$input['idToken']) : '';

if ($idToken === '')
{
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'idToken is required.'
    ));
    exit();
}

// A Firebase ID token is a compact JWT with three dot-separated
// segments. The endpoint performs a structural check only. The token
// is validated by the Firebase server when it is used in a REST API
// request. A structural check here prevents a malformed value from
// being stored in the session.
$segments = explode('.', $idToken);

if (count($segments) !== 3)
{
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'idToken is not a valid JWT.'
    ));
    exit();
}

$_SESSION['firebase_id_token'] = $idToken;
$_SESSION['firebase_id_token_stored_at'] = time();

writeLog(
    "Firebase ID token stored in session for user ID " . getCurrentUserId(),
    "FIREBASE_AUTH"
);

echo json_encode(array(
    'success' => true,
    'message' => 'Firebase ID token stored.',
    'storedAt' => $_SESSION['firebase_id_token_stored_at']
));