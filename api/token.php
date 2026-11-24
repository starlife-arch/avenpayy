<?php
require_once __DIR__ . '/../database_config/env.php';
require_once __DIR__ . '/../lib/daraja_environment.php';
// api/token.php

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

class SecureVault {
    private static function vaultKey(): string { return (string) av_env('VAULT_KEY'); } 
    private const METHOD = 'aes-256-cbc';
    public static function decrypt($data) {
        if (empty($data)) return null;
        $data = base64_decode($data);
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if ($data === false || strlen($data) <= $ivLength) return null;
        $iv = substr($data, 0, $ivLength);
        $ciphertext = substr($data, $ivLength);
        return openssl_decrypt($ciphertext, self::METHOD, self::vaultKey(), 0, $iv);
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