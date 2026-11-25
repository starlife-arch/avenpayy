<?php
session_start();
require_once __DIR__ . '/../database_config/db.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/notifications.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { av_rate_limit_or_die('login', 10, 300); } 

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /login");
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT id, username, email, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        av_notify_lifecycle((string)$user['email'], (string)$user['username'], 'New sign-in to your AvenPay account', 'A new sign-in was detected', 'Your AvenPay account was just accessed successfully. If this was you, no action is needed.', ['Time' => date('d M Y H:i:s T'), 'IP address' => (string)($_SERVER['REMOTE_ADDR'] ?? 'Unavailable')], 'security', 'Open AvenPay', 'https://avenpay.co.ke/dashboard');
        header("Location: /dashboard");
        exit;
    } else {
        // Specific error: Password is wrong
        $_SESSION['error'] = "The password you entered is incorrect.";
        header("Location: /login");
        exit;
    }
} else {
    // Specific error: Email not found
    $_SESSION['error'] = "No account found with that email address.";
    header("Location: /login");
    exit;
}