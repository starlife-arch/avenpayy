<?php
// b2c/docs.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B2C Payouts API - AvenPay Docs</title>
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

    <style id="avenpay-docs-readable-theme">
      html, body { background:#f4f7fb !important; color:#172033 !important; color-scheme:light; }
      main { color:#172033 !important; }
      main [class*="rgba(15,23,42"], main [class*="bg-slate-50"],
      main [class*="bg-white"], main [class*="bg-white"] {
        background:#ffffff !important; border-color:#dbe3ed !important;
        box-shadow:0 8px 28px rgba(15,23,42,.06) !important;
      }
      main h1, main h2, main h3, main h4, main strong, main b,
      main label { color:#172033 !important; }
      main p, main li, main td, main th, main label { opacity:1 !important; }
      main p { color:#475569 !important; }
      main pre, main code, main pre code {
        background:#f1f5f9 !important; color:#1e293b !important;
        border-color:#dbe3ed !important; text-shadow:none !important;
      }
      main pre { padding:1rem !important; border:1px solid #dbe3ed !important;
        border-radius:.65rem !important; overflow-x:auto !important; }
      main input, main textarea, main select {
        background:#ffffff !important; color:#172033 !important;
        border-color:#cbd5e1 !important;
      }
      main .text-slate-100, main .text-white { color:#172033 !important; }
      main .text-slate-200, main .text-slate-300 { color:#334155 !important; }
      main .text-slate-400, main .text-slate-500, main .text-slate-600 { color:#475569 !important; }
      main .bg-emerald-950, main .bg-teal-950 { background:#d1fae5 !important; }
      main .text-emerald-600, main .text-teal-600 { color:#087f67 !important; }
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
    <span id="toastMessage">Copied to clipboard successfully!</span>
</div>

<main class="max-w-4xl mx-auto p-4 md:p-8 w-full flex-1 space-y-8">

    <!-- Header Title Banner -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-6 border-b border-slate-200/80">
        <div>
            <div class="flex items-center gap-2 text-teal-600 text-xs font-bold uppercase tracking-widest mb-1">
                <i class="iclass fa-solid fa-code"></i> Developer Portal
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-wide">AvenPay B2C API Integration</h1>
            <p class="text-xs text-slate-500 mt-1">Automated B2C payouts, receiver name lookups, balance verification, status polling, and STK deposits.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/b2c/index.php" class="bg-teal-600 hover:bg-teal-500 text-white px-4 py-2 rounded-sm text-xs font-bold transition shadow-lg flex items-center gap-2">
                <i class="iclass fa-solid fa-wallet"></i> B2C Dashboard
            </a>
            <a href="/b2c/transactions.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-4 py-2 rounded-sm text-xs font-bold transition flex items-center gap-2">
                <i class="iclass fa-solid fa-receipt text-teal-600"></i> History
            </a>
        </div>
    </div>

    <!-- Quick Overview Card -->
    <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] p-6 rounded-sm shadow-xl space-y-3">
        <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
            <i class="iclass fa-solid fa-circle-info text-teal-600"></i> Integration Overview
        </h2>
        <p class="text-xs text-slate-600 leading-relaxed">
            Integrate AvenPay Business-to-Customer (B2C) operations to manage live wallet balance checks, receiver name lookups from past successful transaction histories, insufficient balance validation, instant wallet top-ups via M-Pesa STK Push, automated disbursement payouts, asynchronous status polling, and real-time webhook callback notifications.
        </p>
    </div>

    <!-- Endpoint Summary Checklist Table -->
    <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] rounded-sm overflow-hidden shadow-xl">
        <div class="p-4 bg-white/80 border-b border-slate-200/80 flex items-center justify-between">
            <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-list-check text-teal-600"></i> B2C Endpoint Checklist
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="p-3.5">Operation</th>
                        <th class="p-3.5">Method</th>
                        <th class="p-3.5">Clean Endpoint URL</th>
                        <th class="p-3.5">Purpose</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/60 font-mono text-[11px] text-slate-600">
                    <tr class="hover:bg-white/50">
                        <td class="p-3.5 font-sans font-bold text-slate-900">Check Balance</td>
                        <td class="p-3.5"><span class="bg-emerald-950 text-emerald-600 px-2 py-0.5 rounded-sm border border-emerald-800 font-bold">POST</span></td>
                        <td class="p-3.5 text-teal-700 domain-sensed-endpoint" data-path="/b2c/walletbalance">...</td>
                        <td class="p-3.5 font-sans text-slate-500">Retrieve live B2C wallet balance</td>
                    </tr>
                    <tr class="hover:bg-white/50">
                        <td class="p-3.5 font-sans font-bold text-slate-900">Lookup Receiver Name</td>
                        <td class="p-3.5"><span class="bg-emerald-950 text-emerald-600 px-2 py-0.5 rounded-sm border border-emerald-800 font-bold">POST</span></td>
                        <td class="p-3.5 text-teal-700 domain-sensed-endpoint" data-path="/b2c/api/v1/lookup_receiver">...</td>
                        <td class="p-3.5 font-sans text-slate-500">Verify receiver name from past payouts</td>
                    </tr>
                    <tr class="hover:bg-white/50">
                        <td class="p-3.5 font-sans font-bold text-slate-900">Top-Up Wallet</td>
                        <td class="p-3.5"><span class="bg-emerald-950 text-emerald-600 px-2 py-0.5 rounded-sm border border-emerald-800 font-bold">POST</span></td>
                        <td class="p-3.5 text-teal-700 domain-sensed-endpoint" data-path="/b2c/topup/stk_push">...</td>
                        <td class="p-3.5 font-sans text-slate-500">Deposit funds via M-Pesa STK Push</td>
                    </tr>
                    <tr class="hover:bg-white/50">
                        <td class="p-3.5 font-sans font-bold text-slate-900">Check Top-Up Status</td>
                        <td class="p-3.5"><span class="bg-emerald-950 text-emerald-600 px-2 py-0.5 rounded-sm border border-emerald-800 font-bold">GET</span></td>
                        <td class="p-3.5 text-teal-700 domain-sensed-endpoint" data-path="/b2c/topup/check_status?checkout_id=">...</td>
                        <td class="p-3.5 font-sans text-slate-500">Poll live status of wallet top-up</td>
                    </tr>
                    <tr class="hover:bg-white/50">
                        <td class="p-3.5 font-sans font-bold text-slate-900">Disbursement Payout</td>
                        <td class="p-3.5"><span class="bg-emerald-950 text-emerald-600 px-2 py-0.5 rounded-sm border border-emerald-800 font-bold">POST</span></td>
                        <td class="p-3.5 text-teal-700 domain-sensed-endpoint" data-path="/b2c/payout/withdraw">...</td>
                        <td class="p-3.5 font-sans text-slate-500">Send instant B2C payouts (validates balance)</td>
                    </tr>
                    <tr class="hover:bg-white/50">
                        <td class="p-3.5 font-sans font-bold text-slate-900">Check Payout Status</td>
                        <td class="p-3.5"><span class="bg-emerald-950 text-emerald-600 px-2 py-0.5 rounded-sm border border-emerald-800 font-bold">POST</span></td>
                        <td class="p-3.5 text-teal-700 domain-sensed-endpoint" data-path="/b2c/payout/status">...</td>
                        <td class="p-3.5 font-sans text-slate-500">Poll live status of processing disbursement</td>
                    </tr>
                    <tr class="hover:bg-white/50">
                        <td class="p-3.5 font-sans font-bold text-slate-900">Safaricom result callback (internal)</td>
                        <td class="p-3.5"><span class="bg-teal-950 text-teal-600 px-2 py-0.5 rounded-sm border border-teal-800 font-bold">POST</span></td>
                        <td class="p-3.5 text-teal-700 domain-sensed-endpoint" data-path="/b2c/payout/result">...</td>
                        <td class="p-3.5 font-sans text-slate-500">AvenPay-only Safaricom callback receiver; not a merchant webhook URL</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 1: Authentication Security Parameters -->
    <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] p-6 rounded-sm shadow-xl space-y-4">
        <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
            <i class="iclass fa-solid fa-key text-teal-600"></i> 1. Authentication Security Headers
        </h3>
        <p class="text-xs text-slate-500 leading-relaxed">
            All API requests to AvenPay endpoints require server-to-server authentication headers:
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
            <div class="bg-slate-50 p-4 border border-slate-200 rounded-sm space-y-2">
                <span class="text-teal-600 font-mono font-bold">X-Wallet-ID</span>
                <p class="text-slate-600 text-[11px]">Your unique merchant wallet identifier from your dashboard sidebar.</p>
            </div>
            <div class="bg-slate-50 p-4 border border-slate-200 rounded-sm space-y-2">
                <span class="text-teal-600 font-mono font-bold">X-Security-Credential</span>
                <p class="text-slate-600 text-[11px]">Your secret API security credential generated in your B2C wallet settings.</p>
            </div>
        </div>
    </div>

    <!-- Section 2: Check Wallet Balance -->
    <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] rounded-sm shadow-xl overflow-hidden space-y-4 p-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-wallet text-teal-600"></i> 2. Check B2C Wallet Balance
            </h3>
            <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-950 border border-emerald-800 px-2 py-0.5 rounded-sm">POST</span>
        </div>
        <div class="space-y-3">
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Request Endpoint URL</label>
                <div class="flex items-center gap-2">
                    <input type="text" id="endpoint-bal" readonly class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-teal-700 select-all">
                    <button onclick="copyInput('endpoint-bal', 'Balance endpoint URL copied!')" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 rounded-sm text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shrink-0">Copy</button>
                </div>
            </div>
            <div class="code-block rounded-sm overflow-hidden">
                <div class="bg-white px-4 py-2 flex items-center justify-between border-b border-slate-200">
                    <div class="flex gap-2 text-xs font-bold">
                        <button onclick="showCode('bal', 'php')" id="bal-btn-php" class="px-2.5 py-1 bg-teal-600 text-white rounded-sm">PHP</button>
                        <button onclick="showCode('bal', 'node')" id="bal-btn-node" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Node.js</button>
                        <button onclick="showCode('bal', 'py')" id="bal-btn-py" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Python</button>
                        <button onclick="showCode('bal', 'curl')" id="bal-btn-curl" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">cURL</button>
                    </div>
                    <button onclick="copyCode('bal')" class="text-xs text-teal-300 hover:text-white bg-teal-950/60 hover:bg-teal-900 border border-teal-800 px-2.5 py-1 rounded-sm flex items-center gap-1.5 cursor-pointer font-bold transition">
                        <i class="iclass fa-regular fa-copy"></i> Copy
                    </button>
                </div>
                <div class="overflow-x-auto w-full">
                    <pre id="bal-code" class="p-4 text-xs text-slate-700 whitespace-pre leading-relaxed"></pre>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider">Example Success Response</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-emerald-600 whitespace-pre">{
  "status": "success",
  "wallet_id": "WALLET_XYZ123",
  "balance": 24500
}</pre>
                    </div>
                </div>
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-rose-600 tracking-wider">Example Failure & Reason</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-rose-600 whitespace-pre">{
  "status": "error",
  "message": "Invalid Wallet ID or Security Credential"
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 3: Lookup Receiver Name (API) -->
    <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] rounded-sm shadow-xl overflow-hidden space-y-4 p-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-address-book text-teal-600"></i> 3. Lookup Receiver Name (API)
            </h3>
            <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-950 border border-emerald-800 px-2 py-0.5 rounded-sm">POST</span>
        </div>
        <p class="text-xs text-slate-500 leading-relaxed">
            Programmatically check if a Kenyan phone number has a verified receiver name from past successful payouts before triggering a disbursement.
        </p>
        <div class="space-y-3">
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Request Endpoint URL</label>
                <div class="flex items-center gap-2">
                    <input type="text" id="endpoint-lookup" readonly class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-teal-700 select-all">
                    <button onclick="copyInput('endpoint-lookup', 'Lookup endpoint URL copied!')" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 rounded-sm text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shrink-0">Copy</button>
                </div>
            </div>
            <div class="code-block rounded-sm overflow-hidden">
                <div class="bg-white px-4 py-2 flex items-center justify-between border-b border-slate-200">
                    <div class="flex gap-2 text-xs font-bold">
                        <button onclick="showCode('lookup', 'php')" id="lookup-btn-php" class="px-2.5 py-1 bg-teal-600 text-white rounded-sm">PHP</button>
                        <button onclick="showCode('lookup', 'node')" id="lookup-btn-node" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Node.js</button>
                        <button onclick="showCode('lookup', 'py')" id="lookup-btn-py" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Python</button>
                        <button onclick="showCode('lookup', 'curl')" id="lookup-btn-curl" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">cURL</button>
                    </div>
                    <button onclick="copyCode('lookup')" class="text-xs text-teal-300 hover:text-white bg-teal-950/60 hover:bg-teal-900 border border-teal-800 px-2.5 py-1 rounded-sm flex items-center gap-1.5 cursor-pointer font-bold transition">
                        <i class="iclass fa-regular fa-copy"></i> Copy
                    </button>
                </div>
                <div class="overflow-x-auto w-full">
                    <pre id="lookup-code" class="p-4 text-xs text-slate-700 whitespace-pre leading-relaxed"></pre>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider">Example Success Response</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-emerald-600 whitespace-pre">{
  "status": "found",
  "phone": "0712345678",
  "receiver_name": "John Doe"
}</pre>
                    </div>
                </div>
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-amber-600 tracking-wider">Example Not Found Response</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-amber-600 whitespace-pre">{
  "status": "not_found",
  "phone": "0712345678",
  "message": "No prior successful payout record found for this number."
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 4: Wallet Top-Up (STK Push) -->
    <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] rounded-sm shadow-xl overflow-hidden space-y-4 p-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-cloud-arrow-down text-teal-600"></i> 4. Wallet Top-Up (STK Push)
            </h3>
            <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-950 border border-emerald-800 px-2 py-0.5 rounded-sm">POST</span>
        </div>
        <div class="space-y-3">
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Request Endpoint URL</label>
                <div class="flex items-center gap-2">
                    <input type="text" id="endpoint-top" readonly class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-teal-700 select-all">
                    <button onclick="copyInput('endpoint-top', 'Top-up endpoint URL copied!')" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 rounded-sm text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shrink-0">Copy</button>
                </div>
            </div>
            <div class="code-block rounded-sm overflow-hidden">
                <div class="bg-white px-4 py-2 flex items-center justify-between border-b border-slate-200">
                    <div class="flex gap-2 text-xs font-bold">
                        <button onclick="showCode('top', 'php')" id="top-btn-php" class="px-2.5 py-1 bg-teal-600 text-white rounded-sm">PHP</button>
                        <button onclick="showCode('top', 'node')" id="top-btn-node" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Node.js</button>
                        <button onclick="showCode('top', 'py')" id="top-btn-py" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Python</button>
                        <button onclick="showCode('top', 'curl')" id="top-btn-curl" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">cURL</button>
                    </div>
                    <button onclick="copyCode('top')" class="text-xs text-teal-300 hover:text-white bg-teal-950/60 hover:bg-teal-900 border border-teal-800 px-2.5 py-1 rounded-sm flex items-center gap-1.5 cursor-pointer font-bold transition">
                        <i class="iclass fa-regular fa-copy"></i> Copy
                    </button>
                </div>
                <div class="overflow-x-auto w-full">
                    <pre id="top-code" class="p-4 text-xs text-slate-700 whitespace-pre leading-relaxed"></pre>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider">Example Success Response</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-emerald-600 whitespace-pre">{
  "status": "success",
  "checkout_id": "ws_CO_221020261200000001",
  "message": "STK push sent. Check your phone."
}</pre>
                    </div>
                </div>
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-rose-600 tracking-wider">Example Failure & Reason</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-rose-600 whitespace-pre">{
  "status": "error",
  "message": "AvenPay gateway not configured."
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 5: Check Top-Up Status (Polling) -->
    <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] rounded-sm shadow-xl overflow-hidden space-y-4 p-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-spinner text-teal-600"></i> 5. Check Top-Up Status (Polling)
            </h3>
            <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-950 border border-emerald-800 px-2 py-0.5 rounded-sm">GET</span>
        </div>
        <div class="space-y-3">
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Request Endpoint URL</label>
                <div class="flex items-center gap-2">
                    <input type="text" id="endpoint-top-status" readonly class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-teal-700 select-all">
                    <button onclick="copyInput('endpoint-top-status', 'Top-up status endpoint URL copied!')" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 rounded-sm text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shrink-0">Copy</button>
                </div>
            </div>
            <div class="code-block rounded-sm overflow-hidden">
                <div class="bg-white px-4 py-2 flex items-center justify-between border-b border-slate-200">
                    <div class="flex gap-2 text-xs font-bold">
                        <button onclick="showCode('topstatus', 'php')" id="topstatus-btn-php" class="px-2.5 py-1 bg-teal-600 text-white rounded-sm">PHP</button>
                        <button onclick="showCode('topstatus', 'node')" id="topstatus-btn-node" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Node.js</button>
                        <button onclick="showCode('topstatus', 'py')" id="topstatus-btn-py" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Python</button>
                        <button onclick="showCode('topstatus', 'curl')" id="topstatus-btn-curl" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">cURL</button>
                    </div>
                    <button onclick="copyCode('topstatus')" class="text-xs text-teal-300 hover:text-white bg-teal-950/60 hover:bg-teal-900 border border-teal-800 px-2.5 py-1 rounded-sm flex items-center gap-1.5 cursor-pointer font-bold transition">
                        <i class="iclass fa-regular fa-copy"></i> Copy
                    </button>
                </div>
                <div class="overflow-x-auto w-full">
                    <pre id="topstatus-code" class="p-4 text-xs text-slate-700 whitespace-pre leading-relaxed"></pre>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider">Example Success Response</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-emerald-600 whitespace-pre">{
  "status": "completed",
  "success": true,
  "failed": false,
  "mpesa_receipt": "QWE123RTY4",
  "message": "Wallet successfully credited",
  "amount": 500
}</pre>
                    </div>
                </div>
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-rose-600 tracking-wider">Example Failure & Reason</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-rose-600 whitespace-pre">{
  "status": "failed",
  "success": false,
  "failed": true,
  "mpesa_receipt": "N/A",
  "message": "The user cancelled the request.",
  "amount": 500
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 6: Make Payout / Disbursement -->
    <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] rounded-sm shadow-xl overflow-hidden space-y-4 p-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-paper-plane text-teal-600"></i> 6. B2C Payout / Disbursement
            </h3>
            <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-950 border border-emerald-800 px-2 py-0.5 rounded-sm">POST</span>
        </div>
        <div class="space-y-3">
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Request Endpoint URL</label>
                <div class="flex items-center gap-2">
                    <input type="text" id="endpoint-pay" readonly class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-teal-700 select-all">
                    <button onclick="copyInput('endpoint-pay', 'Payout endpoint URL copied!')" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 rounded-sm text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shrink-0">Copy</button>
                </div>
            </div>
            <div class="code-block rounded-sm overflow-hidden">
                <div class="bg-white px-4 py-2 flex items-center justify-between border-b border-slate-200">
                    <div class="flex gap-2 text-xs font-bold">
                        <button onclick="showCode('pay', 'php')" id="pay-btn-php" class="px-2.5 py-1 bg-teal-600 text-white rounded-sm">PHP</button>
                        <button onclick="showCode('pay', 'node')" id="pay-btn-node" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Node.js</button>
                        <button onclick="showCode('pay', 'py')" id="pay-btn-py" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Python</button>
                        <button onclick="showCode('pay', 'curl')" id="pay-btn-curl" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">cURL</button>
                    </div>
                    <button onclick="copyCode('pay')" class="text-xs text-teal-300 hover:text-white bg-teal-950/60 hover:bg-teal-900 border border-teal-800 px-2.5 py-1 rounded-sm flex items-center gap-1.5 cursor-pointer font-bold transition">
                        <i class="iclass fa-regular fa-copy"></i> Copy
                    </button>
                </div>
                <div class="overflow-x-auto w-full">
                    <pre id="pay-code" class="p-4 text-xs text-slate-700 whitespace-pre leading-relaxed"></pre>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider">Example Success Response</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-emerald-600 whitespace-pre">{
  "status": "success",
  "conversation_id": "Ag_20261023_...",
  "message": "Disbursement initialized successfully!"
}</pre>
                    </div>
                </div>
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-rose-600 tracking-wider">Example Insufficient Balance Error</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-rose-600 whitespace-pre">{
  "status": "error",
  "code": "insufficient_balance",
  "message": "Required: KES 1,014, Available: KES 500."
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 7: Check Payout Status (Polling) -->
    <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] rounded-sm shadow-xl overflow-hidden space-y-4 p-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-spinner text-teal-600"></i> 7. Check Payout Status (Polling)
            </h3>
            <span class="text-xs font-mono font-bold text-emerald-600 bg-emerald-950 border border-emerald-800 px-2 py-0.5 rounded-sm">POST</span>
        </div>
        <div class="space-y-3">
            <div class="space-y-1">
                <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Request Endpoint URL</label>
                <div class="flex items-center gap-2">
                    <input type="text" id="endpoint-status" readonly class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-teal-700 select-all">
                    <button onclick="copyInput('endpoint-status', 'Status endpoint URL copied!')" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 rounded-sm text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shrink-0">Copy</button>
                </div>
            </div>
            <div class="code-block rounded-sm overflow-hidden">
                <div class="bg-white px-4 py-2 flex items-center justify-between border-b border-slate-200">
                    <div class="flex gap-2 text-xs font-bold">
                        <button onclick="showCode('status', 'php')" id="status-btn-php" class="px-2.5 py-1 bg-teal-600 text-white rounded-sm">PHP</button>
                        <button onclick="showCode('status', 'node')" id="status-btn-node" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Node.js</button>
                        <button onclick="showCode('status', 'py')" id="status-btn-py" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">Python</button>
                        <button onclick="showCode('status', 'curl')" id="status-btn-curl" class="px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm">cURL</button>
                    </div>
                    <button onclick="copyCode('status')" class="text-xs text-teal-300 hover:text-white bg-teal-950/60 hover:bg-teal-900 border border-teal-800 px-2.5 py-1 rounded-sm flex items-center gap-1.5 cursor-pointer font-bold transition">
                        <i class="iclass fa-regular fa-copy"></i> Copy
                    </button>
                </div>
                <div class="overflow-x-auto w-full">
                    <pre id="status-code" class="p-4 text-xs text-slate-700 whitespace-pre leading-relaxed"></pre>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider">Example Success Response</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-emerald-600 whitespace-pre">{
  "status": "success",
  "transaction_status": "COMPLETED",
  "result_code": 0,
  "result_desc": "The service request is processed successfully.",
  "receipt": "QWE123RTY4"
}</pre>
                    </div>
                </div>
                <div class="bg-slate-50 border border-slate-200 p-3 rounded-sm space-y-1">
                    <span class="text-[10px] uppercase font-bold text-rose-600 tracking-wider">Example Failed / Timeout Reason</span>
                    <div class="overflow-x-auto w-full">
                        <pre class="text-xs text-rose-600 whitespace-pre">{
  "status": "success",
  "transaction_status": "FAILED",
  "result_code": 2001,
  "result_desc": "The initiator information is invalid."
}</pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 8: Internal Safaricom Result Callback -->
    <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] rounded-sm shadow-xl overflow-hidden space-y-4 p-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-satellite-dish text-teal-600"></i> 8. Safaricom Result Callback (Internal)
            </h3>
            <span class="text-xs font-mono font-bold text-teal-600 bg-teal-950 border border-teal-800 px-2 py-0.5 rounded-sm">POST</span>
        </div>
        <p class="text-xs text-slate-500 leading-relaxed">
            This is AvenPay’s server-to-server callback receiver used by Safaricom to report B2C payout results. Do not register this URL as your merchant webhook and do not send requests to it yourself. It is protected by AvenPay callback authentication.
        </p>
        <div class="space-y-1">
            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider">Internal Safaricom callback receiver (reference only)</label>
            <div class="flex items-center gap-2">
                <input type="text" id="endpoint-result" readonly class="w-full px-3.5 py-2 bg-slate-50 border border-slate-200 rounded-sm text-xs font-mono text-teal-700 select-all">
                <button onclick="copyInput('endpoint-result', 'Internal callback URL copied!')" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-2 rounded-sm text-xs font-bold transition flex items-center gap-1.5 cursor-pointer shrink-0">Copy</button>
            </div>
        </div>
    </div>

</main>

<script>
const currentDomain = 'https://api.avenpay.co.ke';

document.addEventListener("DOMContentLoaded", function() {
    document.getElementById('endpoint-bal').value = currentDomain + "/b2c/walletbalance";
    document.getElementById('endpoint-lookup').value = currentDomain + "/b2c/api/v1/lookup_receiver";
    document.getElementById('endpoint-top').value = currentDomain + "/b2c/topup/stk_push";
    document.getElementById('endpoint-top-status').value = currentDomain + "/b2c/topup/check_status?checkout_id=ws_CO_221020261200000001";
    document.getElementById('endpoint-pay').value = currentDomain + "/b2c/payout/withdraw";
    document.getElementById('endpoint-status').value = currentDomain + "/b2c/payout/status";
    document.getElementById('endpoint-result').value = currentDomain + "/b2c/payout/result";

    document.querySelectorAll('.domain-sensed-endpoint').forEach(el => {
        const path = el.getAttribute('data-path');
        el.innerText = currentDomain + path;
    });

    renderSnippets();
});

const codeStore = {
    bal: { lang: 'php' },
    lookup: { lang: 'php' },
    top: { lang: 'php' },
    topstatus: { lang: 'php' },
    pay: { lang: 'php' },
    status: { lang: 'php' }
};

function renderSnippets() {
    // 1. Balance Snippets
    const balUrl = currentDomain + "/b2c/walletbalance";
    const balSnippets = {
        php: `\x3C?php\n$ch = curl_init("${balUrl}");\ncurl_setopt_array($ch, [\n    CURLOPT_POST            => true,\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_HTTPHEADER     => [\n        'X-Wallet-ID: WALLET_XYZ123',\n        'X-Security-Credential: your_api_key',\n        'Content-Type: application/json'\n    ],\n]);\n$response = curl_exec($ch);\ncurl_close($ch);\necho $response;\n?>`,
        node: `const fetch = require('node-fetch');\nasync function getBalance() {\n    const res = await fetch('${balUrl}', {\n        method: 'POST',\n        headers: { 'X-Wallet-ID': 'WALLET_XYZ123', 'X-Security-Credential': 'your_api_key' }\n    });\n    console.log(await res.json());\n}\ngetBalance();`,
        py: `import requests\nres = requests.post('${balUrl}', headers={'X-Wallet-ID': 'WALLET_XYZ123', 'X-Security-Credential': 'your_api_key'})\nprint(res.json())`,
        curl: `curl -X POST "${balUrl}" \\\n  -H "X-Wallet-ID: WALLET_XYZ123" \\\n  -H "X-Security-Credential: your_api_key"`
    };

    // 2. Lookup Receiver Snippets
    const lookupUrl = currentDomain + "/b2c/api/v1/lookup_receiver";
    const lookupSnippets = {
        php: `\x3C?php\n$ch = curl_init("${lookupUrl}");\ncurl_setopt_array($ch, [\n    CURLOPT_POST            => true,\n    CURLOPT_POSTFIELDS       => json_encode(['phone' => '0712345678']),\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_HTTPHEADER     => [\n        'Content-Type: application/json'\n    ],\n]);\n$response = curl_exec($ch);\ncurl_close($ch);\necho $response;\n?>`,
        node: `const fetch = require('node-fetch');\nasync function lookupReceiver() {\n    const res = await fetch('${lookupUrl}', {\n        method: 'POST',\n        headers: { 'Content-Type': 'application/json' },\n        body: JSON.stringify({ api_key: 'YOUR_USER_API_KEY', phone: '0712345678' })\n    });\n    console.log(await res.json());\n}\nlookupReceiver();`,
        py: `import requests\nres = requests.post('${lookupUrl}', json={'phone': '0712345678'}, headers={'X-Wallet-ID': 'WALLET_XYZ123', 'X-Security-Credential': 'your_api_key'})\nprint(res.json())`,
        curl: `curl -X POST "${lookupUrl}" \\\n  -H "X-Wallet-ID: WALLET_XYZ123" \\\n  -H "X-Security-Credential: your_api_key" \\\n  -H "Content-Type: application/json" \\\n  -d '{"phone": "0712345678"}'`
    };

    // 3. Topup Snippets
    const topUrl = currentDomain + "/b2c/topup/stk_push";
    const topSnippets = {
        php: `\x3C?php\n$ch = curl_init("${topUrl}");\ncurl_setopt_array($ch, [\n    CURLOPT_POST            => true,\n    CURLOPT_POSTFIELDS       => json_encode(['phone' => '0712345678', 'amount' => 500]),\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_HTTPHEADER     => [\n        'X-Wallet-ID: WALLET_XYZ123',\n        'X-Security-Credential: your_api_key',\n        'Content-Type: application/json'\n    ],\n]);\n$response = curl_exec($ch);\ncurl_close($ch);\necho $response;\n?>`,
        node: `const fetch = require('node-fetch');\nasync function topup() {\n    const res = await fetch('${topUrl}', {\n        method: 'POST',\n        headers: { 'X-Wallet-ID': 'WALLET_XYZ123', 'X-Security-Credential': 'your_api_key', 'Content-Type': 'application/json' },\n        body: JSON.stringify({ phone: '0712345678', amount: 500 })\n    });\n    console.log(await res.json());\n}\ntopup();`,
        py: `import requests\nres = requests.post('${topUrl}', json={'phone': '0712345678', 'amount': 500}, headers={'X-Wallet-ID': 'WALLET_XYZ123', 'X-Security-Credential': 'your_api_key'})\nprint(res.json())`,
        curl: `curl -X POST "${topUrl}" \\\n  -H "X-Wallet-ID: WALLET_XYZ123" \\\n  -H "X-Security-Credential: your_api_key" \\\n  -H "Content-Type: application/json" \\\n  -d '{"phone": "0712345678", "amount": 500}'`
    };

    // 4. Topup Status Snippets
    const topStatusUrl = currentDomain + "/b2c/topup/check_status?checkout_id=ws_CO_221020261200000001";
    const topStatusSnippets = {
        php: `\x3C?php\n$ch = curl_init("${topStatusUrl}");\ncurl_setopt_array($ch, [\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_HTTPHEADER     => [\n        'X-Wallet-ID: WALLET_XYZ123',\n        'X-Security-Credential: your_api_key'\n    ],\n]);\n$response = curl_exec($ch);\ncurl_close($ch);\necho $response;\n?>`,
        node: `const fetch = require('node-fetch');\nasync function checkStatus() {\n    const res = await fetch('${topStatusUrl}', {\n        headers: { 'X-Wallet-ID': 'WALLET_XYZ123', 'X-Security-Credential': 'your_api_key' }\n    });\n    console.log(await res.json());\n}\ncheckStatus();`,
        py: `import requests\nres = requests.get('${topStatusUrl}', headers={'X-Wallet-ID': 'WALLET_XYZ123', 'X-Security-Credential': 'your_api_key'})\nprint(res.json())`,
        curl: `curl -X GET "${topStatusUrl}" \\\n  -H "X-Wallet-ID: WALLET_XYZ123" \\\n  -H "X-Security-Credential: your_api_key"`
    };

    // 5. Payout Snippets
    const payUrl = currentDomain + "/b2c/payout/withdraw";
    const paySnippets = {
        php: `\x3C?php\n$ch = curl_init("${payUrl}");\ncurl_setopt_array($ch, [\n    CURLOPT_POST            => true,\n    CURLOPT_POSTFIELDS       => json_encode(['command_id' => 'BusinessPayment|SalaryPayment|PromotionPayment', 'phone' => '0712345678', 'amount' => 1000]),\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_HTTPHEADER     => [\n        'X-Wallet-ID: WALLET_XYZ123',\n        'X-Security-Credential: your_api_key',\n        'Content-Type: application/json'\n    ],\n]);\n$response = curl_exec($ch);\ncurl_close($ch);\necho $response;\n?>`,
        node: `const fetch = require('node-fetch');\nasync function disburse() {\n    const res = await fetch('${payUrl}', {\n        method: 'POST',\n        headers: { 'X-Wallet-ID': 'WALLET_XYZ123', 'X-Security-Credential': 'your_api_key', 'Content-Type': 'application/json' },\n        body: JSON.stringify({ command_id: 'BusinessPayment|SalaryPayment|PromotionPayment', phone: '0712345678', amount: 1000})\n    });\n    console.log(await res.json());\n}\ndisburse();`,
        py: `import requests\nres = requests.post('${payUrl}', json={'command_id': 'BusinessPayment|SalaryPayment|PromotionPayment', 'phone': '0712345678', 'amount': 1000}, headers={'X-Wallet-ID': 'WALLET_XYZ123', 'X-Security-Credential': 'your_api_key'})\nprint(res.json())`,
        curl: `curl -X POST "${payUrl}" \\\n  -H "X-Wallet-ID: WALLET_XYZ123" \\\n  -H "X-Security-Credential: your_api_key" \\\n  -H "Content-Type: application/json" \\\n  -d '{"command_id": "BusinessPayment|SalaryPayment|PromotionPayment", "phone": "0712345678", "amount": 1000}'`
    };

    // 6. Payout Status Snippets
    const statusUrl = currentDomain + "/b2c/payout/status";
    const statusSnippets = {
        php: `\x3C?php\n$ch = curl_init("${statusUrl}");\ncurl_setopt_array($ch, [\n    CURLOPT_POST            => true,\n    CURLOPT_POSTFIELDS       => json_encode(['conversation_id' => 'Ag_20261023_...']),\n    CURLOPT_RETURNTRANSFER => true,\n    CURLOPT_HTTPHEADER     => [\n        'X-Wallet-ID: WALLET_XYZ123',\n        'X-Security-Credential: your_api_key',\n        'Content-Type: application/json'\n    ],\n]);\n$response = curl_exec($ch);\ncurl_close($ch);\necho $response;\n?>`,
        node: `const fetch = require('node-fetch');\nasync function checkStatus() {\n    const res = await fetch('${statusUrl}', {\n        method: 'POST',\n        headers: { 'X-Wallet-ID': 'WALLET_XYZ123', 'X-Security-Credential': 'your_api_key', 'Content-Type': 'application/json' },\n        body: JSON.stringify({ conversation_id: 'Ag_20261023_...' })\n    });\n    console.log(await res.json());\n}\ncheckStatus();`,
        py: `import requests\nres = requests.post('${statusUrl}', json={'conversation_id': 'Ag_20261023_...'}, headers={'X-Wallet-ID': 'WALLET_XYZ123', 'X-Security-Credential': 'your_api_key'})\nprint(res.json())`,
        curl: `curl -X POST "${statusUrl}" \\\n  -H "X-Wallet-ID: WALLET_XYZ123" \\\n  -H "X-Security-Credential: your_api_key" \\\n  -H "Content-Type: application/json" \\\n  -d '{"conversation_id": "Ag_20261023_..."}'`
    };

    document.getElementById('bal-code').textContent = balSnippets[codeStore.bal.lang];
    document.getElementById('lookup-code').textContent = lookupSnippets[codeStore.lookup.lang];
    document.getElementById('top-code').textContent = topSnippets[codeStore.top.lang];
    document.getElementById('topstatus-code').textContent = topStatusSnippets[codeStore.topstatus.lang];
    document.getElementById('pay-code').textContent = paySnippets[codeStore.pay.lang];
    document.getElementById('status-code').textContent = statusSnippets[codeStore.status.lang];
}

function showCode(section, lang) {
    codeStore[section].lang = lang;
    ['php', 'node', 'py', 'curl'].forEach(l => {
        const btn = document.getElementById(`${section}-btn-${l}`);
        if (btn) {
            btn.className = (l === lang) ? "px-2.5 py-1 bg-teal-600 text-white rounded-sm font-bold" : "px-2.5 py-1 text-slate-500 hover:text-slate-900 rounded-sm font-bold";
        }
    });
    renderSnippets();
}

function copyCode(section) {
    const text = document.getElementById(`${section}-code`).textContent;
    navigator.clipboard.writeText(text).then(() => showToast("Code copied successfully!"));
}

function copyInput(elementId, message) {
    const input = document.getElementById(elementId);
    input.select();
    navigator.clipboard.writeText(input.value).then(() => showToast(message));
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

\x3C?php 
$footer_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; 
if (file_exists($footer_path)) {
    include $footer_path;
}
?>
</body>
</html>
