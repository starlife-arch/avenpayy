<?php
require_once __DIR__ . '/../lib/require_admin.php';
require_once 'config.php';

$accessToken = getAccessToken();

if (!$accessToken) {
    die(json_encode([
        "status" => "error",
        "message" => "Failed to obtain OAuth Access Token."
    ], JSON_PRETTY_PRINT));
}

$url = 'https://api.safaricom.co.ke/mpesa/c2b/v2/registerurl';

// Pointing directly to your logs folder where the files now live
$domain = 'https://avenpay.co.ke/logs';

$requestData = [
    'ShortCode'       => PAYBILL_NUMBER,
    'ResponseType'    => 'Completed',
    'ConfirmationURL' => $domain . '/confirmation.php',
    'ValidationURL'   => $domain . '/validation.php'
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . trim($accessToken),
        'Content-Type: application/json'
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

header('Content-Type: application/json');
echo json_encode([
    "message" => "URL Registration Updated to Logs Folder",
    "http_code" => $httpCode,
    "safaricom_response" => $result
], JSON_PRETTY_PRINT);
?>