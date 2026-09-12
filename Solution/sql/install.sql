-- =============================================================================
-- Campus Eats Database Installation Script
-- =============================================================================
-- CORRECTIONS (Version 15.0 - Add user_sessions Table):
-- - Added user_sessions table for session management.
-- - This table stores active session mappings for authenticated users.
-- - Supports session update and migration operations.
-- - Maintains referential integrity with users table.
--
-- @version 15.0
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `complaints_compliments`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `menu_items`;
DROP TABLE IF EXISTS `vendors`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `password_reset_attempts`;
DROP TABLE IF EXISTS `user_sessions`;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- 1. Users Table
-- =============================================================================
CREATE TABLE IF NOT EXISTS `users`
(
    `user_id`       INT AUTO_INCREMENT PRIMARY KEY,
    `unique_id`     VARCHAR(16) NOT NULL UNIQUE,
    `full_name`     VARCHAR(100) NOT NULL,
    `username`      VARCHAR(50) NOT NULL UNIQUE,
    `email`         VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `account_type`  ENUM('admin', 'vendor', 'student', 'standard') NOT NULL,
    `is_active`     TINYINT(1) DEFAULT 1,
    `is_verified`   TINYINT(1) DEFAULT 0,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_account_type` (`account_type`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_is_verified` (`is_verified`),
    INDEX `idx_email` (`email`),
    INDEX `idx_username` (`username`),
    INDEX `idx_unique_id` (`unique_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. Vendors Table
-- =============================================================================
CREATE TABLE IF NOT EXISTS `vendors`
(
    `vendor_id`       INT AUTO_INCREMENT PRIMARY KEY,
    `vendor_user_id`  INT NOT NULL UNIQUE,
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
    FOREIGN KEY (`vendor_user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_is_open` (`is_open`),
    INDEX `idx_is_approved` (`is_approved`),
    INDEX `idx_vendor_name` (`vendor_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. Menu Items Table
-- =============================================================================
CREATE TABLE IF NOT EXISTS `menu_items`
(
    `item_id`            INT AUTO_INCREMENT PRIMARY KEY,
    `vendor_id`          INT NOT NULL,
    `item_name`          VARCHAR(100) NOT NULL,
    `description`        TEXT NULL,
    `price`              DECIMAL(10,2) NOT NULL CHECK (`price` >= 0),
    `quantity_available` INT NOT NULL DEFAULT 0 CHECK (`quantity_available` >= 0),
    `category`           VARCHAR(50) NULL,
    `is_available`       TINYINT(1) DEFAULT 1,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`vendor_id`) ON DELETE CASCADE,
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_is_available` (`is_available`),
    INDEX `idx_category` (`category`),
    INDEX `idx_price` (`price`),
    INDEX `idx_quantity_available` (`quantity_available`),
    INDEX `idx_vendor_available` (`vendor_id`, `is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. Orders Table
-- =============================================================================
CREATE TABLE IF NOT EXISTS `orders`
(
    `order_id`           INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`            INT NOT NULL,
    `vendor_id`          INT NOT NULL,
    `order_number`       VARCHAR(50) UNIQUE NOT NULL,
    `transaction_id`     VARCHAR(50) UNIQUE NULL,
    `total_amount`       DECIMAL(10,2) NOT NULL CHECK (`total_amount` >= 0),
    `subtotal`           DECIMAL(10,2) DEFAULT 0.00 CHECK (`subtotal` >= 0),
    `service_fee`        DECIMAL(10,2) DEFAULT 0.00 CHECK (`service_fee` >= 0),
    `student_discount`   DECIMAL(10,2) DEFAULT 0.00 CHECK (`student_discount` >= 0),
    `tax`                DECIMAL(10,2) DEFAULT 0.00 CHECK (`tax` >= 0),
    `rounding_adjustment` DECIMAL(10,2) DEFAULT 0.00,
    `order_status`       ENUM('pending', 'accepted', 'preparing', 'ready', 'completed', 'cancelled') DEFAULT 'pending',
    `pickup_time`        VARCHAR(50) NULL,
    `special_requests`   TEXT NULL,
    `order_placed_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`vendor_id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_vendor_id` (`vendor_id`),
    INDEX `idx_order_status` (`order_status`),
    INDEX `idx_order_placed_at` (`order_placed_at`),
    INDEX `idx_pickup_time` (`pickup_time`),
    INDEX `idx_user_vendor` (`user_id`, `vendor_id`),
    INDEX `idx_vendor_status` (`vendor_id`, `order_status`),
    INDEX `idx_transaction_id` (`transaction_id`),
    INDEX `idx_total_amount` (`total_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. Order Items Table
-- =============================================================================
CREATE TABLE IF NOT EXISTS `order_items`
(
    `order_item_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id`      INT NOT NULL,
    `item_id`       INT NOT NULL,
    `quantity`      INT NOT NULL DEFAULT 1 CHECK (`quantity` > 0),
    `unit_price`    DECIMAL(10,2) NOT NULL CHECK (`unit_price` >= 0),
    `subtotal`      DECIMAL(10,2) NOT NULL CHECK (`subtotal` >= 0),
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
    FOREIGN KEY (`item_id`) REFERENCES `menu_items`(`item_id`),
    INDEX `idx_order_id` (`order_id`),
    INDEX `idx_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6. Payments Table
-- =============================================================================
CREATE TABLE IF NOT EXISTS `payments`
(
    `payment_id`            INT AUTO_INCREMENT PRIMARY KEY,
    `order_id`              INT NOT NULL UNIQUE,
    `payment_method`        VARCHAR(50) NOT NULL,
    `payment_status`        ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    `transaction_reference` VARCHAR(100) NOT NULL UNIQUE,
    `amount`                DECIMAL(10,2) NOT NULL CHECK (`amount` >= 0),
    `payment_date`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
    INDEX `idx_payment_status` (`payment_status`),
    INDEX `idx_payment_date` (`payment_date`),
    INDEX `idx_payment_method` (`payment_method`),
    INDEX `idx_transaction_reference` (`transaction_reference`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7. Complaints and Compliments Table
-- =============================================================================
CREATE TABLE IF NOT EXISTS `complaints_compliments`
(
    `entry_id`    INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT NOT NULL,
    `entry_type`  ENUM('complaint', 'compliment') NOT NULL,
    `subject`     VARCHAR(200) NOT NULL,
    `message`     TEXT NOT NULL,
    `is_resolved` TINYINT(1) DEFAULT 0,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`) ON DELETE CASCADE,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_entry_type` (`entry_type`),
    INDEX `idx_is_resolved` (`is_resolved`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_type_resolved` (`entry_type`, `is_resolved`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 8. Login Attempts Table
-- =============================================================================
CREATE TABLE IF NOT EXISTS `login_attempts`
(
    `attempt_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address`   VARCHAR(45) NOT NULL,
    `username`     VARCHAR(100) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip_time` (`ip_address`, `attempted_at`),
    INDEX `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 9. Password Reset Attempts Table
-- =============================================================================
CREATE TABLE IF NOT EXISTS `password_reset_attempts`
(
    `attempt_id`   INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address`   VARCHAR(45) NOT NULL,
    `email`        VARCHAR(100) NOT NULL,
    `attempted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ip_email_time` (`ip_address`, `email`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 10. User Sessions Table
-- =============================================================================
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

-- =============================================================================
-- Verification Queries
-- =============================================================================
SELECT 'Database installation completed successfully.' AS status;

SELECT 
    'Tables created: ' AS message,
    GROUP_CONCAT(table_name SEPARATOR ', ') AS tables
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name IN ('users', 'vendors', 'menu_items', 'orders', 'order_items', 
                   'payments', 'complaints_compliments', 'login_attempts', 
                   'password_reset_attempts', 'user_sessions');