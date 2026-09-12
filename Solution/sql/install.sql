-- =============================================================================
-- Campus Eats Database Installation Script
-- =============================================================================
-- Creates the complete schema required by the application.
--
-- CORRECTIONS (Version 18.0):
-- - The script contains no comment text containing a semicolon. The install
--   loop in database.php splits on semicolons that appear inside comments
--   unless the splitter is comment-aware, and the previous version included
--   an example CREATE DATABASE statement with a trailing semicolon inside a
--   comment. That example has been removed.
-- - The USE statement is the first executed statement. It ensures the script
--   is self-contained and does not depend on the caller having selected the
--   database.
-- - The DROP TABLE order is child-before-parent, so the script is correct
--   even when FOREIGN_KEY_CHECKS is enabled.
-- - No INSERT statement appears anywhere in the file. No user, administrator,
--   demo, or sample account is created by this script. All accounts are
--   created through the registration page.
--
-- SOURCE: DEMO ACCOUNT REQUIREMENT (Interpretation C)
-- SOURCE: SQL SYNTAX ERROR INVESTIGATION REPORT
--
-- @version 18.0
-- =============================================================================

USE `campus_eats`;

SET FOREIGN_KEY_CHECKS = 0;

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
    `is_open`         TINYINT(1) DEFAULT 1,
    `is_approved`     TINYINT(1) DEFAULT 0,
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
    CONSTRAINT `chk_menu_items_price` CHECK (`price` >= 0),
    CONSTRAINT `chk_menu_items_quantity` CHECK (`quantity_available` >= 0),
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_is_available` (`is_available`),
    INDEX `idx_category` (`category`),
    INDEX `idx_price` (`price`),
    INDEX `idx_quantity_available` (`quantity_available`),
    INDEX `idx_vendor_available` (`vendor_id`, `is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Menu items offered by each vendor';

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
    `order_status`        ENUM('pending', 'accepted', 'preparing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
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
    CONSTRAINT `chk_orders_total` CHECK (`total_amount` >= 0),
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
    CONSTRAINT `chk_order_items_quantity` CHECK (`quantity` > 0),
    CONSTRAINT `chk_order_items_unit_price` CHECK (`unit_price` >= 0),
    CONSTRAINT `chk_order_items_subtotal` CHECK (`subtotal` >= 0),
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Individual line items within an order';

CREATE TABLE IF NOT EXISTS `payments`
(
    `payment_id`            INT AUTO_INCREMENT PRIMARY KEY,
    `order_id`              INT NOT NULL UNIQUE,
    `payment_method`        VARCHAR(50) NOT NULL,
    `payment_status`        ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    `transaction_reference` VARCHAR(100) NOT NULL UNIQUE,
    `amount`                DECIMAL(10,2) NOT NULL,
    `payment_date`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_payments_order`
        FOREIGN KEY (`order_id`)
        REFERENCES `orders`(`order_id`)
        ON DELETE CASCADE,
    CONSTRAINT `chk_payments_amount` CHECK (`amount` >= 0),
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_payment_date` (`payment_date`),
    INDEX `idx_payment_method` (`payment_method`),
    INDEX `idx_transaction_reference` (`transaction_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Payment records for completed orders';

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

CREATE TABLE IF NOT EXISTS `login_attempts`
(
    `attempt_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address`   VARCHAR(45) NOT NULL,
    `username`     VARCHAR(100) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip_time` (`ip_address`, `attempted_at`),
    INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Failed login attempts for brute force protection';

CREATE TABLE IF NOT EXISTS `password_reset_attempts`
(
    `attempt_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address`   VARCHAR(45) NOT NULL,
    `email`        VARCHAR(100) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip_email_time` (`ip_address`, `email`, `attempted_at`),
    INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Password reset attempts for rate limiting';

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

SET FOREIGN_KEY_CHECKS = 1;

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