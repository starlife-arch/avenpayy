<?php
/**
 * AvenPay transactional lifecycle notifications.
 * Delivery is deliberately best-effort: mail failure never reverses a completed action.
 */
require_once __DIR__ . '/mailer.php';

function av_notification_html(string $title, string $intro, array $details = [], string $ctaLabel = '', string $ctaUrl = ''): string {
    $esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $rows = '';
    foreach ($details as $label => $value) {
        $rows .= '<tr><td style="padding:10px 0;color:#64748b;font-size:13px;border-bottom:1px solid #e2e8f0">'.$esc($label).'</td><td style="padding:10px 0;color:#0f172a;font-size:13px;text-align:right;border-bottom:1px solid #e2e8f0">'.$esc($value).'</td></tr>';
    }
    $cta = '';
    if ($ctaLabel !== '' && filter_var($ctaUrl, FILTER_VALIDATE_URL) && parse_url($ctaUrl, PHP_URL_SCHEME) === 'https') {
        $cta = '<p style="margin:26px 0"><a href="'.$esc($ctaUrl).'" style="background:#0b9a74;color:#fff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:700;display:inline-block">'.$esc($ctaLabel).'</a></p>';
    }
    return '<!doctype html><html><body style="margin:0;background:#f1f5f9;font-family:Arial,sans-serif;color:#0f172a"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:28px 12px"><tr><td align="center"><table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden"><tr><td style="background:#073b32;padding:22px 28px;color:#fff;font-size:22px;font-weight:800">AvenPay</td></tr><tr><td style="padding:30px 28px"><h1 style="font-size:21px;margin:0 0 14px">'.$esc($title).'</h1><p style="font-size:14px;line-height:1.7;color:#475569">'.$esc($intro).'</p>'.($rows ? '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:20px">'.$rows.'</table>' : '').$cta.'<p style="font-size:13px;line-height:1.7;color:#64748b">If you did not perform this action, contact AvenPay Support at <a href="mailto:support@avenpay.co.ke" style="color:#0b9a74">support@avenpay.co.ke</a>.</p></td></tr><tr><td style="padding:18px 28px;background:#f8fafc;color:#94a3b8;font-size:11px">AvenPay · Secure payments and merchant services<br>© '.date('Y').' AvenPay</td></tr></table></td></tr></table></body></html>';
}

function av_notify_lifecycle(string $to, string $name, string $subject, string $title, string $intro, array $details, string $profile, string $ctaLabel = '', string $ctaUrl = ''): bool {
    global $conn;
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
        error_log('AvenPay lifecycle notification could not be queued: database unavailable.');
        return false;
    }
    $html = av_notification_html($title, $intro, $details, $ctaLabel, $ctaUrl);
    $eventKey = hash('sha256', $profile . '|' . strtolower($to) . '|' . $subject . '|' . microtime(true) . '|' . bin2hex(random_bytes(8)));
    $stmt = $conn->prepare("INSERT INTO email_notification_history (recipient_email, recipient_name, subject, status, created_at, sender_profile, html_body, attempts, next_attempt_at, event_key) VALUES (?, ?, ?, 'pending', NOW(), ?, ?, 0, NULL, ?)");
    if (!$stmt) {
        error_log('AvenPay lifecycle notification queue unavailable. Apply the email notification queue migration: ' . $conn->error);
        return false;
    }
    $stmt->bind_param('ssssss', $to, $name, $subject, $profile, $html, $eventKey);
    $queued = $stmt->execute();
    if (!$queued) error_log('AvenPay lifecycle notification enqueue failed: ' . $stmt->error);
    $stmt->close();
    return $queued;
}
