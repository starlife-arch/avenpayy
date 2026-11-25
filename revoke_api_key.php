<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
require_once __DIR__ . '/database_config/db.php';
require_once __DIR__ . '/lib/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
$postedToken = (string)($_POST['csrf_token'] ?? '');
if (empty($_SESSION['api_key_csrf']) || !hash_equals((string)$_SESSION['api_key_csrf'], $postedToken)) {
    http_response_code(403);
    exit('Invalid security token. Refresh the settlement page and try again.');
}
$settlementId = filter_input(INPUT_POST, 'settlement_id', FILTER_VALIDATE_INT);
if (!$settlementId || $settlementId < 1) {
    http_response_code(400);
    exit('Invalid settlement account.');
}
$userId = (int)$_SESSION['user_id'];

$ownerStmt = $conn->prepare("SELECT u.email, u.username, s.merchant_name FROM settlements s JOIN users u ON u.id=s.user_id WHERE s.id=? AND s.user_id=? AND s.x_api_key IS NOT NULL AND s.x_api_key <> '' LIMIT 1");
$ownerStmt->bind_param('ii', $settlementId, $userId);
$ownerStmt->execute();
$owner = $ownerStmt->get_result()->fetch_assoc();
$ownerStmt->close();
if (!$owner) {
    $_SESSION['error'] = 'No active API key was found for that settlement account.';
    header('Location: /settlement');
    exit;
}

$revoke = $conn->prepare("UPDATE settlements SET x_api_key = NULL WHERE id = ? AND user_id = ? AND x_api_key IS NOT NULL AND x_api_key <> ''");
$revoke->bind_param('ii', $settlementId, $userId);
$ok = $revoke->execute();
$changed = $revoke->affected_rows;
$revoke->close();

if ($ok && $changed === 1) {
    av_notify_lifecycle(
        (string)$owner['email'],
        (string)$owner['username'],
        'Your AvenPay API key was revoked',
        'API key revoked',
        'The API key for this settlement account has been revoked and can no longer authenticate API requests. If you did not perform this action, contact AvenPay Support.',
        ['Settlement account' => '#' . $settlementId, 'Merchant account' => (string)$owner['merchant_name'], 'Date' => date('d M Y H:i:s T')],
        'api',
        'Review your settlement account',
        'https://avenpay.co.ke/settlement'
    );
    $_SESSION['success'] = 'API key revoked successfully.';
} else {
    $_SESSION['error'] = 'The API key could not be revoked. Please try again.';
}
header('Location: /settlement');
exit;
