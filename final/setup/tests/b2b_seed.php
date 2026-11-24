<?php
require_once __DIR__ . '/../../public_html/database_config/db.php'; require_once __DIR__ . '/../../public_html/lib/ledger.php'; require_once __DIR__ . '/../../public_html/lib/callback_auth.php';
$conn->query("SET SESSION sql_mode=''");
$mode = $argv[1] ?? 'seed';
if ($mode === 'token') { echo av_callback_token($argv[2]); exit; }
$conn->query("DELETE FROM wallet_ledger WHERE user_id=999003"); $conn->query("DELETE FROM mpesa_b2b_transactions WHERE user_id=999003"); $conn->query("DELETE FROM users WHERE id=999003");
if ($mode === 'cleanup') exit;
$conn->query("INSERT INTO users (id, username, email, password, b2c_wallet_balance, wallet_id, b2c_security_credential) VALUES (999003,'b2b_test','b2b_test@example.com','x',0,'WB999003','secret123')");
av_wallet_apply($conn, 999003, 500, 'topup_credit', 'topup', 'seed1');
foreach (['AG_FAIL1' => 100, 'AG_OK1' => 50] as $conv => $amt) {
    $conn->query("INSERT INTO mpesa_b2b_transactions (user_id, wallet_id, conversation_id, originator_conversation_id, command_id, party_b, account_reference, transaction_amount, fee_amount, result_code) VALUES (999003,'WB999003','$conv','O_$conv','BusinessPayBill','174379','ACC1',$amt,5,1000)");
    $id = $conn->insert_id; av_wallet_apply($conn, 999003, -($amt+5), 'payout_reserve', 'b2b_tx', (string)$id, 'seed');
}
