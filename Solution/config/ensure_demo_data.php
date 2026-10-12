<?php
/**
 * Ensure demonstration accounts and sample coupons exist.
 *
 * Called from DatabaseConnection after schema verification. Idempotent:
 * existing demo rows are verified; password hashes that no longer match
 * the published plain-text values are refreshed.
 *
 * CORRECTIONS (Version 3.0 - Password refresh):
 *
 * - For every demo email, if the row exists and password_verify() fails
 *   against the definition password, UPDATE users.password_hash with a
 *   fresh bcrypt hash (cost 12). This fixes "Authentication failed:
 *   password mismatch for user_id 2" when the DB hash drifted from
 *   demo_accounts.php.
 * - Updates use WHERE email = :email (not a hard-coded user_id).
 * - Each account is handled in its own try/catch so one failure does
 *   not abort the rest.
 * - Coupons CAMPUS10, EATS20, WELCOME5 are ensured the same way.
 *
 * SOURCE: Error log 2026-10-12 – password mismatch for user_id 2.
 * SOURCE: Solution/config/demo_accounts.php
 * SOURCE: Clean Code, Robert C. Martin.
 *
 * @version 3.0
 */

if (!function_exists('campus_eats_ensure_demo_data'))
{
    /**
     * @param object $db DatabaseConnection instance (or compatible)
     * @return void
     */
    function campus_eats_ensure_demo_data($db)
    {
        if ($db === null || !method_exists($db, 'isAvailable') || !$db->isAvailable())
        {
            return;
        }

        $accountsFile = dirname(__FILE__) . '/demo_accounts.php';

        if (!is_readable($accountsFile))
        {
            if (function_exists('writeLog'))
            {
                writeLog(
                    'Demo seeder skipped: demo_accounts.php not readable',
                    'DATABASE'
                );
            }
            return;
        }

        $accounts = include $accountsFile;

        if (!is_array($accounts) || empty($accounts))
        {
            return;
        }

        if (!function_exists('hashPassword'))
        {
            /**
             * @param string $password
             * @return string
             */
            function hashPassword($password)
            {
                return password_hash(
                    $password,
                    PASSWORD_DEFAULT,
                    array('cost' => 12)
                );
            }
        }

        $refreshed = 0;
        $created = 0;
        $verified = 0;

        foreach ($accounts as $account)
        {
            try
            {
                if (empty($account['email']) || empty($account['password']))
                {
                    continue;
                }

                $email = strtolower(trim($account['email']));
                $plainPassword = $account['password'];

                $existing = null;

                if (method_exists($db, 'fetchOne'))
                {
                    $existing = $db->fetchOne(
                        'SELECT user_id, password_hash, unique_id, account_type
                         FROM users
                         WHERE LOWER(email) = :email
                         LIMIT 1',
                        array('email' => $email)
                    );
                }

                if ($existing && !empty($existing['user_id']))
                {
                    $hash = isset($existing['password_hash'])
                        ? $existing['password_hash']
                        : '';

                    $matches = ($hash !== ''
                        && password_verify($plainPassword, $hash));

                    if (!$matches)
                    {
                        $newHash = hashPassword($plainPassword);

                        if (method_exists($db, 'executeQuery'))
                        {
                            $db->executeQuery(
                                'UPDATE users
                                 SET password_hash = :hash,
                                     is_active = 1,
                                     is_verified = 1,
                                     updated_at = NOW()
                                 WHERE email = :email',
                                array(
                                    'hash'  => $newHash,
                                    'email' => $email
                                )
                            );
                        }

                        $refreshed++;

                        if (function_exists('writeLog'))
                        {
                            writeLog(
                                'Demo password hash refreshed for '
                                    . $email
                                    . ' (user_id '
                                    . $existing['user_id']
                                    . ')',
                                'DATABASE'
                            );
                        }
                    }
                    else
                    {
                        $verified++;
                    }

                    continue;
                }

                // Insert missing demo account
                $uniqueId = isset($account['unique_id'])
                    ? substr((string)$account['unique_id'], 0, 16)
                    : substr(bin2hex(random_bytes(8)), 0, 16);

                $fullName = isset($account['full_name'])
                    ? $account['full_name']
                    : 'Demo User';
                $username = isset($account['username'])
                    ? $account['username']
                    : explode('@', $email)[0];
                $accountType = isset($account['account_type'])
                    ? strtolower($account['account_type'])
                    : 'student';
                $passwordHash = hashPassword($plainPassword);

                if (method_exists($db, 'insert'))
                {
                    $userId = $db->insert(
                        'INSERT INTO users
                            (unique_id, full_name, username, email,
                             password_hash, account_type, is_verified,
                             is_active, created_at, updated_at)
                         VALUES
                            (:unique_id, :full_name, :username, :email,
                             :password_hash, :account_type, 1, 1, NOW(), NOW())',
                        array(
                            'unique_id'     => $uniqueId,
                            'full_name'     => $fullName,
                            'username'      => $username,
                            'email'         => $email,
                            'password_hash' => $passwordHash,
                            'account_type'  => $accountType
                        )
                    );
                }
                else
                {
                    $userId = null;
                }

                if ($accountType === 'vendor'
                    && $userId
                    && method_exists($db, 'insert'))
                {
                    $vendorName = !empty($account['vendor_name'])
                        ? $account['vendor_name']
                        : $fullName;
                    $description = isset($account['description'])
                        ? $account['description']
                        : 'Demo vendor';

                    try
                    {
                        $db->insert(
                            'INSERT INTO vendors
                                (vendor_user_id, vendor_name, description,
                                 is_open, is_approved, created_at)
                             VALUES
                                (:user_id, :vendor_name, :description,
                                 1, 1, NOW())',
                            array(
                                'user_id'     => $userId,
                                'vendor_name' => $vendorName,
                                'description' => $description
                            )
                        );
                    }
                    catch (Exception $vendorException)
                    {
                        // Vendor row may already exist; continue
                    }
                }

                $created++;
            }
            catch (Exception $exception)
            {
                if (function_exists('writeLog'))
                {
                    writeLog(
                        'Demo account ensure failed for '
                            . (isset($account['email'])
                                ? $account['email']
                                : 'unknown')
                            . ': '
                            . $exception->getMessage(),
                        'DATABASE'
                    );
                }
            }
        }

        campus_eats_ensure_demo_coupons($db);

        if (function_exists('writeLog'))
        {
            writeLog(
                'Demo accounts and coupons ensured'
                    . ' (created=' . $created
                    . ', verified=' . $verified
                    . ', password_refreshed=' . $refreshed . ')',
                'DATABASE'
            );
        }
    }
}

if (!function_exists('campus_eats_ensure_demo_coupons'))
{
    /**
     * @param object $db
     * @return void
     */
    function campus_eats_ensure_demo_coupons($db)
    {
        $coupons = array(
            array('code' => 'CAMPUS10', 'percent' => 10),
            array('code' => 'EATS20', 'percent' => 20),
            array('code' => 'WELCOME5', 'percent' => 5)
        );

        foreach ($coupons as $coupon)
        {
            try
            {
                $exists = null;

                if (method_exists($db, 'fetchOne'))
                {
                    $exists = $db->fetchOne(
                        'SELECT coupon_id FROM coupons
                         WHERE code = :code LIMIT 1',
                        array('code' => $coupon['code'])
                    );
                }

                if ($exists)
                {
                    continue;
                }

                if (method_exists($db, 'insert'))
                {
                    $db->insert(
                        'INSERT INTO coupons
                            (code, discount_percent, is_active, created_at)
                         VALUES
                            (:code, :percent, 1, NOW())',
                        array(
                            'code'    => $coupon['code'],
                            'percent' => $coupon['percent']
                        )
                    );
                }
            }
            catch (Exception $exception)
            {
                // Coupons table may not exist yet; ignore
            }
        }
    }
}