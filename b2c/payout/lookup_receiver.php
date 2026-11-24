<?php
// b2c/payout/lookup_receiver.php
session_start();
header('Content-Type: application/json');

require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'not_found']);
    exit();
}

$phone = trim($_GET['phone'] ?? '');

if (empty($phone)) {
    echo json_encode(['status' => 'not_found']);
    exit();
}

$cleanPhone = preg_replace('/[^0-9]/', '', $phone);
$searchSuffix = $cleanPhone;

if (str_starts_with($cleanPhone, '0')) {
    $searchSuffix = '254' . substr($cleanPhone, 1);
} elseif (str_starts_with($cleanPhone, '254')) {
    $searchSuffix = $cleanPhone;
}

$localFormat = '0' . substr($searchSuffix, -9);
$internationalFormat = '254' . substr($searchSuffix, -9);
$likePattern = '%' . substr($cleanPhone, -9);

$stmt = $conn->prepare("
    SELECT receiver_name 
    FROM mpesa_b2c_transactions 
    WHERE (receiver_phone = ? OR receiver_phone = ? OR receiver_phone = ? OR receiver_phone LIKE ?) 
      AND result_code = 0 
      AND receiver_name IS NOT NULL 
      AND TRIM(receiver_name) != ''
    ORDER BY created_at DESC 
    LIMIT 1
");

if ($stmt) {
    $stmt->bind_param("ssss", $internationalFormat, $localFormat, $searchSuffix, $likePattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'status' => 'found',
            'receiver_name' => ucwords(strtolower($row['receiver_name']))
        ]);
        exit();
    }
    $stmt->close();
}

echo json_encode(['status' => 'not_found']);