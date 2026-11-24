<?php
// b2c/topup/check_status.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

$dbPath = __DIR__ . '/../../database_config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database config missing']);
    exit();
}
require_once $dbPath;

$userId = null;

// 1. Dual Authentication: Check Dashboard Session OR API Headers (X-Wallet-ID & X-Security-Credential)
$headers = getallheaders();
$apiWalletId = $headers['X-Wallet-ID'] ?? $headers['x-wallet-id'] ?? $_GET['wallet_id'] ?? '';
$apiSecurityKey = $headers['X-Security-Credential'] ?? $headers['x-security-credential'] ?? $_GET['security_credential'] ?? '';

if (!empty($apiWalletId) && !empty($apiSecurityKey)) {
    // API Mode: Authenticate via credentials
    // Note: If you use SecureVault decryption for security credentials, make sure to decrypt or compare accordingly matching your stk_push.php logic
    $stmt = $conn->prepare("SELECT id, b2c_security_credential FROM users WHERE wallet_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $apiWalletId);
        $stmt->execute();
        $userRes = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$userRes) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid Wallet ID']);
        exit();
    }

    // Direct match or secure comparison with stored credential
    $storedCred = $userRes['b2c_security_credential'] ?? '';
    if (empty($storedCred) || (hash_equals($storedCred, $apiSecurityKey) === false && $storedCred !== $apiSecurityKey)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid Security Credential']);
        exit();
    }

    $userId = $userRes['id'];

} elseif (isset($_SESSION['user_id'])) {
    // Web Session Mode
    $userId = $_SESSION['user_id'];
} else {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized. Provide valid session or X-Wallet-ID and X-Security-Credential headers.']);
    exit();
}

$checkoutId = trim($_GET['checkout_id'] ?? $_GET['check_status'] ?? $_POST['checkout_id'] ?? '');
if (empty($checkoutId)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing checkout ID']);
    exit();
}

// 2. Query Transaction Status
$stmt = $conn->prepare("
    SELECT status, receipt_number, result_desc, amount, created_at 
    FROM b2c_topup_transactions 
    WHERE checkout_request_id = ? AND user_id = ? 
    LIMIT 1
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database prepare failed']);
    exit();
}

$stmt->bind_param("si", $checkoutId, $userId);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tx) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Transaction not found or unauthorized']);
    exit();
}

$status = strtolower($tx['status']);
$isCompleted = ($status === 'completed' || $status === 'success');
$isFailed = ($status === 'failed' || $status === 'cancelled' || $status === 'timeout');

echo json_encode([
    'status' => $status,
    'success' => $isCompleted,
    'failed' => $isFailed,
    'mpesa_receipt' => $tx['receipt_number'] ?? 'N/A',
    'message' => $tx['result_desc'] ?? ($isCompleted ? 'Top-up completed successfully.' : 'Transaction is pending or processing.'),
    'amount' => (float)$tx['amount'],
    'created_at' => $tx['created_at']
]);
exit();