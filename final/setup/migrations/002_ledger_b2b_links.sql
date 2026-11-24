-- AvenPay migration 002: wallet ledger (+ opening balances), OTP, B2B, payment-link controls.
-- Run once, after 001. Uses IF NOT EXISTS so a re-run is harmless.

CREATE TABLE IF NOT EXISTS `wallet_ledger` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `entry_type` VARCHAR(30) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL COMMENT 'signed: + credit, - debit',
  `balance_after` DECIMAL(14,2) NOT NULL,
  `ref_type` VARCHAR(30) NOT NULL,
  `ref_id` VARCHAR(80) NOT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_entry` (`entry_type`,`ref_type`,`ref_id`),
  KEY `idx_user` (`user_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Baseline: one opening entry per wallet that already holds money, so the ledger sums to the balances.
INSERT INTO `wallet_ledger` (`user_id`,`entry_type`,`amount`,`balance_after`,`ref_type`,`ref_id`,`note`)
SELECT u.`id`, 'opening_balance', u.`b2c_wallet_balance`, u.`b2c_wallet_balance`, 'user', CAST(u.`id` AS CHAR), 'Baseline at ledger go-live'
FROM `users` u
WHERE u.`b2c_wallet_balance` <> 0
  AND NOT EXISTS (SELECT 1 FROM `wallet_ledger` l WHERE l.`entry_type`='opening_balance' AND l.`ref_type`='user' AND l.`ref_id`=CAST(u.`id` AS CHAR));

-- Email OTP for withdrawals
CREATE TABLE IF NOT EXISTS `withdrawal_otps` (
  `user_id` INT NOT NULL,
  `otp_hash` CHAR(64) NOT NULL,
  `bind_hash` CHAR(64) NOT NULL,
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Payment link controls
ALTER TABLE `payment_links`
  ADD COLUMN IF NOT EXISTS `status` VARCHAR(12) NOT NULL DEFAULT 'active',
  ADD COLUMN IF NOT EXISTS `expires_at` DATETIME NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `single_use` TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `min_amount` DECIMAL(10,2) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `max_amount` DECIMAL(10,2) NULL DEFAULT NULL;
ALTER TABLE `mpesa_transactions`
  ADD COLUMN IF NOT EXISTS `payment_link_id` INT NULL DEFAULT NULL,
  ADD INDEX IF NOT EXISTS `idx_payment_link` (`payment_link_id`, `status`);

-- Merchant B2B (pay a Paybill / Till from the wallet)
ALTER TABLE `transaction_fees` MODIFY `service_type` ENUM('c2b_topup','b2c_withdrawal','b2b_payment') NOT NULL;
CREATE TABLE IF NOT EXISTS `mpesa_b2b_transactions` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `wallet_id` VARCHAR(64) NOT NULL DEFAULT '',
  `conversation_id` VARCHAR(100) NOT NULL,
  `originator_conversation_id` VARCHAR(100) DEFAULT NULL,
  `command_id` VARCHAR(30) NOT NULL,
  `party_b` VARCHAR(12) NOT NULL,
  `account_reference` VARCHAR(40) NOT NULL DEFAULT '',
  `transaction_amount` DECIMAL(12,2) NOT NULL,
  `fee_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `result_code` INT NOT NULL DEFAULT 1000,
  `result_desc` VARCHAR(255) DEFAULT NULL,
  `mpesa_receipt` VARCHAR(40) DEFAULT NULL,
  `raw_response` MEDIUMTEXT DEFAULT NULL,
  `idempotency_key` VARCHAR(64) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_idem` (`user_id`,`idempotency_key`),
  KEY `idx_conv` (`conversation_id`),
  KEY `idx_orig_conv` (`originator_conversation_id`),
  KEY `idx_user_day` (`user_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
