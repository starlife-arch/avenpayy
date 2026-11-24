<?php
// Dynamic Domain and Environment Detection
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
$protocol = $isSecure ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptPath = dirname($_SERVER['SCRIPT_NAME'] ?? '');

// Base URL of your application determined from the browser request
$baseUrl = rtrim($protocol . $host . $scriptPath, '/\\');

// Determine environment based on domain name
if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
    define('MPESA_ENV', 'sandbox');
} else {
    define('MPESA_ENV', 'production');
}

// Daraja API Credentials
if (MPESA_ENV === 'production') {
    define('MPESA_CONSUMER_KEY', 'jWwGGh5VPpdpe03KNIR7G7gFif6Wd6CLnGGuI4qGSWRcHM7u');
    define('MPESA_CONSUMER_SECRET', 'udjmOJYgu7BvslraGzjyejAW6HLlgSMiVUPRKikygQJRECJKbjFOfTA6SQjuDKLS');
    define('MPESA_INITIATOR_NAME', 'KRwoma');
    define('MPESA_INITIATOR_PASSWORD', 'Kentah@42063403254#');
    define('MPESA_BASE_URL', 'https://api.safaricom.co.ke');
} else {
    define('MPESA_CONSUMER_KEY', 'jWwGGh5VPpdpe03KNIR7G7gFif6Wd6CLnGGuI4qGSWRcHM7u');
    define('MPESA_CONSUMER_SECRET', 'udjmOJYgu7BvslraGzjyejAW6HLlgSMiVUPRKikygQJRECJKbjFOfTA6SQjuDKLS');
    define('MPESA_INITIATOR_NAME', 'KRwoma');
    define('MPESA_INITIATOR_PASSWORD', 'Kentah@42063403254#');
    define('MPESA_BASE_URL', 'https://sandbox.safaricom.co.ke');
}

// Shortcode Details
define('MPESA_SENDER_SHORTCODE', '4022787');
define('MPESA_RECEIVER_SHORTCODE', '4560832');

// Dynamically generated callback URL based on current server request domain
define('MPESA_CALLBACK_URL', $baseUrl . '/callback.php');