<?php
// Daily safety net (run from cPanel cron, once a day):  php tools/reconcile.php
//  1) every wallet balance must equal the sum of its ledger lines
//  2) lists payouts that never received a final result
// Exit code 1 + output when something is wrong (cPanel emails cron output to you).
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../database_config/db.php';
$problems = 0;

$q = $conn->query("SELECT u.id, u.b2c_wallet_balance AS bal, COALESCE(SUM(l.amount),0) AS led FROM users u LEFT JOIN wallet_ledger l ON l.user_id = u.id GROUP BY u.id HAVING ROUND(bal - led, 2) <> 0");
while ($q && ($r = $q->fetch_assoc())) {
    // Wallets that have never had ledger activity and hold nothing are fine; anything else is a mismatch.
    if ((float)$r['bal'] == 0.0 && (float)$r['led'] == 0.0) continue;
    $problems++; echo "WALLET MISMATCH user {$r['id']}: balance {$r['bal']} vs ledger {$r['led']}\n";
}
foreach (['mpesa_b2c_transactions' => 'B2C', 'mpesa_b2b_transactions' => 'B2B'] as $tbl => $label) {
    $s = $conn->query("SELECT id, user_id, transaction_amount, created_at FROM `$tbl` WHERE result_code = 1000 AND created_at < (NOW() - INTERVAL 15 MINUTE)");
    while ($s && ($r = $s->fetch_assoc())) { $problems++; echo "STUCK $label payout #{$r['id']} user {$r['user_id']} KES {$r['transaction_amount']} since {$r['created_at']} (resolve in Admin > Stuck Payouts)\n"; }
}
echo $problems ? "\n$problems problem(s) found.\n" : "OK: all wallets match the ledger and no payouts are stuck.\n";
exit($problems ? 1 : 0);
