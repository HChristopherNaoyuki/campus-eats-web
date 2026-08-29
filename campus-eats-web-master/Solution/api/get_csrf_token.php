<?php
/**
 * Get CSRF Token API Endpoint
 *
 * This endpoint returns a new CSRF token for the frontend.
 * Used to refresh tokens before cart operations.
 *
 * CORRECTIONS (Version 2.0 - CORS and Stability):
 * - Replaced wildcard CORS header with origin-reflection pattern.
 * - Removed indiscriminate token regeneration on every GET request.
 * - Token is now regenerated only when a new one is needed, not on every call.
 * - Added validation to ensure token is session-bound.
 * - Added proper error handling and logging.
 *
 * SOURCE: Software Engineering Incident Report (2026-06-25)
 * SOURCE: Full Code Review Report - Section 1.1 & 1.3
 *
 * @version 2.0
 */

// Set JSON content type before any output
header('Content-Type: application/json');

// =============================================================================
// CORS Header - CORRECTION: Origin-Reflection Pattern
// =============================================================================
// This reflects the request origin if it matches the allowed application origin.
// This is the secure pattern used in delete_menu_item.php and other corrected files.
// It prevents any external domain from accessing this endpoint.
// Source: Full Code Review Report - Section 1.1
// =============================================================================
header
(
    'Access-Control-Allow-Origin: ' .
    (
        isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === 'https://campuseats.example.com'
        ? $_SERVER['HTTP_ORIGIN']
        : ''
    )
);
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
{
    http_response_code(200);
    exit();
}

// Load required dependencies
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php'; // auth.php includes session handling
require_once dirname(__DIR__) . '/config/error_logging.php';

// Start secure session
startSecureSession();

// Verify HTTP method is GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET')
{
    http_response_code(405);
    echo json_encode(array(
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ));
    exit();
}

// =============================================================================
// Token Handling - CORRECTION: Stable Token Management
// =============================================================================
// The CSRF token should be stable for the duration of a session.
// Regenerating it on every GET request creates a race condition where a page
// with multiple concurrent AJAX calls can invalidate its own token.
// The token is only generated or regenerated if it does not exist or
// if the caller explicitly requests a new one.
// Source: Full Code Review Report - Section 1.3
// =============================================================================
try
{
    // Check if a new token is requested, or if one doesn't exist.
    $requestNew = isset($_GET['refresh']) && $_GET['refresh'] === 'true';
    $currentToken = getCsrfToken(); // This function only generates if absent.

    // If a new token is explicitly requested, force regeneration.
    if ($requestNew)
    {
        $currentToken = generateCsrfToken(true);
        writeLog("CSRF token explicitly refreshed via API", "SECURITY");
    }

    $tokenVersion = $_SESSION['csrf_token_version'] ?? 0;

    echo json_encode(array(
        'success' => true,
        'csrf_token' => $currentToken,
        'version' => $tokenVersion,
        'message' => 'CSRF token retrieved successfully.'
    ));
}
catch (Exception $e)
{
    writeLog('Get CSRF token error: ' . $e->getMessage(), "SECURITY_ERROR");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Unable to generate CSRF token. Please try again later.'
    ));
}
?>