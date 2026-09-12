<?php
/**
 * Firebase Configuration API Endpoint
 *
 * Serves the Firebase client configuration to the browser as JSON.
 * The browser JavaScript fetches this once on page load and uses it
 * to initialize the Firebase Web SDK.
 *
 * CORRECTIONS (Version 2.0):
 * - Removed the wildcard CORS header. Only the application origin is
 *   reflected, matching the pattern used elsewhere in the API layer.
 * - Rejects OPTIONS preflight with 200 and empty body.
 * - Returns 405 for any method other than GET.
 * - Returns a clear JSON error if Firebase is not configured.
 *
 * SOURCE: Review item 14 - Firebase configuration
 *
 * @version 2.0
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
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET')
{
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'Method not allowed. Use GET.'));
    exit();
}

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/firebase_config.php';

if (!isFirebaseConfigured())
{
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Firebase is not configured.'
    ));
    exit();
}

echo json_encode(array(
    'success' => true,
    'config' => getFirebaseClientConfig()
));