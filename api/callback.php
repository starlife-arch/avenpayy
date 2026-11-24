<?php
require_once __DIR__ . '/../lib/callback_auth.php';
av_require_callback_token('stk');
require_once __DIR__ . '/../lib/logger.php';
// callback.php

// Force error logging for troubleshooting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Hide display errors in production to avoid invalid JSON output

// 1. Capture Raw POST Data
$raw_input = file_get_contents('php://input');
file_put_contents(av_log_path('callback_debug.log'), "[" . date('Y-m-d H:i:s') . "] " . $raw_input . PHP_EOL, FILE_APPEND);

if (empty($raw_input)) {
    http_response_code(400);
    exit(json_encode(["ResultDesc" => "Empty payload", "ResultCode" => "1"]));
}

$data = json_decode($raw_input, true);

if (!isset($data['Body']['stkCallback'])) {
    http_response_code(200);
    echo json_encode(["ResultDesc" => "Ignored", "ResultCode" => "0"]);
    exit;
}

$stk = $data['Body']['stkCallback'];
$resultCode = (int)$stk['ResultCode'];
$resultDesc = $stk['ResultDesc'] ?? 'Transaction Failed / Unknown Error';
$checkoutID = $stk['CheckoutRequestID'];
$merchantID = $stk['MerchantRequestID'] ?? NULL;

// --- ROOT DATABASE LINK CONFIGURATION LAYER ---
$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
if (file_exists($dbPath)) {
    require_once $dbPath;
} else {
    http_response_code(500); 
    exit(json_encode(["ResultDesc" => "Database config file missing", "ResultCode" => "1"]));
}

if (!isset($conn) || $conn->connect_error) { 
    http_response_code(500); 
    exit(json_encode(["ResultDesc" => "Database connection failed", "ResultCode" => "1"])); 
}

// 2. IDEMPOTENCY CHECK: Prevent duplicate processing
$check = $conn->prepare("SELECT status FROM mpesa_transactions WHERE checkout_request_id = ? LIMIT 1");
$check->bind_param("s", $checkoutID);
$check->execute();
$currentStatus = $check->get_result()->fetch_assoc()['status'] ?? '';
$check->close();

if ($currentStatus === 'SUCCESS' || $currentStatus === 'FAILED') {
    http_response_code(200);
    echo json_encode(["ResultDesc" => "Already processed", "ResultCode" => "0"]);
    exit;
}

if ($resultCode === 0) {
    // SUCCESS
    $receipt = NULL; $amount = 0.00; $phone = '';
    if (isset($stk['CallbackMetadata']['Item'])) {
        foreach ($stk['CallbackMetadata']['Item'] as $item) {
            if ($item['Name'] === 'MpesaReceiptNumber') $receipt = $item['Value'];
            if ($item['Name'] === 'Amount') $amount = (float)$item['Value'];
            if ($item['Name'] === 'PhoneNumber') $phone = (string)$item['Value'];
        }
    }
    
    // Record receipt code and mark SUCCESS
    $stmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'SUCCESS', merchant_request_id = ?, mpesa_receipt_number = ?, result_code = ? WHERE checkout_request_id = ?");
    $stmt->bind_param("ssis", $merchantID, $receipt, $resultCode, $checkoutID);
    $stmt->execute();
    $stmt->close(); 

    // Get transaction ID and settlement ID for queue & webhook dispatch
    $lookup = $conn->prepare("SELECT id, user_id, settlement_id FROM mpesa_transactions WHERE checkout_request_id = ? LIMIT 1");
    $lookup->bind_param("s", $checkoutID);
    $lookup->execute();
    $txRow = $lookup->get_result()->fetch_assoc();
    $transaction_id = (int)($txRow['id'] ?? 0);
    $settlement_id = (int)($txRow['settlement_id'] ?? 0);
    $lookup->close();

    // Stream Queue insertion
    $queue = $conn->prepare("INSERT INTO mpesa_stream_queue (transaction_id, mpesa_code, phone, amount, tx_status, stream_status) VALUES (?, ?, ?, ?, 'SUCCESS', 'pending')");
    $queue->bind_param("issd", $transaction_id, $receipt, $phone, $amount);
    $queue->execute();
    $queue->close();

    // --- WEBHOOK DISPATCH LOGIC ---
    dispatchWebhook($conn, $transaction_id, $settlement_id, $checkoutID, $receipt, $amount, $phone, $resultCode, 'SUCCESS');

} else {
    // FAILED
    $stmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'FAILED', merchant_request_id = ?, mpesa_receipt_number = ?, result_code = ? WHERE checkout_request_id = ?");
    $stmt->bind_param("ssis", $merchantID, $resultDesc, $resultCode, $checkoutID);
    $stmt->execute();
    $stmt->close();

    $lookup = $conn->prepare("SELECT id, settlement_id, phone_number, amount FROM mpesa_transactions WHERE checkout_request_id = ? LIMIT 1");
    $lookup->bind_param("s", $checkoutID);
    $lookup->execute();
    $res = $lookup->get_result()->fetch_assoc();
    $transaction_id = (int)($res['id'] ?? 0);
    $settlement_id = (int)($res['settlement_id'] ?? 0);
    $phone = $res['phone_number'] ?? 'N/A';
    $amount = (float)($res['amount'] ?? 0.00);
    $lookup->close();

    $queue = $conn->prepare("INSERT INTO mpesa_stream_queue (transaction_id, mpesa_code, phone, amount, tx_status, stream_status) VALUES (?, ?, ?, ?, 'FAILED', 'pending')");
    $queue->bind_param("issd", $transaction_id, $resultDesc, $phone, $amount);
    $queue->execute();
    $queue->close();

    dispatchWebhook($conn, $transaction_id, $settlement_id, $checkoutID, $resultDesc, $amount, $phone, $resultCode, 'FAILED');
}

// Webhook Dispatcher Function
function dispatchWebhook($conn, $tx_id, $settlement_id, $checkoutID, $receipt, $amount, $phone, $resultCode, $status) {
    if ($settlement_id <= 0) return;

    // Join directly on settlement_id and check for active settlement
    $query = $conn->prepare("
        SELECT callback_url 
        FROM settlements 
        WHERE id = ? 
          AND is_active = 1 
          AND (expiry_date IS NULL OR expiry_date > NOW()) 
        LIMIT 1
    ");
    $query->bind_param("i", $settlement_id);
    $query->execute();
    $url = trim($query->get_result()->fetch_assoc()['callback_url'] ?? '');
    $query->close();

    if (!empty($url) && filter_var($url, FILTER_VALIDATE_URL)) {
        $payloadData = [
            "checkout_id"   => $checkoutID,
            "status"        => $status,
            "mpesa_receipt" => $receipt,
            "amount"        => (float)$amount,
            "phone"         => (string)$phone,
            "result_code"   => (int)$resultCode
        ];

        $payload_json = json_encode($payloadData);
        
        $stmt = $conn->prepare("INSERT INTO webhook_queue (transaction_id, webhook_url, payload, status) VALUES (?, ?, ?, 'PENDING')");
        $stmt->bind_param("iss", $tx_id, $url, $payload_json);
        $stmt->execute();
        $q_id = $stmt->insert_id;
        $stmt->close();

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $newStatus = ($code >= 200 && $code < 300) ? 'SENT' : 'FAILED';
        $update = $conn->prepare("UPDATE webhook_queue SET status = ? WHERE id = ?");
        $update->bind_param("si", $newStatus, $q_id);
        $update->execute();
        $update->close();
    }
}

$conn->close();
http_response_code(200);
echo json_encode(["ResultDesc" => "Service accepted successfully", "ResultCode" => "0"]);
exit;
?>