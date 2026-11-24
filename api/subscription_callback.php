<?php
require_once __DIR__ . '/../lib/callback_auth.php';
av_require_callback_token('sub');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(E_ALL);

$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
if (!is_file($dbPath)) {
    http_response_code(500);
    exit(json_encode(['status' => 'error', 'message' => 'Database config missing']));
}
require_once $dbPath;

$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['Body']['stkCallback']) || !is_array($data['Body']['stkCallback'])) {
    http_response_code(400);
    exit(json_encode(['status' => 'error', 'message' => 'Invalid callback payload']));
}

$callback = $data['Body']['stkCallback'];
$checkoutRequestId = trim((string) ($callback['CheckoutRequestID'] ?? ''));
$resultCode = filter_var($callback['ResultCode'] ?? null, FILTER_VALIDATE_INT);
$resultDesc = trim((string) ($callback['ResultDesc'] ?? 'Unknown status'));

if ($checkoutRequestId === '' || $resultCode === false || $resultCode === null) {
    http_response_code(400);
    exit(json_encode(['status' => 'error', 'message' => 'Callback is missing transaction details']));
}

$receiptNumber = '';
foreach (($callback['CallbackMetadata']['Item'] ?? []) as $item) {
    if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
        $receiptNumber = (string) ($item['Value'] ?? '');
        break;
    }
}

try {
    $conn->begin_transaction();

    // Lock the transaction row so concurrent/retried callbacks cannot extend twice.
    $stmt = $conn->prepare("SELECT id, user_id, amount, settlement_id, status, processed FROM mpesa_transactions WHERE checkout_request_id = ? LIMIT 1 FOR UPDATE");
    if (!$stmt) {
        throw new RuntimeException('Could not prepare transaction lookup.');
    }
    $stmt->bind_param('s', $checkoutRequestId);
    $stmt->execute();
    $tx = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$tx) {
        $conn->rollback();
        http_response_code(404);
        exit(json_encode(['status' => 'error', 'message' => 'Transaction not found']));
    }

    $currentStatus = strtoupper((string) $tx['status']);
    if ((int) ($tx['processed'] ?? 0) === 1 || in_array($currentStatus, ['SUCCESS', 'FAILED'], true)) {
        $conn->commit();
        exit(json_encode(['status' => 'success', 'message' => 'Callback already processed']));
    }

    $txId = (int) $tx['id'];
    if ($resultCode === 0) {
        $settlementId = (int) ($tx['settlement_id'] ?? 0);
        $userId = (int) ($tx['user_id'] ?? 0);
        if ($settlementId < 1 || $userId < 1) {
            throw new RuntimeException('Transaction is missing its settlement ownership details.');
        }

        $settleStmt = $conn->prepare("SELECT expiry_date FROM settlements WHERE id = ? AND user_id = ? LIMIT 1 FOR UPDATE");
        if (!$settleStmt) {
            throw new RuntimeException('Could not prepare settlement lookup.');
        }
        $settleStmt->bind_param('ii', $settlementId, $userId);
        $settleStmt->execute();
        $settlement = $settleStmt->get_result()->fetch_assoc();
        $settleStmt->close();
        if (!$settlement) {
            throw new RuntimeException('The related settlement account was not found.');
        }

        $amountPaid = (float) $tx['amount'];
        if ($amountPaid <= 0) {
            throw new RuntimeException('Transaction amount is invalid.');
        }
        // Preserve the existing plan pricing rules.
        if ($amountPaid >= 3500) {
            $monthsToAdd = 12;
        } elseif ($amountPaid >= 950) {
            $monthsToAdd = 3;
        } else {
            $monthsToAdd = max(1, (int) round($amountPaid / 350));
        }

        $expiryTimestamp = !empty($settlement['expiry_date']) && strtotime($settlement['expiry_date']) > time()
            ? strtotime($settlement['expiry_date'])
            : time();
        $newExpiryDate = date('Y-m-d H:i:s', strtotime('+' . $monthsToAdd . ' months', $expiryTimestamp));

        $updateSettlement = $conn->prepare("UPDATE settlements SET is_active = 1, status = 'active', expiry_date = ? WHERE id = ? AND user_id = ?");
        if (!$updateSettlement) {
            throw new RuntimeException('Could not prepare settlement activation.');
        }
        $updateSettlement->bind_param('sii', $newExpiryDate, $settlementId, $userId);
        $updateSettlement->execute();
        if ($updateSettlement->affected_rows < 0) {
            throw new RuntimeException('Settlement activation failed.');
        }
        $updateSettlement->close();

        $receiptNumber = $receiptNumber !== '' ? $receiptNumber : 'SUCCESS';
        $updateTx = $conn->prepare("UPDATE mpesa_transactions SET status = 'SUCCESS', result_code = 0, processed = 1, mpesa_receipt_number = ? WHERE id = ? AND status = 'PENDING' AND processed = 0");
        $updateTx->bind_param('si', $receiptNumber, $txId);
        $updateTx->execute();
        $updateTx->close();

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Subscription activated/renewed successfully']);
    } else {
        $updateTx = $conn->prepare("UPDATE mpesa_transactions SET status = 'FAILED', result_code = ?, processed = 1, mpesa_receipt_number = ? WHERE id = ? AND status = 'PENDING' AND processed = 0");
        $updateTx->bind_param('isi', $resultCode, $resultDesc, $txId);
        $updateTx->execute();
        $updateTx->close();
        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Payment failure recorded']);
    }
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('AvenPay subscription callback failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Subscription payment could not be finalized. The callback can be safely retried.']);
}
exit();
