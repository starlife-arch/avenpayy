<?php
require_once __DIR__ . '/../../lib/callback_auth.php';
require_once __DIR__ . '/../../database_config/env.php';
require_once __DIR__ . '/../../lib/daraja_environment.php';
// b2c/topup/stk_push.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Ensure all errors return clean JSON
set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'System Exception: ' . $e->getMessage()]);
    exit();
});

$dbPath = __DIR__ . '/../../database_config/db.php';
if (!file_exists($dbPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Database configuration file missing.']);
    exit();
}
require_once $dbPath;

// 1. SecureVault Decryption Class
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

// 2. Parse Input Data (Support both $_POST and JSON input payloads)
$inputData = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $inputData = array_merge($inputData, $jsonData);
    }
}

$user_id = null;
$wallet_id = '';
$userRes = null;

// 3. AUTHENTICATION: Check Session OR API Headers (X-Wallet-ID & X-Security-Credential)
$headers = getallheaders();
// Normalize header keys for case-insensitive lookup
$normalizedHeaders = [];
foreach ($headers as $k => $v) {
    $normalizedHeaders[strtolower($k)] = $v;
}

$apiWalletId = $normalizedHeaders['x-wallet-id'] ?? $inputData['wallet_id'] ?? '';
$apiSecurityKey = $normalizedHeaders['x-security-credential'] ?? $inputData['security_credential'] ?? '';

if (!empty($apiWalletId) && !empty($apiSecurityKey)) {
    // API / cURL Mode: Authenticate using Wallet ID and Security Credential API Key
    $stmt = $conn->prepare("SELECT id, wallet_id, use_custom_mpesa, b2c_security_credential FROM users WHERE wallet_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $apiWalletId);
        $stmt->execute();
        $userRes = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$userRes) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid Wallet ID.']);
        exit();
    }

    // Decrypt stored credential to compare with incoming API key
    $storedCredential = SecureVault::decrypt($userRes['b2c_security_credential'] ?? '');
    if (empty($storedCredential) || (hash_equals($storedCredential, $apiSecurityKey) === false && $storedCredential !== $apiSecurityKey)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid Security Credential API Key.']);
        exit();
    }

    $user_id = $userRes['id'];
    $wallet_id = $userRes['wallet_id'];

} elseif (isset($_SESSION['user_id'])) {
    // Dashboard Web Session Mode
    $user_id = $_SESSION['user_id'];
    $userStmt = $conn->prepare("SELECT id, wallet_id, use_custom_mpesa, b2c_security_credential FROM users WHERE id = ? LIMIT 1");
    if ($userStmt) {
        $userStmt->bind_param("i", $user_id);
        $userStmt->execute();
        $userRes = $userStmt->get_result()->fetch_assoc();
        $userStmt->close();
    }

    if (!$userRes) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User record not found.']);
        exit();
    }
    $wallet_id = trim($userRes['wallet_id'] ?? '');
} else {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Provide valid session or X-Wallet-ID and X-Security-Credential headers.']);
    exit();
}

if (empty($wallet_id)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'User wallet ID not found or unassigned.']);
    exit();
}

$phoneInput = trim($inputData['phone'] ?? '');
$amount = floatval($inputData['amount'] ?? 0);

if (empty($phoneInput) || $amount < 10) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone number or amount (Minimum KES 10).']);
    exit();
}

// 4. Format phone number to 2547XXXXXXXX
$phone = preg_replace('/[^0-9]/', '', $phoneInput);
if (substr($phone, 0, 1) === '0') {
    $phone = '254' . substr($phone, 1);
} elseif (substr($phone, 0, 3) !== '254') {
    $phone = '254' . $phone;
}

// 5. Resolve Daraja Credentials
$consumerKey = '';
$consumerSecret = '';
$passkey = '';
$paybillShortcode = '4022787';

$useCustom = (int)($userRes['use_custom_mpesa'] ?? 0);
if ($useCustom === 1) {
    $cStmt = $conn->prepare("SELECT key_name, encrypted_value FROM user_daraja_settings WHERE user_id = ?");
    $cStmt->bind_param("i", $user_id);
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    while ($row = $cRes->fetch_assoc()) {
        $k = $row['key_name'];
        $val = SecureVault::decrypt($row['encrypted_value']);
        if ($k === 'consumer_key') $consumerKey = $val;
        if ($k === 'consumer_secret') $consumerSecret = $val;
        if ($k === 'passkey') $passkey = $val;
        if ($k === 'paybill' && !empty($val)) $paybillShortcode = $val;
    }
    $cStmt->close();
}

if (empty($consumerKey) || empty($consumerSecret) || empty($passkey) || empty($paybillShortcode)) {
    $gRes = $conn->query("SELECT key_name, encrypted_value FROM daraja_settings");
    if ($gRes) {
        while ($row = $gRes->fetch_assoc()) {
            $k = $row['key_name'];
            $val = SecureVault::decrypt($row['encrypted_value']);
            if ($k === 'consumer_key') $consumerKey = $val;
            if ($k === 'consumer_secret') $consumerSecret = $val;
            if ($k === 'passkey') $passkey = $val;
            if ($k === 'paybill' && !empty($val)) $paybillShortcode = $val;
        }
    }
}

if (empty($consumerKey) || empty($consumerSecret)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'C2B Daraja settings are incomplete. Save the consumer key, consumer secret, paybill shortcode and passkey in the AvenPay C2B gateway settings.']);
    exit();
}

if (av_callback_token('topup') === '') {
    error_log('AvenPay wallet top-up blocked: CALLBACK_SECRET is not configured.');
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'Wallet top-ups are temporarily unavailable because the secure callback configuration is incomplete.']);
    exit();
}

if (empty($passkey) || empty($paybillShortcode)) {
    http_response_code(503);
    echo json_encode(['status' => 'error', 'message' => 'C2B Paybill shortcode and passkey must be saved before wallet top-ups can be started.']);
    exit();
}

$baseUrl = av_daraja_base_url();

// 6. Generate Production Access Token
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
$ch = curl_init($baseUrl . '/oauth/v1/generate?grant_type=client_credentials');
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $credentials],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 25,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$authHttpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    error_log('AvenPay wallet top-up Daraja OAuth transport failure: ' . $curlError);
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'Safaricom authentication service could not be reached. Please retry shortly.']);
    exit();
}

$authData = json_decode($response, true);
$accessToken = is_array($authData) ? ($authData['access_token'] ?? null) : null;

if (!$accessToken) {
    $providerMessage = is_array($authData)
        ? (string) ($authData['errorMessage'] ?? $authData['error_description'] ?? $authData['error'] ?? '')
        : '';
    error_log('AvenPay wallet top-up Daraja OAuth rejected: HTTP ' . $authHttpCode . '; response=' . substr((string) $response, 0, 400));
    http_response_code(502);
    $safeMessage = $providerMessage !== ''
        ? 'Safaricom rejected the configured credentials: ' . $providerMessage
        : 'Safaricom returned HTTP ' . $authHttpCode . ' without an access token. Check that the consumer key and secret match the selected Daraja environment.';
    echo json_encode(['status' => 'error', 'message' => $safeMessage]);
    exit();
}

// 7. Execute STK Push Request
$timestamp = date('YmdHis');
$password = base64_encode($paybillShortcode . $passkey . $timestamp);

$stkData = [
    'BusinessShortCode' => $paybillShortcode,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => (int)$amount,
    'PartyA' => $phone,
    'PartyB' => $paybillShortcode,
    'PhoneNumber' => $phone,
    'CallBackURL' => av_callback_url('/b2c/topup/callback.php', 'topup'),
    'AccountReference' => $wallet_id,
    'TransactionDesc' => 'Wallet Top-up'
];

$ch2 = curl_init($baseUrl . '/mpesa/stkpush/v1/processrequest');
curl_setopt_array($ch2, [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json'],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($stkData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);
$stkResponse = curl_exec($ch2);
$stkCurlError = curl_error($ch2);
$stkHttpCode = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);
if ($stkResponse === false) {
    error_log('AvenPay wallet top-up STK transport failure: ' . $stkCurlError);
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'Safaricom STK service could not be reached. Please retry shortly.']);
    exit();
}
$stkResData = json_decode($stkResponse, true);
if (!is_array($stkResData)) {
    error_log('AvenPay wallet top-up STK returned invalid JSON: HTTP ' . $stkHttpCode);
    http_response_code(502);
    echo json_encode(['status' => 'error', 'message' => 'Safaricom returned an unreadable STK response. Please retry shortly.']);
    exit();
}

if (isset($stkResData['ResponseCode']) && $stkResData['ResponseCode'] === '0') {
    $checkoutId = $stkResData['CheckoutRequestID'];
    $merchantRequestId = $stkResData['MerchantRequestID'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO b2c_topup_transactions (user_id, wallet_id, checkout_request_id, merchant_request_id, phone_number, amount, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    if ($stmt) {
        $stmt->bind_param("issssd", $user_id, $wallet_id, $checkoutId, $merchantRequestId, $phone, $amount);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode([
        'status' => 'success', 
        'checkout_id' => $checkoutId, 
        'message' => 'STK push sent. Check your phone.'
    ]);
} else {
    $errorMsg = $stkResData['errorMessage'] ?? $stkResData['ResponseDescription'] ?? 'STK Push Initialization Failed';
    $failCheckoutId = 'FAIL_' . uniqid();
    
    $stmtFail = $conn->prepare("
        INSERT INTO b2c_topup_transactions (user_id, wallet_id, checkout_request_id, phone_number, amount, status, receipt_number, created_at)
        VALUES (?, ?, ?, ?, ?, 'failed', ?, NOW())
    ");
    if ($stmtFail) {
        $stmtFail->bind_param("isssds", $user_id, $wallet_id, $failCheckoutId, $phone, $amount, $errorMsg);
        $stmtFail->execute();
        $stmtFail->close();
    }

    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
}
exit();