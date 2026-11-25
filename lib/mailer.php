<?php
require_once __DIR__ . '/../database_config/env.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

/**
 * Resolve a sender profile without exposing credentials.
 * "default" (and "no-reply") use the legacy SMTP settings.
 */
function av_mail_profile_config(string $profile = 'default'): array {
    $profile = strtolower(trim($profile));
    if ($profile === 'no-reply') $profile = 'default';

    $profiles = [
        'default' => [
            'username_key' => 'SMTP_USERNAME',
            'password_key' => 'SMTP_PASSWORD',
            'fallback_from' => (string) av_env('MAIL_FROM', 'no-reply@avenpay.co.ke'),
        ],
        'hello' => [
            'username_key' => 'SMTP_HELLO_USERNAME',
            'password_key' => 'SMTP_HELLO_PASSWORD',
        ],
        'billing' => [
            'username_key' => 'SMTP_BILLING_USERNAME',
            'password_key' => 'SMTP_BILLING_PASSWORD',
        ],
        'api' => [
            'username_key' => 'SMTP_API_USERNAME',
            'password_key' => 'SMTP_API_PASSWORD',
        ],
        'security' => [
            'username_key' => 'SMTP_SECURITY_USERNAME',
            'password_key' => 'SMTP_SECURITY_PASSWORD',
        ],
    ];

    if (!isset($profiles[$profile])) {
        throw new InvalidArgumentException('Unknown AvenPay email sender profile.');
    }

    $config = $profiles[$profile];
    $username = trim((string) av_env($config['username_key'], ''));
    $password = (string) av_env($config['password_key'], '');

    // Never silently send a profile-specific message through the wrong mailbox.
    if ($profile !== 'default' && ($username === '' || $password === '')) {
        throw new RuntimeException('The requested AvenPay email sender profile is not fully configured.');
    }

    $from = $profile === 'default' ? $config['fallback_from'] : $username;
    if (!filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($username, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('The configured AvenPay sender email address is invalid.');
    }

    return [
        'profile' => $profile,
        'from' => $from,
        'username' => $username,
        'password' => $password,
    ];
}

/** Return the visible From address for a profile, without exposing its password. */
function av_mail_sender_address(string $profile = 'default'): string {
    try {
        return (string) av_mail_profile_config($profile)['from'];
    } catch (Throwable $e) {
        $host = parse_url((string) av_env('APP_URL', 'https://avenpay.co.ke'), PHP_URL_HOST) ?: 'avenpay.co.ke';
        return (string) av_env('MAIL_FROM', 'no-reply@' . $host);
    }
}

/**
 * Send an HTML email using one of the configured AvenPay SMTP accounts.
 *
 * Supported profiles: default/no-reply, hello, billing, api, security.
 * Existing five-argument calls remain compatible and use the default mailbox.
 * Pass the optional sixth argument to select a dedicated sender profile.
 */
function av_send_mail(
    string $toEmail,
    string $toName,
    string $subject,
    string $html,
    ?string &$error = null,
    string $senderProfile = 'default'
): bool {
    $error = null;
    $m = null;

    try {
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Recipient email address is invalid.');
        }

        $sender = av_mail_profile_config($senderProfile);
        $host = trim((string) av_env('SMTP_HOST', 'mail.avenpay.co.ke'));
        $port = (int) av_env('SMTP_PORT', 465);
        if ($host === '' || $port < 1 || $port > 65535 || $sender['password'] === '') {
            throw new RuntimeException('AvenPay SMTP configuration is incomplete.');
        }

        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host       = $host;
        $m->SMTPAuth   = true;
        $m->Username   = $sender['username'];
        $m->Password   = $sender['password'];
        $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $m->Port       = $port;
        $m->CharSet    = 'UTF-8';
        $m->setFrom($sender['from'], 'AvenPay');
        $supportEmail = trim((string) av_env('SUPPORT_EMAIL', 'support@avenpay.co.ke'));
        if (filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
            $m->addReplyTo($supportEmail, 'AvenPay Support');
        }
        $m->addAddress($toEmail, $toName);
        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body    = $html;
        $m->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));
        $m->send();
        return true;
    } catch (Throwable $e) {
        $mailError = ($m instanceof \PHPMailer\PHPMailer\PHPMailer && $m->ErrorInfo !== '')
            ? $m->ErrorInfo
            : $e->getMessage();
        $error = substr(preg_replace('/[\r\n\t]+/', ' ', (string) $mailError), 0, 1000);
        error_log('AvenPay mail failed (' . preg_replace('/[^a-z0-9_-]/i', '', $senderProfile) . '): ' . $error);
        return false;
    }
}
