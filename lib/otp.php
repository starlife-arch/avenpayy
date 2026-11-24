<?php
/** Email one-time codes that authorise a single withdrawal (bound to the exact amount + phone). */
require_once __DIR__ . '/mailer.php';

function av_otp_bind(int $userId, string $amount, string $phone): string {
    return hash_hmac('sha256', $userId . '|' . number_format((float)$amount, 2, '.', '') . '|' . preg_replace('/\D/', '', $phone), (string) av_env('VAULT_KEY') . 'otp');
}

function av_otp_issue(mysqli $conn, int $userId, string $email, string $name, string $amount, string $phone): bool {
    $code = (string) random_int(100000, 999999);
    $hash = hash_hmac('sha256', $code, (string) av_env('VAULT_KEY') . 'otp-code');
    $bind = av_otp_bind($userId, $amount, $phone);
    $st = $conn->prepare("REPLACE INTO withdrawal_otps (user_id, otp_hash, bind_hash, attempts, expires_at) VALUES (?, ?, ?, 0, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
    $st->bind_param("iss", $userId, $hash, $bind);
    $st->execute();
    $st->close();
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $html = "<div style=\"font-family:Arial,sans-serif;max-width:480px;margin:auto\"><h2 style=\"color:#0f172a\">AvenPay withdrawal code</h2>"
          . "<p>Hello {$safeName},</p><p>Use this code to confirm your withdrawal of <b>KES " . number_format((float)$amount, 2) . "</b>:</p>"
          . "<p style=\"font-size:32px;letter-spacing:6px;font-weight:bold;color:#00a37a\">{$code}</p>"
          . "<p>It expires in 10 minutes. If you did not request this, change your password and PIN immediately.</p></div>";
    return av_send_mail($email, $name, 'Your AvenPay withdrawal code', $html);
}

/** @return string ok | bad | expired | locked | none */
function av_otp_verify(mysqli $conn, int $userId, string $code, string $amount, string $phone): string {
    $st = $conn->prepare("SELECT otp_hash, bind_hash, attempts, (expires_at < NOW()) AS expired FROM withdrawal_otps WHERE user_id = ?");
    $st->bind_param("i", $userId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$row) return 'none';
    if ((int)$row['expired'] === 1) return 'expired';
    if ((int)$row['attempts'] >= 5) return 'locked';
    $okCode = hash_equals($row['otp_hash'], hash_hmac('sha256', $code, (string) av_env('VAULT_KEY') . 'otp-code'));
    $okBind = hash_equals($row['bind_hash'], av_otp_bind($userId, $amount, $phone));
    if ($okCode && $okBind) {
        $d = $conn->prepare("DELETE FROM withdrawal_otps WHERE user_id = ?");
        $d->bind_param("i", $userId); $d->execute(); $d->close();
        return 'ok';
    }
    $u = $conn->prepare("UPDATE withdrawal_otps SET attempts = attempts + 1 WHERE user_id = ?");
    $u->bind_param("i", $userId); $u->execute(); $u->close();
    return 'bad';
}
