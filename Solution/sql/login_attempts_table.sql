-- =============================================================================
-- Login Attempts Table for Rate Limiting
-- =============================================================================
-- This table stores failed login attempts for brute-force protection.
-- The auth.php functions getFailedLoginAttemptCount, recordFailedLoginAttempt,
-- and clearFailedLoginAttempts depend on this table.
--
-- CORRECTIONS (Version 3.0):
-- - Added IF NOT EXISTS clause to prevent errors on re-runs
-- - Added proper character set and collation
-- - Added descriptive column comments
-- - Added index on attempted_at for cleanup
--
-- Run this script after install.sql to add rate limiting capability.
-- =============================================================================

-- Create the login_attempts table if it doesn't exist.
CREATE TABLE IF NOT EXISTS `login_attempts`
(
    `attempt_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address`   VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address of the requester',
    `username`     VARCHAR(100) NOT NULL COMMENT 'The username or email that was attempted',
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp of the failed login attempt',
    INDEX `idx_ip_time` (`ip_address`, `attempted_at`) COMMENT 'Optimizes the rate limiting query for getFailedLoginAttemptCount',
    INDEX `idx_attempted_at` (`attempted_at`) COMMENT 'Optimizes cleanup queries'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores failed login attempts for brute force protection';

-- Verify the table was created
SELECT 'login_attempts table created successfully.' AS status;

-- Show table structure for verification
DESCRIBE login_attempts;