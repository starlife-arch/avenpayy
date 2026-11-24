<?php
// CLI-only: register AvenPay's manual Paybill C2B validation and confirmation URLs with Safaricom.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require_once __DIR__ . '/../database_config/db.php';
require_once __DIR__ . '/../database_config/env.php';
require_once __DIR__ . '/../lib/callback_auth.php';
require_once __DIR__ . '/../lib/daraja_environment.php';

$key = trim((string) av_env('VAULT_KEY'));
if (!preg_match('/^[a-fA-F0-9]{64}$/', $key)) {
    fwrite(STDERR, "A valid AvenPay VAULT_KEY is not configured.\n");
    exit(1);
}
$binaryKey = hex2bin($key);
$method = 'aes-256-cbc';
$settings = [];
$result = $conn->query("SELECT key_name, encrypted_value FROM daraja_settings");
while ($row = $result->fetch_assoc()) {
    $decoded = base64_decode((string) $row['encrypted_value'], true);
    if ($decoded === false || strlen($decoded) <= openssl_cipher_iv_length($method)) continue;
    $ivLength = openssl_cipher_iv_length($method);
    $plain = openssl_decrypt(substr($decoded, $ivLength), $method, $binaryKey, 0, substr($decoded, 0, $ivLength));
    if ($plain !== false) $settings[$row['key_name']] = trim($plain);
}

foreach (['consumer_key', 'consumer_secret', 'paybill'] as $required) {
    if (empty($settings[$required])) {
        fwrite(STDERR, "Missing encrypted C2B setting: {$required}. Save C2B gateway settings in AvenPay admin first.\n");
        exit(1);
    }
}
if (av_callback_token('c2b') === '') {
    fwrite(STDERR, "CALLBACK_SECRET is missing from the private AvenPay environment file.\n");
    exit(1);
}
$appUrl = rtrim((string) av_env('APP_URL', ''), '/');
if ($appUrl !== 'https://avenpay.co.ke') {
    fwrite(STDERR, "Set APP_URL=https://avenpay.co.ke in /home/avenpayc/avenpay.env first.\n");
    exit(1);
}

$baseUrl = av_daraja_base_url();
$auth = curl_init($baseUrl . '/oauth/v1/generate?grant_type=client_credentials');
curl_setopt_array($auth, [
    CURLOPT_HTTPHEADER => ['Authorization: Basic ' . base64_encode($settings['consumer_key'] . ':' . $settings['consumer_secret'])],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);
$authBody = curl_exec($auth);
$authCode = (int) curl_getinfo($auth, CURLINFO_HTTP_CODE);
$authError = curl_error($auth);
curl_close($auth);
$authData = is_string($authBody) ? json_decode($authBody, true) : null;
$accessToken = is_array($authData) ? ($authData['access_token'] ?? '') : '';
if ($accessToken === '') {
    fwrite(STDERR, "Safaricom OAuth failed (HTTP {$authCode}). " . ($authError !== '' ? $authError : 'Check that the saved C2B credentials match DARAJA_ENV.') . "\n");
    exit(2);
}

$payload = [
    'ShortCode' => $settings['paybill'],
    'ResponseType' => 'Cancelled',
    'ConfirmationURL' => av_callback_url('/logs/confirmation.php', 'c2b'),
    'ValidationURL' => av_callback_url('/logs/validation.php', 'c2b')
];
$ch = curl_init($baseUrl . '/mpesa/c2b/v1/registerurl');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);
$body = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($body === false) {
    fwrite(STDERR, "C2B URL registration request failed (HTTP {$httpCode}): {$error}\n");
    exit(3);
}
echo "Safaricom HTTP {$httpCode}\n";
echo (string) $body . PHP_EOL;
