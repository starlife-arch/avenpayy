<?php
session_start();
require_once __DIR__ . '/../database_config/db.php';
require_once __DIR__ . '/../lib/security.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { av_rate_limit_or_die('login', 10, 300); } 

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /login");
    exit;
}

$email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
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