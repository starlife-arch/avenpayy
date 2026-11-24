<?php
require_once __DIR__ . '/../lib/require_admin.php';
require_once __DIR__ . '/config.php';

$accessToken = getAccessToken();
if (!$accessToken) {
    die("Error: Could not retrieve Access Token. Check error.log.");
}

$url = 'https://api.safaricom.co.ke/pulltransactions/v1/register';

$requestData = [
    "ShortCode"       => PAYBILL_NUMBER,
    "RequestType"     => "Pull",
    "NominatedNumber" => "254748600179", // Your specific admin/manager MSISDN
    "CallBackURL"     => "https://avenpay.co.ke/logs/confirmation.php"
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . trim($accessToken),
        'Content-Type: application/json'
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($requestData),
    CURLOPT_RETURNTRANSFER => true
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode<br>";
echo "<pre>";
print_r(json_decode($response, true));
echo "</pre>";
?>