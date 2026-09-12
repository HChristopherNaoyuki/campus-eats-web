<?php
/**
 * Shared Vendor Management Functions
 *
 * CORRECTION: Extracted the vendor approval logic that was previously
 * duplicated in admin/dashboard.php and admin/manage_vendors.php.
 *
 * SOURCE: Issue report - item 17
 *
 * @version 1.0
 */

require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/error_logging.php';

if (!function_exists('approveVendor'))
{
    /**
     * Approves a vendor and verifies the associated user account.
     *
     * @param int $vendorId The vendor ID to approve
     * @return bool True on success
     */
    function approveVendor($vendorId)
    {
        $vendorId = (int)$vendorId;

        if ($vendorId <= 0)
        {
            return false;
        }

        $db = getDB();

        $vendor = $db->fetchOne(
            "SELECT vendor_user_id FROM vendors WHERE vendor_id = :vendor_id LIMIT 1",
            array('vendor_id' => $vendorId)
        );

        if (!$vendor)
        {
            return false;
        }

        $db->executeQuery(
            "UPDATE vendors SET is_approved = 1 WHERE vendor_id = :vendor_id",
            array('vendor_id' => $vendorId)
        );

        $db->executeQuery(
            "UPDATE users SET is_verified = 1 WHERE user_id = :user_id",
            array('user_id' => $vendor['vendor_user_id'])
        );

        writeLog("Vendor approved: vendor_id=$vendorId", "ADMIN");
        return true;
    }
}

if (!function_exists('rejectVendor'))
{
    /**
     * Rejects a vendor application and removes the associated user.
     *
     * @param int $vendorId The vendor ID to reject
     * @return bool True on success
     */
    function rejectVendor($vendorId)
    {
        $vendorId = (int)$vendorId;

        if ($vendorId <= 0)
        {
            return false;
        }

        $db = getDB();

        $vendor = $db->fetchOne(
            "SELECT vendor_user_id FROM vendors WHERE vendor_id = :vendor_id LIMIT 1",
            array('vendor_id' => $vendorId)
        );

        if (!$vendor)
        {
            return false;
        }

        $db->executeQuery(
            "DELETE FROM vendors WHERE vendor_id = :vendor_id",
            array('vendor_id' => $vendorId)
        );

        $db->executeQuery(
            "DELETE FROM users WHERE user_id = :user_id",
            array('user_id' => $vendor['vendor_user_id'])
        );

        writeLog("Vendor rejected: vendor_id=$vendorId", "ADMIN");
        return true;
    }
}

if (!function_exists('suspendVendor'))
{
    /**
     * Suspends a vendor by deactivating the associated user account.
     *
     * @param int $vendorId The vendor ID to suspend
     * @return bool True on success
     */
    function suspendVendor($vendorId)
    {
        $vendorId = (int)$vendorId;

        if ($vendorId <= 0)
        {
            return false;
        }

        $db = getDB();

        $vendor = $db->fetchOne(
            "SELECT vendor_user_id FROM vendors WHERE vendor_id = :vendor_id LIMIT 1",
            array('vendor_id' => $vendorId)
        );

        if (!$vendor)
        {
            return false;
        }

        $db->executeQuery(
            "UPDATE users SET is_active = 0 WHERE user_id = :user_id",
            array('user_id' => $vendor['vendor_user_id'])
        );

        writeLog("Vendor suspended: vendor_id=$vendorId", "ADMIN");
        return true;
    }
}

if (!function_exists('activateVendor'))
{
    /**
     * Reactivates a suspended vendor.
     *
     * @param int $vendorId The vendor ID to activate
     * @return bool True on success
     */
    function activateVendor($vendorId)
    {
        $vendorId = (int)$vendorId;

        if ($vendorId <= 0)
        {
            return false;
        }

        $db = getDB();

        $vendor = $db->fetchOne(
            "SELECT vendor_user_id FROM vendors WHERE vendor_id = :vendor_id LIMIT 1",
            array('vendor_id' => $vendorId)
        );

        if (!$vendor)
        {
            return false;
        }

        $db->executeQuery(
            "UPDATE users SET is_active = 1 WHERE user_id = :user_id",
            array('user_id' => $vendor['vendor_user_id'])
        );

        writeLog("Vendor activated: vendor_id=$vendorId", "ADMIN");
        return true;
    }
}