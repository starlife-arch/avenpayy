<?php
require_once __DIR__ . '/../lib/logger.php';
require_once __DIR__ . '/../database_config/env.php';
// Pull in centralized database configuration layer from root
require_once __DIR__ . '/../database_config/db.php'; // Adjust path to point to your actual db.php

// Define Path to Logs Directory
define('LOGS_DIR', av_log_dir());
 
/**
 * Logger Helper Function
 */
function logMessage($filename, $message) {
    if (!is_dir(LOGS_DIR)) {
        mkdir(LOGS_DIR, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[{$timestamp}] " . (is_array($message) || is_object($message) ? json_encode($message) : $message) . PHP_EOL;
    file_put_contents(av_log_path($filename), $formattedMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Native SecureVault Decryption Logic
 */
class SecureVault {
    private static function vaultKey(): string { return (string) av_env('VAULT_KEY'); } // Match your app's secret key
    private const METHOD = 'aes-256-cbc';

    public static function decrypt($data) {
        if (empty($data)) return '';
        $decoded = base64_decode($data, true);
        if ($decoded === false) return trim((string)$data);
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if ($ivLength === false || strlen($decoded) <= $ivLength) {
            return trim((string)$data);
        }
        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);
        $decrypted = openssl_decrypt($ciphertext, self::METHOD, self::vaultKey(), 0, $iv);
        return $decrypted !== false ? trim($decrypted) : trim((string)$data);
    }
}

/**
 * Fetch and decrypt B2C settings directly from daraja_b2c_settings dynamically
 */
function getDarajaB2CSetting($columnName) {
    global $conn; 
    try {
        $stmt = $conn->prepare("SELECT {$columnName} FROM daraja_b2c_settings ORDER BY id ASC LIMIT 1");
        
        if ($conn instanceof PDO) {
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $val = $row[$columnName] ?? null;
        } else {
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $val = $row[$columnName] ?? null;
            $stmt->close();
        }

        // Decrypt if value is encrypted or return plain string
        return SecureVault::decrypt($val) ?: trim((string)$val);
    } catch (Exception $e) {
        logMessage('error.log', "Error fetching B2C setting [{$columnName}]: " . $e->getMessage());
        return null;
    }
}

// Load Daraja configuration constants dynamically from daraja_b2c_settings (passkey removed)
define('CONSUMER_KEY', getDarajaB2CSetting('consumer_key'));
define('CONSUMER_SECRET', getDarajaB2CSetting('consumer_secret'));
define('PAYBILL_NUMBER', getDarajaB2CSetting('shortcode')); 
define('INITIATOR_USERNAME', getDarajaB2CSetting('initiator_name'));
define('INITIATOR_PASSWORD', getDarajaB2CSetting('initiator_password'));

/**
 * Generate OAuth Access Token with error logging
 */
function getAccessToken() {
    $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $credentials = base64_encode(CONSUMER_KEY . ':' . CONSUMER_SECRET);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $credentials],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    logMessage('error.log', "OAuth HTTP Code: {$httpCode} | Response: {$response} | cURL Error: {$curlError}");

    if ($curlError || $httpCode !== 200) {
        return null;
    }

    $result = json_decode($response, true);
    return $result['access_token'] ?? null;
}
?>