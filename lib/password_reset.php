<?php
/**
 * Shared AvenPay password-reset helpers.
 * Reset tokens are generated as 32 random bytes and stored as SHA-256 hex digests.
 */
require_once __DIR__ . '/../database_config/env.php';
require_once __DIR__ . '/security.php';

function av_reset_token_hash(string $token): string {
    return hash('sha256', $token);
}

function av_reset_csrf_token(): string {
    if (empty($_SESSION['password_reset_csrf'])) {
        $_SESSION['password_reset_csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['password_reset_csrf'];
}

function av_reset_csrf_valid(): bool {
    $submitted = (string) ($_POST['csrf_token'] ?? '');
    $known = (string) ($_SESSION['password_reset_csrf'] ?? '');
    return $submitted !== '' && $known !== '' && hash_equals($known, $submitted);
}

function av_reset_base_url(): string {
    $configured = trim((string) av_env('APP_URL', 'https://avenpay.co.ke'));
    $parts = parse_url($configured);
    if (!$parts || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['https'], true)
        || empty($parts['host']) || !empty($parts['user']) || !empty($parts['pass'])) {
        return 'https://avenpay.co.ke';
    }
    return rtrim($configured, '/');
}

function av_reset_mail(string $email, string $name, string $link, bool $admin = false): bool {
    require_once __DIR__ . '/mailer.php';
    $safeName = htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $safeLink = htmlspecialchars($link, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $role = $admin ? 'administrator ' : '';
    $subject = $admin ? 'AvenPay Admin Password Reset' : 'AvenPay Password Reset';
    $html = '<div style="font-family:Arial,sans-serif;background:#f4f6f9;padding:32px;color:#172033">'
        . '<div style="max-width:540px;margin:auto;background:#fff;padding:30px;border:1px solid #e2e8f0;border-radius:10px">'
        . '<h2 style="color:#087f5f">Reset your AvenPay password</h2>'
        . '<p>Hello <strong>' . $safeName . '</strong>,</p>'
        . '<p>We received a request to reset your ' . $role . 'account password. Use the button below to choose a new password.</p>'
        . '<p style="margin:28px 0"><a href="' . $safeLink . '" style="background:#0b9a74;color:#fff;padding:13px 22px;border-radius:6px;text-decoration:none;font-weight:bold">Reset password</a></p>'
        . '<p>This link expires in one hour and can only be used once. If you did not request it, you can ignore this email.</p>'
        . '<p style="font-size:12px;color:#64748b;word-break:break-all">If the button does not work, open: ' . $safeLink . '</p>'
        . '</div></div>';
    return av_send_mail($email, $name, $subject, $html);
}
