<?php
// Admin: review B2C payouts that have remained in-flight without a final M-Pesa result.
require_once __DIR__ . '/../lib/require_admin.php';
require_once __DIR__ . '/../database_config/db.php';
require_once __DIR__ . '/../lib/ledger.php';

if (!defined('AV_STUCK_MINUTES')) define('AV_STUCK_MINUTES', 15);

if (!function_exists('av_payout_table_exists')) {
function av_payout_table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1"
    );
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $exists = (bool)$stmt->get_result()->fetch_row();
    $stmt->close();
    return $exists;
}
}

// The original PrintPay database contains mpesa_b2c_transactions.
// B2B was added only in a later optional migration, so do not query it
// unless that table is actually installed.
$tables = [];
if (av_payout_table_exists($conn, 'mpesa_b2c_transactions')) {
    $tables['b2c'] = ['mpesa_b2c_transactions', 'receiver_phone', 'b2c_tx'];
}
if (av_payout_table_exists($conn, 'mpesa_b2b_transactions')) {
    $tables['b2b'] = ['mpesa_b2b_transactions', 'party_b', 'b2b_tx'];
}

$flash = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $kind = (string)($_POST['kind'] ?? '');
    $id = (int)($_POST['id'] ?? 0);
    $outcome = (string)($_POST['outcome'] ?? '');

    if (isset($tables[$kind]) && $id > 0 && in_array($outcome, ['failed', 'success'], true)) {
        [$tbl, , $refType] = $tables[$kind];
        $conn->begin_transaction();
        try {
            $q = $conn->prepare("SELECT user_id FROM `$tbl` WHERE id = ? AND result_code = 1000 FOR UPDATE");
            if (!$q) throw new RuntimeException('Could not load the payout record.');
            $q->bind_param('i', $id);
            $q->execute();
            $row = $q->get_result()->fetch_assoc();
            $q->close();

            if (!$row) {
                $conn->rollback();
                $flash = 'Already resolved by someone else.';
            } elseif ($outcome === 'success') {
                $u = $conn->prepare("UPDATE `$tbl` SET result_code = 0, result_desc = 'Resolved by admin: confirmed paid', completed_at = NOW() WHERE id = ? AND result_code = 1000");
                if (!$u) throw new RuntimeException('Could not prepare the payout update.');
                $u->bind_param('i', $id);
                $u->execute();
                $u->close();
                $conn->commit();
                $flash = "Marked payout #$id as paid. No money was moved.";
            } else {
                $r = $conn->prepare("SELECT -amount AS reserved FROM wallet_ledger WHERE entry_type = 'payout_reserve' AND ref_type = ? AND ref_id = ?");
                if (!$r) throw new RuntimeException('The wallet ledger is unavailable; no refund was made.');
                $rid = (string)$id;
                $r->bind_param('ss', $refType, $rid);
                $r->execute();
                $reservation = $r->get_result()->fetch_assoc();
                $r->close();

                if (!$reservation) {
                    $conn->rollback();
                    $flash = "No matching wallet reservation was found for payout #$id. No automatic refund was made.";
                } else {
                    $u = $conn->prepare("UPDATE `$tbl` SET result_code = 9003, result_desc = 'Resolved by admin: marked failed, refunded', completed_at = NOW() WHERE id = ? AND result_code = 1000");
                    if (!$u) throw new RuntimeException('Could not prepare the payout update.');
                    $u->bind_param('i', $id);
                    $u->execute();
                    $u->close();

                    av_wallet_apply(
                        $conn,
                        (int)$row['user_id'],
                        (float)$reservation['reserved'],
                        'payout_release',
                        $refType,
                        (string)$id,
                        'Admin refund of stuck payout',
                        false
                    );
                    $conn->commit();
                    $flash = "Marked payout #$id failed and released KES " . number_format((float)$reservation['reserved'], 2) . ".";
                }
            }
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('payout_review: ' . $e->getMessage());
            $flash = 'Could not resolve this payout. Check the private server error log.';
        }
    }
}

$stuck = [];
foreach ($tables as $kind => [$tbl, $partyCol]) {
    $sql = "SELECT id, user_id, transaction_amount, `$partyCol` AS party,
                   created_at, result_desc
            FROM `$tbl`
            WHERE result_code = 1000
              AND created_at < (NOW() - INTERVAL " . AV_STUCK_MINUTES . " MINUTE)
            ORDER BY id DESC LIMIT 100";
    $res = $conn->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $row['kind'] = $kind;
            $stuck[] = $row;
        }
        $res->free();
    } else {
        error_log('payout_review query failed for ' . $tbl . ': ' . $conn->error);
    }
}
usort($stuck, static fn($a, $b) => strcmp((string)$b['created_at'], (string)$a['created_at']));
$h = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$active = 'payout_review';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/brand/avenpay-icon.svg?v=4" sizes="any">
    <link rel="icon" href="/icon.png?v=4" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=4">
    <meta name="theme-color" content="#ffffff">
    <title>Stuck payouts - AvenPay Admin</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen p-4 md:p-8">
<div class="max-w-5xl mx-auto">
    <a href="/admin/dashboard.php" class="text-xs text-teal-700">&larr; Dashboard</a>
    <h1 class="text-xl font-bold mt-2 text-slate-900">Stuck payouts</h1>
    <p class="text-sm text-slate-600 mb-4">
        Payouts with no final result from M-Pesa after <?= AV_STUCK_MINUTES ?> minutes.
        Check the real status in your M-Pesa portal first. A failed payout is refunded
        only when a matching wallet reservation exists; marking a payout paid does not move money.
    </p>
    <?php if ($flash): ?>
        <div class="mb-4 p-3 rounded border border-slate-200 bg-white text-sm text-slate-800"><?= $h($flash) ?></div>
    <?php endif; ?>
    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-100 text-slate-600 text-xs uppercase">
                <tr>
                    <th class="p-3 text-left">Type</th><th class="p-3 text-left">#</th>
                    <th class="p-3 text-left">Merchant ID</th><th class="p-3 text-left">Recipient</th>
                    <th class="p-3 text-right">Amount</th><th class="p-3 text-left">Created</th>
                    <th class="p-3 text-left">Note</th><th class="p-3"></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($stuck as $row): ?>
                <tr class="border-t border-slate-200">
                    <td class="p-3 uppercase"><?= $h($row['kind']) ?></td>
                    <td class="p-3"><?= (int)$row['id'] ?></td>
                    <td class="p-3"><?= (int)$row['user_id'] ?></td>
                    <td class="p-3"><?= $h($row['party']) ?></td>
                    <td class="p-3 text-right tabular-nums">KES <?= number_format((float)$row['transaction_amount'], 2) ?></td>
                    <td class="p-3"><?= $h($row['created_at']) ?></td>
                    <td class="p-3 text-xs text-slate-600"><?= $h($row['result_desc']) ?></td>
                    <td class="p-3 whitespace-nowrap">
                        <form method="POST" class="inline" onsubmit="return confirm('Mark as FAILED and release the reserved wallet amount?')">
                            <input type="hidden" name="kind" value="<?= $h($row['kind']) ?>">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <input type="hidden" name="outcome" value="failed">
                            <button class="px-2 py-1 rounded bg-rose-50 border border-rose-200 text-rose-700 text-xs">Failed + refund</button>
                        </form>
                        <form method="POST" class="inline" onsubmit="return confirm('Mark as PAID (no money moves)?')">
                            <input type="hidden" name="kind" value="<?= $h($row['kind']) ?>">
                            <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                            <input type="hidden" name="outcome" value="success">
                            <button class="px-2 py-1 rounded bg-teal-50 border border-teal-200 text-teal-700 text-xs">Paid</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$stuck): ?>
                <tr><td colspan="8" class="p-6 text-center text-slate-500">
                    No stuck payouts found in the installed payout tables.
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
