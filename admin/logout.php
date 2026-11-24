<?php
/**
 * logout.php - Admin Session Termination
 * Located in /admin/
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = array();

// Delete the session cookie if configured
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session storage on the server
session_destroy();

// Redirect back to login in the same directory or root as needed
header("Location: /admin/");
exit();
?>