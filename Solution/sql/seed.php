<?php
/**
 * Database Seeding Script
 *
 * Seeds the database with initial test data for development and testing.
 * Includes 8 hardcoded demo accounts with fixed User IDs and correct roles.
 *
 * @version 14.0
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/user_id.php';
require_once dirname(__DIR__) . '/includes/password_validation.php';

// =============================================================================
// Configuration
// =============================================================================

/**
 * Force mode - when true, forces repair of existing accounts even if they appear correct.
 */
$forceMode = true;

// =============================================================================
// Demo Account Configurations with Fixed User IDs and Correct Roles
// =============================================================================

/**
 * Returns the canonical list of demo accounts as defined in users.txt.
 *
 * @return array Array of demo account definitions
 */
function getDemoAccounts()
{
    return array(
        // Admin Users (2) - Fixed User IDs: 1001, 1002
        array(
            'user_id'      => 1001,
            'full_name'    => 'John Doe',
            'username'     => 'johndoe',
            'email'        => 'john.doe@example.com',
            'password'     => 'AdminPass123!',
            'account_type' => 'admin',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        array(
            'user_id'      => 1002,
            'full_name'    => 'Sarah Wilson',
            'username'     => 'sarahw',
            'email'        => 'sarah.wilson@example.com',
            'password'     => 'AdminPass987%',
            'account_type' => 'admin',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        // Vendor Users (2) - Fixed User IDs: 2001, 2002
        array(
            'user_id'      => 2001,
            'full_name'    => 'Jane Smith',
            'username'     => 'janesmith',
            'email'        => 'jane.smith@example.com',
            'password'     => 'VendorPass456$',
            'account_type' => 'vendor',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => 'Jane\'s Bakery',
            'description'  => 'Fresh baked goods, pastries, and artisanal breads.'
        ),
        array(
            'user_id'      => 2002,
            'full_name'    => 'Michael Brown',
            'username'     => 'mikeb',
            'email'        => 'mike.brown@example.com',
            'password'     => 'VendorPass789#',
            'account_type' => 'vendor',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => 'Brown\'s Deli',
            'description'  => 'Sandwiches, wraps, and daily specials.'
        ),
        // Standard Users (2) - Fixed User IDs: 3001, 3002
        array(
            'user_id'      => 3001,
            'full_name'    => 'Emily Davis',
            'username'     => 'emilyd',
            'email'        => 'emily.davis@example.com',
            'password'     => 'StandardPass321@',
            'account_type' => 'standard',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        array(
            'user_id'      => 3002,
            'full_name'    => 'Robert Taylor',
            'username'     => 'robertt',
            'email'        => 'robert.taylor@example.com',
            'password'     => 'StandardPass654#',
            'account_type' => 'standard',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        // Student Users (2) - Fixed User IDs: 4001, 4002
        array(
            'user_id'      => 4001,
            'full_name'    => 'David Lee',
            'username'     => 'davidl',
            'email'        => 'david.lee@example.com',
            'password'     => 'StudentPass234$',
            'account_type' => 'student',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        array(
            'user_id'      => 4002,
            'full_name'    => 'Maria Garcia',
            'username'     => 'mariag',
            'email'        => 'maria.garcia@example.com',
            'password'     => 'StudentPass567%',
            'account_type' => 'student',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        )
    );
}

/**
 * CORRECTION: Deletes all dependent records for a user before deleting the user.
 *
 * @param int $userId The user ID to clean up
 * @param object $db The database connection object
 * @return void
 */
function cleanupUserDependencies($userId, $db)
{
    try
    {
        $orders = $db->fetchAll
        (
            "SELECT order_id FROM orders WHERE user_id = :user_id",
            array('user_id' => $userId)
        );

        foreach ($orders as $order)
        {
            $db->executeQuery
            (
                "DELETE FROM order_items WHERE order_id = :order_id",
                array('order_id' => $order['order_id'])
            );

            $db->executeQuery
            (
                "DELETE FROM payments WHERE order_id = :order_id",
                array('order_id' => $order['order_id'])
            );
        }

        $db->executeQuery
        (
            "DELETE FROM orders WHERE user_id = :user_id",
            array('user_id' => $userId)
        );

        $db->executeQuery
        (
            "DELETE FROM complaints_compliments WHERE user_id = :user_id",
            array('user_id' => $userId)
        );

        $vendor = $db->fetchOne
        (
            "SELECT vendor_id FROM vendors WHERE vendor_user_id = :user_id",
            array('user_id' => $userId)
        );

        if ($vendor)
        {
            $menuItems = $db->fetchAll
            (
                "SELECT item_id FROM menu_items WHERE vendor_id = :vendor_id",
                array('vendor_id' => $vendor['vendor_id'])
            );

            foreach ($menuItems as $item)
            {
                $db->executeQuery
                (
                    "DELETE FROM order_items WHERE item_id = :item_id",
                    array('item_id' => $item['item_id'])
                );
            }

            $db->executeQuery
            (
                "DELETE FROM menu_items WHERE vendor_id = :vendor_id",
                array('vendor_id' => $vendor['vendor_id'])
            );

            $db->executeQuery
            (
                "DELETE FROM vendors WHERE vendor_user_id = :user_id",
                array('user_id' => $userId)
            );
        }

        $db->executeQuery
        (
            "DELETE FROM user_sessions WHERE user_id = :user_id",
            array('user_id' => $userId)
        );

        writeLog("Cleaned up all dependencies for user ID: $userId", "DATABASE");
    }
    catch (Exception $e)
    {
        writeLog("Error cleaning up dependencies for user $userId: " . $e->getMessage(), "DATABASE_ERROR");
        throw $e;
    }
}

/**
 * ALIGNMENT: Creates or updates a demo account with correct credentials and role.
 *
 * @param array $account The account definition from getDemoAccounts()
 * @param object $db The database connection object
 * @param bool $forceMode When true, forces repair even if account appears correct
 * @return bool True on success, false on failure
 */
function alignDemoAccount($account, $db, $forceMode = false)
{
    $passwordHash = hashPassword($account['password']);

    $existing = $db->fetchOne
    (
        "SELECT `user_id`, `password_hash`, `account_type`, `is_verified`, `is_active`
         FROM `users`
         WHERE `email` = :email OR `username` = :username
         LIMIT 1",
        array(
            'email' => $account['email'],
            'username' => $account['username']
        )
    );

    if ($existing)
    {
        $currentUserId = (int)$existing['user_id'];
        $expectedUserId = (int)$account['user_id'];
        $needsRepair = $forceMode;
        $repairReason = array();

        if ($currentUserId !== $expectedUserId)
        {
            $needsRepair = true;
            $repairReason[] = "User ID mismatch (Expected: {$expectedUserId}, Found: {$currentUserId})";
        }

        if ($existing['account_type'] !== $account['account_type'])
        {
            $needsRepair = true;
            $repairReason[] = "Role mismatch (Expected: {$account['account_type']}, Found: {$existing['account_type']})";
        }

        if (!password_verify($account['password'], $existing['password_hash']))
        {
            $needsRepair = true;
            $repairReason[] = "Password mismatch";
        }

        if ($needsRepair)
        {
            echo "REPAIRING: {$account['email']} - " . implode(', ', $repairReason) . "\n";

            if ($currentUserId !== $expectedUserId)
            {
                $idCheck = $db->fetchOne
                (
                    "SELECT user_id FROM users WHERE user_id = :user_id",
                    array('user_id' => $expectedUserId)
                );

                if ($idCheck)
                {
                    echo "  Conflict: Expected ID {$expectedUserId} is taken. Cleaning up conflicting user...\n";
                    cleanupUserDependencies($expectedUserId, $db);
                    $db->executeQuery
                    (
                        "DELETE FROM users WHERE user_id = :user_id",
                        array('user_id' => $expectedUserId)
                    );
                    echo "  Removed conflicting user ID: {$expectedUserId}\n";
                }
            }

            echo "  Cleaning up dependencies for user ID: {$currentUserId}\n";
            cleanupUserDependencies($currentUserId, $db);

            $db->executeQuery
            (
                "DELETE FROM users WHERE user_id = :user_id",
                array('user_id' => $currentUserId)
            );
            echo "  Deleted user ID: {$currentUserId}\n";

            $result = createDemoAccount($account, $db);

            if ($result)
            {
                echo "  ✓ RECREATED: {$account['email']} (ID: {$account['user_id']}, Role: {$account['account_type']})\n";
            }
            else
            {
                echo "  ✗ FAILED to recreate: {$account['email']}\n";
            }

            return $result;
        }

        echo "✓ VERIFIED: {$account['email']} (ID: {$account['user_id']}, Role: {$account['account_type']})\n";

        if ($account['account_type'] === 'vendor' && !empty($account['vendor_name']))
        {
            ensureVendorProfile($account, $db, $existing['user_id']);
        }

        return true;
    }
    else
    {
        echo "  CREATING: {$account['email']} (ID: {$account['user_id']}, Role: {$account['account_type']})\n";
        return createDemoAccount($account, $db);
    }
}

/**
 * Creates a new demo account with the specified User ID and role.
 *
 * @param array $account The account definition
 * @param object $db The database connection object
 * @return bool True on success, false on failure
 */
function createDemoAccount($account, $db)
{
    $uniqueId = generateUserId($account['account_type']);
    $passwordHash = hashPassword($account['password']);

    try
    {
        $userId = $db->insert
        (
            "INSERT INTO `users`
                (`user_id`, `unique_id`, `full_name`, `username`, `email`, `password_hash`,
                 `account_type`, `is_verified`, `is_active`, `created_at`, `updated_at`)
             VALUES
                (:user_id, :unique_id, :full_name, :username, :email, :password_hash,
                 :account_type, :is_verified, :is_active, NOW(), NOW())",
            array(
                'user_id'       => $account['user_id'],
                'unique_id'     => $uniqueId,
                'full_name'     => $account['full_name'],
                'username'      => $account['username'],
                'email'         => $account['email'],
                'password_hash' => $passwordHash,
                'account_type'  => $account['account_type'],
                'is_verified'   => $account['is_verified'],
                'is_active'     => $account['is_active']
            )
        );

        if ($userId)
        {
            echo "  ✓ CREATED: {$account['email']} (ID: {$account['user_id']}, Role: {$account['account_type']})\n";

            if ($account['account_type'] === 'vendor' && !empty($account['vendor_name']))
            {
                ensureVendorProfile($account, $db, $userId);
            }

            return true;
        }
        else
        {
            echo "  ✗ FAILED to create: {$account['email']}\n";
            return false;
        }
    }
    catch (Exception $e)
    {
        echo "  ✗ ERROR creating {$account['email']}: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Ensures a vendor profile exists for a vendor user.
 *
 * @param array $account The account definition
 * @param object $db The database connection object
 * @param int $userId The user ID of the vendor
 * @return void
 */
function ensureVendorProfile($account, $db, $userId = null)
{
    if ($userId === null)
    {
        $user = $db->fetchOne
        (
            "SELECT user_id FROM users WHERE email = :email",
            array('email' => $account['email'])
        );

        if (!$user)
        {
            echo "  Vendor user not found: {$account['email']}\n";
            return;
        }

        $userId = $user['user_id'];
    }

    $existingVendor = $db->fetchOne
    (
        "SELECT vendor_id FROM vendors WHERE vendor_user_id = :user_id",
        array('user_id' => $userId)
    );

    if ($existingVendor)
    {
        $db->executeQuery
        (
            "UPDATE vendors SET vendor_name = :vendor_name, description = :description, updated_at = NOW()
             WHERE vendor_user_id = :user_id",
            array(
                'vendor_name' => $account['vendor_name'],
                'description' => $account['description'] ?? 'Campus food vendor.',
                'user_id' => $userId
            )
        );
        echo "  Vendor profile updated for: {$account['vendor_name']}\n";
        return;
    }

    $db->insert
    (
        "INSERT INTO vendors
            (vendor_user_id, vendor_name, description, is_open, is_approved, created_at)
         VALUES
            (:user_id, :vendor_name, :description, 1, 1, NOW())",
        array(
            'user_id'     => $userId,
            'vendor_name' => $account['vendor_name'],
            'description' => $account['description'] ?? 'Campus food vendor.'
        )
    );

    echo "  Vendor profile created for: {$account['vendor_name']}\n";
}

// =============================================================================
// Main Execution
// =============================================================================

echo "========================================\n";
echo "Campus Eats - Demo Account Alignment\n";
echo "========================================\n\n";

$db = getDB();

try
{
    $db->executeQuery(
        "ALTER TABLE `users` 
         MODIFY COLUMN `account_type` 
         ENUM('admin', 'vendor', 'student', 'standard') 
         NOT NULL 
         COMMENT 'User role: admin, vendor, student, or standard'"
    );
    echo "✓ Updated account_type ENUM to include 'standard'.\n\n";
}
catch (Exception $e)
{
    echo "ℹ Note: account_type ENUM already supports 'standard'.\n\n";
}

$demoAccounts = getDemoAccounts();

echo "Aligning " . count($demoAccounts) . " demo accounts with force mode: " . ($forceMode ? 'ON' : 'OFF') . "\n\n";

$successCount = 0;
$failCount = 0;

foreach ($demoAccounts as $account)
{
    try
    {
        if (alignDemoAccount($account, $db, $forceMode))
        {
            $successCount++;
        }
        else
        {
            $failCount++;
        }
    }
    catch (Exception $e)
    {
        echo "  ✗ ERROR processing {$account['email']}: " . $e->getMessage() . "\n";
        $failCount++;
    }
}

echo "\n========================================\n";
echo "Alignment Summary:\n";
echo "  - Successful: $successCount\n";
echo "  - Failed: $failCount\n";
echo "========================================\n";

// =============================================================================
// Verification Section
// =============================================================================

echo "\n========================================\n";
echo "Verification Results:\n";
echo "========================================\n";

$verification = verifyDemoAccounts($db, $demoAccounts);

echo "\nSummary:\n";
echo "  - Total Accounts: {$verification['total']}\n";
echo "  - Valid: {$verification['valid']}\n";
echo "  - Invalid: {$verification['invalid']}\n";

if (!empty($verification['errors']))
{
    echo "\nErrors Found:\n";
    foreach ($verification['errors'] as $error)
    {
        echo "  - $error\n";
    }
}

// =============================================================================
// Specific Verification for David Lee
// =============================================================================

echo "\n========================================\n";
echo "David Lee Verification:\n";
echo "========================================\n";

$david = $db->fetchOne
(
    "SELECT user_id, username, account_type, is_verified, is_active
     FROM users
     WHERE username = 'davidl'"
);

if ($david)
{
    echo "Username: davidl\n";
    echo "User ID: {$david['user_id']}\n";
    echo "Role: {$david['account_type']}\n";
    echo "Verified: " . ($david['is_verified'] ? 'Yes' : 'No') . "\n";
    echo "Active: " . ($david['is_active'] ? 'Yes' : 'No') . "\n";

    if ($david['account_type'] === 'student')
    {
        echo "\n✓ David Lee is correctly assigned as a STUDENT.\n";
    }
    else
    {
        echo "\n✗ ERROR: David Lee is assigned as {$david['account_type']}. Expected: student.\n";
        echo "  Please run the script again with forceMode = true.\n";
    }
}
else
{
    echo "✗ ERROR: User 'davidl' not found in the database!\n";
}

echo "\n========================================\n";
echo "Demo account alignment completed.\n";
echo "========================================\n";