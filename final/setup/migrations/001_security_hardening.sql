-- AvenPay security hardening migration 001
-- Run ONCE on your database (phpMyAdmin > SQL). Safe to review first; nothing is dropped.

-- 1. Withdrawal PIN brute-force protection
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `pin_failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `pin_locked_until` DATETIME NULL DEFAULT NULL;

-- 2. Idempotent payouts (same Idempotency-Key can never pay twice)
ALTER TABLE `mpesa_b2c_transactions`
  ADD COLUMN IF NOT EXISTS `idempotency_key` VARCHAR(64) NULL DEFAULT NULL,
  ADD UNIQUE KEY IF NOT EXISTS `uniq_user_idem` (`user_id`, `idempotency_key`);

-- 3. Helpful indexes for callback lookups and daily-limit checks
ALTER TABLE `mpesa_b2c_transactions`
  ADD INDEX IF NOT EXISTS `idx_conv` (`conversation_id`),
  ADD INDEX IF NOT EXISTS `idx_orig_conv` (`originator_conversation_id`),
  ADD INDEX IF NOT EXISTS `idx_user_day` (`user_id`, `created_at`);
