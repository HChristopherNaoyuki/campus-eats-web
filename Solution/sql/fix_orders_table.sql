-- =============================================================================
-- Database Fix: Add Missing Columns to Orders Table
-- =============================================================================
-- This script adds all missing columns to the orders table to fix the
-- database schema mismatch causing order submission failures.
--
-- CORRECTIONS (Version 1.0):
-- - Added transaction_id column for transaction tracking.
-- - Added subtotal column for order subtotal tracking.
-- - Added service_fee column for service fee tracking.
-- - Added tax column for tax calculation tracking.
-- - Added rounding_adjustment column for rounding rule tracking.
-- - Added pickup_time column for pickup time selection.
-- - Added special_requests column for special order requests.
-- - Added IF NOT EXISTS checks for each column.
--
-- Source: Campus Eats Software Engineering Report - Issue 2
-- Date: 2026-06-21
-- =============================================================================

-- Switch to the campus_eats database
USE `campus_eats`;

-- =============================================================================
-- Add transaction_id column
-- =============================================================================

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'campus_eats'
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'transaction_id'
);

IF @column_exists = 0 THEN
    ALTER TABLE `orders`
    ADD COLUMN `transaction_id` VARCHAR(50) NULL UNIQUE
    COMMENT 'Unique transaction identifier (Format: TDYYYYMMDDHHMMSS)';

    ALTER TABLE `orders`
    ADD INDEX `idx_transaction_id` (`transaction_id`);

    SELECT 'transaction_id column added successfully.' AS status;
ELSE
    SELECT 'transaction_id column already exists.' AS status;
END IF;

-- =============================================================================
-- Add subtotal column
-- =============================================================================

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'campus_eats'
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'subtotal'
);

IF @column_exists = 0 THEN
    ALTER TABLE `orders`
    ADD COLUMN `subtotal` DECIMAL(10,2) DEFAULT 0.00
    COMMENT 'Subtotal before fees and taxes';

    ALTER TABLE `orders`
    ADD INDEX `idx_subtotal` (`subtotal`);

    SELECT 'subtotal column added successfully.' AS status;
ELSE
    SELECT 'subtotal column already exists.' AS status;
END IF;

-- =============================================================================
-- Add service_fee column
-- =============================================================================

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'campus_eats'
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'service_fee'
);

IF @column_exists = 0 THEN
    ALTER TABLE `orders`
    ADD COLUMN `service_fee` DECIMAL(10,2) DEFAULT 0.00
    COMMENT 'Service fee (10% or 6.5% based on subtotal)';

    ALTER TABLE `orders`
    ADD INDEX `idx_service_fee` (`service_fee`);

    SELECT 'service_fee column added successfully.' AS status;
ELSE
    SELECT 'service_fee column already exists.' AS status;
END IF;

-- =============================================================================
-- Add tax column
-- =============================================================================

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'campus_eats'
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'tax'
);

IF @column_exists = 0 THEN
    ALTER TABLE `orders`
    ADD COLUMN `tax` DECIMAL(10,2) DEFAULT 0.00
    COMMENT 'Tax amount (20% of subtotal + service fee)';

    ALTER TABLE `orders`
    ADD INDEX `idx_tax` (`tax`);

    SELECT 'tax column added successfully.' AS status;
ELSE
    SELECT 'tax column already exists.' AS status;
END IF;

-- =============================================================================
-- Add rounding_adjustment column
-- =============================================================================

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'campus_eats'
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'rounding_adjustment'
);

IF @column_exists = 0 THEN
    ALTER TABLE `orders`
    ADD COLUMN `rounding_adjustment` DECIMAL(10,2) DEFAULT 0.00
    COMMENT 'Rounding adjustment to reach next multiple of R5';

    ALTER TABLE `orders`
    ADD INDEX `idx_rounding_adjustment` (`rounding_adjustment`);

    SELECT 'rounding_adjustment column added successfully.' AS status;
ELSE
    SELECT 'rounding_adjustment column already exists.' AS status;
END IF;

-- =============================================================================
-- Add pickup_time column
-- =============================================================================

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'campus_eats'
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'pickup_time'
);

IF @column_exists = 0 THEN
    ALTER TABLE `orders`
    ADD COLUMN `pickup_time` VARCHAR(50) NULL
    COMMENT 'Preferred pickup time (Format: HH:MM)';

    ALTER TABLE `orders`
    ADD INDEX `idx_pickup_time` (`pickup_time`);

    SELECT 'pickup_time column added successfully.' AS status;
ELSE
    SELECT 'pickup_time column already exists.' AS status;
END IF;

-- =============================================================================
-- Add special_requests column
-- =============================================================================

SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = 'campus_eats'
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'special_requests'
);

IF @column_exists = 0 THEN
    ALTER TABLE `orders`
    ADD COLUMN `special_requests` TEXT NULL
    COMMENT 'Special requests for the order';

    SELECT 'special_requests column added successfully.' AS status;
ELSE
    SELECT 'special_requests column already exists.' AS status;
END IF;

-- =============================================================================
-- Verify all columns exist
-- =============================================================================

SELECT 'Verifying columns in orders table:' AS status;

SELECT
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'campus_eats'
  AND TABLE_NAME = 'orders'
ORDER BY ORDINAL_POSITION;