-- =============================================================================
-- Database Fix: Add transaction_id column to orders table
-- =============================================================================
-- This script adds the missing transaction_id column to the orders table.
-- The column is used to store unique transaction identifiers for each order.
--
-- CORRECTIONS (Version 1.0):
-- - Added transaction_id column to orders table.
-- - Column is nullable to maintain backward compatibility.
-- - Added unique constraint to prevent duplicate transaction IDs.
-- - Added index for faster lookups.
--
-- Source: Campus Eats Software Engineering Report - Issue 5
-- Date: 2026-06-21
-- =============================================================================

-- Check if the column already exists before adding it
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'campus_eats'
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'transaction_id'
);

-- Add the column only if it does not exist
IF @column_exists = 0 THEN

    ALTER TABLE `orders`
    ADD COLUMN `transaction_id` VARCHAR(50) NULL UNIQUE
    COMMENT 'Unique transaction identifier (Format: TDYYYYMMDDHHMMSS)';

    -- Add index for faster lookups
    ALTER TABLE `orders`
    ADD INDEX `idx_transaction_id` (`transaction_id`);

    SELECT 'transaction_id column added successfully.' AS status;

ELSE

    SELECT 'transaction_id column already exists.' AS status;

END IF;

-- Verify the column was added
SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'campus_eats'
  AND TABLE_NAME = 'orders'
  AND COLUMN_NAME = 'transaction_id';