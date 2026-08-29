<?php
/**
 * Password Validation Helper (Complete Implementation)
 *
 * Validates passwords according to the policy:
 * - Minimum 8 characters
 * - At least 1 uppercase letter
 * - At least 1 digit
 * - At least 1 special symbol
 *
 * Source: campus-eats-process-document.pdf (Password Policy)
 *
 * @version 2.0
 */

function validatePasswordPolicy($password)
{
    $errors = array();

    if (strlen($password) < 8)
    {
        $errors[] = 'Password must be at least 8 characters long.';
    }

    if (!preg_match('/[A-Z]/', $password))
    {
        $errors[] = 'Password must contain at least 1 uppercase letter.';
    }

    if (!preg_match('/[0-9]/', $password))
    {
        $errors[] = 'Password must contain at least 1 digit.';
    }

    if (!preg_match('/[^a-zA-Z0-9]/', $password))
    {
        $errors[] = 'Password must contain at least 1 special symbol.';
    }

    if (empty($errors))
    {
        return array('valid' => true, 'message' => 'Password is valid.');
    }
    else
    {
        return array('valid' => false, 'message' => implode(' ', $errors));
    }
}

function hashPassword($password)
{
    return password_hash($password, PASSWORD_DEFAULT, array('cost' => 12));
}

function verifyPassword($password, $hash)
{
    return password_verify($password, $hash);
}
?>