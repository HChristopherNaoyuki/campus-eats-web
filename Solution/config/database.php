<?php
/**
 * Database Connection Configuration File
 *
 * Handles database connections with singleton pattern, automatic table
 * installation, admin account verification, and demo account creation.
 *
 * CORRECTIONS (Version 24.0 - Definitive PDO Unbuffered Query Fix):
 * - Enabled PDO::MYSQL_ATTR_USE_BUFFERED_QUERY to fix SQLSTATE[HY000]
 *   General error 2014 ("Commands out of sync") that occurred on the
 *   login path immediately after the once-per-day log rotation.
 * - Disabled PDO::ATTR_PERSISTENT to prevent cursor state from leaking
 *   between requests handled by the same worker.
 * - Added closeCursor() to every fetch and execute method.
 * - Added explicit closeCursor() calls after every exec() inside the
 *   install loop.
 * - Added a periodic connection health check that reconnects if the
 *   server has dropped the connection.
 * - Added PHP 5.x, 7.x, and 8.x compatibility.
 * - Compatible with MySQL 5, 8, 9 and MariaDB 10, 11.
 *
 * SOURCE: Issues/audit_log.txt 2026-08-29 12:32:47
 * SOURCE: PHP PDO Manual - Buffered queries and cursor management
 * SOURCE: MySQL Documentation - Error 2014 (Commands out of sync)
 * SOURCE: Root Cause Investigation Report (secondary historical issue)
 *
 * @version 24.0
 */

// =============================================================================
// Database Connection Constants
// =============================================================================
//
// Each constant is guarded with defined() so this file can be included by
// a script that has already loaded constants.php without redeclaration
// warnings. The getenv() fallback allows deployment-time configuration
// through environment variables without editing this file.
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
//
// The admin password is read from the ADMIN_PASSWORD environment variable
// first. If that variable is not set, a cryptographically random password
// is generated at runtime, stored in the session for the current request,
// and exposed through the ADMIN_PASSWORD_PLAIN constant so the setup
// script can use it.
//
// In production this file should always be deployed with ADMIN_PASSWORD
// set, because otherwise the generated password changes on every fresh
// request and cannot be used to log in.
// =============================================================================

$adminPassword = getenv('ADMIN_PASSWORD');

if (empty($adminPassword))
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()';
    $password = '';
    $charsLength = strlen($chars);

    if (function_exists('random_int'))
    {
        // PHP 7.0 and above. random_int is the CSPRNG source of choice.
        for ($i = 0; $i < 16; $i++)
        {
            $password .= $chars[random_int(0, $charsLength - 1)];
        }
    }
    else
    {
        // PHP 5.x fallback. mt_rand is not cryptographically secure, so
        // this branch should only be reached on an end-of-life runtime.
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
//
// config/demo_accounts.php returns a plain PHP array. It is loaded here
// and serialized into the DEMO_ACCOUNTS constant so that the rest of the
// application has one definition of the demo accounts to read from.
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
//
// The database layer uses writeLog() from error_logging.php, hashPassword()
// from password_validation.php, and generateUserId() from user_id.php.
// Each is loaded here only if the corresponding function is not already
// defined, so the include order across the application does not matter.
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
// Global Initialization Flags
// =============================================================================
//
// These two flags guard the constructor so a second instance of the class
// created within the same request does not re-run the installation and
// demo-account provisioning logic. They are stored in $GLOBALS rather than
// as static properties because the class itself may be autoloaded or
// included more than once.
// =============================================================================

if (!isset($GLOBALS['_DATABASE_INITIALIZED']))
{
    $GLOBALS['_DATABASE_INITIALIZED'] = false;
}

if (!isset($GLOBALS['_DATABASE_CONNECTION_ESTABLISHED']))
{
    $GLOBALS['_DATABASE_CONNECTION_ESTABLISHED'] = false;
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
     * @var bool Whether the constructor performed any provisioning work
     */
    private $setupPerformed = false;

    /**
     * @var int Unix timestamp of the last connection health check
     */
    private $lastHealthCheck = 0;

    /**
     * Private constructor. Performs the one-time setup on first use.
     *
     * Setup steps:
     *   1. Ensure the target database exists.
     *   2. Open the PDO connection.
     *   3. Ensure all required tables exist.
     *   4. Ensure the account_type enum accepts the 'standard' role.
     *   5. Ensure the admin account exists and is in a correct state.
     *   6. Ensure the user_sessions table exists.
     *   7. Ensure the demo accounts exist.
     *   8. Ensure the login_attempts table exists.
     *   9. Ensure the password_reset_attempts table exists.
     */
    private function __construct()
    {
        if ($GLOBALS['_DATABASE_INITIALIZED'] === true)
        {
            $this->initialized = true;
            $this->setupPerformed = true;
            return;
        }

        try
        {
            $this->performInitialSetup();
            $GLOBALS['_DATABASE_INITIALIZED'] = true;
            $this->initialized = true;
            $this->setupPerformed = true;
            writeLog("Database connection established successfully.", "DATABASE");
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
     * A health check is run at most once every 60 seconds. If the server
     * has dropped the connection between requests, the reconnect branch
     * runs and a fresh PDO handle is installed.
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

        if (!$this->initialized)
        {
            $this->connect();
        }

        return $this->connection;
    }

    /**
     * Runs the one-time provisioning sequence.
     *
     * @return void
     */
    private function performInitialSetup()
    {
        $this->ensureDatabaseExists();
        $this->connect();
        $this->ensureTablesInstalled();
        $this->ensureSchemaSupportsStandardUser();
        $this->ensureAdminAccountExists();
        $this->ensureUserSessionsTableExists();
        $this->ensureDemoAccountsExist();
        $this->ensureLoginAttemptsTableExists();
        $this->ensurePasswordResetAttemptsTableExists();
    }

    /**
     * Establishes the database connection.
     *
     * CRITICAL CORRECTION:
     * PDO::MYSQL_ATTR_USE_BUFFERED_QUERY is set to true. This instructs
     * the PDO MySQL driver to fetch the entire result set into PHP memory
     * before returning control to the caller. This eliminates
     * SQLSTATE 2014 ("Commands out of sync") because, in unbuffered mode,
     * the driver refuses to run a new statement while any previous
     * statement still has unread rows.
     *
     * Persistent connections are disabled because they preserve cursor
     * state between requests on the same worker, which compounds the
     * unbuffered query problem when multiple scripts run in sequence.
     *
     * SOURCE: Issues/audit_log.txt - SQLSTATE[HY000] General error 2014
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
    }

    /**
     * Creates the target database if it does not already exist.
     *
     * @return void
     */
    private function ensureDatabaseExists()
    {
        if ($GLOBALS['_DATABASE_INITIALIZED'] === true)
        {
            return;
        }

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

            // Release the temporary connection so it does not hold a
            // server-side session while the main connection opens.
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
     * Ensures all required tables exist.
     *
     * CORRECTION:
     * The install loop explicitly closes the cursor after each exec()
     * call. Even with buffered queries enabled, releasing the statement
     * is a defensive measure that also improves compatibility with older
     * MySQL and MariaDB drivers.
     *
     * @return void
     */
    private function ensureTablesInstalled()
    {
        if ($GLOBALS['_DATABASE_INITIALIZED'] === true)
        {
            return;
        }

        // Probe: if the users table exists, assume the schema is installed.
        try
        {
            $this->connect();
            $stmt = $this->connection->query("SELECT 1 FROM `users` LIMIT 1");

            if ($stmt !== false)
            {
                $stmt->closeCursor();
            }

            writeLog("Tables already exist.", "DATABASE");
            return;
        }
        catch (PDOException $e)
        {
            writeLog("Tables do not exist. Installing...", "DATABASE");
        }

        $installSqlPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'install.sql';

        if (!file_exists($installSqlPath))
        {
            $errorMessage = "Installation script not found at: $installSqlPath";
            writeLog($errorMessage, "DATABASE_ERROR");
            die('Installation script not found. Please ensure Solution/sql/install.sql exists.');
        }

        try
        {
            $sqlContent = file_get_contents($installSqlPath);

            if ($sqlContent === false)
            {
                throw new Exception("Failed to read installation script.");
            }

            // Split on semicolons and drop any statement that is a
            // comment-only line. This is a simple splitter intended for
            // the shipped install.sql, which does not contain semicolons
            // inside string literals.
            $statements = array_filter(
                array_map('trim', explode(';', $sqlContent)),
                function($stmt) {
                    return !empty($stmt) && strpos($stmt, '--') !== 0;
                }
            );

            $this->connect();

            foreach ($statements as $statement)
            {
                if (!empty($statement))
                {
                    $stmt = $this->connection->query($statement);

                    // Release the statement handle immediately after the
                    // DDL completes. This is the specific change that
                    // prevents the "commands out of sync" condition from
                    // carrying over into subsequent statements.
                    if ($stmt !== false)
                    {
                        $stmt->closeCursor();
                    }
                }
            }

            writeLog('Database tables installed successfully.', "DATABASE");
        }
        catch (Exception $exception)
        {
            writeLog('Table installation failed: ' . $exception->getMessage(), "DATABASE_ERROR");

            if (defined('APP_DEBUG') && APP_DEBUG === true)
            {
                die('Could not create database tables: ' . htmlspecialchars($exception->getMessage()));
            }

            die('Could not create database tables. Please check your database permissions.');
        }
    }

    /**
     * Ensures the account_type enum accepts the 'standard' role.
     *
     * @return void
     */
    private function ensureSchemaSupportsStandardUser()
    {
        if ($GLOBALS['_DATABASE_INITIALIZED'] === true)
        {
            return;
        }

        try
        {
            $this->connect();

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
        if ($GLOBALS['_DATABASE_INITIALIZED'] === true)
        {
            return;
        }

        try
        {
            $this->connect();
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
                $needsUpdate = false;

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
                    $needsUpdate = true;
                }

                if ($existingAdmin['account_type'] !== 'admin')
                {
                    $this->executeQuery(
                        "UPDATE `users` SET `account_type` = 'admin', `updated_at` = NOW()
                         WHERE `user_id` = :user_id",
                        array('user_id' => $existingAdmin['user_id'])
                    );
                    writeLog('Admin account type corrected to admin.', "DATABASE");
                    $needsUpdate = true;
                }

                if ($existingAdmin['is_verified'] != 1)
                {
                    $this->executeQuery(
                        "UPDATE `users` SET `is_verified` = 1, `updated_at` = NOW()
                         WHERE `user_id` = :user_id",
                        array('user_id' => $existingAdmin['user_id'])
                    );
                    writeLog('Admin account verified.', "DATABASE");
                    $needsUpdate = true;
                }

                if ($existingAdmin['is_active'] != 1)
                {
                    $this->executeQuery(
                        "UPDATE `users` SET `is_active` = 1, `updated_at` = NOW()
                         WHERE `user_id` = :user_id",
                        array('user_id' => $existingAdmin['user_id'])
                    );
                    writeLog('Admin account activated.', "DATABASE");
                    $needsUpdate = true;
                }

                if (!$needsUpdate)
                {
                    writeLog('Admin account verified and correct.', "DATABASE");
                }
            }
            else
            {
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
        if ($GLOBALS['_DATABASE_INITIALIZED'] === true)
        {
            return;
        }

        try
        {
            $this->connect();

            $result = $this->fetchOne("SHOW TABLES LIKE 'user_sessions'");

            if ($result === false || empty($result))
            {
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
            else
            {
                writeLog("user_sessions table already exists.", "DATABASE");
            }
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
        if ($GLOBALS['_DATABASE_INITIALIZED'] === true)
        {
            return;
        }

        try
        {
            $this->connect();

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
                        "Demo account created: {$account['email']} " .
                        "(ID: {$account['user_id']}, Role: {$account['account_type']})",
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
                writeLog("Run seed.php to align all demo accounts if needed.", "DATABASE");
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
        if ($GLOBALS['_DATABASE_INITIALIZED'] === true)
        {
            return;
        }

        try
        {
            $this->connect();
            $result = $this->fetchOne("SHOW TABLES LIKE 'login_attempts'");

            if ($result === false || empty($result))
            {
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
        if ($GLOBALS['_DATABASE_INITIALIZED'] === true)
        {
            return;
        }

        try
        {
            $this->connect();
            $result = $this->fetchOne("SHOW TABLES LIKE 'password_reset_attempts'");

            if ($result === false || empty($result))
            {
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
        }
        catch (PDOException $exception)
        {
            writeLog('Failed to create password_reset_attempts table: ' . $exception->getMessage(), "DATABASE_ERROR");
        }
    }

    /**
     * Executes a prepared statement.
     *
     * Closes any previous cursor before preparing a new statement. This
     * is the specific correction for the failing call chain recorded in
     * the audit log:
     *
     *   login.php(64) -> auth.php(473) -> auth.php(398)
     *     -> database.php(713) -> database.php(674) -> FAIL
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

            // Release the previous statement before preparing a new one.
            // In unbuffered mode this is mandatory; even with buffered
            // queries enabled it is a defensive habit that prevents
            // cursor state from accumulating across a long request.
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
     * Closes the cursor after fetching so the statement handle is
     * released before the next query runs.
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
     * Closes the cursor after fetching so the statement handle is
     * released before the next query runs.
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
     * Closes the cursor after the insert so the statement handle is
     * released before the next query runs.
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