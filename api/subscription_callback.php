<?php
require_once __DIR__ . '/../lib/callback_auth.php';
av_require_callback_token('sub');
// /api/subscription_callback.php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    exit(json_encode(['status' => 'error', 'message' => 'Database config missing']));
}
require_once $dbPath;

$callbackPayload = file_get_contents('php://input');
$data = json_decode($callbackPayload, true);

if (!isset($data['Body']['stkCallback'])) {
    http_response_code(400);
    exit(json_encode(['status' => 'error', 'message' => 'Invalid callback payload']));
}

$stkCallback = $data['Body']['stkCallback'];
$checkoutRequestId = $stkCallback['CheckoutRequestID'] ?? '';
$resultCode = intval($stkCallback['ResultCode'] ?? -1);
$resultDesc = $stkCallback['ResultDesc'] ?? 'Unknown status';

// Fetch transaction along with stored settlement_id
$stmt = $conn->prepare("SELECT id, user_id, amount, settlement_id, status FROM mpesa_transactions WHERE checkout_request_id = ? LIMIT 1");
$stmt->bind_param("s", $checkoutRequestId);
$stmt->execute();
$txRes = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$txRes) {
    http_response_code(404);
    exit(json_encode(['status' => 'error', 'message' => 'Transaction not found']));
}

$txId = $txRes['id'];
$userId = (int)$txRes['user_id'];
$settlementId = (int)($txRes['settlement_id'] ?? 0);

if ($txRes['status'] === 'SUCCESS' || $txRes['status'] === 'FAILED') {
    exit(json_encode(['status' => 'success', 'message' => 'Already processed']));
}

if ($resultCode === 0) {
    $receiptNumber = '';
    foreach ($stkCallback['CallbackMetadata']['Item'] ?? [] as $item) {
        if ($item['Name'] === 'MpesaReceiptNumber') {
            $receiptNumber = $item['Value'];
            break;
        }
    }
    if (empty($receiptNumber)) $receiptNumber = 'SUCCESS';

    $conn->begin_transaction();
    try {
        // 1. Update transaction status
        $updTx = $conn->prepare("UPDATE mpesa_transactions SET status = 'SUCCESS', result_code = 0, mpesa_receipt_number = ? WHERE id = ?");
        $updTx->bind_param("si", $receiptNumber, $txId);
        $updTx->execute();
        $updTx->close();

        // 2. Derive months from amount paid (350 = 1 mo, 950 = 3 mo, 3500 = 12 mo)
        $amountPaid = floatval($txRes['amount']);
        $monthsToAdd = 1;
        if ($amountPaid >= 3500) {
            $monthsToAdd = 12;
        } elseif ($amountPaid >= 950) {
            $monthsToAdd = 3;
        } elseif ($amountPaid > 0) {
            $monthsToAdd = max(1, round($amountPaid / 350));
        }

        // 3. Extend or activate settlement expiry
        if ($settlementId > 0) {
            $sStmt = $conn->prepare("SELECT expiry_date, is_active FROM settlements WHERE id = ? AND user_id = ? LIMIT 1");
            $sStmt->bind_param("ii", $settlementId, $userId);
            $sStmt->execute();
            $sInfo = $sStmt->get_result()->fetch_assoc();
            $sStmt->close();

            if ($sInfo) {
                $currentExpiry = (!empty($sInfo['expiry_date']) && strtotime($sInfo['expiry_date']) > time()) 
                    ? strtotime($sInfo['expiry_date']) 
                    : time();
                
                $newExpiryDate = date('Y-m-d H:i:s', strtotime("+$monthsToAdd months", $currentExpiry));

                $updS = $conn->prepare("UPDATE settlements SET is_active = 1, status = 'active', expiry_date = ? WHERE id = ?");
                $updS->bind_param("si", $newExpiryDate, $settlementId);
                $updS->execute();
                $updS->close();
            }
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Subscription activated/renewed successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Database error during subscription extension']);
    }
} else {
    $updFail = $conn->prepare("UPDATE mpesa_transactions SET status = 'FAILED', result_code = ?, mpesa_receipt_number = ? WHERE id = ?");
    $updFail->bind_param("isi", $resultCode, $resultDesc, $txId);
    $updFail->execute();
    $updFail->close();

    echo json_encode(['status' => 'success', 'message' => 'Failure recorded']);
}
exit();