<?php
/* Location: /database_config/db.php */
require_once __DIR__ . '/env.php';

// Database credentials are loaded only from the private AvenPay environment file
// outside public_html or from real process environment variables.
$host = trim((string) av_env('DB_HOST', 'localhost'));
$db_user = trim((string) av_env('DB_USER'));
$db_pass = (string) av_env('DB_PASS');
$db_name = trim((string) av_env('DB_NAME'));

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$isAvenPayJsonEndpoint = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH'], true)
    && (str_starts_with($requestPath, '/api/')
        || str_starts_with($requestPath, '/b2c/')
        || str_starts_with($requestPath, '/logs/'));

function av_db_unavailable(bool $jsonResponse): void
{
    http_response_code(503);
    if ($jsonResponse) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'error' => 'Service temporarily unavailable',
            'message' => 'The database connection is not available. Check the private AvenPay database settings and server error log.'
        ]);
    } else {
        echo 'Service temporarily unavailable.';
    }
    exit();
}

if ($db_user === '' || $db_pass === '' || $db_name === '') {
    error_log('AvenPay database configuration is incomplete. Set DB_USER, DB_PASS and DB_NAME in the private environment file.');
    av_db_unavailable($isAvenPayJsonEndpoint);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = new mysqli($host, $db_user, $db_pass, $db_name);
    $conn->set_charset('utf8mb4');
    date_default_timezone_set('Africa/Nairobi');
    $conn->query("SET time_zone = '+03:00'");
} catch (mysqli_sql_exception $e) {
    error_log('AvenPay database connection failed: ' . $e->getMessage());
    av_db_unavailable($isAvenPayJsonEndpoint);
}

/**
 * Create a new admin record.
 */
function createAdmin(mysqli $conn, string $username, string $email, string $plainPassword): bool
{
    $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO admins (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $username, $email, $hashedPassword);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Verify an admin login by username or email.
 */
function verifyAdminLogin(mysqli $conn, string $loginIdentifier, string $plainPassword): ?array
{
    $stmt = $conn->prepare(
        "SELECT id, username, email, password, created_at
         FROM admins
         WHERE username = ? OR email = ?
         LIMIT 1"
    );
    $stmt->bind_param("ss", $loginIdentifier, $loginIdentifier);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
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
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $admin ?: null;
}
