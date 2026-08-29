-- =============================================================================
-- Password Reset Attempts Table for Rate Limiting
-- =============================================================================
-- This table stores password reset attempts for brute-force protection.
-- Used by the rate limiting in forgot_password.php.
--
-- CORRECTION: HIGH-07 - Added rate limiting table
--
-- @version 1.0
-- =============================================================================

CREATE TABLE IF NOT EXISTS `password_reset_attempts`
(
    `attempt_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address`   VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address of the requester',
    `email`        VARCHAR(100) NOT NULL COMMENT 'Email address being reset',
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp of the reset attempt',
    INDEX `idx_ip_email_time` (`ip_address`, `email`, `attempted_at`) COMMENT 'Optimizes rate limiting queries',
    INDEX `idx_attempted_at` (`attempted_at`) COMMENT 'Optimizes cleanup queries'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores password reset attempts for rate limiting';