<?php
/**
 * Automatic Demo Data Seeder
 *
 * Ensures demonstration accounts and sample coupons exist after the
 * database schema is available. Idempotent; runs at most once per
 * PHP process. Failures are logged and do not break page load.
 *
 * CORRECTIONS (Version 2.0 - Column name fix):
 *
 * - Vendor lookups and inserts use vendor_user_id (the real column
 *   name on the vendors table), not user_id. The previous version
 *   caused SQLSTATE[42S22] Unknown column 'user_id'.
 * - Password hash updates key on email, not user_id, for safety.
 * - Each account is processed in its own try/catch so one failure
 *   does not abort the rest.
 *
 * SOURCE: Error log – Unknown column user_id in where clause.
 *
 * @version 2.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

if (!function_exists('campus_eats_ensure_demo_data'))
{
    /**
     * Ensure demo accounts and sample coupons exist.
     *
     * @param object $db Open DatabaseConnection instance
     * @return void
     */
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

if (!function_exists('campus_eats_ensure_coupons_table'))
{
    /**
     * @param object $db
     * @return void
     */
    function campus_eats_ensure_coupons_table($db)
    {
        $db->executeQuery(
            "CREATE TABLE IF NOT EXISTS coupons (
                coupon_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(32) NOT NULL UNIQUE,
                discount_percent DECIMAL(5,2) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci"
        );
    }
}

if (!function_exists('campus_eats_ensure_sample_coupons'))
{
    /**
     * @param object $db
     * @return void
     */
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
                'INSERT INTO coupons (code, discount_percent, is_active)
                 VALUES (?, ?, 1)',
                array($sample[0], $sample[1])
            );
        }
    }
}

if (!function_exists('campus_eats_ensure_demo_accounts'))
{
    /**
     * @param object $db
     * @return void
     */
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
            try
            {
                $email = isset($account['email']) ? $account['email'] : '';

                if ($email === '')
                {
                    continue;
                }

                $existing = $db->fetchOne(
                    'SELECT user_id, password_hash FROM users
                     WHERE email = ? LIMIT 1',
                    array($email)
                );

                $plainPassword = isset($account['password'])
                    ? $account['password']
                    : '';
                $uniqueId = isset($account['unique_id'])
                    ? substr((string)$account['unique_id'], 0, 16)
                    : '';

                if ($existing)
                {
                    if ($plainPassword !== ''
                        && !password_verify(
                            $plainPassword,
                            $existing['password_hash']
                        ))
                    {
                        $newHash = password_hash(
                            $plainPassword,
                            PASSWORD_DEFAULT,
                            array('cost' => 12)
                        );

                        $db->executeQuery(
                            'UPDATE users
                             SET password_hash = ?, unique_id = ?
                             WHERE email = ?',
                            array($newHash, $uniqueId, $email)
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
                            unique_id, full_name, username, email,
                            password_hash, account_type, is_verified, is_active
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

                // Vendor profile – column is vendor_user_id, not user_id.
                if ($userId > 0
                    && isset($account['account_type'])
                    && $account['account_type'] === 'vendor'
                    && !empty($account['vendor_name']))
                {
                    $vendor = $db->fetchOne(
                        'SELECT vendor_id FROM vendors
                         WHERE vendor_user_id = ? LIMIT 1',
                        array($userId)
                    );

                    if (!$vendor)
                    {
                        $db->executeQuery(
                            'INSERT INTO vendors (
                                vendor_user_id, vendor_name, description,
                                is_open, is_approved
                            ) VALUES (?, ?, ?, 1, 1)',
                            array(
                                $userId,
                                $account['vendor_name'],
                                isset($account['description'])
                                    ? $account['description']
                                    : ''
                            )
                        );
                    }
                }
            }
            catch (Throwable $accountError)
            {
                if (function_exists('writeLog'))
                {
                    writeLog(
                        'Demo account seed failed for '
                            . (isset($account['email']) ? $account['email'] : '?')
                            . ': ' . $accountError->getMessage(),
                        'DATABASE'
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