-- =============================================================================
-- Database Indexes for Performance
-- =============================================================================
-- This script adds missing indexes to improve query performance.
-- 
-- CORRECTION: MED-03 - Added missing database indexes
--
-- @version 1.0
-- =============================================================================

USE `campus_eats`;

-- =============================================================================
-- Orders Table Indexes
-- =============================================================================

-- Composite index for user vendor status queries
ALTER TABLE `orders` ADD INDEX IF NOT EXISTS `idx_user_vendor_status` (`user_id`, `vendor_id`, `order_status`);

-- Index for order date queries
ALTER TABLE `orders` ADD INDEX IF NOT EXISTS `idx_created_at` (`order_placed_at`);

-- Index for status filtering
ALTER TABLE `orders` ADD INDEX IF NOT EXISTS `idx_status_created` (`order_status`, `order_placed_at`);

-- =============================================================================
-- Menu Items Table Indexes
-- =============================================================================

-- Index for vendor availability queries
ALTER TABLE `menu_items` ADD INDEX IF NOT EXISTS `idx_vendor_available` (`vendor_id`, `is_available`);

-- Index for category filtering
ALTER TABLE `menu_items` ADD INDEX IF NOT EXISTS `idx_category` (`category`);

-- Index for price sorting
ALTER TABLE `menu_items` ADD INDEX IF NOT EXISTS `idx_price` (`price`);

-- =============================================================================
-- Payments Table Indexes
-- =============================================================================

-- Index for order status queries
ALTER TABLE `payments` ADD INDEX IF NOT EXISTS `idx_order_status` (`order_id`, `payment_status`);

-- Index for date queries
ALTER TABLE `payments` ADD INDEX IF NOT EXISTS `idx_payment_date` (`payment_date`);

-- =============================================================================
-- Users Table Indexes
-- =============================================================================

-- Index for role-based queries
ALTER TABLE `users` ADD INDEX IF NOT EXISTS `idx_role_active` (`account_type`, `is_active`);

-- =============================================================================
-- Feedback Table Indexes
-- =============================================================================

-- Index for user feedback queries
ALTER TABLE `complaints_compliments` ADD INDEX IF NOT EXISTS `idx_user_resolved` (`user_id`, `is_resolved`);

-- Index for type and resolved queries
ALTER TABLE `complaints_compliments` ADD INDEX IF NOT EXISTS `idx_type_resolved` (`entry_type`, `is_resolved`);

-- =============================================================================
-- Verify Indexes
-- =============================================================================

SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'campus_eats'
  AND INDEX_NAME IN (
    'idx_user_vendor_status',
    'idx_created_at',
    'idx_status_created',
    'idx_vendor_available',
    'idx_category',
    'idx_price',
    'idx_order_status',
    'idx_payment_date',
    'idx_role_active',
    'idx_user_resolved',
    'idx_type_resolved'
  )
ORDER BY TABLE_NAME, INDEX_NAME;