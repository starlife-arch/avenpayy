<?php
require_once __DIR__ . '/../../lib/ledger.php';
require_once __DIR__ . '/../../lib/callback_auth.php';
require_once __DIR__ . '/../../database_config/env.php';
// b2c/payout/withdraw.php
session_start(); // <-- Added session_start() to allow session authentication from fetch requests

$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
if (!file_exists($dbPath)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'The database configuration file is missing. Please contact support.']);
    exit();
}
require_once $dbPath;
require_once __DIR__ . '/../../lib/security.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { av_rate_limit_or_die('withdraw', 20, 60); }

class SecureVault {
    private static function vaultKey(): string {
        $key = trim((string) av_env('VAULT_KEY'));
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $key)) throw new RuntimeException('A valid AvenPay VAULT_KEY is not configured.');
        return hex2bin($key);
    } 
    private const METHOD = 'aes-256-cbc';

    public static function decrypt($data) {
        if (empty($data)) return null;
        $decoded = base64_decode($data, true);
        if ($decoded === false) return trim($data);
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if ($ivLength === false || strlen($decoded) <= $ivLength) return trim($data);
        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);
        $decrypted = openssl_decrypt($ciphertext, self::METHOD, self::vaultKey(), 0, $iv);
        return $decrypted !== false ? trim($decrypted) : trim($data);
    }
}

function getTransactionFee($conn, $serviceType, $amount) {
    $stmt = $conn->prepare("
        SELECT fee_type, fee_value 
        FROM transaction_fees 
        WHERE service_type = ? AND is_active = 1 AND ? >= min_amount AND ? <= max_amount 
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("sdd", $serviceType, $amount, $amount);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) {
            return ($res['fee_type'] === 'percentage') 
                ? ($amount * floatval($res['fee_value'])) / 100 
                : floatval($res['fee_value']);
        }
    }
    return 0.00;
}


function av_has_column($conn, $table, $col) {
    static $cache = [];
    $k = $table . '.' . $col;
    if (isset($cache[$k])) return $cache[$k];
    $st = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$st) return $cache[$k] = false;
    $st->bind_param("ss", $table, $col);
    $st->execute();
    $cache[$k] = (bool) $st->get_result()->fetch_row();
    $st->close();
    return $cache[$k];
}

// Atomically claim a still-pending reservation and give the money back (safe against double refunds).
function av_release_reservation($conn, $txId, $userId, $amount, $totalDeduction, $code, $desc, $raw) {
    $conn->begin_transaction();
    try {
        $claim = $conn->prepare("UPDATE mpesa_b2c_transactions SET result_code = ?, result_desc = ?, raw_response = ?, completed_at = NOW() WHERE id = ? AND result_code = 1000");
        $claim->bind_param("issi", $code, $desc, $raw, $txId);
        $claim->execute();
        $claimed = $claim->affected_rows > 0;
        $claim->close();
        if ($claimed) {
            av_wallet_apply($conn, (int)$userId, (float)$totalDeduction, 'payout_release', 'b2c_tx', (string)$txId, 'Payout not completed: ' . substr((string)$desc, 0, 120), false);
            $ref = $conn->prepare("UPDATE users SET total_b2c_withdrawn = GREATEST(0, total_b2c_withdrawn - ?) WHERE id = ?");
            $ref->bind_param("di", $amount, $userId);
            $ref->execute();
            $ref->close();
        }
        $conn->commit();
        return $claimed;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('AvenPay release_reservation failed tx ' . $txId . ': ' . $e->getMessage());
        return false;
    }
}

// Parse Input Data (Support both $_POST and JSON input payloads)
$inputData = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);
    if (is_array($jsonData)) {
        $inputData = array_merge($inputData, $jsonData);
    }
}

$user_id = null;
$wallet_id = '';
$userRes = null;
$isApiAuth = false;

// AUTHENTICATION: Check API Headers OR Web Session
$headers = function_exists('getallheaders') ? getallheaders() : [];
$normalizedHeaders = [];
foreach ($headers as $k => $v) {
    $normalizedHeaders[strtolower($k)] = $v;
}

$apiWalletId = $normalizedHeaders['x-wallet-id'] ?? $inputData['wallet_id'] ?? '';
$apiSecurityKey = $normalizedHeaders['x-security-credential'] ?? $inputData['security_credential'] ?? '';

if (!empty($apiWalletId) && !empty($apiSecurityKey)) {
    // API / cURL Mode: Authenticate using Wallet ID and Security Credential API Key
    $stmt = $conn->prepare("SELECT id, username, wallet_id, b2c_wallet_balance, b2c_withdrawal_pin, b2c_security_credential, use_custom_mpesa FROM users WHERE wallet_id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $apiWalletId);
        $stmt->execute();
        $userRes = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    if (!$userRes) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'The Wallet ID provided is invalid or does not exist.']);
        exit();
    }

    $storedCredential = trim((string)($userRes['b2c_security_credential'] ?? ''));
    if (empty($storedCredential) || (hash_equals($storedCredential, $apiSecurityKey) === false && $storedCredential !== $apiSecurityKey)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Access denied. The security credential or API key is incorrect.']);
        exit();
    }

    $user_id = $userRes['id'];
    $isApiAuth = true;

} elseif (isset($_SESSION['user_id'])) {
    // Dashboard Web Session Mode
    $user_id = $_SESSION['user_id'];
    $uStmt = $conn->prepare("SELECT id, username, wallet_id, b2c_wallet_balance, b2c_withdrawal_pin, use_custom_mpesa FROM users WHERE id = ? LIMIT 1");
    $uStmt->bind_param("i", $user_id);
    $uStmt->execute();
    $userRes = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    if (!$userRes) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Your user account could not be found.']);
        exit();
    }

    // Web Session Mode requires PIN verification
    $pinInput = trim($inputData['pin'] ?? '');
    if (empty($pinInput) || !preg_match('/^\d{4}$/', $pinInput)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Please provide a valid withdrawal PIN consisting of exactly 4 numbers.']);
        exit();
    }

    $hasPinLock = av_has_column($conn, 'users', 'pin_failed_attempts') && av_has_column($conn, 'users', 'pin_locked_until');
    if ($hasPinLock) {
        $lk = $conn->prepare("SELECT pin_locked_until FROM users WHERE id = ? AND pin_locked_until IS NOT NULL AND pin_locked_until > NOW()");
        $lk->bind_param("i", $user_id);
        $lk->execute();
        $locked = $lk->get_result()->fetch_assoc();
        $lk->close();
        if ($locked) {
            http_response_code(429);
            echo json_encode(['status' => 'error', 'message' => 'Too many incorrect PIN attempts. Withdrawals are locked for 15 minutes.']);
            exit();
        }
    }

    $hashedPin = $userRes['b2c_withdrawal_pin'] ?? '';
    if (empty($hashedPin) || !password_verify($pinInput, $hashedPin)) {
        if ($hasPinLock) {
            $bad = $conn->prepare("UPDATE users SET pin_locked_until = IF(pin_failed_attempts + 1 >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), pin_locked_until), pin_failed_attempts = pin_failed_attempts + 1 WHERE id = ?");
            $bad->bind_param("i", $user_id);
            $bad->execute();
            $bad->close();
            $rs = $conn->prepare("UPDATE users SET pin_failed_attempts = 0 WHERE id = ? AND pin_locked_until IS NOT NULL AND pin_locked_until > NOW()");
            $rs->bind_param("i", $user_id);
            $rs->execute();
            $rs->close();
        }
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'The withdrawal PIN you entered is incorrect. Please try again.']);
        exit();
    }
    if ($hasPinLock) {
        $ok = $conn->prepare("UPDATE users SET pin_failed_attempts = 0, pin_locked_until = NULL WHERE id = ?");
        $ok->bind_param("i", $user_id);
        $ok->execute();
        $ok->close();
    }
} else {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access. Please log in or provide valid security headers.']);
    exit();
}

$recipientPhoneInput = trim($inputData['phone'] ?? $inputData['recipient_phone'] ?? '');
$amount = floatval($inputData['amount'] ?? 0);
$commandID = trim($inputData['command_id'] ?? 'BusinessPayment');
$remarks = trim($inputData['remarks'] ?? 'Service Payout');
$occasion = trim($inputData['occasion'] ?? 'Bonus');

$allowedCommands = ['BusinessPayment', 'SalaryPayment', 'PromotionPayment'];
if (!in_array($commandID, $allowedCommands)) {
    $commandID = 'BusinessPayment';
}

$minWithdrawal = $isApiAuth ? 10 : 1; 

if (empty($recipientPhoneInput) || $amount < $minWithdrawal) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Please check the recipient phone number. Also, please note that the minimum withdrawal amount must be at least KES ' . $minWithdrawal . '.'
    ]);
    exit();
}

$recipientPhone = preg_replace('/[^0-9]/', '', $recipientPhoneInput);
if (strlen($recipientPhone) === 10 && strpos($recipientPhone, '0') === 0) {
    $recipientPhone = '254' . substr($recipientPhone, 1);
} elseif (strlen($recipientPhone) === 9 && (strpos($recipientPhone, '7') === 0 || strpos($recipientPhone, '1') === 0)) {
    $recipientPhone = '254' . $recipientPhone;
}

if (!preg_match('/^254(7|1)[0-9]{8}$/', $recipientPhone)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'The phone number format is invalid. Please use a format like 2547XXXXXXXX or 07XXXXXXXX.']);
    exit();
}

$withdrawalFee = getTransactionFee($conn, 'b2c_withdrawal', $amount);
$totalDeduction = $amount + $withdrawalFee;

$currentBalance = floatval($userRes['b2c_wallet_balance'] ?? 0);
if ($currentBalance < $totalDeduction) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error', 
        'message' => 'Your B2C wallet balance is insufficient to complete this transaction. Total required is KES ' . number_format($totalDeduction, 0) . ' (Withdrawal Amount: KES ' . number_format($amount, 0) . ' plus Fee: KES ' . number_format($withdrawalFee, 0) . '). Your current balance is KES ' . number_format($currentBalance, 0) . '.'
    ]);
    exit();
}

// ---- Email OTP for dashboard withdrawals (enable with WITHDRAW_OTP=1 in avenpay.env) ----
if (isset($pinInput) && av_env('WITHDRAW_OTP', '0') === '1') {
    require_once __DIR__ . '/../../lib/otp.php';
    $otpInput = preg_replace('/\D/', '', (string)($inputData['otp'] ?? ''));
    $otpEmail = (string)($userRes['email'] ?? '');
    if ($otpInput === '') {
        if ($otpEmail === '' || !av_otp_issue($conn, (int)$user_id, $otpEmail, (string)($userRes['username'] ?? ''), (string)$amount, $recipientPhone)) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'We could not send your verification code. Please try again shortly.']);
            exit();
        }
        echo json_encode(['status' => 'otp_required', 'message' => 'We emailed a 6-digit code to ' . preg_replace('/(?<=.).(?=[^@]*@)/', '*', $otpEmail) . '. Enter it to confirm this withdrawal.']);
        exit();
    }
    $otpResult = av_otp_verify($conn, (int)$user_id, $otpInput, (string)$amount, $recipientPhone);
    if ($otpResult !== 'ok') {
        http_response_code(401);
        $msgs = ['bad' => 'That code is incorrect.', 'expired' => 'That code has expired. Start the withdrawal again.', 'locked' => 'Too many wrong codes. Start the withdrawal again.', 'none' => 'No active code. Start the withdrawal again.'];
        echo json_encode(['status' => 'error', 'message' => $msgs[$otpResult] ?? 'Verification failed.']);
        exit();
    }
}

// ---- Daily withdrawal limit ----
$dailyLimit = (float) av_env('B2C_DAILY_LIMIT', 150000);
if ($dailyLimit > 0) {
    $dl = $conn->prepare("SELECT COALESCE(SUM(transaction_amount), 0) AS total FROM mpesa_b2c_transactions WHERE user_id = ? AND DATE(created_at) = CURDATE() AND result_code IN (0, 1000)");
    $dl->bind_param("i", $user_id);
    $dl->execute();
    $todayTotal = (float) ($dl->get_result()->fetch_assoc()['total'] ?? 0);
    $dl->close();
    if (($todayTotal + $amount) > $dailyLimit) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Daily withdrawal limit of KES ' . number_format($dailyLimit, 0) . ' would be exceeded.']);
        exit();
    }
}

// ---- Idempotency: the same key never pays out twice ----
$idemKey = substr(trim($normalizedHeaders['idempotency-key'] ?? ($inputData['idempotency_key'] ?? '')), 0, 64);
$hasIdem = av_has_column($conn, 'mpesa_b2c_transactions', 'idempotency_key');
if ($hasIdem && $idemKey !== '') {
    $ik = $conn->prepare("SELECT conversation_id, result_code FROM mpesa_b2c_transactions WHERE user_id = ? AND idempotency_key = ? LIMIT 1");
    $ik->bind_param("is", $user_id, $idemKey);
    $ik->execute();
    $prev = $ik->get_result()->fetch_assoc();
    $ik->close();
    if ($prev) {
        echo json_encode(['status' => 'success', 'duplicate' => true, 'conversation_id' => (strpos($prev['conversation_id'], 'PENDING-') === 0 ? null : $prev['conversation_id']), 'message' => 'This request was already submitted. No new payout was made.']);
        exit();
    }
}

$wallet_id = trim($userRes['wallet_id'] ?? '');

// Fetch B2C Configuration dynamically from daraja_b2c_settings
$b2cQuery = $conn->prepare("SELECT shortcode, initiator_name, consumer_key, consumer_secret, initiator_password FROM daraja_b2c_settings ORDER BY id ASC LIMIT 1");
$b2cQuery->execute();
$b2cConfig = $b2cQuery->get_result()->fetch_assoc();
$b2cQuery->close();

if (!$b2cConfig) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No B2C configuration settings were found in daraja_b2c_settings.']);
    exit();
}

$shortcode = trim($b2cConfig['shortcode']);
$initiatorName = trim($b2cConfig['initiator_name']);
$consumerKey = trim($b2cConfig['consumer_key']);
$consumerSecret = trim($b2cConfig['consumer_secret']);
$plainInitiatorPassword = trim($b2cConfig['initiator_password']);

// consumer_key is plaintext; consumer_secret and initiator_password are encrypted.
$consumerSecret = SecureVault::decrypt($consumerSecret);
$plainInitiatorPassword = SecureVault::decrypt($plainInitiatorPassword);
if ($consumerSecret === false || $consumerSecret === '' || $plainInitiatorPassword === false || $plainInitiatorPassword === '') {
    error_log('AvenPay B2C payout stopped: stored Daraja secrets could not be decrypted with VAULT_KEY.');
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'B2C credentials need to be saved again in the admin B2C settings before payouts can run.']);
    exit();
}

if (empty($consumerKey) || empty($consumerSecret) || empty($initiatorName) || empty($shortcode)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'The payment gateway configuration in daraja_b2c_settings is incomplete.']);
    exit();
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'] ?? 'yourdomain.com';
$baseUrl = $protocol . $domainName;

$config = [
    'env' => 'production',
    'consumer_key' => $consumerKey,
    'consumer_secret' => $consumerSecret,
    'short_code' => $shortcode,
    'initiator_name' => $initiatorName,
    'initiator_password' => $plainInitiatorPassword,
    'cert_path' => __DIR__ . '/ProductionCertificate.cer',
    'base_url' => 'https://api.safaricom.co.ke',
    'token_url' => 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials',
    'b2c_url' => 'https://api.safaricom.co.ke/mpesa/b2c/v1/paymentrequest',
    'result_url' => av_callback_url('/b2c/payout/result.php', 'b2c_result'),
    'timeout_url' => av_callback_url('/b2c/payout/timeout.php', 'b2c_timeout'),
];

require_once __DIR__ . '/token.php';
require_once __DIR__ . '/encrypt.php';

$accessToken = getAccessToken($config);
if (empty($accessToken)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to connect to the payment provider. Please try again shortly.']);
    exit();
}

$securityCredential = generateSecurityCredential($config['initiator_password'], $config['cert_path']);

$payload = [
    'InitiatorName'      => $config['initiator_name'],
    'SecurityCredential' => $securityCredential,
    'CommandID'          => $commandID,
    'Amount'             => (int)$amount,
    'PartyA'             => $config['short_code'],
    'PartyB'             => $recipientPhone,
    'Remarks'            => substr($remarks, 0, 100),
    'QueueTimeOutURL'    => $config['timeout_url'],
    'ResultURL'          => $config['result_url'],
    'Occasion'           => $occasion
];

// ---- RESERVE FUNDS FIRST (row-locked), THEN CALL SAFARICOM ----
$txId = null;
$pendingRef = 'PENDING-' . bin2hex(random_bytes(12));
$conn->begin_transaction();
try {
    $lock = $conn->prepare("SELECT b2c_wallet_balance FROM users WHERE id = ? FOR UPDATE");
    $lock->bind_param("i", $user_id);
    $lock->execute();
    $balRow = $lock->get_result()->fetch_assoc();
    $lock->close();

    if (!$balRow || floatval($balRow['b2c_wallet_balance']) < $totalDeduction) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Your B2C wallet balance is insufficient to complete this transaction.']);
        exit();
    }

    if ($hasIdem && $idemKey !== '') {
        $ins = $conn->prepare("INSERT INTO mpesa_b2c_transactions (user_id, wallet_id, conversation_id, transaction_amount, receiver_phone, command_id, result_code, result_desc, raw_response, idempotency_key, created_at) VALUES (?, ?, ?, ?, ?, ?, 1000, 'Reserved - awaiting provider', '', ?, NOW())");
        $ins->bind_param("issdsss", $user_id, $wallet_id, $pendingRef, $amount, $recipientPhone, $commandID, $idemKey);
    } else {
        $ins = $conn->prepare("INSERT INTO mpesa_b2c_transactions (user_id, wallet_id, conversation_id, transaction_amount, receiver_phone, command_id, result_code, result_desc, raw_response, created_at) VALUES (?, ?, ?, ?, ?, ?, 1000, 'Reserved - awaiting provider', '', NOW())");
        $ins->bind_param("issdss", $user_id, $wallet_id, $pendingRef, $amount, $recipientPhone, $commandID);
    }
    $ins->execute();
    $txId = $conn->insert_id;
    $ins->close();

    // Ledger-backed debit (amount + fee). Unique per payout, so it can never be debited twice.
    if (!av_wallet_apply($conn, (int)$user_id, -$totalDeduction, 'payout_reserve', 'b2c_tx', (string)$txId, 'B2C payout to ' . $recipientPhone, false)) {
        $conn->rollback();
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Your B2C wallet balance is insufficient to complete this transaction.']);
        exit();
    }
    $tw = $conn->prepare("UPDATE users SET total_b2c_withdrawn = total_b2c_withdrawn + ? WHERE id = ?");
    $tw->bind_param("di", $amount, $user_id);
    $tw->execute();
    $tw->close();
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('AvenPay payout reservation failed: ' . $e->getMessage());
    if ((int) $e->getCode() === 1062) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'A request with this Idempotency-Key is already being processed.']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Could not reserve funds for this payout. Please try again.']);
    }
    exit();
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $config['b2c_url']);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . trim($accessToken),
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $curlErrno = curl_errno($ch);
    curl_close($ch);
    // Errors that happen BEFORE the request leaves our server are safe to refund immediately.
    // Anything else (e.g. timeout) is ambiguous: keep funds reserved; the result/timeout callbacks or a manual review settle it.
    if (in_array($curlErrno, [6, 7, 35, 58, 60], true)) {
        av_release_reservation($conn, $txId, $user_id, $amount, $totalDeduction, 9001, 'Connection to provider failed before sending', '');
        http_response_code(502);
        echo json_encode(['status' => 'error', 'message' => 'Could not reach the payment provider. You were not charged. Please try again.']);
    } else {
        error_log('AvenPay payout ambiguous network error (curl ' . $curlErrno . ') tx ' . $txId);
        http_response_code(202);
        echo json_encode(['status' => 'pending', 'message' => 'Your payout is being processed. Check your transactions for the final status.']);
    }
    exit();
}
curl_close($ch);

$resData = json_decode($response, true);
$conversationID = !empty($resData['ConversationID']) ? trim($resData['ConversationID']) : null;
$originatorConversationID = !empty($resData['OriginatorConversationID']) ? trim($resData['OriginatorConversationID']) : null;
$responseCode = isset($resData['ResponseCode']) ? (int)$resData['ResponseCode'] : 1;
$desc = $resData['ResponseDescription'] ?? $resData['errorMessage'] ?? 'Processing';

if ($httpCode === 200 && $responseCode === 0) {
    $upd = $conn->prepare("UPDATE mpesa_b2c_transactions SET conversation_id = ?, originator_conversation_id = ?, result_desc = ?, raw_response = ? WHERE id = ?");
    $convSave = $conversationID ?: $pendingRef;
    $upd->bind_param("ssssi", $convSave, $originatorConversationID, $desc, $response, $txId);
    $upd->execute();
    $upd->close();

    echo json_encode([
        'status' => 'success',
        'conversation_id' => $conversationID,
        'message' => 'Disbursement initialized successfully! A total of KES ' . number_format($totalDeduction, 0) . ' has been reserved. You will be notified when the transfer completes.'
    ]);
} else {
    $errMsg = $resData['errorMessage'] ?? $resData['ResponseDescription'] ?? 'The disbursement request could not be completed at this time.';
    av_release_reservation($conn, $txId, $user_id, $amount, $totalDeduction, 9002, $errMsg, (string) $response);
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $errMsg]);
}
exit();
