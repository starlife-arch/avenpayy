<?php
session_start();
// Default user_id to 1 if not set in session for standalone development/testing
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$query = $conn->prepare("SELECT * FROM settlements WHERE id = ? AND user_id = ?");
$query->bind_param("ii", $id, $user_id);
$query->execute();
$s = $query->get_result()->fetch_assoc();
$query->close();

if (!$s) { header("Location: /dashboard.php"); exit(); }

$now = time();
$is_currently_active = ((int)$s['is_active'] === 1 && !empty($s['expiry_date']) && strtotime($s['expiry_date']) > $now);

include $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';
?>

<style>
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus, 
    input:-webkit-autofill:active {
        -webkit-transition: background-color 9999s ease-in-out 0s;
        transition: background-color 9999s ease-in-out 0s;
        -webkit-text-fill-color: #0f172a !important;
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
        border-left-color: #3df3c6;
        animation: spin 0.8s linear infinite;
        display: inline-block;
        vertical-align: middle;
        margin-right: 8px;
    }
</style>

<main class="max-w-md mx-auto mt-16 px-4 pb-24">
    <div class="bg-white border border-slate-200/80 p-6 shadow-xl rounded-lg">
        
        <!-- Header Info -->
        <div class="flex items-center justify-between pb-4 mb-5 border-b border-slate-200">
            <div>
                <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 block mb-0.5">MERCHANT ACC NAME</span>
                <h1 class="text-lg font-bold text-slate-800 tracking-tight"><?= htmlspecialchars($s['merchant_name']) ?></h1>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center px-2.5 py-1 rounded text-[11px] font-medium <?= $is_currently_active ? 'bg-emerald-500/10 text-emerald-600 border border-emerald-500/20' : 'bg-teal-500/10 text-teal-600 border border-teal-500/20' ?>">
                    <span class="w-1.5 h-1.5 rounded-full mr-1.5 <?= $is_currently_active ? 'bg-emerald-400' : 'bg-teal-400' ?>"></span>
                    <?= $is_currently_active ? 'API Renewal Plan' : 'API Subscription Plan' ?>
                </span>
            </div>
        </div>

        <?php if ($is_currently_active): ?>
        <div class="mb-5 bg-slate-50/60 border border-slate-200/60 rounded p-3 flex items-center justify-between text-xs">
            <span class="text-slate-500">Current Expiry</span>
            <span class="font-medium text-slate-700"><?= htmlspecialchars($s['expiry_date']) ?></span>
        </div>
        <?php endif; ?>

        <form id="paymentForm" class="space-y-4">
            <input type="hidden" name="id" value="<?= $id ?>">
            
            <!-- Plan Grid -->
            <div class="grid grid-cols-2 gap-2">
                <?php foreach(['monthly'=>200, 'quarterly'=>570, 'yearly'=>2040, 'custom_months'=>0, 'custom_days'=>0] as $plan => $price): ?>
                <label class="cursor-pointer group">
                    <input type="radio" name="plan" value="<?= $plan ?>" class="peer sr-only" <?= $plan === 'monthly' ? 'checked' : '' ?> onchange="toggleCustom('<?= $plan ?>', <?= $price ?>)">
                    <div class="p-3 bg-slate-50/70 border border-slate-200 rounded-lg group-hover:border-slate-300 peer-checked:border-teal-500 peer-checked:bg-teal-50 transition-all text-center h-full">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-600 group-hover:text-slate-700 peer-checked:text-teal-700"><?= $plan === 'custom_months' ? 'Custom Months' : ($plan === 'custom_days' ? 'Custom Days' : ucfirst($plan)) ?></div>
                        <div class="text-xs font-bold text-slate-800 mt-1"><?= $plan === 'custom_months' ? 'Choose months' : ($plan === 'custom_days' ? 'Choose days' : 'KES '.number_format($price)) ?></div>
                        <?php if ($plan === 'quarterly'): ?><div class="text-[10px] text-emerald-700 mt-1">5% off</div><?php elseif ($plan === 'yearly'): ?><div class="text-[10px] text-emerald-700 mt-1">Save KES 360 · 15% off</div><?php endif; ?>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <!-- Custom subscription duration -->
            <div id="customMonthsDiv" class="hidden space-y-1">
                <input type="number" name="custom_months" id="custom_months" placeholder="Number of months" min="1" max="120" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded text-sm text-slate-700 outline-none focus:border-teal-500 transition" oninput="calculateCustom()">
                <div class="text-[10px] text-slate-500">Discounts: 5% for 3–5 months, 10% for 6–11 months, 15% for 12+ months.</div>
                <div id="customSummary" class="hidden text-right text-[11px] font-medium text-teal-700 px-1"></div>
            </div>
            <div id="customDaysDiv" class="hidden space-y-1">
                <input type="number" name="custom_days" id="custom_days" placeholder="Number of days" min="1" max="3650" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded text-sm text-slate-700 outline-none focus:border-teal-500 transition" oninput="calculateCustom()">
                <div class="text-[10px] text-slate-500">KES 7 per day. Your API access expires after the selected number of days.</div>
                <div id="customDaysSummary" class="hidden text-right text-[11px] font-medium text-teal-700 px-1"></div>
            </div>

            <!-- Phone Number Input -->
            <div>
                <label class="block text-[11px] font-medium text-slate-500 uppercase tracking-wider mb-1">M-Pesa Number</label>
                <input type="text" name="phone" placeholder="07XXXXXXXX / 01XXXXXXXX" required class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded text-sm text-slate-700 outline-none focus:border-teal-500 transition">
            </div>

            <!-- Submit Button -->
            <button type="submit" id="payBtn" class="w-full bg-teal-600 hover:bg-teal-500 active:bg-teal-700 text-white py-2.5 rounded font-semibold text-xs uppercase tracking-wider transition shadow-sm">
                Pay KES <span id="displayAmount">200</span>
            </button>
        </form>

        <div id="status" class="mt-4 text-center text-xs font-medium"></div>
    </div>
</main>

<script>
function calculateMonthPrice(months) {
    const discount = months >= 12 ? 0.15 : (months >= 6 ? 0.10 : (months >= 3 ? 0.05 : 0));
    return Math.round(months * 200 * (1 - discount));
}

function toggleCustom(plan, price) {
    const isCustomMonths = plan === 'custom_months';
    const isCustomDays = plan === 'custom_days';
    document.getElementById('customMonthsDiv').style.display = isCustomMonths ? 'block' : 'none';
    document.getElementById('customDaysDiv').style.display = isCustomDays ? 'block' : 'none';

    if (!isCustomMonths) {
        document.getElementById('custom_months').value = '';
        document.getElementById('customSummary').style.display = 'none';
    }
    if (!isCustomDays) {
        document.getElementById('custom_days').value = '';
        document.getElementById('customDaysSummary').style.display = 'none';
    }
    document.getElementById('displayAmount').innerText = (isCustomMonths || isCustomDays ? 0 : price).toLocaleString();
}

function calculateCustom() {
    const months = parseInt(document.getElementById('custom_months').value, 10) || 0;
    const days = parseInt(document.getElementById('custom_days').value, 10) || 0;
    const monthSummary = document.getElementById('customSummary');
    const daySummary = document.getElementById('customDaysSummary');
    if (months > 0 && months <= 120) {
        const amount = calculateMonthPrice(months);
        monthSummary.innerText = 'Total: KES ' + amount.toLocaleString();
        monthSummary.style.display = 'block';
        document.getElementById('displayAmount').innerText = amount.toLocaleString();
    } else {
        monthSummary.style.display = 'none';
    }
    if (days > 0 && days <= 3650) {
        const amount = days * 7;
        daySummary.innerText = 'Total: KES ' + amount.toLocaleString();
        daySummary.style.display = 'block';
        document.getElementById('displayAmount').innerText = amount.toLocaleString();
    } else {
        daySummary.style.display = 'none';
    }
}

document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('payBtn');
    const statusDiv = document.getElementById('status');
    
    const plan = document.querySelector('input[name="plan"]:checked').value;
    const months = document.getElementById('custom_months').value;
    const days = document.getElementById('custom_days').value;

    if (plan === 'custom_months' && (!Number.isInteger(Number(months)) || Number(months) < 1 || Number(months) > 120)) {
        statusDiv.className = "mt-3 text-center text-rose-600 text-xs";
        statusDiv.innerText = "Choose a duration between 1 and 120 months.";
        return;
    }
    if (plan === 'custom_days' && (!Number.isInteger(Number(days)) || Number(days) < 1 || Number(days) > 3650)) {
        statusDiv.className = "mt-3 text-center text-rose-600 text-xs";
        statusDiv.innerText = "Choose a duration between 1 and 3650 days.";
        return;
    }

    btn.disabled = true; 
    btn.innerText = "PROCESSING...";
    statusDiv.className = "mt-3 text-center text-teal-600 text-xs";
    statusDiv.innerHTML = '<span class="spinner"></span>Initiating STK Push...';

    fetch('/api/subscription_stk.php', { method: 'POST', body: new FormData(this) })
    .then(async r => {
        const text = await r.text();
        try {
            return JSON.parse(text);
        } catch (err) {
            console.error("PHP Fatal Error / Non-JSON Response:", text);
            throw new Error("Server returned non-JSON response. Check F12 Console.");
        }
    })
    .then(d => {
        if(d.status === 'success' && d.checkout_id) {
            statusDiv.innerHTML = '<span class="spinner"></span>STK prompt sent. Check your phone...';
            pollStatus(d.checkout_id, <?= $id ?>);
        } else {
            statusDiv.className = "mt-3 text-center text-rose-600 text-xs";
            statusDiv.innerText = d.message || "Failed to initiate payment.";
            btn.disabled = false; 
            btn.innerText = "RETRY PAYMENT";
        }
    })
    .catch(err => {
        console.error("Fetch catch triggered:", err);
        statusDiv.className = "mt-3 text-center text-rose-600 text-xs";
        statusDiv.innerText = err.message || "Network error. Please try again.";
        btn.disabled = false; 
        btn.innerText = "RETRY PAYMENT";
    });
});

function pollStatus(cid, targetID) {
    const btn = document.getElementById('payBtn');
    const statusDiv = document.getElementById('status');
    let elapsedSeconds = 0;

    let interval = setInterval(() => {
        elapsedSeconds += 3;

        fetch('/api/subscription_status.php?check_status=' + encodeURIComponent(cid))
        .then(async r => {
            const text = await r.text();
            try {
                return JSON.parse(text);
            } catch (err) {
                console.error("Status check error:", text);
                return null; 
            }
        })
        .then(d => {
            if (!d) return; 

            console.log("Polling response:", d);
            const status = (d.status || '').toUpperCase();

            if (status === 'SUCCESS') {
                clearInterval(interval);
                statusDiv.className = "mt-3 text-center text-emerald-600 text-xs";
                statusDiv.innerText = "Payment confirmed! Redirecting...";
                setTimeout(() => window.location.href = '/settlement.php?id=' + targetID, 1500);
            } else if (status === 'FAILED' || status === 'CANCELLED') {
                clearInterval(interval);
                statusDiv.className = "mt-3 text-center text-rose-600 text-xs";
                statusDiv.innerText = d.mpesa_receipt || "Transaction failed or cancelled.";
                btn.disabled = false;
                btn.innerText = "RETRY PAYMENT";
            } else {
                // Escape hatch if pending for more than 15 seconds
                if (elapsedSeconds >= 15) {
                    statusDiv.innerHTML = `
                        <div class="text-amber-400 mb-2">Still waiting for PIN entry...</div>
                        <button type="button" onclick="location.reload()" class="bg-slate-800 hover:bg-slate-200 text-slate-700 px-3 py-1.5 rounded text-[11px] border border-slate-300 transition">
                            Stuck? Click here to Retry
                        </button>
                    `;
                } else {
                    if (!statusDiv.innerHTML.includes('spinner')) {
                        statusDiv.innerHTML = '<span class="spinner"></span>STK prompt sent. Check your phone...';
                    }
                }
            }
        })
        .catch(err => {
            console.error("Polling network error:", err);
        });
    }, 3000);
}
</script>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>