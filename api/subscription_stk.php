<?php
require_once __DIR__ . '/../lib/callback_auth.php';
require_once __DIR__ . '/../database_config/env.php';
require_once __DIR__ . '/../lib/daraja_environment.php';
// /api/subscription_stk.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
if (!file_exists($dbPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Database configuration file missing.']);
    exit();
}
require_once $dbPath;

class SecureVault
{
    private const METHOD = 'aes-256-cbc';

    /** AvenPay uses only its own private VAULT_KEY. No PrintPay fallback. */
    private static function vaultKeys(): array
    {
        $key = trim((string) av_env('VAULT_KEY'));
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $key)) {
            throw new RuntimeException('VAULT_KEY must be a 64-character hexadecimal AvenPay key.');
        }
        return [hex2bin($key)];
    }

    private static function encryptionKey(): string
    {
        $keys = self::vaultKeys();
        if (!$keys) {
            throw new RuntimeException('No Daraja encryption key is configured.');
        }
        return $keys[0];
    }

    public static function encrypt(string $plainText): string
    {
        if ($plainText === '') return '';

        $ivLength = openssl_cipher_iv_length(self::METHOD);
        $iv = random_bytes($ivLength);
        $ciphertext = openssl_encrypt(
            $plainText,
            self::METHOD,
            self::encryptionKey(),
            0,
            $iv
        );
        if ($ciphertext === false) {
            throw new RuntimeException('Daraja credential encryption failed.');
        }
        return base64_encode($iv . $ciphertext);
    }

    public static function decrypt($data)
    {
        if (!is_string($data) || $data === '') return null;
        $decoded = base64_decode($data, true);
        if ($decoded === false) return null;

        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if (strlen($decoded) <= $ivLength) return null;

        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);

        foreach (self::vaultKeys() as $key) {
            $plainText = openssl_decrypt($ciphertext, self::METHOD, $key, 0, $iv);
            if ($plainText !== false) return trim($plainText);
        }
        return null;
    }
}

// Force user_id to 1 (using session as fallback if available)
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
$settlement_id = (int)($_POST['id'] ?? 0);
$phoneInput = trim($_POST['phone'] ?? '');
$plan = strtolower(trim($_POST['plan'] ?? 'monthly'));
$customMonths = (int)($_POST['custom_months'] ?? 1);

if ($settlement_id <= 0 || empty($phoneInput)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required settlement ID or phone number.']);
    exit();
}

// Fetch settlement record
$stmt = $conn->prepare("SELECT * FROM settlements WHERE id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $settlement_id, $user_id);
$stmt->execute();
$settlement = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$settlement) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Settlement account not found for user ID 1.']);
    exit();
}

// Determine Pricing & Duration
$months = 1;
$amount = 350;

if ($plan === '3 months') {
    $months = 3;
    $amount = 950;
} elseif ($plan === 'yearly' || $plan === '1 year') {
    $months = 12;
    $amount = 3500;
} elseif ($plan === 'custom') {
    $months = max(1, $customMonths);
    $amount = $months * 350;
}

// Determine Description: Active = Renewal Plan, Inactive/Expired = Subscription Plan
$now = time();
$isActive = (int)($settlement['is_active'] ?? 0);
$status = strtolower(trim($settlement['status'] ?? ''));
$expiryDate = $settlement['expiry_date'] ?? '';

$isCurrentlyActive = ($isActive === 1 && $status !== 'expired' && !empty($expiryDate) && strtotime($expiryDate) > $now);
$transactionDesc = $isCurrentlyActive ? 'API RENEWAL PLAN' : 'API SUBSCRIPTION PLAN';

// Format Phone Number (2547XXXXXXXX)
$phone = preg_replace('/[^0-9]/', '', $phoneInput);
if (strlen($phone) == 10) $phone = '254' . substr($phone, 1);
elseif (strlen($phone) == 9) $phone = '254' . $phone;

// Resolve Daraja Credentials for user_id = 1 (Default Paybill: 4022787)
$consumerKey = ''; $consumerSecret = ''; $passkey = ''; $paybill = '4022787';

$uStmt = $conn->prepare("SELECT use_custom_mpesa FROM users WHERE id = ?");
$uStmt->bind_param("i", $user_id);
$uStmt->execute();
$userRes = $uStmt->get_result()->fetch_assoc();
$uStmt->close();

if ($userRes && !empty($userRes['use_custom_mpesa'])) {
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
        if ($k === 'paybill' && !empty($val)) $paybill = $val;
    }
    $cStmt->close();
}

if (empty($consumerKey) || empty($consumerSecret) || empty($passkey) || empty($paybill)) {
    $gRes = $conn->query("SELECT key_name, encrypted_value FROM daraja_settings");
    if ($gRes) {
        while ($row = $gRes->fetch_assoc()) {
            $k = $row['key_name'];
            $val = SecureVault::decrypt($row['encrypted_value']);
            if ($k === 'consumer_key') $consumerKey = $val;
            if ($k === 'consumer_secret') $consumerSecret = $val;
            if ($k === 'passkey') $passkey = $val;
            if ($k === 'paybill' && !empty($val)) $paybill = $val;
        }
    }
}

if (empty($consumerKey) || empty($consumerSecret)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Daraja gateway credentials not configured.']);
    exit();
}

// Generate Access Token
$credentials = base64_encode($consumerKey . ':' . $consumerSecret);
$ch = curl_init(av_daraja_oauth_url());
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$authRes = json_decode(curl_exec($ch), true);
curl_close($ch);

$accessToken = $authRes['access_token'] ?? null;
if (!$accessToken) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to generate Safaricom access token.']);
    exit();
}

// Execute STK Push (Settling to Paybill 4022787)
$timestamp = date('YmdHis');
$password = base64_encode($paybill . $passkey . $timestamp);

$stkData = [
    'BusinessShortCode' => $paybill,
    'Password' => $password,
    'Timestamp' => $timestamp,
    'TransactionType' => 'CustomerPayBillOnline',
    'Amount' => $amount,
    'PartyA' => $phone,
    'PartyB' => $paybill,
    'PhoneNumber' => $phone,
    'CallBackURL' => av_callback_url('/api/subscription_callback.php', 'sub'),
    'AccountReference' => $transactionDesc, 
    'TransactionDesc' => $transactionDesc
];

$ch2 = curl_init(av_daraja_endpoint('mpesa/stkpush/v1/processrequest'));
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']);
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($stkData));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch2, CURLOPT_TIMEOUT, 25);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch2);
curl_close($ch2);

$res = json_decode($response, true);

if (isset($res['CheckoutRequestID'])) {
    $checkoutId = $res['CheckoutRequestID'];
    $merchantRequestId = $res['MerchantRequestID'] ?? null;

    $stmt = $conn->prepare("
        INSERT INTO mpesa_transactions (user_id, checkout_request_id, merchant_request_id, phone_number, amount, settlement_id, transaction_desc, status, processed) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING', 0) 
        ON DUPLICATE KEY UPDATE status = 'PENDING', amount = VALUES(amount), settlement_id = VALUES(settlement_id), transaction_desc = VALUES(transaction_desc)
    ");
    if ($stmt) {
        $stmt->bind_param("isssdis", $user_id, $checkoutId, $merchantRequestId, $phone, $amount, $settlement_id, $transactionDesc);
        $stmt->execute();
        $stmt->close();
    }

    echo json_encode(['status' => 'success', 'checkout_id' => $checkoutId, 'months' => $months]);
} else {
    $errorMsg = $res['errorMessage'] ?? $res['ResponseDescription'] ?? 'STK Push Initialization Failed';
    echo json_encode(['status' => 'error', 'message' => $errorMsg]);
}
exit();