-- =============================================================================
-- Login Attempts Table Installation Script
-- =============================================================================
-- This script creates the login_attempts table for rate limiting functionality.
-- It is designed to be run separately from the main install.sql to avoid
-- modifying the existing production schema unexpectedly.
--
-- The table stores failed login attempts and is used by the getFailedLoginAttemptCount,
-- recordFailedLoginAttempt, and clearFailedLoginAttempts functions in auth.php.
--
-- CORRECTIONS (Version 3.0):
-- - Added IF NOT EXISTS clause to prevent errors on re-runs
-- - Added proper character set and collation
-- - Added index on attempted_at for cleanup queries
-- - Added descriptive comments for each field
--
-- To run this script manually (if the automatic creation in auth.php fails):
--   1. Log into MySQL: mysql -u root -p
--   2. USE campus_eats;
--   3. SOURCE /path/to/install_login_attempts_table.sql;
-- =============================================================================

-- First, ensure we are using the correct database.
USE `campus_eats`;

-- Check if the table already exists. If not, create it.
CREATE TABLE IF NOT EXISTS `login_attempts`
(
    `attempt_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address`   VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address of the requester',
    `username`     VARCHAR(100) NOT NULL COMMENT 'The username that was attempted',
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp of the failed attempt',
    INDEX `idx_ip_time` (`ip_address`, `attempted_at`) COMMENT 'Optimises the rate limiting query for getFailedLoginAttemptCount',
    INDEX `idx_attempted_at` (`attempted_at`) COMMENT 'Optimises cleanup queries'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores failed login attempts for rate limiting and brute force protection';

-- Optional: Add a cleanup event to remove old records automatically.
-- This event runs daily and deletes attempts older than 30 days.
-- Note: Events must be enabled on the MySQL server (event_scheduler=ON).

-- Check if the event already exists before creating it
SET @event_exists = (SELECT COUNT(*) FROM information_schema.EVENTS WHERE EVENT_SCHEMA = 'campus_eats' AND EVENT_NAME = 'cleanup_login_attempts');

IF @event_exists = 0 THEN
    CREATE EVENT IF NOT EXISTS `cleanup_login_attempts`
    ON SCHEDULE EVERY 1 DAY
    STARTS CURRENT_TIMESTAMP
    DO
        DELETE FROM `login_attempts`
        WHERE `attempted_at` < DATE_SUB(NOW(), INTERVAL 30 DAY);
END IF;

-- Confirm the table was created.
SELECT 'login_attempts table created successfully.' AS status;
SELECT 'Table columns: attempt_id, ip_address, username, attempted_at' AS details;