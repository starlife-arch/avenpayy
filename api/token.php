<?php
require_once __DIR__ . '/../database_config/env.php';
require_once __DIR__ . '/../lib/daraja_environment.php';
// api/token.php

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

class SecureVault {
    private const METHOD = 'aes-256-cbc';
    private static function encryptionKey(): string {
        $key = trim((string) av_env('VAULT_KEY'));
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $key)) {
            throw new RuntimeException('A valid AvenPay VAULT_KEY is not configured.');
        }
        return hex2bin($key);
    }
    public static function decrypt($data) {
        if (!is_string($data) || $data === '') return null;
        $decoded = base64_decode($data, true);
        if ($decoded === false) return null;
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if (strlen($decoded) <= $ivLength) return null;
        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);
        $plainText = openssl_decrypt($ciphertext, self::METHOD, self::encryptionKey(), 0, $iv);
        return $plainText !== false ? trim($plainText) : null;
    }
}

$res = $conn->query("SELECT key_name, encrypted_value FROM daraja_settings");
$cfg = [];
while ($row = $res->fetch_assoc()) {
    $cfg[$row['key_name']] = trim((string) SecureVault::decrypt($row['encrypted_value']));
}

if (empty($cfg['consumer_key']) || empty($cfg['consumer_secret'])) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Consumer credentials missing.']);
    exit;
}

$credentials = base64_encode($cfg['consumer_key'] . ':' . $cfg['consumer_secret']);
$ch = curl_init(av_daraja_oauth_url());
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode >= 200 && $httpCode < 300 && isset($result['access_token'])) {
    echo json_encode([
        'status' => 'success',
        'access_token' => $result['access_token']
    ]);
} else {
    http_response_code(502);
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to generate token from Safaricom.',
        'raw' => $response
    ]);
}
exit();