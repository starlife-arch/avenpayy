<?php
/**
 * AvenPay queued email dispatcher.
 * Run from cPanel cron using PHP CLI every minute.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found");
}
require_once __DIR__ . '/../database_config/db.php';
require_once __DIR__ . '/../lib/mailer.php';

if (!$conn || $conn->connect_error) {
    fwrite(STDERR, "Database unavailable\n");
    exit(1);
}
$lock = $conn->query("SELECT GET_LOCK('avenpay_email_dispatch', 0) AS acquired");
$lockRow = $lock ? $lock->fetch_assoc() : null;
if ((int)($lockRow['acquired'] ?? 0) !== 1) exit(0);

try {
    // Recover jobs left in 'sending' after a worker crash.
    $conn->query("UPDATE email_notification_history SET status='pending' WHERE status='sending' AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)");

    $stmt = $conn->prepare("SELECT id, recipient_email, recipient_name, subject, sender_profile, html_body, attempts FROM email_notification_history WHERE status IN ('pending','failed') AND (next_attempt_at IS NULL OR next_attempt_at <= NOW()) AND attempts < 5 AND html_body IS NOT NULL ORDER BY created_at ASC LIMIT 25");
    if (!$stmt) throw new RuntimeException('Could not prepare email queue query: ' . $conn->error);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($rows as $row) {
        $id = (int)$row['id'];
        $claim = $conn->prepare("UPDATE email_notification_history SET status = 'sending' WHERE id = ? AND status IN ('pending','failed') AND attempts < 5");
        $claim->bind_param('i', $id);
        $claim->execute();
        $claimed = $claim->affected_rows === 1;
        $claim->close();
        if (!$claimed) continue;

        $error = null;
        $ok = av_send_mail((string)$row['recipient_email'], (string)$row['recipient_name'], (string)$row['subject'], (string)$row['html_body'], $error, (string)$row['sender_profile']);
        if ($ok) {
            $done = $conn->prepare("UPDATE email_notification_history SET status='sent', sent_at=NOW(), error_message=NULL, next_attempt_at=NULL, attempts=attempts+1 WHERE id=?");
            $done->bind_param('i', $id);
            $done->execute();
            $done->close();
        } else {
            $attempt = (int)$row['attempts'] + 1;
            $delayMinutes = min(1440, 5 * (2 ** max(0, $attempt - 1)));
            $status = $attempt >= 5 ? 'failed' : 'pending';
            $fail = $conn->prepare("UPDATE email_notification_history SET status=?, attempts=?, error_message=?, next_attempt_at=DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id=?");
            $safeError = substr((string)$error, 0, 1000);
            $fail->bind_param('sisii', $status, $attempt, $safeError, $delayMinutes, $id);
            $fail->execute();
            $fail->close();
        }
    }
} catch (Throwable $e) {
    error_log('AvenPay email dispatcher failed: ' . $e->getMessage());
    exit(1);
} finally {
    $conn->query("SELECT RELEASE_LOCK('avenpay_email_dispatch')");
}
