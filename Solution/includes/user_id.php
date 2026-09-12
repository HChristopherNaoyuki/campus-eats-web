<?php
/**
 * User ID Generation Helper
 *
 * Generates alphanumeric 16-character user IDs in the documented format
 * XXXXXXXXXXXXXXXX (grouped as XXXX-XXXX-XXXX-XXXX for display).
 *
 * CORRECTIONS (Version 4.0):
 * - Replaced numeric generator with cryptographically random alphanumeric
 *   generator matching process document Section 11.1
 * - Added generateAlphanumericUserId() as the primary generator
 * - Preserved generateNumericUserId() for legacy numeric IDs still in DB
 * - validateUserIdFormat() accepts both formats for login compatibility
 *
 * SOURCE: Issue report - item 8
 * SOURCE: campus-eats-process-document.pdf Section 11.1
 *
 * @version 4.0
 */

if (!function_exists('generateUserId'))
{
    /**
     * Generates a new 16-character user ID in the documented alphanumeric
     * format: 16 uppercase alphanumeric characters.
     *
     * The account type is no longer encoded in the ID. Account type is
     * stored in the users.account_type column, so encoding it in the ID
     * was redundant and reduced entropy.
     *
     * @param string $accountType The account type (for logging only)
     * @return string 16-character alphanumeric user ID
     */
    function generateUserId($accountType = 'student')
    {
        return generateAlphanumericUserId($accountType);
    }
}

if (!function_exists('generateAlphanumericUserId'))
{
    /**
     * Generates a 16-character alphanumeric ID.
     *
     * Uses a cryptographically secure random generator. Excludes the
     * characters I, O, 0, and 1 to avoid confusion during manual entry.
     *
     * @param string $accountType The account type (for logging only)
     * @return string 16-character alphanumeric user ID
     */
    function generateAlphanumericUserId($accountType = 'student')
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = 16;

        if (function_exists('random_int'))
        {
            $bytes = random_bytes($length);
        }
        else
        {
            $bytes = openssl_random_pseudo_bytes($length, $strong);
            if (!$strong)
            {
                throw new RuntimeException('No cryptographically secure random source available.');
            }
        }

        $maxIndex = strlen($chars) - 1;
        $userId = '';

        for ($i = 0; $i < $length; $i++)
        {
            $index = ord($bytes[$i]) % ($maxIndex + 1);
            $userId .= $chars[$index];
        }

        if (function_exists('writeLog'))
        {
            writeLog(
                "Generated alphanumeric user ID for account type: $accountType",
                "USER_ID"
            );
        }

        return $userId;
    }
}

if (!function_exists('generateNumericUserId'))
{
    /**
     * Generates a legacy numeric 16-character user ID.
     *
     * Kept for compatibility with databases that still contain numeric
     * user IDs created before version 4.0. New records should use
     * generateAlphanumericUserId().
     *
     * @param string $accountType The account type
     * @return string 16-character numeric user ID
     */
    function generateNumericUserId($accountType = 'student')
    {
        $microtime = microtime(true);
        $seconds = (int)$microtime;
        $microseconds = (int)(($microtime - $seconds) * 1000000);

        $datePart12 = gmdate('YmdHi', $seconds);
        $microPart = str_pad(
            substr((string)$microseconds, 0, 2),
            2,
            '0',
            STR_PAD_RIGHT
        );

        $accountTypeCodes = array(
            'admin'    => '01',
            'vendor'   => '02',
            'student'  => '03',
            'standard' => '04'
        );

        $typeCode = isset($accountTypeCodes[$accountType])
            ? $accountTypeCodes[$accountType]
            : '03';

        return $datePart12 . $microPart . $typeCode;
    }
}

if (!function_exists('validateUserIdFormat'))
{
    /**
     * Validates whether a string is a supported user ID.
     *
     * Supported formats:
     * - Numeric: 16 digits (legacy)
     * - Alphanumeric: 16 uppercase letters and digits (current)
     *
     * @param string $userId The ID to validate
     * @return bool True if valid
     */
    function validateUserIdFormat($userId)
    {
        if (!is_string($userId) || strlen($userId) !== 16)
        {
            return false;
        }

        // Legacy numeric format: 16 digits
        if (ctype_digit($userId))
        {
            return true;
        }

        // Current alphanumeric format: 16 uppercase letters and digits
        if (preg_match('/^[A-Z0-9]{16}$/', $userId))
        {
            return true;
        }

        return false;
    }
}

if (!function_exists('formatUserIdForDisplay'))
{
    /**
     * Formats a user ID for display by inserting hyphens.
     *
     * Numeric and alphanumeric IDs are both displayed as
     * XXXX-XXXX-XXXX-XXXX for readability.
     *
     * @param string $userId The 16-character user ID
     * @return string Formatted ID
     */
    function formatUserIdForDisplay($userId)
    {
        if (!is_string($userId) || strlen($userId) !== 16)
        {
            return $userId;
        }

        return substr($userId, 0, 4) . '-'
             . substr($userId, 4, 4) . '-'
             . substr($userId, 8, 4) . '-'
             . substr($userId, 12, 4);
    }
}