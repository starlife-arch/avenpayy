<?php
// b2c/walletbalance.php
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

// Pull input parameters from HTTP headers or JSON body or POST
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$wallet_id = trim($_SERVER['HTTP_X_WALLET_ID'] ?? $input['wallet_id'] ?? $_POST['wallet_id'] ?? '');
$security_credential = trim($_SERVER['HTTP_X_SECURITY_CREDENTIAL'] ?? $input['security_credential'] ?? $_POST['security_credential'] ?? '');

try {
    if (empty($wallet_id) || empty($security_credential)) {
        http_response_code(400);
        echo json_encode([
            "status" => "error", 
            "message" => "Missing required authentication headers: X-Wallet-ID and X-Security-Credential"
        ], JSON_PRETTY_PRINT);
        exit();
    }

    // Query strictly wallet balance and security credential
    $stmt = $conn->prepare("SELECT wallet_id, b2c_wallet_balance, b2c_security_credential FROM users WHERE wallet_id = ?");
    $stmt->bind_param("s", $wallet_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            "status" => "error", 
            "message" => "Invalid Wallet ID."
        ], JSON_PRETTY_PRINT);
        exit();
    }

    $wallet = $result->fetch_assoc();
    $stmt->close();

    $db_credential = $wallet['b2c_security_credential'] ?? '';

    // Validate security credential
    if (!empty($db_credential)) {
        $isValid = ($security_credential === $db_credential) || password_verify($security_credential, $db_credential);
        if (!$isValid) {
            http_response_code(401);
            echo json_encode([
                "status" => "error", 
                "message" => "Unauthorized: Incorrect Security Credential."
            ], JSON_PRETTY_PRINT);
            exit();
        }
    }

    // Success JSON response focusing strictly on the balance
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "wallet_id" => $wallet_id,
        "balance" => (float)$wallet['b2c_wallet_balance']
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error", 
        "message" => "Server Error: " . $e->getMessage()
    ], JSON_PRETTY_PRINT);
}