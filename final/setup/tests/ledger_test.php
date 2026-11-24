<?php
// CLI test: php tests/ledger_test.php  (needs DB_* env vars pointing at a THROWAWAY database)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../../public_html/lib/ledger.php';
require_once __DIR__ . '/../../public_html/database_config/env.php';
$conn = new mysqli(av_env('DB_HOST', 'localhost'), av_env('DB_USER'), av_env('DB_PASS'), av_env('DB_NAME'));
$conn->query("SET SESSION sql_mode=''");
$conn->query("DELETE FROM wallet_ledger WHERE user_id = 999001");
$conn->query("DELETE FROM users WHERE id = 999001");
$conn->query("INSERT INTO users (id, username, email, password, b2c_wallet_balance) VALUES (999001, 'ledger_test', 'ledger_test@example.com', 'x', 0)");

$fail = 0;
function check($name, $cond) { global $fail; echo ($cond ? "PASS" : "FAIL") . "  $name\n"; if (!$cond) $fail++; }
function bal($conn) { return (float)$conn->query("SELECT b2c_wallet_balance b FROM users WHERE id=999001")->fetch_assoc()['b']; }
function ledgerSum($conn) { return (float)$conn->query("SELECT COALESCE(SUM(amount),0) s FROM wallet_ledger WHERE user_id=999001")->fetch_assoc()['s']; }

check('credit 1000 applies',                 av_wallet_apply($conn, 999001, 1000, 'topup_credit', 'topup', 'T1') === true);
check('balance is 1000',                     bal($conn) == 1000);
check('same top-up credited twice is blocked', av_wallet_apply($conn, 999001, 1000, 'topup_credit', 'topup', 'T1', '', true, $why) === false && $why === 'duplicate');
check('balance still 1000',                  bal($conn) == 1000);
check('reserve 600 applies',                 av_wallet_apply($conn, 999001, -600, 'payout_reserve', 'b2c_tx', 'P1') === true);
check('balance is 400',                      bal($conn) == 400);
check('overdraw 500 is refused',             av_wallet_apply($conn, 999001, -500, 'payout_reserve', 'b2c_tx', 'P2', '', true, $why) === false && $why === 'insufficient');
check('balance still 400',                   bal($conn) == 400);
check('release 600 applies',                 av_wallet_apply($conn, 999001, 600, 'payout_release', 'b2c_tx', 'P1') === true);
check('balance back to 1000',                bal($conn) == 1000);
check('double refund is blocked',            av_wallet_apply($conn, 999001, 600, 'payout_release', 'b2c_tx', 'P1', '', true, $why) === false && $why === 'duplicate');
check('balance still 1000 after dup refund', bal($conn) == 1000);
check('ledger sum equals balance',           ledgerSum($conn) == bal($conn));

// inside a caller-managed transaction, a duplicate must not commit anything
$conn->begin_transaction();
$ok = av_wallet_apply($conn, 999001, 50, 'topup_credit', 'topup', 'T2', '', false);
$conn->rollback();
check('caller-managed rollback undoes credit', $ok === true && bal($conn) == 1000 && ledgerSum($conn) == 1000);

$conn->query("SET SESSION sql_mode=''");
$conn->query("DELETE FROM wallet_ledger WHERE user_id = 999001");
$conn->query("DELETE FROM users WHERE id = 999001");
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n";
exit($fail ? 1 : 0);
