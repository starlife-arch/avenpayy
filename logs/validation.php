<?php
require_once __DIR__ . '/../lib/callback_auth.php';
av_require_callback_token('c2b');
require_once __DIR__ . '/../database_config/db.php';

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$payload = json_decode(file_get_contents('php://input'), true);
$receipt = trim((string) ($payload['TransID'] ?? ''));
$walletId = trim((string) ($payload['BillRefNumber'] ?? ''));
$amount = filter_var($payload['TransAmount'] ?? null, FILTER_VALIDATE_FLOAT);

// Match the original PrintPay validation policy: accept structurally valid
// Paybill payments, then reconcile known wallet references in confirmation.php.
// Rejecting an unknown wallet here can cause Safaricom to reject a real payment.
$valid = is_array($payload)
    && $receipt !== ''
    && $walletId !== ''
    && $amount !== false
    && $amount > 0;

if (!$valid) {
    error_log('AvenPay C2B validation rejected malformed transaction details.');
}

echo json_encode([
    'ResultCode' => $valid ? 0 : 1,
    'ResultDesc' => $valid ? 'Accepted' : 'Invalid payment details'
]);
exit();
