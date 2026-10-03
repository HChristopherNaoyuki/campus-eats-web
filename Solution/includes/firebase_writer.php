<?php
/**
 * Firebase Realtime Database Writer
 *
 * Provides server-side helpers for writing to the Firebase Realtime
 * Database in a manner that complies with the existing database rules.
 *
 * IMPORTANT: THE DATABASE RULES ARE AUTHORITATIVE
 *
 * The rules in firebase.rules.json are not modified by this file. Every
 * write performed by this file is constructed to satisfy the validation
 * expressions in those rules. When a write would violate a rule, the
 * Firebase server rejects it, and this file surfaces that rejection as
 * an exception so the calling code can handle it.
 *
 * CORRECTIONS (Version 1.1 - Technical Audit Report):
 * - The normaliseRoleForFirebase() helper is the single source of truth
 *   for the mapping from the lowercase MySQL account_type values to the
 *   uppercase Firebase role values. The rule
 *   users/$campus_user_id/role accepts only STUDENT, VENDOR, STANDARD,
 *   and ADMIN. Every caller that writes a user record passes the
 *   lowercase MySQL value to writeUser(), and writeUser() calls this
 *   helper. This keeps the mapping in one place and prevents the
 *   mapping from drifting between files.
 *
 * - The file header comment now names the audit finding the helper
 *   addresses. No functional change was required in this file. The
 *   defect identified by the audit was in firebase_sync_helper.php,
 *   which performed its own role mapping in addition to the mapping
 *   performed here.
 *
 * COMPLIANCE NOTES
 *
 * 1. Users node. The rules require:
 *    - userId must equal the node key and be 16 or 19 characters.
 *    - role must be one of STUDENT, VENDOR, STANDARD, ADMIN in uppercase.
 *    - passwordHash must equal the literal string [FIREBASE_SSO].
 *    - email is immutable after creation.
 *    - status may change only from initial state or by an admin token.
 *    - Several optional fields must be string, number, or null if present.
 *
 * 2. Feedback node. The rules require:
 *    - All nine fields must be present: userId, type, subject, message,
 *      userName, userEmail, status, createdAt, updatedAt.
 *    - type must be the lowercase string complaint or compliment.
 *    - status must be the lowercase string pending or resolved.
 *    - createdAt and updatedAt must be non-empty strings.
 *
 * 3. Orders node. The rules require:
 *    - orderId must equal the node key.
 *    - customerId and vendorId must be strings.
 *    - totalAmount must be a number greater than or equal to zero.
 *    - status must be a string.
 *    - timestamp, if present, must be a number.
 *
 * 4. Coupons node. The rules require:
 *    - code must equal the node key.
 *    - discountPercent must be a number greater than or equal to zero.
 *    - isActive must be a boolean.
 *
 * 5. Admin claims. The rules permit read and write only when the token
 *    carries the admin custom claim. This file does not write to the
 *    admin_claims path. Admin claims are set through the Firebase Admin
 *    SDK or the Firebase console, not through this application.
 *
 * AUTHENTICATION
 *
 * The rules require auth != null for the users, orders, feedback, and
 * coupons nodes. The application writes with a Firebase ID token that
 * the client obtains from the Firebase Authentication SDK. The token is
 * passed to this file through the calling context.
 *
 * SOURCE: Existing Firebase Realtime Database rules, firebase.rules.json.
 * SOURCE: Campus Eats PHP Web Platform - Technical Audit Report,
 *         Sections 1, 2, 3, and 9.
 *
 * @version 1.1
 */

if (!defined('BASE_PATH'))
{
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/config/constants.php';
require_once BASE_PATH . '/includes/firebase_config.php';
require_once BASE_PATH . '/config/error_logging.php';

class FirebaseWriter
{
    /**
     * @var string The database URL with no trailing slash
     */
    private $databaseUrl;

    /**
     * @var string|null The Firebase ID token for the current user
     */
    private $idToken;

    /**
     * Constructor.
     *
     * @param string|null $idToken The Firebase ID token for the current user
     */
    public function __construct($idToken = null)
    {
        $this->databaseUrl = rtrim(FIREBASE_DATABASE_URL, '/');
        $this->idToken = $idToken;
    }

    /**
     * Sets the Firebase ID token for subsequent requests.
     *
     * @param string $idToken The Firebase ID token
     * @return void
     */
    public function setIdToken($idToken)
    {
        $this->idToken = $idToken;
    }

    /**
     * Returns the current Firebase ID token, or null.
     *
     * @return string|null
     */
    public function getIdToken()
    {
        return $this->idToken;
    }

    /**
     * Builds the full URL for a database path.
     *
     * @param string $path The database path
     * @return string The full URL
     */
    private function buildUrl($path)
    {
        $normalisedPath = ltrim($path, '/');
        return $this->databaseUrl . '/' . $normalisedPath . '.json';
    }

    /**
     * Performs an HTTP request against the Firebase REST API.
     *
     * The method is private because callers should use the typed
     * methods below, which enforce the rule constraints.
     *
     * @param string $method The HTTP method (PUT, PATCH, POST, DELETE)
     * @param string $path   The database path
     * @param mixed  $data   The request body
     * @return array{status:int, body:string}
     * @throws RuntimeException When the request cannot be completed
     */
    private function request($method, $path, $data = null)
    {
        $url = $this->buildUrl($path);

        if ($this->idToken !== null && $this->idToken !== '')
        {
            // The auth query parameter is the standard Firebase REST API
            // mechanism for supplying an ID token. The rules evaluate
            // auth against the decoded token.
            $url .= '?auth=' . urlencode($this->idToken);
        }

        $ch = curl_init($url);

        if ($ch === false)
        {
            throw new RuntimeException('Failed to initialise cURL.');
        }

        $headers = array('Content-Type: application/json');

        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        );

        if ($data !== null)
        {
            $options[CURLOPT_POSTFIELDS] = json_encode(
                $data,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        }

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($errno !== 0)
        {
            writeLog(
                "Firebase request failed: cURL error $errno - $error",
                "FIREBASE_ERROR"
            );
            throw new RuntimeException('cURL error ' . $errno . ': ' . $error);
        }

        if ($status < 200 || $status >= 300)
        {
            writeLog(
                "Firebase request returned HTTP $status for $method $path: "
                    . substr((string)$body, 0, 300),
                "FIREBASE_ERROR"
            );
            throw new RuntimeException(
                'Firebase request failed with HTTP ' . $status . ': '
                    . substr((string)$body, 0, 300)
            );
        }

        return array('status' => $status, 'body' => (string)$body);
    }

    /**
     * Converts a PHP role string to the uppercase form required by the
     * users/$uid/role rule.
     *
     * The rule accepts only STUDENT, VENDOR, STANDARD, and ADMIN. The
     * application stores lowercase role values in MySQL. This method
     * performs the conversion required for the Firebase projection. It
     * is the single source of truth for the mapping.
     *
     * @param string $role The role in lowercase
     * @return string The role in uppercase
     * @throws InvalidArgumentException When the role is not recognised
     */
    public static function normaliseRoleForFirebase($role)
    {
        $normalised = strtoupper(trim((string)$role));

        $allowed = array('STUDENT', 'VENDOR', 'STANDARD', 'ADMIN');

        if (!in_array($normalised, $allowed, true))
        {
            throw new InvalidArgumentException(
                'Role is not one of the permitted values: ' . $role
            );
        }

        return $normalised;
    }

    /**
     * Normalises a user ID for the Firebase key.
     *
     * The users/$uid/userId rule requires the value to equal the node
     * key and to be 16 or 19 characters. This method strips hyphens
     * when the input is the 19-character hyphenated form, producing the
     * canonical 16-character form used as the node key. When the input
     * is already 16 characters, it is returned unchanged.
     *
     * @param string $userId The user ID in either form
     * @return string The 16-character form
     * @throws InvalidArgumentException When the ID length is not valid
     */
    public static function normaliseUserIdForFirebase($userId)
    {
        $stripped = str_replace('-', '', (string)$userId);

        $length = strlen($stripped);

        if ($length !== 16)
        {
            throw new InvalidArgumentException(
                'User ID must be 16 characters after stripping hyphens. '
                    . 'Received length: ' . $length
            );
        }

        return $stripped;
    }

    /**
     * Writes a user record to the users node.
     *
     * The payload is constructed to satisfy every validation expression
     * in the users/$campus_user_id rule. Optional fields are omitted
     * rather than written as null, because the rule permits null for
     * some fields but not all, and omitting a field is always safe.
     *
     * The email field is immutable. On update, the caller must pass the
     * email that already exists in the node. If the caller passes a
     * different email, the rule rejects the write.
     *
     * The status field is preserved on non-admin updates. The caller
     * must pass the existing status when the write is not performed by
     * an administrator.
     *
     * @param string $userId     The 16-character user ID
     * @param array  $userData   The user data to write
     * @param bool   $isNewUser  True when creating a new node
     * @return void
     * @throws RuntimeException When the write fails
     * @throws InvalidArgumentException When the payload cannot satisfy the rules
     */
    public function writeUser($userId, $userData, $isNewUser)
    {
        $canonicalId = self::normaliseUserIdForFirebase($userId);

        // The role is converted from the lowercase MySQL value to the
        // uppercase value the rule requires. The helper is the single
        // source of truth for the mapping.
        $role = self::normaliseRoleForFirebase(
            isset($userData['role']) ? $userData['role'] : 'student'
        );

        // The userId field must equal the node key.
        $payload = array(
            'userId'       => $canonicalId,
            'fullName'     => isset($userData['fullName'])
                ? (string)$userData['fullName']
                : '',
            'email'        => isset($userData['email'])
                ? (string)$userData['email']
                : '',
            'role'         => $role,
            'passwordHash' => '[FIREBASE_SSO]'
        );

        // The username field is optional in the rules but is commonly
        // present. It must be a string when written.
        if (isset($userData['username']))
        {
            $payload['username'] = (string)$userData['username'];
        }

        // The status field. On a new node, any string is accepted. On
        // update, the rule permits the value to remain the same, or to
        // change when the token carries the admin claim. The caller is
        // responsible for supplying the correct value. This method
        // validates that the value is a string.
        if (isset($userData['status']))
        {
            $payload['status'] = (string)$userData['status'];
        }

        // Optional fields. Each must be a string, a number, or null
        // depending on the field. This method casts to the type the rule
        // expects and includes the field only when a value is supplied.
        if (array_key_exists('walletBalance', $userData)
            && $userData['walletBalance'] !== null)
        {
            $balance = (float)$userData['walletBalance'];

            if ($balance < 0)
            {
                throw new InvalidArgumentException(
                    'walletBalance must not be negative.'
                );
            }

            $payload['walletBalance'] = $balance;
        }

        if (array_key_exists('shopName', $userData)
            && $userData['shopName'] !== null
            && $userData['shopName'] !== '')
        {
            $payload['shopName'] = (string)$userData['shopName'];
        }

        if (array_key_exists('shopStatus', $userData)
            && $userData['shopStatus'] !== null
            && $userData['shopStatus'] !== '')
        {
            $payload['shopStatus'] = (string)$userData['shopStatus'];
        }

        if (array_key_exists('bankAccountInfo', $userData)
            && $userData['bankAccountInfo'] !== null
            && $userData['bankAccountInfo'] !== '')
        {
            $payload['bankAccountInfo'] = (string)$userData['bankAccountInfo'];
        }

        if (array_key_exists('registrationDate', $userData)
            && $userData['registrationDate'] !== null)
        {
            $payload['registrationDate'] = (int)$userData['registrationDate'];
        }

        if (array_key_exists('usercode', $userData)
            && $userData['usercode'] !== null
            && $userData['usercode'] !== '')
        {
            $payload['usercode'] = (string)$userData['usercode'];
        }

        $path = 'users/' . $canonicalId;

        // PUT replaces the entire node. On update, the email rule
        // requires the value to be unchanged. The caller is responsible
        // for passing the existing email. On create, the rule does not
        // constrain the email value beyond being a string.
        $this->request('PUT', $path, $payload);

        writeLog(
            "Firebase user write succeeded for $canonicalId "
                . "(role: $role, "
                . ($isNewUser ? 'create' : 'update') . ")",
            "FIREBASE"
        );
    }

    /**
     * Writes a feedback record to the feedback node.
     *
     * The payload is constructed to satisfy every validation expression
     * in the feedback/$feedback_id rule. All nine required fields are
     * present. The type and status values are lowercase.
     *
     * @param string $feedbackId The feedback node key
     * @param array  $data       The feedback data
     * @return void
     * @throws RuntimeException When the write fails
     * @throws InvalidArgumentException When the payload cannot satisfy the rules
     */
    public function writeFeedback($feedbackId, $data)
    {
        $type = isset($data['type'])
            ? strtolower((string)$data['type'])
            : '';

        if ($type !== 'complaint' && $type !== 'compliment')
        {
            throw new InvalidArgumentException(
                'Feedback type must be complaint or compliment.'
            );
        }

        $status = isset($data['status'])
            ? strtolower((string)$data['status'])
            : '';

        if ($status !== 'pending' && $status !== 'resolved')
        {
            throw new InvalidArgumentException(
                'Feedback status must be pending or resolved.'
            );
        }

        $createdAt = isset($data['createdAt'])
            ? (string)$data['createdAt']
            : gmdate('c');

        $updatedAt = isset($data['updatedAt'])
            ? (string)$data['updatedAt']
            : $createdAt;

        if ($createdAt === '' || $updatedAt === '')
        {
            throw new InvalidArgumentException(
                'Feedback createdAt and updatedAt must be non-empty strings.'
            );
        }

        $payload = array(
            'userId'    => isset($data['userId'])
                ? (string)$data['userId'] : '',
            'type'      => $type,
            'subject'   => isset($data['subject'])
                ? (string)$data['subject'] : '',
            'message'   => isset($data['message'])
                ? (string)$data['message'] : '',
            'userName'  => isset($data['userName'])
                ? (string)$data['userName'] : '',
            'userEmail' => isset($data['userEmail'])
                ? (string)$data['userEmail'] : '',
            'status'    => $status,
            'createdAt' => $createdAt,
            'updatedAt' => $updatedAt
        );

        $path = 'feedback/' . $feedbackId;

        $this->request('PUT', $path, $payload);

        writeLog(
            "Firebase feedback write succeeded for $feedbackId",
            "FIREBASE"
        );
    }

    /**
     * Writes an order record to the orders node.
     *
     * The payload is constructed to satisfy every validation expression
     * in the orders/$order_id rule. The orderId field equals the node
     * key. The customerId and vendorId are strings. The totalAmount is
     * a number greater than or equal to zero. The timestamp, when
     * present, is a number.
     *
     * @param string $orderId The order node key
     * @param array  $data    The order data
     * @return void
     * @throws RuntimeException When the write fails
     * @throws InvalidArgumentException When the payload cannot satisfy the rules
     */
    public function writeOrder($orderId, $data)
    {
        if (empty($orderId))
        {
            throw new InvalidArgumentException('Order ID must not be empty.');
        }

        $totalAmount = isset($data['totalAmount'])
            ? (float)$data['totalAmount']
            : 0.0;

        if ($totalAmount < 0)
        {
            throw new InvalidArgumentException(
                'Order totalAmount must not be negative.'
            );
        }

        $payload = array(
            'orderId'     => (string)$orderId,
            'customerId'  => isset($data['customerId'])
                ? (string)$data['customerId'] : '',
            'vendorId'    => isset($data['vendorId'])
                ? (string)$data['vendorId'] : '',
            'totalAmount' => $totalAmount,
            'status'      => isset($data['status'])
                ? (string)$data['status'] : 'pending'
        );

        // Optional fields. Each is written only when a value is supplied
        // and the value satisfies the corresponding rule.
        if (array_key_exists('itemsJson', $data)
            && $data['itemsJson'] !== null
            && $data['itemsJson'] !== '')
        {
            $payload['itemsJson'] = (string)$data['itemsJson'];
        }

        if (array_key_exists('paymentMethod', $data)
            && $data['paymentMethod'] !== null
            && $data['paymentMethod'] !== '')
        {
            $payload['paymentMethod'] = (string)$data['paymentMethod'];
        }

        if (array_key_exists('pickupTime', $data)
            && $data['pickupTime'] !== null
            && $data['pickupTime'] !== '')
        {
            // The rule permits a string or a number for pickupTime.
            // The application supplies a string in the format HH:MM.
            $payload['pickupTime'] = (string)$data['pickupTime'];
        }

        if (array_key_exists('specialRequests', $data)
            && $data['specialRequests'] !== null
            && $data['specialRequests'] !== '')
        {
            $payload['specialRequests'] = (string)$data['specialRequests'];
        }

        if (array_key_exists('timestamp', $data)
            && $data['timestamp'] !== null)
        {
            $payload['timestamp'] = (int)$data['timestamp'];
        }
        else
        {
            $payload['timestamp'] = time();
        }

        $path = 'orders/' . $orderId;

        $this->request('PUT', $path, $payload);

        writeLog(
            "Firebase order write succeeded for $orderId",
            "FIREBASE"
        );
    }

    /**
     * Writes a coupon record to the coupons node.
     *
     * The payload is constructed to satisfy every validation expression
     * in the coupons/$code rule. The code field equals the node key.
     * The discountPercent is a number greater than or equal to zero. The
     * isActive field is a boolean.
     *
     * @param string $code The coupon code node key
     * @param array  $data The coupon data
     * @return void
     * @throws RuntimeException When the write fails
     * @throws InvalidArgumentException When the payload cannot satisfy the rules
     */
    public function writeCoupon($code, $data)
    {
        if (empty($code))
        {
            throw new InvalidArgumentException('Coupon code must not be empty.');
        }

        $discountPercent = isset($data['discountPercent'])
            ? (float)$data['discountPercent']
            : 0.0;

        if ($discountPercent < 0)
        {
            throw new InvalidArgumentException(
                'Coupon discountPercent must not be negative.'
            );
        }

        $payload = array(
            'code'            => (string)$code,
            'discountPercent' => $discountPercent,
            'isActive'        => isset($data['isActive'])
                ? (bool)$data['isActive']
                : false
        );

        if (array_key_exists('expiryDate', $data)
            && $data['expiryDate'] !== null
            && $data['expiryDate'] !== '')
        {
            // The rule permits a number or a string for expiryDate.
            $payload['expiryDate'] = $data['expiryDate'];
        }

        if (array_key_exists('assignedUserId', $data)
            && $data['assignedUserId'] !== null
            && $data['assignedUserId'] !== '')
        {
            $payload['assignedUserId'] = (string)$data['assignedUserId'];
        }

        $path = 'coupons/' . $code;

        $this->request('PUT', $path, $payload);

        writeLog(
            "Firebase coupon write succeeded for $code",
            "FIREBASE"
        );
    }

    /**
     * Deletes a node at the given path.
     *
     * The rules for the users, orders, feedback, and coupons nodes
     * permit a write when auth != null. A DELETE request sets the node
     * to null, which the rules treat as a write. The caller must ensure
     * that the authenticated user is permitted to delete the node under
     * the specific node rule.
     *
     * @param string $path The database path
     * @return void
     * @throws RuntimeException When the delete fails
     */
    public function deleteNode($path)
    {
        $this->request('DELETE', $path, null);

        writeLog("Firebase delete succeeded for $path", "FIREBASE");
    }
}