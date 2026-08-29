<?php
/**
 * Demo Accounts Configuration - Single Source of Truth
 *
 * This file contains the canonical definition of all demo accounts.
 * All other files should include this file rather than defining their own accounts.
 *
 * CORRECTIONS (Version 1.0):
 * - Moved DEMO_ACCOUNTS from auth.php and database.php to a single location
 * - Fixes SEC-01 and ARCH-01 from the scope note
 *
 * SOURCE: campus-eats-process-document.pdf (Section 12 - User Roles)
 * SOURCE: campus-eats-web/Solution/data/users.txt
 * SOURCE: Scope Note - SEC-01, ARCH-01
 *
 * @version 1.0
 */

if (!defined('DEMO_ACCOUNTS'))
{
    define('DEMO_ACCOUNTS', serialize(array(
        // Admin Users (2) - Fixed User IDs: 1001, 1002
        array(
            'user_id'      => 1001,
            'full_name'    => 'John Doe',
            'username'     => 'johndoe',
            'email'        => 'john.doe@example.com',
            'password'     => 'AdminPass123!',
            'account_type' => 'admin',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        array(
            'user_id'      => 1002,
            'full_name'    => 'Sarah Wilson',
            'username'     => 'sarahw',
            'email'        => 'sarah.wilson@example.com',
            'password'     => 'AdminPass987%',
            'account_type' => 'admin',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        // Vendor Users (2) - Fixed User IDs: 2001, 2002
        array(
            'user_id'      => 2001,
            'full_name'    => 'Jane Smith',
            'username'     => 'janesmith',
            'email'        => 'jane.smith@example.com',
            'password'     => 'VendorPass456$',
            'account_type' => 'vendor',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => 'Jane\'s Bakery',
            'description'  => 'Fresh baked goods, pastries, and artisanal breads.'
        ),
        array(
            'user_id'      => 2002,
            'full_name'    => 'Michael Brown',
            'username'     => 'mikeb',
            'email'        => 'mike.brown@example.com',
            'password'     => 'VendorPass789#',
            'account_type' => 'vendor',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => 'Brown\'s Deli',
            'description'  => 'Sandwiches, wraps, and daily specials.'
        ),
        // Standard Users (2) - Fixed User IDs: 3001, 3002
        array(
            'user_id'      => 3001,
            'full_name'    => 'Emily Davis',
            'username'     => 'emilyd',
            'email'        => 'emily.davis@example.com',
            'password'     => 'StandardPass321@',
            'account_type' => 'standard',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        array(
            'user_id'      => 3002,
            'full_name'    => 'Robert Taylor',
            'username'     => 'robertt',
            'email'        => 'robert.taylor@example.com',
            'password'     => 'StandardPass654#',
            'account_type' => 'standard',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        // Student Users (2) - Fixed User IDs: 4001, 4002
        array(
            'user_id'      => 4001,
            'full_name'    => 'David Lee',
            'username'     => 'davidl',
            'email'        => 'david.lee@example.com',
            'password'     => 'StudentPass234$',
            'account_type' => 'student',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        ),
        array(
            'user_id'      => 4002,
            'full_name'    => 'Maria Garcia',
            'username'     => 'mariag',
            'email'        => 'maria.garcia@example.com',
            'password'     => 'StudentPass567%',
            'account_type' => 'student',
            'is_verified'  => 1,
            'is_active'    => 1,
            'vendor_name'  => null,
            'description'  => null
        )
    )));
}