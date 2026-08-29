<?php
/**
 * User ID Generation Helper
 *
 * Generates User IDs in the format: YYYYMMDDHHMM + SS + AA
 *
 * CORRECTIONS (Version 3.0 - Standard User Support):
 * - Added 'standard' account type code '04'.
 * - Updated validation to include '04' as a valid code.
 *
 * @version 3.0
 */

function generateUserId($accountType)
{
    $microtime = microtime(true);
    $seconds = (int)$microtime;
    $microseconds = (int)(($microtime - $seconds) * 1000000);

    $datePart12 = gmdate('YmdHi', $seconds);
    $microPart = str_pad(substr((string)$microseconds, 0, 2), 2, '0', STR_PAD_RIGHT);

    $accountTypeCodes = array
    (
        'admin'    => '01',
        'vendor'   => '02',
        'student'  => '03',
        'standard' => '04'  // CORRECTION: Added standard user type
    );

    $typeCode = isset($accountTypeCodes[$accountType]) ? $accountTypeCodes[$accountType] : '03';

    return $datePart12 . $microPart . $typeCode;
}

function validateUserIdFormat($userId)
{
    if (strlen($userId) !== 16)
    {
        return false;
    }

    if (!ctype_digit(substr($userId, 0, 14)))
    {
        return false;
    }

    $typeCode = substr($userId, 14, 2);
    $validCodes = array('01', '02', '03', '04');  // CORRECTION: Added '04'

    return in_array($typeCode, $validCodes, true);
}