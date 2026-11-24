<?php
// CLI-only diagnostic. Never prints database credentials.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("Not found\n");
}

require_once __DIR__ . '/../database_config/env.php';

$required = ['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'];
$missing = [];
foreach ($required as $name) {
    $value = (string) av_env($name, '');
    $present = $value !== '';
    echo $name . ': ' . ($present ? 'SET' : 'MISSING') . PHP_EOL;
    if (!$present) $missing[] = $name;
}

if ($missing) {
    echo 'RESULT: database environment is incomplete.' . PHP_EOL;
    exit(1);
}

mysqli_report(MYSQLI_REPORT_OFF);
$host = (string) av_env('DB_HOST', 'localhost');
$conn = @new mysqli($host, (string) av_env('DB_USER'), (string) av_env('DB_PASS'), (string) av_env('DB_NAME'));
if ($conn->connect_errno) {
    echo 'RESULT: database connection failed (MySQL error ' . (int) $conn->connect_errno . ').' . PHP_EOL;
    exit(2);
}
$conn->set_charset('utf8mb4');
echo 'RESULT: database connection OK.' . PHP_EOL;
echo 'MYSQL VERSION: ' . $conn->server_info . PHP_EOL;
$conn->close();
