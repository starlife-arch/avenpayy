<?php
// index.php
session_start();
require_once 'database_config/db.php';

// Access control: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];
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
    <title>AvenPay Checkout</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .animate-spin-hover:hover {
            animation: spin-slow 3s linear infinite;
        }
        .custom-scrollbar::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f8fafc;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 2px;
        }

        /* Prevent white background/flash on browser autofill for dark inputs */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active,
        select:-webkit-autofill,
        select:-webkit-autofill:hover,
        select:-webkit-autofill:focus,
        select:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #ffffff inset !important;
            -webkit-text-fill-color: #0f172a !important;
            caret-color: #0f172a !important;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php'; ?>

<main class="flex-1 flex items-center justify-center p-4 md:p-8">
    <div class="max-w-md w-full space-y-6">
        
        <div class="bg-white border border-slate-200 rounded-sm p-6 md:p-8 shadow-2xl relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-teal-500 via-teal-500 to-emerald-500"></div>

            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-slate-200">
                <div class="w-10 h-10 rounded-sm bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-600">
                    <i class="iclass fa-solid fa-shield-halved text-lg"></i>
                </div>
                <div>
                    <h1 class="text-lg font-extrabold text-slate-900 tracking-tight">Secure Gateway</h1>
                    <p class="text-xs text-slate-500">Instantaneous M-Pesa STK Push Checkout</p>
                </div>
            </div>

            <form id="payForm" class="space-y-4">
                <div>
                    <label for="x_api_key" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Settlement Gateway Account</label>
                    <select name="x_api_key" id="x_api_key" required class="w-full bg-slate-50 border border-slate-200 rounded-sm px-3.5 py-3 text-sm text-slate-700 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition">
                        <option value="" disabled selected>-- Choose Settlement Account --</option>
                        <?php
                        $stmt = $conn->prepare("SELECT x_api_key, merchant_name, type, is_active FROM settlements WHERE user_id = ?");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $res = $stmt->get_result();

                        if ($res && $res->num_rows > 0) {
                            while($row = $res->fetch_assoc()) {
                                $statusLabel = $row['is_active'] ? "" : " [INACTIVE]";
                                echo "<option value='" . htmlspecialchars($row['x_api_key']) . "'>" 
                                     . htmlspecialchars($row['merchant_name']) . " (" . strtoupper($row['type']) . ")" 
                                     . $statusLabel . "</option>";
                            }
                        } else {
                            echo "<option value='' disabled>No accounts available</option>";
                        }
                        ?>
                    </select>
                </div>

                <div>
                    <label for="phone_number" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Customer Phone Number</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 text-sm">
                            <i class="iclass fa-solid fa-phone"></i>
                        </span>
                        <input type="text" id="phone_number" name="phone_number" placeholder="e.g., 0712345678" required class="w-full bg-slate-50 border border-slate-200 rounded-sm pl-10 pr-3.5 py-3 text-sm text-slate-700 placeholder-slate-600 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition">
                    </div>
                </div>

                <div>
                    <label for="amount" class="block text-xs font-bold text-slate-600 uppercase tracking-wider mb-1.5">Amount (KES)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 text-sm">
                            <i class="iclass fa-solid fa-coins"></i>
                        </span>
                        <input type="number" id="amount" name="amount" value="1" min="1" required class="w-full bg-slate-50 border border-slate-200 rounded-sm pl-10 pr-3.5 py-3 text-sm text-slate-700 placeholder-slate-600 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition">
                    </div>
                </div>

                <button type="submit" id="btn" class="w-full mt-2 bg-gradient-to-r from-teal-500 to-teal-500 hover:from-teal-400 hover:to-teal-400 text-slate-950 font-extrabold py-3.5 px-4 rounded-sm transition cursor-pointer flex items-center justify-center gap-2 shadow-lg shadow-teal-500/10 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="iclass fa-solid fa-credit-card text-slate-950"></i> Pay Now
                </button>
            </form>
        </div>

        <div id="statusCard" class="bg-zinc-900 border border-zinc-800 rounded-sm p-6 shadow-2xl hidden transition-all duration-300">
            <div class="flex items-start gap-4">
                <div id="statusIconWrap" class="w-12 h-12 rounded-sm flex items-center justify-center flex-shrink-0 text-xl bg-zinc-800 text-zinc-300">
                    <i class="iclass fa-solid fa-spinner fa-spin" id="statusIcon"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 id="statusTitle" class="text-sm font-bold text-slate-900 mb-1">Processing</h3>
                    <p id="statusBody" class="text-xs text-zinc-400 leading-relaxed">Initializing connection profiles...</p>
                    
                    <div id="statusMeta" class="mt-4 pt-3 border-t border-zinc-800 text-[11px] font-mono text-zinc-400 space-y-1.5" style="display: none;">
                        <div class="flex justify-between gap-2">
                            <span>Ref: <strong id="metaRef" class="text-zinc-200">-</strong></span>
                        </div>
                        <div class="flex justify-between gap-2 border-t border-zinc-800/60 pt-1.5">
                            <span>Amount: <strong id="metaAmount" class="text-emerald-600">-</strong></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>

<script>
let pollInterval = null;
let globalCheckoutID = null;

const payForm = document.getElementById('payForm');
const payBtn = document.getElementById('btn');
const statusCard = document.getElementById('statusCard');
const statusIconWrap = document.getElementById('statusIconWrap');
const statusIcon = document.getElementById('statusIcon');
const statusTitle = document.getElementById('statusTitle');
const statusBody = document.getElementById('statusBody');
const statusMeta = document.getElementById('statusMeta');
const metaRef = document.getElementById('metaRef');
const metaAmount = document.getElementById('metaAmount');

function updateStatusCard(state, title, description, meta = null) {
    statusCard.style.display = "block";
    
    // Reset wrapper classes with near-sharp edges (rounded-sm)
    statusIconWrap.className = "w-12 h-12 rounded-sm flex items-center justify-center flex-shrink-0 text-xl";
    statusCard.className = "bg-zinc-900 border rounded-sm p-6 shadow-2xl transition-all duration-300";

    if (state === 'processing') {
        statusCard.classList.add('border-teal-500/40');
        statusIconWrap.classList.add('bg-teal-500/10', 'text-teal-600', 'border', 'border-teal-500/30');
        statusIcon.className = "iclass fa-solid fa-circle-notch fa-spin";
    } else if (state === 'pin') {
        statusCard.classList.add('border-amber-500/40');
        statusIconWrap.classList.add('bg-amber-500/10', 'text-amber-600', 'border', 'border-amber-500/30');
        statusIcon.className = "iclass fa-solid fa-mobile-screen-button fa-bounce";
    } else if (state === 'success') {
        statusCard.classList.add('border-emerald-500/40');
        statusIconWrap.classList.add('bg-emerald-500/10', 'text-emerald-600', 'border', 'border-emerald-500/30');
        statusIcon.className = "iclass fa-solid fa-circle-check";
    } else if (state === 'failed') {
        statusCard.classList.add('border-rose-500/40');
        statusIconWrap.classList.add('bg-rose-500/10', 'text-rose-600', 'border', 'border-rose-500/30');
        statusIcon.className = "iclass fa-solid fa-circle-xmark";
    }
    
    statusTitle.innerText = title;
    statusBody.innerHTML = description;

    if (meta) {
        statusMeta.style.display = "block";
        metaRef.innerText = meta.ref || '-';
        metaAmount.innerText = meta.amount ? `KES ${meta.amount}` : '-';
    } else {
        statusMeta.style.display = "none";
    }
}

function checkCurrentPaymentStatus(checkoutId) {
    fetch('stk_push.php?check_status=' + encodeURIComponent(checkoutId))
    .then(r => r.json())
    .then(s => {
        if (s && s.status && s.status !== 'PENDING') {
            finalizeTransaction(s.status, s);
        }
    })
    .catch(err => console.log("Waiting for stable pipeline sync..."));
}

function finalizeTransaction(status, data) {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
    payBtn.disabled = false;
    
    const inputAmount = document.getElementById('amount').value;
    const paidAmount = data.amount || inputAmount;

    if (status === 'SUCCESS') {
        updateStatusCard('success', 'Payment Completed!', `Transaction verified successfully.`, {
            ref: data.mpesa_receipt || 'CONFIRMED',
            amount: paidAmount
        });
    } else {
        updateStatusCard('failed', 'Transaction Failed', data.mpesa_code || data.message || 'The transaction was cancelled or timed out.', {
            ref: 'DECLINED',
            amount: paidAmount
        });
    }
}

payForm.addEventListener('submit', function(e) {
    e.preventDefault();
    payBtn.disabled = true;
    
    updateStatusCard('processing', 'Sending Prompt', 'Securing channels and formatting billing metrics...');
    
    fetch('stk_push.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json())
    .then(d => {
        if(d.status === 'success') {
            globalCheckoutID = d.checkout_id;
            
            updateStatusCard('pin', 'PIN Prompt Active', 'Please look at your handset screen and authenticate with your secret M-Pesa PIN.');
            
            if(pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(() => {
                if (navigator.onLine && globalCheckoutID) {
                    checkCurrentPaymentStatus(globalCheckoutID);
                }
            }, 3000);
            
        } else {
            payBtn.disabled = false;
            updateStatusCard('failed', 'Request Rejected', d.message);
        }
    })
    .catch(err => {
        payBtn.disabled = false;
        updateStatusCard('failed', 'System Error', 'Failed to connect to the internal billing gateway.');
    });
});

window.addEventListener('online', () => {
    if (globalCheckoutID && !pollInterval) {
        pollInterval = setInterval(() => {
            if (navigator.onLine) checkCurrentPaymentStatus(globalCheckoutID);
        }, 3000);
    }
});
</script>
</body>
</html>