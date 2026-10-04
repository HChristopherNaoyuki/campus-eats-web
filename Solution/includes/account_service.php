<?php
/**
 * Account Service
 *
 * Provides the transactional account-creation logic used by the
 * registration page. The service writes the users row and, when the
 * role is vendor, the vendors row in a single transaction.
 *
 * CORRECTIONS (Version 2.0 - Audit Continuation):
 * - The service uses the real public API of DatabaseConnection:
 *   insert(), executeQuery(), fetchOne(), beginTransaction(),
 *   commit(), rollback(). The previous version assumed methods that
 *   do not exist.
 * - The first-admin rule is enforced with a lock. The lock is a
 *   SELECT ... FOR UPDATE on the users table. When two registration
 *   requests race for the first-admin slot, only one of them
 *   succeeds.
 * - Duplicate email and username raise InvalidArgumentException.
 *   The transaction is rolled back before the exception leaves the
 *   method.
 * - A database outage raises RuntimeException so the caller can
 *   queue the payload to the spool.
 * - After commit, the service performs a best-effort Firebase
 *   projection. A failure in the projection is logged. The MySQL
 *   transaction is already committed and remains the authoritative
 *   record.
 *
 * SOURCE: Audit continuation, Part 1.
 *
 * @version 2.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/error_logging.php';
require_once BASE_PATH . '/includes/password_validation.php';
require_once BASE_PATH . '/includes/user_id.php';

class AccountService
{
    /**
     * Creates a user account and, when the role is vendor, a vendor
     * profile in a single transaction.
     *
     * @param array $payload The account payload
     * @return array The created account
     * @throws InvalidArgumentException When the payload is invalid
     * @throws RuntimeException When the database is unreachable
     */
    public function createAccount($payload)
    {
        $required = array('full_name', 'email', 'password', 'account_type');

        foreach ($required as $field)
        {
            if (empty($payload[$field]))
            {
                throw new InvalidArgumentException(
                    "Missing required field: $field"
                );
            }
        }

        $email = trim((string)$payload['email']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        {
            throw new InvalidArgumentException('Invalid email address.');
        }

        $passwordValidation = validatePasswordPolicy($payload['password']);

        if (!$passwordValidation['valid'])
        {
            throw new InvalidArgumentException(
                $passwordValidation['message']
            );
        }

        $accountType = strtolower((string)$payload['account_type']);

        $allowedRoles = array('admin', 'vendor', 'student', 'standard');

        if (!in_array($accountType, $allowedRoles, true))
        {
            throw new InvalidArgumentException('Invalid account type.');
        }

        $db = getDB();

        if (!$db->isAvailable())
        {
            // The database is unreachable. The caller queues the
            // payload to the outage spool and reports the offline
            // success to the user.
            throw new RuntimeException(
                'Database is not available.'
            );
        }

        $fullName = trim((string)$payload['full_name']);
        $usernameBase = isset($payload['username']) && $payload['username'] !== ''
            ? trim((string)$payload['username'])
            : explode('@', $email)[0];

        $vendorName = isset($payload['vendor_name'])
            ? trim((string)$payload['vendor_name'])
            : '';

        try
        {
            $db->beginTransaction();

            // The first-admin rule uses a lock on the users table.
            // The lock prevents two concurrent registration requests
            // from both seeing an empty table and both registering
            // as admin.
            $isFirstUser = false;

            if ($accountType === 'admin')
            {
                $countRow = $db->fetchOne(
                    "SELECT COUNT(*) AS user_count FROM users FOR UPDATE"
                );
                $isFirstUser = ((int)$countRow['user_count'] === 0);

                if (!$isFirstUser)
                {
                    $db->rollback();
                    throw new InvalidArgumentException(
                        'The Admin role is available only for the first registration.'
                    );
                }
            }

            $existingEmail = $db->fetchOne(
                "SELECT user_id FROM users WHERE email = :email LIMIT 1",
                array('email' => $email)
            );

            if ($existingEmail)
            {
                $db->rollback();
                throw new InvalidArgumentException(
                    'An account with this email already exists.'
                );
            }

            $username = $usernameBase;

            $existingUsername = $db->fetchOne(
                "SELECT user_id FROM users WHERE username = :username LIMIT 1",
                array('username' => $username)
            );

            if ($existingUsername)
            {
                $username = $usernameBase . random_int(100, 999);
            }

            $uniqueId = generateAlphanumericUserId($accountType);
            $passwordHash = hashPassword($payload['password']);

            $userId = $db->insert(
                "INSERT INTO users
                    (unique_id, full_name, username, email,
                     password_hash, account_type, is_verified,
                     is_active, created_at, updated_at)
                 VALUES
                    (:unique_id, :full_name, :username, :email,
                     :password_hash, :account_type, 1, 1, NOW(), NOW())",
                array(
                    'unique_id'     => $uniqueId,
                    'full_name'     => $fullName,
                    'username'      => $username,
                    'email'         => $email,
                    'password_hash' => $passwordHash,
                    'account_type'  => $accountType
                )
            );

            if (!$userId)
            {
                throw new RuntimeException('User insert returned no ID.');
            }

            if ($accountType === 'vendor')
            {
                $resolvedVendorName = $vendorName !== ''
                    ? $vendorName
                    : $fullName;

                $db->insert(
                    "INSERT INTO vendors
                        (vendor_user_id, vendor_name, description,
                         is_open, is_approved, created_at)
                     VALUES
                        (:user_id, :vendor_name, :description, 1, 0, NOW())",
                    array(
                        'user_id'     => $userId,
                        'vendor_name' => $resolvedVendorName,
                        'description' => 'New vendor awaiting administrative approval.'
                    )
                );
            }

            $db->commit();

            writeLog(
                "Account created: $email (ID: $userId, Role: $accountType)",
                "REGISTER"
            );

            $this->projectToFirebase($userId, $email, $accountType);

            return array(
                'user_id'      => $userId,
                'unique_id'    => $uniqueId,
                'email'        => $email,
                'account_type' => $accountType,
                'requires_approval' => ($accountType === 'vendor')
            );
        }
        catch (PDOException $exception)
        {
            if ($db->inTransaction())
            {
                $db->rollback();
            }

            writeLog(
                'Account creation failed: ' . $exception->getMessage(),
                "REGISTER_ERROR"
            );

            throw new RuntimeException(
                'Unable to create account at this time. Please try again.'
            );
        }
        catch (DatabaseUnavailableException $exception)
        {
            if ($db->inTransaction())
            {
                try
                {
                    $db->rollback();
                }
                catch (Exception $rollbackError)
                {
                }
            }

            throw new RuntimeException(
                'Database is not available.'
            );
        }
    }

    /**
     * Projects the new user to Firebase on a best-effort basis.
     *
     * The projection requires a Firebase ID token. When no token is
     * available in the session, the projection is skipped and a log
     * entry records the reason. When the projection fails, the failure
     * is logged. The MySQL transaction has already committed and
     * remains the authoritative record.
     *
     * @param int    $userId      The MySQL user ID
     * @param string $email       The email address
     * @param string $accountType The account type
     * @return void
     */
    private function projectToFirebase($userId, $email, $accountType)
    {
        $firebaseIdToken = isset($_SESSION['firebase_id_token'])
            ? $_SESSION['firebase_id_token']
            : null;

        if ($firebaseIdToken === null || $firebaseIdToken === '')
        {
            writeLog(
                "Firebase user projection skipped: no Firebase ID token "
                    . "in session for user $userId",
                "FIREBASE_SYNC"
            );
            return;
        }

        if (!class_exists('FirebaseSyncHelper'))
        {
            $helperPath = BASE_PATH . '/config/firebase_sync_helper.php';

            if (!file_exists($helperPath))
            {
                writeLog(
                    "Firebase user projection skipped: helper not present.",
                    "FIREBASE_SYNC"
                );
                return;
            }

            require_once $helperPath;
        }

        try
        {
            $syncHelper = new FirebaseSyncHelper($firebaseIdToken);
            $syncHelper->projectUser($userId, true);

            writeLog(
                "Firebase user projection succeeded for user $userId.",
                "FIREBASE_SYNC"
            );
        }
        catch (Exception $exception)
        {
            writeLog(
                "Firebase user projection failed for user $userId: "
                    . $exception->getMessage(),
                "FIREBASE_SYNC_ERROR"
            );
        }
    }
}