<?php
/**
 * Database Connection Configuration File
 *
 * Handles database connections with a singleton pattern and automatic
 * schema installation.
 *
 * CORRECTIONS (Version 26.0 - Demo Account Removal):
 * - Removed the ensureAdminAccountExists() method. No user accounts are
 *   created by the installer.
 * - Removed the ensureDemoAccountsExist() method and the DEMO_ACCOUNTS
 *   constant. No sample users are inserted into the database.
 * - Added userCount(), a helper used by the registration page to decide
 *   whether the first user may register as an administrator.
 * - Retained all Version 25.0 corrections: separate schema-verified flag,
 *   post-installation verification, closeCursor() on every query, and
 *   the buffered-query fix.
 *
 * SOURCE: DEMO ACCOUNT REQUIREMENT (Interpretation C)
 *
 * @version 26.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/error_logging.php';

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
    private static $instance = null;
    private $connection;
    private $statement = null;
    private $inTransaction = false;
    private $initialized = false;
    private $schemaVerified = false;
    private $lastHealthCheck = 0;

    /**
     * Private constructor. Performs the one-time setup on first use.
     *
     * CORRECTION:
     * The constructor no longer creates an admin account and no longer
     * creates demo accounts. The schema is installed and verified, and
     * that is the only provisioning the installer performs. All user
     * accounts are created through the registration page.
     */
    private function __construct()
    {
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
            $this->ensureUserSessionsTableExists();
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

    private function ensureSchemaInstalled()
    {
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

        if (!$this->tableExists('users'))
        {
            $message = "Installation completed but the users table is still "
                     . "missing from database '" . DB_NAME . "'.";

            writeLog($message, "DATABASE_ERROR");
            throw new RuntimeException($message);
        }

        writeLog("Schema installation verified. users table is present.", "DATABASE");
    }

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
     * Returns the number of rows in the users table.
     *
     * CORRECTION: Added for the registration page. The registration page
     * calls this to decide whether the current visitor is the first user.
     * If the count is zero, the page offers the Admin role. Once any user
     * exists, the Admin option is no longer offered.
     *
     * @return int The number of users in the database
     */
    public function userCount()
    {
        try
        {
            $row = $this->fetchOne("SELECT COUNT(*) AS user_count FROM `users`");
            return isset($row['user_count']) ? (int)$row['user_count'] : 0;
        }
        catch (PDOException $exception)
        {
            writeLog('userCount failed: ' . $exception->getMessage(), "DATABASE_ERROR");
            return 0;
        }
    }

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

    // =========================================================================
    // The following methods are unchanged from Version 25.0:
    //   executeQuery, fetchOne, fetchAll, insert, rowCount,
    //   beginTransaction, commit, rollback, __clone, __wakeup
    // =========================================================================

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
    }

    public function __wakeup()
    {
        throw new Exception("Cannot unserialize a singleton.");
    }
}

if (!function_exists('getDB'))
{
    function getDB()
    {
        return DatabaseConnection::getInstance();
    }
}