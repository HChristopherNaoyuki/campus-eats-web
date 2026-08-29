-- =============================================================================
-- Database Migration: Add 'standard' to account_type ENUM and create user_sessions
-- =============================================================================
-- This script updates the account_type column in the users table to support
-- the 'standard' account type and creates the user_sessions table if missing.
--
-- CORRECTIONS (Version 2.0):
-- - Added user_sessions table creation if it doesn't exist.
-- - This fixes the "Table 'campus_eats.user_sessions' doesn't exist" error.
--
-- @version 2.0
-- =============================================================================

USE `campus_eats`;

-- =============================================================================
-- Update the account_type column to include 'standard'
-- =============================================================================

SELECT 'Checking account_type ENUM...' AS status;

SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'campus_eats'
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'account_type';

ALTER TABLE `users`
MODIFY COLUMN `account_type` 
ENUM('admin', 'vendor', 'student', 'standard') 
NOT NULL 
COMMENT 'User role: admin, vendor, student, or standard';

SELECT 'account_type ENUM updated.' AS status;

-- =============================================================================
-- Create user_sessions table if it doesn't exist (CORRECTION)
-- =============================================================================

SELECT 'Checking user_sessions table...' AS status;

CREATE TABLE IF NOT EXISTS `user_sessions`
(
    `session_id`    VARCHAR(128) NOT NULL PRIMARY KEY,
    `user_id`       INT NOT NULL,
    `ip_address`    VARCHAR(45) NOT NULL,
    `user_agent`    TEXT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores active user sessions for session management and tracking';

SELECT 'user_sessions table created or already exists.' AS status;

-- =============================================================================
-- Verification
-- =============================================================================

SELECT 'Verification Results:' AS status;

SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'campus_eats'
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'account_type';

SELECT 'account_type ENUM values:' AS status;
SELECT 'admin, vendor, student, standard' AS enum_values;

-- Verify user_sessions table exists
SELECT 'user_sessions table columns:' AS status;

SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'campus_eats'
  AND TABLE_NAME = 'user_sessions'
ORDER BY ORDINAL_POSITION;

-- =============================================================================
-- Rollback Instructions
-- =============================================================================
/*
To rollback this change, execute:
ALTER TABLE `users`
MODIFY COLUMN `account_type` 
ENUM('admin', 'vendor', 'student') 
NOT NULL 
COMMENT 'User role: admin, vendor, or student';

DROP TABLE IF EXISTS `user_sessions`;
*/

-- =============================================================================
-- End of Migration
-- =============================================================================
SELECT 'Migration completed successfully.' AS status;