<?php
/**
 * Firebase Synchronization Helper for PHP
 *
 * Provides the server-side integration points between the MySQL
 * application and the Firebase Realtime Database. The helper is used
 * when the application performs a write to MySQL that must be
 * projected to Firebase to keep the two stores consistent.
 *
 * IMPORTANT: THE DATABASE RULES ARE AUTHORITATIVE
 *
 * The existing Firebase Realtime Database rules are not modified by
 * this file. Every Firebase write is constructed to satisfy the
 * validation expressions in those rules. When the local validation
 * performed by this helper would produce a payload that the rules
 * reject, the helper throws before the network request is attempted.
 *
 * DESIGN
 *
 * MySQL remains the authoritative store for accounts, authentication,
 * orders, carts, vendors, and payments. Firebase holds a projection of
 * a subset of that data for use by the client. This helper is the
 * single point at which the projection is written from PHP. It uses
 * the FirebaseWriter class, which encapsulates the REST API calls and
 * the rule compliance checks.
 *
 * AUTHENTICATION
 *
 * The rules require auth != null for the users, orders, feedback, and
 * coupons nodes. When the application writes from the server, it must
 * supply a Firebase ID token that carries the identity of the acting
 * user, or it must use the Firebase Admin SDK. This helper accepts an
 * ID token through the FirebaseWriter constructor. The caller is
 * responsible for obtaining that token.
 *
 * For server-side writes that are not tied to a specific user, such as
 * a scheduled projection refresh, the caller should use the Firebase
 * Admin SDK rather than this helper. This helper is intended for
 * user-initiated writes where the user's own token is available.
 *
 * SOURCE: Existing Firebase Realtime Database rules, firebase.rules.json.
 * SOURCE: Campus Eats Technical Audit Report, Section 4.
 *
 * @version 1.0
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/config/firebase_config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/config/error_logging.php';
require_once BASE_PATH . '/includes/firebase_writer.php';

class FirebaseSyncHelper
{
    /**
     * @var FirebaseWriter The writer instance
     */
    private $writer;

    /**
     * Constructor.
     *
     * @param string|null $idToken The Firebase ID token for the acting user
     */
    public function __construct($idToken = null)
    {
        $this->writer = new FirebaseWriter($idToken);
    }

    /**
     * Projects a MySQL user row to the Firebase users node.
     *
     * The method reads the user row from MySQL, constructs a payload
     * that satisfies the users/$campus_user_id rule, and writes it to
     * Firebase. The user ID is used as the node key, so the userId field
     * in the payload matches the key as the rule requires.
     *
     * The email field is immutable after creation. When the method is
     * called for an existing node, it passes the email from the MySQL
     * row. The MySQL row is the authoritative source, so its email is
     * the value that already exists in Firebase. If the two have drifted,
     * the Firebase write is rejected by the rule, and the caller is
     * notified so the drift can be resolved.
     *
     * @param int    $mysqlUserId The user ID in MySQL
     * @param bool   $isNewUser   True when the Firebase node is new
     * @return void
     * @throws RuntimeException When the write fails
     */
    public function projectUser($mysqlUserId, $isNewUser = false)
    {
        $db = getDB();

        $user = $db->fetchOne(
            "SELECT u.user_id, u.unique_id, u.full_name, u.username,
                    u.email, u.account_type, u.is_active,
                    v.vendor_name, v.is_open
             FROM users u
             LEFT JOIN vendors v ON u.user_id = v.vendor_user_id
             WHERE u.user_id = :user_id
             LIMIT 1",
            array('user_id' => (int)$mysqlUserId)
        );

        if (!$user)
        {
            throw new RuntimeException(
                'Cannot project user: MySQL row not found for ID ' . $mysqlUserId
            );
        }

        // Map the MySQL account_type to the Firebase role values the
        // rule accepts. The mapping is explicit so an unrecognised
        // MySQL value cannot silently produce a non-compliant payload.
        $roleMap = array(
            'student'  => 'student',
            'standard' => 'standard',
            'vendor'   => 'vendor',
            'admin'    => 'admin'
        );

        $accountType = strtolower((string)$user['account_type']);
        $role = isset($roleMap[$accountType]) ? $roleMap[$accountType] : 'student';

        // Map the MySQL is_active flag to the status string. The rule
        // permits any string for status on creation. On update, the
        // value may remain the same or may change when the token carries
        // the admin claim. The application uses the strings active and
        // suspended consistently across both stores.
        $status = ((int)$user['is_active'] === 1) ? 'active' : 'suspended';

        $payload = array(
            'fullName' => (string)$user['full_name'],
            'username' => (string)$user['username'],
            'email'    => (string)$user['email'],
            'role'     => $role,
            'status'   => $status
        );

        // Vendor-specific fields. The rule permits these as strings.
        if ($accountType === 'vendor')
        {
            if (!empty($user['vendor_name']))
            {
                $payload['shopName'] = (string)$user['vendor_name'];
            }

            $payload['shopStatus'] = ((int)$user['is_open'] === 1)
                ? 'open'
                : 'closed';
        }

        $this->writer->writeUser($user['unique_id'], $payload, $isNewUser);

        writeLog(
            "Projected MySQL user {$user['user_id']} to Firebase node "
                . $user['unique_id'],
            "FIREBASE_SYNC"
        );
    }

    /**
     * Projects a MySQL order row to the Firebase orders node.
     *
     * The method reads the order row from MySQL, constructs a payload
     * that satisfies the orders/$order_id rule, and writes it to
     * Firebase. The order number is used as the node key.
     *
     * @param int $mysqlOrderId The order ID in MySQL
     * @return void
     * @throws RuntimeException When the write fails
     */
    public function projectOrder($mysqlOrderId)
    {
        $db = getDB();

        $order = $db->fetchOne(
            "SELECT o.order_id, o.order_number, o.user_id, o.vendor_id,
                    o.total_amount, o.order_status, o.payment_method,
                    o.pickup_time, o.special_requests, o.order_placed_at,
                    u.unique_id AS customer_unique_id,
                    v.vendor_user_id,
                    vu.unique_id AS vendor_unique_id
             FROM orders o
             JOIN users u ON o.user_id = u.user_id
             JOIN vendors v ON o.vendor_id = v.vendor_id
             JOIN users vu ON v.vendor_user_id = vu.user_id
             WHERE o.order_id = :order_id
             LIMIT 1",
            array('order_id' => (int)$mysqlOrderId)
        );

        if (!$order)
        {
            throw new RuntimeException(
                'Cannot project order: MySQL row not found for ID '
                    . $mysqlOrderId
            );
        }

        // The customer and vendor are identified in the Firebase
        // projection by their 16-character user IDs. These match the
        // keys used in the users node, so a client can navigate from an
        // order to the corresponding user records.
        $payload = array(
            'customerId'  => (string)$order['customer_unique_id'],
            'vendorId'    => (string)$order['vendor_unique_id'],
            'totalAmount' => (float)$order['total_amount'],
            'status'      => (string)$order['order_status']
        );

        if (!empty($order['payment_method']))
        {
            $payload['paymentMethod'] = (string)$order['payment_method'];
        }

        if (!empty($order['pickup_time']))
        {
            $payload['pickupTime'] = (string)$order['pickup_time'];
        }

        if (!empty($order['special_requests']))
        {
            $payload['specialRequests'] = (string)$order['special_requests'];
        }

        $payload['timestamp'] = strtotime($order['order_placed_at']);

        $this->writer->writeOrder($order['order_number'], $payload);

        writeLog(
            "Projected MySQL order {$order['order_id']} to Firebase node "
                . $order['order_number'],
            "FIREBASE_SYNC"
        );
    }

    /**
     * Projects a MySQL feedback row to the Firebase feedback node.
     *
     * The method reads the feedback row from MySQL, constructs a payload
     * that satisfies the feedback/$feedback_id rule, and writes it to
     * Firebase. The entry ID is used as the node key.
     *
     * @param int $mysqlFeedbackId The feedback entry ID in MySQL
     * @return void
     * @throws RuntimeException When the write fails
     */
    public function projectFeedback($mysqlFeedbackId)
    {
        $db = getDB();

        $feedback = $db->fetchOne(
            "SELECT cc.entry_id, cc.user_id, cc.entry_type, cc.subject,
                    cc.message, cc.is_resolved, cc.created_at,
                    u.unique_id AS user_unique_id,
                    u.full_name, u.email
             FROM complaints_compliments cc
             JOIN users u ON cc.user_id = u.user_id
             WHERE cc.entry_id = :entry_id
             LIMIT 1",
            array('entry_id' => (int)$mysqlFeedbackId)
        );

        if (!$feedback)
        {
            throw new RuntimeException(
                'Cannot project feedback: MySQL row not found for ID '
                    . $mysqlFeedbackId
            );
        }

        $status = ((int)$feedback['is_resolved'] === 1)
            ? 'resolved'
            : 'pending';

        $createdAt = date('c', strtotime($feedback['created_at']));

        $payload = array(
            'userId'    => (string)$feedback['user_unique_id'],
            'type'      => (string)$feedback['entry_type'],
            'subject'   => (string)$feedback['subject'],
            'message'   => (string)$feedback['message'],
            'userName'  => (string)$feedback['full_name'],
            'userEmail' => (string)$feedback['email'],
            'status'    => $status,
            'createdAt' => $createdAt,
            'updatedAt' => $createdAt
        );

        $this->writer->writeFeedback($feedback['entry_id'], $payload);

        writeLog(
            "Projected MySQL feedback {$feedback['entry_id']} to Firebase.",
            "FIREBASE_SYNC"
        );
    }

    /**
     * Builds the node key for a feedback record.
     *
     * The rule requires the node key to be a stable identifier. The
     * application uses the Firebase user ID concatenated with the
     * creation timestamp. This produces a key that is unique per user
     * and per submission without requiring a round trip to Firebase to
     * obtain a push key.
     *
     * @param string $firebaseUserId The Firebase UID of the submitter
     * @return string The node key
     */
    public static function buildFeedbackKey($firebaseUserId)
    {
        return (string)$firebaseUserId . '_' . time();
    }
}