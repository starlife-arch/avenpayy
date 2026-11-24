<?php
// File: auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if user_id is missing
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit;
}

// Global variable that all your files can now use
$current_user_id = $_SESSION['user_id'];
?>