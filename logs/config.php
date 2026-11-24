<?php
require_once __DIR__ . '/../lib/logger.php';
require_once __DIR__ . '/../database_config/env.php';
// Pull in centralized database configuration layer from root
require_once __DIR__ . '/../database_config/db.php';

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
        if (empty($data)) return null;
        $data = base64_decode($data);
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if (strlen($data) < $ivLength) {
            return '';
        }
        $iv = substr($data, 0, $ivLength);
        $ciphertext = substr($data, $ivLength);
        return openssl_decrypt($ciphertext, self::METHOD, self::vaultKey(), 0, $iv);
    }
}

/**
 * Fetch and decrypt Daraja settings using your $conn connection
 */
function getDarajaSetting($keyName) {
    global $conn; 
    try {
        $stmt = $conn->prepare("SELECT encrypted_value FROM daraja_settings WHERE key_name = ? LIMIT 1");
        
        if ($conn instanceof PDO) {
            $stmt->execute([$keyName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $val = $row['encrypted_value'] ?? null;
        } else {
            $stmt->bind_param("s", $keyName);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $val = $row['encrypted_value'] ?? null;
            $stmt->close();
        }

        return trim(SecureVault::decrypt($val));
    } catch (Exception $e) {
        logMessage('error.log', "Error fetching setting [{$keyName}]: " . $e->getMessage());
        return null;
    }
}

// Load Daraja configuration constants dynamically via SecureVault decryption
define('CONSUMER_KEY', getDarajaSetting('consumer_key'));
define('CONSUMER_SECRET', getDarajaSetting('consumer_secret'));
define('PAYBILL_NUMBER', getDarajaSetting('paybill')); // Resolves '4022787'
define('PASSKEY', getDarajaSetting('passkey'));
define('INITIATOR_USERNAME', getDarajaSetting('initiator_username'));
define('INITIATOR_PASSWORD', getDarajaSetting('initiator_password'));

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