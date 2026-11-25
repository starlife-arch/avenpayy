CREATE TABLE IF NOT EXISTS email_notification_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id BIGINT UNSIGNED NULL,
    recipient_user_id BIGINT UNSIGNED NULL,
    recipient_email VARCHAR(254) NOT NULL,
    recipient_name VARCHAR(190) NOT NULL DEFAULT '',
    subject VARCHAR(190) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    provider_reference VARCHAR(190) NULL,
    error_message TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_email_notice_created (created_at),
    KEY idx_email_notice_status (status),
    KEY idx_email_notice_recipient (recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
