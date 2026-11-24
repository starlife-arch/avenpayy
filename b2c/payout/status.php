<?php
require_once __DIR__ . '/../../database_config/env.php';
// b2c/payout/status.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

// 1. Safe Database Connection Loader
$db_paths = [
    __DIR__ . '/../../database_config/db.php',
    '../../database_config/db.php',
    $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php',
    'database_config/db.php'
];

$db_loaded = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded || !isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection unavailable']);
    exit();
}

// 2. SecureVault Decryption Class
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

// 3. Parse Input Data (Support both $_POST and JSON payloads)
$inputData = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $inputData = array_merge($inputData, $jsonData);
    }
}

// 4. Extract Headers for Authentication
$headers = getallheaders();
$normalizedHeaders = [];
foreach ($headers as $k => $v) {
    $normalizedHeaders[strtolower($k)] = $v;
}

$walletId = $normalizedHeaders['x-wallet-id'] ?? $inputData['wallet_id'] ?? '';
$securityCredential = $normalizedHeaders['x-security-credential'] ?? $inputData['security_credential'] ?? '';

if (empty($walletId) || empty($securityCredential)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Missing authentication headers (X-Wallet-ID and X-Security-Credential required)']);
    exit();
}

// 5. Verify User / Merchant Credentials using correct column 'wallet_id'
$authStmt = $conn->prepare("SELECT id, b2c_security_credential FROM users WHERE wallet_id = ? LIMIT 1");
if (!$authStmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database query preparation failed']);
    exit();
}

$authStmt->bind_param("s", $walletId);
$authStmt->execute();
$authRes = $authStmt->get_result()->fetch_assoc();
$authStmt->close();

if (!$authRes) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid Wallet ID.']);
    exit();
}

// Decrypt and verify security credential securely
$storedCredential = trim((string)($authRes['b2c_security_credential'] ?? ''));
if (empty($storedCredential) || (hash_equals($storedCredential, $securityCredential) === false && $storedCredential !== $securityCredential)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid Security Credential API Key.']);
    exit();
}

$userId = $authRes['id'];

// 6. Extract Target Parameters
$conversationId = trim($inputData['conversation_id'] ?? '');
$originatorId = trim($inputData['originator_conversation_id'] ?? '');
$transactionId = trim($inputData['transaction_id'] ?? '');

if (empty($conversationId) && empty($originatorId) && empty($transactionId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Please provide conversation_id, originator_conversation_id, or transaction_id']);
    exit();
}

// 7. Query Transaction Status from Database
$query = "
    SELECT id, receiver_phone AS phone_number, transaction_amount, result_code, result_desc, transaction_id, conversation_id, receiver_name, created_at, completed_at
    FROM mpesa_b2c_transactions
    WHERE user_id = ? AND (conversation_id = ? OR originator_conversation_id = ? OR transaction_id = ?)
    LIMIT 1
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database query failed']);
    exit();
}

$stmt->bind_param("isss", $userId, $conversationId, $originatorId, $transactionId);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tx) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized']);
    exit();
}

// 8. Format Response Output
$resultCode = $tx['result_code'];
$statusText = 'PROCESSING';

if ($resultCode === 0 || $resultCode === '0') {
    $statusText = 'COMPLETED';
} elseif ($resultCode !== null) {
    $statusText = 'FAILED';
}

echo json_encode([
    'status' => 'success',
    'transaction_status' => $statusText,
    'result_code' => $resultCode,
    'result_desc' => $tx['result_desc'] ?? 'Transaction is currently processing or awaiting callback.',
    'receipt' => $tx['transaction_id'] ?? null,
    'conversation_id' => $tx['conversation_id'],
    'phone' => $tx['phone_number'],
    'amount' => (float)$tx['transaction_amount'],
    'receiver_name' => $tx['receiver_name'] ?? null,
    'created_at' => $tx['created_at'],
    'completed_at' => $tx['completed_at'] ?? null
]);
exit();