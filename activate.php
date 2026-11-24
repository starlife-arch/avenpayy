<?php
session_start();
// Default user_id to 1 if not set in session for standalone development/testing
if (empty($_SESSION['user_id'])) { header('Location: /login'); exit(); }
$user_id = (int) $_SESSION['user_id'];
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
            
            <!-- One subscription price for every merchant -->
            <div class="grid grid-cols-2 gap-2">
                <?php foreach(['monthly'=>200, '3 months'=>570, 'yearly'=>2040, 'custom_months'=>0, 'custom_days'=>0] as $plan => $price): ?>
                <label class="cursor-pointer group">
                    <input type="radio" name="plan" value="<?= $plan ?>" class="peer sr-only" <?= $plan === 'monthly' ? 'checked' : '' ?> onchange="toggleCustom('<?= $plan ?>', <?= $price ?>)">
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded group-hover:border-slate-300 peer-checked:border-teal-500 peer-checked:bg-teal-50 transition-all text-center">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-600 peer-checked:text-teal-700"><?= $plan === '3 months' ? 'Quarterly' : ($plan === 'custom_months' ? 'Custom Months' : ($plan === 'custom_days' ? 'Custom Days' : ucfirst($plan))) ?></div>
                        <div class="text-xs font-bold text-slate-800 mt-1"><?= $plan === 'custom_months' || $plan === 'custom_days' ? 'Choose duration' : 'KES '.number_format($price) ?></div>
                    </div>
                </label>
                <?php endforeach; ?>
            </div>

            <div id="customDurationDiv" class="hidden space-y-2">
                <label id="customDurationLabel" class="block text-[11px] font-medium text-slate-600">Choose duration</label>
                <input type="number" id="custom_duration" min="1" max="3650" placeholder="Enter duration" class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded text-sm text-slate-800 outline-none focus:border-teal-500 transition" oninput="calculateCustom()">
                <input type="hidden" name="custom_months" id="custom_months">
                <input type="hidden" name="custom_days" id="custom_days">
                <div id="customSummary" class="text-right text-[11px] font-medium text-teal-700"></div>
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
function discountForMonths(months) {
    if (months >= 12) return 0.15;
    if (months >= 6) return 0.10;
    if (months >= 3) return 0.05;
    return 0;
}
function toggleCustom(plan, price) {
    const custom = plan === 'custom_months' || plan === 'custom_days';
    document.getElementById('customDurationDiv').style.display = custom ? 'block' : 'none';
    document.getElementById('customDurationLabel').textContent = plan === 'custom_days' ? 'Number of days' : 'Number of months';
    document.getElementById('custom_duration').value = '';
    document.getElementById('custom_months').value = '';
    document.getElementById('custom_days').value = '';
    document.getElementById('customSummary').textContent = '';
    document.getElementById('displayAmount').textContent = Number(price).toLocaleString();
}
function calculateCustom() {
    const plan = document.querySelector('input[name="plan"]:checked').value;
    const n = Math.max(0, parseInt(document.getElementById('custom_duration').value, 10) || 0);
    let amount = 0, months = 0, days = 0;
    if (plan === 'custom_months' && n > 0 && n <= 120) {
        months = n; amount = Math.round(200 * n * (1 - discountForMonths(n)));
        document.getElementById('custom_months').value = n;
    } else if (plan === 'custom_days' && n > 0 && n <= 3650) {
        days = n; amount = Math.max(1, Math.round((200 * n / 30) * (1 - discountForMonths(n / 30))));
        document.getElementById('custom_days').value = n;
    }
    document.getElementById('customSummary').textContent = amount ? `Total: KES ${amount.toLocaleString()} • ${n} ${plan === 'custom_days' ? 'days' : 'months'}` : 'Enter a valid duration (max 120 months or 3,650 days).';
    document.getElementById('displayAmount').textContent = amount.toLocaleString();
}

document.getElementById('paymentForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('payBtn');
    const statusDiv = document.getElementById('status');
    
    const plan = document.querySelector('input[name="plan"]:checked').value;
    const months = document.getElementById('custom_months').value;
    const days = document.getElementById('custom_days').value;
    const duration = document.getElementById('custom_duration').value;
    
    if ((plan === 'custom_months' && (!months || parseInt(months) < 1 || parseInt(months) > 120)) || (plan === 'custom_days' && (!days || parseInt(days) < 1 || parseInt(days) > 3650))) {
        statusDiv.className = "mt-3 text-center text-rose-600 text-xs";
        statusDiv.innerText = "Please specify a valid custom duration.";
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