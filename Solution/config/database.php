<?php
/**
 * Database Connection Configuration File
 *
 * Handles database connections with a singleton pattern, automatic
 * schema installation, admin account provisioning, and demo account
 * creation.
 *
 * CORRECTIONS (Version 25.0 - Installation State Separation):
 * - Separated the "connection is open" state from the "schema is
 *   installed" state. The previous version used a single global flag,
 *   $GLOBALS['_DATABASE_INITIALIZED'], for both meanings. That made it
 *   possible for the installation sequence to be skipped on a fresh
 *   database if the flag had already been set earlier in the same
 *   worker, and it made a partially completed installation look
 *   complete. The observed failure
 *
 *     SQLSTATE[42S02]: Base table or view not found: 1146
 *     Table 'campus_eats.users' doesn't exist
 *
 *   occurred because login.php reached fetchOne() without the install
 *   sequence ever running against the campus_eats database.
 * - Added a post-installation verification step that queries
 *   information_schema for the users table. If the table is missing
 *   after the install sequence, the method raises an exception instead
 *   of silently setting a flag that claims installation is complete.
 * - The install loop now reports the specific statement that failed,
 *   instead of swallowing the exception and continuing.
 * - The install loop now uses the shared getConnection() handle and
 *   closes the cursor after each statement, so a single failed
 *   statement cannot leave a dangling cursor that breaks every
 *   subsequent query.
 * - Retained all Version 24.0 corrections: buffered queries enabled,
 *   persistent connections disabled, closeCursor() on every fetch and
 *   execute, and a periodic connection health check.
 *
 * SOURCE: DATABASE ERROR ROOT CAUSE REPORT
 * SOURCE: SQLSTATE[42S02] 1146 Table 'campus_eats.users' doesn't exist
 * SOURCE: MySQL Documentation - Error 1146
 *
 * @version 25.0
 */

// =============================================================================
// Database Connection Constants
// =============================================================================

if (!defined('DB_HOST'))
{
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
}

if (!defined('DB_NAME'))
{
    define('DB_NAME', getenv('DB_NAME') ?: 'campus_eats');
}

if (!defined('DB_USER'))
{
    define('DB_USER', getenv('DB_USER') ?: 'root');
}

if (!defined('DB_PASS'))
{
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

if (!defined('DB_CHARSET'))
{
    define('DB_CHARSET', 'utf8mb4');
}

if (!defined('BCRYPT_COST'))
{
    define('BCRYPT_COST', 12);
}

// =============================================================================
// Admin Account Configuration
// =============================================================================

if (!defined('ADMIN_FULL_NAME'))
{
    define('ADMIN_FULL_NAME', 'System Administrator');
}

if (!defined('ADMIN_USERNAME'))
{
    define('ADMIN_USERNAME', 'admin');
}

if (!defined('ADMIN_EMAIL'))
{
    define('ADMIN_EMAIL', 'admin@campuseats.com');
}

// =============================================================================
// Admin Password Configuration
// =============================================================================

$adminPassword = getenv('ADMIN_PASSWORD');

if (empty($adminPassword))
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
    $password = '';
    $charsLength = strlen($chars);

    if (function_exists('random_int'))
    {
        for ($i = 0; $i < 16; $i++)
        {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }
    }
    else
    {
        for ($i = 0; $i < 16; $i++)
        {
            $password .= $chars[mt_rand(0, $charsLength - 1)];
        }
    }

    $adminPassword = $password;

    if (session_status() === PHP_SESSION_ACTIVE)
    {
        $_SESSION['_admin_initial_password'] = $adminPassword;
    }
}

if (!defined('ADMIN_PASSWORD_PLAIN'))
{
    define('ADMIN_PASSWORD_PLAIN', $adminPassword);
}

// =============================================================================
// Load Demo Accounts from the Single Source of Truth
// =============================================================================

$demoAccountsFile = __DIR__ . '/demo_accounts.php';

if (file_exists($demoAccountsFile))
{
    $demoAccounts = require_once $demoAccountsFile;

    if (!defined('DEMO_ACCOUNTS'))
    {
        define('DEMO_ACCOUNTS', serialize($demoAccounts));
    }
}
else
{
    if (!defined('DEMO_ACCOUNTS'))
    {
        define('DEMO_ACCOUNTS', serialize(array()));
    }
}

// =============================================================================
// Load Required Helper Files
// =============================================================================

if (!function_exists('writeLog'))
{
    require_once __DIR__ . '/error_logging.php';
}

if (!function_exists('hashPassword'))
{
    require_once dirname(__DIR__) . '/includes/password_validation.php';
}

if (!function_exists('generateUserId'))
{
    require_once dirname(__DIR__) . '/includes/user_id.php';
}

// =============================================================================
// Global State Flags
// =============================================================================
//
// CORRECTION:
// Two separate flags replace the single $GLOBALS['_DATABASE_INITIALIZED'].
//
//   $GLOBALS['_DATABASE_CONNECTION_ESTABLISHED'] is true once a PDO
//     handle exists for this request.
//
//   $GLOBALS['_DATABASE_SCHEMA_VERIFIED'] is true once the users table
//     has been observed to exist in the target database during this
//     request. It is set by verifySchemaInstalled() after the install
//     sequence has run, and by the constructor of any subsequent
//     DatabaseConnection instance if the schema is already present.
//
// This separation is what makes the install sequence reliable on a
// fresh database. Even if the connection flag was set by an earlier
// request in the same worker, the schema flag will be false, and the
// install sequence will run.
// =============================================================================

if (!isset($GLOBALS['_DATABASE_CONNECTION_ESTABLISHED']))
{
    $GLOBALS['_DATABASE_CONNECTION_ESTABLISHED'] = false;
}

if (!isset($GLOBALS['_DATABASE_SCHEMA_VERIFIED']))
{
    $GLOBALS['_DATABASE_SCHEMA_VERIFIED'] = false;
}

// =============================================================================
// DatabaseConnection Singleton Class
// =============================================================================

class DatabaseConnection
{
    /**
     * @var DatabaseConnection|null The single instance for this request
     */
    private static $instance = null;

    /**
     * @var PDO|null The active PDO connection
     */
    private $connection;

    /**
     * @var PDOStatement|null The most recently executed statement
     */
    private $statement = null;

    /**
     * @var bool Whether a transaction is currently open
     */
    private $inTransaction = false;

    /**
     * @var bool Whether the constructor completed its setup
     */
    private $initialized = false;

    /**
     * @var bool Whether the schema has been confirmed present
     */
    private $schemaVerified = false;

    /**
     * @var int Unix timestamp of the last connection health check
     */
    private $lastHealthCheck = 0;

    /**
     * Private constructor. Performs the one-time setup on first use.
     */
    private function __construct()
    {
        // If the schema has already been verified during this request,
        // a second instance can return immediately.
        if ($GLOBALS['_DATABASE_SCHEMA_VERIFIED'] === true)
        {
            $this->initialized = true;
            $this->schemaVerified = true;
            return;
        }

        try
        {
            $this->connect();
            $this->ensureDatabaseExists();
            $this->ensureSchemaInstalled();
            $this->ensureAdminAccountExists();
            $this->ensureUserSessionsTableExists();
            $this->ensureDemoAccountsExist();
            $this->ensureLoginAttemptsTableExists();
            $this->ensurePasswordResetAttemptsTableExists();

            $GLOBALS['_DATABASE_SCHEMA_VERIFIED'] = true;
            $this->initialized = true;
            $this->schemaVerified = true;

            writeLog("Database connection and schema verified successfully.", "DATABASE");
        }
        catch (PDOException $exception)
        {
            writeLog('Database Connection Error: ' . $exception->getMessage(), "DATABASE_ERROR");

            if (defined('APP_DEBUG') && APP_DEBUG === true)
            {
                die('Database error: ' . htmlspecialchars($exception->getMessage()));
            }

            die('Database service is temporarily unavailable. Please try again later.');
        }
    }

    /**
     * Returns the single DatabaseConnection instance.
     *
     * @return DatabaseConnection
     */
    public static function getInstance()
    {
        if (self::$instance === null)
        {
            self::$instance = new DatabaseConnection();
        }

        return self::$instance;
    }

    /**
     * Returns the active PDO connection, reconnecting if necessary.
     *
     * @return PDO The active connection
     */
    public function getConnection()
    {
        if ($this->connection !== null)
        {
            $currentTime = time();

            if (($currentTime - $this->lastHealthCheck) > 60)
            {
                try
                {
                    $stmt = $this->connection->query("SELECT 1");

                    if ($stmt !== false)
                    {
                        $stmt->closeCursor();
                    }

                    $this->lastHealthCheck = $currentTime;
                    return $this->connection;
                }
                catch (PDOException $e)
                {
                    writeLog("Database connection lost, reconnecting...", "DATABASE");
                    $this->connection = null;
                    $this->connect();
                    $this->lastHealthCheck = $currentTime;
                    return $this->connection;
                }
            }

            return $this->connection;
        }

        $this->connect();
        return $this->connection;
    }

    /**
     * Establishes the database connection.
     *
     * Buffered queries are enabled so the PDO MySQL driver fetches the
     * entire result set into PHP memory before returning control to the
     * caller. This eliminates SQLSTATE 2014 ("Commands out of sync")
     * that occurred in unbuffered mode when a new statement was prepared
     * while a previous statement still had unread rows.
     *
     * Persistent connections are disabled so cursor state does not leak
     * between requests handled by the same worker.
     *
     * @return void
     */
    private function connect()
    {
        if ($this->connection !== null)
        {
            return;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = array(
            PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE       => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES         => false,
            PDO::ATTR_PERSISTENT               => false,
            PDO::ATTR_TIMEOUT                  => 5,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::MYSQL_ATTR_INIT_COMMAND       => 'SET NAMES ' . DB_CHARSET
        );

        $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        $GLOBALS['_DATABASE_CONNECTION_ESTABLISHED'] = true;
        $this->lastHealthCheck = time();

        writeLog("PDO connection opened to database '" . DB_NAME . "'.", "DATABASE");
    }

    /**
     * Creates the target database if it does not already exist.
     *
     * @return void
     */
    private function ensureDatabaseExists()
    {
        try
        {
            $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;

            $tempConnection = new PDO($dsn, DB_USER, DB_PASS, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5
            ));

            $sql = "CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` "
                 . "CHARACTER SET " . DB_CHARSET . " "
                 . "COLLATE " . DB_CHARSET . "_unicode_ci";

            $tempConnection->exec($sql);
            $tempConnection = null;

            writeLog("Database '" . DB_NAME . "' ensured to exist.", "DATABASE");
        }
        catch (PDOException $exception)
        {
            writeLog('Database creation failed: ' . $exception->getMessage(), "DATABASE_ERROR");
            throw $exception;
        }
    }

    /**
     * Ensures the schema is installed and the users table exists.
     *
     * CORRECTION:
     * The previous version ran the install script only if a probe query
     * failed, and it swallowed any exception the install loop produced.
     * That made a partially installed schema look complete. This version
     * runs the install script whenever the users table is not present,
     * reports the specific failing statement, and then verifies the
     * users table exists before returning.
     *
     * @return void
     */
    private function ensureSchemaInstalled()
    {
        // Probe: does the users table exist in the target database?
        if ($this->tableExists('users'))
        {
            writeLog("Schema probe: users table already exists.", "DATABASE");
            return;
        }

        writeLog("Schema probe: users table not found. Running install.sql.", "DATABASE");

        $installSqlPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'install.sql';

        if (!file_exists($installSqlPath))
        {
            $message = "Installation script not found at: $installSqlPath";
            writeLog($message, "DATABASE_ERROR");
            throw new RuntimeException($message);
        }

        $sqlContent = file_get_contents($installSqlPath);

        if ($sqlContent === false)
        {
            $message = "Failed to read installation script: $installSqlPath";
            writeLog($message, "DATABASE_ERROR");
            throw new RuntimeException($message);
        }

        // Split on semicolons. Comment-only lines are dropped. This is
        // a simple splitter intended for the shipped install.sql, which
        // does not contain semicolons inside string literals.
        $statements = array();

        foreach (explode(';', $sqlContent) as $rawStatement)
        {
            $statement = trim($rawStatement);

            if ($statement === '')
            {
                continue;
            }

            if (strpos($statement, '--') === 0)
            {
                continue;
            }

            $statements[] = $statement;
        }

        $this->connect();

        foreach ($statements as $index => $statement)
        {
            try
            {
                $stmt = $this->connection->query($statement);

                if ($stmt !== false)
                {
                    $stmt->closeCursor();
                }
            }
            catch (PDOException $exception)
            {
                // Report the specific statement that failed. The index
                // is one-based in the log so a reader can locate it in
                // the file with a text editor.
                $snippet = substr(preg_replace('/\s+/', ' ', $statement), 0, 160);

                writeLog(
                    "Install statement " . ($index + 1) . " failed: "
                        . $exception->getMessage()
                        . " | Statement: " . $snippet,
                    "DATABASE_ERROR"
                );

                throw $exception;
            }
        }

        // Verify the table exists after installation. If the install
        // script silently did nothing (for example, because it was run
        // against a different database than the one the connection is
        // using), this check fails, and the constructor aborts with a
        // clear error instead of setting a flag that claims success.
        if (!$this->tableExists('users'))
        {
            $message = "Installation completed but the users table is still "
                     . "missing from database '" . DB_NAME . "'. Check that "
                     . "install.sql creates the users table and that DB_NAME "
                     . "points at the database the script targets.";

            writeLog($message, "DATABASE_ERROR");
            throw new RuntimeException($message);
        }

        writeLog("Schema installation verified. users table is present.", "DATABASE");
    }

    /**
     * Returns true if the named table exists in the connected database.
     *
     * The check queries information_schema directly rather than using
     * SHOW TABLES LIKE, because information_schema returns a plain
     * scalar and is not affected by the current cursor state.
     *
     * @param string $tableName The table to look for
     * @return bool True if the table exists
     */
    private function tableExists($tableName)
    {
        try
        {
            $this->connect();

            $stmt = $this->connection->prepare(
                "SELECT COUNT(*) AS table_count
                 FROM information_schema.tables
                 WHERE table_schema = :database
                   AND table_name = :table"
            );

            $stmt->bindValue(':database', DB_NAME, PDO::PARAM_STR);
            $stmt->bindValue(':table', $tableName, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return isset($row['table_count']) && (int)$row['table_count'] > 0;
        }
        catch (PDOException $exception)
        {
            writeLog(
                "tableExists check failed for '$tableName': " . $exception->getMessage(),
                "DATABASE_ERROR"
            );
            return false;
        }
    }

    /**
     * Ensures the account_type enum accepts the 'standard' role.
     *
     * @return void
     */
    private function ensureSchemaSupportsStandardUser()
    {
        try
        {
            $result = $this->fetchOne(
                "SELECT COLUMN_TYPE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = :database
                   AND TABLE_NAME = 'users'
                   AND COLUMN_NAME = 'account_type'",
                array('database' => DB_NAME)
            );

            if (!$result)
            {
                writeLog("Could not check account_type column definition.", "DATABASE_ERROR");
                return;
            }

            $columnType = $result['COLUMN_TYPE'];

            if (strpos($columnType, "'standard'") === false)
            {
                writeLog("account_type ENUM does not include 'standard'. Updating schema...", "DATABASE");

                $this->executeQuery(
                    "ALTER TABLE `users`
                     MODIFY COLUMN `account_type`
                     ENUM('admin', 'vendor', 'student', 'standard')
                     NOT NULL
                     COMMENT 'User role: admin, vendor, student, or standard'"
                );

                writeLog("account_type ENUM updated to include 'standard'.", "DATABASE");
            }
        }
        catch (Exception $exception)
        {
            writeLog('Failed to ensure schema supports standard user: ' . $exception->getMessage(), "DATABASE_ERROR");
        }
    }

    /**
     * Ensures the admin account exists and is in a usable state.
     *
     * @return void
     */
    private function ensureAdminAccountExists()
    {
        try
        {
            $freshHash = password_hash(ADMIN_PASSWORD_PLAIN, PASSWORD_DEFAULT, array('cost' => BCRYPT_COST));

            $existingAdmin = $this->fetchOne(
                "SELECT `user_id`, `password_hash`, `is_verified`, `is_active`, `account_type`
                 FROM `users`
                 WHERE `username` = :username
                 LIMIT 1",
                array('username' => ADMIN_USERNAME)
            );

            if ($existingAdmin)
            {
                if (!password_verify(ADMIN_PASSWORD_PLAIN, $existingAdmin['password_hash']))
                {
                    $this->executeQuery(
                        "UPDATE `users` SET `password_hash` = :password_hash, `updated_at` = NOW()
                         WHERE `user_id` = :user_id",
                        array(
                            'password_hash' => $freshHash,
                            'user_id' => $existingAdmin['user_id']
                        )
                    );
                    writeLog('Admin password hash updated.', "DATABASE");
                }

                if ($existingAdmin['account_type'] !== 'admin')
                {
                    $this->executeQuery(
                        "UPDATE `users` SET `account_type` = 'admin', `updated_at` = NOW()
                         WHERE `user_id` = :user_id",
                        array('user_id' => $existingAdmin['user_id'])
                    );
                    writeLog('Admin account type corrected to admin.', "DATABASE");
                }

                if ($existingAdmin['is_verified'] != 1)
                {
                    $this->executeQuery(
                        "UPDATE `users` SET `is_verified` = 1, `updated_at` = NOW()
                         WHERE `user_id` = :user_id",
                        array('user_id' => $existingAdmin['user_id'])
                    );
                    writeLog('Admin account verified.', "DATABASE");
                }

                if ($existingAdmin['is_active'] != 1)
                {
                    $this->executeQuery(
                        "UPDATE `users` SET `is_active` = 1, `updated_at` = NOW()
                         WHERE `user_id` = :user_id",
                        array('user_id' => $existingAdmin['user_id'])
                    );
                    writeLog('Admin account activated.', "DATABASE");
                }

                return;
            }

            $adminUniqueId = date('YmdHis') . '01';

            $this->insert(
                "INSERT INTO `users`
                    (`unique_id`, `full_name`, `username`, `email`, `password_hash`,
                     `account_type`, `is_verified`, `is_active`, `created_at`, `updated_at`)
                 VALUES
                    (:unique_id, :full_name, :username, :email, :password_hash,
                     'admin', 1, 1, NOW(), NOW())",
                array(
                    'unique_id'     => $adminUniqueId,
                    'full_name'     => ADMIN_FULL_NAME,
                    'username'      => ADMIN_USERNAME,
                    'email'         => ADMIN_EMAIL,
                    'password_hash' => $freshHash
                )
            );

            writeLog('Admin account created successfully with username: ' . ADMIN_USERNAME, "DATABASE");
        }
        catch (PDOException $exception)
        {
            writeLog('Failed to ensure admin account: ' . $exception->getMessage(), "DATABASE_ERROR");
        }
    }

    /**
     * Ensures the user_sessions table exists.
     *
     * @return void
     */
    private function ensureUserSessionsTableExists()
    {
        try
        {
            if ($this->tableExists('user_sessions'))
            {
                return;
            }

            writeLog("user_sessions table does not exist. Creating...", "DATABASE");

            $this->executeQuery(
                "CREATE TABLE IF NOT EXISTS `user_sessions`
                (
                    `session_id`    VARCHAR(128) NOT NULL PRIMARY KEY,
                    `user_id`       INT NOT NULL,
                    `ip_address`    VARCHAR(45) NOT NULL,
                    `user_agent`    TEXT NULL,
                    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
                    INDEX `idx_user_id` (`user_id`),
                    INDEX `idx_last_activity` (`last_activity`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Stores active user sessions for session management and tracking'"
            );

            writeLog("user_sessions table created successfully.", "DATABASE");
        }
        catch (PDOException $exception)
        {
            writeLog('Failed to create user_sessions table: ' . $exception->getMessage(), "DATABASE_ERROR");
        }
    }

    /**
     * Ensures the demo accounts exist and match the configured passwords.
     *
     * @return void
     */
    private function ensureDemoAccountsExist()
    {
        try
        {
            $demoAccounts = unserialize(DEMO_ACCOUNTS);

            if (!is_array($demoAccounts))
            {
                writeLog('Failed to unserialize demo accounts configuration.', "DATABASE_ERROR");
                return;
            }

            $accountsCreated = 0;

            foreach ($demoAccounts as $account)
            {
                $existingUser = $this->fetchOne(
                    "SELECT `user_id`, `password_hash`, `account_type`, `is_verified`, `is_active`
                     FROM `users`
                     WHERE `email` = :email OR `username` = :username
                     LIMIT 1",
                    array(
                        'email' => $account['email'],
                        'username' => $account['username']
                    )
                );

                if ($existingUser)
                {
                    $currentUserId = (int)$existingUser['user_id'];
                    $expectedUserId = (int)$account['user_id'];

                    if ($currentUserId !== $expectedUserId)
                    {
                        writeLog(
                            "User ID mismatch for {$account['email']}. " .
                            "Expected: {$expectedUserId}, Found: {$currentUserId}. " .
                            "Run seed.php to align all demo accounts.",
                            "DATABASE_WARNING"
                        );
                    }

                    if (!password_verify($account['password'], $existingUser['password_hash']))
                    {
                        $this->executeQuery(
                            "UPDATE `users` SET `password_hash` = :password_hash, `updated_at` = NOW()
                             WHERE `user_id` = :user_id",
                            array(
                                'password_hash' => hashPassword($account['password']),
                                'user_id' => $existingUser['user_id']
                            )
                        );
                        writeLog("Updated password for: {$account['email']}", "DATABASE");
                    }

                    continue;
                }

                $uniqueId = generateUserId($account['account_type']);
                $passwordHash = hashPassword($account['password']);

                $userId = $this->insert(
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
                    $accountsCreated++;
                    writeLog(
                        "Demo account created: {$account['email']} "
                            . "(ID: {$account['user_id']}, Role: {$account['account_type']})",
                        "DATABASE"
                    );

                    if ($account['account_type'] === 'vendor' && !empty($account['vendor_name']))
                    {
                        $this->insert(
                            "INSERT INTO `vendors`
                                (`vendor_user_id`, `vendor_name`, `description`, `is_open`, `is_approved`, `created_at`)
                             VALUES
                                (:user_id, :vendor_name, :description, 1, 1, NOW())",
                            array(
                                'user_id'     => $userId,
                                'vendor_name' => $account['vendor_name'],
                                'description' => $account['description'] ?? 'Campus food vendor.'
                            )
                        );
                        writeLog("Vendor profile created for: {$account['vendor_name']}", "DATABASE");
                    }
                }
            }

            if ($accountsCreated > 0)
            {
                writeLog("Created {$accountsCreated} new demo accounts.", "DATABASE");
            }
        }
        catch (Exception $exception)
        {
            writeLog('Failed to ensure demo accounts: ' . $exception->getMessage(), "DATABASE_ERROR");
        }
    }

    /**
     * Ensures the login_attempts table exists.
     *
     * @return void
     */
    private function ensureLoginAttemptsTableExists()
    {
        try
        {
            if ($this->tableExists('login_attempts'))
            {
                return;
            }

            $this->executeQuery(
                "CREATE TABLE IF NOT EXISTS `login_attempts`
                (
                    `attempt_id`   INT AUTO_INCREMENT PRIMARY KEY,
                    `ip_address`   VARCHAR(45) NOT NULL,
                    `username`     VARCHAR(100) NOT NULL,
                    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );

            writeLog('Created login_attempts table.', "DATABASE");
        }
        catch (PDOException $exception)
        {
            writeLog('Failed to create login_attempts table: ' . $exception->getMessage(), "DATABASE_ERROR");
        }
    }

    /**
     * Ensures the password_reset_attempts table exists.
     *
     * @return void
     */
    private function ensurePasswordResetAttemptsTableExists()
    {
        try
        {
            if ($this->tableExists('password_reset_attempts'))
            {
                return;
            }

            $this->executeQuery(
                "CREATE TABLE IF NOT EXISTS `password_reset_attempts`
                (
                    `attempt_id`   INT AUTO_INCREMENT PRIMARY KEY,
                    `ip_address`   VARCHAR(45) NOT NULL,
                    `email`        VARCHAR(100) NOT NULL,
                    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX `idx_ip_email_time` (`ip_address`, `email`, `attempted_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
                COMMENT='Stores password reset attempts for rate limiting'"
            );

            writeLog('Created password_reset_attempts table.', "DATABASE");
        }
        catch (PDOException $exception)
        {
            writeLog('Failed to create password_reset_attempts table: ' . $exception->getMessage(), "DATABASE_ERROR");
        }
    }

    /**
     * Executes a prepared statement.
     *
     * Closes any previous cursor before preparing a new statement.
     *
     * @param string $sql    SQL query with named placeholders
     * @param array  $params Associative array of parameters
     * @return PDOStatement  The executed statement
     */
    public function executeQuery($sql, $params = array())
    {
        try
        {
            $this->connect();

            if ($this->statement !== null)
            {
                try
                {
                    $this->statement->closeCursor();
                }
                catch (PDOException $e)
                {
                    // Ignore cursor close errors on already-closed statements.
                }
                $this->statement = null;
            }

            $this->statement = $this->connection->prepare($sql);

            if ($this->statement === false)
            {
                throw new PDOException("Failed to prepare statement: " . $sql);
            }

            foreach ($params as $key => $value)
            {
                $paramType = PDO::PARAM_STR;

                if (is_int($value))
                {
                    $paramType = PDO::PARAM_INT;
                }
                elseif (is_bool($value))
                {
                    $paramType = PDO::PARAM_BOOL;
                }
                elseif (is_null($value))
                {
                    $paramType = PDO::PARAM_NULL;
                }

                $this->statement->bindValue(':' . $key, $value, $paramType);
            }

            $this->statement->execute();
            return $this->statement;
        }
        catch (PDOException $exception)
        {
            writeLog('Query failed: ' . $exception->getMessage() . ' | SQL: ' . $sql, "DATABASE_ERROR");
            throw $exception;
        }
    }

    /**
     * Fetches a single row from a query.
     *
     * @param string $sql    SQL query with named placeholders
     * @param array  $params Associative array of parameters
     * @return array|false   The row data, or false if no rows matched
     */
    public function fetchOne($sql, $params = array())
    {
        try
        {
            $this->executeQuery($sql, $params);
            $result = $this->statement->fetch();

            $this->statement->closeCursor();
            $this->statement = null;

            return $result;
        }
        catch (PDOException $exception)
        {
            writeLog('fetchOne failed: ' . $exception->getMessage(), "DATABASE_ERROR");
            throw $exception;
        }
    }

    /**
     * Fetches all rows from a query.
     *
     * @param string $sql    SQL query with named placeholders
     * @param array  $params Associative array of parameters
     * @return array         Array of rows
     */
    public function fetchAll($sql, $params = array())
    {
        try
        {
            $this->executeQuery($sql, $params);
            $result = $this->statement->fetchAll();

            $this->statement->closeCursor();
            $this->statement = null;

            return $result;
        }
        catch (PDOException $exception)
        {
            writeLog('fetchAll failed: ' . $exception->getMessage(), "DATABASE_ERROR");
            throw $exception;
        }
    }

    /**
     * Inserts a row and returns the last insert ID.
     *
     * @param string $sql    SQL insert statement
     * @param array  $params Associative array of parameters
     * @return int           The last insert ID
     */
    public function insert($sql, $params = array())
    {
        try
        {
            $this->executeQuery($sql, $params);
            $insertId = (int)$this->connection->lastInsertId();

            $this->statement->closeCursor();
            $this->statement = null;

            return $insertId;
        }
        catch (PDOException $exception)
        {
            writeLog('insert failed: ' . $exception->getMessage(), "DATABASE_ERROR");
            throw $exception;
        }
    }

    /**
     * Returns the number of rows affected by the most recent statement.
     *
     * @return int
     */
    public function rowCount()
    {
        if ($this->statement === null)
        {
            return 0;
        }

        return $this->statement->rowCount();
    }

    /**
     * Begins a transaction.
     *
     * @return bool True on success
     */
    public function beginTransaction()
    {
        if ($this->inTransaction)
        {
            writeLog("Transaction already in progress", "DATABASE");
            return false;
        }

        $this->connect();
        $result = $this->connection->beginTransaction();

        if ($result)
        {
            $this->inTransaction = true;
            writeLog("Transaction started", "DATABASE");
        }

        return $result;
    }

    /**
     * Commits the active transaction.
     *
     * @return bool True on success
     */
    public function commit()
    {
        if (!$this->inTransaction)
        {
            writeLog("No transaction to commit", "DATABASE");
            return false;
        }

        $result = $this->connection->commit();

        if ($result)
        {
            $this->inTransaction = false;
            writeLog("Transaction committed", "DATABASE");
        }

        return $result;
    }

    /**
     * Rolls back the active transaction.
     *
     * @return bool True on success
     */
    public function rollback()
    {
        if (!$this->inTransaction)
        {
            writeLog("No transaction to rollback", "DATABASE");
            return false;
        }

        $result = $this->connection->rollback();

        if ($result)
        {
            $this->inTransaction = false;
            writeLog("Transaction rolled back", "DATABASE");
        }

        return $result;
    }

    /**
     * Prevents cloning of the singleton.
     */
    private function __clone()
    {
    }

    /**
     * Prevents unserialization of the singleton.
     *
     * @throws Exception Always
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize a singleton.");
    }
}

// =============================================================================
// Global Accessor
// =============================================================================

if (!function_exists('getDB'))
{
    /**
     * Returns the application's shared DatabaseConnection instance.
     *
     * @return DatabaseConnection
     */
    function getDB()
    {
        return DatabaseConnection::getInstance();
    }
}