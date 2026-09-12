<?php
/**
 * Demonstration Accounts Configuration
 *
 * Returns the demonstration account table used by the seed script and by
 * the development installer. The accounts exist so that a fresh
 * installation can be tested without manually registering ten users.
 *
 * IMPORTANT: DEMONSTRATION DATA
 *
 * The ten accounts below are demonstration data. Every name, email
 * address, and password is fabricated for local testing. They are not
 * real people. They are not associated with any real email provider.
 * The passwords are public and must never be used in a deployed
 * environment.
 *
 * Before deploying this application to any host that is reachable from
 * a network, delete this file or change every password to a value that
 * is not published here, and remove the call to the seed script from
 * the installation procedure.
 *
 * ROLE DISTRIBUTION
 *
 * The ten accounts are distributed as requested:
 *
 *   admins     2
 *   vendors    3
 *   standard   4
 *   students   1
 *
 * Total       10
 *
 * PASSWORD POLICY
 *
 * Each password satisfies the application policy:
 *
 *   - At least 8 characters.
 *   - At least one uppercase letter.
 *   - At least one lowercase letter.
 *   - At least one digit.
 *   - At least one special symbol.
 *
 * The accounts are stored in MySQL with a bcrypt hash. The plain-text
 * values in this file exist only so the accounts can be seeded and
 * documented. They are never stored in the database.
 *
 * SOURCE: NOTES - Populate the database using real or simulated data,
 *         with at least ten records per table. Make use of demo
 *         accounts.
 *
 * @version 1.0
 */

return array(

    // =========================================================================
    // Administrators - 2 accounts
    // =========================================================================

    array(
        'user_id'       => 1001,
        'unique_id'     => 'ADMN4K7P2Q9XRT5M',
        'full_name'     => 'Amara Nkosi',
        'username'      => 'amara.nkosi',
        'email'         => 'amara.nkosi@campuseats.test',
        'password'      => 'Adm1n#Amara',
        'account_type'  => 'admin',
        'is_verified'   => 1,
        'is_active'     => 1,
        'vendor_name'   => null,
        'description'   => null
    ),

    array(
        'user_id'       => 1002,
        'unique_id'     => 'ADMN8B3W6Y1ZPL4N',
        'full_name'     => 'Pieter van Wyk',
        'username'      => 'pieter.vanwyk',
        'email'         => 'pieter.vanwyk@campuseats.test',
        'password'      => 'Adm1n#Pieter',
        'account_type'  => 'admin',
        'is_verified'   => 1,
        'is_active'     => 1,
        'vendor_name'   => null,
        'description'   => null
    ),

    // =========================================================================
    // Vendors - 3 accounts
    // =========================================================================

    array(
        'user_id'       => 2001,
        'unique_id'     => 'VNDR2T5H8J3KQ7L',
        'full_name'     => 'Thandiwe Mokoena',
        'username'      => 'thandiwe.mokoena',
        'email'         => 'thandiwe.mokoena@campuseats.test',
        'password'      => 'Vend0r#Thandi',
        'account_type'  => 'vendor',
        'is_verified'   => 1,
        'is_active'     => 1,
        'vendor_name'   => 'Campus Corner Kitchen',
        'description'   => 'Traditional South African meals prepared fresh daily. Pap, chakalaka, and grilled chicken are the house specialities.'
    ),

    array(
        'user_id'       => 2002,
        'unique_id'     => 'VNDR9F4G7N2MXP5Q',
        'full_name'     => 'Sipho Dlamini',
        'username'      => 'sipho.dlamini',
        'email'         => 'sipho.dlamini@campuseats.test',
        'password'      => 'Vend0r#Sipho',
        'account_type'  => 'vendor',
        'is_verified'   => 1,
        'is_active'     => 1,
        'vendor_name'   => 'Braai Brothers',
        'description'   => 'Flame-grilled meat, boerewors rolls, and vegetarian skewers. Open from breakfast until late afternoon.'
    ),

    array(
        'user_id'       => 2003,
        'unique_id'     => 'VNDR6C1V9B4LZR8T',
        'full_name'     => 'Annelie Botha',
        'username'      => 'annelie.botha',
        'email'         => 'annelie.botha@campuseats.test',
        'password'      => 'Vend0r#Annelie',
        'account_type'  => 'vendor',
        'is_verified'   => 1,
        'is_active'     => 1,
        'vendor_name'   => 'Coffee and Koeksisters',
        'description'   => 'Speciality coffee, freshly baked koeksisters, and light breakfast options for early lectures.'
    ),

    // =========================================================================
    // Standard users - 4 accounts
    // =========================================================================

    array(
        'user_id'       => 3001,
        'unique_id'     => 'STDN3J7R5H2KXQ9M',
        'full_name'     => 'Lerato Khumalo',
        'username'      => 'lerato.khumalo',
        'email'         => 'lerato.khumalo@campuseats.test',
        'password'      => 'Stand@rd#Lerato',
        'account_type'  => 'standard',
        'is_verified'   => 1,
        'is_active'     => 1,
        'vendor_name'   => null,
        'description'   => null
    ),

    array(
        'user_id'       => 3002,
        'unique_id'     => 'STDN7P4W1Y6NBLZ2',
        'full_name'     => 'Johan Pretorius',
        'username'      => 'johan.pretorius',
        'email'         => 'johan.pretorius@campuseats.test',
        'password'      => 'Stand@rd#Johan',
        'account_type'  => 'standard',
        'is_verified'   => 1,
        'is_active'     => 1,
        'vendor_name'   => null,
        'description'   => null
    ),

    array(
        'user_id'       => 3003,
        'unique_id'     => 'STDN5T8M3K2LZXR6Q',
        'full_name'     => 'Zanele Ndlovu',
        'username'      => 'zanele.ndlovu',
        'email'         => 'zanele.ndlovu@campuseats.test',
        'password'      => 'Stand@rd#Zanele',
        'account_type'  => 'standard',
        'is_verified'   => 1,
        'is_active'     => 1,
        'vendor_name'   => null,
        'description'   => null
    ),

    array(
        'user_id'       => 3004,
        'unique_id'     => 'STDN9R2B7V4MQP1X',
        'full_name'     => 'Marius Steyn',
        'username'      => 'marius.steyn',
        'email'         => 'marius.steyn@campuseats.test',
        'password'      => 'Stand@rd#Marius',
        'account_type'  => 'standard',
        'is_verified'   => 1,
        'is_active'     => 1,
        'vendor_name'   => null,
        'description'   => null
    ),

    // =========================================================================
    // Student - 1 account
    // =========================================================================

    array(
        'user_id'       => 4001,
        'unique_id'     => 'STDT4K9X2P7MNZR5B',
        'full_name'     => 'Naledi Mahlangu',
        'username'      => 'naledi.mahlangu',
        'email'         => 'naledi.mahlangu@campuseats.test',
        'password'      => 'Stud3nt#Naledi',
        'account_type'  => 'student',
        'is_verified'   => 1,
        'is_active'     => 1,
        'vendor_name'   => null,
        'description'   => null
    ),

);