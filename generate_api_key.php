<?php
session_start();
require_once __DIR__ . '/database_config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) exit;

$id = (int)$_GET['id']; // Get the ID from the URL

$random_part = bin2hex(random_bytes(24));
$new_key = 'avp_live_' . $random_part;

// Target the specific ID, not just active accounts
$stmt = $conn->prepare("UPDATE settlements SET x_api_key = ? WHERE id = ? AND user_id = ?");
$stmt->bind_param("sii", $new_key, $id, $_SESSION['user_id']);

if ($stmt->execute()) {
    $_SESSION['new_api_key'] = $new_key; 
}

header("Location: settlement.php");
exit();
?>