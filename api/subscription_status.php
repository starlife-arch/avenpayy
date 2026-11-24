<?php
require_once __DIR__ . '/../database_config/env.php';
// /api/subscription_status.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
error_reporting(E_ALL);

$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database config missing']);
    exit();
}
require_once $dbPath;
require_once __DIR__ . '/../lib/subscription_payment.php';

class SecureVault {
    private static function vaultKey(): string { return (string) av_env('VAULT_KEY'); } 
    private const METHOD = 'aes-256-cbc';

    public static function decrypt($data) {
        if (empty($data)) return null;
        $decoded = base64_decode($data);
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if (strlen($decoded) < $ivLength) return $data;
        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);
        $decrypted = openssl_decrypt($ciphertext, self::METHOD, self::vaultKey(), 0, $iv);
        return $decrypted !== false ? trim($decrypted) : trim($data);
    }
}

// A user may only inspect transactions belonging to their own account.
if (empty($_SESSION['user_id']) || (int) $_SESSION['user_id'] < 1) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Please sign in to check this transaction.']);
    exit();
}
$user_id = (int) $_SESSION['user_id'];
$checkoutID = trim($_GET['check_status'] ?? $_GET['checkout_id'] ?? '');

if (empty($checkoutID)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing checkout ID']);
    exit();
}

$stmt = $conn->prepare("SELECT id, user_id, status, mpesa_receipt_number, amount FROM mpesa_transactions WHERE checkout_request_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("si", $checkoutID, $user_id);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$tx) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Transaction not found for your account.']);
    exit();
}

$status = strtoupper($tx['status']);

// If pending, live-query Daraja Query API
if ($status === 'PENDING') {
    $consumerKey = ''; $consumerSecret = ''; $passkey = ''; $paybill = '4022787';

    $uStmt = $conn->prepare("SELECT use_custom_mpesa FROM users WHERE id = ?");
    $uStmt->bind_param("i", $user_id);
    $uStmt->execute();
    $userRes = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    if ($userRes && !empty($userRes['use_custom_mpesa'])) {
        $cStmt = $conn->prepare("SELECT key_name, encrypted_value FROM user_daraja_settings WHERE user_id = ?");
        $cStmt->bind_param("i", $user_id);
        $cStmt->execute();
        $cRes = $cStmt->get_result();
        while ($row = $cRes->fetch_assoc()) {
            $k = $row['key_name'];
            $val = SecureVault::decrypt($row['encrypted_value']);
            if ($k === 'consumer_key') $consumerKey = $val;
            if ($k === 'consumer_secret') $consumerSecret = $val;
            if ($k === 'passkey') $passkey = $val;
            if ($k === 'paybill' && !empty($val)) $paybill = $val;
        }
        $cStmt->close();
    }

    if (empty($consumerKey) || empty($consumerSecret)) {
        $gRes = $conn->query("SELECT key_name, encrypted_value FROM daraja_settings");
        if ($gRes) {
            while ($row = $gRes->fetch_assoc()) {
                $k = $row['key_name'];
                $val = SecureVault::decrypt($row['encrypted_value']);
                if ($k === 'consumer_key') $consumerKey = $val;
                if ($k === 'consumer_secret') $consumerSecret = $val;
                if ($k === 'passkey') $passkey = $val;
                if ($k === 'paybill' && !empty($val)) $paybill = $val;
            }
        }
    }

    if (!empty($consumerKey) && !empty($consumerSecret)) {
        $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
        $ch = curl_init('https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $authRes = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $accessToken = $authRes['access_token'] ?? null;
        if ($accessToken) {
            $timestamp = date('YmdHis');
            $password = base64_encode($paybill . $passkey . $timestamp);

            $queryData = [
                'BusinessShortCode' => $paybill,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkoutID
            ];

            $ch2 = curl_init('https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query');
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($queryData));
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            $qRes = json_decode(curl_exec($ch2), true);
            curl_close($ch2);

            if (isset($qRes['ResultCode'])) {
                $resCode = (string)$qRes['ResultCode'];
                if (preg_match('/^-?\\d+$/', $resCode) && $resCode !== '4999') {
                    $receipt = $resCode === '0' ? (string) ($qRes['MpesaReceiptNumber'] ?? '') : '';
                    $resultDesc = (string) ($qRes['ResultDesc'] ?? '');
                    // Use the same atomic finalizer as the asynchronous Daraja callback.
                    // Never mark a payment successful without activating its owned settlement.
                    $finalized = av_finalize_subscription_payment(
                        $conn,
                        $checkoutID,
                        (int) $resCode,
                        $receipt,
                        $resultDesc
                    );
                    $status = $finalized['status'];
                    $refresh = $conn->prepare("SELECT mpesa_receipt_number FROM mpesa_transactions WHERE id = ? AND user_id = ? LIMIT 1");
                    $refresh->bind_param('ii', $tx['id'], $user_id);
                    $refresh->execute();
                    $fresh = $refresh->get_result()->fetch_assoc();
                    $refresh->close();
                    $tx['mpesa_receipt_number'] = $fresh['mpesa_receipt_number'] ?? null;
                }
            }
        }
    }
}

echo json_encode([
    'checkout_id' => $checkoutID,
    'status' => $status,
    'mpesa_receipt' => $tx['mpesa_receipt_number'] ?? 'N/A',
    'amount' => (float)$tx['amount']
]);
exit();