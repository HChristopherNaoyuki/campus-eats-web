<?php
/**
 * Get Vendors API Endpoint
 *
 * Returns a list of approved, verified, and active vendors.
 * This endpoint requires authentication and uses parameterized queries.
 *
 * CORRECTIONS (Version 6.0):
 * - Fixed deprecated session.php include - now uses auth.php directly.
 * - Added parameterized query with proper bound parameters.
 * - Added authentication requirement before returning vendor data.
 * - Returns generic error messages only (no internal details exposed).
 * - Uses secure session start.
 * - Added input validation for optional parameters.
 * - Added pagination support for large vendor lists.
 * - Added vendor open/closed status filtering.
 * - Added search functionality for vendor names and descriptions.
 *
 * Source: campus-eats-process-document.pdf (Section 6.1 - Browse vendors)
 * Source: Error log deprecation warnings - Issue fixed
 *
 * @version 6.0
 */

// Set JSON content type before any output
header('Content-Type: application/json');

// Set CORS headers for API access
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

// Load required dependencies - FIXED: Using auth.php directly instead of session.php
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

// Start secure session
startSecureSession();

// =============================================================================
// Authentication Check
// =============================================================================

// SECURITY FIX: Require authentication before returning vendor list.
// This prevents unauthorized access to vendor information.
if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'message' => 'Authentication required. Please log in to view vendors.'
    ));
    exit();
}

// =============================================================================
// Method Validation
// =============================================================================

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
// Parameter Handling with Validation
// =============================================================================

// Get pagination parameters with validation
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 20;
$offset = ($page - 1) * $perPage;

// Get search parameter with sanitization
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get category filter parameter
$category = isset($_GET['category']) ? trim($_GET['category']) : '';

// Get status filter parameter (open, closed, all)
$statusFilter = isset($_GET['status']) ? trim($_GET['status']) : 'all';

// =============================================================================
// Database Query with Parameterization
// =============================================================================

try
{
    $db = getDB();

    // Build the base SQL query with parameterized placeholders
    // CORRECTION: Used parameterized query instead of concatenation
    $sql = "SELECT
                v.vendor_id,
                v.vendor_name,
                v.business_name,
                v.description,
                v.operating_hours,
                v.is_open,
                v.is_approved,
                v.created_at,
                u.is_active,
                u.is_verified
            FROM vendors v
            INNER JOIN users u ON v.vendor_user_id = u.user_id
            WHERE u.is_verified = 1
              AND u.is_active = 1
              AND v.is_approved = 1";

    $params = array();

    // Add search condition if provided
    if (!empty($search))
    {
        $sql .= " AND (v.vendor_name LIKE :search OR v.business_name LIKE :search OR v.description LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }

    // Add category filter if provided
    if (!empty($category))
    {
        $sql .= " AND v.cuisine_type = :category";
        $params['category'] = $category;
    }

    // Add status filter if provided
    if ($statusFilter === 'open')
    {
        $sql .= " AND v.is_open = 1";
    }
    elseif ($statusFilter === 'closed')
    {
        $sql .= " AND v.is_open = 0";
    }

    // Add ordering and pagination
    $sql .= " ORDER BY v.vendor_name ASC LIMIT :limit OFFSET :offset";
    $params['limit'] = $perPage;
    $params['offset'] = $offset;

    // Execute parameterized query
    // Note: LIMIT and OFFSET need special handling as they are integers
    $pdo = $db->getConnection();
    $stmt = $pdo->prepare($sql);

    // Bind parameters with appropriate types
    foreach ($params as $key => $value)
    {
        if ($key === 'limit' || $key === 'offset')
        {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        }
        else
        {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->execute();
    $vendors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countSql = "SELECT COUNT(*) as total
                 FROM vendors v
                 INNER JOIN users u ON v.vendor_user_id = u.user_id
                 WHERE u.is_verified = 1
                   AND u.is_active = 1
                   AND v.is_approved = 1";

    $countParams = array();

    if (!empty($search))
    {
        $countSql .= " AND (v.vendor_name LIKE :search OR v.business_name LIKE :search OR v.description LIKE :search)";
        $countParams['search'] = '%' . $search . '%';
    }

    if (!empty($category))
    {
        $countSql .= " AND v.cuisine_type = :category";
        $countParams['category'] = $category;
    }

    if ($statusFilter === 'open')
    {
        $countSql .= " AND v.is_open = 1";
    }
    elseif ($statusFilter === 'closed')
    {
        $countSql .= " AND v.is_open = 0";
    }

    $countStmt = $pdo->prepare($countSql);

    foreach ($countParams as $key => $value)
    {
        $countStmt->bindValue(':' . $key, $value, PDO::PARAM_STR);
    }

    $countStmt->execute();
    $totalResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalVendors = (int)($totalResult['total'] ?? 0);
    $totalPages = ceil($totalVendors / $perPage);

    // Sanitize output data to prevent XSS
    foreach ($vendors as &$vendor)
    {
        $vendor['vendor_name'] = htmlspecialchars($vendor['vendor_name'], ENT_QUOTES, 'UTF-8');
        $vendor['business_name'] = isset($vendor['business_name'])
            ? htmlspecialchars($vendor['business_name'], ENT_QUOTES, 'UTF-8')
            : null;
        $vendor['description'] = isset($vendor['description'])
            ? htmlspecialchars($vendor['description'], ENT_QUOTES, 'UTF-8')
            : null;
    }

    // Return success response with vendor data
    echo json_encode(array(
        'success' => true,
        'vendors' => $vendors,
        'pagination' => array(
            'current_page' => $page,
            'per_page' => $perPage,
            'total_vendors' => $totalVendors,
            'total_pages' => $totalPages
        )
    ));

    writeLog("Vendors API called - Returned " . count($vendors) . " vendors", "API");
}
catch (PDOException $e)
{
    // Log the actual error for debugging
    writeLog('get_vendors.php PDO error: ' . $e->getMessage(), "API_ERROR");

    // Return generic error message to user (no internal details)
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Unable to load vendors. Please try again later.'
    ));
}
catch (Exception $e)
{
    // Log the actual error for debugging
    writeLog('get_vendors.php error: ' . $e->getMessage(), "API_ERROR");

    // Return generic error message to user
    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'An unexpected error occurred. Please try again later.'
    ));
}
?>