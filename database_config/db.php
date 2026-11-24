<?php
/* Location: /database_config/db.php */

// Send JSON/CORS headers only when db.php is requested directly via AJAX/fetch
if (!headers_sent() && basename($_SERVER['PHP_SELF']) === 'db.php') {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
}

// Enable error reporting during setup; turn display_errors off in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host    = "localhost";
$db_user = "avenpayc_avenpay";        // Replace with your cPanel MySQL username
$db_pass = "@Sep7eliana";    // Replace with your MySQL password
$db_name = "avenpayc_avenpay";

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    error_log("Admin DB connection failed: " . $conn->connect_error);

    if (!headers_sent() && basename($_SERVER['PHP_SELF']) === 'db.php') {
        header('Content-Type: application/json');
        http_response_code(500);
        die(json_encode([
            "success" => false,
            "error"   => "Database Connection Failed: " . $conn->connect_error
        ]));
    }

    http_response_code(500);
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Sync timezone to East Africa Time (+03:00) for created_at timestamps
date_default_timezone_set('Africa/Nairobi');
$conn->query("SET time_zone = '+03:00'");

/**
 * Create a new admin record in the `admins` table.
 * `id` (AUTO_INCREMENT) and `created_at` (current_timestamp()) are set automatically.
 */
function createAdmin(mysqli $conn, string $username, string $email, string $plainPassword): bool
{
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare(
        "INSERT INTO admins (username, email, password) VALUES (?, ?, ?)"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sss", $username, $email, $hashedPassword);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}

/**
 * Verify an admin login by username or email.
 * Returns the admin row without `password` on success, or null on failure.
 */
function verifyAdminLogin(mysqli $conn, string $loginIdentifier, string $plainPassword): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, username, email, password, created_at
         FROM admins
         WHERE username = ? OR email = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("ss", $loginIdentifier, $loginIdentifier);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();

    if (!$admin || !password_verify($plainPassword, $admin['password'])) {
        return null;
    }

    unset($admin['password']);
    return $admin;
}

/**
 * Fetch an admin by ID without exposing the password hash.
 */
function getAdminById(mysqli $conn, int $adminId): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, username, email, created_at
         FROM admins
         WHERE id = ?
         LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();

    return $admin ?: null;
}