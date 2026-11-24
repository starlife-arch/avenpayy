<?php
// b2c/index.php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$b2c_wallet_balance = 0;
$on_hold_amount = 0;
$total_withdrawn = 0;
$total_topped = 0;
$user_wallet_id = '';

// 1. Fetch user B2C wallet balance, on-hold amount, and wallet_id from users table
$stmt = $conn->prepare("SELECT b2c_wallet_balance, on_hold_amount, wallet_id FROM users WHERE id = ?");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    if ($res) {
        $b2c_wallet_balance = floatval($res['b2c_wallet_balance'] ?? 0);
        $on_hold_amount = floatval($res['on_hold_amount'] ?? 0);
        $user_wallet_id = $res['wallet_id'] ?? '';
    }
    $stmt->close();
}

// 2. Fetch Total Paid Out from mpesa_b2c_transactions (result_code = 0 indicates success)
if (!empty($user_wallet_id)) {
    $payoutStmt = $conn->prepare("
        SELECT SUM(transaction_amount) as total_out 
        FROM mpesa_b2c_transactions 
        WHERE wallet_id = ? AND result_code = 0
    ");
    if ($payoutStmt) {
        $payoutStmt->bind_param("s", $user_wallet_id);
        $payoutStmt->execute();
        $payoutRes = $payoutStmt->get_result()->fetch_assoc();
        if ($payoutRes && isset($payoutRes['total_out'])) {
            $total_withdrawn = $payoutRes['total_out'];
        }
        $payoutStmt->close();
    }
}

// 3. Fetch Total Topped In from b2c_topup_transactions
$topupStmt = $conn->prepare("
    SELECT SUM(amount) as total_in 
    FROM b2c_topup_transactions 
    WHERE user_id = ? AND status = 'completed'
");
if ($topupStmt) {
    $topupStmt->bind_param("i", $user_id);
    $topupStmt->execute();
    $topupRes = $topupStmt->get_result()->fetch_assoc();
    if ($topupRes && isset($topupRes['total_in'])) {
        $total_topped = $topupRes['total_in'];
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
$header_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';
if (file_exists($header_path)) include $header_path;
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

    <!-- 3-Card Metrics Grid (Displaying B2C Wallet Balance directly) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white border border-slate-200 p-4 rounded-sm shadow-md">
            <div class="flex items-center justify-between text-slate-500 mb-1">
                <span class="text-[10px] uppercase font-bold tracking-wider">B2C Wallet Balance</span>
                <i class="iclass fa-solid fa-wallet text-emerald-600 text-xs"></i>
            </div>
            <div class="text-base font-mono font-black text-emerald-600">
                KES <?= number_format($b2c_wallet_balance, 0) ?>
            </div>
            <div class="text-[10px] text-slate-500 mt-0.5">On-hold: KES <?= number_format($on_hold_amount, 0) ?></div>
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
        
        <!-- Tab Switcher Header -->
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
                <button type="button" id="tabBillBtn" onclick="switchTab('bill')" class="px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer text-slate-500 hover:text-slate-900">
                    Pay Bill / Till
                </button>
            </div>
        </div>

        <div class="p-5">
            <!-- TAB 1: TOP UP FORM -->
            <div id="topupSection" class="space-y-4">
                <form id="topupForm" class="space-y-4" autocomplete="off">
                    <input type="text" name="fake_username" style="display:none !important;" tabindex="-1">
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

            <!-- TAB 2: MAKE PAYMENTS FORM -->
            <div id="paymentSection" class="space-y-4 hidden">
                <form id="paymentForm" class="space-y-4" autocomplete="off">
                    <input type="text" name="fake_username" style="display:none !important;" tabindex="-1">
                    <input type="password" name="fake_password" style="display:none !important;" tabindex="-1">

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
                        <input type="text" id="recipientPhone" name="phone" placeholder="07XXXXXXXX / 01XXXXXXXX" required autocomplete="off" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                        <!-- Dynamic Receiver Name Feedback Badge -->
                        <div id="receiverFeedback" class="mt-1.5 text-[11px] font-bold text-emerald-600 hidden"></div>
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Payout Amount (KES)</label>
                        <input type="number" name="amount" min="10" placeholder="e.g. 1000" required autocomplete="off" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>

                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Withdrawal PIN</label>
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

            <!-- TAB 3: PAY BILL / TILL (B2B) -->
            <div id="billSection" class="space-y-4 hidden">
                <form id="billForm" class="space-y-4" autocomplete="off">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Pay To</label>
                        <select name="command_id" id="billType" onchange="toggleBillAccount()" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700 cursor-pointer">
                            <option value="BusinessPayBill">Paybill (needs account number)</option>
                            <option value="BusinessBuyGoods">Till Number (Buy Goods)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Paybill / Till Number</label>
                        <input type="text" name="party_b" inputmode="numeric" maxlength="7" placeholder="e.g. 247247" required autocomplete="off" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>
                    <div id="billAccountWrap">
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Account Number / Reference</label>
                        <input type="text" name="account_reference" maxlength="20" placeholder="e.g. invoice or account no." autocomplete="off" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Amount (KES)</label>
                        <input type="number" name="amount" min="10" placeholder="e.g. 2500" required autocomplete="off" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Withdrawal PIN</label>
                        <input type="password" name="pin" placeholder="Enter your 4-digit security PIN" required maxlength="4" autocomplete="one-time-code" inputmode="numeric" data-lpignore="true" readonly onfocus="this.removeAttribute('readonly');" class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-700">
                    </div>
                    <button type="submit" id="billBtn" class="w-full bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white py-2.5 rounded-sm font-bold text-xs uppercase tracking-wider transition flex items-center justify-center gap-2 cursor-pointer">
                        <i class="iclass fa-solid fa-building-columns"></i> Pay Bill / Till
                    </button>
                </form>
                <div id="billStatus" class="text-center text-xs font-medium pt-1"></div>
            </div>
        </div>

    </div>
</main>

<?php 
$footer_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; 
if (file_exists($footer_path)) include $footer_path;
?>

<script>
function switchTab(tab) {
    const tabs = {
        topup:   { sec: 'topupSection',   btn: 'tabTopupBtn',   title: '<i class="iclass fa-solid fa-wallet text-teal-400"></i> Top Up Wallet' },
        payment: { sec: 'paymentSection', btn: 'tabPaymentBtn', title: '<i class="iclass fa-solid fa-money-bill-transfer text-teal-400"></i> Make Payout / Payment' },
        bill:    { sec: 'billSection',    btn: 'tabBillBtn',    title: '<i class="iclass fa-solid fa-building-columns text-teal-400"></i> Pay Bill / Till' }
    };
    Object.keys(tabs).forEach(function (k) {
        const on = (k === tab);
        document.getElementById(tabs[k].sec).classList.toggle('hidden', !on);
        document.getElementById(tabs[k].btn).className = "px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer " +
            (on ? "bg-teal-600 text-white shadow-md" : "text-slate-500 hover:text-slate-900");
    });
    document.getElementById('formTitle').innerHTML = tabs[tab].title;
}

function toggleBillAccount() {
    const isPaybill = document.getElementById('billType').value === 'BusinessPayBill';
    document.getElementById('billAccountWrap').classList.toggle('hidden', !isPaybill);
}

// Pay Bill / Till form handler (B2B)
document.getElementById('billForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('billBtn');
    const statusDiv = document.getElementById('billStatus');
    const form = this;
    btn.disabled = true;
    statusDiv.className = "mt-3 text-center text-emerald-600 text-xs";
    statusDiv.innerText = "Processing payment request...";

    const send = (fd) => fetch('/b2c/payout/b2b_pay.php', { method: 'POST', body: fd }).then(r => r.json()).then(d => {
        if (d.status === 'otp_required') {
            const code = window.prompt(d.message || 'Enter the 6-digit code we emailed you');
            if (code) { fd.set('otp', code.trim()); return send(fd); }
            return { status: 'error', message: 'Payment cancelled: verification code required.' };
        }
        return d;
    });

    send(new FormData(form)).then(d => {
        btn.disabled = false;
        if (d.status === 'success') {
            statusDiv.className = "mt-3 text-center text-emerald-600 text-xs font-bold";
            statusDiv.innerText = d.message || "Payment initiated successfully!";
            form.reset();
            setTimeout(() => { location.reload(); }, 2500);
        } else {
            statusDiv.className = "mt-3 text-center text-amber-600 text-xs font-medium";
            statusDiv.innerText = d.message || "Payment failed.";
        }
    }).catch(() => {
        btn.disabled = false;
        statusDiv.className = "mt-3 text-center text-amber-600 text-xs";
        statusDiv.innerText = "Network error. Please try again.";
    });
});

// Live Receiver Name Lookup
let lookupTimeout;
document.getElementById('recipientPhone').addEventListener('input', function() {
    const phone = this.value.trim();
    const feedback = document.getElementById('receiverFeedback');
    
    clearTimeout(lookupTimeout);
    
    if (phone.length < 9) {
        feedback.classList.add('hidden');
        feedback.innerHTML = '';
        return;
    }

    lookupTimeout = setTimeout(() => {
        fetch(`/b2c/payout/lookup_receiver.php?phone=${encodeURIComponent(phone)}`)
        .then(res => res.json())
        .then(data => {
            if (data.status === 'found') {
                feedback.classList.remove('hidden');
                feedback.innerText = `Receiver Name: ${data.receiver_name}`;
            } else {
                feedback.classList.add('hidden');
                feedback.innerText = '';
            }
        })
        .catch(() => {
            feedback.classList.add('hidden');
            feedback.innerText = '';
        });
    }, 300);
});

// Top Up Form Handler
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

// Make Payment / Withdrawal Form Handler
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
            document.getElementById('receiverFeedback').classList.add('hidden');
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