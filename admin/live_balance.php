<?php
// admin/live_balance.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin_id'])) {
    exit(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
$config = include $_SERVER['DOCUMENT_ROOT'] . '/b2c/config.php';

function getLiveAdminBalance($conn, $config) {
    $consumerKey    = $config['consumer_key'] ?? '';
    $consumerSecret = $config['consumer_secret'] ?? '';
    $shortCode      = $config['short_code'] ?? '';
    $initiatorName  = $config['initiator_name'] ?? '';
    $certPath       = $config['cert_path'] ?? '';
    $tokenUrl       = $config['token_url'] ?? 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $baseUrl        = $config['base_url'] ?? 'https://api.safaricom.co.ke';

    if (empty($consumerKey) || empty($consumerSecret) || empty($shortCode)) {
        return null;
    }

    // 1. Generate OAuth Access Token
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $authRes = json_decode($response);
    if (!isset($authRes->access_token)) return null;
    $accessToken = $authRes->access_token;

    // 2. Fetch Initiator Password from daraja_settings or your database config securely
    // (Assuming you query the initiator_password row from daraja_settings and decrypt it if necessary)
    $passQuery = $conn->query("SELECT encrypted_value FROM daraja_settings WHERE key_name = 'initiator_password' LIMIT 1");
    $initiatorPass = '';
    if ($passQuery && $row = $passQuery->fetch_assoc()) {
        $initiatorPass = function_exists('decryptData') ? decryptData($row['encrypted_value']) : $row['encrypted_value'];
    }
    if (empty($initiatorPass)) return null;

    // 3. Encrypt Initiator Password using Safaricom Public Certificate
    $securityCredential = '';
    if (file_exists($certPath)) {
        $pubKey = openssl_pkey_get_public(file_get_contents($certPath));
        if ($pubKey) {
            openssl_public_encrypt($initiatorPass, $encrypted, $pubKey, OPENSSL_PKCS1_PADDING);
            $securityCredential = base64_encode($encrypted);
        }
    }
    if (empty($securityCredential)) return null;

    // 4. Send Account Balance Request to Safaricom
    $balanceUrl = "$baseUrl/mpesa/accountbalance/v1/query";
    
    $payload = [
        "Initiator" => $initiatorName,
        "SecurityCredential" => $securityCredential,
        "CommandID" => "AccountBalance",
        "PartyA" => $shortCode,
        "IdentifierType" => "4", // 4 for Shortcode
        "Remarks" => "Admin Live Balance Check",
        "QueueTimeoutURL" => $config['timeout_url'],
        "ResultURL" => $config['result_url']
    ];

    $ch = curl_init($balanceUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $apiResponse = curl_exec($ch);
    curl_close($ch);

    // Fetch latest updated utility balance from your database log table
    $balQuery = $conn->query("SELECT utility_balance FROM mpesa_b2c_transactions WHERE utility_balance IS NOT NULL ORDER BY id DESC LIMIT 1");
    if ($balQuery && $row = $balQuery->fetch_assoc()) {
        return (float)$row['utility_balance'];
    }

    return null;
}

// Handle AJAX request from admin dashboard
if (isset($_GET['action']) && $_GET['action'] === 'fetch_balance') {
    getLiveAdminBalance($conn, $config);
    
    // Return latest recorded utility balance
    $balQuery = $conn->query("SELECT utility_balance FROM mpesa_b2c_transactions WHERE utility_balance IS NOT NULL ORDER BY id DESC LIMIT 1");
    $balance = 0.00;
    if ($balQuery && $row = $balQuery->fetch_assoc()) {
        $balance = (float)$row['utility_balance'];
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => 'success', 'balance' => $balance]);
    exit();
}