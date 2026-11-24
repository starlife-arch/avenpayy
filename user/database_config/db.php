<?php
require_once dirname(__DIR__, 2) . '/database_config/env.php';

error_reporting(E_ALL);

$host     = av_env('DB_HOST', 'localhost');
$db_user  = av_env('DB_USER');
$db_pass  = av_env('DB_PASS');
$db_name  = av_env('DB_NAME');

try {
    $conn = new mysqli($host, $db_user, $db_pass, $db_name);
    $__dbErr = $conn->connect_error;
} catch (Throwable $e) {
    $conn = null;
    $__dbErr = $e->getMessage();
}

if (!$conn || $__dbErr) {
    error_log('AvenPay DB connection failed: ' . $__dbErr);
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode(["success" => false, "error" => "Service temporarily unavailable"]));
}

$conn->set_charset("utf8mb4");

date_default_timezone_set('Africa/Nairobi');
$conn->query("SET time_zone = '+03:00'");

require_once __DIR__ . '/../../lib/security.php';
av_security_bootstrap();
