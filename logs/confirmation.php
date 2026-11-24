<?php
require_once __DIR__ . '/../lib/callback_auth.php';
av_require_callback_token('c2b');
require_once __DIR__ . '/../database_config/db.php';
require_once __DIR__ . '/../lib/ledger.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$raw = file_get_contents('php://input');
$payload = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($payload)) {
    http_response_code(400);
    exit(json_encode(['ResultCode' => 1, 'ResultDesc' => 'Invalid JSON callback']));
}

$receipt = strtoupper(trim((string) ($payload['TransID'] ?? '')));
$walletId = strtoupper(trim((string) ($payload['BillRefNumber'] ?? '')));
$amount = round((float) ($payload['TransAmount'] ?? 0), 2);
$phone = preg_replace('/[^0-9]/', '', (string) ($payload['MSISDN'] ?? ''));
$shortcode = trim((string) ($payload['BusinessShortCode'] ?? ''));
$transTime = trim((string) ($payload['TransTime'] ?? date('YmdHis')));
$firstName = trim((string) ($payload['FirstName'] ?? ''));
$middleName = trim((string) ($payload['MiddleName'] ?? ''));
$lastName = trim((string) ($payload['LastName'] ?? ''));

if ($receipt === '' || $walletId === '' || $amount <= 0 || $phone === '') {
    http_response_code(400);
    exit(json_encode(['ResultCode' => 1, 'ResultDesc' => 'Missing receipt, wallet reference, phone or valid amount']));
}

try {
    $conn->begin_transaction();

    // Keep the original PrintPay reconciliation log as the durable record of every
    // incoming Paybill payment, including references that do not match a wallet yet.
    $transType = 'c2b-paybill';
    $rawPayload = $raw;
    $log = $conn->prepare(
        "INSERT INTO paybill_logs
            (trans_id, trans_type, trans_time, amount, shortcode, bill_ref, msisdn,
             first_name, middle_name, last_name, raw_payload)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            amount = VALUES(amount), bill_ref = VALUES(bill_ref),
            msisdn = VALUES(msisdn), raw_payload = VALUES(raw_payload)"
    );
    if (!$log) throw new RuntimeException('Could not prepare Paybill transaction log: ' . $conn->error);
    $log->bind_param(
        'sssdsssssss',
        $receipt, $transType, $transTime, $amount, $shortcode, $walletId,
        $phone, $firstName, $middleName, $lastName, $rawPayload
    );
    $log->execute();
    $log->close();

    // Preserve PrintPay's accept-and-reconcile behavior. Unknown references must
    // not cause Safaricom to reject a payment that has already reached the Paybill.
    $userStmt = $conn->prepare("SELECT id FROM users WHERE wallet_id = ? LIMIT 1 FOR UPDATE");
    if (!$userStmt) throw new RuntimeException('Could not prepare wallet lookup: ' . $conn->error);
    $userStmt->bind_param('s', $walletId);
    $userStmt->execute();
    $user = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$user) {
        $conn->commit();
        echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Payment received and logged for wallet reconciliation']);
        exit();
    }

    $userId = (int) $user['id'];
    $fee = 0.00;
    $feeStmt = $conn->prepare(
        "SELECT fee_type, fee_value FROM transaction_fees
         WHERE service_type = 'c2b_topup' AND is_active = 1
           AND ? >= min_amount AND ? <= max_amount
         LIMIT 1"
    );
    if ($feeStmt) {
        $feeStmt->bind_param('dd', $amount, $amount);
        $feeStmt->execute();
        $feeRow = $feeStmt->get_result()->fetch_assoc();
        $feeStmt->close();
        if ($feeRow) {
            $fee = ($feeRow['fee_type'] === 'percentage')
                ? ($amount * (float) $feeRow['fee_value']) / 100
                : (float) $feeRow['fee_value'];
        }
    }
    $netAmount = round(max(0, $amount - $fee), 2);

    // C2B callback and the scheduled Safaricom pull use the same receipt namespace
    // in wallet_ledger, so whichever path runs first wins and the other cannot
    // credit the same M-Pesa receipt a second time.
    $desc = 'Manual Paybill wallet deposit';
    $insert = $conn->prepare(
        "INSERT IGNORE INTO mpesa_transactions
            (checkout_request_id, phone_number, amount, mpesa_receipt_number,
             status, result_code, user_id, processed, transaction_desc)
         VALUES (?, ?, ?, ?, 'SUCCESS', 0, ?, 1, ?)"
    );
    if (!$insert) throw new RuntimeException('Could not prepare M-Pesa transaction record: ' . $conn->error);
    $insert->bind_param('ssdsis', $receipt, $phone, $amount, $receipt, $userId, $desc);
    $insert->execute();
    $insert->close();

    if ($netAmount > 0) {
        $reason = null;
        $credited = av_wallet_apply(
            $conn,
            $userId,
            $netAmount,
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
    }

    // Keep the same admin-visible top-up record produced by the legacy pull cron.
    $topupCheck = $conn->prepare("SELECT id FROM b2c_topup_transactions WHERE receipt_number = ? LIMIT 1 FOR UPDATE");
    if (!$topupCheck) throw new RuntimeException('Could not prepare top-up duplicate check: ' . $conn->error);
    $topupCheck->bind_param('s', $receipt);
    $topupCheck->execute();
    $existingTopup = $topupCheck->get_result()->fetch_assoc();
    $topupCheck->close();

    if (!$existingTopup) {
        $checkoutId = 'C2B_' . $receipt;
        $merchantId = 'C2B_CALLBACK';
        $status = 'completed';
        $resultDesc = 'Credited via Paybill confirmation callback';
        $topup = $conn->prepare(
            "INSERT INTO b2c_topup_transactions
                (user_id, wallet_id, checkout_request_id, merchant_request_id,
                 phone_number, amount, status, receipt_number, result_code, result_desc, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, NOW())"
        );
        if (!$topup) throw new RuntimeException('Could not prepare top-up history record: ' . $conn->error);
        $topup->bind_param(
            'issssdsss',
            $userId, $walletId, $checkoutId, $merchantId, $phone,
            $amount, $status, $receipt, $resultDesc
        );
        $topup->execute();
        $topup->close();
    }

    $conn->commit();
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => $netAmount > 0
            ? 'Payment received and wallet reconciliation completed'
            : 'Payment received and recorded; no positive net amount remained after fees'
    ]);
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('AvenPay C2B confirmation failed for receipt ' . $receipt . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Payment could not be reconciled. Safaricom may retry the callback.']);
}
exit();
