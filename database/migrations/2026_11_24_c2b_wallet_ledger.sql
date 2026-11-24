-- AvenPay manual C2B wallet deposits.
-- Safe to run when the wallet ledger already exists: CREATE TABLE IF NOT EXISTS is idempotent.
CREATE TABLE IF NOT EXISTS wallet_ledger (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    entry_type VARCHAR(30) NOT NULL,
    amount DECIMAL(14,2) NOT NULL COMMENT 'signed: + credit, - debit',
    balance_after DECIMAL(14,2) NOT NULL,
    ref_type VARCHAR(30) NOT NULL,
    ref_id VARCHAR(80) NOT NULL,
    note VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_entry (entry_type, ref_type, ref_id),
    KEY idx_user (user_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
