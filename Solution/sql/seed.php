<?php
/**
 * Database Seeding Script
 *
 * Inserts the demonstration accounts and their associated vendor
 * profiles into the MySQL database.
 *
 * IMPORTANT: DEMONSTRATION DATA
 *
 * The accounts inserted by this script are the ten accounts defined in
 * Solution/config/demo_accounts.php. Every name, email, and password in
 * that file is fabricated for local testing. The passwords are public
 * and must never be used in a deployed environment. See the header of
 * demo_accounts.php for the full notice.
 *
 * USAGE
 *
 * From a command prompt, with the working directory set to the project
 * root:
 *
 *   php Solution/sql/seed.php
 *
 * The script is idempotent. Running it multiple times does not produce
 * duplicate rows. An account that already exists with the same email is
 * updated in place, and its password hash is checked and refreshed if it
 * does not match the value in demo_accounts.php.
 *
 * ROLE DISTRIBUTION
 *
 *   admins     2
 *   vendors    3
 *   standard   4
 *   students   1
 *
 * Total       10
 *
 * SOURCE: NOTES - Populate the database using real or simulated data,
 *         with at least ten records per table. Make use of demo
 *         accounts.
 *
 * @version 1.0
 */

// =============================================================================
// Bootstrap
// =============================================================================
//
// The script runs from the command line. It does not run inside a web
// request, so the session and HTTP helpers are not used. Only the
// database layer, the logging layer, and the two helper functions
// hashPassword() and generateAlphanumericUserId() are required.
// =============================================================================

if (PHP_SAPI !== 'cli')
{
    header('Content-Type: text/plain; charset=utf-8');
    echo "This script is intended to be run from the command line.\n";
    echo "Usage: php Solution/sql/seed.php\n";
    exit(1);
}

define('BASE_PATH', dirname(__DIR__, 2));

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/error_logging.php';
require_once BASE_PATH . '/includes/password_validation.php';
require_once BASE_PATH . '/includes/user_id.php';

// =============================================================================
// Output Helpers
// =============================================================================

if (!function_exists('seedLine'))
{
    /**
     * Writes a single line of output to the console.
     *
     * @param string $text The text to print
     * @return void
     */
    function seedLine($text)
    {
        echo $text . PHP_EOL;
    }
}

if (!function_exists('seedHeading'))
{
    /**
     * Writes a heading to the console.
     *
     * @param string $text The heading
     * @return void
     */
    function seedHeading($text)
    {
        echo PHP_EOL;
        echo $text . PHP_EOL;
        echo str_repeat('=', strlen($text)) . PHP_EOL;
        echo PHP_EOL;
    }
}

// =============================================================================
// Account Insertion
// =============================================================================

if (!function_exists('seedUserAccount'))
{
    /**
     * Inserts or repairs a single demonstration account.
     *
     * If a user with the same email already exists, the row is updated
     * so that the full name, role, and verification flags match the
     * definition in demo_accounts.php. The password hash is refreshed
     * only if the stored hash does not verify against the plain-text
     * password in demo_accounts.php. That check avoids unnecessary
     * writes on repeated runs.
     *
     * @param array             $account The account definition
     * @param DatabaseConnection $db      The database connection
     * @return string One of: "created", "updated", "verified"
     */
    function seedUserAccount($account, $db)
    {
        $email = $account['email'];
        $plain = $account['password'];

        $existing = $db->fetchOne(
            "SELECT user_id, password_hash, account_type,
                    is_verified, is_active
             FROM users
             WHERE email = :email OR unique_id = :unique_id
             LIMIT 1",
            array(
                'email'     => $email,
                'unique_id' => $account['unique_id']
            )
        );

        $hashMatches = false;

        if ($existing)
        {
            $hashMatches = password_verify($plain, $existing['password_hash']);
        }

        if ($existing && $hashMatches
            && $existing['account_type'] === $account['account_type']
            && (int)$existing['is_verified'] === (int)$account['is_verified']
            && (int)$existing['is_active'] === (int)$account['is_active'])
        {
            return 'verified';
        }

        $passwordHash = hashPassword($plain);

        if ($existing)
        {
            $db->executeQuery(
                "UPDATE users
                 SET full_name   = :full_name,
                     username    = :username,
                     password_hash = :password_hash,
                     account_type  = :account_type,
                     is_verified   = :is_verified,
                     is_active     = :is_active,
                     updated_at    = NOW()
                 WHERE user_id = :user_id",
                array(
                    'full_name'     => $account['full_name'],
                    'username'      => $account['username'],
                    'password_hash' => $passwordHash,
                    'account_type'  => $account['account_type'],
                    'is_verified'   => $account['is_verified'],
                    'is_active'     => $account['is_active'],
                    'user_id'       => $existing['user_id']
                )
            );

            return 'updated';
        }

        $userId = $db->insert(
            "INSERT INTO users
                (user_id, unique_id, full_name, username, email,
                 password_hash, account_type, is_verified, is_active,
                 created_at, updated_at)
             VALUES
                (:user_id, :unique_id, :full_name, :username, :email,
                 :password_hash, :account_type, :is_verified, :is_active,
                 NOW(), NOW())",
            array(
                'user_id'       => $account['user_id'],
                'unique_id'     => $account['unique_id'],
                'full_name'     => $account['full_name'],
                'username'      => $account['username'],
                'email'         => $account['email'],
                'password_hash' => $passwordHash,
                'account_type'  => $account['account_type'],
                'is_verified'   => $account['is_verified'],
                'is_active'     => $account['is_active']
            )
        );

        if (!$userId)
        {
            throw new RuntimeException(
                'Unable to insert user: ' . $account['email']
            );
        }

        return 'created';
    }
}

if (!function_exists('seedVendorProfile'))
{
    /**
     * Inserts or repairs the vendor profile for a vendor account.
     *
     * @param array             $account The account definition
     * @param DatabaseConnection $db      The database connection
     * @return string One of: "created", "updated", "skipped"
     */
    function seedVendorProfile($account, $db)
    {
        if ($account['account_type'] !== 'vendor')
        {
            return 'skipped';
        }

        if (empty($account['vendor_name']))
        {
            return 'skipped';
        }

        $user = $db->fetchOne(
            "SELECT user_id FROM users WHERE email = :email LIMIT 1",
            array('email' => $account['email'])
        );

        if (!$user)
        {
            throw new RuntimeException(
                'Vendor profile without user account: ' . $account['email']
            );
        }

        $existing = $db->fetchOne(
            "SELECT vendor_id FROM vendors WHERE vendor_user_id = :user_id",
            array('user_id' => $user['user_id'])
        );

        if ($existing)
        {
            $db->executeQuery(
                "UPDATE vendors
                 SET vendor_name = :vendor_name,
                     description = :description,
                     is_open     = 1,
                     is_approved = 1,
                     updated_at  = NOW()
                 WHERE vendor_id = :vendor_id",
                array(
                    'vendor_name' => $account['vendor_name'],
                    'description' => $account['description'],
                    'vendor_id'   => $existing['vendor_id']
                )
            );

            return 'updated';
        }

        $db->insert(
            "INSERT INTO vendors
                (vendor_user_id, vendor_name, description,
                 is_open, is_approved, created_at)
             VALUES
                (:user_id, :vendor_name, :description, 1, 1, NOW())",
            array(
                'user_id'     => $user['user_id'],
                'vendor_name' => $account['vendor_name'],
                'description' => $account['description']
            )
        );

        return 'created';
    }
}

// =============================================================================
// Main Execution
// =============================================================================

seedHeading('Campus Eats - Demonstration Data Seeding');

seedLine('This script inserts the ten demonstration accounts and their');
seedLine('vendor profiles. The accounts are fabricated for local testing.');
seedLine('Do not run this script against a production database.');
seedLine('');

$demoAccountsPath = BASE_PATH . '/config/demo_accounts.php';

if (!file_exists($demoAccountsPath))
{
    seedLine('ERROR: demo_accounts.php was not found at:');
    seedLine('       ' . $demoAccountsPath);
    exit(1);
}

$demoAccounts = require $demoAccountsPath;

if (!is_array($demoAccounts) || empty($demoAccounts))
{
    seedLine('ERROR: demo_accounts.php did not return a non-empty array.');
    exit(1);
}

seedLine('Loaded ' . count($demoAccounts) . ' account definitions.');
seedLine('');

try
{
    $db = getDB();
}
catch (Throwable $t)
{
    seedLine('ERROR: Unable to connect to the database.');
    seedLine('       ' . $t->getMessage());
    exit(1);
}

$counts = array(
    'created'   => 0,
    'updated'   => 0,
    'verified'  => 0,
    'vendor_created' => 0,
    'vendor_updated' => 0
);

$roleSummary = array(
    'admin'    => 0,
    'vendor'   => 0,
    'standard' => 0,
    'student'  => 0
);

seedHeading('Accounts');

foreach ($demoAccounts as $account)
{
    try
    {
        $result = seedUserAccount($account, $db);
        $counts[$result]++;

        if (isset($roleSummary[$account['account_type']]))
        {
            $roleSummary[$account['account_type']]++;
        }

        seedLine(
            sprintf(
                '  [%-8s] %-32s %s',
                $result,
                $account['email'],
                $account['account_type']
            )
        );

        if ($account['account_type'] === 'vendor')
        {
            $vendorResult = seedVendorProfile($account, $db);

            if ($vendorResult === 'created')
            {
                $counts['vendor_created']++;
            }
            elseif ($vendorResult === 'updated')
            {
                $counts['vendor_updated']++;
            }

            seedLine(
                sprintf(
                    '  [%-8s] %-32s %s',
                    'vendor',
                    $account['vendor_name'],
                    $vendorResult
                )
            );
        }
    }
    catch (Throwable $t)
    {
        seedLine('  [FAILED] ' . $account['email']);
        seedLine('           ' . $t->getMessage());
    }
}

// =============================================================================
// Summary
// =============================================================================

seedHeading('Summary');

seedLine('  Users created  : ' . $counts['created']);
seedLine('  Users updated  : ' . $counts['updated']);
seedLine('  Users verified : ' . $counts['verified']);
seedLine('  Vendor profiles created : ' . $counts['vendor_created']);
seedLine('  Vendor profiles updated : ' . $counts['vendor_updated']);

seedLine('');
seedLine('  Role distribution:');
seedLine('    admins   : ' . $roleSummary['admin']);
seedLine('    vendors  : ' . $roleSummary['vendor']);
seedLine('    standard : ' . $roleSummary['standard']);
seedLine('    students : ' . $roleSummary['student']);

seedLine('');

$total = $roleSummary['admin']
       + $roleSummary['vendor']
       + $roleSummary['standard']
       + $roleSummary['student'];

seedLine('  Total accounts: ' . $total);
seedLine('');

if ($total !== 10)
{
    seedLine('WARNING: Expected exactly 10 accounts. Found ' . $total . '.');
    seedLine('         Check the demo_accounts.php file.');
    seedLine('');
}

seedLine('Seeding complete.');
seedLine('');
seedLine('The demonstration passwords are listed in');
seedLine('Solution/config/demo_accounts.php. They are public and must');
seedLine('be replaced before any deployment to a reachable host.');
seedLine('');

exit(0);