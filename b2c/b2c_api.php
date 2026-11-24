<?php
// b2c/index.php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

$user_id = $_SESSION['user_id'];$balance = 0;
$total_withdrawn = 0;
$total_topped = 0;
$user_wallet_id = '';$use_custom_mpesa = 0;

// 1. Fetch user data from database
$stmt =$conn->prepare("SELECT b2c_wallet_balance, wallet_id, use_custom_mpesa FROM users WHERE id = ?");
if ($stmt) {$stmt->bind_param("i", $user_id);$stmt->execute();
    $res =$stmt->get_result()->fetch_assoc();
    if ($res) {
        $balance =$res['b2c_wallet_balance'] ?? 0;
        $user_wallet_id = $res['wallet_id'] ?? '';$use_custom_mpesa = (int)($res['use_custom_mpesa'] ?? 0); }$stmt->close();
}

// 2. Fetch Total Paid Out
if (!empty($user_wallet_id)) {
    $payoutStmt =$conn->prepare("
        SELECT SUM(transaction_amount) as total_out 
        FROM mpesa_b2c_transactions 
        WHERE wallet_id = ? AND result_code = 0
    ");
    if ($payoutStmt) {$payoutStmt->bind_param("s", $user_wallet_id);$payoutStmt->execute();
        $payoutRes =$payoutStmt->get_result()->fetch_assoc();
        if ($payoutRes && isset($payoutRes['total_out'])) {
            $total_withdrawn =$payoutRes['total_out'];
        }
        $payoutStmt->close();
    }
}

// 3. Fetch Total Topped In
$topupStmt =$conn->prepare("
    SELECT SUM(amount) as total_in 
    FROM b2c_topup_transactions 
    WHERE user_id = ? AND status = 'completed'
");
if ($topupStmt) {$topupStmt->bind_param("i", $user_id);$topupStmt->execute();
    $topupRes =$topupStmt->get_result()->fetch_assoc();
    if ($topupRes && isset($topupRes['total_in'])) {
        $total_topped =$topupRes['total_in'];
    }
    $topupStmt->close();
}
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
    <title>Wallet & Payouts - AvenPay</title>
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
        
        /* ELIMINATE WHITE AUTOFILL BACKGROUND & TEXT COLOR OVERRIDE */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active,
        select:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
            -webkit-text-fill-color: #0f172a !important;
            transition: background-color 5000s ease-in-out 0s;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .spinner {
            border: 2px solid rgba(15, 23, 42, 0.15);
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border-left-color: #87f9dc;
            animation: spin 0.8s linear infinite;
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
        }
        input, select { transition: all 0.2s; }
        input:focus, select:focus { border-color: #60f2ce; outline: none; box-shadow: 0 0 0 2px rgba(96, 242, 206, 0.2); }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">

<?php 
$header_path =$_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';
if (file_exists($header_path)) include$header_path;
?>

<main class="max-w-2xl mx-auto p-4 md:p-6 w-full flex-1 space-y-5">
    
    <!-- Top Header Title -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200/80">
        <div>
            <h1 class="text-sm font-black text-slate-900 tracking-wide flex items-center gap-2">
                <i class="iclass fa-solid fa-money-bill-transfer text-teal-600"></i>
                <span>B2C Wallet Operations</span>
            </h1>
            <p class="text-[11px] text-slate-500 mt-0.5">Manage your wallet deposits and instant disbursement payouts</p>
        </div>
        <div>
            <a href="/b2c/transactions.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-1.5 rounded-sm text-xs font-bold transition flex items-center gap-1.5 shadow-md">
                <i class="iclass fa-solid fa-receipt text-teal-600"></i> View History
            </a>
        </div>
    </div>

    <!-- 3-Card Metrics Grid (Whole Numbers) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white border border-slate-200 p-4 rounded-sm shadow-md">
            <div class="flex items-center justify-between text-slate-500 mb-1">
                <span class="text-[10px] uppercase font-bold tracking-wider">Current Balance</span>
                <i class="iclass fa-solid fa-wallet text-emerald-600 text-xs"></i>
            </div>
            <div class="text-base font-mono font-black text-emerald-600">
                KES <?= number_format($balance, 0) ?>
            </div>
        </div>

        <div class="bg-white border border-slate-200 p-4 rounded-sm shadow-md">
            <div class="flex items-center justify-between text-slate-500 mb-1">
                <span class="text-[10px] uppercase font-bold tracking-wider">Total Paid Out</span>
                <i class="iclass fa-solid fa-paper-plane text-amber-600 text-xs"></i>
            </div>
            <div class="text-base font-mono font-black text-amber-600">
                KES <?= number_format($total_withdrawn, 0) ?>
            </div>
        </div>

        <div class="bg-white border border-slate-200 p-4 rounded-sm shadow-md">
            <div class="flex items-center justify-between text-slate-500 mb-1">
                <span class="text-[10px] uppercase font-bold tracking-wider">Total Topped In</span>
                <i class="iclass fa-solid fa-cloud-arrow-down text-teal-600 text-xs"></i>
            </div>
            <div class="text-base font-mono font-black text-teal-600">
                KES <?= number_format($total_topped, 0) ?>
            </div>
        </div>
    </div>

    <!-- Main Card Wrapper -->
    <div class="bg-white border border-slate-200 rounded-sm shadow-xl backdrop-blur-md overflow-hidden">
        
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between gap-3">
            <h2 id="formTitle" class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-wallet text-teal-600"></i> Top Up Wallet
            </h2>

            <div class="flex items-center bg-white border border-slate-200 p-1 rounded-sm">
                <button type="button" id="tabTopupBtn" onclick="switchTab('topup')" class="px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer bg-teal-600 text-white shadow-md">
                    Top Up
                </button>
                <button type="button" id="tabPaymentBtn" onclick="switchTab('payment')" class="px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer text-slate-500 hover:text-slate-900">
                    Make Payment
                </button>
            </div>
        </div>

        <div class="p-5">
            <!-- TAB 1: TOP UP FORM -->
            <div id="topupSection" class="space-y-4">
                <form id="topupForm" class="space-y-4" autocomplete="off">
                    <input type="text" name="fake_username" style="display:none !important;" tabindex="-1">
                    
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Wallet ID (Locked)</label>
                        <input type="text" name="wallet_id" value="<?= htmlspecialchars($user_wallet_id) ?>" readonly class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-sm text-xs text-slate-500 font-mono cursor-not-allowed select-none">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">B2C Security Credential / API Key</label>
                        <input type="password" name="security_credential" placeholder="Enter your B2C security credential" required autocomplete="new-password" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">M-Pesa Phone Number</label>
                        <input type="text" name="phone" placeholder="07XXXXXXXX / 01XXXXXXXX" required autocomplete="off" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Amount (KES)</label>
                        <input type="number" name="amount" min="10" placeholder="e.g. 500" required autocomplete="off" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>

                    <button type="submit" id="topupBtn" class="w-full bg-teal-600 hover:bg-teal-500 active:bg-teal-700 text-white py-2.5 rounded-sm font-bold text-xs uppercase tracking-wider transition shadow-md cursor-pointer flex items-center justify-center gap-2">
                        <i class="iclass fa-solid fa-cloud-arrow-down"></i> Initiate Top Up
                    </button>
                </form>
                <div id="topupStatus" class="text-center text-xs font-medium pt-1"></div>
            </div>

            <!-- TAB 2: MAKE PAYMENTS FORM (WITH 3 WITHDRAWAL TYPES) -->
            <div id="paymentSection" class="space-y-4 hidden">
                <form id="paymentForm" class="space-y-4" autocomplete="off">
                    <!-- Dummy hidden inputs to block password managers -->
                    <input type="text" name="fake_username" style="display:none !important;" tabindex="-1">
                    <input type="password" name="fake_password" style="display:none !important;" tabindex="-1">

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Wallet ID (Locked)</label>
                        <input type="text" name="wallet_id" value="<?= htmlspecialchars($user_wallet_id) ?>" readonly class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-sm text-xs text-slate-500 font-mono cursor-not-allowed select-none">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">B2C Security Credential / API Key</label>
                        <input type="password" name="security_credential" placeholder="Enter your B2C security credential" required autocomplete="new-password" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>

                    <!-- Command ID / Withdrawal Type Selection -->
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Payment Type (Command ID)</label>
                        <select name="command_id" required class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700 cursor-pointer">
                            <option value="BusinessPayment">Business Payment (Standard Payout)</option>
                            <option value="SalaryPayment">Salary Payment (Staff Disbursement)</option>
                            <option value="PromotionPayment">Promotion Payment (Bonus / Reward)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Recipient Phone Number</label>
                        <input type="text" name="phone" placeholder="07XXXXXXXX / 01XXXXXXXX" required autocomplete="off" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Payout Amount (KES)</label>
                        <input type="number" name="amount" min="10" placeholder="e.g. 1000" required autocomplete="off" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Withdrawal PIN</label>
                        <!-- Anti-Autofill & Password Manager Protection -->
                        <input type="password" name="pin" placeholder="Enter your 4-digit security PIN" required maxlength="4" 
                               autocomplete="one-time-code" inputmode="numeric" data-lpignore="true" 
                               readonly onfocus="this.removeAttribute('readonly');"
                               class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>

                    <button type="submit" id="paymentBtn" class="w-full bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white py-2.5 rounded-sm font-bold text-xs uppercase tracking-wider transition shadow-md cursor-pointer flex items-center justify-center gap-2">
                        <i class="iclass fa-solid fa-paper-plane"></i> Send Disbursement
                    </button>
                </form>
                <div id="paymentStatus" class="text-center text-xs font-medium pt-1"></div>
            </div>
        </div>

    </div>
</main>

<?php 
$footer_path =$_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; 
if (file_exists($footer_path)) include$footer_path;
?>

<script>
function switchTab(tab) {
    const topupSec = document.getElementById('topupSection');
    const paymentSec = document.getElementById('paymentSection');
    const topupBtn = document.getElementById('tabTopupBtn');
    const paymentBtn = document.getElementById('tabPaymentBtn');
    const formTitle = document.getElementById('formTitle');

    if (tab === 'topup') {
        topupSec.classList.remove('hidden');
        paymentSec.classList.add('hidden');
        topupBtn.className = "px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer bg-teal-600 text-white shadow-md";
        paymentBtn.className = "px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer text-slate-500 hover:text-slate-900";
        formTitle.innerHTML = '<i class="iclass fa-solid fa-wallet text-teal-400"></i> Top Up Wallet';
    } else {
        paymentSec.classList.remove('hidden');
        topupSec.classList.add('hidden');
        paymentBtn.className = "px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer bg-teal-600 text-white shadow-md";
        topupBtn.className = "px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer text-slate-500 hover:text-slate-900";
        formTitle.innerHTML = '<i class="iclass fa-solid fa-money-bill-transfer text-teal-400"></i> Make Payout / Payment';
    }
}

// Top Up Form Handler with Automatic Polling
document.getElementById('topupForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('topupBtn');
    const statusDiv = document.getElementById('topupStatus');

    btn.disabled = true;
    btn.innerText = "PROCESSING...";
    statusDiv.className = "mt-3 text-center text-teal-600 text-xs";
    statusDiv.innerHTML = '<span class="spinner"></span>Initiating STK Push...';

    fetch('/b2c/topup/stk_push.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json())
    .then(d => {
        if (d.status === 'success' && d.checkout_id) {
            statusDiv.innerHTML = '<span class="spinner"></span>STK push sent. Enter your M-Pesa PIN on your phone...';
            
            const pollInterval = setInterval(() => {
                fetch(`/b2c/topup/check_status.php?checkout_id=${d.checkout_id}`)
                .then(res => res.json())
                .then(statusData => {
                    if (statusData.success || statusData.status === 'completed') {
                        clearInterval(pollInterval);
                        statusDiv.className = "mt-3 text-center text-emerald-600 text-xs font-bold";
                        statusDiv.innerText = "Top-up successful! Wallet credited.";
                        btn.disabled = false;
                        btn.innerHTML = '<i class="iclass fa-solid fa-cloud-arrow-down"></i> Initiate Top Up';
                        setTimeout(() => { location.reload(); }, 2000);
                    } else if (statusData.status === 'failed') {
                        clearInterval(pollInterval);
                        statusDiv.className = "mt-3 text-center text-amber-600 text-xs";
                        statusDiv.innerText = "Top-up failed or cancelled by user.";
                        btn.disabled = false;
                        btn.innerHTML = '<i class="iclass fa-solid fa-cloud-arrow-down"></i> Retry Top Up';
                    }
                }).catch(() => {});
            }, 3000);

        } else {
            statusDiv.className = "mt-3 text-center text-amber-600 text-xs";
            statusDiv.innerText = d.message || "Failed to initiate top up.";
            btn.disabled = false;
            btn.innerHTML = '<i class="iclass fa-solid fa-cloud-arrow-down"></i> Retry Top Up';
        }
    })
    .catch(() => {
        statusDiv.className = "mt-3 text-center text-amber-600 text-xs";
        statusDiv.innerText = "Network error. Please try again.";
        btn.disabled = false;
        btn.innerHTML = '<i class="iclass fa-solid fa-cloud-arrow-down"></i> Retry Top Up';
    });
});

// Make Payment / Withdrawal Form Handler pointing to payout folder
document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('paymentBtn');
    const statusDiv = document.getElementById('paymentStatus');

    btn.disabled = true;
    btn.innerText = "PROCESSING...";
    statusDiv.className = "mt-3 text-center text-emerald-600 text-xs";
    statusDiv.innerHTML = '<span class="spinner"></span>Processing payout request...';

    const __send = (fd) => fetch('/b2c/payout/withdraw.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
        if (d.status === 'otp_required') {
            const code = window.prompt(d.message || 'Enter the 6-digit code we emailed you');
            if (code) { fd.set('otp', code.trim()); return __send(fd); }
            return { status: 'error', message: 'Withdrawal cancelled: verification code required.' };
        }
        return d;
    });
    __send(new FormData(this))
    .then(d => {
        if (d.status === 'success') {
            statusDiv.className = "mt-3 text-center text-emerald-600 text-xs font-bold";
            statusDiv.innerText = d.message || "Disbursement sent successfully!";
            btn.disabled = false;
            btn.innerHTML = '<i class="iclass fa-solid fa-paper-plane"></i> Send Disbursement';
            this.reset();
            setTimeout(() => { location.reload(); }, 2000);
        } else {
            statusDiv.className = "mt-3 text-center text-amber-600 text-xs font-medium";
            statusDiv.innerText = d.message || "Disbursement failed.";
            btn.disabled = false;
            btn.innerHTML = '<i class="iclass fa-solid fa-paper-plane"></i> Retry Disbursement';
        }
    })
    .catch(() => {
        statusDiv.className = "mt-3 text-center text-amber-600 text-xs";
        statusDiv.innerText = "Network error. Please try again.";
        btn.disabled = false;
        btn.innerHTML = '<i class="iclass fa-solid fa-paper-plane"></i> Retry Disbursement';
    });
});
</script>

</body>
</html>