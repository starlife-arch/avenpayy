<?php
require_once __DIR__ . '/../../lib/callback_auth.php';
av_require_callback_token('b2c_timeout');
// b2c/payout/timeout.php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

// Ensure file exists before inclusion
$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database configuration file missing.']);
    exit();
}
require_once $dbPath;

// Dynamic Fee Calculator helper for calculating refund amounts (Amount + Fee)
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

// 1. Read Raw POST Payload from Safaricom
$rawPayload = file_get_contents('php://input');

if (empty($rawPayload)) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Empty payload received']);
    exit();
}

$data = json_decode($rawPayload, true);

// 2. Extract Response Metadata
$resultData = $data['Result'] ?? [];
$conversationID = trim($resultData['ConversationID'] ?? '');
$originatorConversationID = trim($resultData['OriginatorConversationID'] ?? '');
$resultCode = intval($resultData['ResultCode'] ?? -1);
$resultDesc = $resultData['ResultDesc'] ?? 'Request Timed Out on Safaricom Queue';

if (empty($conversationID) && empty($originatorConversationID)) {
    http_response_code(400);
    echo json_encode(['ResultCode' => 1, 'ResultDesc' => 'Missing conversation IDs']);
    exit();
}

if (isset($conn) && $conn) {
    // 3. Lookup transaction to refund the user (Amount + Fee) upon timeout
    $lookupStmt = $conn->prepare("
        SELECT id, user_id, transaction_amount 
        FROM mpesa_b2c_transactions 
        WHERE conversation_id = ? OR originator_conversation_id = ? 
        LIMIT 1
    ");
    
    if ($lookupStmt) {
        $lookupStmt->bind_param("ss", $conversationID, $originatorConversationID);
        $lookupStmt->execute();
        $txRes = $lookupStmt->get_result()->fetch_assoc();
        $lookupStmt->close();

        if ($txRes) {
            $txId = $txRes['id'];
            $userId = $txRes['user_id'];
            $amount = floatval($txRes['transaction_amount']);
            
            // A queue timeout does NOT prove the payout failed - Safaricom may still deliver a final result.
            // Refunding here could pay the customer twice, so funds stay reserved (result_code stays 1000)
            // and the final result callback (or a status query / admin review) settles the transaction.
            $note = 'Queue timeout received - awaiting final result from M-Pesa';
            $updateStmt = $conn->prepare("
                UPDATE mpesa_b2c_transactions
                SET result_desc = ?,
                    raw_response = CONCAT(IFNULL(raw_response, ''), '\n[TIMEOUT CALLBACK]')
                WHERE id = ? AND result_code = 1000
            ");
            if ($updateStmt) {
                $updateStmt->bind_param("si", $note, $txId);
                $updateStmt->execute();
                $updateStmt->close();
            }
            error_log('AvenPay B2C queue timeout for tx ' . $txId . ' - needs status query / review if no result arrives');
        }
    }
}

// 4. Acknowledge Safaricom Callback Successfully
echo json_encode([
    'ResultCode' => 0,
    'ResultDesc' => 'Timeout payload received and logged'
]);
exit();