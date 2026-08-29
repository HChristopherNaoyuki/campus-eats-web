<?php
/**
 * Database Connection Configuration File - Refactored for Performance
 *
 * Handles database connections with singleton pattern, automatic table installation,
 * admin account verification, and demo account creation.
 *
 * CORRECTIONS (Version 18.0 - Demo Accounts Consolidation):
 * - Removed duplicate DEMO_ACCOUNTS definition
 * - Now includes demo_accounts.php for single source of truth
 * - Fixes SEC-01 and ARCH-01 from the scope note
 *
 * @version 18.0
 */

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

if (!defined('ADMIN_PASSWORD_PLAIN'))
{
    define('ADMIN_PASSWORD_PLAIN', 'Admin@123');
}

// =============================================================================
// Load Demo Accounts from Single Source of Truth
// CORRECTION: SEC-01 / ARCH-01 - Removed duplicate DEMO_ACCOUNTS array
// Source: Scope Note - SEC-01, ARCH-01
// =============================================================================
if (file_exists(__DIR__ . '/demo_accounts.php'))
{
    require_once __DIR__ . '/demo_accounts.php';
}
else
{
    // Fallback definition if demo_accounts.php is missing
    define('DEMO_ACCOUNTS', serialize(array()));
}

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

if (!isset($GLOBALS['_DATABASE_INITIALIZED']))
{
    $GLOBALS['_DATABASE_INITIALIZED'] = false;
}

if (!isset($GLOBALS['_DATABASE_CONNECTION_ESTABLISHED']))
{
    $GLOBALS['_DATABASE_CONNECTION_ESTABLISHED'] = false;
}

class DatabaseConnection
{
    private static $instance = null;
    private $connection;
    private $statement = null;
    private $inTransaction = false;
    private $initialized = false;
    private $setupPerformed = false;
    private $lastHealthCheck = 0;

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
            die('Database service is temporarily unavailable. Please try again later.');
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null)
        {
            self::$instance = new DatabaseConnection();
        }

        return self::$instance;
    }

    public function getConnection()
    {
        if ($this->connection !== null)
        {
            $currentTime = time();
            if (($currentTime - $this->lastHealthCheck) > 60)
            {
                try
                {
                    $this->connection->query("SELECT 1");
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
    }

    private function connect()
    {
        if ($this->connection !== null)
        {
            return;
        }

        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true,
            PDO::ATTR_TIMEOUT            => 5,
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES ' . DB_CHARSET
        );

        $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        $GLOBALS['_DATABASE_CONNECTION_ESTABLISHED'] = true;
        $this->lastHealthCheck = time();
    }

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
            $tempConnection = null;

            writeLog("Database '" . DB_NAME . "' ensured to exist.", "DATABASE");
        }
        catch (PDOException $exception)
        {
            writeLog('Database creation failed: ' . $exception->getMessage(), "DATABASE_ERROR");
            throw $exception;
        }
    }

    private function ensureTablesInstalled()
    {
        if ($GLOBALS['_DATABASE_INITIALIZED'] === true)
        {
            return;
        }

        try
        {
            $this->connect();
            $this->connection->query("SELECT 1 FROM `users` LIMIT 1");
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
                    $this->connection->exec($statement);
                }
            }

            writeLog('Database tables installed successfully.', "DATABASE");
        }
        catch (Exception $exception)
        {
            writeLog('Table installation failed: ' . $exception->getMessage(), "DATABASE_ERROR");
            die('Could not create database tables. Please check your database permissions.');
        }
    }

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
                    writeLog("Demo account created: {$account['email']} (ID: {$account['user_id']}, Role: {$account['account_type']})", "DATABASE");

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

    public function executeQuery($sql, $params = array())
    {
        try
        {
            $this->connect();
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

    public function fetchOne($sql, $params = array())
    {
        $this->executeQuery($sql, $params);
        return $this->statement->fetch();
    }

    public function fetchAll($sql, $params = array())
    {
        $this->executeQuery($sql, $params);
        return $this->statement->fetchAll();
    }

    public function insert($sql, $params = array())
    {
        $this->executeQuery($sql, $params);
        return (int)$this->connection->lastInsertId();
    }

    public function rowCount()
    {
        if ($this->statement === null)
        {
            return 0;
        }

        return $this->statement->rowCount();
    }

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

    private function __clone()
    {
        // Prevent cloning
    }

    public function __wakeup()
    {
        throw new Exception("Cannot unserialize a singleton.");
    }
}

function getDB()
{
    return DatabaseConnection::getInstance();
}