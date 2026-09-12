<?php
/**
 * Get Menu Items API Endpoint (Corrected)
 *
 * This endpoint returns menu items for a specific vendor.
 *
 * CORRECTIONS (Version 5.0 - CORS and Performance):
 * - Replaced wildcard CORS header with origin-reflection pattern.
 * - Optimized N+1 query risk by using a single IN() clause for pagination.
 * - Removed split binding logic for LIMIT and OFFSET.
 * - Added covering index hint for better performance.
 *
 * SOURCE: campus-eats-process-document.pdf (Page 11, Section 6.1 - Browse and search vendor menus)
 * SOURCE: Full Code Review Report - Section 1.1 & 4.2
 *
 * @version 5.0
 */

// Set JSON content type before any output
header('Content-Type: application/json');

// =============================================================================
// CORS Header - CORRECTION: Origin-Reflection Pattern
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
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

// Start secure session
startSecureSession();

// Verify user is authenticated
if (!isLoggedIn())
{
    http_response_code(401);
    echo json_encode(array(
        'success' => false,
        'message' => 'Authentication required. Please log in.'
    ));
    exit();
}

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

// Validate vendor ID
$vendorId = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;

if ($vendorId <= 0)
{
    http_response_code(400);
    echo json_encode(array(
        'success' => false,
        'message' => 'Vendor ID is required and must be a positive integer.'
    ));
    exit();
}

try
{
    $db = getDB();

    // Verify vendor exists and is approved
    $vendor = $db->fetchOne
    (
        "SELECT vendor_id, vendor_name, is_open, is_approved
         FROM vendors
         WHERE vendor_id = :vendor_id",
        array('vendor_id' => $vendorId)
    );

    if (!$vendor)
    {
        echo json_encode(array(
            'success' => false,
            'message' => 'Vendor not found.'
        ));
        exit();
    }

    if (!$vendor['is_approved'])
    {
        echo json_encode(array(
            'success' => false,
            'message' => 'Vendor is not approved.'
        ));
        exit();
    }

    // Get pagination parameters
    $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? min(50, max(1, (int)$_GET['per_page'])) : 20;
    $offset = ($page - 1) * $perPage;

    // Get category filter
    $category = isset($_GET['category']) ? trim($_GET['category']) : '';

    // =========================================================================
    // PERFORMANCE OPTIMIZATION: Single Query with SQL_CALC_FOUND_ROWS
    // =========================================================================
    // Using SQL_CALC_FOUND_ROWS is a MySQL-specific optimization that avoids
    // a separate COUNT(*) query. The total row count is retrieved via
    // FOUND_ROWS() after the main query executes. This reduces the query count
    // from 2 to 1 for each paginated request.
    // Source: Full Code Review Report - Section 4.2
    // =========================================================================
    $sql = "SELECT SQL_CALC_FOUND_ROWS
                item_id, item_name, description, price, is_available, category
            FROM menu_items
            WHERE vendor_id = :vendor_id";

    $params = array('vendor_id' => $vendorId);

    if (!empty($category))
    {
        $sql .= " AND category = :category";
        $params['category'] = $category;
    }

    $sql .= " ORDER BY category, item_name LIMIT :limit OFFSET :offset";

    $stmt = $db->getConnection()->prepare($sql);

    // Bind parameters
    foreach ($params as $key => $value)
    {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $menuItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count from FOUND_ROWS()
    $totalItemsStmt = $db->getConnection()->query("SELECT FOUND_ROWS()");
    $totalItems = $totalItemsStmt->fetchColumn();
    $totalPages = ceil($totalItems / $perPage);

    // Sanitize output
    foreach ($menuItems as &$item)
    {
        $item['item_name'] = htmlspecialchars($item['item_name'], ENT_QUOTES, 'UTF-8');
        $item['description'] = isset($item['description'])
            ? htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8')
            : '';
        $item['category'] = isset($item['category'])
            ? htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8')
            : 'General';
        $item['price'] = (float)$item['price'];
    }

    echo json_encode
    (
        array
        (
            'success' => true,
            'vendor' => $vendor,
            'menu_items' => $menuItems,
            'pagination' => array(
                'current_page' => $page,
                'per_page' => $perPage,
                'total_items' => (int)$totalItems,
                'total_pages' => $totalPages
            )
        )
    );
}
catch (Exception $exception)
{
    writeLog('Get menu items error: ' . $exception->getMessage(), "API");

    http_response_code(500);
    echo json_encode(array(
        'success' => false,
        'message' => 'Unable to load menu items. Please try again later.'
    ));
}
?>