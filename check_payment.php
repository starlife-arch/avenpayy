<?php
session_start();
require_once '../database_config/db.php';
require_once '../config.php';
header('Content-Type: application/json');

$checkoutID = $_GET['checkoutID'] ?? '';
if (empty($checkoutID)) {
    echo json_encode(['status' => 'error', 'message' => 'No ID provided']);
    exit;
}

$response = file_get_contents("https://avenpay.co.ke/api/stk_push?check_status=" . urlencode($checkoutID));
$data = json_decode($response, true);

// Check if SUCCESS or SETTLEMENT
if ($data && ($data['status'] === 'SUCCESS' || $data['status'] === 'SETTLEMENT')) {
    $user_id = $_SESSION['user_id'];
    
    // Update user status and expiry
    $stmt = $conn->prepare("UPDATE users SET subscription_status = 1, 
                            subscription_expiry = DATE_ADD(CURDATE(), INTERVAL 1 MONTH) 
                            WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    echo json_encode(['status' => 'active']);
} else {
    echo json_encode(['status' => 'pending']);
}
?>