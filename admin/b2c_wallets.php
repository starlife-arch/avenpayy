<?php
require_once __DIR__ . '/../lib/require_admin.php';
require_once __DIR__ . '/../database_config/db.php';
require_once __DIR__ . '/../lib/ledger.php';

$adminId = (int)($_SESSION['admin_id'] ?? 0);
if ($adminId < 1) {
    http_response_code(403);
    exit('Forbidden');
}

if (empty($_SESSION['b2c_wallet_admin_csrf'])) {
    $_SESSION['b2c_wallet_admin_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = (string)$_SESSION['b2c_wallet_admin_csrf'];

function avB2cWalletEscape($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function avB2cWalletRedirect(int $userId, string $search = ''): void {
    $params = [];
    if ($userId > 0) $params['user_id'] = $userId;
    if ($search !== '') $params['search'] = $search;
    header('Location: /admin/b2c_wallets.php' . ($params ? '?' . http_build_query($params) : ''));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $action = (string)($_POST['action'] ?? '');
    $amountRaw = trim((string)($_POST['amount'] ?? ''));
    $reason = trim((string)($_POST['reason'] ?? ''));
    $operationKey = strtolower(trim((string)($_POST['operation_key'] ?? '')));
    $search = trim((string)($_POST['search'] ?? ''));

    $flash = ['type' => 'error', 'message' => 'The wallet operation could not be completed.'];
    if (!hash_equals($csrfToken, $postedToken)) {
        $flash['message'] = 'Your security token expired. Refresh the page and try again.';
    } elseif (!$userId || $userId < 1) {
        $flash['message'] = 'Select a valid customer wallet.';
    } elseif (!in_array($action, ['credit', 'debit', 'hold', 'release'], true)) {
        $flash['message'] = 'Choose a valid wallet operation.';
    } elseif (!preg_match('/^\d{1,12}(?:\.\d{1,2})?$/D', $amountRaw) || (float)$amountRaw <= 0) {
        $flash['message'] = 'Enter a valid amount greater than zero, with no more than two decimal places.';
    } elseif (strlen($reason) < 8 || strlen($reason) > 255) {
        $flash['message'] = 'A reason of at least 8 characters is required (maximum 255).';
    } elseif (!preg_match('/^[a-f0-9]{32}$/', $operationKey)) {
        $flash['message'] = 'This form has expired. Refresh the page and try again.';
    } else {
        $amount = round((float)$amountRaw, 2);
        $why = null;
        if ($action === 'credit' || $action === 'debit') {
            $delta = $action === 'credit' ? $amount : -$amount;
            $entryType = $action === 'credit' ? 'admin_credit' : 'admin_debit';
            $ok = av_wallet_apply(
                $conn,
                (int)$userId,
                $delta,
                $entryType,
                'admin_adjustment',
                $operationKey,
                $reason,
                true,
                $why,
                $adminId
            );
        } else {
            $ok = av_wallet_hold_apply(
                $conn,
                (int)$userId,
                $amount,
                $action,
                $operationKey,
                $reason,
                $adminId,
                $why
            );
        }

        if ($ok) {
            $labels = ['credit' => 'credited', 'debit' => 'deducted', 'hold' => 'placed on hold', 'release' => 'released from hold'];
            $flash = [
                'type' => 'success',
                'message' => 'KES ' . number_format($amount, 2) . ' successfully ' . $labels[$action] . ' ' . ($action === 'credit' || $action === 'debit' ? ($action === 'credit' ? 'to' : 'from') : 'for') . ' the selected B2C wallet.'
            ];
        } else {
            $messages = [
                'duplicate' => 'This exact operation was already processed. No second wallet movement was made.',
                'insufficient' => 'The available wallet balance is too low for this operation.',
                'insufficient_held' => 'The held balance is too low to release that amount.',
                'no_user' => 'The selected customer wallet no longer exists.',
                'zero' => 'The amount must be greater than zero.',
                'invalid' => 'The requested hold operation is invalid.'
            ];
            $flash['message'] = $messages[$why ?? ''] ?? 'The wallet operation failed. Check the private server error log.';
        }
    }

    $_SESSION['b2c_wallet_admin_flash'] = $flash;
    avB2cWalletRedirect((int)($userId ?: 0), $search);
}

$flash = $_SESSION['b2c_wallet_admin_flash'] ?? null;
unset($_SESSION['b2c_wallet_admin_flash']);

$search = trim((string)($_GET['search'] ?? ''));
$selectedUserId = max(0, (int)($_GET['user_id'] ?? 0));
$users = [];

if ($search !== '') {
    $like = '%' . addcslashes($search, '\\%_') . '%';
    $stmt = $conn->prepare("
        SELECT id, username, email, phone_number, wallet_id, b2c_wallet_balance, on_hold_amount
        FROM users
        WHERE username LIKE ? OR email LIKE ? OR phone_number LIKE ? OR wallet_id LIKE ?
        ORDER BY id DESC
        LIMIT 50
    ");
    $stmt->bind_param('ssss', $like, $like, $like, $like);
} else {
    $stmt = $conn->prepare("
        SELECT id, username, email, phone_number, wallet_id, b2c_wallet_balance, on_hold_amount
        FROM users
        ORDER BY id DESC
        LIMIT 25
    ");
}
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $users[] = $row;
$stmt->close();

$selectedUser = null;
if ($selectedUserId > 0) {
    $stmt = $conn->prepare("
        SELECT id, username, email, phone_number, wallet_id, b2c_wallet_balance, on_hold_amount
        FROM users WHERE id = ? LIMIT 1
    ");
    $stmt->bind_param('i', $selectedUserId);
    $stmt->execute();
    $selectedUser = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

$ledgerRows = [];
if ($selectedUser) {
    $stmt = $conn->prepare("
        SELECT l.id, l.entry_type, l.amount, l.balance_after, l.held_after,
               l.ref_type, l.ref_id, l.note, l.created_at,
               a.username AS admin_username
        FROM wallet_ledger l
        LEFT JOIN admins a ON a.id = l.admin_id
        WHERE l.user_id = ?
        ORDER BY l.id DESC
        LIMIT 50
    ");
    $stmt->bind_param('i', $selectedUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) $ledgerRows[] = $row;
    $stmt->close();
}

$operationKey = bin2hex(random_bytes(16));
$page_title = 'B2C Wallet Management';
$active = 'b2c_wallets';
require_once __DIR__ . '/admin-frame/header.php';
?>

<div class="space-y-5 pb-10">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-base font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <i class="iclass fa-solid fa-wallet text-teal-600"></i> B2C Wallet Management
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Admin credits, deductions, fund holds and releases. Every movement is recorded in the wallet ledger.</p>
        </div>
        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 bg-slate-100 dark:bg-slate-800 px-3 py-2 rounded">KES · Admin only</span>
    </div>

    <?php if ($flash): ?>
        <div class="p-3 rounded border text-xs <?= ($flash['type'] ?? '') === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800 dark:bg-emerald-900/20 dark:border-emerald-800 dark:text-emerald-300' : 'bg-rose-50 border-rose-200 text-rose-800 dark:bg-rose-900/20 dark:border-rose-800 dark:text-rose-300' ?>">
            <?= avB2cWalletEscape($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>

    <section class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded p-4 shadow-xs">
        <form method="GET" action="/admin/b2c_wallets.php" class="flex flex-col sm:flex-row gap-2">
            <input type="search" name="search" value="<?= avB2cWalletEscape($search) ?>" placeholder="Search username, email, phone or wallet ID..." class="flex-1 px-3 py-2 text-xs rounded border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
            <button class="px-4 py-2 rounded bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold">Find wallets</button>
            <?php if ($search !== ''): ?><a href="/admin/b2c_wallets.php" class="px-4 py-2 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold text-center">Clear</a><?php endif; ?>
        </form>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[700px] text-left text-xs">
                <thead class="text-[10px] uppercase tracking-wider text-slate-500 bg-slate-50 dark:bg-slate-900/60">
                    <tr>
                        <th class="p-3">Customer</th><th class="p-3">Wallet ID</th><th class="p-3 text-right">Available</th><th class="p-3 text-right">On hold</th><th class="p-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                <?php foreach ($users as $user): ?>
                    <tr class="<?= (int)$user['id'] === $selectedUserId ? 'bg-teal-50/70 dark:bg-teal-900/10' : '' ?>">
                        <td class="p-3">
                            <div class="font-bold text-slate-800 dark:text-slate-100"><?= avB2cWalletEscape($user['username']) ?> <span class="text-slate-400 font-normal">#<?= (int)$user['id'] ?></span></div>
                            <div class="text-[10px] text-slate-500"><?= avB2cWalletEscape($user['email']) ?> · <?= avB2cWalletEscape($user['phone_number']) ?></div>
                        </td>
                        <td class="p-3 font-mono text-slate-600 dark:text-slate-300"><?= avB2cWalletEscape($user['wallet_id']) ?></td>
                        <td class="p-3 text-right font-bold tabular-nums text-emerald-700 dark:text-emerald-400">KES <?= number_format((float)$user['b2c_wallet_balance'], 2) ?></td>
                        <td class="p-3 text-right font-bold tabular-nums text-amber-700 dark:text-amber-400">KES <?= number_format((float)$user['on_hold_amount'], 2) ?></td>
                        <td class="p-3 text-right"><a class="inline-block px-3 py-1.5 rounded bg-teal-600 hover:bg-teal-700 text-white font-bold" href="/admin/b2c_wallets.php?user_id=<?= (int)$user['id'] ?><?= $search !== '' ? '&amp;search=' . urlencode($search) : '' ?>">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$users): ?><tr><td colspan="5" class="p-6 text-center text-slate-500">No customer wallets matched your search.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if ($selectedUser): ?>
        <?php
            $available = (float)$selectedUser['b2c_wallet_balance'];
            $held = (float)$selectedUser['on_hold_amount'];
            $owned = $available + $held;
        ?>
        <section class="space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">Wallet: <?= avB2cWalletEscape($selectedUser['username']) ?></h2>
                    <p class="text-[11px] text-slate-500">Wallet ID <span class="font-mono"><?= avB2cWalletEscape($selectedUser['wallet_id']) ?></span> · Customer #<?= (int)$selectedUser['id'] ?></p>
                </div>
                <a href="/admin/b2c_wallets.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>" class="text-xs text-teal-700 dark:text-teal-400 font-semibold">Close wallet details</a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="p-4 rounded border border-emerald-200 bg-emerald-50 dark:bg-emerald-900/10 dark:border-emerald-900/50">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-emerald-700 dark:text-emerald-400">Available balance</div>
                    <div class="mt-1 text-lg font-black text-emerald-800 dark:text-emerald-300 tabular-nums">KES <?= number_format($available, 2) ?></div>
                </div>
                <div class="p-4 rounded border border-amber-200 bg-amber-50 dark:bg-amber-900/10 dark:border-amber-900/50">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-amber-700 dark:text-amber-400">On hold</div>
                    <div class="mt-1 text-lg font-black text-amber-800 dark:text-amber-300 tabular-nums">KES <?= number_format($held, 2) ?></div>
                </div>
                <div class="p-4 rounded border border-slate-200 bg-white dark:bg-slate-900 dark:border-slate-700">
                    <div class="text-[10px] uppercase tracking-wider font-bold text-slate-500">Total customer funds</div>
                    <div class="mt-1 text-lg font-black text-slate-800 dark:text-slate-100 tabular-nums">KES <?= number_format($owned, 2) ?></div>
                    <p class="text-[10px] text-slate-500 mt-1">Available + held</p>
                </div>
            </div>

            <div class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded p-4 shadow-xs">
                <h3 class="text-xs font-bold text-slate-800 dark:text-slate-100 mb-1">Admin wallet operation</h3>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-4">Credits and deductions change available funds. Hold and release move funds between available and held without changing the customer's total funds. A reason is mandatory.</p>
                <form method="POST" action="/admin/b2c_wallets.php" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <input type="hidden" name="csrf_token" value="<?= avB2cWalletEscape($csrfToken) ?>">
                    <input type="hidden" name="user_id" value="<?= (int)$selectedUser['id'] ?>">
                    <input type="hidden" name="search" value="<?= avB2cWalletEscape($search) ?>">
                    <input type="hidden" name="operation_key" value="<?= avB2cWalletEscape($operationKey) ?>">
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">Operation</label>
                        <select name="action" required class="w-full px-3 py-2.5 text-xs rounded border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
                            <option value="credit">Add funds (credit)</option>
                            <option value="debit">Deduct funds (debit)</option>
                            <option value="hold">Place funds on hold</option>
                            <option value="release">Release held funds</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">Amount (KES)</label>
                        <input type="number" name="amount" min="0.01" max="999999999999.99" step="0.01" required placeholder="0.00" class="w-full px-3 py-2.5 text-xs rounded border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] uppercase tracking-wider font-bold text-slate-500 mb-1">Reason / internal reference</label>
                        <textarea name="reason" minlength="8" maxlength="255" required rows="2" placeholder="Explain why this wallet operation is being made..." class="w-full px-3 py-2.5 text-xs rounded border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100"></textarea>
                    </div>
                    <div class="md:col-span-2 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <p class="text-[10px] text-slate-500">All operations are attributed to your admin account and recorded in the immutable wallet ledger.</p>
                        <button type="submit" onclick="return confirm('Confirm this B2C wallet operation?')" class="px-5 py-2.5 rounded bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold">Confirm operation</button>
                    </div>
                </form>
            </div>

            <div class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-200 dark:border-slate-800">
                    <h3 class="text-xs font-bold text-slate-800 dark:text-slate-100">Wallet ledger and admin audit trail</h3>
                    <p class="text-[10px] text-slate-500 mt-1">Latest 50 entries. Each row records the resulting available and held balances.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[950px] text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-900/60 text-[10px] uppercase tracking-wider text-slate-500">
                            <tr><th class="p-3">Date</th><th class="p-3">Operation</th><th class="p-3 text-right">Movement</th><th class="p-3 text-right">Available after</th><th class="p-3 text-right">Held after</th><th class="p-3">Admin / source</th><th class="p-3">Reason / reference</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <?php foreach ($ledgerRows as $entry): ?>
                            <?php
                                $entryType = (string)$entry['entry_type'];
                                $movement = (float)$entry['amount'];
                                $movementLabel = match ($entryType) {
                                    'admin_hold' => 'Hold',
                                    'admin_release' => 'Release',
                                    'admin_credit' => 'Admin credit',
                                    'admin_debit' => 'Admin debit',
                                    default => ucwords(str_replace('_', ' ', $entryType))
                                };
                            ?>
                            <tr>
                                <td class="p-3 whitespace-nowrap text-slate-500"><?= avB2cWalletEscape($entry['created_at']) ?></td>
                                <td class="p-3 font-semibold text-slate-700 dark:text-slate-200"><?= avB2cWalletEscape($movementLabel) ?></td>
                                <td class="p-3 text-right font-mono font-bold <?= $movement >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' ?>"><?= $movement > 0 ? '+' : '' ?>KES <?= number_format($movement, 2) ?></td>
                                <td class="p-3 text-right font-mono">KES <?= number_format((float)$entry['balance_after'], 2) ?></td>
                                <td class="p-3 text-right font-mono">KES <?= number_format((float)$entry['held_after'], 2) ?></td>
                                <td class="p-3"><?= avB2cWalletEscape($entry['admin_username'] ?: 'System / automated') ?></td>
                                <td class="p-3 max-w-sm">
                                    <div class="text-slate-700 dark:text-slate-200 break-words"><?= avB2cWalletEscape($entry['note']) ?></div>
                                    <div class="text-[10px] text-slate-400 font-mono mt-1"><?= avB2cWalletEscape($entry['ref_type']) ?> · <?= avB2cWalletEscape($entry['ref_id']) ?></div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$ledgerRows): ?><tr><td colspan="7" class="p-6 text-center text-slate-500">No ledger entries recorded for this wallet yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    <?php elseif ($selectedUserId > 0): ?>
        <div class="p-4 rounded border border-rose-200 bg-rose-50 text-xs text-rose-800">The selected customer wallet was not found.</div>
    <?php else: ?>
        <div class="p-4 rounded border border-slate-200 bg-slate-50 dark:bg-slate-900 dark:border-slate-700 text-xs text-slate-600 dark:text-slate-300">Select a customer wallet above to add or deduct funds, place a hold, release held funds, and review its ledger.</div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/admin-frame/footer.php'; ?>
