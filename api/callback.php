<?php
require_once __DIR__ . '/../lib/callback_auth.php';
av_require_callback_token('stk');
require_once __DIR__ . '/../lib/mpesa_finalizer.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
if (!is_file($dbPath)) {
    http_response_code(500);
    exit(json_encode(['ResultDesc' => 'Database config file missing', 'ResultCode' => '1']));
}
require_once $dbPath;

$rawInput = file_get_contents('php://input');
if (!is_string($rawInput) || $rawInput === '') {
    http_response_code(400);
    exit(json_encode(['ResultDesc' => 'Empty payload', 'ResultCode' => '1']));
}
$data = json_decode($rawInput, true);
if (!isset($data['Body']['stkCallback']) || !is_array($data['Body']['stkCallback'])) {
    http_response_code(400);
    exit(json_encode(['ResultDesc' => 'Invalid callback payload', 'ResultCode' => '1']));
}

$callback = $data['Body']['stkCallback'];
$checkoutId = trim((string) ($callback['CheckoutRequestID'] ?? ''));
$resultCode = filter_var($callback['ResultCode'] ?? null, FILTER_VALIDATE_INT);
$merchantId = isset($callback['MerchantRequestID']) ? (string) $callback['MerchantRequestID'] : null;
$resultDesc = trim((string) ($callback['ResultDesc'] ?? 'Transaction failed or returned an unknown error.'));

if ($checkoutId === '' || $resultCode === false || $resultCode === null) {
    http_response_code(400);
    exit(json_encode(['ResultDesc' => 'Callback is missing transaction details', 'ResultCode' => '1']));
}

$receipt = '';
if ($resultCode === 0) {
    foreach (($callback['CallbackMetadata']['Item'] ?? []) as $item) {
        if (($item['Name'] ?? '') === 'MpesaReceiptNumber') {
            $receipt = (string) ($item['Value'] ?? '');
            break;
        }
    }
} else {
    $receipt = $resultDesc;
}

try {
    $result = av_finalize_mpesa_transaction(
        $conn,
        $checkoutId,
        (int) $resultCode,
        $receipt,
        $merchantId
    );
    http_response_code(200);
    echo json_encode([
        'ResultDesc' => $result['already_processed'] ? 'Already processed' : 'Service accepted successfully',
        'ResultCode' => '0'
    ]);
} catch (Throwable $e) {
    error_log('AvenPay M-Pesa callback finalization failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ResultDesc' => 'Transaction could not be finalized. Please retry the callback.',
        'ResultCode' => '1'
    ]);
}
exit();
