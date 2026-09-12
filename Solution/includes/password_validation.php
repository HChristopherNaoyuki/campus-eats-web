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
 * CORRECTIONS (Version 3.0 - Security Fix):
 * - Added validateAndHashPassword() function for centralized validation (HIGH-05)
 * - All password-setting operations must use this function
 * - Added password strength scoring
 *
 * Source: campus-eats-process-document.pdf (Password Policy)
 * Source: Scope Note - HIGH-05
 *
 * @version 3.0
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

// =============================================================================
// CORRECTION: HIGH-05 - Centralized password validation and hashing
// All password-setting operations should call this function
// =============================================================================

function validateAndHashPassword($password, $context = 'user')
{
    // Validate password policy
    $validation = validatePasswordPolicy($password);
    
    if (!$validation['valid'])
    {
        throw new InvalidArgumentException($validation['message']);
    }
    
    // Hash the password
    return hashPassword($password);
}

/**
 * Calculates password strength score for display.
 *
 * @param string $password The password to score
 * @return array Score and feedback
 */
function getPasswordStrength($password)
{
    $score = 0;
    $feedback = array();
    
    // Length check
    if (strlen($password) >= 8) $score++;
    if (strlen($password) >= 12) $score++;
    
    // Uppercase check
    if (preg_match('/[A-Z]/', $password)) $score++;
    
    // Lowercase check
    if (preg_match('/[a-z]/', $password)) $score++;
    
    // Digit check
    if (preg_match('/[0-9]/', $password)) $score++;
    
    // Special character check
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $score++;
    
    // Determine strength level
    if ($score <= 2)
    {
        $level = 'weak';
        $feedback[] = 'Consider using a longer password with more variety.';
    }
    elseif ($score <= 4)
    {
        $level = 'fair';
        $feedback[] = 'Adding more complexity would strengthen this password.';
    }
    elseif ($score <= 6)
    {
        $level = 'good';
        $feedback[] = 'This is a good password.';
    }
    else
    {
        $level = 'strong';
        $feedback[] = 'This is a strong password.';
    }
    
    return array(
        'score' => $score,
        'level' => $level,
        'feedback' => $feedback
    );
}