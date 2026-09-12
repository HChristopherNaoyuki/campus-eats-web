-- =============================================================================
-- Campus Eats Database Installation Script
-- =============================================================================
-- Creates the complete schema required by the application.
--
-- CORRECTIONS (Version 17.0):
-- - Removed every semicolon that appeared inside a comment line. The install
--   loop in database.php splits this file on every semicolon, whether or not
--   the semicolon is inside a comment. A semicolon inside a comment creates a
--   false statement boundary, and the pieces that follow the false boundary
--   are malformed when MySQL receives them. That is the origin of
--   SQLSTATE[42000] 1064 with a backtick near the start of a statement.
-- - Removed every backtick that appeared inside a comment line, for the same
--   reason. A backtick inside a comment is harmless to MySQL, but it is
--   misleading when a partial piece is logged, because the log reader cannot
--   tell whether the backtick came from the comment or from the SQL.
-- - Removed every single-quote and double-quote from comment text. These do
--   not affect the splitter, but a future splitter that tracks string state
--   would be confused by unbalanced quotes inside comments.
-- - Kept every SQL statement unchanged, so the resulting schema is identical
--   to the previous version. This version corrects the packaging, not the
--   schema.
--
-- SOURCE: SQL SYNTAX ERROR INVESTIGATION REPORT
-- SOURCE: DATABASE INSTALLATION FAILURE REPORT
-- SOURCE: MySQL Documentation - Comments and statement delimiters
--
-- @version 17.0
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Select the target database.
-- -----------------------------------------------------------------------------
-- This statement makes the script self-contained. It must appear before any
-- CREATE TABLE statement so every table is created in the intended database,
-- regardless of which database the caller had selected.
--
-- The database itself is created by database.php on first connection. If this
-- script is being run manually in phpMyAdmin and the database does not yet
-- exist, create it first with the CREATE DATABASE statement documented in the
-- MySQL manual, using the utf8mb4 character set and the utf8mb4_unicode_ci
-- collation.
-- -----------------------------------------------------------------------------

USE `campus_eats`;

-- -----------------------------------------------------------------------------
-- Disable foreign key checks during the drop and create phase.
-- -----------------------------------------------------------------------------
-- This is required because some DROP TABLE statements reference tables that
-- are still referenced by other tables foreign keys. The check is re-enabled
-- at the end of the script.
-- -----------------------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Drop existing tables.
-- -----------------------------------------------------------------------------
-- Order matters even with FOREIGN_KEY_CHECKS disabled, because it makes the
-- intent clear and keeps the script correct if the FOREIGN_KEY_CHECKS
-- statements are ever removed. Child tables are dropped before their parents.
--
-- Dependency order, child before parent:
--   complaints_compliments references users
--   payments references orders
--   order_items references orders and menu_items
--   orders references users and vendors
--   menu_items references vendors
--   vendors references users
--   user_sessions references users
--   login_attempts has no foreign keys
--   password_reset_attempts has no foreign keys
-- -----------------------------------------------------------------------------

DROP TABLE IF EXISTS `complaints_compliments`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `menu_items`;
DROP TABLE IF EXISTS `vendors`;
DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `password_reset_attempts`;
DROP TABLE IF EXISTS `users`;

-- -----------------------------------------------------------------------------
-- 1. Users table.
-- -----------------------------------------------------------------------------
-- Every column referenced by includes/auth.php is present here.
-- The account_type enum includes standard because the application supports
-- four roles, which are admin, vendor, student, and standard.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users`
(
    `user_id`       INT AUTO_INCREMENT PRIMARY KEY,
    `unique_id`     VARCHAR(16) NOT NULL UNIQUE
                    COMMENT 'Sixteen character alphanumeric user ID',
    `full_name`     VARCHAR(100) NOT NULL,
    `username`      VARCHAR(50) NOT NULL UNIQUE,
    `email`         VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL
                    COMMENT 'Password hash produced by the password_hash function',
    `account_type`  ENUM('admin', 'vendor', 'student', 'standard') NOT NULL
                    COMMENT 'User role, one of admin, vendor, student, or standard',
    `is_active`     TINYINT(1) DEFAULT 1
                    COMMENT 'Set to 1 when active, 0 when suspended',
    `is_verified`   TINYINT(1) DEFAULT 0
                    COMMENT 'Set to 1 when verified, 0 while pending approval',
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_account_type` (`account_type`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_is_verified` (`is_verified`),
    INDEX `idx_email` (`email`),
    INDEX `idx_username` (`username`),
    INDEX `idx_unique_id` (`unique_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Registered users across all four roles';

-- -----------------------------------------------------------------------------
-- 2. Vendors table.
-- -----------------------------------------------------------------------------
-- One row per vendor, linked to a users row through the vendor_user_id column.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `vendors`
(
    `vendor_id`       INT AUTO_INCREMENT PRIMARY KEY,
    `vendor_user_id`  INT NOT NULL UNIQUE
                      COMMENT 'References the user_id column in the users table',
    `vendor_name`     VARCHAR(100) NOT NULL,
    `business_name`   VARCHAR(100) NULL,
    `description`     TEXT NULL,
    `operating_hours` VARCHAR(100) NULL,
    `contact_phone`   VARCHAR(20) NULL,
    `contact_email`   VARCHAR(100) NULL,
    `address`         TEXT NULL,
    `is_open`         TINYINT(1) DEFAULT 1
                      COMMENT 'Set to 1 when accepting orders, 0 when closed',
    `is_approved`     TINYINT(1) DEFAULT 0
                      COMMENT 'Set to 1 when approved by an administrator, 0 while pending',
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `fk_vendors_user`
        FOREIGN KEY (`vendor_user_id`)
        REFERENCES `users`(`user_id`)
        ON DELETE CASCADE,

    INDEX `idx_is_open` (`is_open`),
    INDEX `idx_is_approved` (`is_approved`),
    INDEX `idx_vendor_name` (`vendor_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vendor profiles linked to user accounts';

-- -----------------------------------------------------------------------------
-- 3. Menu items table.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `menu_items`
(
    `item_id`            INT AUTO_INCREMENT PRIMARY KEY,
    `vendor_id`          INT NOT NULL,
    `item_name`          VARCHAR(100) NOT NULL,
    `description`        TEXT NULL,
    `price`              DECIMAL(10,2) NOT NULL,
    `quantity_available` INT NOT NULL DEFAULT 0,
    `category`           VARCHAR(50) NULL,
    `is_available`       TINYINT(1) DEFAULT 1,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `fk_menu_items_vendor`
        FOREIGN KEY (`vendor_id`)
        REFERENCES `vendors`(`vendor_id`)
        ON DELETE CASCADE,

    CONSTRAINT `chk_menu_items_price`
        CHECK (`price` >= 0),

    CONSTRAINT `chk_menu_items_quantity`
        CHECK (`quantity_available` >= 0),

    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_is_available` (`is_available`),
    INDEX `idx_category` (`category`),
    INDEX `idx_price` (`price`),
    INDEX `idx_quantity_available` (`quantity_available`),
    INDEX `idx_vendor_available` (`vendor_id`, `is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Menu items offered by each vendor';

-- -----------------------------------------------------------------------------
-- 4. Orders table.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `orders`
(
    `order_id`            INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`             INT NOT NULL,
    `vendor_id`           INT NOT NULL,
    `order_number`        VARCHAR(50) UNIQUE NOT NULL,
    `transaction_id`      VARCHAR(50) UNIQUE NULL,
    `total_amount`        DECIMAL(10,2) NOT NULL,
    `subtotal`            DECIMAL(10,2) DEFAULT 0.00,
    `service_fee`         DECIMAL(10,2) DEFAULT 0.00,
    `student_discount`    DECIMAL(10,2) DEFAULT 0.00,
    `tax`                 DECIMAL(10,2) DEFAULT 0.00,
    `rounding_adjustment` DECIMAL(10,2) DEFAULT 0.00,
    `order_status`        ENUM('pending', 'accepted', 'preparing', 'ready', 'completed', 'cancelled')
                          DEFAULT 'pending',
    `pickup_time`         VARCHAR(50) NULL,
    `special_requests`    TEXT NULL,
    `order_placed_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `fk_orders_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users`(`user_id`)
        ON DELETE CASCADE,

    CONSTRAINT `fk_orders_vendor`
        FOREIGN KEY (`vendor_id`)
        REFERENCES `vendors`(`vendor_id`)
        ON DELETE CASCADE,

    CONSTRAINT `chk_orders_total`
        CHECK (`total_amount` >= 0),

    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_order_status` (`order_status`),
    INDEX `idx_order_placed_at` (`order_placed_at`),
    INDEX `idx_pickup_time` (`pickup_time`),
    INDEX `idx_user_vendor` (`user_id`, `vendor_id`),
    INDEX `idx_vendor_status` (`vendor_id`, `order_status`),
    INDEX `idx_transaction_id` (`transaction_id`),
    INDEX `idx_total_amount` (`total_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Customer orders placed against a vendor';

-- -----------------------------------------------------------------------------
-- 5. Order items table.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `order_items`
(
    `order_item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id`      INT NOT NULL,
    `item_id`       INT NOT NULL,
    `quantity`      INT NOT NULL DEFAULT 1,
    `unit_price`    DECIMAL(10,2) NOT NULL,
    `subtotal`      DECIMAL(10,2) NOT NULL,

    CONSTRAINT `fk_order_items_order`
        FOREIGN KEY (`order_id`)
        REFERENCES `orders`(`order_id`)
        ON DELETE CASCADE,

    CONSTRAINT `fk_order_items_menu_item`
        FOREIGN KEY (`item_id`)
        REFERENCES `menu_items`(`item_id`),

    CONSTRAINT `chk_order_items_quantity`
        CHECK (`quantity` > 0),

    CONSTRAINT `chk_order_items_unit_price`
        CHECK (`unit_price` >= 0),

    CONSTRAINT `chk_order_items_subtotal`
        CHECK (`subtotal` >= 0),

    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Individual line items within an order';

-- -----------------------------------------------------------------------------
-- 6. Payments table.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `payments`
(
    `payment_id`            INT AUTO_INCREMENT PRIMARY KEY,
    `order_id`              INT NOT NULL UNIQUE,
    `payment_method`        VARCHAR(50) NOT NULL,
    `payment_status`        ENUM('pending', 'completed', 'failed', 'refunded')
                            DEFAULT 'pending',
    `transaction_reference` VARCHAR(100) NOT NULL UNIQUE,
    `amount`                DECIMAL(10,2) NOT NULL,
    `payment_date`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT `fk_payments_order`
        FOREIGN KEY (`order_id`)
        REFERENCES `orders`(`order_id`)
        ON DELETE CASCADE,

    CONSTRAINT `chk_payments_amount`
        CHECK (`amount` >= 0),

    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_payment_date` (`payment_date`),
    INDEX `idx_payment_method` (`payment_method`),
    INDEX `idx_transaction_reference` (`transaction_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Payment records for completed orders';

-- -----------------------------------------------------------------------------
-- 7. Complaints and compliments table.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `complaints_compliments`
(
    `entry_id`    INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT NOT NULL,
    `entry_type`  ENUM('complaint', 'compliment') NOT NULL,
    `subject`     VARCHAR(200) NOT NULL,
    `message`     TEXT NOT NULL,
    `is_resolved` TINYINT(1) DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT `fk_feedback_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users`(`user_id`)
        ON DELETE CASCADE,

    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_entry_type` (`entry_type`),
    INDEX `idx_is_resolved` (`is_resolved`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_type_resolved` (`entry_type`, `is_resolved`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='User-submitted complaints and compliments';

-- -----------------------------------------------------------------------------
-- 8. Login attempts table.
-- -----------------------------------------------------------------------------
-- Used by the rate limiting in includes/auth.php. Each failed login inserts
-- one row. The row count within the rate limit window determines whether
-- further attempts are blocked.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `login_attempts`
(
    `attempt_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address`   VARCHAR(45) NOT NULL
                   COMMENT 'IPv4 or IPv6 address of the requester',
    `username`     VARCHAR(100) NOT NULL
                   COMMENT 'Username or email used in the failed attempt',
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_ip_time` (`ip_address`, `attempted_at`),
    INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Failed login attempts for brute force protection';

-- -----------------------------------------------------------------------------
-- 9. Password reset attempts table.
-- -----------------------------------------------------------------------------
-- Used by the rate limiting in modules/auth/forgot_password.php.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `password_reset_attempts`
(
    `attempt_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address`   VARCHAR(45) NOT NULL
                   COMMENT 'IPv4 or IPv6 address of the requester',
    `email`        VARCHAR(100) NOT NULL
                   COMMENT 'Email address being reset',
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_ip_email_time` (`ip_address`, `email`, `attempted_at`),
    INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Password reset attempts for rate limiting';

-- -----------------------------------------------------------------------------
-- 10. User sessions table.
-- -----------------------------------------------------------------------------
-- Tracks active session mappings for authenticated users. Created defensively
-- by database.php as well, but listed here so the schema installed by this
-- script is complete.
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `user_sessions`
(
    `session_id`    VARCHAR(128) NOT NULL PRIMARY KEY,
    `user_id`       INT NOT NULL,
    `ip_address`    VARCHAR(45) NOT NULL,
    `user_agent`    TEXT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `last_activity` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `fk_user_sessions_user`
        FOREIGN KEY (`user_id`)
        REFERENCES `users`(`user_id`)
        ON DELETE CASCADE,

    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_last_activity` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Active user sessions for session management and tracking';

-- -----------------------------------------------------------------------------
-- Re-enable foreign key checks.
-- -----------------------------------------------------------------------------

SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Verification.
-- -----------------------------------------------------------------------------
-- The following queries report what was created. They do not change the
-- schema. They are safe to run through the phpMyAdmin import and through the
-- application install loop.
-- -----------------------------------------------------------------------------

SELECT 'Database installation completed successfully.' AS status;

SELECT
    table_name,
    table_rows
FROM information_schema.tables
WHERE table_schema = 'campus_eats'
  AND table_name IN (
      'users',
      'vendors',
      'menu_items',
      'orders',
      'order_items',
      'payments',
      'complaints_compliments',
      'login_attempts',
      'password_reset_attempts',
      'user_sessions'
  )
ORDER BY table_name;

SELECT
    COUNT(*) AS users_table_present
FROM information_schema.tables
WHERE table_schema = 'campus_eats'
  AND table_name = 'users';

SELECT
    column_name,
    column_type,
    is_nullable,
    column_default
FROM information_schema.columns
WHERE table_schema = 'campus_eats'
  AND table_name = 'users'
ORDER BY ordinal_position;