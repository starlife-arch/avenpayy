<?php
require_once __DIR__ . '/../database_config/env.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

/** Send an HTML email through the SMTP account configured in avenpay.env. Returns true on success. */
function av_send_mail(string $toEmail, string $toName, string $subject, string $html, ?string &$error = null): bool {
    $error = null;
    $m = null;
    try {
        $host = parse_url((string) av_env('APP_URL', 'https://avenpay.co.ke'), PHP_URL_HOST) ?: 'avenpay.co.ke';
        $from = av_env('MAIL_FROM', 'no-reply@' . $host);
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host       = av_env('SMTP_HOST', 'mail.' . $host);
        $m->SMTPAuth   = true;
        $m->Username   = av_env('SMTP_USERNAME', $from);
        $m->Password   = av_env('SMTP_PASSWORD');
        $m->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $m->Port       = (int) av_env('SMTP_PORT', 465);
        $m->CharSet    = 'UTF-8';
        $m->setFrom($from, 'AvenPay');
        $supportEmail = av_env('SUPPORT_EMAIL', 'avenpayenterprises@gmail.com');
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
        $mailError = ($m instanceof \PHPMailer\PHPMailer\PHPMailer && $m->ErrorInfo !== '') ? $m->ErrorInfo : $e->getMessage();
        $error = substr(preg_replace('/[\\r\\n\\t]+/', ' ', (string) $mailError), 0, 1000);
        error_log('AvenPay mail failed: ' . $error);
        return false;
    }
}
