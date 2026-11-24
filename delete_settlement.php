<?php
session_start();
require_once __DIR__ . '/database_config/db.php';

if (isset($_GET['id']) && isset($_SESSION['user_id'])) {
    $id = intval($_GET['id']);
    $user_id = $_SESSION['user_id'];

    // Updated Query: Only delete if:
    // 1. It belongs to the current user
    // 2. It is NOT active
    // 3. It has never been used (test_stk_count is 0)
    $stmt = $conn->prepare("DELETE FROM settlements 
                            WHERE id = ? 
                            AND user_id = ? 
                            AND is_active = 0 
                            AND test_stk_count = 0");
    
    $stmt->bind_param("ii", $id, $user_id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = "Account deleted successfully.";
        } else {
            // This happens if the account is active, has transactions, or doesn't belong to the user
            $_SESSION['error'] = "Account cannot be deleted. It was used for sandbox test.";
        }
    } else {
        $_SESSION['error'] = "An error occurred while processing your request.";
    }
}

header("Location: /settlement");
exit();
?>
