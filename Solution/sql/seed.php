<?php
/**
 * Database Seeding Script
 *
 * Inserts the ten demonstration accounts and their associated vendor
 * profiles into the MySQL database.
 *
 * IMPORTANT: DEMONSTRATION DATA
 *
 * The accounts inserted by this script are the ten accounts supplied
 * in REPORT.txt. Every name, email, and password is fabricated for
 * local testing. The passwords are public and must never be used in a
 * deployed environment.
 *
 * USAGE
 *
 * From a command prompt, with the working directory set to the
 * project root:
 *
 *   php Solution/sql/seed.php
 *
 * The script is idempotent. A second run does not duplicate a row. An
 * account that already exists is left in place. The password hash is
 * refreshed only when the stored hash does not verify against the
 * plain-text password in the definitions below.
 *
 * The script is intended to be run from the command line. It prints a
 * report to standard output and exits with a non-zero status when any
 * account fails to be created or verified.
 *
 * SOURCE: REPORT.txt, Demo Account Seeding.
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
// Account definitions
// =============================================================================
//
// The list is taken from REPORT.txt. The userId and uniqueId values
// are generated on insert. The plain-text passwords are present only
// in this file. They are hashed before they reach the database.
// =============================================================================

$demoAccounts = array(
    array(
        'full_name'     => 'Amara Nkosi',
        'email'         => 'amara.nkosi@campuseats.test',
        'password'      => 'Adm1n#Amara',
        'account_type'  => 'admin',
        'vendor_name'   => null
    ),
    array(
        'full_name'     => 'Pieter van Wyk',
        'email'         => 'pieter.vanwyk@campuseats.test',
        'password'      => 'Adm1n#Pieter',
        'account_type'  => 'admin',
        'vendor_name'   => null
    ),
    array(
        'full_name'     => 'Thandiwe Mokoena',
        'email'         => 'thandiwe.mokoena@campuseats.test',
        'password'      => 'Vend0r#Thandi',
        'account_type'  => 'vendor',
        'vendor_name'   => 'Campus Corner Kitchen'
    ),
    array(
        'full_name'     => 'Sipho Dlamini',
        'email'         => 'sipho.dlamini@campuseats.test',
        'password'      => 'Vend0r#Sipho',
        'account_type'  => 'vendor',
        'vendor_name'   => 'Braai Brothers'
    ),
    array(
        'full_name'     => 'Annelie Botha',
        'email'         => 'annelie.botha@campuseats.test',
        'password'      => 'Vend0r#Annelie',
        'account_type'  => 'vendor',
        'vendor_name'   => 'Coffee and Koeksisters'
    ),
    array(
        'full_name'     => 'Lerato Khumalo',
        'email'         => 'lerato.khumalo@campuseats.test',
        'password'      => 'Stand@rd#Lerato',
        'account_type'  => 'standard',
        'vendor_name'   => null
    ),
    array(
        'full_name'     => 'Johan Pretorius',
        'email'         => 'johan.pretorius@campuseats.test',
        'password'      => 'Stand@rd#Johan',
        'account_type'  => 'standard',
        'vendor_name'   => null
    ),
    array(
        'full_name'     => 'Zanele Ndlovu',
        'email'         => 'zanele.ndlovu@campuseats.test',
        'password'      => 'Stand@rd#Zanele',
        'account_type'  => 'standard',
        'vendor_name'   => null
    ),
    array(
        'full_name'     => 'Marius Steyn',
        'email'         => 'marius.steyn@campuseats.test',
        'password'      => 'Stand@rd#Marius',
        'account_type'  => 'standard',
        'vendor_name'   => null
    ),
    array(
        'full_name'     => 'Naledi Mahlangu',
        'email'         => 'naledi.mahlangu@campuseats.test',
        'password'      => 'Stud3nt#Naledi',
        'account_type'  => 'student',
        'vendor_name'   => null
    )
);

// =============================================================================
// Seed execution
// =============================================================================

echo "Campus Eats demonstration data seeding.\n";
echo "Accounts: " . count($demoAccounts) . "\n\n";

try
{
    $db = getDB();
}
catch (Throwable $t)
{
    echo "Unable to connect to the database: " . $t->getMessage() . "\n";
    exit(1);
}

if (!$db->isAvailable())
{
    echo "Database is not available: " . $db->getLastError() . "\n";
    exit(1);
}

$created = 0;
$verified = 0;
$failed = 0;

foreach ($demoAccounts as $account)
{
    $email = $account['email'];

    try
    {
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
                // The stored hash does not verify against the plain
                // text value in the definition. This case is reached
                // only when the definition was edited after a previous
                // seed run. The hash is refreshed.
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

        $uniqueId = generateAlphanumericUserId($account['account_type']);
        $username = explode('@', $email)[0];
        $passwordHash = hashPassword($account['password']);

        $db->beginTransaction();

        $userId = $db->insert(
            "INSERT INTO users
                (unique_id, full_name, username, email,
                 password_hash, account_type, is_verified,
                 is_active, created_at, updated_at)
             VALUES
                (:unique_id, :full_name, :username, :email,
                 :password_hash, :account_type, 1, 1, NOW(), NOW())",
            array(
                'unique_id'     => $uniqueId,
                'full_name'     => $account['full_name'],
                'username'      => $username,
                'email'         => $email,
                'password_hash' => $passwordHash,
                'account_type'  => $account['account_type']
            )
        );

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
                    'description' => 'Demonstration vendor.'
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

        echo "  FAILED    $email: " . $t->getMessage() . "\n";
        $failed++;
    }
}

echo "\n";
echo "Created:  $created\n";
echo "Verified: $verified\n";
echo "Failed:   $failed\n";
echo "\n";

if ($failed > 0)
{
    exit(1);
}

exit(0);