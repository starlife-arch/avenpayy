<?php
require_once __DIR__ . '/lib/logger.php';
// check_status.php

// 1. Force explicit error visibility for logging if something breaks
error_reporting(E_ALL);
ini_set('display_errors', '0');

$callbackJSON = file_get_contents('php://input');
$callbackData = json_decode($callbackJSON, true);

// Log raw Safaricom inputs for safety audits
file_put_contents(av_log_path('callback_debug.log'), $callbackJSON . PHP_EOL, FILE_APPEND);

if (isset($callbackData['Body']['stkCallback'])) {
    $result = $callbackData['Body']['stkCallback'];
    $checkoutID = $result['CheckoutRequestID'];
    $resultCode = $result['ResultCode'];

    // --- ROOT DATABASE LINK CONFIGURATION LAYER ---
    if (file_exists('database_config/db.php')) {
        require_once 'database_config/db.php';
    } else {
        file_put_contents(av_log_path('callback_errors.log'), "[" . date('Y-m-d H:i:s') . "] Core Configuration Error: database_config/db.php missing in check_status.php" . PHP_EOL, FILE_APPEND);
        exit;
    }

    // Catch connection instances missing or broken mid-execution
    if (!isset($conn) || $conn->connect_error) {
        file_put_contents(av_log_path('callback_errors.log'), "[" . date('Y-m-d H:i:s') . "] DB Connection Missing or Failed in check_status.php" . PHP_EOL, FILE_APPEND);
        exit;
    }

    // Find the original transaction records to retrieve details for our stream
    $stmt = $conn->prepare("SELECT id, phone_number, amount FROM mpesa_transactions WHERE checkout_request_id = ? LIMIT 1");
    $stmt->bind_param("s", $checkoutID);
    $stmt->execute();
    $txRecord = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $db_tx_id = $txRecord ? (int)$txRecord['id'] : 0;
    $phone    = $txRecord ? $txRecord['phone_number'] : 'N/A';
    $amount   = $txRecord ? $txRecord['amount'] : '0.00';

    if ($resultCode == 0) {
        // --- CASE A: SUCCESSFUL TRANSACTION ---
        $receipt = "";
        if (isset($result['CallbackMetadata']['Item'])) {
            $meta = $result['CallbackMetadata']['Item'];
            foreach ($meta as $item) {
                if ($item['Name'] === 'MpesaReceiptNumber') {
                    $receipt = $item['Value'];
                    break;
                }
            }
        }
        
        // Update master logs
        $stmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'SUCCESS', result_code = ?, mpesa_receipt_number = ? WHERE checkout_request_id = ?");
        $stmt->bind_param("iss", $resultCode, $receipt, $checkoutID);
        $stmt->execute();
        $stmt->close();

        // PUSH TO QUEUE: Trigger real-time stream layout
        if ($db_tx_id > 0) {
            $queue_stmt = $conn->prepare("INSERT INTO mpesa_stream_queue (transaction_id, mpesa_code, phone, amount, tx_status, stream_status) VALUES (?, ?, ?, ?, 'SUCCESS', 'pending')");
            $queue_stmt->bind_param("isss", $db_tx_id, $receipt, $phone, $amount);
            $queue_stmt->execute();
            $queue_stmt->close();
        }

    } else {
        // --- CASE B: FAILED/CANCELLED TRANSACTION ---
        $stmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'FAILED', result_code = ? WHERE checkout_request_id = ?");
        $stmt->bind_param("is", $resultCode, $checkoutID);
        $stmt->execute();
        $stmt->close();

        // PUSH TO QUEUE: Trigger real-time layout failure update
        if ($db_tx_id > 0) {
            $failed_code = 'FAILED';
            $queue_stmt = $conn->prepare("INSERT INTO mpesa_stream_queue (transaction_id, mpesa_code, phone, amount, tx_status, stream_status) VALUES (?, ?, ?, ?, 'FAILED', 'pending')");
            $queue_stmt->bind_param("isss", $db_tx_id, $failed_code, $phone, $amount);
            $queue_stmt->execute();
            $queue_stmt->close();
        }
    }
    
    $conn->close();
}

// Acknowledge Safaricom response loop quickly
echo json_encode(["ResponseCode" => "0", "ResponseDesc" => "Callback received successfully"]);
?>
