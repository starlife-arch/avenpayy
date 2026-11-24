<?php
// b2c/payout/b2b_result.php - Daraja B2B result callback (authenticated by the ?k= token in the URL)
require_once __DIR__ . '/../../lib/callback_auth.php';
av_require_callback_token('b2b_result');
require_once __DIR__ . '/../../lib/logger.php';
require_once __DIR__ . '/../../database_config/db.php';
require_once __DIR__ . '/../../lib/ledger.php';
header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
av_log('b2b_results.log', $raw);
$data = json_decode($raw, true);
$res  = is_array($data) ? ($data['Result'] ?? null) : null;
$ack  = ['ResultCode' => 0, 'ResultDesc' => 'Accepted'];
if (!$res) { echo json_encode($ack); exit; }

$conv    = (string)($res['ConversationID'] ?? '');
$orig    = (string)($res['OriginatorConversationID'] ?? '');
$code    = $res['ResultCode'] ?? null;
$desc    = (string)($res['ResultDesc'] ?? '');
$receipt = (string)($res['TransactionID'] ?? '');

$st = $conn->prepare("SELECT id, user_id, transaction_amount, fee_amount, result_code FROM mpesa_b2b_transactions WHERE conversation_id = ? OR originator_conversation_id = ? LIMIT 1");
$st->bind_param("ss", $conv, $orig);
$st->execute();
$tx = $st->get_result()->fetch_assoc();
$st->close();
if (!$tx) { av_log('b2b_results.log', "No matching B2B transaction for conv=$conv orig=$orig"); echo json_encode($ack); exit; }

$txId = (int)$tx['id'];
if ((string)$code === '0') {
    $ok = $conn->prepare("UPDATE mpesa_b2b_transactions SET result_code = 0, result_desc = ?, mpesa_receipt = ?, completed_at = NOW() WHERE id = ? AND result_code = 1000");
    $ok->bind_param("ssi", $desc, $receipt, $txId);
    $ok->execute();
    if ($ok->affected_rows === 0) {
        // The payout was already marked failed/refunded but M-Pesa says it succeeded: needs a human.
        av_log('b2b_review_needed.log', "LATE SUCCESS for b2b tx $txId (already status {$tx['result_code']}) receipt=$receipt");
    }
    $ok->close();
} else {
    $dbCode = is_numeric($code) ? (int)$code : 9999;
    $dbDesc = $desc . (is_numeric($code) ? '' : " [code: $code]");
    $conn->begin_transaction();
    try {
        $fail = $conn->prepare("UPDATE mpesa_b2b_transactions SET result_code = ?, result_desc = ?, completed_at = NOW() WHERE id = ? AND result_code = 1000");
        $fail->bind_param("isi", $dbCode, $dbDesc, $txId);
        $fail->execute();
        $claimed = $fail->affected_rows > 0;
        $fail->close();
        if ($claimed) {
            $refund = (float)$tx['transaction_amount'] + (float)$tx['fee_amount'];
            av_wallet_apply($conn, (int)$tx['user_id'], $refund, 'payout_release', 'b2b_tx', (string)$txId, 'B2B payment failed: ' . substr($desc, 0, 120), false);
            $tw = $conn->prepare("UPDATE users SET total_b2c_withdrawn = GREATEST(0, total_b2c_withdrawn - ?) WHERE id = ?");
            $amt = (float)$tx['transaction_amount']; $uid = (int)$tx['user_id'];
            $tw->bind_param("di", $amt, $uid); $tw->execute(); $tw->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('AvenPay b2b_result refund failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Retry']);
        exit;
    }
}
echo json_encode($ack);
