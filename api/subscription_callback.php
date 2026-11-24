<?php
require_once __DIR__ . '/../lib/callback_auth.php';
av_require_callback_token('sub');
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
if (!is_file($dbPath)) {
    http_response_code(500);
    exit(json_encode(['status' => 'error', 'message' => 'Database config missing']));
}
require_once $dbPath;
require_once __DIR__ . '/../lib/subscription_payment.php';

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
    $result = av_finalize_subscription_payment(
        $conn,
        $checkoutRequestId,
        (int) $resultCode,
        $receiptNumber,
        $resultDesc
    );
    echo json_encode([
        'status' => 'success',
        'message' => $result['already_processed']
            ? 'Callback already processed'
            : ($result['status'] === 'SUCCESS' ? 'Subscription activated/renewed successfully' : 'Payment failure recorded')
    ]);
} catch (Throwable $e) {
    error_log('AvenPay subscription callback failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Subscription payment could not be finalized. The callback can be safely retried.'
    ]);
}
exit();
