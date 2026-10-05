<?php
/**
 * Database Seeding Script
 *
 * Inserts the ten demonstration accounts and their associated vendor
 * profiles into the MySQL database.
 *
 * IMPORTANT: DEMONSTRATION DATA
 *
 * The accounts inserted by this script are the ten accounts defined
 * in Solution/config/demo_accounts.php. Every name, email, and
 * password in that file is fabricated for local testing. The
 * passwords are public and must never be used in a deployed
 * environment. See the header of demo_accounts.php for the full
 * notice.
 *
 * USAGE
 *
 * From a command prompt, with the working directory set to the
 * project root:
 *
 *   php Solution/sql/seed.php
 *
 * The script is idempotent. Running it multiple times does not
 * produce duplicate rows. An account that already exists with the
 * same email is left in place. Its password hash is refreshed only
 * when the stored hash does not verify against the plain-text
 * password in the definitions.
 *
 * The script does not accept any argument. It exits with a non-zero
 * status when any account fails to be created or verified.
 *
 * BEHAVIOUR WITH RESPECT TO FIREBASE
 *
 * The seed script writes to MySQL only. Firebase is not contacted.
 * The accounts become visible in Firebase only after a client signs
 * in and the Firebase projection runs. The seed script does not
 * require a Firebase ID token and does not depend on Firebase being
 * reachable.
 *
 * SOURCE: Campus Eats process document, section 12.5.
 * SOURCE: Demonstration account reference file.
 *
 * @version 1.0
 */

if (PHP_SAPI !== 'cli')
{
    header('Content-Type: text/plain; charset=utf-8');
    echo "This script is intended to be run from the command line.\n";
    echo "Usage: php Solution/sql/seed.php\n";
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/error_logging.php';
require_once BASE_PATH . '/includes/password_validation.php';
require_once BASE_PATH . '/includes/user_id.php';

// =============================================================================
// Load the demonstration account definitions
// =============================================================================

$demoAccountsPath = BASE_PATH . '/config/demo_accounts.php';

if (!file_exists($demoAccountsPath))
{
    echo "ERROR: demo_accounts.php was not found at:\n";
    echo "       " . $demoAccountsPath . "\n";
    exit(1);
}

$demoAccounts = require $demoAccountsPath;

if (!is_array($demoAccounts) || empty($demoAccounts))
{
    echo "ERROR: demo_accounts.php did not return a non-empty array.\n";
    exit(1);
}

// =============================================================================
// Verify the role distribution
// =============================================================================
//
// The reference file states a role distribution of two administrators,
// three vendors, four standard users, and one student. The script
// checks the distribution and reports a warning when the file does
// not match. The script does not stop on a mismatch because a future
// change to the reference may legitimately alter the distribution.

$roleCounts = array(
    'admin'    => 0,
    'vendor'   => 0,
    'standard' => 0,
    'student'  => 0
);

foreach ($demoAccounts as $account)
{
    if (isset($roleCounts[$account['account_type']]))
    {
        $roleCounts[$account['account_type']]++;
    }
}

$expectedCounts = array(
    'admin'    => 2,
    'vendor'   => 3,
    'standard' => 4,
    'student'  => 1
);

$distributionMatches = true;

foreach ($expectedCounts as $role => $expected)
{
    if ($roleCounts[$role] !== $expected)
    {
        $distributionMatches = false;
        break;
    }
}

// =============================================================================
// Connect to the database
// =============================================================================

echo "Campus Eats demonstration data seeding.\n";
echo "Accounts: " . count($demoAccounts) . "\n";
echo "\n";

try
{
    $db = getDB();
}
catch (Throwable $t)
{
    echo "ERROR: Unable to connect to the database.\n";
    echo "       " . $t->getMessage() . "\n";
    exit(1);
}

if (!$db->isAvailable())
{
    echo "ERROR: The database is not available.\n";
    echo "       " . $db->getLastError() . "\n";
    exit(1);
}

if (!$distributionMatches)
{
    echo "WARNING: The role distribution does not match the reference.\n";
    echo "         Expected: 2 admins, 3 vendors, 4 standard, 1 student.\n";
    echo "         Found:    "
        . $roleCounts['admin'] . " admins, "
        . $roleCounts['vendor'] . " vendors, "
        . $roleCounts['standard'] . " standard, "
        . $roleCounts['student'] . " student.\n";
    echo "\n";
}

// =============================================================================
// Seed each account
// =============================================================================

$created = 0;
$verified = 0;
$failed = 0;

foreach ($demoAccounts as $account)
{
    $email = $account['email'];

    try
    {
        // Check whether the account already exists. The check is by
        // email. When the account exists, the stored hash is compared
        // against the plain-text password. When the hash matches, the
        // account is left in place. When the hash does not match, the
        // hash is refreshed. The refresh path is reached only when the
        // definition file was edited after a previous seed run.

        $existing = $db->fetchOne(
            "SELECT user_id, password_hash
             FROM users
             WHERE email = :email
             LIMIT 1",
            array('email' => $email)
        );

        if ($existing)
        {
            $hashMatches = password_verify(
                $account['password'],
                $existing['password_hash']
            );

            if (!$hashMatches)
            {
                $newHash = hashPassword($account['password']);

                $db->executeQuery(
                    "UPDATE users
                     SET password_hash = :hash, updated_at = NOW()
                     WHERE user_id = :user_id",
                    array(
                        'hash' => $newHash,
                        'user_id' => $existing['user_id']
                    )
                );
            }

            echo "  verified  $email\n";
            $verified++;
            continue;
        }

        // The account does not exist. It is created. The password is
        // hashed with bcrypt at cost factor 12. The unique ID and the
        // username are taken from the definition. The role is taken
        // from the definition.

        $passwordHash = hashPassword($account['password']);

        $db->beginTransaction();

        $userId = $db->insert(
            "INSERT INTO users
                (unique_id, full_name, username, email,
                 password_hash, account_type, is_verified,
                 is_active, created_at, updated_at)
             VALUES
                (:unique_id, :full_name, :username, :email,
                 :password_hash, :account_type, :is_verified,
                 :is_active, NOW(), NOW())",
            array(
                'unique_id'     => $account['unique_id'],
                'full_name'     => $account['full_name'],
                'username'      => $account['username'],
                'email'         => $email,
                'password_hash' => $passwordHash,
                'account_type'  => $account['account_type'],
                'is_verified'   => $account['is_verified'],
                'is_active'     => $account['is_active']
            )
        );

        if (!$userId)
        {
            throw new RuntimeException(
                "User insert returned no ID for $email."
            );
        }

        // The vendor accounts receive a row in the vendors table. The
        // vendor row is linked to the user row through the vendor_user_id
        // column. The vendor is marked as approved so the account can
        // sign in immediately.

        if ($account['account_type'] === 'vendor'
            && !empty($account['vendor_name']))
        {
            $db->insert(
                "INSERT INTO vendors
                    (vendor_user_id, vendor_name, description,
                     is_open, is_approved, created_at)
                 VALUES
                    (:user_id, :vendor_name, :description, 1, 1, NOW())",
                array(
                    'user_id'     => $userId,
                    'vendor_name' => $account['vendor_name'],
                    'description' => $account['description']
                )
            );
        }

        $db->commit();

        echo "  created   $email\n";
        $created++;
    }
    catch (Throwable $t)
    {
        if ($db->inTransaction())
        {
            $db->rollback();
        }

        echo "  FAILED    $email\n";
        echo "            " . $t->getMessage() . "\n";
        $failed++;
    }
}

// =============================================================================
// Summary
// =============================================================================

echo "\n";
echo "Summary\n";
echo "-------\n";
echo "  Created:  $created\n";
echo "  Verified: $verified\n";
echo "  Failed:   $failed\n";
echo "  Total:    " . ($created + $verified + $failed) . "\n";
echo "\n";

if ($failed > 0)
{
    echo "One or more accounts could not be seeded.\n";
    exit(1);
}

echo "Seeding complete.\n";
exit(0);