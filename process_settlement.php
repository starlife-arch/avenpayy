<?php
/**
 * process_settlement.php
 */

require_once __DIR__ . '/user/auth.php'; 
require_once __DIR__ . '/database_config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Capture inputs
    $id        = $_POST['id'] ?? null; // Added ID capture
    $merchant  = trim($_POST['merchant_name'] ?? '');
    $type      = $_POST['type'] ?? 'paybill';
    $shortcode = trim($_POST['shortcode'] ?? '');
    $account   = ($type === 'till') ? NULL : (trim($_POST['account_number'] ?? NULL));
    
    // 2. Basic validation
    if (empty($merchant) || empty($shortcode)) {
        $_SESSION['error'] = "Merchant name and shortcode are required.";
        header("Location: /settlement.php");
        exit;
    }

    // 3. Logic: Update if ID exists, otherwise Insert
    if (!empty($id)) {
        // UPDATE (Security: user_id constraint ensures they only edit their own)
        $sql = "UPDATE settlements SET merchant_name = ?, type = ?, shortcode = ?, account_number = ? 
                WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssii", $merchant, $type, $shortcode, $account, $id, $current_user_id);
        $action = "updated";
    } else {
        // INSERT
        $sql = "INSERT INTO settlements (user_id, merchant_name, type, shortcode, account_number) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issss", $current_user_id, $merchant, $type, $shortcode, $account);
        $action = "submitted";
    }
    
    // 4. Execute
    if ($stmt->execute()) {
        $_SESSION['success'] = "Account $action successfully!";
        header("Location: /settlement.php?status=success");
        exit;
    } else {
        error_log("DB Error: " . $stmt->error);
        $_SESSION['error'] = "Failed to process request. Please try again.";
        header("Location: /settlement.php");
        exit;
    }
} else {
    header("Location: /settlement.php");
    exit;
}
?>