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
 * CORRECTIONS (Version 30.0 - Audit Continuation):
 *
 * - Fix 1 (splitter). The state machine that splits install.sql into
 *   statements now preserves both hyphens of a `--` comment. The
 *   previous version appended only one hyphen and advanced the loop
 *   index by one inside the block, so the loop's own increment
 *   skipped the second hyphen. The result was a statement that began
 *   with `- Campus Eats database schema...`, which MySQL rejected
 *   with SQLSTATE[42000] 1064. The block now appends both hyphens and
 *   advances the index by two with a `continue 2` so the loop's own
 *   increment is skipped.
 *
 * - Fix 2 (no die()). The constructor no longer calls die() when the
 *   database is unreachable. It records the failure in $lastError and
 *   leaves $available set to false. Query methods throw a catchable
 *   exception. This lets the caller decide how to respond. A
 *   registration form can queue the payload; a page can render a
 *   friendly message.
 *
 * - Fix 3 (connect order). The constructor calls
 *   ensureDatabaseExists() before connect(). The previous order
 *   produced error 1049 (unknown database) on a fresh server.
 *
 * - Fix 4 (single schema probe). The constructor probes the users
 *   table once and runs install.sql only when the table is absent.
 *   The previous version probed four times.
 *
 * - Fix 5 (skip the USE statement). The splitter discards a statement
 *   that consists only of a USE directive. The connection already
 *   selects the database, so the directive is a no-op in the
 *   installer path and is not needed.
 *
 * - Fix 6 (firebase_sync_state table). The installer creates the
 *   firebase_sync_state table. The table is used by the client-side
 *   synchronization worker to detect changes between polls.
 *
 * - Retained all Version 29.0 behaviour: the six-state splitter, the
 *   connection options, the health check, and the auxiliary table
 *   helpers.
 *
 * SOURCE: Audit continuation, Part 1.
 * SOURCE: Technical Audit Report, Section 1.
 *
 * @version 30.0
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
// Database Unavailable Exception
// =============================================================================
//
// The exception is thrown by query methods when the connection has
// not been established or has been lost. The caller can catch the
// exception and respond without terminating the request. The previous
// version terminated the request through die().
// =============================================================================

if (!class_exists('DatabaseUnavailableException'))
{
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
     * No user, administrator, demo, or sample account is created
     * here. The installer creates the tables. Accounts are created by
     * the registration page.
     */
    private function __construct()
    {
        if ($GLOBALS['_DATABASE_SCHEMA_VERIFIED'] === true)
        {
            $this->available = true;
            return;
        }

        try
        {
            // Create the database before opening the main connection.
            // A connection whose DSN names a database that does not
            // exist fails with error 1049. Creating the database first
            // avoids that failure on a fresh server.
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
                "Database connection and schema verified successfully.",
                "DATABASE"
            );
        }
        catch (PDOException $exception)
        {
            $this->lastError = $exception->getMessage();
            $this->available = false;

            writeLog(
                'Database Connection Error: ' . $exception->getMessage(),
                "DATABASE_ERROR"
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
     * A caller can use this to render a degraded view when the
     * database is unreachable.
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
     * Returns the PDO connection handle.
     *
     * When the connection is not available, the method throws a
     * DatabaseUnavailableException. The caller can catch the exception
     * and respond.
     *
     * @return PDO The PDO connection handle
     * @throws DatabaseUnavailableException When the connection is not usable
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
                $stmt = $this->connection->query("SELECT 1");

                if ($stmt !== false)
                {
                    $stmt->closeCursor();
                }

                $this->lastHealthCheck = $currentTime;
            }
            catch (PDOException $e)
            {
                writeLog(
                    "Database connection lost, reconnecting...",
                    "DATABASE"
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
                    throw new DatabaseUnavailableException(
                        $this->lastError
                    );
                }
            }
        }

        return $this->connection;
    }

    /**
     * Opens the PDO connection.
     *
     * @return void
     * @throws PDOException When the connection cannot be opened
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

        writeLog(
            "PDO connection opened to database '" . DB_NAME . "'.",
            "DATABASE"
        );
    }

    /**
     * Creates the target database if it does not exist.
     *
     * @return void
     * @throws PDOException When the database cannot be created
     */
    private function ensureDatabaseExists()
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

        writeLog(
            "Database '" . DB_NAME . "' ensured to exist.",
            "DATABASE"
        );
    }

    /**
     * Splits a SQL script into individual statements.
     *
     * The method walks the input one character at a time and maintains
     * a state machine with six states. A semicolon is treated as a
     * statement boundary only when the parser is in the NORMAL state.
     *
     * The fix in this version is in the branch that handles the `--`
     * sequence. The previous version appended one hyphen and advanced
     * the index by one inside the block. The loop's own increment then
     * skipped the second hyphen. The result was a statement that began
     * with a single hyphen, which MySQL rejected. The branch now
     * appends both hyphens and uses continue 2 to skip the loop's own
     * increment.
     *
     * @param string $sql The full SQL script
     * @return array An array of trimmed, non-empty, non-comment statements
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
                    // The sequence -- begins a line comment. Both
                    // characters are appended and the parser advances
                    // past both. The continue 2 skips the loop's own
                    // increment, which is the fix for the splitter
                    // defect recorded in the audit log.
                    if ($char === '-' && $next === '-')
                    {
                        $state = 'LINE_COMMENT';
                        $current .= '--';
                        $i += 2;
                        continue 2;
                    }

                    // The sequence /* begins a block comment.
                    if ($char === '/' && $next === '*')
                    {
                        $state = 'BLOCK_COMMENT';
                        $current .= '/*';
                        $i += 2;
                        continue 2;
                    }

                    // A single quote begins a string literal.
                    if ($char === "'")
                    {
                        $state = 'SINGLE_QUOTE';
                        $current .= $char;
                        break;
                    }

                    // A double quote begins a string literal.
                    if ($char === '"')
                    {
                        $state = 'DOUBLE_QUOTE';
                        $current .= $char;
                        break;
                    }

                    // A backtick begins a quoted identifier.
                    if ($char === '`')
                    {
                        $state = 'BACKTICK';
                        $current .= $char;
                        break;
                    }

                    // A semicolon is a statement boundary.
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

        // The installer discards a statement that consists only of a
        // USE directive. The connection already selects the database,
        // so the directive is a no-op in this path. The filter runs
        // after the splitter so the file can still be imported
        // manually in phpMyAdmin, where the USE directive is required.
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
     * @param string $piece The candidate piece
     * @return bool True when the piece is a comment only
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

        foreach ($lines as $line)
        {
            $line = trim($line);

            if ($line === '')
            {
                continue;
            }

            if ($line[0] !== '-')
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Installs the schema when the users table is not present.
     *
     * @return void
     * @throws RuntimeException When the schema cannot be installed
     */
    private function ensureSchemaInstalled()
    {
        if ($this->tableExists('users'))
        {
            writeLog(
                "Schema probe: users table already exists.",
                "DATABASE"
            );
            return;
        }

        writeLog(
            "Schema probe: users table not found. Running install.sql.",
            "DATABASE"
        );

        $installSqlPath = dirname(__DIR__)
                        . DIRECTORY_SEPARATOR . 'sql'
                        . DIRECTORY_SEPARATOR . 'install.sql';

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

        if (substr($sqlContent, 0, 3) === "\xEF\xBB\xBF")
        {
            $sqlContent = substr($sqlContent, 3);
            writeLog("Stripped UTF-8 BOM from install.sql.", "DATABASE");
        }

        $statements = $this->splitSqlStatements($sqlContent);

        writeLog(
            "Parsed " . count($statements) . " SQL statement(s) from install.sql.",
            "DATABASE"
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
            $message = "Installation completed but the users table is "
                     . "still missing from database '" . DB_NAME . "'. "
                     . "Check that install.sql contains a CREATE TABLE "
                     . "users statement and that DB_NAME points at the "
                     . "database the script targets.";

            writeLog($message, "DATABASE_ERROR");
            throw new RuntimeException($message);
        }

        writeLog(
            "Schema installation verified. users table is present.",
            "DATABASE"
        );
    }

    /**
     * Returns true when a table exists in the target database.
     *
     * @param string $tableName The table name
     * @return bool True when the table exists
     */
    private function tableExists($tableName)
    {
        try
        {
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

            return isset($row['table_count'])
                && (int)$row['table_count'] > 0;
        }
        catch (PDOException $exception)
        {
            writeLog(
                "tableExists check failed for '$tableName': "
                    . $exception->getMessage(),
                "DATABASE_ERROR"
            );
            return false;
        }
    }

    /**
     * Returns the number of rows in the users table.
     *
     * @return int The number of users in the database
     */
    public function userCount()
    {
        try
        {
            $row = $this->fetchOne(
                "SELECT COUNT(*) AS user_count FROM `users`"
            );

            return isset($row['user_count'])
                ? (int)$row['user_count']
                : 0;
        }
        catch (PDOException $exception)
        {
            writeLog(
                'userCount failed: ' . $exception->getMessage(),
                "DATABASE_ERROR"
            );
            return 0;
        }
        catch (DatabaseUnavailableException $exception)
        {
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
                    `session_id`    VARCHAR(128) NOT NULL PRIMARY KEY,
                    `user_id`       INT NOT NULL,
                    `ip_address`    VARCHAR(45) NOT NULL,
                    `user_agent`    TEXT NULL,
                    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                    ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`user_id`)
                        REFERENCES `users`(`user_id`)
                        ON DELETE CASCADE,
                    INDEX `idx_user_id` (`user_id`),
                    INDEX `idx_last_activity` (`last_activity`)
                ) ENGINE=InnoDB
                  DEFAULT CHARSET=utf8mb4
                  COLLATE=utf8mb4_unicode_ci
                  COMMENT='Stores active user sessions'"
            );

            writeLog(
                "user_sessions table created successfully.",
                "DATABASE"
            );
        }
        catch (PDOException $exception)
        {
            writeLog(
                'Failed to create user_sessions table: '
                    . $exception->getMessage(),
                "DATABASE_ERROR"
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
                    INDEX `idx_ip_time` (`ip_address`, `attempted_at`)
                ) ENGINE=InnoDB
                  DEFAULT CHARSET=utf8mb4
                  COLLATE=utf8mb4_unicode_ci"
            );

            writeLog('Created login_attempts table.', "DATABASE");
        }
        catch (PDOException $exception)
        {
            writeLog(
                'Failed to create login_attempts table: '
                    . $exception->getMessage(),
                "DATABASE_ERROR"
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

            writeLog(
                'Created password_reset_attempts table.',
                "DATABASE"
            );
        }
        catch (PDOException $exception)
        {
            writeLog(
                'Failed to create password_reset_attempts table: '
                    . $exception->getMessage(),
                "DATABASE_ERROR"
            );
        }
    }

    /**
     * Ensures the firebase_sync_state table exists.
     *
     * The table is used by the client-side synchronization worker to
     * detect changes between polls. Each row records the hash of the
     * last projection written for a user and the timestamp of the
     * write. The worker reads the table, compares the hashes, and
     * returns only the records whose hashes have changed.
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
                    INDEX `idx_synced_at` (`synced_at`),
                    CONSTRAINT `fk_sync_state_user`
                        FOREIGN KEY (`user_id`)
                        REFERENCES `users`(`user_id`)
                        ON DELETE CASCADE
                ) ENGINE=InnoDB
                  DEFAULT CHARSET=utf8mb4
                  COLLATE=utf8mb4_unicode_ci
                  COMMENT='Tracks the last projection written to Firebase'"
            );

            writeLog(
                'Created firebase_sync_state table.',
                "DATABASE"
            );
        }
        catch (PDOException $exception)
        {
            writeLog(
                'Failed to create firebase_sync_state table: '
                    . $exception->getMessage(),
                "DATABASE_ERROR"
            );
        }
    }

    /**
     * Executes a prepared statement.
     *
     * @param string $sql    The SQL statement
     * @param array  $params The named parameters
     * @return PDOStatement The executed statement
     * @throws DatabaseUnavailableException When the connection is not usable
     * @throws PDOException When the statement cannot be prepared or executed
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
            throw new PDOException(
                "Failed to prepare statement: " . $sql
            );
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

    /**
     * Executes a prepared statement and returns the first row.
     *
     * @param string $sql    The SQL statement
     * @param array  $params The named parameters
     * @return array|false The first row, or false when there is no row
     */
    public function fetchOne($sql, $params = array())
    {
        $this->executeQuery($sql, $params);
        $result = $this->statement->fetch();

        $this->statement->closeCursor();
        $this->statement = null;

        return $result;
    }

    /**
     * Executes a prepared statement and returns all rows.
     *
     * @param string $sql    The SQL statement
     * @param array  $params The named parameters
     * @return array The rows
     */
    public function fetchAll($sql, $params = array())
    {
        $this->executeQuery($sql, $params);
        $result = $this->statement->fetchAll();

        $this->statement->closeCursor();
        $this->statement = null;

        return $result;
    }

    /**
     * Executes an INSERT statement and returns the new row ID.
     *
     * @param string $sql    The SQL statement
     * @param array  $params The named parameters
     * @return int The value returned by lastInsertId()
     */
    public function insert($sql, $params = array())
    {
        $this->executeQuery($sql, $params);
        $insertId = (int)$this->getConnection()->lastInsertId();

        $this->statement->closeCursor();
        $this->statement = null;

        return $insertId;
    }

    /**
     * Returns the row count of the most recent statement.
     *
     * @return int The row count, or zero when no statement is open
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
     * @throws DatabaseUnavailableException When the connection is not usable
     */
    public function beginTransaction()
    {
        if ($this->inTransaction)
        {
            writeLog(
                "Transaction already in progress",
                "DATABASE"
            );
            return false;
        }

        $connection = $this->getConnection();
        $result = $connection->beginTransaction();

        if ($result)
        {
            $this->inTransaction = true;
            writeLog("Transaction started", "DATABASE");
        }

        return $result;
    }

    /**
     * Commits the current transaction.
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

        $result = $this->getConnection()->commit();

        if ($result)
        {
            $this->inTransaction = false;
            writeLog("Transaction committed", "DATABASE");
        }

        return $result;
    }

    /**
     * Rolls back the current transaction.
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

        $result = $this->getConnection()->rollback();

        if ($result)
        {
            $this->inTransaction = false;
            writeLog("Transaction rolled back", "DATABASE");
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
     * @throws Exception Always
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize a singleton.");
    }
}

if (!function_exists('getDB'))
{
    /**
     * Returns the shared DatabaseConnection instance.
     *
     * @return DatabaseConnection The shared instance
     */
    function getDB()
    {
        return DatabaseConnection::getInstance();
    }
}