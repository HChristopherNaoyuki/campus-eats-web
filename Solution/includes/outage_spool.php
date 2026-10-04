<?php
/**
 * Outage Spool
 *
 * Queues registration payloads when the database is unreachable. The
 * spool writes one JSON file per queued payload under
 * Solution/data/spool/. The file permissions are restricted to the
 * owner. The password is hashed before it is written. A replay reads
 * the files in order, submits each payload to the account service,
 * and deletes the file when the account is created. The replay is
 * idempotent: a duplicate email is reported as an already-existing
 * account, and the file is deleted because the account now exists.
 *
 * SECURITY NOTES
 *
 * The spool is a defence against a transient database outage. It is
 * not a general-purpose queue. The directory must be writable by the
 * web server user and must not be reachable from the browser. The
 * .htaccess file in the repository root denies access to the
 * Solution/data directory.
 *
 * The spool does not store the plain-text password. It stores a
 * bcrypt hash. The hash is the value that would have been written to
 * the users table. The password cannot be recovered from the hash.
 * When the database returns, the hash is written directly without
 * rehashing.
 *
 * CORRECTIONS (Version 1.0 - Audit Continuation):
 * - Initial implementation.
 * - The payload is stored as JSON. The password field holds the
 *   bcrypt hash rather than the plain-text password.
 * - A file lock prevents two replay processes from reading the same
 *   file.
 * - The spool directory is created with mode 0700 on the first use.
 *
 * SOURCE: Audit continuation, Part 1.
 *
 * @version 1.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/error_logging.php';
require_once BASE_PATH . '/includes/password_validation.php';

class OutageSpool
{
    /**
     * @var string The spool directory path
     */
    private $spoolDir;

    /**
     * Constructor.
     *
     * @param string|null $spoolDir Optional override for the spool directory
     */
    public function __construct($spoolDir = null)
    {
        if ($spoolDir === null)
        {
            $spoolDir = BASE_PATH . '/data/spool';
        }

        $this->spoolDir = rtrim($spoolDir, '/\\');

        if (!is_dir($this->spoolDir))
        {
            mkdir($this->spoolDir, 0700, true);
        }
    }

    /**
     * Queues a registration payload.
     *
     * The password is hashed before it is written. The payload is
     * stored as a JSON file. The file name includes a timestamp and a
     * random suffix so two payloads written in the same second do not
     * collide.
     *
     * @param string $type    The payload type, for example "register"
     * @param array  $payload The payload
     * @return string The path of the queued file
     * @throws RuntimeException When the payload cannot be queued
     */
    public function enqueue($type, $payload)
    {
        if (empty($type))
        {
            throw new InvalidArgumentException('Payload type is required.');
        }

        if (!is_array($payload))
        {
            throw new InvalidArgumentException('Payload must be an array.');
        }

        // Hash the password before it is written. When the payload
        // has no password field, the hash is skipped.
        if (isset($payload['password']) && $payload['password'] !== '')
        {
            $payload['password_hash'] = hashPassword($payload['password']);
        }

        unset($payload['password']);

        $entry = array(
            'type'        => (string)$type,
            'queued_at'   => date('c'),
            'payload'     => $payload
        );

        $fileName = sprintf(
            '%s-%s-%s.json',
            date('YmdHis'),
            (string)$type,
            bin2hex(random_bytes(4))
        );

        $filePath = $this->spoolDir . DIRECTORY_SEPARATOR . $fileName;

        $encoded = json_encode(
            $entry,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($encoded === false)
        {
            throw new RuntimeException(
                'Failed to encode the spool payload as JSON.'
            );
        }

        $written = @file_put_contents($filePath, $encoded, LOCK_EX);

        if ($written === false)
        {
            throw new RuntimeException(
                'Failed to write the spool file: ' . $filePath
            );
        }

        @chmod($filePath, 0600);

        writeLog(
            "Spooled payload of type '$type' to $filePath",
            "OUTAGE"
        );

        return $filePath;
    }

    /**
     * Returns the list of queued files in chronological order.
     *
     * @return array The absolute file paths
     */
    public function listQueued()
    {
        if (!is_dir($this->spoolDir))
        {
            return array();
        }

        $files = glob($this->spoolDir . DIRECTORY_SEPARATOR . '*.json');

        if ($files === false)
        {
            return array();
        }

        sort($files, SORT_STRING);

        return $files;
    }

    /**
     * Replays every queued file.
     *
     * Each file is read under a lock. The payload is submitted to the
     * account service. When the account is created or when the email
     * already exists, the file is deleted. When the database is still
     * unreachable, the replay stops and the file is left in place.
     *
     * @return array A summary with the number of replayed and remaining files
     */
    public function replay()
    {
        $replayed = 0;
        $remaining = 0;

        if (!class_exists('AccountService'))
        {
            require_once BASE_PATH . '/includes/account_service.php';
        }

        foreach ($this->listQueued() as $filePath)
        {
            $handle = @fopen($filePath, 'r+');

            if ($handle === false)
            {
                $remaining++;
                continue;
            }

            if (!flock($handle, LOCK_EX | LOCK_NB))
            {
                fclose($handle);
                $remaining++;
                continue;
            }

            $content = stream_get_contents($handle);
            $entry = json_decode($content, true);

            if (!is_array($entry) || !isset($entry['payload']))
            {
                fclose($handle);
                @unlink($filePath);
                continue;
            }

            try
            {
                $service = new AccountService();
                $service->createAccount($entry['payload']);

                $replayed++;

                fclose($handle);
                @unlink($filePath);

                writeLog(
                    "Replayed spooled payload: $filePath",
                    "OUTAGE"
                );
            }
            catch (InvalidArgumentException $exception)
            {
                // A validation error means the payload cannot succeed
                // even when the database is up. The file is removed to
                // prevent an infinite loop.
                fclose($handle);
                @unlink($filePath);

                writeLog(
                    "Discarded spooled payload after validation error: "
                        . $exception->getMessage(),
                    "OUTAGE"
                );
            }
            catch (RuntimeException $exception)
            {
                // The database is still unreachable. The file is left
                // in place.
                fclose($handle);
                $remaining++;

                writeLog(
                    "Replay postponed; database still unavailable: "
                        . $exception->getMessage(),
                    "OUTAGE"
                );
                break;
            }
        }

        return array(
            'replayed'  => $replayed,
            'remaining' => $remaining
        );
    }
}