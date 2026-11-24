<?php
// docs/index.php
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
    <title>API Documentation - AvenPay</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #ffffff;
            --card-bg: rgba(248, 250, 252, 0.95);
            --card-border: rgba(15, 23, 42, 0.08);
        }
        body { background-color: var(--bg-color); color: #0f172a; }
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

<main class="max-w-4xl mx-auto p-4 md:p-8 w-full flex-1 space-y-8">

    <!-- Header Title Banner -->
    <div class="pb-6 border-b border-slate-200/80">
        <div class="flex items-center gap-2 text-teal-600 text-xs font-bold uppercase tracking-widest mb-1">
            <i class="iclass fa-solid fa-book"></i> Developer Hub
        </div>
        <h1 class="text-2xl font-black text-slate-900 tracking-wide">AvenPay API Documentation</h1>
        <p class="text-xs text-slate-500 mt-1">Collect with STK Push (C2B), pay people with B2C, and pay Paybills and Tills with B2B.</p>
    </div>

    <!-- Overview Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- C2B Card -->
        <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] p-6 rounded-sm shadow-xl flex flex-col justify-between space-y-6">
            <div class="space-y-3">
                <div class="w-10 h-10 rounded-sm bg-teal-950 border border-teal-800 flex items-center justify-center text-teal-600 text-base font-bold">
                    <i class="iclass fa-solid fa-arrow-down-long"></i>
                </div>
                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">C2B (Customer-to-Business)</h2>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Enable your customers to pay you or deposit funds directly into your system using M-Pesa STK Push. Triggers payment prompts on mobile handsets and verifies status via webhooks.
                </p>
                <div class="text-[11px] text-slate-500 font-mono bg-slate-50 p-2.5 rounded-sm border border-slate-200">
                    <span class="text-teal-600 font-bold">Authentication:</span> Uses merchant API key (<code class="text-amber-700">x_api_key</code>) in request payloads.
                </div>
            </div>
            <div>
                <a href="/api/docs/c2b.php" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 py-2.5 rounded-sm text-xs font-bold transition shadow-md flex items-center justify-center gap-2 cursor-pointer">
                    <i class="iclass fa-solid fa-code text-teal-600"></i> Visit C2B Documentation
                </a>
            </div>
        </div>

        <!-- B2C Card -->
        <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] p-6 rounded-sm shadow-xl flex flex-col justify-between space-y-6">
            <div class="space-y-3">
                <div class="w-10 h-10 rounded-sm bg-teal-950 border border-teal-800 flex items-center justify-center text-teal-600 text-base font-bold">
                    <i class="iclass fa-solid fa-arrow-up-long"></i>
                </div>
                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">B2C (Business-to-Customer)</h2>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Send payouts, salaries, or rewards directly to mobile money recipients from your merchant wallet. Features dynamic fee calculation, multi-type payouts, and automated refunds.
                </p>
                <div class="text-[11px] text-slate-500 font-mono bg-slate-50 p-2.5 rounded-sm border border-slate-200">
                    <span class="text-teal-600 font-bold">Authentication:</span> Uses header-based keys (<code class="text-amber-700">X-Wallet-ID</code> & <code class="text-amber-700">X-Security-Credential</code>).
                </div>
            </div>
            <div>
                <a href="/api/docs/b2c.php" class="w-full bg-teal-600 hover:bg-teal-500 text-white py-2.5 rounded-sm text-xs font-bold transition shadow-md flex items-center justify-center gap-2 cursor-pointer">
                    <i class="iclass fa-solid fa-code text-teal-800"></i> Visit B2C Documentation
                </a>
            </div>
        </div>


        <!-- B2B Card -->
        <div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] p-6 rounded-sm shadow-xl flex flex-col justify-between space-y-6">
            <div class="space-y-3">
                <div class="w-10 h-10 rounded-sm bg-teal-950 border border-teal-800 flex items-center justify-center text-teal-600 text-base font-bold">
                    <i class="iclass fa-solid fa-building-columns"></i>
                </div>
                <h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">B2B (Pay Paybill / Till)</h2>
                <p class="text-xs text-slate-600 leading-relaxed">Pay suppliers, utilities and merchants from your wallet. Funds are reserved first and refunded automatically if the payment fails.</p>
                <div class="text-[11px] text-slate-500 font-mono bg-slate-50 p-2.5 rounded-sm border border-slate-200">
                    <span class="text-teal-600 font-bold">Authentication:</span> Same wallet headers as B2C (<code class="text-amber-700">X-Wallet-ID</code> + <code class="text-amber-700">X-Security-Credential</code>).
                </div>
            </div>
            <div>
                <a href="/api/docs/b2b.php" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 py-2.5 rounded-sm text-xs font-bold transition shadow-md flex items-center justify-center gap-2">
                    <i class="iclass fa-solid fa-code text-teal-600"></i> Visit B2B Documentation
                </a>
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

</body>
</html>