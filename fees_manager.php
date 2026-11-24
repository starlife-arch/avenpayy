<?php
// fees_manager.php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

// Optional: Add your admin authentication check here
// if (!isset($_SESSION['user_id']) || $_SESSION['is_admin'] != 1) { header("Location: /login"); exit; }

$success_message = "";
$error_message = "";

// Default view mode ('b2c' or 'c2b')
$current_view = $_GET['view'] ?? 'b2c';
if (!in_array($current_view, ['b2c', 'c2b'])) {
    $current_view = 'b2c';
}

$active_service_type = ($current_view === 'c2b') ? 'c2b_topup' : 'b2c_withdrawal';

$edit_id = 0;
$edit_data = [
    'service_type' => $active_service_type,
    'min_amount' => '',
    'max_amount' => '',
    'fee_type' => 'fixed',
    'fee_value' => ''
];

// --- HANDLE EDIT POPULATE ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM transaction_fees WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $edit_data = $res->fetch_assoc();
        $active_service_type = $edit_data['service_type'];
        $current_view = ($active_service_type === 'c2b_topup') ? 'c2b' : 'b2c';
    }
    $stmt->close();
}

// --- HANDLE SAVE (CREATE / UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_fee') {
    $id           = (int)($_POST['fee_id'] ?? 0);
    $service_type = $_POST['service_type'] ?? '';
    $min_amount   = (float)($_POST['min_amount'] ?? 0);
    $max_amount   = (float)($_POST['max_amount'] ?? 0);
    $fee_type     = $_POST['fee_type'] ?? 'fixed';
    $fee_value    = (float)($_POST['fee_value'] ?? 0);

    $redirect_view = ($service_type === 'c2b_topup') ? 'c2b' : 'b2c';

    if (empty($service_type) || $max_amount <= 0 || $min_amount > $max_amount) {
        $error_message = "Please provide valid amount brackets (Min must be less than or equal to Max).";
    } else {
        if ($id > 0) {
            // Update existing tier
            $stmt = $conn->prepare("UPDATE transaction_fees SET service_type = ?, min_amount = ?, max_amount = ?, fee_type = ?, fee_value = ? WHERE id = ?");
            $stmt->bind_param("sddsdi", $service_type, $min_amount, $max_amount, $fee_type, $fee_value, $id);
            $msg = "Fee tier successfully updated.";
        } else {
            // Insert new tier
            $stmt = $conn->prepare("INSERT INTO transaction_fees (service_type, min_amount, max_amount, fee_type, fee_value, is_active) VALUES (?, ?, ?, ?, ?, 1)");
            $stmt->bind_param("sddsd", $service_type, $min_amount, $max_amount, $fee_type, $fee_value);
            $msg = "Fee tier successfully registered.";
        }

        if ($stmt->execute()) {
            $_SESSION['success'] = $msg;
            header("Location: /fees_manager.php?view=" . $redirect_view);
            exit();
        } else {
            $error_message = "Database error: Could not save fee tier.";
        }
        $stmt->close();
    }
}

// --- HANDLE DELETE FEE TIER ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $fee_id = (int)$_GET['id'];
    $stmt = $conn->prepare("DELETE FROM transaction_fees WHERE id = ?");
    $stmt->bind_param("i", $fee_id);
    $stmt->execute();
    $stmt->close();
    header("Location: /fees_manager.php?view=" . $current_view);
    exit();
}

// Session success message check
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

// --- FETCH REGISTERED FEES FOR SELECTED SERVICE ---
$stmt_fetch = $conn->prepare("SELECT * FROM transaction_fees WHERE service_type = ? ORDER BY min_amount ASC");
$stmt_fetch->bind_param("s", $active_service_type);
$stmt_fetch->execute();
$result_fees = $stmt_fetch->get_result();
$fees = $result_fees ? $result_fees->fetch_all(MYSQLI_ASSOC) : [];
$stmt_fetch->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta name="theme-color" content="#ffffff">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Fees - AvenPay</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #ffffff;
            --card-bg: rgba(248, 250, 252, 0.95);
            --card-border: rgba(15, 23, 42, 0.08);
            --input-bg: #ffffff;
            --input-border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #475569;
            --accent-purple: #60f2ce;
        }
        body { background-color: var(--bg-color); color: var(--text-main); }
        input:-webkit-autofill, select:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 30px #ffffff inset !important;
            -webkit-text-fill-color: #0f172a !important;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">

<?php 
$header_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';
if (file_exists($header_path)) include $header_path;
?>

<main class="max-w-5xl mx-auto p-4 md:p-6 w-full flex-1">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-200/80">
        <div>
            <h1 class="text-sm font-black text-slate-900 tracking-wide flex items-center gap-2">
                <i class="iclass fa-solid fa-percent text-teal-600"></i>
                <span>Fee Tiers Manager</span>
            </h1>
            <p class="text-[11px] text-slate-500 mt-0.5">Configure transaction fee tiers for B2C Withdrawals and C2B Top-Ups</p>
        </div>

        <!-- Service Toggle Switch -->
        <div class="flex items-center bg-[#0b0f19] border border-[#1e293b] p-1 rounded-sm">
            <a href="fees_manager.php?view=b2c" class="px-3 py-1.5 rounded-sm text-xs font-bold transition flex items-center gap-2 <?= $current_view === 'b2c' ? 'bg-teal-600 text-slate-900 shadow-md' : 'text-slate-400 hover:text-white' ?>">
                <i class="iclass fa-solid fa-money-bill-transfer text-[11px]"></i>
                <span>B2C Withdrawal</span>
            </a>
            <a href="fees_manager.php?view=c2b" class="px-3 py-1.5 rounded-sm text-xs font-bold transition flex items-center gap-2 <?= $current_view === 'c2b' ? 'bg-teal-600 text-slate-900 shadow-md' : 'text-slate-400 hover:text-white' ?>">
                <i class="iclass fa-solid fa-wallet text-[11px]"></i>
                <span>C2B Top-Up</span>
            </a>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 p-3 rounded-sm mb-4 text-xs flex items-center gap-2">
            <i class="iclass fa-solid fa-circle-check"></i>
            <span><?= htmlspecialchars($success_message) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="bg-rose-500/10 border border-rose-500/30 text-rose-600 p-3 rounded-sm mb-4 text-xs flex items-center gap-2">
            <i class="iclass fa-solid fa-triangle-exclamation"></i>
            <span><?= htmlspecialchars($error_message) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Registration / Edit Form Card -->
        <div class="md:col-span-1 bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] rounded-sm p-5 shadow-xl h-fit backdrop-blur-md">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="iclass fa-solid <?= $edit_id > 0 ? 'fa-pen-to-square text-amber-400' : 'fa-plus text-teal-400' ?>"></i> 
                    <?= $edit_id > 0 ? 'Edit Fee Tier' : 'Add Fee Tier' ?>
                </h2>
                <?php if ($edit_id > 0): ?>
                    <a href="fees_manager.php?view=<?= $current_view ?>" class="text-[10px] text-slate-500 hover:text-slate-900 underline">Cancel</a>
                <?php endif; ?>
            </div>

            <form action="fees_manager.php?view=<?= $current_view ?>" method="POST" class="space-y-3.5">
                <input type="hidden" name="action" value="save_fee">
                <input type="hidden" name="fee_id" value="<?= $edit_id ?>">

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Target Service</label>
                    <select name="service_type" required class="w-full bg-[#0b0f19] border border-[#1e293b] px-3.5 py-2.5 rounded-sm text-slate-900 text-xs outline-none focus:border-teal-500 transition">
                        <option value="b2c_withdrawal" <?= $edit_data['service_type'] === 'b2c_withdrawal' ? 'selected' : '' ?>>B2C Withdrawal</option>
                        <option value="c2b_topup" <?= $edit_data['service_type'] === 'c2b_topup' ? 'selected' : '' ?>>C2B Top-Up</option>
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Min Amount</label>
                        <input type="number" step="1" name="min_amount" value="<?= htmlspecialchars($edit_data['min_amount']) ?>" placeholder="1" required class="w-full bg-[#0b0f19] border border-[#1e293b] px-3.5 py-2.5 rounded-sm text-slate-900 text-xs outline-none focus:border-teal-500 transition font-mono">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Max Amount</label>
                        <input type="number" step="1" name="max_amount" value="<?= htmlspecialchars($edit_data['max_amount']) ?>" placeholder="1000" required class="w-full bg-[#0b0f19] border border-[#1e293b] px-3.5 py-2.5 rounded-sm text-slate-900 text-xs outline-none focus:border-teal-500 transition font-mono">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Fee Type</label>
                        <select name="fee_type" class="w-full bg-[#0b0f19] border border-[#1e293b] px-3.5 py-2.5 rounded-sm text-slate-900 text-xs outline-none focus:border-teal-500 transition">
                            <option value="fixed" <?= $edit_data['fee_type'] === 'fixed' ? 'selected' : '' ?>>Fixed (KES)</option>
                            <option value="percentage" <?= $edit_data['fee_type'] === 'percentage' ? 'selected' : '' ?>>Percentage (%)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Fee Value</label>
                        <input type="number" step="0.01" name="fee_value" value="<?= htmlspecialchars($edit_data['fee_value']) ?>" placeholder="15" required class="w-full bg-[#0b0f19] border border-[#1e293b] px-3.5 py-2.5 rounded-sm text-slate-900 text-xs outline-none focus:border-teal-500 transition font-mono">
                    </div>
                </div>

                <button type="submit" class="w-full mt-2 <?= $edit_id > 0 ? 'bg-amber-500 hover:bg-amber-400 text-slate-950' : 'bg-teal-600 hover:bg-teal-500 text-white' ?> font-black py-2.5 px-4 rounded-sm transition cursor-pointer flex items-center justify-center gap-2 text-xs shadow-lg">
                    <i class="iclass fa-solid <?= $edit_id > 0 ? 'fa-pen-to-square' : 'fa-save' ?>"></i> 
                    <?= $edit_id > 0 ? 'Update Fee Tier' : 'Register Fee Tier' ?>
                </button>
            </form>
        </div>

        <!-- Fees Listing Card for Selected Service -->
        <div class="md:col-span-2 bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] rounded-sm shadow-xl overflow-hidden backdrop-blur-md">
            <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
                <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                    <i class="iclass fa-solid <?= $current_view === 'c2b' ? 'fa-wallet' : 'fa-money-bill-transfer' ?> text-teal-600"></i> 
                    <span><?= $current_view === 'c2b' ? 'C2B Top-Up' : 'B2C Withdrawal' ?> Fee Brackets</span>
                </h2>
                <span class="text-[10px] bg-teal-500/10 border border-teal-500/20 text-teal-700 px-2 py-0.5 rounded-sm font-mono uppercase">
                    <?= count($fees) ?> Tiers Active
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-slate-50/60 text-slate-500 uppercase text-[10px] border-b border-slate-200/80 tracking-wider">
                        <tr>
                            <th class="p-3 pl-4">Min Amount</th>
                            <th class="p-3">Max Amount</th>
                            <th class="p-3">Fee Structure</th>
                            <th class="p-3 text-center pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80">
                        <?php if (empty($fees)): ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-500 text-xs">
                                    <i class="iclass fa-solid fa-folder-open text-3xl text-slate-600 mb-2 block"></i>
                                    No fee tiers registered for <?= $current_view === 'c2b' ? 'C2B Top-Up' : 'B2C Withdrawal' ?> yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($fees as $fee): ?>
                            <tr class="hover:bg-slate-100/40 transition-colors <?= $edit_id == $fee['id'] ? 'bg-teal-500/10' : '' ?>">
                                <td class="p-3 pl-4 font-mono text-slate-700">
                                    KES <?= number_format($fee['min_amount'], 0) ?>
                                </td>
                                <td class="p-3 font-mono text-slate-700">
                                    KES <?= number_format($fee['max_amount'], 0) ?>
                                </td>
                                <td class="p-3 font-semibold text-amber-600 font-mono">
                                    <?php if ($fee['fee_type'] === 'percentage'): ?>
                                        <?= rtrim(rtrim(number_format($fee['fee_value'], 2), '0'), '.') ?>%
                                    <?php else: ?>
                                        KES <?= number_format($fee['fee_value'], 0) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 pr-4 text-center flex items-center justify-center gap-1.5">
                                    <a href="fees_manager.php?view=<?= $current_view ?>&action=edit&id=<?= $fee['id'] ?>" class="w-7 h-7 rounded-sm bg-teal-500/10 border border-teal-500/20 text-teal-600 hover:bg-teal-500/20 inline-flex items-center justify-center transition text-xs" title="Edit Tier">
                                        <i class="iclass fa-solid fa-pen"></i>
                                    </a>
                                    <a href="fees_manager.php?view=<?= $current_view ?>&action=delete&id=<?= $fee['id'] ?>" onclick="return confirm('Are you sure you want to delete this fee tier?');" class="w-7 h-7 rounded-sm bg-rose-500/10 border border-rose-500/20 text-rose-600 hover:bg-rose-500/20 inline-flex items-center justify-center transition text-xs" title="Delete Tier">
                                        <i class="iclass fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php 
$footer_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php';
if (file_exists($footer_path)) include $footer_path;
?>

</body>
</html>