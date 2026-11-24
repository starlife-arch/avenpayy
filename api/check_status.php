<?php
require_once __DIR__ . '/../lib/logger.php';
// check_status.php
// Consolidated Core Processing Engine (Handles AJAX Polls, Inbound Webhooks & Real-Time UI Updates)

error_reporting(E_ALL);
ini_set('display_errors', 0);

$dbPath = __DIR__ . '/../database_config/db.php';
if (is_file($dbPath)) {
    require_once $dbPath;
} else {
    file_put_contents(av_log_path('callback_errors.log'), "[" . date('Y-m-d H:i:s') . "] Core Configuration Error: database_config/db.php missing" . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    exit("Core engine configuration missing.");
}

if (!isset($conn) || $conn->connect_error) {
    file_put_contents(av_log_path('callback_errors.log'), "[" . date('Y-m-d H:i:s') . "] DB Connection Missing or Failed" . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    exit("Core engine database offline.");
}


// --- CORS HEADERS (Allow requests from your external frontend domain) ---
header("Access-Control-Allow-Origin: *"); // Or replace '*' with 'https://yourdomain.com'
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle browser preflight OPTIONS requests immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// =========================================================================
// ROUTE A: FRONTEND UI REAL-TIME EVENT ENGINE (?mode=live)
// =========================================================================
if (isset($_GET['mode']) && $_GET['mode'] === 'live') {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header("Access-Control-Allow-Origin: *");
    
    if (function_exists('apache_setenv')) { @apache_setenv('no-gzip', '1'); }
    @ini_set('zlib.output_compression', '0');

    while (true) {
        if (connection_status() !== CONNECTION_NORMAL || connection_aborted()) {
            $conn->close();
            exit;
        }

        $query = "SELECT * FROM mpesa_stream_queue WHERE stream_status = 'pending' ORDER BY queue_id ASC LIMIT 5";
        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $txId = (int)$row['transaction_id'];
                $chkQuery = $conn->query("SELECT checkout_request_id FROM mpesa_transactions WHERE id = $txId LIMIT 1");
                $chkRow = $chkQuery ? $chkQuery->fetch_assoc() : null;
                $checkoutId = $chkRow['checkout_request_id'] ?? '';

                $payload = [
                    'id'          => $txId,
                    'checkout_id' => $checkoutId,
                    'mpesa_code'  => !empty($row['mpesa_code']) ? $row['mpesa_code'] : 'N/A',
                    'phone'       => $row['phone'],
                    'amount'      => $row['amount'],
                    'status'      => $row['tx_status']
                ];

                echo "data: " . json_encode($payload) . "\n\n";
                
                $updateId = (int)$row['queue_id'];
                $conn->query("UPDATE mpesa_stream_queue SET stream_status = 'sent' WHERE queue_id = $updateId");
            }
            if (function_exists('ob_flush')) { @ob_flush(); }
            @flush();
        }
        sleep(1); 
    }
    exit;
}

// =========================================================================
// ROUTE B: INBOUND SAFARICOM STK CALLBACK PROCESSING (RAW POST PAYLOAD)
// =========================================================================
$callbackJSON = file_get_contents('php://input');
if (!empty($callbackJSON)) {
    file_put_contents(av_log_path('callback_debug.log'), "[" . date('Y-m-d H:i:s') . "] " . $callbackJSON . PHP_EOL, FILE_APPEND);
    $callbackData = json_decode($callbackJSON, true);

    if (isset($callbackData['Body']['stkCallback'])) {
        $result = $callbackData['Body']['stkCallback'];
        $checkoutID = $result['CheckoutRequestID'];
        $resultCode = (int)$result['ResultCode'];
        $resultDesc = isset($result['ResultDesc']) ? $result['ResultDesc'] : 'Transaction Failed / Unknown Error';
        $merchantID = $result['MerchantRequestID'] ?? NULL;

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("SELECT id, phone_number, amount, user_id, settlement_id, status FROM mpesa_transactions WHERE checkout_request_id = ? FOR UPDATE");
            $stmt->bind_param("s", $checkoutID);
            $stmt->execute();
            $txRecord = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$txRecord) {
                $conn->rollback();
                http_response_code(200);
                echo json_encode(["ResultDesc" => "Transaction not found", "ResultCode" => "1"]);
                exit;
            }

            if ($txRecord['status'] === 'SUCCESS' || $txRecord['status'] === 'FAILED') {
                $conn->rollback();
                http_response_code(200);
                echo json_encode(["ResultDesc" => "Already processed", "ResultCode" => "0"]);
                exit;
            }

            $db_tx_id     = (int)$txRecord['id'];
            $settlement_id= (int)$txRecord['settlement_id'];
            $phone        = $txRecord['phone_number'];
            $amount       = (float)$txRecord['amount'];

            if ($resultCode === 0) {
                // --- CASE A: SUCCESSFUL TRANSACTION ---
                $receipt = "";
                if (isset($result['CallbackMetadata']['Item'])) {
                    foreach ($result['CallbackMetadata']['Item'] as $item) {
                        if ($item['Name'] === 'MpesaReceiptNumber') { 
                            $receipt = trim($item['Value']); 
                            break; 
                        }
                    }
                }
                if (empty($receipt)) $receipt = 'SUCCESS';
                
                $stmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'SUCCESS', merchant_request_id = ?, result_code = ?, mpesa_receipt_number = ? WHERE checkout_request_id = ?");
                $stmt->bind_param("siss", $merchantID, $resultCode, $receipt, $checkoutID);
                $stmt->execute();
                $stmt->close();

                // UI STREAM UPDATE (Fixed with ON DUPLICATE KEY UPDATE)
                $queue_stmt = $conn->prepare("
                    INSERT INTO mpesa_stream_queue (transaction_id, mpesa_code, phone, amount, tx_status, stream_status) 
                    VALUES (?, ?, ?, ?, 'SUCCESS', 'pending')
                    ON DUPLICATE KEY UPDATE 
                        mpesa_code = VALUES(mpesa_code), 
                        tx_status = VALUES(tx_status), 
                        stream_status = 'pending'
                ");
                $queue_stmt->bind_param("issd", $db_tx_id, $receipt, $phone, $amount);
                $queue_stmt->execute();
                $queue_stmt->close();

                dispatchWebhook($conn, $db_tx_id, $settlement_id, $checkoutID, $receipt, $amount, $phone, $resultCode, 'SUCCESS');

            } else {
                // --- CASE B: FAILED / CANCELLED ---
                $stmt = $conn->prepare("UPDATE mpesa_transactions SET status = 'FAILED', merchant_request_id = ?, result_code = ?, mpesa_receipt_number = ? WHERE checkout_request_id = ?");
                $stmt->bind_param("siss", $merchantID, $resultCode, $resultDesc, $checkoutID);
                $stmt->execute();
                $stmt->close();

                // UI STREAM UPDATE (Fixed with ON DUPLICATE KEY UPDATE)
                $queue_stmt = $conn->prepare("
                    INSERT INTO mpesa_stream_queue (transaction_id, mpesa_code, phone, amount, tx_status, stream_status) 
                    VALUES (?, ?, ?, ?, 'FAILED', 'pending')
                    ON DUPLICATE KEY UPDATE 
                        mpesa_code = VALUES(mpesa_code), 
                        tx_status = VALUES(tx_status), 
                        stream_status = 'pending'
                ");
                $queue_stmt->bind_param("issd", $db_tx_id, $resultDesc, $phone, $amount);
                $queue_stmt->execute();
                $queue_stmt->close();

                dispatchWebhook($conn, $db_tx_id, $settlement_id, $checkoutID, $resultDesc, $amount, $phone, $resultCode, 'FAILED');
            }

            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            file_put_contents(av_log_path('callback_errors.log'), "[" . date('Y-m-d H:i:s') . "] Callback Exception: " . $e->getMessage() . PHP_EOL, FILE_APPEND);
        }
        
        $conn->close();
        http_response_code(200);
        echo json_encode(["ResultCode" => 0, "ResultDesc" => "Accepted"]);
        exit;
    }
}

// =========================================================================
// WEBHOOK DISPATCHER HELPER FUNCTION
// =========================================================================
function dispatchWebhook($conn, $tx_id, $settlement_id, $checkoutID, $receipt, $amount, $phone, $resultCode, $status) {
    if ($settlement_id <= 0) return;

    $query = $conn->prepare("SELECT callback_url FROM settlements WHERE id = ? AND is_active = 1 AND (expiry_date IS NULL OR expiry_date > NOW()) LIMIT 1");
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

// =========================================================================
// ROUTE C: FRONTEND AJAX STATUS POLL (?check_status=...)
// =========================================================================
if (isset($_GET['check_status'])) {
    header('Content-Type: application/json');
    
    $checkoutID = $conn->real_escape_string($_GET['check_status']);
    
    $query = $conn->query("SELECT t.id, t.status, t.mpesa_receipt_number, t.amount, t.result_code, t.user_id, t.settlement_id FROM mpesa_transactions t WHERE t.checkout_request_id = '$checkoutID'");
    $data = $query->fetch_assoc();

    if ($data) {
        $apiResponse = [
            "checkout_id"   => $checkoutID,
            "status"        => $data['status'],
            "success"       => ($data['status'] === 'SUCCESS'),
            "state"         => strtolower($data['status']),
            "mpesa_receipt" => (!empty($data['mpesa_receipt_number']) && $data['mpesa_receipt_number'] !== 'SUCCESS') 
                                ? $data['mpesa_receipt_number'] 
                                : 'N/A',
            "amount"        => (float)$data['amount']
        ];

        if (isset($data['result_code'])) {
            $apiResponse['result_code'] = (int)$data['result_code'];
        }
        if ($data['status'] === 'FAILED') {
            $apiResponse['mpesa_code'] = $data['mpesa_receipt_number'];
        }

        echo json_encode($apiResponse);
    } else {
        echo json_encode(["status" => "error", "message" => "Transaction not found"]);
    }
    exit;
}

header('Content-Type: application/json');
echo json_encode(["status" => "active"]);
exit;
?>