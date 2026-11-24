<?php
require_once __DIR__ . '/../lib/callback_auth.php';
av_require_callback_token('c2b');
require_once __DIR__ . '/../database_config/db.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$payload = json_decode(file_get_contents('php://input'), true);
$walletId = strtoupper(trim((string) ($payload['BillRefNumber'] ?? '')));
$receipt = trim((string) ($payload['TransID'] ?? ''));
$amount = (float) ($payload['TransAmount'] ?? 0);

$accepted = false;
if ($walletId !== '' && $receipt !== '' && $amount > 0) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE wallet_id = ? LIMIT 1");
    $stmt->bind_param('s', $walletId);
    $stmt->execute();
    $accepted = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$accepted) {
    error_log('AvenPay C2B validation rejected unknown wallet reference or invalid transaction.');
}
echo json_encode([
    'ResultCode' => $accepted ? 0 : 1,
    'ResultDesc' => $accepted ? 'Accepted' : 'Unknown wallet ID or invalid payment details'
]);
exit();
