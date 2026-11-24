-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 22, 2026 at 09:46 AM
-- Server version: 11.8.9-MariaDB-cll-lve
-- PHP Version: 8.4.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `avenpay`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_password_resets`
--

CREATE TABLE `admin_password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_settlement_accounts`
--

CREATE TABLE `admin_settlement_accounts` (
  `id` int(11) NOT NULL,
  `account_type` enum('bank','paybill','till') NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `bank_branch` varchar(100) DEFAULT NULL,
  `paybill_number` varchar(50) DEFAULT NULL,
  `till_number` varchar(50) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_settlement_transactions`
--

CREATE TABLE `admin_settlement_transactions` (
  `id` int(11) NOT NULL,
  `settlement_id` int(11) DEFAULT NULL,
  `phone_number` varchar(15) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `mpesa_receipt` varchar(50) DEFAULT NULL,
  `checkout_request_id` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `b2c_topup_transactions`
--

CREATE TABLE `b2c_topup_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `wallet_id` varchar(50) DEFAULT NULL,
  `checkout_request_id` varchar(100) NOT NULL,
  `merchant_request_id` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'pending',
  `receipt_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `result_code` int(11) DEFAULT NULL,
  `result_desc` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bank_paybills`
--

CREATE TABLE `bank_paybills` (
  `id` int(11) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `paybill_number` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `c2b_payments`
--

CREATE TABLE `c2b_payments` (
  `id` int(11) NOT NULL,
  `trans_id` varchar(50) NOT NULL,
  `trans_type` varchar(50) DEFAULT NULL,
  `trans_time` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `shortcode` varchar(20) NOT NULL,
  `bill_ref` varchar(100) DEFAULT NULL,
  `msisdn` varchar(20) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `raw_payload` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daraja_b2c_settings`
--

CREATE TABLE `daraja_b2c_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `shortcode` varchar(32) NOT NULL,
  `initiator_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `consumer_key` text DEFAULT NULL,
  `consumer_secret` text DEFAULT NULL,
  `initiator_password` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daraja_gateways`
--

CREATE TABLE `daraja_gateways` (
  `id` int(11) NOT NULL,
  `gateway_name` varchar(100) NOT NULL,
  `consumer_key` text NOT NULL,
  `consumer_secret` text NOT NULL,
  `passkey` text NOT NULL,
  `shortcode` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `initiator_username` varchar(255) NOT NULL DEFAULT '',
  `initiator_password` text NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daraja_settings`
--

CREATE TABLE `daraja_settings` (
  `id` int(11) NOT NULL,
  `gateway_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `key_name` varchar(50) DEFAULT NULL,
  `encrypted_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mpesa_b2c_balance_snapshots`
--

CREATE TABLE `mpesa_b2c_balance_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `utility_balance` decimal(18,2) DEFAULT NULL,
  `working_balance` decimal(18,2) DEFAULT NULL,
  `charges_balance` decimal(18,2) DEFAULT NULL,
  `merchant_balance` decimal(18,2) DEFAULT NULL,
  `airtime_balance` decimal(18,2) DEFAULT NULL,
  `settlement_balance` decimal(18,2) DEFAULT NULL,
  `result_code` varchar(30) NOT NULL,
  `result_description` text DEFAULT NULL,
  `conversation_id` varchar(100) DEFAULT NULL,
  `originator_conversation_id` varchar(100) DEFAULT NULL,
  `raw_response` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mpesa_b2c_transactions`
--

CREATE TABLE `mpesa_b2c_transactions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `conversation_id` varchar(100) NOT NULL,
  `originator_conversation_id` varchar(100) DEFAULT NULL,
  `transaction_id` varchar(50) DEFAULT NULL,
  `result_code` int(11) NOT NULL,
  `result_desc` text NOT NULL,
  `receiver_phone` varchar(20) DEFAULT NULL,
  `command_id` varchar(50) DEFAULT NULL,
  `receiver_name` varchar(150) DEFAULT NULL,
  `transaction_amount` decimal(12,2) DEFAULT 0.00,
  `utility_balance` decimal(12,2) DEFAULT 0.00,
  `working_balance` decimal(12,2) DEFAULT 0.00,
  `completed_at` datetime DEFAULT NULL,
  `raw_response` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `wallet_id` varchar(50) DEFAULT NULL,
  `charges_balance` decimal(12,2) DEFAULT 0.00,
  `merchant_balance` decimal(12,2) DEFAULT 0.00,
  `airtime_balance` decimal(12,2) DEFAULT 0.00,
  `settlement_balance` decimal(12,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mpesa_balance_callbacks`
--

CREATE TABLE `mpesa_balance_callbacks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `callback_type` varchar(30) NOT NULL,
  `conversation_id` varchar(100) DEFAULT NULL,
  `originator_conversation_id` varchar(100) DEFAULT NULL,
  `result_code` varchar(30) DEFAULT NULL,
  `result_desc` text DEFAULT NULL,
  `account_balance` longtext DEFAULT NULL,
  `raw_payload` longtext NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mpesa_stream_queue`
--

CREATE TABLE `mpesa_stream_queue` (
  `queue_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `mpesa_code` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `tx_status` varchar(20) DEFAULT NULL,
  `stream_status` enum('pending','sent') DEFAULT 'pending',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `mpesa_transactions`
--

CREATE TABLE `mpesa_transactions` (
  `id` int(11) NOT NULL,
  `checkout_request_id` varchar(100) NOT NULL,
  `merchant_request_id` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `mpesa_receipt_number` varchar(50) DEFAULT NULL,
  `status` varchar(20) DEFAULT 'PENDING',
  `result_code` int(11) DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `processed` tinyint(1) DEFAULT 0,
  `settlement_id` int(11) DEFAULT NULL,
  `transaction_desc` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Triggers `mpesa_transactions`
--
DELIMITER $$
CREATE TRIGGER `after_mpesa_callback_sync` AFTER UPDATE ON `mpesa_transactions` FOR EACH ROW INSERT INTO mpesa_stream_queue (transaction_id, mpesa_code, phone, amount, tx_status, stream_status) VALUES (NEW.id, NEW.mpesa_receipt_number, NEW.phone_number, NEW.amount, NEW.status, 'pending')
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_mpesa_success` AFTER UPDATE ON `mpesa_transactions` FOR EACH ROW INSERT INTO webhook_queue (transaction_id, webhook_url, payload, status)
    SELECT 
        NEW.id, 
        s.webhook_url, 
        JSON_OBJECT(
            'checkout_id', NEW.checkout_request_id,
            'status', NEW.status,
            'mpesa_receipt', IFNULL(NEW.mpesa_receipt_number, ''),
            'amount', NEW.amount,
            'phone', IFNULL(NEW.phone_number, ''),
            'result_code', IFNULL(NEW.result_code, 0)
        ),
        'PENDING'
    FROM settlements s
    WHERE NEW.status = 'SUCCESS' 
    AND (OLD.status = 'PENDING' OR OLD.status != 'SUCCESS')
    AND s.user_id = NEW.user_id 
    AND s.webhook_url IS NOT NULL 
    AND s.webhook_url != ''
    AND s.is_active = 1
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_insert_mpesa_transactions` BEFORE INSERT ON `mpesa_transactions` FOR EACH ROW BEGIN
    IF NEW.transaction_date IS NULL THEN
        SET NEW.transaction_date = DATE_ADD(NOW(), INTERVAL 1 HOUR); 
        -- Replace '1 HOUR' with the exact difference between your DB and EAT
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expiry_date` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `paybill_logs`
--

CREATE TABLE `paybill_logs` (
  `id` int(11) NOT NULL,
  `trans_id` varchar(50) NOT NULL,
  `trans_type` varchar(50) DEFAULT NULL,
  `trans_time` varchar(20) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `shortcode` varchar(20) NOT NULL,
  `bill_ref` varchar(100) DEFAULT NULL,
  `msisdn` varchar(20) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `raw_payload` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_links`
--

CREATE TABLE `payment_links` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `x_api_key` varchar(64) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL,
  `slug` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `is_fixed` tinyint(1) DEFAULT 0,
  `logo_path` varchar(255) DEFAULT NULL,
  `success_type` enum('url','download') DEFAULT 'url',
  `success_url` varchar(500) DEFAULT NULL,
  `redirect_mode` varchar(20) DEFAULT 'redirect',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settlements`
--

CREATE TABLE `settlements` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `x_api_key` varchar(64) DEFAULT NULL,
  `merchant_name` varchar(100) DEFAULT NULL,
  `type` enum('paybill','till') NOT NULL,
  `shortcode` varchar(20) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `webhook_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `test_stk_count` int(11) DEFAULT 0,
  `amount_to_pay` decimal(10,2) DEFAULT 0.00,
  `plan_type` varchar(20) DEFAULT 'monthly',
  `months_count` int(11) DEFAULT 1,
  `status` varchar(20) DEFAULT 'inactive',
  `start_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transaction_fees`
--

CREATE TABLE `transaction_fees` (
  `id` int(11) NOT NULL,
  `service_type` enum('c2b_topup','b2c_withdrawal') NOT NULL,
  `min_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `max_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `fee_type` enum('fixed','percentage') NOT NULL DEFAULT 'fixed',
  `fee_value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone_number` varchar(15) NOT NULL,
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `use_custom_mpesa` tinyint(1) DEFAULT 0,
  `b2c_wallet_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `on_hold_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_b2c_withdrawn` decimal(12,2) NOT NULL DEFAULT 0.00,
  `wallet_id` varchar(50) DEFAULT NULL,
  `b2c_security_credential` varchar(255) DEFAULT NULL,
  `b2c_withdrawal_pin` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_daraja_settings`
--

CREATE TABLE `user_daraja_settings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `key_name` varchar(50) NOT NULL,
  `encrypted_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_subscriptions`
--

CREATE TABLE `user_subscriptions` (
  `id` int(11) NOT NULL,
  `settlement_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `plan_type` enum('monthly','quarterly','yearly','custom') NOT NULL,
  `months_count` int(11) DEFAULT 1,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `status` enum('active','expired') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `webhook_queue`
--

CREATE TABLE `webhook_queue` (
  `id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `webhook_url` varchar(255) DEFAULT NULL,
  `payload` text DEFAULT NULL,
  `status` enum('PENDING','SENT','FAILED') DEFAULT 'PENDING',
  `attempts` int(11) DEFAULT 0,
  `last_attempt` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `admin_password_resets`
--
ALTER TABLE `admin_password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_unique` (`email`),
  ADD KEY `token_index` (`token`);

--
-- Indexes for table `admin_settlement_accounts`
--
ALTER TABLE `admin_settlement_accounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admin_settlement_transactions`
--
ALTER TABLE `admin_settlement_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `b2c_topup_transactions`
--
ALTER TABLE `b2c_topup_transactions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bank_paybills`
--
ALTER TABLE `bank_paybills`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `paybill_number` (`paybill_number`);

--
-- Indexes for table `c2b_payments`
--
ALTER TABLE `c2b_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `trans_id` (`trans_id`);

--
-- Indexes for table `daraja_b2c_settings`
--
ALTER TABLE `daraja_b2c_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `daraja_gateways`
--
ALTER TABLE `daraja_gateways`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gateway_name` (`gateway_name`);

--
-- Indexes for table `daraja_settings`
--
ALTER TABLE `daraja_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key_name` (`key_name`),
  ADD UNIQUE KEY `uq_gateway_setting` (`gateway_id`,`key_name`);

--
-- Indexes for table `mpesa_b2c_balance_snapshots`
--
ALTER TABLE `mpesa_b2c_balance_snapshots`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_balance_snapshot_created` (`created_at`);

--
-- Indexes for table `mpesa_b2c_transactions`
--
ALTER TABLE `mpesa_b2c_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `conversation_id` (`conversation_id`);

--
-- Indexes for table `mpesa_balance_callbacks`
--
ALTER TABLE `mpesa_balance_callbacks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_id` (`conversation_id`),
  ADD KEY `idx_callback_type` (`callback_type`);

--
-- Indexes for table `mpesa_stream_queue`
--
ALTER TABLE `mpesa_stream_queue`
  ADD PRIMARY KEY (`queue_id`),
  ADD UNIQUE KEY `transaction_id` (`transaction_id`),
  ADD UNIQUE KEY `transaction_id_2` (`transaction_id`),
  ADD UNIQUE KEY `transaction_id_3` (`transaction_id`);

--
-- Indexes for table `mpesa_transactions`
--
ALTER TABLE `mpesa_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `checkout_request_id_2` (`checkout_request_id`),
  ADD UNIQUE KEY `checkout_request_id_3` (`checkout_request_id`),
  ADD UNIQUE KEY `checkout_request_id_4` (`checkout_request_id`),
  ADD KEY `checkout_request_id` (`checkout_request_id`),
  ADD KEY `idx_user_transactions` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `paybill_logs`
--
ALTER TABLE `paybill_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `trans_id` (`trans_id`);

--
-- Indexes for table `payment_links`
--
ALTER TABLE `payment_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `x_api_key` (`x_api_key`);

--
-- Indexes for table `settlements`
--
ALTER TABLE `settlements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `x_api_key` (`x_api_key`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `transaction_fees`
--
ALTER TABLE `transaction_fees`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `phone_number` (`phone_number`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `user_daraja_settings`
--
ALTER TABLE `user_daraja_settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `webhook_queue`
--
ALTER TABLE `webhook_queue`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_password_resets`
--
ALTER TABLE `admin_password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_settlement_accounts`
--
ALTER TABLE `admin_settlement_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_settlement_transactions`
--
ALTER TABLE `admin_settlement_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `b2c_topup_transactions`
--
ALTER TABLE `b2c_topup_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bank_paybills`
--
ALTER TABLE `bank_paybills`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `c2b_payments`
--
ALTER TABLE `c2b_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daraja_b2c_settings`
--
ALTER TABLE `daraja_b2c_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daraja_gateways`
--
ALTER TABLE `daraja_gateways`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daraja_settings`
--
ALTER TABLE `daraja_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mpesa_b2c_balance_snapshots`
--
ALTER TABLE `mpesa_b2c_balance_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mpesa_b2c_transactions`
--
ALTER TABLE `mpesa_b2c_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mpesa_balance_callbacks`
--
ALTER TABLE `mpesa_balance_callbacks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mpesa_stream_queue`
--
ALTER TABLE `mpesa_stream_queue`
  MODIFY `queue_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mpesa_transactions`
--
ALTER TABLE `mpesa_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `paybill_logs`
--
ALTER TABLE `paybill_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_links`
--
ALTER TABLE `payment_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settlements`
--
ALTER TABLE `settlements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transaction_fees`
--
ALTER TABLE `transaction_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_daraja_settings`
--
ALTER TABLE `user_daraja_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `webhook_queue`
--
ALTER TABLE `webhook_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `settlements`
--
ALTER TABLE `settlements`
  ADD CONSTRAINT `settlements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_subscriptions`
--
ALTER TABLE `user_subscriptions`
  ADD CONSTRAINT `user_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;


-- ===== migration 001 =====
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


-- ===== migration 002 =====
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
