<?php
require_once __DIR__ . '/../../lib/ledger.php';
require_once __DIR__ . '/../../lib/callback_auth.php';
av_require_callback_token('topup');
// b2c/topup/callback.php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

$dbPath = __DIR__ . '/../../database_config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    exit(json_encode(['status' => 'error', 'message' => 'Database config missing']));
}
require_once $dbPath;

// Dynamic Fee Calculator from database brackets
function getTransactionFee($conn, $serviceType, $amount) {
    $stmt = $conn->prepare("
        SELECT fee_type, fee_value 
        FROM transaction_fees 
        WHERE service_type = ? AND is_active = 1 AND ? >= min_amount AND ? <= max_amount 
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("sdd", $serviceType, $amount, $amount);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) {
            return ($res['fee_type'] === 'percentage') 
                ? ($amount * floatval($res['fee_value'])) / 100 
                : floatval($res['fee_value']);
        }
    }
    return 0.00;
}

$callbackPayload = file_get_contents('php://input');
$data = json_decode($callbackPayload, true);

if (!isset($data['Body']['stkCallback'])) {
    http_response_code(400);
    exit(json_encode(['status' => 'error', 'message' => 'Invalid callback payload']));
}

$stkCallback = $data['Body']['stkCallback'];
$merchantRequestId = $stkCallback['MerchantRequestID'] ?? '';
$checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? '';
$resultCode = intval($stkCallback['ResultCode'] ?? -1);
$resultDesc = $stkCallback['ResultDesc'] ?? 'Unknown status';

// Fetch pending transaction from database
$stmt = $conn->prepare("
    SELECT id, user_id, amount, status 
    FROM b2c_topup_transactions 
    WHERE checkout_request_id = ? LIMIT 1
");
$stmt->bind_param("s", $checkoutRequestId);
$stmt->execute();
$txRes = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$txRes) {
    http_response_code(404);
    exit(json_encode(['status' => 'error', 'message' => 'Transaction not found']));
}

$txId = $txRes['id'];
$userId = $txRes['user_id'];
$grossAmount = floatval($txRes['amount']);

// Prevent duplicate processing if already completed/failed
if ($txRes['status'] === 'completed' || $txRes['status'] === 'failed') {
    exit(json_encode(['status' => 'success', 'message' => 'Transaction already processed']));
}

if ($resultCode === 0) {
    // SUCCESS: Extract M-Pesa Receipt Number from Callback Metadata
    $receiptNumber = '';
    $callbackItems = $stkCallback['CallbackMetadata']['Item'] ?? [];
    foreach ($callbackItems as $item) {
        if ($item['Name'] === 'MpesaReceiptNumber') {
            $receiptNumber = $item['Value'];
            break;
        }
    }

    // Calculate Fee & Net Creditable Amount
    $topupFee = getTransactionFee($conn, 'c2b_topup', $grossAmount);
    $netCreditAmount = $grossAmount - $topupFee;

    $conn->begin_transaction();
    try {
        // 1. Atomically claim the transaction (a duplicate callback finds it already completed and stops)
        $updTx = $conn->prepare("
            UPDATE b2c_topup_transactions
            SET status = 'completed', receipt_number = ?, result_desc = ?
            WHERE id = ? AND status <> 'completed'
        ");
        $updTx->bind_param("ssi", $receiptNumber, $resultDesc, $txId);
        $updTx->execute();
        $claimedTopup = $updTx->affected_rows > 0;
        $updTx->close();

        if (!$claimedTopup) {
            $conn->rollback();
            echo json_encode(['status' => 'success', 'message' => 'Already processed']);
            exit();
        }

        // 2. CREDIT USER B2C WALLET (ledger-backed, idempotent per top-up)
        if ($netCreditAmount > 0) {
            $creditReason = null;
            $credited = av_wallet_apply(
                $conn,
                (int) $userId,
                (float) $netCreditAmount,
                'topup_credit',
                'topup',
                (string) $txId,
                'M-Pesa top-up ' . $receiptNumber,
                false,
                $creditReason
            );
            if (!$credited && $creditReason !== 'duplicate') {
                throw new RuntimeException('Wallet credit was not applied (' . (string) $creditReason . ').');
            }
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Wallet successfully credited']);
    } catch (Throwable $e) {
        $conn->rollback();
        http_response_code(500);
        error_log('AvenPay wallet top-up callback failed for transaction ' . (string) $txId . ': ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Wallet credit could not be completed. Safaricom may retry the callback.']);
    }

} else {
    // FAILURE / CANCELLED BY USER: Mark as failed, no wallet credit
    $failTx = $conn->prepare("
        UPDATE b2c_topup_transactions 
        SET status = 'failed', result_desc = ? 
        WHERE id = ?
    ");
    $failTx->bind_param("si", $resultDesc, $txId);
    $failTx->execute();
    $failTx->close();

    echo json_encode(['status' => 'success', 'message' => 'Failure recorded']);
}
exit();