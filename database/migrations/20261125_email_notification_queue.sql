-- Apply once in phpMyAdmin after the existing email_notification_history table exists.
-- Adds durable queued delivery and retry metadata without changing existing history rows.
ALTER TABLE email_notification_history
    ADD COLUMN sender_profile VARCHAR(30) NOT NULL DEFAULT 'default' AFTER subject,
    ADD COLUMN html_body LONGTEXT NULL AFTER error_message,
    ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN next_attempt_at DATETIME NULL AFTER attempts,
    ADD COLUMN event_key CHAR(64) NULL AFTER provider_reference,
    ADD UNIQUE KEY uq_email_notice_event_key (event_key),
    ADD KEY idx_email_notice_due (status, next_attempt_at, created_at);
