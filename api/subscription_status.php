<?php
require_once __DIR__ . '/../database_config/env.php';
require_once __DIR__ . '/../lib/daraja_environment.php';
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

class SecureVault {
    private const METHOD = 'aes-256-cbc';

    private static function encryptionKey(): string {
        $key = trim((string) av_env('VAULT_KEY'));
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $key)) {
            throw new RuntimeException('A valid AvenPay VAULT_KEY is not configured.');
        }
        return hex2bin($key);
    }

    public static function decrypt($data) {
        if (!is_string($data) || $data === '') return null;
        $decoded = base64_decode($data, true);
        if ($decoded === false) return null;
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if (strlen($decoded) <= $ivLength) return null;
        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);
        $decrypted = openssl_decrypt($ciphertext, self::METHOD, self::encryptionKey(), 0, $iv);
        return $decrypted !== false ? trim($decrypted) : null;
    }
}

// Force user_id to 1
$user_id = 1;
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
    echo json_encode(['status' => 'error', 'message' => 'Transaction not found for user ID 1']);
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
        $ch = curl_init(av_daraja_oauth_url());
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

            $ch2 = curl_init(av_daraja_endpoint('mpesa/stkpushquery/v1/query'));
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken, 'Content-Type: application/json']);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($queryData));
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
            $qRes = json_decode(curl_exec($ch2), true);
            curl_close($ch2);

            if (isset($qRes['ResultCode'])) {
                $resCode = (string)$qRes['ResultCode'];
                if ($resCode === '0') {
                    $receipt = !empty($qRes['MpesaReceiptNumber']) ? $qRes['MpesaReceiptNumber'] : 'SUCCESS';
                    
                    $up = $conn->prepare("UPDATE mpesa_transactions SET status = 'SUCCESS', result_code = 0, mpesa_receipt_number = ? WHERE id = ?");
                    $up->bind_param("si", $receipt, $tx['id']);
                    $up->execute();
                    $up->close();

                    $status = 'SUCCESS';
                    $tx['mpesa_receipt_number'] = $receipt;
                } elseif ($resCode !== '4999') {
                    $resultDesc = $qRes['ResultDesc'] ?? 'Transaction Failed';
                    $up = $conn->prepare("UPDATE mpesa_transactions SET status = 'FAILED', result_code = ?, mpesa_receipt_number = ? WHERE id = ?");
                    $up->bind_param("isi", $resCode, $resultDesc, $tx['id']);
                    $up->execute();
                    $up->close();

                    $status = 'FAILED';
                    $tx['mpesa_receipt_number'] = $resultDesc;
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