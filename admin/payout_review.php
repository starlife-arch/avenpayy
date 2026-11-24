<?php
// Admin: payouts stuck "in flight" (no final result from M-Pesa) - resolve them by hand, safely.
require_once __DIR__ . '/../lib/require_admin.php';
require_once __DIR__ . '/../database_config/db.php';
require_once __DIR__ . '/../lib/ledger.php';

const AV_STUCK_MINUTES = 15;
$tables = ['b2c' => ['mpesa_b2c_transactions', 'receiver_phone', 'b2c_tx'], 'b2b' => ['mpesa_b2b_transactions', 'party_b', 'b2b_tx']];
$flash = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $kind = $_POST['kind'] ?? ''; $id = (int)($_POST['id'] ?? 0); $outcome = $_POST['outcome'] ?? '';
    if (isset($tables[$kind]) && $id > 0 && in_array($outcome, ['failed', 'success'], true)) {
        [$tbl, , $refType] = $tables[$kind];
        $conn->begin_transaction();
        try {
            $q = $conn->prepare("SELECT user_id FROM `$tbl` WHERE id = ? AND result_code = 1000 FOR UPDATE");
            $q->bind_param("i", $id); $q->execute();
            $row = $q->get_result()->fetch_assoc(); $q->close();
            if (!$row) { $conn->rollback(); $flash = 'Already resolved by someone else.'; }
            elseif ($outcome === 'success') {
                $u = $conn->prepare("UPDATE `$tbl` SET result_code = 0, result_desc = 'Resolved by admin: confirmed paid', completed_at = NOW() WHERE id = ? AND result_code = 1000");
                $u->bind_param("i", $id); $u->execute(); $u->close();
                $conn->commit(); $flash = "Marked #$id as paid. No money moved.";
            } else {
                $r = $conn->prepare("SELECT -amount AS reserved FROM wallet_ledger WHERE entry_type = 'payout_reserve' AND ref_type = ? AND ref_id = ?");
                $rid = (string)$id; $r->bind_param("ss", $refType, $rid); $r->execute();
                $res = $r->get_result()->fetch_assoc(); $r->close();
                if (!$res) { $conn->rollback(); $flash = "No ledger reservation found for #$id (older than the ledger). Refund it manually."; }
                else {
                    $u = $conn->prepare("UPDATE `$tbl` SET result_code = 9003, result_desc = 'Resolved by admin: marked failed, refunded', completed_at = NOW() WHERE id = ? AND result_code = 1000");
                    $u->bind_param("i", $id); $u->execute(); $u->close();
                    av_wallet_apply($conn, (int)$row['user_id'], (float)$res['reserved'], 'payout_release', $refType, (string)$id, 'Admin refund of stuck payout', false);
                    $conn->commit(); $flash = "Marked #$id failed and refunded KES " . number_format((float)$res['reserved'], 2) . ".";
                }
            }
        } catch (Throwable $e) { $conn->rollback(); error_log('payout_review: ' . $e->getMessage()); $flash = 'Could not resolve (see server log).'; }
    }
}

$stuck = [];
foreach ($tables as $kind => [$tbl, $partyCol]) {
    $res = $conn->query("SELECT id, user_id, transaction_amount, `$partyCol` AS party, created_at, result_desc FROM `$tbl` WHERE result_code = 1000 AND created_at < (NOW() - INTERVAL " . AV_STUCK_MINUTES . " MINUTE) ORDER BY id DESC LIMIT 100");
    while ($res && ($r = $res->fetch_assoc())) { $r['kind'] = $kind; $stuck[] = $r; }
}
usort($stuck, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$active = 'payout_review';
?>
<!DOCTYPE html>
<html lang="en"><head>    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta name="theme-color" content="#ffffff">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Stuck payouts - AvenPay Admin</title>
<script src="https://cdn.tailwindcss.com/3.4.17"></script></head>
<body class="bg-slate-950 text-slate-200 min-h-screen p-4 md:p-8">
<div class="max-w-5xl mx-auto">
  <a href="/admin/dashboard.php" class="text-xs text-teal-400">&larr; Dashboard</a>
  <h1 class="text-xl font-bold mt-2">Stuck payouts</h1>
  <p class="text-sm text-slate-400 mb-4">Payouts with no final result from M-Pesa after <?= AV_STUCK_MINUTES ?> minutes. Check the real status in your M-Pesa portal first, then resolve here. Failed = refunds the merchant's wallet (once, ledger-backed). Paid = just closes the record.</p>
  <?php if ($flash): ?><div class="mb-4 p-3 rounded bg-slate-800 text-sm"><?= $h($flash) ?></div><?php endif; ?>
  <div class="overflow-x-auto rounded-lg border border-slate-800">
  <table class="w-full text-sm"><thead class="bg-slate-900 text-slate-400 text-xs uppercase"><tr>
    <th class="p-3 text-left">Type</th><th class="p-3 text-left">#</th><th class="p-3 text-left">Merchant</th><th class="p-3 text-left">To</th><th class="p-3 text-right">Amount</th><th class="p-3 text-left">Created</th><th class="p-3 text-left">Note</th><th class="p-3"></th></tr></thead><tbody>
  <?php foreach ($stuck as $r): ?>
    <tr class="border-t border-slate-800"><td class="p-3 uppercase"><?= $h($r['kind']) ?></td><td class="p-3"><?= (int)$r['id'] ?></td><td class="p-3"><?= (int)$r['user_id'] ?></td><td class="p-3"><?= $h($r['party']) ?></td>
      <td class="p-3 text-right">KES <?= number_format((float)$r['transaction_amount'], 2) ?></td><td class="p-3"><?= $h($r['created_at']) ?></td><td class="p-3 text-xs text-slate-400"><?= $h($r['result_desc']) ?></td>
      <td class="p-3 whitespace-nowrap">
        <form method="POST" class="inline" onsubmit="return confirm('Mark as FAILED and refund the merchant?')"><input type="hidden" name="kind" value="<?= $h($r['kind']) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="outcome" value="failed"><button class="px-2 py-1 rounded bg-rose-600/20 text-rose-300 text-xs">Failed + refund</button></form>
        <form method="POST" class="inline" onsubmit="return confirm('Mark as PAID (no money moves)?')"><input type="hidden" name="kind" value="<?= $h($r['kind']) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="outcome" value="success"><button class="px-2 py-1 rounded bg-teal-600/20 text-teal-300 text-xs">Paid</button></form>
      </td></tr>
  <?php endforeach; if (!$stuck): ?><tr><td colspan="8" class="p-6 text-center text-slate-500">Nothing stuck. All payouts have a final result.</td></tr><?php endif; ?>
  </tbody></table></div>
</div></body></html>
