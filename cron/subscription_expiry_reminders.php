<?php
/**
 * Queue subscription expiry reminders for 7, 3 and 1 days before expiry.
 * Run once daily via cPanel PHP CLI cron. Safe to run repeatedly; event_key deduplicates.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}
require_once __DIR__ . '/../database_config/db.php';
require_once __DIR__ . '/../lib/notifications.php';

if (!$conn || $conn->connect_error) {
    fwrite(STDERR, "Database unavailable\n");
    exit(1);
}
$lock = $conn->query("SELECT GET_LOCK('avenpay_subscription_reminders', 0) AS acquired");
$lockRow = $lock ? $lock->fetch_assoc() : null;
if ((int)($lockRow['acquired'] ?? 0) !== 1) exit(0);

try {
    $sql = "SELECT s.id AS settlement_id, s.expiry_date, u.id AS user_id, u.email, u.username,
                   DATEDIFF(DATE(s.expiry_date), CURDATE()) AS days_left
            FROM settlements s
            INNER JOIN users u ON u.id = s.user_id
            WHERE s.is_active = 1
              AND s.status = 'active'
              AND s.expiry_date IS NOT NULL
              AND DATEDIFF(DATE(s.expiry_date), CURDATE()) IN (7, 3, 1)
              AND u.email IS NOT NULL AND u.email <> ''";
    $result = $conn->query($sql);
    if (!$result) throw new RuntimeException('Subscription reminder query failed: ' . $conn->error);

    while ($row = $result->fetch_assoc()) {
        $days = (int)$row['days_left'];
        $expiry = (string)$row['expiry_date'];
        $label = $days === 1 ? 'tomorrow' : 'in ' . $days . ' days';
        $subject = 'AvenPay subscription expires ' . $label;
        $dedupe = 'subscription-expiry:' . (int)$row['user_id'] . ':' . (int)$row['settlement_id'] . ':' . $expiry . ':' . $days;

        av_notify_lifecycle(
            (string)$row['email'],
            (string)$row['username'],
            $subject,
            'Your AvenPay subscription expires ' . $label,
            'This is a reminder that the subscription for your settlement account is approaching its expiry date. Renew before expiry to avoid interruption to your API services.',
            [
                'Settlement account' => '#' . (int)$row['settlement_id'],
                'Expiry date' => $expiry,
                'Time remaining' => $days . ($days === 1 ? ' day' : ' days')
            ],
            'billing',
            'Review your subscription',
            'https://avenpay.co.ke/dashboard',
            $dedupe
        );
    }
} catch (Throwable $e) {
    error_log('AvenPay subscription reminder cron failed: ' . $e->getMessage());
    exit(1);
} finally {
    $conn->query("SELECT RELEASE_LOCK('avenpay_subscription_reminders')");
}
