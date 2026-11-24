<?php
// admin/daraja_helper.php

function triggerDarajaBalanceCheck($conn) {
    // 1. Load and decrypt settings from daraja_settings table
    $settings = [];
    $res = $conn->query("SELECT key_name, encrypted_value FROM daraja_settings");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            // Decrypt your database value using your project's decryption function
            $settings[$row['key_name']] = function_exists('decryptData') 
                ? decryptData($row['encrypted_value']) 
                : $row['encrypted_value']; 
        }
    }

    $consumerKey    = $settings['consumer_key'] ?? '';
    $consumerSecret = $settings['consumer_secret'] ?? '';
    $shortCode      = $settings['paybill'] ?? ''; 
    $initiatorName  = $settings['initiator_username'] ?? '';
    $initiatorPass  = $settings['initiator_password'] ?? '';

    if (empty($consumerKey) || empty($consumerSecret) || empty($shortCode)) {
        return false;
    }

    $baseUrl = 'https://api.safaricom.co.ke'; // Switch to sandbox if testing: https://sandbox.safaricom.co.ke

    // 2. Generate OAuth Access Token
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    $ch = curl_init("$baseUrl/oauth/v1/generate?grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);

    $authRes = json_decode($response);
    if (!isset($authRes->access_token)) return false;
    $accessToken = $authRes->access_token;

    // 3. Encrypt Initiator Password using Safaricom Certificate Standards
    // (If you already have a project function for this, use it. Otherwise standard OpenSSL PKCS1 padding:)
    $securityCredential = $initiatorPass;
    // Example encryption if cert file is present:
    /*
    $certPath = __DIR__ . '/cert.cer'; // path to Safaricom public certificate
    if (file_exists($certPath)) {
        $pubKey = openssl_pkey_get_public(file_get_contents($certPath));
        openssl_public_encrypt($initiatorPass, $encrypted, $pubKey, OPENSSL_PKCS1_PADDING);
        $securityCredential = base64_encode($encrypted);
    }
    */

    // 4. Send Account Balance Request
    $balanceUrl = "$baseUrl/mpesa/accountbalance/v1/query";
    
    $payload = [
        "Initiator" => $initiatorName,
        "SecurityCredential" => $securityCredential,
        "CommandID" => "AccountBalance",
        "PartyA" => $shortCode,
        "IdentifierType" => "4", 
        "Remarks" => "Admin Dashboard Live Balance Query",
        "QueueTimeoutURL" => "https://yourdomain.com/api/mpesa_timeout.php",
        "ResultURL" => "https://yourdomain.com/api/mpesa_result.php"
    ];

    $ch = curl_init($balanceUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: json' // or application/json
    ]);
    // Fix headers array
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

    return true;
}