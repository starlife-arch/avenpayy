<?php
require_once __DIR__ . '/../lib/logger.php';
// validation.php - Automatically accepts all incoming C2B transactions

header('Content-Type: application/json');

// Optional: Log validation requests to see who is paying
$payload = file_get_contents('php://input');
if (!empty($payload)) {
    file_put_contents(av_log_path('validation_raw.log'), "[" . date('Y-m-d H:i:s') . "] " . $payload . PHP_EOL, FILE_APPEND);
}

// ResultCode 0 tells Safaricom to ACCEPT the transaction
echo json_encode([
    "ResultCode" => 0,
    "ResultDesc" => "Accepted"
]);
exit;
?>