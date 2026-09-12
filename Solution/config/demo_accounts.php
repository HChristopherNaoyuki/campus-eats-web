<?php
/**
 * Demo Accounts Configuration
 *
 * CORRECTIONS (Version 3.0 - Demo Account Removal):
 * - This file previously returned eight named demo accounts. The
 *   requirement is that the application must not create or provide demo
 *   accounts, default login credentials, sample accounts, or test
 *   accounts. The file now returns an empty array.
 *
 * - The file is kept rather than deleted because database.php and
 *   login.php both include it. Returning an empty array keeps those
 *   includes working without change. No caller receives any account
 *   from this file.
 *
 * - The first user account is created through the registration page.
 *   If no users exist yet, the registration page offers the Admin role
 *   for that first account only.
 *
 * SOURCE: DEMO ACCOUNT REQUIREMENT (Interpretation C)
 *
 * @version 3.0
 */

return array();