<?php
require_once __DIR__ . '/../lib/cron_guard.php';
av_cron_guard();
require_once __DIR__ . '/../lib/logger.php';
// Force the script to change to its own directory for file resolution
chdir(__DIR__);

// Use the absolute path to your database configuration
require_once __DIR__ . '/database_config/db.php';

// Log script execution
file_put_contents(av_log_path('dispatch_debug.log'), "[" . date('Y-m-d H:i:s') . "] Trigger-fed dispatcher executed.\n", FILE_APPEND);

// 1. Fetch pending webhook items inserted by the trigger and join to get checkout_id and result_code
$sql = "SELECT w.id, w.webhook_url, t.settlement_id, t.user_id, t.checkout_request_id, t.mpesa_receipt_number, t.amount, t.phone_number, t.result_code, t.status 
        FROM webhook_queue w 
        JOIN mpesa_transactions t ON w.transaction_id = t.id 
        WHERE w.status = 'PENDING' AND w.attempts < 5";

$result = $conn->query($sql);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $id = (int)$row['id'];
        $url = trim($row['webhook_url'] ?? '');

        if (empty($url)) {
            $conn->query("UPDATE webhook_queue SET status = 'FAILED: No URL', last_attempt = NOW(), attempts = attempts + 1 WHERE id = $id");
            file_put_contents(av_log_path('dispatch_debug.log'), "[" . date('Y-m-d H:i:s') . "] ID $id Failed: Webhook URL is empty.\n", FILE_APPEND);
            continue;
        }

        // 2. Build the exact blueprint payload structure requested
        $payloadData = [
            "checkout_id"   => $row['checkout_request_id'] ?? '',
            "status"        => $row['status'] ?? 'SUCCESS',
            "mpesa_receipt" => $row['mpesa_receipt_number'] ?? '',
            "amount"        => isset($row['amount']) ? (float)$row['amount'] : 0.00,
            "phone"         => $row['phone_number'] ?? '',
            "result_code"   => isset($row['result_code']) ? (int)$row['result_code'] : 0
        ];

        $updatedPayload = json_encode($payloadData);

        // 3. Send (signed, SSRF-guarded) to the merchant's registered webhook endpoint
        require_once __DIR__ . '/../lib/webhooks.php';
        $secret = av_webhook_secret((int)($row['settlement_id'] ?? 0), (int)($row['user_id'] ?? 0));
        [$httpCode, $error, $response] = av_webhook_send($url, $updatedPayload, $secret);
        if (strpos((string)$error, 'Blocked:') === 0) {
            $conn->query("UPDATE webhook_queue SET status = 'FAILED: Unsafe URL', last_attempt = NOW(), attempts = 5 WHERE id = $id");
            av_log('dispatch_debug.log', "ID $id blocked: $error");
            continue;
        }

        // 4. Update queue status based on HTTP result
        if ($httpCode >= 200 && $httpCode < 300) {
            $conn->query("UPDATE webhook_queue SET status = 'SENT', last_attempt = NOW(), attempts = attempts + 1 WHERE id = $id");
            file_put_contents(av_log_path('dispatch_debug.log'), "[" . date('Y-m-d H:i:s') . "] ID $id successfully SENT to $url\n", FILE_APPEND);
        } else {
            $logMsg = sprintf("[%s] ID %d Failed | URL: %s | Code: %d | cURL Error: %s | Response: %s\n", 
                            date('Y-m-d H:i:s'), $id, $url, $httpCode, $error, $response);
            file_put_contents(av_log_path('dispatch_debug.log'), $logMsg, FILE_APPEND);
            
            $conn->query("UPDATE webhook_queue SET status = 'FAILED', last_attempt = NOW(), attempts = attempts + 1 WHERE id = $id");
        }
    }
} else {
    file_put_contents(av_log_path('dispatch_debug.log'), "[" . date('Y-m-d H:i:s') . "] DB Error: " . $conn->error . "\n", FILE_APPEND);
}

$conn->close();
?>