<?php
// CLI test of admin/payout_review.php resolve logic (CLI skips the admin gate by design)
$root = dirname(__DIR__, 2) . '/public_html';
require_once $root . '/database_config/db.php'; require_once $root . '/lib/ledger.php';
$conn->query("SET SESSION sql_mode=''");
$mode = $argv[1] ?? 'run';
function seed($conn) {
    $conn->query("DELETE FROM wallet_ledger WHERE user_id=999004"); $conn->query("DELETE FROM mpesa_b2b_transactions WHERE user_id=999004"); $conn->query("DELETE FROM users WHERE id=999004");
    $conn->query("INSERT INTO users (id, username, email, password, b2c_wallet_balance, wallet_id) VALUES (999004,'rev_test','rev@example.com','x',0,'WR999004')");
    av_wallet_apply($conn, 999004, 300, 'topup_credit', 'topup', 'rev1');
    $conn->query("INSERT INTO mpesa_b2b_transactions (id, user_id, wallet_id, conversation_id, command_id, party_b, transaction_amount, fee_amount, result_code, created_at) VALUES (999004, 999004,'WR999004','PENDING-x','BusinessPayBill','174379',100,5,1000, NOW() - INTERVAL 1 HOUR)");
    av_wallet_apply($conn, 999004, -105, 'payout_reserve', 'b2b_tx', '999004', 'seed');
}
if ($mode === 'run') {
    seed($conn);
    $bal = fn() => (float)$conn->query("SELECT b2c_wallet_balance b FROM users WHERE id=999004")->fetch_assoc()['b'];
    $fail = 0; $chk = function($n,$c) use (&$fail){ echo ($c?'PASS':'FAIL')."  $n\n"; if(!$c)$fail++; };
    $chk('balance after reservation is 195', $bal() == 195);
    $_SERVER['REQUEST_METHOD'] = 'POST'; $_POST = ['kind'=>'b2b','id'=>'999004','outcome'=>'failed'];
    ob_start(); include $root . '/admin/payout_review.php'; $html = ob_get_clean();
    $chk('failed + refund restores 300', $bal() == 300);
    $chk('page reports the refund', strpos($html, 'refunded KES 105.00') !== false);
    ob_start(); include $root . '/admin/payout_review.php'; $html = ob_get_clean();
    $chk('second click does NOT refund again', $bal() == 300 && strpos($html, 'Already resolved') !== false);
    $chk('ledger matches balance', (float)$conn->query("SELECT SUM(amount) s FROM wallet_ledger WHERE user_id=999004")->fetch_assoc()['s'] == 300);
    $conn->query("DELETE FROM wallet_ledger WHERE user_id=999004"); $conn->query("DELETE FROM mpesa_b2b_transactions WHERE user_id=999004"); $conn->query("DELETE FROM users WHERE id=999004");
    echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n"; exit($fail?1:0);
}
