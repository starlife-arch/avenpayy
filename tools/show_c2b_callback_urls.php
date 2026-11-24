<?php
// CLI-only: print the authenticated C2B callback URLs for Safaricom registration.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require_once __DIR__ . '/../lib/callback_auth.php';

$appUrl = rtrim((string) av_env('APP_URL', ''), '/');
if ($appUrl !== 'https://avenpay.co.ke') {
    fwrite(STDERR, "Set APP_URL=https://avenpay.co.ke in /home/avenpayc/avenpay.env first.\n");
    exit(1);
}
if (av_callback_token('c2b') === '') {
    fwrite(STDERR, "CALLBACK_SECRET is missing from the private AvenPay environment file.\n");
    exit(1);
}

echo "ValidationURL: " . av_callback_url('/logs/validation.php', 'c2b') . PHP_EOL;
echo "ConfirmationURL: " . av_callback_url('/logs/confirmation.php', 'c2b') . PHP_EOL;
