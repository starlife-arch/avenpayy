<?php
// api/sandbox.php
session_start();

$db_config_path = __DIR__ . '/../database_config/db.php';
if (is_file($db_config_path)) {
    require_once $db_config_path;
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    exit(json_encode(['status' => 'error', 'message' => 'AvenPay database configuration file is missing.']));
}

if (!isset($conn)) {
    if (isset($mysqli)) $conn = $mysqli;
    elseif (isset($pdo)) $conn = $pdo;
    elseif (isset($connection)) $conn = $connection;
}

// Handle AJAX verification or trigger actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json; charset=utf-8');
    $action = $_POST['action'] ?? '';

    // 1. Verify API Key against settlements table
    if ($action === 'verify_key') {
        if (!isset($conn) || !$conn) {
            echo json_encode(['status' => 'error', 'message' => 'Database connection not initialized.']);
            exit();
        }

        $x_api_key = trim($_POST['x_api_key'] ?? '');
        if (empty($x_api_key)) {
            $input = json_decode(file_get_contents('php://input'), true);
            if (!empty($input['x_api_key'])) {
                $x_api_key = trim($input['x_api_key']);
            }
        }

        if (empty($x_api_key)) {
            echo json_encode(['status' => 'error', 'message' => 'API key parameter was empty.']);
            exit();
        }

        $stmt = $conn->prepare("SELECT merchant_name, type, shortcode, account_number, is_active FROM settlements WHERE x_api_key = ? LIMIT 1");
        if (!$stmt) {
            echo json_encode(['status' => 'error', 'message' => 'SQL prepare failed: ' . $conn->error]);
            exit();
        }

        $stmt->bind_param("s", $x_api_key);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'merchant_name' => $row['merchant_name'],
                    'type' => $row['type'],
                    'shortcode' => $row['shortcode'],
                    'account_number' => $row['account_number'],
                    'is_active' => (int)$row['is_active']
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'API key not found in settlements table.']);
        }
        exit();
    }
    
    // 2. Send STK Push simulating POST https://avenpay.co.ke/api/stk_push
    if ($action === 'send_stk') {
        $x_api_key = trim($_POST['x_api_key'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $amount = trim($_POST['amount'] ?? 1);

        if (empty($x_api_key) || empty($phone_number)) {
            echo json_encode(['status' => 'error', 'message' => 'API key and phone number are required.']);
            exit();
        }

        $formatted_phone = preg_replace('/[^0-9]/', '', $phone_number);
        if (strpos($formatted_phone, '0') === 0) {
            $formatted_phone = '254' . substr($formatted_phone, 1);
        }

        // Delegate to stk_push.php endpoint logic
        $_POST['phone'] = $formatted_phone;
        $_POST['phone_number'] = $formatted_phone;
        $_POST['amount'] = $amount;
        $_POST['x_api_key'] = $x_api_key;

        $stk_push_file = __DIR__ . '/stk_push.php';
        if (file_exists($stk_push_file)) {
            ob_start();
            include $stk_push_file;
            $stk_response = ob_get_clean();
            echo $stk_response;
        } else {
            echo json_encode(['status' => 'error', 'message' => 'stk_push.php handler file not found in /api/ directory.']);
        }
        exit();
    }
    
    echo json_encode(['status' => 'error', 'message' => 'Invalid action specified.']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
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
    <title>STK Push Sandbox - AvenPay</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #ffffff;
            --card-bg: rgba(248, 250, 252, 0.95);
            --card-border: rgba(15, 23, 42, 0.08);
        }
        body { background-color: var(--bg-color); color: #0f172a; }
        input:-webkit-autofill {
            -webkit-box-shadow: 0 0 0 1000px #ffffff inset !important;
            -webkit-text-fill-color: #0f172a !important;
        }
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
                <i class="iclass fa-solid fa-code text-teal-600"></i>
                <span>C2B API Sandbox Gateway</span>
            </h1>
            <p class="text-[11px] text-slate-500 mt-0.5">Simulates the API checkout flow locally. It does not contact Safaricom or move real money.</p>
        </div>
        <div>
            <a href="/transactions" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-1.5 rounded-sm text-xs font-bold transition flex items-center gap-1.5 shadow-md">
                <i class="iclass fa-solid fa-receipt text-teal-600"></i> View History
            </a>
        </div>
    </div>

    <!-- Main Card Wrapper -->
    <div class="bg-white border border-slate-200 rounded-sm shadow-xl backdrop-blur-md overflow-hidden">
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between gap-3">
            <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-terminal text-teal-600"></i> POST /api/stk_push Simulator
            </h2>
            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-teal-500/10 text-teal-600 border border-teal-500/20">Internal Simulation</span>
        </div>

        <div class="p-5 space-y-4">
            <form id="sandboxForm" class="space-y-4" onsubmit="return false;" autocomplete="off">
                <div>
                    <label for="test_api_key" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Settlement API Key (x_api_key)</label>
                    <div class="relative">
                        <input type="text" id="test_api_key" name="test_api_key" placeholder="Paste your settlement x_api_key here..." required autocomplete="off" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-slate-700">
                        <span id="keyStatusIndicator" class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none text-xs hidden">
                            <i class="iclass fa-solid fa-spinner fa-spin text-teal-600"></i>
                        </span>
                    </div>
                </div>

                <!-- Account Verified Details Box -->
                <div id="accountDetailsBox" class="bg-white border border-teal-500/30 rounded-sm p-3.5 text-xs font-mono space-y-2 hidden">
                    <div class="flex justify-between items-center text-slate-500">
                        <span>Merchant Name:</span>
                        <strong id="resMerchantName" class="text-slate-900">-</strong>
                    </div>
                    <div class="flex justify-between items-center text-slate-500">
                        <span>Gateway Type:</span>
                        <strong id="resGatewayType" class="text-teal-600 uppercase">-</strong>
                    </div>
                    <div class="flex justify-between items-center text-slate-500">
                        <span>Shortcode:</span>
                        <strong id="resShortcode" class="text-slate-900">-</strong>
                    </div>
                    <div class="flex justify-between items-center text-slate-500">
                        <span>Account Status:</span>
                        <strong id="resAccountStatus" class="text-emerald-600">-</strong>
                    </div>
                </div>

                <div>
                    <label for="phone_number" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Customer Phone Number</label>
                    <input type="text" id="phone_number" name="phone_number" placeholder="07XXXXXXXX / 2547XXXXXXXX" required class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-800">
                </div>

                <div>
                    <label for="amount" class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Amount (KES)</label>
                    <input type="number" id="amount" name="amount" value="10" min="1" required class="w-full px-3.5 py-2.5 bg-white border border-slate-200 rounded-sm text-xs text-slate-800">
                </div>

                <button type="button" id="simulateBtn" disabled class="w-full bg-teal-600 hover:bg-teal-500 active:bg-teal-700 text-white py-2.5 rounded-sm font-bold text-xs uppercase tracking-wider transition shadow-md cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="iclass fa-solid fa-paper-plane"></i> Trigger STK Push
                </button>
            </form>
        </div>
    </div>

    <!-- Execution & Follow-up Status Card -->
    <div id="statusCard" class="bg-white border border-slate-200 p-4 rounded-sm shadow-xl hidden transition-all duration-300">
        <div class="flex items-start gap-3">
            <div id="statusIconWrap" class="w-10 h-10 rounded-sm flex items-center justify-center flex-shrink-0 text-sm bg-slate-100 text-slate-600">
                <i class="iclass fa-solid fa-spinner fa-spin" id="statusIcon"></i>
            </div>
            <div class="flex-1 min-w-0">
                <h3 id="statusTitle" class="text-xs font-bold text-slate-900 uppercase tracking-wider mb-1">Gateway Execution</h3>
                <p id="statusBody" class="text-xs text-slate-500 leading-relaxed">Ready to run transaction pipeline...</p>
                
                <div id="statusMeta" class="mt-3 pt-2.5 border-t border-slate-200 text-[11px] font-mono text-slate-500 space-y-1" style="display: none;">
                    <div class="flex justify-between gap-2">
                        <span>Checkout ID: <strong id="metaCheckoutId" class="text-slate-800">-</strong></span>
                    </div>
                    <div class="flex justify-between gap-2">
                        <span>Receipt: <strong id="metaRef" class="text-slate-800">-</strong></span>
                    </div>
                    <div class="flex justify-between gap-2 pt-1">
                        <span>Amount: <strong id="metaAmount" class="text-emerald-600">-</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>

<?php 
$footer_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php';
if (file_exists($footer_path)) include $footer_path;
?>

<script>
let verifiedApiKey = null;
let pollInterval = null;
let globalCheckoutID = null;

const apiKeyInput = document.getElementById('test_api_key');
const keyStatusIndicator = document.getElementById('keyStatusIndicator');
const accountDetailsBox = document.getElementById('accountDetailsBox');
const resMerchantName = document.getElementById('resMerchantName');
const resGatewayType = document.getElementById('resGatewayType');
const resShortcode = document.getElementById('resShortcode');
const resAccountStatus = document.getElementById('resAccountStatus');
const simulateBtn = document.getElementById('simulateBtn');

const statusCard = document.getElementById('statusCard');
const statusIconWrap = document.getElementById('statusIconWrap');
const statusIcon = document.getElementById('statusIcon');
const statusTitle = document.getElementById('statusTitle');
const statusBody = document.getElementById('statusBody');
const statusMeta = document.getElementById('statusMeta');
const metaCheckoutId = document.getElementById('metaCheckoutId');
const metaRef = document.getElementById('metaRef');
const metaAmount = document.getElementById('metaAmount');

function updateStatusCard(state, title, description, meta = null) {
    statusCard.style.display = "block";
    statusIconWrap.className = "w-10 h-10 rounded-sm flex items-center justify-center flex-shrink-0 text-sm";
    statusCard.className = "bg-white border border-slate-200 rounded-sm p-4 shadow-xl transition-all duration-300";

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
        metaCheckoutId.innerText = meta.checkout_id || globalCheckoutID || '-';
        metaRef.innerText = meta.ref || 'PENDING / PROCESSING';
        metaAmount.innerText = meta.amount ? `KES ${meta.amount}` : '-';
    } else {
        statusMeta.style.display = "none";
    }
}

function verifyApiKey(keyValue) {
    if (!keyValue || keyValue.trim() === '') {
        resetKeyUI();
        return;
    }

    keyStatusIndicator.classList.remove('hidden');
    keyStatusIndicator.innerHTML = '<i class="iclass fa-solid fa-spinner fa-spin text-teal-400"></i>';

    const formData = new FormData();
    formData.append('action', 'verify_key');
    formData.append('x_api_key', keyValue.trim());

    fetch('/api/sandbox.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(d => {
        keyStatusIndicator.classList.remove('hidden');
        if (d.status === 'success') {
            verifiedApiKey = keyValue.trim();
            keyStatusIndicator.innerHTML = '<i class="iclass fa-solid fa-circle-check text-emerald-400"></i>';
            
            resMerchantName.innerText = d.data.merchant_name;
            resGatewayType.innerText = d.data.type;
            resShortcode.innerText = d.data.shortcode;
            resAccountStatus.innerText = d.data.is_active == 1 ? 'ACTIVE' : 'INACTIVE';
            accountDetailsBox.style.display = 'block';
            
            simulateBtn.disabled = false;
            updateStatusCard('success', 'Key Verified', `Successfully linked profile for <strong>${d.data.merchant_name}</strong>.`);
        } else {
            resetKeyUI();
            keyStatusIndicator.innerHTML = '<i class="iclass fa-solid fa-circle-xmark text-rose-400"></i>';
            updateStatusCard('failed', 'Invalid API Key', d.message || 'No matching settlement profile found.');
        }
    })
    .catch(() => {
        resetKeyUI();
        keyStatusIndicator.classList.add('hidden');
        updateStatusCard('failed', 'Verification Error', 'Could not reach settlement database pipeline.');
    });
}

function resetKeyUI() {
    verifiedApiKey = null;
    accountDetailsBox.style.display = 'none';
    simulateBtn.disabled = true;
    keyStatusIndicator.classList.add('hidden');
}

// Follow-up status check polling GET /api/stk_push?check_status={CHECKOUT_ID}
function checkCurrentPaymentStatus(checkoutId) {
    fetch('/api/stk_push?check_status=' + encodeURIComponent(checkoutId))
    .then(r => r.json())
    .then(s => {
        if (s && s.status && s.status !== 'PENDING') {
            finalizeTransaction(s.status, s);
        }
    })
    .catch(() => console.log("Polling network sync..."));
}

function finalizeTransaction(status, data) {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
    simulateBtn.disabled = false;
    
    const inputAmount = document.getElementById('amount').value;
    const paidAmount = data.amount || inputAmount;

    if (status === 'SUCCESS' || status === '0') {
        updateStatusCard('success', 'Payment Completed!', `Transaction confirmed via M-Pesa.`, {
            checkout_id: globalCheckoutID,
            ref: data.mpesa_receipt_number || data.mpesa_receipt || 'CONFIRMED',
            amount: paidAmount
        });
    } else {
        updateStatusCard('failed', 'Transaction Failed', data.message || 'The transaction was cancelled or timed out.', {
            checkout_id: globalCheckoutID,
            ref: 'DECLINED',
            amount: paidAmount
        });
    }
}

apiKeyInput.addEventListener('paste', function() { setTimeout(() => verifyApiKey(this.value), 50); });
apiKeyInput.addEventListener('input', function() { if(this.value.trim().length <= 10) resetKeyUI(); });
apiKeyInput.addEventListener('change', function() { verifyApiKey(this.value); });

simulateBtn.addEventListener('click', function() {
    if (!verifiedApiKey) return;
    
    simulateBtn.disabled = true;
    updateStatusCard('processing', 'Triggering STK Push', 'Sending request to POST /api/stk_push...');
    
    const formData = new FormData();
    formData.append('action', 'send_stk');
    formData.append('x_api_key', verifiedApiKey);
    formData.append('phone_number', document.getElementById('phone_number').value);
    formData.append('amount', document.getElementById('amount').value);

    fetch('/api/sandbox.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(d => {
        if(d.status === 'success' || d.checkout_id) {
            globalCheckoutID = d.checkout_id;
            
            updateStatusCard('pin', 'PIN Prompt Active', 'STK Push sent. Enter your M-Pesa PIN on your phone.', {
                checkout_id: globalCheckoutID,
                ref: 'Awaiting PIN...',
                amount: document.getElementById('amount').value
            });
            
            if(pollInterval) clearInterval(pollInterval);
            // Poll status every 3 seconds per documentation instructions
            pollInterval = setInterval(() => {
                if (navigator.onLine && globalCheckoutID) {
                    checkCurrentPaymentStatus(globalCheckoutID);
                }
            }, 3000);
            
        } else {
            simulateBtn.disabled = false;
            updateStatusCard('failed', 'Request Rejected', d.message || 'Failed to dispatch STK push.');
        }
    })
    .catch(() => {
        simulateBtn.disabled = false;
        updateStatusCard('failed', 'System Error', 'Failed to connect to the billing gateway.');
    });
});
</script>
</body>
</html>