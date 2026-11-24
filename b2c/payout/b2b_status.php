<?php
// b2c/payout/b2b_status.php - look up a B2B payment you created (same X-Wallet-ID / X-Security-Credential auth)
require_once __DIR__ . '/../../database_config/db.php';
header('Content-Type: application/json; charset=utf-8');
av_rate_limit_or_die('b2b_status', 120, 60);

$h = [];
foreach (function_exists('getallheaders') ? getallheaders() : [] as $k => $v) $h[strtolower($k)] = $v;
$walletId = trim((string)($h['x-wallet-id'] ?? ''));
$secret   = (string)($h['x-security-credential'] ?? '');
if ($walletId === '' || $secret === '') { http_response_code(401); exit(json_encode(['status' => 'error', 'message' => 'X-Wallet-ID and X-Security-Credential headers are required.'])); }

$st = $conn->prepare("SELECT id, b2c_security_credential FROM users WHERE wallet_id = ? LIMIT 1");
$st->bind_param("s", $walletId); $st->execute();
$u = $st->get_result()->fetch_assoc(); $st->close();
if (!$u || !hash_equals((string)$u['b2c_security_credential'], $secret)) { http_response_code(403); exit(json_encode(['status' => 'error', 'message' => 'Access denied. The security credential or API key is incorrect.'])); }

$conv = trim((string)($_GET['conversation_id'] ?? '')); $idem = trim((string)($_GET['idempotency_key'] ?? ''));
if ($conv === '' && $idem === '') { http_response_code(400); exit(json_encode(['status' => 'error', 'message' => 'Provide conversation_id or idempotency_key.'])); }

$uid = (int)$u['id'];
$q = $conn->prepare("SELECT conversation_id, command_id, party_b, account_reference, transaction_amount, fee_amount, result_code, result_desc, mpesa_receipt, created_at, completed_at FROM mpesa_b2b_transactions WHERE user_id = ? AND (conversation_id = ? OR (idempotency_key IS NOT NULL AND idempotency_key = ?)) LIMIT 1");
$q->bind_param("iss", $uid, $conv, $idem); $q->execute();
$r = $q->get_result()->fetch_assoc(); $q->close();
if (!$r) { http_response_code(404); exit(json_encode(['status' => 'error', 'message' => 'Payment not found.'])); }

$code = (int)$r['result_code'];
$state = ($code === 1000) ? 'pending' : (($code === 0) ? 'completed' : 'failed');
echo json_encode(['status' => 'success', 'payment' => [
    'state' => $state, 'conversation_id' => $r['conversation_id'], 'command_id' => $r['command_id'], 'party_b' => $r['party_b'],
    'account_reference' => $r['account_reference'], 'amount' => (float)$r['transaction_amount'], 'fee' => (float)$r['fee_amount'],
    'mpesa_receipt' => $r['mpesa_receipt'], 'description' => $r['result_desc'], 'created_at' => $r['created_at'], 'completed_at' => $r['completed_at'],
]]);
