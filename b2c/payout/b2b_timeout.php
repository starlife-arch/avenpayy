<?php
// b2c/payout/b2b_timeout.php - Daraja B2B queue-timeout callback. Does NOT refund (the payment may still complete).
require_once __DIR__ . '/../../lib/callback_auth.php';
av_require_callback_token('b2b_timeout');
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../database_config/db.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
av_log('b2b_results.log', 'TIMEOUT ' . $raw);
$data = json_decode($raw, true);
$res  = is_array($data) ? ($data['Result'] ?? null) : null;
if ($res) {
    $conv = (string)($res['ConversationID'] ?? ''); $orig = (string)($res['OriginatorConversationID'] ?? '');
    $note = 'Queue timeout received - awaiting final result from M-Pesa';
    $st = $conn->prepare("UPDATE mpesa_b2b_transactions SET result_desc = ? WHERE (conversation_id = ? OR originator_conversation_id = ?) AND result_code = 1000");
    $st->bind_param("sss", $note, $conv, $orig); $st->execute(); $st->close();
}
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
