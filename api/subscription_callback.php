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
require_once __DIR__ . '/../lib/notifications.php';

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
    $txLookup = $conn->prepare("SELECT user_id, settlement_id, amount FROM mpesa_transactions WHERE checkout_request_id = ? LIMIT 1");
    $txLookup->bind_param('s', $checkoutRequestId);
    $txLookup->execute();
    $subscriptionTx = $txLookup->get_result()->fetch_assoc();
    $txLookup->close();

    $result = av_finalize_subscription_payment(
        $conn,
        $checkoutRequestId,
        (int) $resultCode,
        $receiptNumber,
        $resultDesc
    );
    if (!$result['already_processed'] && $result['status'] === 'FAILED' && $subscriptionTx) {
        $ownerStmt = $conn->prepare("SELECT email, username FROM users WHERE id = ? LIMIT 1");
        $ownerStmt->bind_param('i', $subscriptionTx['user_id']);
        $ownerStmt->execute();
        $owner = $ownerStmt->get_result()->fetch_assoc();
        $ownerStmt->close();
        if ($owner) {
            av_notify_lifecycle((string)$owner['email'], (string)$owner['username'], 'AvenPay subscription payment was not completed', 'Subscription payment unsuccessful', 'Safaricom reported that your subscription payment did not complete. Your subscription has not been extended. You can try again from your AvenPay dashboard.', ['Amount attempted' => 'KES ' . number_format((float)$subscriptionTx['amount'], 2), 'Result' => $resultDesc], 'billing', 'Review subscription', 'https://avenpay.co.ke/dashboard');
        }
    }
    if (!$result['already_processed'] && $result['status'] === 'SUCCESS' && $subscriptionTx) {
        $ownerStmt = $conn->prepare("SELECT u.email, u.username, s.expiry_date FROM users u JOIN settlements s ON s.user_id = u.id WHERE u.id = ? AND s.id = ? LIMIT 1");
        $ownerStmt->bind_param('ii', $subscriptionTx['user_id'], $subscriptionTx['settlement_id']);
        $ownerStmt->execute();
        $owner = $ownerStmt->get_result()->fetch_assoc();
        $ownerStmt->close();
        if ($owner) {
            av_notify_lifecycle((string)$owner['email'], (string)$owner['username'], 'AvenPay subscription payment successful', 'Your subscription is active', 'Your subscription payment was confirmed and your settlement account access has been updated.', ['Amount paid' => 'KES ' . number_format((float)$subscriptionTx['amount'], 2), 'M-Pesa receipt' => $receiptNumber !== '' ? $receiptNumber : 'Confirmed', 'Expiry date' => (string)($owner['expiry_date'] ?? 'Lifetime / not set')], 'billing', 'View your dashboard', 'https://avenpay.co.ke/dashboard');
        }
    }
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
