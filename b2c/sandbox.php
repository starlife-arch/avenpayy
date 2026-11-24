<?php
// b2c/api_tester.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ini_set('display_errors', 0);
error_reporting(E_ALL);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<style>
    html, body { background-color: #f8fafc !important; color: #0f172a !important; }
    input, select, textarea { color: #0f172a !important; background-color: #ffffff !important; }
    input::placeholder, textarea::placeholder { color: #64748b !important; opacity: 1; }
        [class~="text-slate-400"] { color: #64748b !important; }
        [class~="text-slate-500"] { color: #475569 !important; }
</style>


    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta name="theme-color" content="#ffffff">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payouts Sandbox - AvenPay</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #ffffff;
            --card-bg: rgba(248, 250, 252, 0.95);
            --card-border: rgba(15, 23, 42, 0.08);
            --input-bg: #ffffff;
            --accent-purple: #60f2ce;
        }
        body { background-color: var(--bg-color); color: #0f172a; }
        .code-block { background-color: #ffffff; border: 1px solid rgba(15, 23, 42, 0.08); }
        pre { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">

<?php 
$header_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';
if (file_exists($header_path)) {
    include $header_path;
}
?>

<!-- UI Toast Notification Banner -->
<div id="toastNotification" class="fixed bottom-6 right-6 z-50 transform translate-y-20 opacity-0 transition-all duration-300 bg-white border border-teal-500/50 text-slate-700 px-4 py-3 rounded-sm shadow-2xl flex items-center gap-3 text-xs">
    <i class="iclass fa-solid fa-circle-check text-teal-600 text-sm"></i>
    <span id="toastMessage">Action completed successfully!</span>
</div>

<main class="max-w-5xl mx-auto p-4 md:p-8 w-full flex-1 space-y-8">

    <!-- Header Title Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200/80">
        <div>
            <div class="flex items-center gap-2 text-teal-600 text-xs font-bold uppercase tracking-widest mb-1">
                <i class="iclass fa-solid fa-code"></i> Developer Portal & Sandbox
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-wide">AvenPay B2C API Suite</h1>
            <p class="text-xs text-slate-500 mt-1">Test live cURL endpoints securely using X-Wallet-ID & X-Security-Credential headers.</p>
        </div>
    </div>

    <!-- Global Credentials Configuration Bar -->
    <div class="bg-white border border-slate-200 p-5 rounded-sm shadow-xl space-y-4">
        <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
            <i class="iclass fa-solid fa-shield-halved text-teal-600"></i> API Authentication Credentials (No Session Required)
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">X-Wallet-ID</label>
                <input type="text" id="test-wallet-id" autocomplete="off" data-lpignore="true" spellcheck="false" placeholder="Paste your Wallet ID here (e.g., WALLET_XYZ123)" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-teal-700 focus:border-teal-500 outline-none">
            </div>
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">X-Security-Credential</label>
                <input type="text" id="test-security-key" autocomplete="off" data-lpignore="true" spellcheck="false" placeholder="Paste your secret API key here" class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-teal-700 focus:border-teal-500 outline-none">
            </div>
        </div>
    </div>

    <!-- ENDPOINTS CONTAINER -->
    <div class="space-y-6">

        <!-- 1. CHECK BALANCE ENDPOINT -->
        <div class="bg-white border border-slate-200 p-5 rounded-sm shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="iclass fa-solid fa-wallet text-teal-600"></i> 1. Check Wallet Balance
                    </span>
                    <span class="text-[10px] font-mono font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-sm">POST</span>
                </div>
                <div class="flex items-center bg-white p-0.5 rounded-sm border border-slate-200">
                    <button onclick="toggleCardView('bal', 'sandbox')" id="bal-btn-sandbox" class="px-3 py-1 rounded-sm text-[11px] font-bold transition bg-teal-600 text-white cursor-pointer">Sandbox</button>
                    <button onclick="toggleCardView('bal', 'docs')" id="bal-btn-docs" class="px-3 py-1 rounded-sm text-[11px] font-bold transition text-slate-500 hover:text-slate-900 cursor-pointer">Docs</button>
                </div>
            </div>

            <div id="bal-sandbox-view" class="space-y-3">
                <button onclick="runTest('balance')" class="w-full bg-teal-600 hover:bg-teal-500 text-white font-bold py-2 rounded-sm text-xs transition cursor-pointer flex items-center justify-center gap-2 shadow-md">
                    <i class="iclass fa-solid fa-play"></i> Send Balance Request
                </button>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block mb-1">JSON Output</span>
                        <pre id="bal-json" class="text-xs text-emerald-600 whitespace-pre">Ready to test...</pre>
                    </div>
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-teal-700 uppercase tracking-wider block mb-1">Raw Response</span>
                        <pre id="bal-raw" class="text-xs text-teal-700 whitespace-pre">Waiting...</pre>
                    </div>
                </div>
            </div>

            <div id="bal-docs-view" class="hidden space-y-3 text-xs text-slate-600">
                <p>Retrieves live B2C wallet balance securely using API headers.</p>
                <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                    <pre class="text-xs text-slate-700 whitespace-pre">curl -X POST "<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/b2c/walletbalance'; ?>" \
  -H "X-Wallet-ID: WALLET_XYZ123" \
  -H "X-Security-Credential: your_api_key"</pre>
                </div>
            </div>
        </div>

        <!-- 2. LOOKUP RECEIVER NAME ENDPOINT -->
        <div class="bg-white border border-slate-200 p-5 rounded-sm shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="iclass fa-solid fa-address-book text-teal-600"></i> 2. Lookup Receiver Name
                    </span>
                    <span class="text-[10px] font-mono font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-sm">POST</span>
                </div>
                <div class="flex items-center bg-white p-0.5 rounded-sm border border-slate-200">
                    <button onclick="toggleCardView('lookup', 'sandbox')" id="lookup-btn-sandbox" class="px-3 py-1 rounded-sm text-[11px] font-bold transition bg-teal-600 text-white cursor-pointer">Sandbox</button>
                    <button onclick="toggleCardView('lookup', 'docs')" id="lookup-btn-docs" class="px-3 py-1 rounded-sm text-[11px] font-bold transition text-slate-500 hover:text-slate-900 cursor-pointer">Docs</button>
                </div>
            </div>

            <div id="lookup-sandbox-view" class="space-y-3">
                <input type="text" id="lookup-phone" autocomplete="off" placeholder="Phone Number (e.g., 0712345678)" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-slate-700 outline-none focus:border-teal-500">
                <button onclick="runTest('lookup')" class="w-full bg-teal-600 hover:bg-teal-500 text-white font-bold py-2 rounded-sm text-xs transition cursor-pointer flex items-center justify-center gap-2 shadow-md">
                    <i class="iclass fa-solid fa-play"></i> Lookup Receiver Name
                </button>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block mb-1">JSON Output</span>
                        <pre id="lookup-json" class="text-xs text-emerald-600 whitespace-pre">Ready to test...</pre>
                    </div>
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-teal-700 uppercase tracking-wider block mb-1">Raw Response</span>
                        <pre id="lookup-raw" class="text-xs text-teal-700 whitespace-pre">Waiting...</pre>
                    </div>
                </div>
            </div>

            <div id="lookup-docs-view" class="hidden space-y-3 text-xs text-slate-600">
                <p>Verify receiver names from past successful payouts using API credentials.</p>
                <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                    <pre class="text-xs text-slate-700 whitespace-pre">curl -X POST "<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/b2c/api/v1/lookup_receiver'; ?>" \
  -H "X-Wallet-ID: WALLET_XYZ123" \
  -H "X-Security-Credential: your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"phone": "0712345678"}'</pre>
                </div>
            </div>
        </div>

        <!-- 3. STK TOP-UP ENDPOINT -->
        <div class="bg-white border border-slate-200 p-5 rounded-sm shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="iclass fa-solid fa-cloud-arrow-down text-teal-600"></i> 3. Wallet Top-Up (STK Push)
                    </span>
                    <span class="text-[10px] font-mono font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-sm">POST</span>
                </div>
                <div class="flex items-center bg-white p-0.5 rounded-sm border border-slate-200">
                    <button onclick="toggleCardView('top', 'sandbox')" id="top-btn-sandbox" class="px-3 py-1 rounded-sm text-[11px] font-bold transition bg-teal-600 text-white cursor-pointer">Sandbox</button>
                    <button onclick="toggleCardView('top', 'docs')" id="top-btn-docs" class="px-3 py-1 rounded-sm text-[11px] font-bold transition text-slate-500 hover:text-slate-900 cursor-pointer">Docs</button>
                </div>
            </div>

            <div id="top-sandbox-view" class="space-y-3">
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" id="topup-phone" autocomplete="off" placeholder="Phone (2547...)" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-slate-700 outline-none focus:border-teal-500">
                    <input type="number" id="topup-amount" autocomplete="off" placeholder="Amount (KES)" value="500" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-slate-700 outline-none focus:border-teal-500">
                </div>
                <button onclick="runTest('topup')" class="w-full bg-teal-600 hover:bg-teal-500 text-white font-bold py-2 rounded-sm text-xs transition cursor-pointer flex items-center justify-center gap-2 shadow-md">
                    <i class="iclass fa-solid fa-play"></i> Send STK Push
                </button>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block mb-1">JSON Output</span>
                        <pre id="top-json" class="text-xs text-emerald-600 whitespace-pre">Ready to test...</pre>
                    </div>
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-teal-700 uppercase tracking-wider block mb-1">Raw Response</span>
                        <pre id="top-raw" class="text-xs text-teal-700 whitespace-pre">Waiting...</pre>
                    </div>
                </div>
            </div>

            <div id="top-docs-view" class="hidden space-y-3 text-xs text-slate-600">
                <p>Initiates STK Push deposit using API headers.</p>
                <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                    <pre class="text-xs text-slate-700 whitespace-pre">curl -X POST "<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/b2c/topup/stk_push'; ?>" \
  -H "X-Wallet-ID: WALLET_XYZ123" \
  -H "X-Security-Credential: your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"phone": "0712345678", "amount": 500}'</pre>
                </div>
            </div>
        </div>

        <!-- 4. CHECK TOP-UP STATUS ENDPOINT -->
        <div class="bg-white border border-slate-200 p-5 rounded-sm shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="iclass fa-solid fa-spinner text-teal-600"></i> 4. Check Top-Up Status
                    </span>
                    <span class="text-[10px] font-mono font-bold text-teal-600 bg-teal-950 border border-teal-800 px-2 py-0.5 rounded-sm">GET</span>
                </div>
                <div class="flex items-center bg-white p-0.5 rounded-sm border border-slate-200">
                    <button onclick="toggleCardView('topstatus', 'sandbox')" id="topstatus-btn-sandbox" class="px-3 py-1 rounded-sm text-[11px] font-bold transition bg-teal-600 text-white cursor-pointer">Sandbox</button>
                    <button onclick="toggleCardView('topstatus', 'docs')" id="topstatus-btn-docs" class="px-3 py-1 rounded-sm text-[11px] font-bold transition text-slate-500 hover:text-slate-900 cursor-pointer">Docs</button>
                </div>
            </div>

            <div id="topstatus-sandbox-view" class="space-y-3">
                <input type="text" id="topup-checkout-id" autocomplete="off" placeholder="Checkout Request ID (e.g. ws_CO_...)" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-slate-700 outline-none focus:border-teal-500">
                <button onclick="runTest('topup_status')" class="w-full bg-teal-600 hover:bg-teal-500 text-white font-bold py-2 rounded-sm text-xs transition cursor-pointer flex items-center justify-center gap-2 shadow-md">
                    <i class="iclass fa-solid fa-play"></i> Poll Top-Up Status
                </button>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block mb-1">JSON Output</span>
                        <pre id="topstatus-json" class="text-xs text-emerald-600 whitespace-pre">Ready to test...</pre>
                    </div>
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-teal-700 uppercase tracking-wider block mb-1">Raw Response</span>
                        <pre id="topstatus-raw" class="text-xs text-teal-700 whitespace-pre">Waiting...</pre>
                    </div>
                </div>
            </div>

            <div id="topstatus-docs-view" class="hidden space-y-3 text-xs text-slate-600">
                <p>Polls top-up transaction status using API credentials.</p>
                <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                    <pre class="text-xs text-slate-700 whitespace-pre">curl -X GET "<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/b2c/topup/check_status?checkout_id=ws_CO_...'; ?>" \
  -H "X-Wallet-ID: WALLET_XYZ123" \
  -H "X-Security-Credential: your_api_key"</pre>
                </div>
            </div>
        </div>

        <!-- 5. DISBURSEMENT PAYOUT ENDPOINT -->
        <div class="bg-white border border-slate-200 p-5 rounded-sm shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="iclass fa-solid fa-paper-plane text-teal-600"></i> 5. B2C Disbursement Payout
                    </span>
                    <span class="text-[10px] font-mono font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-sm">POST</span>
                </div>
                <div class="flex items-center bg-white p-0.5 rounded-sm border border-slate-200">
                    <button onclick="toggleCardView('pay', 'sandbox')" id="pay-btn-sandbox" class="px-3 py-1 rounded-sm text-[11px] font-bold transition bg-teal-600 text-white cursor-pointer">Sandbox</button>
                    <button onclick="toggleCardView('pay', 'docs')" id="pay-btn-docs" class="px-3 py-1 rounded-sm text-[11px] font-bold transition text-slate-500 hover:text-slate-900 cursor-pointer">Docs</button>
                </div>
            </div>

            <div id="pay-sandbox-view" class="space-y-3">
                <div class="grid grid-cols-2 gap-2">
                    <input type="text" id="payout-phone" autocomplete="off" placeholder="Recipient Phone (2547...)" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-slate-700 outline-none focus:border-teal-500">
                    <input type="number" id="payout-amount" autocomplete="off" placeholder="Amount (KES)" value="1000" class="px-3 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-slate-700 outline-none focus:border-teal-500">
                </div>
                <button onclick="runTest('payout')" class="w-full bg-teal-600 hover:bg-teal-500 text-white font-bold py-2 rounded-sm text-xs transition cursor-pointer flex items-center justify-center gap-2 shadow-md">
                    <i class="iclass fa-solid fa-play"></i> Send Payout (API Headless)
                </button>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block mb-1">JSON Output</span>
                        <pre id="pay-json" class="text-xs text-emerald-600 whitespace-pre">Ready to test...</pre>
                    </div>
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-teal-700 uppercase tracking-wider block mb-1">Raw Response</span>
                        <pre id="pay-raw" class="text-xs text-teal-700 whitespace-pre">Waiting...</pre>
                    </div>
                </div>
            </div>

            <div id="pay-docs-view" class="hidden space-y-3 text-xs text-slate-600">
                <p>Sends disbursement headlessly via cURL using API headers.</p>
                <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                    <pre class="text-xs text-slate-700 whitespace-pre">curl -X POST "<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/b2c/payout/withdraw'; ?>" \
  -H "X-Wallet-ID: WALLET_XYZ123" \
  -H "X-Security-Credential: your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"command_id": "BusinessPayment", "phone": "0712345678", "amount": 1000}'</pre>
                </div>
            </div>
        </div>

        <!-- 6. CHECK PAYOUT STATUS ENDPOINT -->
        <div class="bg-white border border-slate-200 p-5 rounded-sm shadow-xl space-y-4">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                        <i class="iclass fa-solid fa-spinner text-teal-600"></i> 6. Check Payout Status
                    </span>
                    <span class="text-[10px] font-mono font-bold text-emerald-600 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-sm">POST</span>
                </div>
                <div class="flex items-center bg-white p-0.5 rounded-sm border border-slate-200">
                    <button onclick="toggleCardView('status', 'sandbox')" id="status-btn-sandbox" class="px-3 py-1 rounded-sm text-[11px] font-bold transition bg-teal-600 text-white cursor-pointer">Sandbox</button>
                    <button onclick="toggleCardView('status', 'docs')" id="status-btn-docs" class="px-3 py-1 rounded-sm text-[11px] font-bold transition text-slate-500 hover:text-slate-900 cursor-pointer">Docs</button>
                </div>
            </div>

            <div id="status-sandbox-view" class="space-y-3">
                <input type="text" id="payout-conv-id" autocomplete="off" placeholder="Conversation ID (e.g. Ag_20261023_...)" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-slate-700 outline-none focus:border-teal-500">
                <button onclick="runTest('payout_status')" class="w-full bg-teal-600 hover:bg-teal-500 text-white font-bold py-2 rounded-sm text-xs transition cursor-pointer flex items-center justify-center gap-2 shadow-md">
                    <i class="iclass fa-solid fa-play"></i> Poll Payout Status
                </button>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-emerald-600 uppercase tracking-wider block mb-1">JSON Output</span>
                        <pre id="status-json" class="text-xs text-emerald-600 whitespace-pre">Ready to test...</pre>
                    </div>
                    <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                        <span class="text-[10px] font-bold text-teal-700 uppercase tracking-wider block mb-1">Raw Response</span>
                        <pre id="status-raw" class="text-xs text-teal-700 whitespace-pre">Waiting...</pre>
                    </div>
                </div>
            </div>

            <div id="status-docs-view" class="hidden space-y-3 text-xs text-slate-600">
                <p>Polls status of processing disbursement using API credentials.</p>
                <div class="code-block rounded-sm p-3 overflow-x-auto w-full">
                    <pre class="text-xs text-slate-700 whitespace-pre">curl -X POST "<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/b2c/payout/status'; ?>" \
  -H "X-Wallet-ID: WALLET_XYZ123" \
  -H "X-Security-Credential: your_api_key" \
  -H "Content-Type: application/json" \
  -d '{"conversation_id": "Ag_20261023_..."}'</pre>
                </div>
            </div>
        </div>

    </div>

</main>

<?php 
$footer_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; 
if (file_exists($footer_path)) {
    include $footer_path;
}
?>

<script>
const currentDomain = window.location.origin;

function toggleCardView(cardKey, view) {
    const sandboxView = document.getElementById(cardKey + '-sandbox-view');
    const docsView = document.getElementById(cardKey + '-docs-view');
    const btnSandbox = document.getElementById(cardKey + '-btn-sandbox');
    const btnDocs = document.getElementById(cardKey + '-btn-docs');

    if (view === 'sandbox') {
        sandboxView.classList.remove('hidden');
        docsView.classList.add('hidden');
        btnSandbox.className = "px-3 py-1 rounded-sm text-[11px] font-bold transition bg-teal-600 text-white cursor-pointer";
        btnDocs.className = "px-3 py-1 rounded-sm text-[11px] font-bold transition text-slate-500 hover:text-slate-900 cursor-pointer";
    } else {
        sandboxView.classList.add('hidden');
        docsView.classList.remove('hidden');
        btnDocs.className = "px-3 py-1 rounded-sm text-[11px] font-bold transition bg-teal-600 text-white cursor-pointer";
        btnSandbox.className = "px-3 py-1 rounded-sm text-[11px] font-bold transition text-slate-500 hover:text-slate-900 cursor-pointer";
    }
}

async function runTest(action) {
    const walletId = document.getElementById('test-wallet-id').value.trim();
    const securityKey = document.getElementById('test-security-key').value.trim();

    if (!walletId || !securityKey) {
        showToast("Please enter your X-Wallet-ID and X-Security-Credential.");
        return;
    }

    let url = "";
    let method = "POST";
    let bodyData = null;
    let targetKey = "";

    if (action === 'balance') {
        url = currentDomain + "/b2c/walletbalance";
        targetKey = "bal";
    } else if (action === 'lookup') {
        url = currentDomain + "/b2c/api/v1/lookup_receiver";
        bodyData = {
            phone: document.getElementById('lookup-phone').value.trim()
        };
        targetKey = "lookup";
    } else if (action === 'topup') {
        url = currentDomain + "/b2c/topup/stk_push";
        bodyData = {
            phone: document.getElementById('topup-phone').value.trim(),
            amount: parseFloat(document.getElementById('topup-amount').value) || 0
        };
        targetKey = "top";
    } else if (action === 'topup_status') {
        method = "GET";
        const checkoutId = document.getElementById('topup-checkout-id').value.trim();
        url = currentDomain + "/b2c/topup/check_status?checkout_id=" + encodeURIComponent(checkoutId);
        targetKey = "topstatus";
    } else if (action === 'payout') {
        url = currentDomain + "/b2c/payout/withdraw";
        bodyData = {
            command_id: 'BusinessPayment',
            phone: document.getElementById('payout-phone').value.trim(),
            amount: parseFloat(document.getElementById('payout-amount').value) || 0
        };
        targetKey = "pay";
    } else if (action === 'payout_status') {
        url = currentDomain + "/b2c/payout/status";
        bodyData = {
            conversation_id: document.getElementById('payout-conv-id').value.trim()
        };
        targetKey = "status";
    }

    document.getElementById(targetKey + '-json').textContent = "Sending request to " + url + " ...";
    document.getElementById(targetKey + '-raw').textContent = "Loading...";

    try {
        const options = {
            method: method,
            headers: {
                'X-Wallet-ID': walletId,
                'X-Security-Credential': securityKey,
                'Content-Type': 'application/json'
            }
        };

        if (method === 'POST' && bodyData) {
            options.body = JSON.stringify(bodyData);
        }

        const res = await fetch(url, options);
        const rawText = await res.text();

        try {
            const jsonParsed = JSON.parse(rawText);
            document.getElementById(targetKey + '-json').textContent = JSON.stringify(jsonParsed, null, 2);
        } catch (e) {
            document.getElementById(targetKey + '-json').textContent = rawText;
        }

        document.getElementById(targetKey + '-raw').textContent = `HTTP ${res.status} ${res.statusText}\n\n` + rawText;
        showToast("Request completed with status " + res.status);

    } catch (err) {
        document.getElementById(targetKey + '-json').textContent = JSON.stringify({ status: "error", message: err.message }, null, 2);
        document.getElementById(targetKey + '-raw').textContent = err.toString();
        showToast("Network connection error.");
    }
}

function showToast(message) {
    const toast = document.getElementById('toastNotification');
    document.getElementById('toastMessage').innerText = message;
    toast.classList.remove('translate-y-20', 'opacity-0');
    toast.classList.add('translate-y-0', 'opacity-100');
    setTimeout(() => {
        toast.classList.remove('translate-y-0', 'opacity-100');
        toast.classList.add('translate-y-20', 'opacity-0');
    }, 3000);
}
</script>

</body>
</html>