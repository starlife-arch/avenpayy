<?php
require_once __DIR__ . '/../lib/callback_auth.php';
require_once __DIR__ . '/../database_config/env.php';
// stk_push.php

require_once 'database_config/db.php';
require_once __DIR__ . '/../lib/security.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { av_rate_limit_or_die('api_stk', 300, 60); }

// --- SECURE DECRYPTION LOGIC ---
class SecureVault
{
    private const METHOD = 'aes-256-cbc';

    /** AvenPay uses only its own private VAULT_KEY. No PrintPay fallback. */
    private static function vaultKeys(): array
    {
        $key = trim((string) av_env('VAULT_KEY'));
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $key)) {
            throw new RuntimeException('VAULT_KEY must be a 64-character hexadecimal AvenPay key.');
        }
        return [$key];
    }

    private static function encryptionKey(): string
    {
        $keys = self::vaultKeys();
        if (!$keys) {
            throw new RuntimeException('No Daraja encryption key is configured.');
        }
        return $keys[0];
    }

    public static function encrypt(string $plainText): string
    {
        if ($plainText === '') return '';

        $ivLength = openssl_cipher_iv_length(self::METHOD);
        $iv = random_bytes($ivLength);
        $ciphertext = openssl_encrypt(
            $plainText,
            self::METHOD,
            self::encryptionKey(),
            0,
            $iv
        );
        if ($ciphertext === false) {
            throw new RuntimeException('Daraja credential encryption failed.');
        }
        return base64_encode($iv . $ciphertext);
    }

    public static function decrypt($data)
    {
        if (!is_string($data) || $data === '') return null;
        $decoded = base64_decode($data, true);
        if ($decoded === false) return null;

        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if (strlen($decoded) <= $ivLength) return null;

        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);

        foreach (self::vaultKeys() as $key) {
            $plainText = openssl_decrypt($ciphertext, self::METHOD, $key, 0, $iv);
            if ($plainText !== false) return trim($plainText);
        }
        return null;
    }
}

// --- CORS HEADERS ---
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// --- DARAJA CONFIGURATION ---
function getDarajaConfig($conn, $user_id = null) {
    if ($user_id) {
        $stmt = $conn->prepare("SELECT use_custom_mpesa FROM users WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $userRes = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($userRes && !empty($userRes['use_custom_mpesa'])) {
                $stmt2 = $conn->prepare(
                    "SELECT key_name, encrypted_value
                     FROM user_daraja_settings
                     WHERE user_id = ?"
                );

                if ($stmt2) {
                    $stmt2->bind_param("i", $user_id);
                    $stmt2->execute();
                    $res = $stmt2->get_result();

                    $customSettings = [];
                    while ($row = $res->fetch_assoc()) {
                        $customSettings[$row['key_name']] =
                            trim((string) SecureVault::decrypt($row['encrypted_value']));
                    }
                    $stmt2->close();

                    if (!empty($customSettings['consumer_key']) && !empty($customSettings['consumer_secret']) && !empty($customSettings['paybill']) && !empty($customSettings['passkey'])) {
                        return [
                            'consumer_key'    => $customSettings['consumer_key'] ?? '',
                            'consumer_secret' => $customSettings['consumer_secret'] ?? '',
                            'paybill'         => $customSettings['paybill'] ?? '',
                            'passkey'         => $customSettings['passkey'] ?? ''
                        ];
                    }
                }
            }
        }
    }

    $settings = [];
    $res = $conn->query("SELECT key_name, encrypted_value FROM daraja_settings");

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $settings[$row['key_name']] =
                trim((string) SecureVault::decrypt($row['encrypted_value']));
        }
    }

    return $settings;
}

function getAccessToken($conn, $user_id = null) {
    $cfg = getDarajaConfig($conn, $user_id);

    if (empty($cfg['consumer_key']) || empty($cfg['consumer_secret'])) {
        return '';
    }

    $ch = curl_init(
        'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials'
    );

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Basic ' . base64_encode(
            $cfg['consumer_key'] . ':' . $cfg['consumer_secret']
        )
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response ?: '', true);
    if (empty($result['access_token'])) {
        error_log('AvenPay Daraja OAuth failed; HTTP ' . $httpCode . ($curlError !== '' ? '; cURL: ' . $curlError : '') . '; provider message: ' . (string)($result['errorMessage'] ?? $result['error'] ?? 'No access token returned'));
    }
    return $result['access_token'] ?? '';
}

function initiateSTK($phone, $amount, $settlement, $token, $conn, $user_id = null) {
    $cfg = getDarajaConfig($conn, $user_id);
    $ts = date('YmdHis');
    $isTill = (($settlement['type'] ?? '') === 'till');

    // Build callback URL from the current request host.
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
    );
    $protocol = $isHttps ? 'https://' : 'http://';
    $currentHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $callbackUrl = av_callback_url('/api/callback.php', 'stk');

    $payload = [
        'BusinessShortCode' => $cfg['paybill'] ?? '',
        'Password' => base64_encode(
            ($cfg['paybill'] ?? '') . ($cfg['passkey'] ?? '') . $ts
        ),
        'Timestamp' => $ts,
        'TransactionType' => $isTill
            ? 'CustomerBuyGoodsOnline'
            : 'CustomerPayBillOnline',
        'Amount' => $amount,
        'PartyA' => $phone,
        'PartyB' => $settlement['shortcode'] ?? '',
        'PhoneNumber' => $phone,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => $isTill
            ? 'Payment'
            : ($settlement['account_number'] ?? ''),
        'TransactionDesc' => 'Aggregated Payment'
    ];

    $ch = curl_init(
        'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
    );

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response ?: '', true) ?: [];
}

function checkStatus($checkoutRequestID, $token, $conn, $user_id = null) {
    $cfg = getDarajaConfig($conn, $user_id);
    $ts = date('YmdHis');

    $queryData = [
        'BusinessShortCode' => $cfg['paybill'] ?? '',
        'Password' => base64_encode(
            ($cfg['paybill'] ?? '') . ($cfg['passkey'] ?? '') . $ts
        ),
        'Timestamp' => $ts,
        'CheckoutRequestID' => $checkoutRequestID
    ];

    $ch = curl_init(
        'https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query'
    );

    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($queryData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response ?: '', true) ?: [];
}

// --- AJAX STATUS CHECK ---
if (isset($_GET['check_status'])) {
    header('Content-Type: application/json');

    $checkoutID = trim((string) $_GET['check_status']);

    $stmt = $conn->prepare(
        "SELECT id, status, mpesa_receipt_number, amount, result_code, user_id
         FROM mpesa_transactions
         WHERE checkout_request_id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Unable to check transaction status."
        ]);
        exit;
    }

    $stmt->bind_param("s", $checkoutID);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($data) {
        $user_id = (int) $data['user_id'];

        if ($data['status'] === 'PENDING') {
            $token = getAccessToken($conn, $user_id);

            if ($token !== '') {
                $statusRes = checkStatus($checkoutID, $token, $conn, $user_id);

                if (isset($statusRes['ResultCode'])) {
                    $resCode = (string) $statusRes['ResultCode'];

                    if ($resCode === '0') {
                        $receipt = !empty($statusRes['MpesaReceiptNumber'])
                            ? $statusRes['MpesaReceiptNumber']
                            : 'SUCCESS';

                        $updateStmt = $conn->prepare(
                            "UPDATE mpesa_transactions
                             SET status = 'SUCCESS',
                                 result_code = 0,
                                 mpesa_receipt_number = ?
                             WHERE checkout_request_id = ?"
                        );

                        if ($updateStmt) {
                            $updateStmt->bind_param("ss", $receipt, $checkoutID);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }

                        $data['status'] = 'SUCCESS';
                        $data['mpesa_receipt_number'] = $receipt;
                        $data['result_code'] = 0;

                        $queueStmt = $conn->prepare(
                            "INSERT IGNORE INTO mpesa_stream_queue
                             (transaction_id, stream_status)
                             VALUES (?, 'sent')"
                        );

                        if ($queueStmt) {
                            $txId = (int) $data['id'];
                            $queueStmt->bind_param("i", $txId);
                            $queueStmt->execute();
                            $queueStmt->close();
                        }
                    } elseif ($resCode !== '4999' && $resCode !== '') {
                        $resultDesc = !empty($statusRes['ResultDesc'])
                            ? $statusRes['ResultDesc']
                            : 'Transaction failed or returned an unknown error.';
                        $resultCode = (int) $resCode;

                        $updateStmt = $conn->prepare(
                            "UPDATE mpesa_transactions
                             SET status = 'FAILED',
                                 result_code = ?,
                                 mpesa_receipt_number = ?
                             WHERE checkout_request_id = ?"
                        );

                        if ($updateStmt) {
                            $updateStmt->bind_param(
                                "iss",
                                $resultCode,
                                $resultDesc,
                                $checkoutID
                            );
                            $updateStmt->execute();
                            $updateStmt->close();
                        }

                        $data['status'] = 'FAILED';
                        $data['mpesa_receipt_number'] = $resultDesc;
                        $data['result_code'] = $resultCode;

                        $queueStmt = $conn->prepare(
                            "INSERT IGNORE INTO mpesa_stream_queue
                             (transaction_id, stream_status)
                             VALUES (?, 'sent')"
                        );

                        if ($queueStmt) {
                            $txId = (int) $data['id'];
                            $queueStmt->bind_param("i", $txId);
                            $queueStmt->execute();
                            $queueStmt->close();
                        }
                    }
                }
            }
        }

        $apiResponse = [
            "checkout_id" => $checkoutID,
            "status" => $data['status'],
            "mpesa_receipt" => (
                !empty($data['mpesa_receipt_number']) &&
                $data['mpesa_receipt_number'] !== 'SUCCESS'
            ) ? $data['mpesa_receipt_number'] : 'N/A',
            "amount" => (float) $data['amount']
        ];

        if (isset($data['result_code'])) {
            $apiResponse['result_code'] = (int) $data['result_code'];
        }

        if ($data['status'] === 'FAILED') {
            $apiResponse['mpesa_code'] = $data['mpesa_receipt_number'];
        }

        echo json_encode($apiResponse);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => "Transaction not found"
        ]);
    }

    exit;
}

// --- POST PAYMENT PROCESSING ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header('Content-Type: application/json');

    $phoneInput = $_POST['phone_number'] ?? $_POST['phone'] ?? '';
    $phone = preg_replace('/[^0-9]/', '', (string) $phoneInput);

    if (substr($phone, 0, 1) === '0') {
        $phone = '254' . substr($phone, 1);
    }

    $amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 0;
    $x_api_key = isset($_POST['x_api_key'])
        ? trim((string) $_POST['x_api_key'])
        : '';

    // ---- Payment-link checkout: the key and amount come from OUR database, never from the browser ----
    $linkId = null;
    $slugIn = isset($_POST['slug']) ? preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $_POST['slug'])) : '';
    if ($slugIn !== '') {
        require_once __DIR__ . '/../lib/payment_links.php';
        $ls = $conn->prepare("SELECT * FROM payment_links WHERE slug = ? LIMIT 1");
        $ls->bind_param("s", $slugIn);
        $ls->execute();
        $L = $ls->get_result()->fetch_assoc();
        $ls->close();
        if (!$L) { http_response_code(404); echo json_encode(["status" => "error", "message" => "Payment link not found."]); exit; }
        $why = av_link_unavailable_reason($conn, $L);
        if ($why !== '') { http_response_code(410); echo json_encode(["status" => "error", "message" => $why]); exit; }
        [$linkAmount, $amtErr] = av_link_resolve_amount($L, $amount);
        if ($amtErr !== '') { http_response_code(400); echo json_encode(["status" => "error", "message" => $amtErr]); exit; }
        $amount = $linkAmount;
        $x_api_key = (string) $L['x_api_key'];
        $linkId = (int) $L['id'];
    }
    $mode = isset($_POST['mode']) ? trim((string) $_POST['mode']) : 'live';

    if ($x_api_key === '') {
        echo json_encode([
            "status" => "error",
            "message" => "X-API-KEY required. Generate it from settlement accounts."
        ]);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT * FROM settlements WHERE x_api_key = ? LIMIT 1"
    );

    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Unable to verify the settlement account."
        ]);
        exit;
    }

    $stmt->bind_param("s", $x_api_key);
    $stmt->execute();
    $settlement = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$settlement) {
        echo json_encode([
            "status" => "error",
            "message" => "Invalid X-API-KEY."
        ]);
        exit;
    }

    /*
- Suspended-account check.
     */
    $userStmt = $conn->prepare(
        "SELECT status FROM users WHERE id = ? LIMIT 1"
    );

    if (!$userStmt) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Unable to verify your account status. Please contact customer care."
        ]);
        exit;
    }

    $settlementUserId = (int) $settlement['user_id'];
    $userStmt->bind_param("i", $settlementUserId);
    $userStmt->execute();
    $account = $userStmt->get_result()->fetch_assoc();
    $userStmt->close();

    if (!$account) {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "Your account could not be verified. Please contact customer care."
        ]);
        exit;
    }

    if (strtolower(trim((string) $account['status'])) === 'suspended') {
        http_response_code(403);
        echo json_encode([
            "status" => "error",
            "message" => "STK Push cannot be performed because your account is suspended. Please contact customer care for assistance."
        ]);
        exit;
    }

    $currentDateTime = date('Y-m-d H:i:s');

    if (
        $settlement['is_active'] == 1 &&
        !empty($settlement['expiry_date']) &&
        $settlement['expiry_date'] <= $currentDateTime
    ) {
        $updateStmt = $conn->prepare(
            "UPDATE settlements
             SET is_active = 0, status = 'expired'
             WHERE id = ?"
        );

        if ($updateStmt) {
            $settlementId = (int) $settlement['id'];
            $updateStmt->bind_param("i", $settlementId);
            $updateStmt->execute();
            $updateStmt->close();
        }

        $settlement['is_active'] = 0;
        $settlement['status'] = 'expired';
    }

    if (($settlement['status'] ?? '') === 'expired') {
        echo json_encode([
            "status" => "error",
            "message" => "Your API subscription has expired. Please renew it."
        ]);
        exit;
    }

    $user_id = (int) $settlement['user_id'];
    $settlement_id = (int) $settlement['id'];

    $usageStmt = $conn->prepare(
        "SELECT SUM(test_stk_count) AS total_tests
         FROM settlements
         WHERE user_id = ?"
    );

    if (!$usageStmt) {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Unable to verify your test usage."
        ]);
        exit;
    }

    $usageStmt->bind_param("i", $user_id);
    $usageStmt->execute();
    $usage = $usageStmt->get_result()->fetch_assoc();
    $usageStmt->close();

    $totalTests = (int) ($usage['total_tests'] ?? 0);

    if ($settlement['is_active'] != 1 && $totalTests >= 2) {
        echo json_encode([
            "status" => "error",
            "message" => "This settlement account is inactive and you have used your 2 free test trials."
        ]);
        exit;
    }

    if ($mode === 'test') {
        if ($totalTests >= 2) {
            echo json_encode([
                "status" => "error",
                "message" => "Sandbox limit reached (2/2). Please activate your account."
            ]);
            exit;
        }

        $updateStmt = $conn->prepare(
            "UPDATE settlements
             SET test_stk_count = test_stk_count + 1
             WHERE id = ?"
        );

        if ($updateStmt) {
            $updateStmt->bind_param("i", $settlement_id);
            $updateStmt->execute();
            $updateStmt->close();
        }

        echo json_encode([
            "status" => "success",
            "message" => "Sandbox STK pushed successfully.",
            "checkout_id" => "TEST_" . bin2hex(random_bytes(8))
        ]);
        exit;
    }

    if ($settlement['is_active'] != 1) {
        if ($totalTests >= 2) {
            echo json_encode([
                "status" => "error",
                "message" => "Your 2 free sandbox trials have been used. Please activate your account."
            ]);
            exit;
        }

        $updateStmt = $conn->prepare(
            "UPDATE settlements
             SET test_stk_count = test_stk_count + 1
             WHERE id = ?"
        );

        if ($updateStmt) {
            $updateStmt->bind_param("i", $settlement_id);
            $updateStmt->execute();
            $updateStmt->close();
        }
    }

    $token = getAccessToken($conn, $user_id);

    if ($token === '') {
        http_response_code(502);
        $config = getDarajaConfig($conn, $user_id);
        $missing = [];
        foreach (['consumer_key', 'consumer_secret', 'paybill', 'passkey'] as $requiredKey) {
            if (empty($config[$requiredKey])) $missing[] = $requiredKey;
        }
        echo json_encode([
            "status" => "error",
            "message" => $missing
                ? "Daraja gateway credentials are incomplete. Please ask the administrator to check: " . implode(', ', $missing) . "."
                : "Safaricom authentication is temporarily unavailable. Please try again later."
        ]);
        exit;
    }

    $res = initiateSTK(
        $phone,
        $amount,
        $settlement,
        $token,
        $conn,
        $user_id
    );

    if (!empty($res['CheckoutRequestID'])) {
        $insertStmt = $conn->prepare(
            "INSERT INTO mpesa_transactions
                (checkout_request_id, phone_number, amount, status, user_id, settlement_id, payment_link_id)
             VALUES (?, ?, ?, 'PENDING', ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                amount = VALUES(amount)"
        );

        if (!$insertStmt) {
            http_response_code(500);
            echo json_encode([
                "status" => "error",
                "message" => "STK Push was initiated, but the transaction could not be saved. Please contact customer care."
            ]);
            exit;
        }

        $checkoutRequestId = $res['CheckoutRequestID'];
        $insertStmt->bind_param(
            "ssdisi",
            $checkoutRequestId,
            $phone,
            $amount,
            $user_id,
            $settlement_id,
            $linkId
        );
        $insertStmt->execute();
        $insertStmt->close();

        echo json_encode([
            "status" => "success",
            "checkout_id" => $checkoutRequestId
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => $res['errorMessage']
                ?? $res['ResponseDescription']
                ?? $res['ResultDesc']
                ?? "STK Push initialization failed. Please try again or contact customer care."
        ]);
    }

    exit;
}
?>