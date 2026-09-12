-- =============================================================================
-- Database Fix: Add transaction_reference column default value
-- =============================================================================
-- This script fixes the payments table to allow transaction_reference to be NULL
-- or provides a default value.
--
-- CORRECTIONS (Version 1.0):
-- - Modified transaction_reference column to allow NULL values.
-- - This prevents the "Field 'transaction_reference' doesn't have a default value" error.
-- - Alternatively, the application should provide a value for this column.
--
-- Source: Campus Eats Software Engineering Report - Issue 2
-- Date: 2026-06-22
-- =============================================================================

-- Switch to the campus_eats database
USE `campus_eats`;

-- Option A: Allow NULL values for transaction_reference
ALTER TABLE `payments`
MODIFY COLUMN `transaction_reference` VARCHAR(100) NULL;

-- Option B: Set a default value (uncomment if needed)
-- ALTER TABLE `payments`
-- MODIFY COLUMN `transaction_reference` VARCHAR(100) DEFAULT 'N/A';

-- Verify the change
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'campus_eats'
  AND TABLE_NAME = 'payments'
  AND COLUMN_NAME = 'transaction_reference';