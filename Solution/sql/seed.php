<?php
/**
 * Database Seeding Script
 *
 * CORRECTIONS (Version 15.0 - Demo Account Removal):
 * - This script previously created and repaired eight named demo
 *   accounts. The requirement is that the application must not create
 *   or provide demo accounts, sample users, default login credentials,
 *   or test accounts. The script no longer inserts or updates any rows.
 *
 * - The script is kept rather than deleted so that any existing
 *   documentation, CI job, or runbook that references its path still
 *   finds a valid PHP file. It exits without performing any work.
 *
 * - The first user account is created through the registration page.
 *   If no users exist yet, the registration page offers the Admin role
 *   for that first account only.
 *
 * SOURCE: DEMO ACCOUNT REQUIREMENT (Interpretation C)
 *
 * @version 15.0
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

echo "========================================\n";
echo "Campus Eats - Database Seeding\n";
echo "========================================\n\n";

echo "This script does not create any accounts.\n";
echo "The application is configured to require that users register their\n";
echo "own accounts through the registration page.\n\n";

echo "To create the first account:\n";
echo "  1. Open the application in a browser.\n";
echo "  2. Navigate to the registration page.\n";
echo "  3. Because no users exist yet, the Admin role will be offered.\n";
echo "  4. Register the first account as an administrator.\n";
echo "  5. Subsequent registrations will only offer Student, Standard,\n";
echo "     and Vendor roles.\n\n";

// Touch the database so the schema is installed on a fresh machine.
// This does not create any user accounts.
try
{
    $db = getDB();
    $count = $db->userCount();
    echo "Current user count: " . $count . "\n";
}
catch (Exception $exception)
{
    echo "Database check failed: " . $exception->getMessage() . "\n";
}

echo "\nDone.\n";