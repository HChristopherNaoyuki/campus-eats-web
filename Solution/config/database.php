<?php
/**
 * Database Connection Configuration File
 *
 * Handles database connections with a singleton pattern and automatic
 * schema installation. The application is MySQL-authoritative for user
 * accounts, authentication, registration, orders, carts, vendors, and
 * payments. Firebase is used only for reading feedback and for the
 * lightweight per-user projection written by the client-side
 * synchronization worker.
 *
 * CORRECTIONS (Version 29.0 - Technical Audit and Fixes Report):
 * - Retained the six-state character-by-character SQL splitter from
 *   Version 28.0. The Technical Audit and Fixes Report proposed a
 *   regex-based splitter. That proposal is rejected here because it is
 *   less robust than the existing parser. The regex approach fails on
 *   three cases that the six-state parser handles correctly:
 *
 *     1. A semicolon inside a single-quoted string literal. The regex
 *        splitter treats the semicolon as a statement boundary and
 *        produces two malformed fragments.
 *     2. A semicolon inside a backtick-quoted identifier. The same
 *        failure occurs.
 *     3. A single quote followed by an escaped single quote inside a
 *        string literal. The regex splitter cannot distinguish the
 *        escaped quote from the closing quote.
 *
 *   The six-state parser walks the input one character at a time and
 *   tracks the parser state at every position. A semicolon is treated
 *   as a statement boundary only when the parser is in the NORMAL
 *   state. The parser states are:
 *
 *     NORMAL        - ordinary SQL text.
 *     LINE_COMMENT  - inside a line comment that begins with two
 *                     consecutive hyphens.
 *     BLOCK_COMMENT - inside a block comment that begins with slash-star
 *                     and ends with star-slash.
 *     SINGLE_QUOTE  - inside a single-quoted string literal.
 *     DOUBLE_QUOTE  - inside a double-quoted string literal.
 *     BACKTICK      - inside a backtick-quoted identifier.
 *
 *   The parser does not strip comment lines from a piece that contains
 *   real SQL. It drops a piece only when the piece as a whole is a
 *   comment. This avoids the failure mode where a comment banner line
 *   is partially stripped and the remaining fragment begins with
 *   '=====' and is rejected by MySQL with SQLSTATE[42000] 1064.
 *
 * - Retained all Version 28.0 corrections:
 *     - Separate $GLOBALS['_DATABASE_CONNECTION_ESTABLISHED'] and
 *       $GLOBALS['_DATABASE_SCHEMA_VERIFIED'] flags.
 *     - No ensureAdminAccountExists() call and no ensureDemoAccountsExist()
 *       call. No account is created by the installer.
 *     - userCount() helper used by the registration page to decide
 *       whether the first user may register as Admin.
 *     - PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true.
 *     - PDO::ATTR_PERSISTENT => false.
 *     - closeCursor() on every fetch and execute method.
 *     - Post-installation verification that the users table exists.
 *     - Separate helpers for user_sessions, login_attempts, and
 *       password_reset_attempts, each guarded by tableExists().
 *
 * SOURCE: Technical Audit and Fixes Report.
 * SOURCE: Campus Eats Technical Audit Report, Section 4.
 * SOURCE: SQL SYNTAX ERROR INVESTIGATION REPORT.
 * SOURCE: DATABASE INSTALLATION FAILURE REPORT.
 *
 * @version 29.0
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
//
// The constants are defined here only when they are not already defined
// by constants.php. constants.php is the single source of truth for
// these values. The guards below keep the file usable when it is
// included by a script that has not loaded constants.php.
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
//
// The password helper and the user identifier helper are loaded here so
// that the installer and the singleton can call hashPassword() and
// generateAlphanumericUserId() without a separate require. Both helpers
// are guarded by function_exists() so a second include does not cause a
// redeclaration fatal.
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
// The two flags are separate because they represent two distinct facts.
// The connection flag records that a PDO handle has been opened at
// least once during the request. The schema flag records that the
// installer has verified that the required tables are present. A
// request that opens a second connection does not need to repeat the
// schema verification.
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
     * @var bool True after the constructor has completed
     */
    private $initialized = false;

    /**
     * @var bool True after the schema has been verified
     */
    private $schemaVerified = false;

    /**
     * @var int Unix timestamp of the most recent health check
     */
    private $lastHealthCheck = 0;

    /**
     * Private constructor. Performs the one-time setup on first use.
     *
     * The constructor does not create any user, administrator, demo, or
     * sample account. The installer creates the tables. Accounts are
     * created by the registration page. This keeps the installer
     * idempotent and avoids the case where a fresh database
     * automatically contains credentials that an operator did not
     * intend to create.
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

            writeLog(
                "Database connection and schema verified successfully.",
                "DATABASE"
            );
        }
        catch (PDOException $exception)
        {
            writeLog(
                'Database Connection Error: ' . $exception->getMessage(),
                "DATABASE_ERROR"
            );

            if (defined('APP_DEBUG') && APP_DEBUG === true)
            {
                die(
                    'Database error: '
                        . htmlspecialchars($exception->getMessage())
                );
            }

            die(
                'Database service is temporarily unavailable. '
                    . 'Please try again later.'
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
     * Returns the PDO connection handle.
     *
     * When the handle is older than 60 seconds, a lightweight probe is
     * issued. If the probe fails, the handle is discarded and a new
     * connection is opened. The probe is a single SELECT 1, which is
     * cheap on every supported MySQL version.
     *
     * @return PDO The PDO connection handle
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
                    writeLog(
                        "Database connection lost, reconnecting...",
                        "DATABASE"
                    );
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
     * Opens the PDO connection.
     *
     * The options are chosen for correctness under the documented
     * workload:
     *
     *   - ATTR_ERRMODE EXCEPTION so a failed statement raises rather
     *     than returning false. The callers rely on exceptions.
     *   - ATTR_DEFAULT_FETCH_MODE ASSOC so every fetch returns an
     *     associative array.
     *   - ATTR_EMULATE_PREPARES false so the driver uses server-side
     *     prepared statements. This is required for the LIMIT and
     *     OFFSET bindings to be treated as integers.
     *   - ATTR_PERSISTENT false so a request does not inherit a
     *     connection whose transaction state is unknown.
     *   - ATTR_TIMEOUT 5 so an unreachable host fails within five
     *     seconds rather than hanging the request.
     *   - MYSQL_ATTR_USE_BUFFERED_QUERY true so a fetch does not fail
     *     with "Cannot execute queries while other unbuffered queries
     *     are active" when a caller issues a second statement before
     *     exhausting the first.
     *   - MYSQL_ATTR_INIT_COMMAND so the connection character set
     *     matches the database character set.
     *
     * @return void
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
     * The connection string omits the database name because the database
     * may not exist yet. A temporary connection is opened against the
     * server. The CREATE DATABASE statement is issued, then the
     * temporary connection is released.
     *
     * The character set and collation are set to the same values the
     * application uses everywhere else so no conversion is required
     * when data moves between the connection and the tables.
     *
     * @return void
     * @throws PDOException When the database cannot be created
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

            writeLog(
                "Database '" . DB_NAME . "' ensured to exist.",
                "DATABASE"
            );
        }
        catch (PDOException $exception)
        {
            writeLog(
                'Database creation failed: ' . $exception->getMessage(),
                "DATABASE_ERROR"
            );
            throw $exception;
        }
    }

    /**
     * Splits a SQL script into individual statements.
     *
     * The method walks the input one character at a time and maintains
     * a state machine with six states. A semicolon is treated as a
     * statement boundary only when the parser is in the NORMAL state.
     * The full state description is in the file header comment.
     *
     * The method does not strip comment lines from a piece that
     * contains real SQL. It drops a piece only when the piece as a
     * whole is a comment. This preserves the correctness of a script
     * that mixes comment banners with executable statements.
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
                    // characters are appended to the current piece so
                    // the comment text is preserved if the piece is
                    // ultimately kept.
                    if ($char === '-' && $next === '-')
                    {
                        $state = 'LINE_COMMENT';
                        $current .= $char;
                        $i++;
                        break;
                    }

                    // The sequence /* begins a block comment. The two
                    // characters are appended and the parser advances
                    // past both.
                    if ($char === '/' && $next === '*')
                    {
                        $state = 'BLOCK_COMMENT';
                        $current .= '/*';
                        $i += 2;
                        continue 2;
                    }

                    // A single quote begins a single-quoted string
                    // literal. Semicolons inside the literal are not
                    // boundaries.
                    if ($char === "'")
                    {
                        $state = 'SINGLE_QUOTE';
                        $current .= $char;
                        break;
                    }

                    // A double quote begins a double-quoted string
                    // literal. The same protection applies.
                    if ($char === '"')
                    {
                        $state = 'DOUBLE_QUOTE';
                        $current .= $char;
                        break;
                    }

                    // A backtick begins a backtick-quoted identifier.
                    // The same protection applies.
                    if ($char === '`')
                    {
                        $state = 'BACKTICK';
                        $current .= $char;
                        break;
                    }

                    // A semicolon is a statement boundary only in the
                    // NORMAL state. The piece before the semicolon is
                    // trimmed and added to the result when it is not
                    // empty.
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
                    // A newline ends the line comment. The newline
                    // itself is appended so the boundary is preserved.
                    if ($char === "\n")
                    {
                        $state = 'NORMAL';
                    }

                    $current .= $char;
                    break;

                case 'BLOCK_COMMENT':
                    // The sequence */ ends the block comment. Both
                    // characters are appended and the parser advances
                    // past both.
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
                    // A backslash escapes the next character. Both
                    // characters are appended and the parser advances
                    // past both. The escaped character cannot end the
                    // literal.
                    if ($char === '\\' && $next !== '')
                    {
                        $current .= $char . $next;
                        $i += 2;
                        continue 2;
                    }

                    // A doubled single quote is an escaped single quote
                    // inside the literal. Both quotes are appended and
                    // the parser advances past both.
                    if ($char === "'" && $next === "'")
                    {
                        $current .= "''";
                        $i += 2;
                        continue 2;
                    }

                    // A single quote that is not doubled ends the
                    // literal.
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

        if ($trimmed !== '')
        {
            if (!$this->isCommentOnly($trimmed))
            {
                $statements[] = $trimmed;
            }
        }

        return $statements;
    }

    /**
     * Returns true when a piece contains no executable SQL.
     *
     * The check classifies a piece as a comment when every non-empty
     * line begins with two hyphens, or when the piece begins with
     * slash-star and ends with star-slash and there is nothing after
     * the closing star-slash.
     *
     * The check does not attempt to parse the piece. It is a
     * classification for the purpose of dropping a trailing comment
     * from the statement list.
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

        // Whole-piece block comment.
        if (strpos($trimmed, '/*') === 0 && substr($trimmed, -2) === '*/')
        {
            $closing = strpos($trimmed, '*/');
            $after = trim(substr($trimmed, $closing + 2));

            if ($after === '')
            {
                return true;
            }
        }

        // Line-by-line check for two-hyphen comments.
        $lines = preg_split('/\r\n|\r|\n/', $trimmed);

        foreach ($lines as $line)
        {
            $line = trim($line);

            if ($line === '')
            {
                continue;
            }

            if (strpos($line, '--') !== 0)
            {
                return false;
            }
        }

        return true;
    }

    /**
     * Installs the schema when the users table is not present.
     *
     * The method reads install.sql from the sql directory, strips a
     * UTF-8 byte order mark if present, splits the script into
     * statements with the state machine, and executes each statement.
     *
     * When a statement fails, the failing statement is logged and the
     * exception is rethrown so the caller can halt. After the loop, the
     * method probes for the users table a second time. When the table
     * is still absent, an exception is thrown. This makes a silent
     * partial installation visible.
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
                        . DIRECTORY_SEPARATOR
                        . 'sql'
                        . DIRECTORY_SEPARATOR
                        . 'install.sql';

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

        // Strip a UTF-8 byte order mark if present. A BOM before the
        // first character would be seen by the state machine as part of
        // the first token and would cause the first statement to be
        // rejected by MySQL.
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
                // The failing statement is truncated to 200 characters
                // so the log line remains readable even when a long
                // CREATE TABLE statement is the one that failed.
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
     * The probe queries information_schema. A database whose account
     * cannot read information_schema would fail the probe. The account
     * the application uses must have that permission. The installer
     * therefore requires a MySQL account with the standard read
     * privileges.
     *
     * @param string $tableName The table name
     * @return bool True when the table exists
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
     * The registration page calls this method to decide whether the
     * current visitor is the first user. When the count is zero, the
     * page offers the Admin role. Once any user exists, the Admin role
     * is not offered, and a submission that claims the role is
     * rejected server-side.
     *
     * The method is public because the registration page calls it
     * through the singleton returned by getDB().
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
    }

    /**
     * Ensures the user_sessions table exists.
     *
     * The table is a convenience record of active sessions. The
     * application does not depend on it for authentication. It is
     * created here so a deployment that uses session tracking has the
     * table available without a separate migration.
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

            writeLog(
                "user_sessions table does not exist. Creating...",
                "DATABASE"
            );

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
                  COMMENT='Stores active user sessions for session management and tracking'"
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
     * The table records failed login attempts for the rate limiter in
     * Solution/includes/auth.php. The table is created here so the
     * rate limiter has a destination without a separate migration.
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
     * The table records password reset attempts for the rate limiter in
     * Solution/modules/auth/forgot_password.php. The table is created
     * here so the rate limiter has a destination without a separate
     * migration.
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
                  COMMENT='Stores password reset attempts for rate limiting'"
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
     * Executes a prepared statement.
     *
     * When a previous statement handle is still open, it is closed
     * before the new one is prepared. This avoids the
     * "Cannot execute queries while other unbuffered queries are
     * active" error that can occur when a caller issues a new
     * statement before the previous result set is exhausted.
     *
     * Parameters are bound by name. The type is inferred from the PHP
     * value: integer, boolean, null, or string. Every other type is
     * bound as a string.
     *
     * @param string $sql    The SQL statement
     * @param array  $params The named parameters
     * @return PDOStatement The executed statement
     * @throws PDOException When the statement cannot be prepared or executed
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
                    // The cursor may already be closed. The exception
                    // is not actionable at this point.
                }
                $this->statement = null;
            }

            $this->statement = $this->connection->prepare($sql);

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
        catch (PDOException $exception)
        {
            writeLog(
                'Query failed: ' . $exception->getMessage()
                    . ' | SQL: ' . $sql,
                "DATABASE_ERROR"
            );
            throw $exception;
        }
    }

    /**
     * Executes a prepared statement and returns the first row.
     *
     * The statement handle is closed before the method returns so the
     * caller cannot accidentally leave an open cursor.
     *
     * @param string $sql    The SQL statement
     * @param array  $params The named parameters
     * @return array|false The first row, or false when there is no row
     * @throws PDOException When the statement cannot be prepared or executed
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
            writeLog(
                'fetchOne failed: ' . $exception->getMessage(),
                "DATABASE_ERROR"
            );
            throw $exception;
        }
    }

    /**
     * Executes a prepared statement and returns all rows.
     *
     * The statement handle is closed before the method returns.
     *
     * @param string $sql    The SQL statement
     * @param array  $params The named parameters
     * @return array The rows
     * @throws PDOException When the statement cannot be prepared or executed
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
            writeLog(
                'fetchAll failed: ' . $exception->getMessage(),
                "DATABASE_ERROR"
            );
            throw $exception;
        }
    }

    /**
     * Executes an INSERT statement and returns the new row ID.
     *
     * The statement handle is closed before the method returns.
     *
     * @param string $sql    The SQL statement
     * @param array  $params The named parameters
     * @return int The value returned by lastInsertId()
     * @throws PDOException When the statement cannot be prepared or executed
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
            writeLog(
                'insert failed: ' . $exception->getMessage(),
                "DATABASE_ERROR"
            );
            throw $exception;
        }
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
     * The method refuses to begin a second transaction on the same
     * connection. The caller must commit or roll back the current
     * transaction before beginning a new one.
     *
     * @return bool True on success
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

        $result = $this->connection->commit();

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

        $result = $this->connection->rollback();

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
     * Every caller uses this function. The function delegates to the
     * singleton so a request opens at most one connection.
     *
     * @return DatabaseConnection The shared instance
     */
    function getDB()
    {
        return DatabaseConnection::getInstance();
    }
}