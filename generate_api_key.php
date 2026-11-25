<?php
session_start();
require_once __DIR__ . '/database_config/db.php';
require_once __DIR__ . '/lib/notifications.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) exit;

$id = (int)$_GET['id']; // Get the ID from the URL

$ownerStmt = $conn->prepare("SELECT u.email, u.username FROM settlements s JOIN users u ON u.id = s.user_id WHERE s.id = ? AND s.user_id = ? LIMIT 1");
$ownerStmt->bind_param("ii", $id, $_SESSION['user_id']);
$ownerStmt->execute();
$owner = $ownerStmt->get_result()->fetch_assoc();
$ownerStmt->close();
if (!$owner) { header("Location: settlement.php"); exit; }
$random_part = bin2hex(random_bytes(24));
$new_key = 'avp_live_' . $random_part;

// Target the specific ID, not just active accounts
$stmt = $conn->prepare("UPDATE settlements SET x_api_key = ? WHERE id = ? AND user_id = ?");
$stmt->bind_param("sii", $new_key, $id, $_SESSION['user_id']);

if ($stmt->execute() && $stmt->affected_rows === 1) {
    $_SESSION['new_api_key'] = $new_key;
    av_notify_lifecycle((string)$owner['email'], (string)$owner['username'], 'AvenPay API key created', 'A new API key was created', 'A new API key was generated for one of your settlement accounts. For your protection, the key itself is not included in this email.', ['Settlement account' => '#' . $id, 'Key' => 'New live API key generated', 'Date' => date('d M Y H:i:s T')], 'api', 'Review your settlement account', 'https://avenpay.co.ke/settlement');
}

header("Location: settlement.php");
exit();
?>