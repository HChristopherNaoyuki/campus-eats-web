<?php
/**
 * Automatic Demo Data Seeder
 *
 * Ensures the demonstration accounts and sample coupons exist after
 * the database schema is available. The seeder is idempotent and
 * runs at most once per PHP process. Failures are logged and do not
 * break page load.
 *
 * SOURCE: Technical Audit Update – Demo Accounts, Coupons, and SSL.
 *
 * @version 1.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

/**
 * Ensure demo accounts and sample coupons exist.
 *
 * @param DatabaseConnection $db Open database connection
 * @return void
 */
if (!function_exists('campus_eats_ensure_demo_data'))
{
    function campus_eats_ensure_demo_data($db)
    {
        static $alreadyRan = false;

        if ($alreadyRan)
        {
            return;
        }

        $alreadyRan = true;

        if (!$db || !method_exists($db, 'isAvailable') || !$db->isAvailable())
        {
            return;
        }

        try
        {
            campus_eats_ensure_coupons_table($db);
            campus_eats_ensure_sample_coupons($db);
            campus_eats_ensure_demo_accounts($db);
        }
        catch (Throwable $e)
        {
            if (function_exists('writeLog'))
            {
                writeLog(
                    'Demo data seeder failed: ' . $e->getMessage(),
                    'DATABASE'
                );
            }
        }
    }
}

/**
 * Create the coupons table when it is absent.
 *
 * @param DatabaseConnection $db
 * @return void
 */
if (!function_exists('campus_eats_ensure_coupons_table'))
{
    function campus_eats_ensure_coupons_table($db)
    {
        $sql = "CREATE TABLE IF NOT EXISTS coupons (
            coupon_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(32) NOT NULL UNIQUE,
            discount_percent DECIMAL(5,2) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->executeQuery($sql);
    }
}

/**
 * Insert the three sample coupons when they are absent.
 *
 * @param DatabaseConnection $db
 * @return void
 */
if (!function_exists('campus_eats_ensure_sample_coupons'))
{
    function campus_eats_ensure_sample_coupons($db)
    {
        $samples = array(
            array('CAMPUS10', 10.00),
            array('EATS20', 20.00),
            array('WELCOME5', 5.00)
        );

        foreach ($samples as $sample)
        {
            $existing = $db->fetchOne(
                'SELECT coupon_id FROM coupons WHERE code = ? LIMIT 1',
                array($sample[0])
            );

            if ($existing)
            {
                continue;
            }

            $db->executeQuery(
                'INSERT INTO coupons (code, discount_percent, is_active) VALUES (?, ?, 1)',
                array($sample[0], $sample[1])
            );
        }
    }
}

/**
 * Insert any missing demonstration accounts and keep vendor rows
 * in sync. Refreshes the bcrypt hash only when the stored hash no
 * longer verifies against the documented password.
 *
 * @param DatabaseConnection $db
 * @return void
 */
if (!function_exists('campus_eats_ensure_demo_accounts'))
{
    function campus_eats_ensure_demo_accounts($db)
    {
        $accountsFile = BASE_PATH . '/config/demo_accounts.php';

        if (!is_readable($accountsFile))
        {
            return;
        }

        $accounts = require $accountsFile;

        if (!is_array($accounts))
        {
            return;
        }

        foreach ($accounts as $account)
        {
            $email = isset($account['email']) ? $account['email'] : '';

            if ($email === '')
            {
                continue;
            }

            $existing = $db->fetchOne(
                'SELECT user_id, password_hash, unique_id FROM users WHERE email = ? LIMIT 1',
                array($email)
            );

            $plainPassword = isset($account['password']) ? $account['password'] : '';
            $uniqueId = isset($account['unique_id'])
                ? substr($account['unique_id'], 0, 16)
                : '';

            if ($existing)
            {
                // Refresh hash only when the documented password no longer matches.
                if ($plainPassword !== ''
                    && !password_verify($plainPassword, $existing['password_hash']))
                {
                    $newHash = password_hash(
                        $plainPassword,
                        PASSWORD_DEFAULT,
                        array('cost' => 12)
                    );

                    $db->executeQuery(
                        'UPDATE users SET password_hash = ?, unique_id = ? WHERE user_id = ?',
                        array($newHash, $uniqueId, $existing['user_id'])
                    );
                }

                $userId = (int)$existing['user_id'];
            }
            else
            {
                $hash = password_hash(
                    $plainPassword,
                    PASSWORD_DEFAULT,
                    array('cost' => 12)
                );

                $db->executeQuery(
                    'INSERT INTO users (
                        unique_id, full_name, username, email, password_hash,
                        account_type, is_verified, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    array(
                        $uniqueId,
                        $account['full_name'],
                        $account['username'],
                        $email,
                        $hash,
                        $account['account_type'],
                        (int)$account['is_verified'],
                        (int)$account['is_active']
                    )
                );

                $row = $db->fetchOne(
                    'SELECT user_id FROM users WHERE email = ? LIMIT 1',
                    array($email)
                );

                $userId = $row ? (int)$row['user_id'] : 0;
            }

            // Ensure an approved vendor row for vendor accounts.
            if ($userId > 0
                && isset($account['account_type'])
                && $account['account_type'] === 'vendor'
                && !empty($account['vendor_name']))
            {
                $vendor = $db->fetchOne(
                    'SELECT vendor_id FROM vendors WHERE user_id = ? LIMIT 1',
                    array($userId)
                );

                if (!$vendor)
                {
                    $db->executeQuery(
                        'INSERT INTO vendors (
                            user_id, vendor_name, description, is_open, is_approved
                        ) VALUES (?, ?, ?, 1, 1)',
                        array(
                            $userId,
                            $account['vendor_name'],
                            isset($account['description']) ? $account['description'] : ''
                        )
                    );
                }
            }
        }

        if (function_exists('writeLog'))
        {
            writeLog('Demo accounts and coupons ensured.', 'DATABASE');
        }
    }
}