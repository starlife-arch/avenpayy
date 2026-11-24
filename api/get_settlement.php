<?php
session_start();
require_once __DIR__ . '/../database_config/db.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $conn->prepare("UPDATE settlements SET merchant_name = ?, shortcode = ?, account_number = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("sssii", $_POST['merchant_name'], $_POST['shortcode'], $_POST['account_number'], $_POST['id'], $_SESSION['user_id']);
    $stmt->execute();
    header("Location: ../settlement.php?status=updated");
}