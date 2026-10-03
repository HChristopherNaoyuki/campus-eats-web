<?php
/**
 * Firebase Configuration API Endpoint
 *
 * Serves the Firebase client configuration to the browser as JSON.
 * The browser JavaScript fetches this once on page load and uses it
 * to initialize the Firebase Web SDK.
 *
 * CORRECTIONS (Version 3.0 - Technical Audit and Fixes Report):
 * - The allowed origin is now read from the ALLOWED_CORS_ORIGIN
 *   constant defined in Solution/config/constants.php. The previous
 *   version hard-coded https://campuseats.example.com, which meant
 *   that development hosts received an empty CORS header and could
 *   not fetch the configuration. The constant allows each deployment
 *   to set the correct origin without modifying this file.
 * - The endpoint continues to require GET. OPTIONS is answered with
 *   200 and an empty body so preflight requests succeed.
 * - The endpoint continues to return a clear JSON error when Firebase
 *   is not configured.
 *
 * SOURCE: Technical Audit and Fixes Report.
 * SOURCE: Review item 14 - Firebase configuration.
 *
 * @version 3.0
 */

header('Content-Type: application/json');

// =============================================================================
// CORS Header
// =============================================================================
//
// The allowed origin is read from the ALLOWED_CORS_ORIGIN constant. The
// constant is defined in Solution/config/constants.php. When the
// constant is not defined or is an empty string, the header is omitted
// so the browser applies its default same-origin policy.
// =============================================================================

$allowedOrigin = defined('ALLOWED_CORS_ORIGIN') ? ALLOWED_CORS_ORIGIN : '';
$requestOrigin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

if ($allowedOrigin !== '' && $requestOrigin === $allowedOrigin)
{
    header('Access-Control-Allow-Origin: ' . $allowedOrigin);
}

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
    echo json_encode(array(
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ));
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