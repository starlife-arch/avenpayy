<?php
// admin/admin_settlement.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

// Authentication check for admin session
if (!isset($_SESSION['admin_id'])) {
    header("Location: /admin/");
    exit();
}

$page_title = "Settlement Accounts";

$success_message = "";
$error_message = "";

$edit_id = 0;
$edit_data = [
    'account_type'   => 'bank',
    'account_name'   => '',
    'account_number' => '',
    'bank_name'      => '',
    'bank_branch'    => '',
    'paybill_number' => '',
    'till_number'    => '',
    'is_default'     => 0
];

// --- HANDLE EDIT POPULATE ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM admin_settlement_accounts WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $edit_data = $res->fetch_assoc();
    }
    $stmt->close();
}

// --- HANDLE SAVE (CREATE / UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_account') {
    $id             = (int)($_POST['account_id'] ?? 0);
    $account_type   = $_POST['account_type'] ?? 'bank';
    $account_name   = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $bank_name      = trim($_POST['bank_name'] ?? '');
    $bank_branch    = trim($_POST['bank_branch'] ?? '');
    $paybill_number = trim($_POST['paybill_number'] ?? '');
    $till_number    = trim($_POST['till_number'] ?? '');
    $is_default     = isset($_POST['is_default']) ? 1 : 0;

    if (empty($account_name)) {
        $error_message = "Account holder name is required.";
    } else {
        // Reset existing primary flag if this account is marked primary
        if ($is_default === 1) {
            $conn->query("UPDATE admin_settlement_accounts SET is_default = 0");
        }

        if ($id > 0) {
            // Update existing record
            $stmt = $conn->prepare("UPDATE admin_settlement_accounts SET account_type = ?, account_name = ?, account_number = ?, bank_name = ?, bank_branch = ?, paybill_number = ?, till_number = ?, is_default = ? WHERE id = ?");
            $stmt->bind_param("sssssssii", $account_type, $account_name, $account_number, $bank_name, $bank_branch, $paybill_number, $till_number, $is_default, $id);
            $msg = "Settlement account updated successfully.";
        } else {
            // Insert new record
            $stmt = $conn->prepare("INSERT INTO admin_settlement_accounts (account_type, account_name, account_number, bank_name, bank_branch, paybill_number, till_number, is_default, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sssssssi", $account_type, $account_name, $account_number, $bank_name, $bank_branch, $paybill_number, $till_number, $is_default);
            $msg = "Settlement account added successfully.";
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = $msg;
            header("Location: /admin/admin_settlement.php");
            exit();
        } else {
            $error_message = "Database error: Unable to save account.";
        }
        $stmt->close();
    }
}

// --- HANDLE SET PRIMARY ---
if (isset($_GET['action']) && $_GET['action'] === 'set_default' && isset($_GET['id'])) {
    $acc_id = (int)$_GET['id'];
    $conn->query("UPDATE admin_settlement_accounts SET is_default = 0");
    $stmt = $conn->prepare("UPDATE admin_settlement_accounts SET is_default = 1 WHERE id = ?");
    $stmt->bind_param("i", $acc_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = "Primary settlement account updated.";
    header("Location: /admin/admin_settlement.php");
    exit();
}

// --- HANDLE DELETE ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $acc_id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM admin_settlement_accounts WHERE id = ?");
    $stmt->bind_param("i", $acc_id);
    $stmt->execute();
    $stmt->close();
    $_SESSION['success'] = "Settlement account removed.";
    header("Location: /admin/admin_settlement.php");
    exit();
}

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Fetch all settlement accounts
$result_accounts = $conn->query("SELECT * FROM admin_settlement_accounts ORDER BY is_default DESC, id DESC");
$accounts = $result_accounts ? $result_accounts->fetch_all(MYSQLI_ASSOC) : [];

// Include admin header via admin-frame folder
include_once __DIR__ . '/admin-frame/header.php'; 
?>

<!-- BREADCRUMB & HEADER TITLE -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 pb-4 border-b border-inherit">
    <div>
        <h1 class="text-sm font-black tracking-wide text-slate-800 dark:text-white uppercase flex items-center gap-2">
            <i class="iclass fa-solid fa-building-columns text-teal-600 dark:text-teal-400"></i>
            <span>Admin Settlement Accounts</span>
        </h1>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Manage bank accounts, Paybills, and Till numbers for automated payouts</p>
    </div>
</div>

<?php if ($success_message): ?>
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 p-3 rounded-sm text-xs flex items-center gap-2 mb-4">
        <i class="iclass fa-solid fa-circle-check"></i>
        <span><?= htmlspecialchars($success_message) ?></span>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 p-3 rounded-sm text-xs flex items-center gap-2 mb-4">
        <i class="iclass fa-solid fa-triangle-exclamation"></i>
        <span><?= htmlspecialchars($error_message) ?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ADD / EDIT FORM CARD -->
    <div class="lg:col-span-1 dash-card rounded-sm p-5 h-fit">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-inherit">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-2">
                <i class="iclass fa-solid <?= $edit_id > 0 ? 'fa-pen-to-square text-amber-500' : 'fa-plus text-teal-500' ?>"></i> 
                <?= $edit_id > 0 ? 'Edit Account' : 'Add Settlement Account' ?>
            </h2>
            <?php if ($edit_id > 0): ?>
                <a href="/admin/admin_settlement.php" class="text-[10px] text-rose-500 hover:underline">Cancel</a>
            <?php endif; ?>
        </div>

        <form action="/admin/admin_settlement.php" method="POST" class="space-y-3.5">
            <input type="hidden" name="action" value="save_account">
            <input type="hidden" name="account_id" value="<?= $edit_id ?>">

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Account Type</label>
                <select id="accountTypeSelect" name="account_type" onchange="toggleFormFields(this.value)" required class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-3 py-2 rounded-sm text-xs text-slate-800 dark:text-slate-100 outline-none focus:border-teal-500">
                    <option value="bank" <?= $edit_data['account_type'] === 'bank' ? 'selected' : '' ?>>Bank Account</option>
                    <option value="paybill" <?= $edit_data['account_type'] === 'paybill' ? 'selected' : '' ?>>M-Pesa Paybill</option>
                    <option value="till" <?= $edit_data['account_type'] === 'till' ? 'selected' : '' ?>>M-Pesa Till Number</option>
                </select>
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Account Holder Name</label>
                <input type="text" name="account_name" value="<?= htmlspecialchars($edit_data['account_name']) ?>" placeholder="e.g. AvenPay Ltd Main" required class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-3 py-2 rounded-sm text-xs text-slate-800 dark:text-slate-100 outline-none focus:border-teal-500">
            </div>

            <!-- BANK FIELDS -->
            <div id="bankFields" class="space-y-3.5">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Bank Name</label>
                    <input type="text" name="bank_name" value="<?= htmlspecialchars($edit_data['bank_name']) ?>" placeholder="e.g. Equity Bank / KCB" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-3 py-2 rounded-sm text-xs text-slate-800 dark:text-slate-100 outline-none focus:border-teal-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Bank Account Number</label>
                    <input type="text" name="account_number" value="<?= htmlspecialchars($edit_data['account_number']) ?>" placeholder="e.g. 01234567890" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-3 py-2 rounded-sm text-xs text-slate-800 dark:text-slate-100 font-mono outline-none focus:border-teal-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Branch Name (Optional)</label>
                    <input type="text" name="bank_branch" value="<?= htmlspecialchars($edit_data['bank_branch']) ?>" placeholder="e.g. Westlands Branch" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-3 py-2 rounded-sm text-xs text-slate-800 dark:text-slate-100 outline-none focus:border-teal-500">
                </div>
            </div>

            <!-- PAYBILL FIELDS -->
            <div id="paybillFields" class="space-y-3.5 hidden">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Paybill Business Number</label>
                    <input type="text" name="paybill_number" value="<?= htmlspecialchars($edit_data['paybill_number']) ?>" placeholder="e.g. 400200" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-3 py-2 rounded-sm text-xs text-slate-800 dark:text-slate-100 font-mono outline-none focus:border-teal-500">
                </div>
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Account Reference</label>
                    <input type="text" name="account_number" value="<?= htmlspecialchars($edit_data['account_number']) ?>" placeholder="e.g. AVENPAY" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-3 py-2 rounded-sm text-xs text-slate-800 dark:text-slate-100 font-mono outline-none focus:border-teal-500">
                </div>
            </div>

            <!-- TILL FIELDS -->
            <div id="tillFields" class="space-y-3.5 hidden">
                <div>
                    <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Till Number</label>
                    <input type="text" name="till_number" value="<?= htmlspecialchars($edit_data['till_number']) ?>" placeholder="e.g. 889900" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-3 py-2 rounded-sm text-xs text-slate-800 dark:text-slate-100 font-mono outline-none focus:border-teal-500">
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" id="is_default" name="is_default" value="1" <?= $edit_data['is_default'] == 1 ? 'checked' : '' ?> class="accent-teal-500 rounded-sm cursor-pointer">
                <label for="is_default" class="text-xs text-slate-600 dark:text-slate-300 font-medium cursor-pointer">Set as Primary Settlement Account</label>
            </div>

            <button type="submit" class="w-full mt-2 <?= $edit_id > 0 ? 'bg-amber-500 hover:bg-amber-600' : 'bg-teal-600 hover:bg-teal-500' ?> text-white font-bold py-2 px-4 rounded-sm transition cursor-pointer flex items-center justify-center gap-2 text-xs">
                <i class="iclass fa-solid <?= $edit_id > 0 ? 'fa-pen-to-square' : 'fa-save' ?>"></i> 
                <?= $edit_id > 0 ? 'Update Account' : 'Save Settlement Account' ?>
            </button>
        </form>
    </div>

    <!-- LIST OF REGISTERED ACCOUNTS -->
    <div class="lg:col-span-2 dash-card rounded-sm shadow-xl overflow-hidden">
        <div class="p-4 border-b border-inherit flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-2">
                <i class="iclass fa-solid fa-list text-teal-600 dark:text-teal-400"></i> Active Settlement Accounts
            </h2>
            <span class="text-[10px] bg-teal-500/10 border border-teal-500/20 text-teal-600 dark:text-teal-400 px-2 py-0.5 rounded-sm font-mono uppercase">
                <?= count($accounts) ?> Configured
            </span>
        </div>

        <?php if (empty($accounts)): ?>
            <div class="p-8 text-center text-slate-500 text-xs">
                <i class="iclass fa-solid fa-building-columns text-3xl text-slate-400 dark:text-slate-600 mb-2 block"></i>
                No settlement accounts found. Register one using the form on the left.
            </div>
        <?php else: ?>
            <div class="divide-y divide-inherit">
                <?php foreach ($accounts as $acc): ?>
                    <div class="p-4 hover:bg-slate-100/50 dark:hover:bg-slate-900/40 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-black text-slate-800 dark:text-white"><?= htmlspecialchars($acc['account_name']) ?></span>
                                <?php if ($acc['is_default'] == 1): ?>
                                    <span class="text-[9px] bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 px-1.5 py-0.5 rounded-sm font-bold uppercase">Primary</span>
                                <?php endif; ?>
                            </div>

                            <div class="text-[11px] text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-3">
                                <?php if ($acc['account_type'] === 'bank'): ?>
                                    <span><i class="iclass fa-solid fa-building-columns text-teal-500 mr-1"></i> Bank: <strong class="text-slate-700 dark:text-slate-200"><?= htmlspecialchars($acc['bank_name']) ?></strong></span>
                                    <span>A/C: <strong class="text-slate-700 dark:text-slate-200 font-mono"><?= htmlspecialchars($acc['account_number']) ?></strong></span>
                                <?php elseif ($acc['account_type'] === 'paybill'): ?>
                                    <span><i class="iclass fa-solid fa-mobile-screen text-teal-500 mr-1"></i> Paybill: <strong class="text-slate-700 dark:text-slate-200 font-mono"><?= htmlspecialchars($acc['paybill_number']) ?></strong></span>
                                    <span>Ref: <strong class="text-slate-700 dark:text-slate-200 font-mono"><?= htmlspecialchars($acc['account_number']) ?></strong></span>
                                <?php elseif ($acc['account_type'] === 'till'): ?>
                                    <span><i class="iclass fa-solid fa-store text-teal-500 mr-1"></i> Till No: <strong class="text-slate-700 dark:text-slate-200 font-mono"><?= htmlspecialchars($acc['till_number']) ?></strong></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex items-center gap-1.5 self-end sm:self-center">
                            <?php if ($acc['is_default'] == 0): ?>
                                <a href="/admin/admin_settlement.php?action=set_default&id=<?= $acc['id'] ?>" class="text-[10px] bg-slate-200 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:text-teal-500 px-2 py-1 rounded-sm transition font-medium" title="Set as Primary">
                                    Set Primary
                                </a>
                            <?php endif; ?>
                            <a href="/admin/admin_settlement.php?action=edit&id=<?= $acc['id'] ?>" class="w-7 h-7 rounded-sm bg-teal-500/10 border border-teal-500/20 text-teal-600 dark:text-teal-400 hover:bg-teal-500/20 inline-flex items-center justify-center transition text-xs" title="Edit Account">
                                <i class="iclass fa-solid fa-pen"></i>
                            </a>
                            <a href="/admin/admin_settlement.php?action=delete&id=<?= $acc['id'] ?>" onclick="return confirm('Delete this settlement account?');" class="w-7 h-7 rounded-sm bg-rose-500/10 border border-rose-500/20 text-rose-500 hover:bg-rose-500/20 inline-flex items-center justify-center transition text-xs" title="Delete Account">
                                <i class="iclass fa-solid fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
function toggleFormFields(type) {
    const bankFields = document.getElementById('bankFields');
    const paybillFields = document.getElementById('paybillFields');
    const tillFields = document.getElementById('tillFields');

    bankFields.classList.add('hidden');
    paybillFields.classList.add('hidden');
    tillFields.classList.add('hidden');

    if (type === 'bank') {
        bankFields.classList.remove('hidden');
    } else if (type === 'paybill') {
        paybillFields.classList.remove('hidden');
    } else if (type === 'till') {
        tillFields.classList.remove('hidden');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('accountTypeSelect');
    if (select) toggleFormFields(select.value);
});
</script>

<?php 
// Include admin footer via admin-frame folder
include_once __DIR__ . '/admin-frame/footer.php'; 
?>