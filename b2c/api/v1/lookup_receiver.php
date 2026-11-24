<?php
require_once __DIR__ . '/../../../database_config/env.php';
// b2c/api/v1/lookup_receiver.php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

set_exception_handler(function ($e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'System Exception: ' . $e->getMessage()]);
    exit();
});

$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
if (!file_exists($dbPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Database configuration file missing.']);
    exit();
}
require_once $dbPath;

// 1. SecureVault Decryption Class
class SecureVault {
    private static function vaultKey(): string { return (string) av_env('VAULT_KEY'); } 
    private const METHOD = 'aes-256-cbc';

    public static function decrypt($data) {
        if (empty($data)) return null;
        $decoded = base64_decode($data);
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if (strlen($decoded) < $ivLength) return $data;
        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);
        $decrypted = openssl_decrypt($ciphertext, self::METHOD, self::vaultKey(), 0, $iv);
        return $decrypted !== false ? trim($decrypted) : trim($data);
    }
}

// 2. Parse Input Data (Support $_REQUEST, $_POST, and JSON payloads)
$inputData = $_REQUEST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $inputData = array_merge($inputData, $jsonData);
    }
}

$phone = trim($inputData['phone'] ?? '');

if (empty($phone)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Missing required parameter: phone'
    ]);
    exit();
}

// 3. AUTHENTICATION: Check Headers (X-Wallet-ID & X-Security-Credential) or Fallback to API Key / Session
$headers = getallheaders();
$normalizedHeaders = [];
foreach ($headers as $k => $v) {
    $normalizedHeaders[strtolower($k)] = $v;
}

$apiWalletId = $normalizedHeaders['x-wallet-id'] ?? $inputData['wallet_id'] ?? '';
$apiSecurityKey = $normalizedHeaders['x-security-credential'] ?? $inputData['security_credential'] ?? $inputData['api_key'] ?? '';

$userRes = null;

if (!empty($apiWalletId) && !empty($apiSecurityKey)) {
    // Mode A: Authenticate using X-Wallet-ID and X-Security-Credential
    $stmt = $conn->prepare("SELECT id, wallet_id, b2c_security_credential FROM users WHERE wallet_id = ? LIMIT 1");
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

    $storedCredential = SecureVault::decrypt($userRes['b2c_security_credential'] ?? '');
    if (empty($storedCredential) || (hash_equals($storedCredential, $apiSecurityKey) === false && $storedCredential !== $apiSecurityKey)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid Security Credential API Key.']);
        exit();
    }
} elseif (!empty($apiSecurityKey)) {
    // Mode B: Authenticate using API Key directly against users table
    $stmt = $conn->prepare("SELECT id, wallet_id, b2c_security_credential FROM users WHERE api_key = ? OR reset_token = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("ss", $apiSecurityKey, $apiSecurityKey);
        $stmt->execute();
        $userRes = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$userRes) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid API Key.']);
        exit();
    }
} elseif (isset($_SESSION['user_id'])) {
    // Mode C: Dashboard Session Authentication
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT id, wallet_id, b2c_security_credential FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $userRes = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$userRes) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User record not found.']);
        exit();
    }
} else {
    http_response_code(401);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Unauthorized. Provide X-Wallet-ID and X-Security-Credential headers or api_key.'
    ]);
    exit();
}

// 4. Normalize phone number format (handles 07..., 01..., 254...)
$cleanPhone = preg_replace('/[^0-9]/', '', $phone);
$searchSuffix = $cleanPhone;

if (str_starts_with($cleanPhone, '0')) {
    $searchSuffix = '254' . substr($cleanPhone, 1);
} elseif (str_starts_with($cleanPhone, '254')) {
    $searchSuffix = $cleanPhone;
}

$localFormat = '0' . substr($searchSuffix, -9);
$internationalFormat = '254' . substr($searchSuffix, -9);
$likePattern = '%' . substr($cleanPhone, -9);

// 5. Query mpesa_b2c_transactions for the verified receiver name
$lookupStmt = $conn->prepare("
    SELECT receiver_name 
    FROM mpesa_b2c_transactions 
    WHERE (receiver_phone = ? OR receiver_phone = ? OR receiver_phone = ? OR receiver_phone LIKE ?) 
      AND result_code = 0 
      AND receiver_name IS NOT NULL 
      AND TRIM(receiver_name) != ''
    ORDER BY created_at DESC 
    LIMIT 1
");

if ($lookupStmt) {
    $lookupStmt->bind_param("ssss", $internationalFormat, $localFormat, $searchSuffix, $likePattern);
    $lookupStmt->execute();
    $result = $lookupStmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'status' => 'found',
            'phone' => $phone,
            'receiver_name' => ucwords(strtolower($row['receiver_name']))
        ]);
        exit();
    }
    $lookupStmt->close();
}

echo json_encode([
    'status' => 'not_found',
    'phone' => $phone,
    'message' => 'No prior successful payout record found for this number.'
]);
exit();