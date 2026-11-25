<?php
session_start();
require_once __DIR__ . '/../database_config/db.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/notifications.php';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') { av_rate_limit_or_die('register', 10, 3600); } 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Sanitize Inputs
    $name = trim($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];

    // 2. Validation
    if ($password !== $confirm) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: /register"); // Absolute path
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters.";
        header("Location: /register");
        exit;
    }

    // 3. Pre-Check for Duplicates
    $stmt = $conn->prepare("SELECT email, phone_number FROM users WHERE email = ? OR phone_number = ?");
    $stmt->bind_param("ss", $email, $phone);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $conflicts = [];
    while ($row = $result->fetch_assoc()) {
        if ($row['email'] === $email) $conflicts[] = "email address";
        if ($row['phone_number'] === $phone) $conflicts[] = "phone number";
    }

    if (!empty($conflicts)) {
        $_SESSION['error'] = "The " . implode(" and ", array_unique($conflicts)) . " are already registered.";
        header("Location: /register");
        exit;
    }

    // 4. Registration
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, phone_number, password) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $name, $email, $phone, $hashed_password);
    
    if ($stmt->execute()) {
        av_notify_lifecycle($email, $name, 'Welcome to AvenPay', 'Welcome to AvenPay, ' . $name, 'Your merchant account has been created. AvenPay helps you collect payments with C2B and STK Push, make B2C payouts and B2B payments, manage merchant wallets, track transactions, configure webhooks and test integrations in sandbox mode.', ['Next step' => 'Sign in and complete your merchant setup'], 'hello', 'Sign in to AvenPay', 'https://avenpay.co.ke/login');
        $_SESSION['success'] = "Account created successfully!";
        header("Location: /login"); // Absolute path
        exit;
    } else {
        $_SESSION['error'] = "Registration failed. Please try again.";
        header("Location: /register");
        exit;
    }
} else {
    header("Location: /register");
    exit;
}