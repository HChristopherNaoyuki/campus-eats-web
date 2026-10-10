<?php
/**
 * Database Connection Configuration File
 *
 * Handles database connections with a singleton pattern and automatic
 * schema installation. The application is MySQL-authoritative for
 * user accounts, authentication, registration, orders, carts,
 * vendors, and payments. Firebase is used only for the projection
 * written by the client-side synchronization worker and for feedback.
 *
 * CORRECTIONS (Version 31.0 - Demo Data Auto-Seeder):
 *
 * - After schema verification the constructor calls
 *   campus_eats_ensure_demo_data($this) when the helper is present.
 *   The first successful connection therefore creates the ten demo
 *   accounts and the three sample coupons without a manual seed run.
 * - Added public inTransaction() so callers can perform safe
 *   rollback checks without reading private state.
 * - All Version 30.0 behaviour is retained (no die(), connect order,
 *   single schema probe, comment-aware splitter, auxiliary tables).
 *
 * SOURCE: Technical Audit Update – Demo Accounts, Coupons, and SSL.
 * SOURCE: Clean Code, Robert C. Martin, Chapters 2–4.
 *
 * @version 31.0
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
// Schema-verification flag (process-wide)
// =============================================================================

if (!isset($GLOBALS['_DATABASE_SCHEMA_VERIFIED']))
{
    $GLOBALS['_DATABASE_SCHEMA_VERIFIED'] = false;
}

// =============================================================================
// Exception
// =============================================================================

if (!class_exists('DatabaseUnavailableException'))
{
    /**
     * Thrown when a query is attempted while the connection is not usable.
     */
    class DatabaseUnavailableException extends RuntimeException
    {
    }
}

// =============================================================================
// DatabaseConnection Singleton Class
// =============================================================================

class DatabaseConnection
{
    /**
     * @var DatabaseConnection|null The singleton instance
     */
    private static $instance = null;

    /**
     * @var PDO|null The PDO connection handle
     */
    private $connection;

    /**
     * @var PDOStatement|null The statement from the most recent execute
     */
    private $statement = null;

    /**
     * @var bool True while a transaction is open on this connection
     */
    private $inTransaction = false;

    /**
     * @var bool True when the connection is usable
     */
    private $available = false;

    /**
     * @var string The last recorded error message, or an empty string
     */
    private $lastError = '';

    /**
     * @var int Unix timestamp of the most recent health check
     */
    private $lastHealthCheck = 0;

    /**
     * Private constructor. Performs the one-time setup on first use.
     *
     * The constructor never calls die(). When the database is
     * unreachable, the failure is recorded in $lastError and the
     * instance remains available=false. Query methods throw a
     * catchable exception. The caller decides how to respond.
     *
     * After schema verification the constructor invokes the demo-data
     * seeder when the helper is present. Seeder failures are logged
     * and do not break page load.
     */
    private function __construct()
    {
        if ($GLOBALS['_DATABASE_SCHEMA_VERIFIED'] === true)
        {
            $this->available = true;

            // Still attempt a lightweight reconnect for subsequent
            // requests within the same process that already verified.
            try
            {
                $this->connect();
            }
            catch (PDOException $e)
            {
                $this->lastError = $e->getMessage();
                $this->available = false;
            }

            return;
        }

        try
        {
            // Create the database before opening the main connection.
            $this->ensureDatabaseExists();
            $this->connect();
            $this->ensureSchemaInstalled();
            $this->ensureUserSessionsTableExists();
            $this->ensureLoginAttemptsTableExists();
            $this->ensurePasswordResetAttemptsTableExists();
            $this->ensureFirebaseSyncStateTableExists();

            $GLOBALS['_DATABASE_SCHEMA_VERIFIED'] = true;
            $this->available = true;

            writeLog(
                'Database connection and schema verified successfully.',
                'DATABASE'
            );

            // Ensure demo accounts and sample coupons exist.
            // Failures are non-fatal and are logged inside the seeder.
            $ensureFile = BASE_PATH . '/config/ensure_demo_data.php';

            if (is_readable($ensureFile))
            {
                require_once $ensureFile;

                if (function_exists('campus_eats_ensure_demo_data'))
                {
                    campus_eats_ensure_demo_data($this);
                }
            }
        }
        catch (PDOException $exception)
        {
            $this->lastError = $exception->getMessage();
            $this->available = false;

            writeLog(
                'Database Connection Error: ' . $exception->getMessage(),
                'DATABASE_ERROR'
            );
        }
        catch (RuntimeException $exception)
        {
            $this->lastError = $exception->getMessage();
            $this->available = false;

            writeLog(
                'Database Setup Error: ' . $exception->getMessage(),
                'DATABASE_ERROR'
            );
        }
    }

    /**
     * Returns the singleton instance.
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
     * Returns true when the connection is usable.
     *
     * @return bool
     */
    public function isAvailable()
    {
        return $this->available;
    }

    /**
     * Returns the last recorded error message, or an empty string.
     *
     * @return string
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Returns true when a transaction is currently open.
     *
     * @return bool
     */
    public function inTransaction()
    {
        return $this->inTransaction;
    }

    /**
     * Returns the PDO connection handle.
     *
     * @return PDO
     * @throws DatabaseUnavailableException
     */
    public function getConnection()
    {
        if (!$this->available || $this->connection === null)
        {
            throw new DatabaseUnavailableException(
                $this->lastError !== ''
                    ? $this->lastError
                    : 'Database connection is not available.'
            );
        }

        $currentTime = time();

        if (($currentTime - $this->lastHealthCheck) > 60)
        {
            try
            {
                $stmt = $this->connection->query('SELECT 1');

                if ($stmt !== false)
                {
                    $stmt->closeCursor();
                }

                $this->lastHealthCheck = $currentTime;
            }
            catch (PDOException $e)
            {
                writeLog(
                    'Database connection lost, reconnecting...',
                    'DATABASE'
                );

                $this->connection = null;
                $this->available = false;

                try
                {
                    $this->connect();
                    $this->lastHealthCheck = $currentTime;
                    $this->available = true;
                }
                catch (PDOException $reconnectError)
                {
                    $this->lastError = $reconnectError->getMessage();
                    throw new DatabaseUnavailableException($this->lastError);
                }
            }
        }

        return $this->connection;
    }

    /**
     * Opens the PDO connection.
     *
     * @return void
     * @throws PDOException
     */
    private function connect()
    {
        if ($this->connection !== null)
        {
            return;
        }

        $dsn = 'mysql:host=' . DB_HOST
             . ';dbname=' . DB_NAME
             . ';charset=' . DB_CHARSET;

        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 5,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        );

        $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        $this->available = true;
        $this->lastHealthCheck = time();

        writeLog(
            "PDO connection opened to database '" . DB_NAME . "'.",
            'DATABASE'
        );
    }

    /**
     * Creates the target database if it does not exist.
     *
     * @return void
     * @throws PDOException
     */
    private function ensureDatabaseExists()
    {
        $dsn = 'mysql:host=' . DB_HOST . ';charset=' . DB_CHARSET;

        $tempConnection = new PDO($dsn, DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5
        ));

        $sql = 'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` '
             . 'CHARACTER SET ' . DB_CHARSET . ' '
             . 'COLLATE ' . DB_CHARSET . '_unicode_ci';

        $tempConnection->exec($sql);
        $tempConnection = null;

        writeLog(
            "Database '" . DB_NAME . "' ensured to exist.",
            'DATABASE'
        );
    }

    /**
     * Splits a SQL script into individual statements.
     *
     * @param string $sql
     * @return array
     */
    private function splitSqlStatements($sql)
    {
        $statements = array();
        $current = '';
        $length = strlen($sql);
        $state = 'NORMAL';
        $i = 0;

        while ($i < $length)
        {
            $char = $sql[$i];
            $next = ($i + 1 < $length) ? $sql[$i + 1] : '';

            switch ($state)
            {
                case 'NORMAL':
                    if ($char === '-' && $next === '-')
                    {
                        $state = 'LINE_COMMENT';
                        $current .= '--';
                        $i += 2;
                        continue 2;
                    }

                    if ($char === '/' && $next === '*')
                    {
                        $state = 'BLOCK_COMMENT';
                        $current .= '/*';
                        $i += 2;
                        continue 2;
                    }

                    if ($char === "'")
                    {
                        $state = 'SINGLE_QUOTE';
                        $current .= $char;
                        break;
                    }

                    if ($char === '"')
                    {
                        $state = 'DOUBLE_QUOTE';
                        $current .= $char;
                        break;
                    }

                    if ($char === '`')
                    {
                        $state = 'BACKTICK';
                        $current .= $char;
                        break;
                    }

                    if ($char === ';')
                    {
                        $trimmed = trim($current);

                        if ($trimmed !== '')
                        {
                            $statements[] = $trimmed;
                        }

                        $current = '';
                        break;
                    }

                    $current .= $char;
                    break;

                case 'LINE_COMMENT':
                    if ($char === "\n")
                    {
                        $state = 'NORMAL';
                    }

                    $current .= $char;
                    break;

                case 'BLOCK_COMMENT':
                    if ($char === '*' && $next === '/')
                    {
                        $current .= '*/';
                        $state = 'NORMAL';
                        $i += 2;
                        continue 2;
                    }

                    $current .= $char;
                    break;

                case 'SINGLE_QUOTE':
                    if ($char === '\\' && $next !== '')
                    {
                        $current .= $char . $next;
                        $i += 2;
                        continue 2;
                    }

                    if ($char === "'" && $next === "'")
                    {
                        $current .= "''";
                        $i += 2;
                        continue 2;
                    }

                    if ($char === "'")
                    {
                        $state = 'NORMAL';
                    }

                    $current .= $char;
                    break;

                case 'DOUBLE_QUOTE':
                    if ($char === '\\' && $next !== '')
                    {
                        $current .= $char . $next;
                        $i += 2;
                        continue 2;
                    }

                    if ($char === '"' && $next === '"')
                    {
                        $current .= '""';
                        $i += 2;
                        continue 2;
                    }

                    if ($char === '"')
                    {
                        $state = 'NORMAL';
                    }

                    $current .= $char;
                    break;

                case 'BACKTICK':
                    if ($char === '`' && $next === '`')
                    {
                        $current .= '``';
                        $i += 2;
                        continue 2;
                    }

                    if ($char === '`')
                    {
                        $state = 'NORMAL';
                    }

                    $current .= $char;
                    break;
            }

            $i++;
        }

        $trimmed = trim($current);

        if ($trimmed !== '' && !$this->isCommentOnly($trimmed))
        {
            $statements[] = $trimmed;
        }

        $filtered = array();

        foreach ($statements as $statement)
        {
            if (preg_match('/^USE\s+/i', trim($statement)))
            {
                continue;
            }

            $filtered[] = $statement;
        }

        return $filtered;
    }

    /**
     * Returns true when a piece contains no executable SQL.
     *
     * @param string $piece
     * @return bool
     */
    private function isCommentOnly($piece)
    {
        $trimmed = trim($piece);

        if ($trimmed === '')
        {
            return true;
        }

        if (strpos($trimmed, '/*') === 0 && substr($trimmed, -2) === '*/')
        {
            $closing = strpos($trimmed, '*/');
            $after = trim(substr($trimmed, $closing + 2));

            if ($after === '')
            {
                return true;
            }
        }

        $lines = preg_split('/\r\n|\r|\n/', $trimmed);
        $hasCode = false;

        foreach ($lines as $line)
        {
            $line = trim($line);

            if ($line === '' || strpos($line, '--') === 0)
            {
                continue;
            }

            $hasCode = true;
            break;
        }

        return !$hasCode;
    }

    /**
     * Installs the schema when the users table is not present.
     *
     * @return void
     * @throws RuntimeException
     */
    private function ensureSchemaInstalled()
    {
        if ($this->tableExists('users'))
        {
            writeLog(
                'Schema probe: users table already exists.',
                'DATABASE'
            );
            return;
        }

        writeLog(
            'Schema probe: users table not found. Running install.sql.',
            'DATABASE'
        );

        $installSqlPath = dirname(__DIR__)
                        . DIRECTORY_SEPARATOR . 'sql'
                        . DIRECTORY_SEPARATOR . 'install.sql';

        if (!file_exists($installSqlPath))
        {
            $message = 'Installation script not found at: ' . $installSqlPath;
            writeLog($message, 'DATABASE_ERROR');
            throw new RuntimeException($message);
        }

        $sqlContent = file_get_contents($installSqlPath);

        if ($sqlContent === false)
        {
            $message = 'Failed to read installation script: ' . $installSqlPath;
            writeLog($message, 'DATABASE_ERROR');
            throw new RuntimeException($message);
        }

        if (substr($sqlContent, 0, 3) === "\xEF\xBB\xBF")
        {
            $sqlContent = substr($sqlContent, 3);
            writeLog('Stripped UTF-8 BOM from install.sql.', 'DATABASE');
        }

        $statements = $this->splitSqlStatements($sqlContent);

        writeLog(
            'Parsed ' . count($statements) . ' SQL statement(s) from install.sql.',
            'DATABASE'
        );

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
                $snippet = substr(
                    preg_replace('/\s+/', ' ', $statement),
                    0,
                    200
                );

                writeLog(
                    'Install statement ' . ($index + 1) . ' failed: '
                        . $exception->getMessage()
                        . ' | Statement: ' . $snippet,
                    'DATABASE_ERROR'
                );

                throw $exception;
            }
        }

        if (!$this->tableExists('users'))
        {
            $message = "Installation completed but the users table is "
                     . "still missing from database '" . DB_NAME . "'. "
                     . 'Check that install.sql contains a CREATE TABLE '
                     . 'users statement and that DB_NAME points at the '
                     . 'database the script targets.';

            writeLog($message, 'DATABASE_ERROR');
            throw new RuntimeException($message);
        }

        writeLog(
            'Schema installation verified. users table is present.',
            'DATABASE'
        );
    }

    /**
     * Returns true when a table exists in the target database.
     *
     * @param string $tableName
     * @return bool
     */
    private function tableExists($tableName)
    {
        try
        {
            $stmt = $this->connection->prepare(
                'SELECT COUNT(*) AS table_count
                 FROM information_schema.tables
                 WHERE table_schema = :database
                   AND table_name = :table'
            );

            $stmt->bindValue(':database', DB_NAME, PDO::PARAM_STR);
            $stmt->bindValue(':table', $tableName, PDO::PARAM_STR);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return isset($row['table_count'])
                && (int)$row['table_count'] > 0;
        }
        catch (PDOException $exception)
        {
            writeLog(
                "tableExists check failed for '$tableName': "
                    . $exception->getMessage(),
                'DATABASE_ERROR'
            );
            return false;
        }
    }

    /**
     * Returns the number of rows in the users table.
     *
     * @return int
     */
    public function userCount()
    {
        try
        {
            $row = $this->fetchOne(
                'SELECT COUNT(*) AS user_count FROM `users`'
            );

            return isset($row['user_count'])
                ? (int)$row['user_count']
                : 0;
        }
        catch (Exception $exception)
        {
            writeLog(
                'userCount failed: ' . $exception->getMessage(),
                'DATABASE_ERROR'
            );
            return 0;
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

            $this->executeQuery(
                "CREATE TABLE IF NOT EXISTS `user_sessions`
                (
                    `session_id`   VARCHAR(128) NOT NULL PRIMARY KEY,
                    `user_id`      INT NOT NULL,
                    `ip_address`   VARCHAR(45) DEFAULT NULL,
                    `user_agent`   VARCHAR(255) DEFAULT NULL,
                    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
                    INDEX `idx_user_id` (`user_id`),
                    INDEX `idx_last_activity` (`last_activity`)
                ) ENGINE=InnoDB
                  DEFAULT CHARSET=utf8mb4
                  COLLATE=utf8mb4_unicode_ci
                  COMMENT='Active session mappings for authenticated users'"
            );

            writeLog('Created user_sessions table.', 'DATABASE');
        }
        catch (PDOException $exception)
        {
            writeLog(
                'Failed to create user_sessions table: '
                    . $exception->getMessage(),
                'DATABASE_ERROR'
            );
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
                    INDEX `idx_ip_user_time`
                        (`ip_address`, `username`, `attempted_at`)
                ) ENGINE=InnoDB
                  DEFAULT CHARSET=utf8mb4
                  COLLATE=utf8mb4_unicode_ci
                  COMMENT='Stores failed login attempts for rate limiting'"
            );

            writeLog('Created login_attempts table.', 'DATABASE');
        }
        catch (PDOException $exception)
        {
            writeLog(
                'Failed to create login_attempts table: '
                    . $exception->getMessage(),
                'DATABASE_ERROR'
            );
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
                    INDEX `idx_ip_email_time`
                        (`ip_address`, `email`, `attempted_at`)
                ) ENGINE=InnoDB
                  DEFAULT CHARSET=utf8mb4
                  COLLATE=utf8mb4_unicode_ci
                  COMMENT='Stores password reset attempts'"
            );

            writeLog('Created password_reset_attempts table.', 'DATABASE');
        }
        catch (PDOException $exception)
        {
            writeLog(
                'Failed to create password_reset_attempts table: '
                    . $exception->getMessage(),
                'DATABASE_ERROR'
            );
        }
    }

    /**
     * Ensures the firebase_sync_state table exists.
     *
     * @return void
     */
    private function ensureFirebaseSyncStateTableExists()
    {
        try
        {
            if ($this->tableExists('firebase_sync_state'))
            {
                return;
            }

            $this->executeQuery(
                "CREATE TABLE IF NOT EXISTS `firebase_sync_state`
                (
                    `sync_id`        INT AUTO_INCREMENT PRIMARY KEY,
                    `user_id`        INT NOT NULL,
                    `node`           VARCHAR(64) NOT NULL,
                    `record_key`     VARCHAR(64) NOT NULL,
                    `payload_hash`   VARCHAR(64) NOT NULL,
                    `synced_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                     ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY `uq_user_node_record`
                        (`user_id`, `node`, `record_key`),
                    INDEX `idx_user_id` (`user_id`),
                    INDEX `idx_synced_at` (`synced_at`)
                ) ENGINE=InnoDB
                  DEFAULT CHARSET=utf8mb4
                  COLLATE=utf8mb4_unicode_ci
                  COMMENT='Tracks the last projection written to Firebase'"
            );

            writeLog('Created firebase_sync_state table.', 'DATABASE');
        }
        catch (PDOException $exception)
        {
            writeLog(
                'Failed to create firebase_sync_state table: '
                    . $exception->getMessage(),
                'DATABASE_ERROR'
            );
        }
    }

    /**
     * Executes a prepared statement.
     *
     * @param string $sql
     * @param array  $params
     * @return PDOStatement
     * @throws DatabaseUnavailableException
     * @throws PDOException
     */
    public function executeQuery($sql, $params = array())
    {
        $connection = $this->getConnection();

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

        $this->statement = $connection->prepare($sql);

        if ($this->statement === false)
        {
            throw new PDOException('Failed to prepare statement: ' . $sql);
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
            elseif ($value === null)
            {
                $paramType = PDO::PARAM_NULL;
            }

            if (is_int($key))
            {
                $this->statement->bindValue($key + 1, $value, $paramType);
            }
            else
            {
                $paramName = (strpos($key, ':') === 0) ? $key : ':' . $key;
                $this->statement->bindValue($paramName, $value, $paramType);
            }
        }

        $this->statement->execute();

        return $this->statement;
    }

    /**
     * Fetches a single row.
     *
     * @param string $sql
     * @param array  $params
     * @return array|null
     */
    public function fetchOne($sql, $params = array())
    {
        $stmt = $this->executeQuery($sql, $params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $row !== false ? $row : null;
    }

    /**
     * Fetches all rows.
     *
     * @param string $sql
     * @param array  $params
     * @return array
     */
    public function fetchAll($sql, $params = array())
    {
        $stmt = $this->executeQuery($sql, $params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $rows;
    }

    /**
     * Inserts a row and returns the last insert id.
     *
     * @param string $sql
     * @param array  $params
     * @return string
     */
    public function insert($sql, $params = array())
    {
        $this->executeQuery($sql, $params);
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Returns the number of rows affected by the last statement.
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
     * @return bool
     * @throws DatabaseUnavailableException
     */
    public function beginTransaction()
    {
        if ($this->inTransaction)
        {
            writeLog('Transaction already in progress', 'DATABASE');
            return false;
        }

        $connection = $this->getConnection();
        $result = $connection->beginTransaction();

        if ($result)
        {
            $this->inTransaction = true;
            writeLog('Transaction started', 'DATABASE');
        }

        return $result;
    }

    /**
     * Commits the current transaction.
     *
     * @return bool
     */
    public function commit()
    {
        if (!$this->inTransaction)
        {
            writeLog('No transaction to commit', 'DATABASE');
            return false;
        }

        $result = $this->getConnection()->commit();

        if ($result)
        {
            $this->inTransaction = false;
            writeLog('Transaction committed', 'DATABASE');
        }

        return $result;
    }

    /**
     * Rolls back the current transaction.
     *
     * @return bool
     */
    public function rollback()
    {
        if (!$this->inTransaction)
        {
            writeLog('No transaction to rollback', 'DATABASE');
            return false;
        }

        $result = $this->getConnection()->rollback();

        if ($result)
        {
            $this->inTransaction = false;
            writeLog('Transaction rolled back', 'DATABASE');
        }

        return $result;
    }

    /**
     * Prevents cloning the singleton.
     *
     * @return void
     */
    private function __clone()
    {
    }

    /**
     * Prevents unserializing the singleton.
     *
     * @return void
     * @throws Exception
     */
    public function __wakeup()
    {
        throw new Exception('Cannot unserialize a singleton.');
    }
}

if (!function_exists('getDB'))
{
    /**
     * Returns the shared DatabaseConnection instance.
     *
     * @return DatabaseConnection
     */
    function getDB()
    {
        return DatabaseConnection::getInstance();
    }
}