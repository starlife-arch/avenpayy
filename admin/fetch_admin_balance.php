<?php
require_once __DIR__ . '/../lib/callback_auth.php';
require_once __DIR__ . '/../database_config/env.php';
// /admin/fetch_admin_balance.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection unavailable'
    ]);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

// --------------------------------------------------
// 1. SECURE VAULT DECRYPTION
// --------------------------------------------------
if (!class_exists('SecureVault')) {
    class SecureVault
    {
        private static function vaultKey(): string { return (string) av_env('VAULT_KEY'); }
        private const METHOD = 'aes-256-cbc';

        public static function decrypt($data)
        {
            if (empty($data)) {
                return null;
            }

            $decoded = base64_decode($data, true);

            if ($decoded === false) {
                return trim($data);
            }

            $ivLength = openssl_cipher_iv_length(self::METHOD);

            if ($ivLength === false || strlen($decoded) <= $ivLength) {
                return trim($data);
            }

            $iv = substr($decoded, 0, $ivLength);
            $ciphertext = substr($decoded, $ivLength);

            $decrypted = openssl_decrypt(
                $ciphertext,
                self::METHOD,
                self::vaultKey(),
                0,
                $iv
            );

            return $decrypted === false ? trim($data) : trim($decrypted);
        }
    }
}

// --------------------------------------------------
// 2. LOAD DARAJA CONFIGURATION FROM daraja_b2c_settings
// --------------------------------------------------
function getProductionDarajaConfig($conn)
{
    $stmt = $conn->prepare("SELECT shortcode, initiator_name, consumer_key, consumer_secret, initiator_password FROM daraja_b2c_settings ORDER BY id ASC LIMIT 1");
    $stmt->execute();
    $b2cConfig = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$b2cConfig) {
        throw new Exception('Daraja B2C settings are missing from daraja_b2c_settings.');
    }

    $settings = array(
        'paybill'             => trim($b2cConfig['shortcode']),
        'initiator_username'  => trim($b2cConfig['initiator_name']),
        'consumer_key'        => trim($b2cConfig['consumer_key']),
        'consumer_secret'     => trim($b2cConfig['consumer_secret']),
        'initiator_password'  => trim($b2cConfig['initiator_password'])
    );

    // Decrypt credentials if stored encrypted
    $settings['consumer_key']       = SecureVault::decrypt($settings['consumer_key']) ?: $settings['consumer_key'];
    $settings['consumer_secret']    = SecureVault::decrypt($settings['consumer_secret']) ?: $settings['consumer_secret'];
    $settings['initiator_password'] = SecureVault::decrypt($settings['initiator_password']) ?: $settings['initiator_password'];

    $requiredKeys = array(
        'consumer_key',
        'consumer_secret',
        'initiator_username',
        'initiator_password',
        'paybill'
    );

    foreach ($requiredKeys as $key) {
        if (empty($settings[$key])) {
            throw new Exception(
                'Missing or undecryptable Daraja configuration in daraja_b2c_settings: ' . $key
            );
        }
    }

    return $settings;
}

// --------------------------------------------------
// 3. GENERATE PRODUCTION ACCESS TOKEN
// --------------------------------------------------
function getProductionAccessToken($cfg)
{
    $url = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';

    $ch = curl_init($url);

    if ($ch === false) {
        throw new Exception('Unable to initialize OAuth request.');
    }

    curl_setopt_array($ch, array(
        CURLOPT_HTTPHEADER => array(
            'Authorization: Basic ' . base64_encode(
                $cfg['consumer_key'] . ':' . $cfg['consumer_secret']
            ),
            'Accept: application/json'
        ),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ));

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false) {
        throw new Exception('OAuth connection failed: ' . $curlError);
    }

    $result = json_decode($response, true);

    if (
        $httpCode < 200 ||
        $httpCode >= 300 ||
        empty($result['access_token'])
    ) {
        throw new Exception(
            'Safaricom OAuth failed. HTTP status: ' . $httpCode
            . '. Response: ' . substr($response, 0, 500)
        );
    }

    return $result['access_token'];
}

// --------------------------------------------------
// 4. QUERY M-PESA ACCOUNT BALANCE (TEST API ENGINE)
// --------------------------------------------------
function checkMpesaUtilityBalanceLive($conn)
{
    $cfg = getProductionDarajaConfig($conn);
    $token = getProductionAccessToken($cfg);

    $certPath = $_SERVER['DOCUMENT_ROOT'] . '/b2c/ProductionCertificate.cer';

    if (!is_file($certPath) || !is_readable($certPath)) {
        $certPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/production_cert.cer';
    }

    if (!is_file($certPath) || !is_readable($certPath)) {
        throw new Exception('Production certificate is missing or unreadable.');
    }

    $certificate = file_get_contents($certPath);

    if ($certificate === false || trim($certificate) === '') {
        throw new Exception('Production certificate is empty or cannot be read.');
    }

    $certificateKey = openssl_pkey_get_public($certificate);

    if ($certificateKey === false) {
        throw new Exception('Unable to load public key from production certificate.');
    }

    $encryptedData = '';

    $encrypted = openssl_public_encrypt(
        $cfg['initiator_password'],
        $encryptedData,
        $certificateKey,
        OPENSSL_PKCS1_PADDING
    );

    if (function_exists('openssl_free_key')) {
        openssl_free_key($certificateKey);
    }

    if (!$encrypted) {
        throw new Exception('Failed to encrypt initiator password. Verify the production certificate.');
    }

    $encryptedSecurityCredential = base64_encode($encryptedData);

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];

    $payload = array(
        'Initiator' => $cfg['initiator_username'],
        'SecurityCredential' => $encryptedSecurityCredential,
        'CommandID' => 'AccountBalance',
        'PartyA' => $cfg['paybill'],
        'IdentifierType' => '4',
        'Remarks' => 'AvenPay Admin Sync Balance',
        'QueueTimeOutURL' => av_callback_url('/b2c/balance_timeout.php', 'bal_timeout'),
        'ResultURL' => av_callback_url('/b2c/balance_result.php', 'bal_result')
    );

    $url = 'https://api.safaricom.co.ke/mpesa/accountbalance/v1/query';

    $jsonPayload = json_encode($payload);

    if ($jsonPayload === false) {
        throw new Exception('Unable to encode balance request.');
    }

    $ch = curl_init($url);

    if ($ch === false) {
        throw new Exception('Unable to initialize balance request.');
    }

    curl_setopt_array($ch, array(
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ),
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ));

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($response === false) {
        throw new Exception('Balance API connection failed: ' . $curlError);
    }

    $decodedResponse = json_decode($response, true);

    return array(
        'http_code' => $httpCode,
        'response' => (
            json_last_error() === JSON_ERROR_NONE
                ? $decodedResponse
                : $response
        )
    );
}

// --------------------------------------------------
// HANDLE ?action=sync REQUEST
// --------------------------------------------------
$syncDispatched = false;
$syncError = null;

if (isset($_GET['action']) && $_GET['action'] === 'sync') {
    try {
        $apiResult = checkMpesaUtilityBalanceLive($conn);
        $syncDispatched = true;
        // Wait up to 1.5 seconds giving time for fast local/remote result handlers to log into the DB
        usleep(1500000); 
    } catch (Throwable $e) {
        $syncError = $e->getMessage();
        error_log('Admin fetch balance sync error: ' . $syncError);
    }
}

// --------------------------------------------------
// PARSE LATEST CALLBACK DATA FROM mpesa_balance_callbacks
// --------------------------------------------------
$wallets = [
    'utility' => null,
    'working' => null,
    'charges' => null,
    'merchant' => null,
    'airtime' => null,
    'settlement' => null
];

$updatedAt = null;
$source = null;
$hasBalance = false;

$sql = "
    SELECT account_balance, created_at 
    FROM mpesa_balance_callbacks 
    WHERE account_balance IS NOT NULL AND trim(account_balance) != ''
    ORDER BY id DESC 
    LIMIT 1
";

$result = $conn->query($sql);

if ($result && ($row = $result->fetch_assoc())) {
    $rawBalanceString = $row['account_balance'];
    $updatedAt = $row['created_at'];
    $source = 'mpesa_balance_callbacks';
    $hasBalance = true;

    $accounts = explode('&', $rawBalanceString);
    foreach ($accounts as $accountBlock) {
        $parts = explode('|', $accountBlock);
        if (count($parts) >= 2) {
            $accountType = trim($parts[0]);
            
            $amount = 0.00;
            for ($p = 1; $p < count($parts); $p++) {
                if (is_numeric($parts[$p]) && floatval($parts[$p]) >= 0) {
                    $amount = (float) $parts[$p];
                    break;
                }
            }

            if (stripos($accountType, 'Utility') !== false) {
                $wallets['utility'] = $amount;
            } elseif (stripos($accountType, 'Working') !== false) {
                $wallets['working'] = $amount;
            } elseif (stripos($accountType, 'Charges') !== false) {
                $wallets['charges'] = $amount;
            } elseif (stripos($accountType, 'Merchant') !== false) {
                $wallets['merchant'] = $amount;
            } elseif (stripos($accountType, 'Airtime') !== false) {
                $wallets['airtime'] = $amount;
            } elseif (stripos($accountType, 'Settlement') !== false) {
                $wallets['settlement'] = $amount;
            }
        }
    }
}

if ($result instanceof mysqli_result) {
    $result->free();
}

echo json_encode([
    'status' => 'success',
    'sync_dispatched' => $syncDispatched,
    'sync_error' => $syncError,
    'has_balance' => $hasBalance,
    'source' => $source,
    'updated_at' => $updatedAt,
    'utility' => $wallets['utility'],
    'working' => $wallets['working'],
    'charges' => $wallets['charges'],
    'merchant' => $wallets['merchant'],
    'airtime' => $wallets['airtime'],
    'settlement' => $wallets['settlement']
], JSON_UNESCAPED_SLASHES);

exit;