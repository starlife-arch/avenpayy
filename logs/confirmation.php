<?php
require_once __DIR__ . '/../lib/callback_auth.php';
av_require_callback_token('c2b');
require_once __DIR__ . '/../database_config/db.php';
require_once __DIR__ . '/../lib/ledger.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    http_response_code(400);
    exit(json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON callback']));
}

$receipt = strtoupper(trim((string) ($payload['TransID'] ?? '')));
$walletId = strtoupper(trim((string) ($payload['BillRefNumber'] ?? '')));
$amount = round((float) ($payload['TransAmount'] ?? 0), 2);
$phone = preg_replace('/[^0-9]/', '', (string) ($payload['MSISDN'] ?? ''));

if ($receipt === '' || $walletId === '' || $amount <= 0) {
    http_response_code(400);
    exit(json_encode(['ResultCode' => 1, 'ResultDesc' => 'Missing receipt, wallet reference or valid amount']));
}

try {
    $conn->begin_transaction();

    $userStmt = $conn->prepare("SELECT id FROM users WHERE wallet_id = ? LIMIT 1 FOR UPDATE");
    $userStmt->bind_param('s', $walletId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        $conn->rollback();
        http_response_code(422);
        exit(json_encode(['ResultCode' => 1, 'ResultDesc' => 'Wallet reference was not found']));
    }

    $userId = (int) $user['id'];
    $desc = 'Manual Paybill wallet deposit';
    $insert = $conn->prepare(
        "INSERT IGNORE INTO mpesa_transactions
            (checkout_request_id, phone_number, amount, mpesa_receipt_number, status, result_code, user_id, processed, transaction_desc)
         VALUES (?, ?, ?, ?, 'SUCCESS', 0, ?, 1, ?)"
    );
    $insert->bind_param('ssdsis', $receipt, $phone, $amount, $receipt, $userId, $desc);
    $insert->execute();
    $inserted = $insert->affected_rows === 1;
    $insert->close();

    if (!$inserted) {
        $conn->commit();
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Payment was already recorded']);
        exit();
    }

    $reason = null;
    $credited = av_wallet_apply(
        $conn,
        $userId,
        $amount,
        'topup_credit',
        'c2b_manual',
        $receipt,
        'Manual Paybill deposit ' . $receipt,
        false,
        $reason
    );

    if (!$credited && $reason !== 'duplicate') {
        throw new RuntimeException('Wallet credit was not applied (' . (string) $reason . ').');
    }

    $conn->commit();
    echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Wallet credited successfully']);
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('AvenPay C2B confirmation failed for receipt ' . $receipt . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Payment could not be posted to the wallet.']);
}
exit();
