<?php
// logs/query_transaction.php
require_once __DIR__ . '/config.php';

$accessToken = getAccessToken(); // Your existing token generation function

$url = 'https://api.safaricom.co.ke/mpesa/transactionstatus/v1/query';

$curl_post_data = [
    'Initiator' => 'YOUR_API_INITIATOR_NAME',
    'SecurityCredential' => 'YOUR_ENCRYPTED_PASSWORD',
    'CommandID' => 'TransactionStatusQuery',
    'TransactionID' => 'RGH7X9Y1Z2', // The M-Pesa receipt code provided by the customer
    'PartyA' => '4022787',
    'IdentifierType' => '4', // 4 for Shortcode
    'ResultURL' => 'https://avenpay.co.ke/logs/status_callback.php',
    'QueueTimeOutURL' => 'https://avenpay.co.ke/logs/status_timeout.php',
    'Remarks' => 'Transaction Check',
    'DebuggingFlags' => ''
];

$data_string = json_encode($curl_post_data);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $data_string,
    CURLOPT_RETURNTRANSFER => true
]);

$response = curl_exec($ch);
curl_close($ch);

echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>