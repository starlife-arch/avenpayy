<?php
// b2c/fees.php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

// Optional: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

// Fetch active fee tiers from database
$result_fees = $conn->query("SELECT * FROM transaction_fees WHERE is_active = 1 ORDER BY service_type ASC, min_amount ASC");
$fees = $result_fees ? $result_fees->fetch_all(MYSQLI_ASSOC) : [];

$b2c_fees = array_filter($fees, function($f) { return $f['service_type'] === 'b2c_withdrawal'; });
$c2b_fees = array_filter($fees, function($f) { return $f['service_type'] === 'c2b_topup'; });
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
    <title>Fees - AvenPay</title>
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
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">

<?php 
$header_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';
if (file_exists($header_path)) include $header_path;
?>

<main class="max-w-4xl mx-auto p-4 md:p-6 w-full flex-1">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-200/80">
        <div>
            <h1 class="text-sm font-black text-slate-900 tracking-wide flex items-center gap-2">
                <i class="iclass fa-solid fa-receipt text-teal-600"></i>
                <span>Transaction Charges Schedule</span>
            </h1>
            <p class="text-[11px] text-slate-500 mt-0.5">Overview of applicable fee structures for wallet operations and payouts</p>
        </div>

        <!-- Tab Switcher Buttons -->
        <div class="flex items-center bg-white border border-slate-200 p-1 rounded-sm">
            <button id="btnB2C" onclick="switchTab('b2c')" class="px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer bg-teal-600 text-white shadow-md">
                B2C Withdrawal
            </button>
            <button id="btnC2B" onclick="switchTab('c2b')" class="px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer text-slate-500 hover:text-slate-900">
                C2B Top-Up
            </button>
        </div>
    </div>

    <!-- B2C Withdrawal Master Card -->
    <div id="cardB2C" class="bg-white border border-slate-200 rounded-sm shadow-xl backdrop-blur-md overflow-hidden">
        <div class="p-4 border-b border-slate-200/80">
            <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-money-bill-transfer text-teal-600"></i> B2C Withdrawal Fee Tiers
            </h2>
        </div>

        <?php if (empty($b2c_fees)): ?>
            <div class="p-8 text-center text-slate-500 text-xs">
                No active B2C withdrawal charges configured.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-slate-200/80">
                <?php foreach ($b2c_fees as $fee): ?>
                    <div class="p-4 hover:bg-slate-100/30 transition-colors">
                        <div class="grid grid-cols-3 divide-x divide-slate-200/80 text-center">
                            <div class="pr-2">
                                <span class="text-slate-500 block text-[9px] uppercase font-bold tracking-wider mb-1">Min Amount</span>
                                <strong class="text-slate-800 font-mono text-xs">KES <?= number_format($fee['min_amount'], 0) ?></strong>
                            </div>
                            <div class="px-2">
                                <span class="text-slate-500 block text-[9px] uppercase font-bold tracking-wider mb-1">Max Amount</span>
                                <strong class="text-slate-800 font-mono text-xs">KES <?= number_format($fee['max_amount'], 0) ?></strong>
                            </div>
                            <div class="pl-2">
                                <span class="text-slate-500 block text-[9px] uppercase font-bold tracking-wider mb-1">Charges Fee</span>
                                <strong class="text-amber-600 font-mono text-xs">
                                    <?php if ($fee['fee_type'] === 'percentage'): ?>
                                        <?= rtrim(rtrim(number_format($fee['fee_value'], 2), '0'), '.') ?>%
                                    <?php else: ?>
                                        KES <?= number_format($fee['fee_value'], 0) ?>
                                    <?php endif; ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- C2B Top-Up Master Card -->
    <div id="cardC2B" class="bg-white border border-slate-200 rounded-sm shadow-xl backdrop-blur-md overflow-hidden hidden">
        <div class="p-4 border-b border-slate-200/80">
            <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-wallet text-teal-600"></i> C2B Top-Up Fee Tiers
            </h2>
        </div>

        <?php if (empty($c2b_fees)): ?>
            <div class="p-8 text-center text-slate-500 text-xs">
                No active C2B top-up charges configured.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-slate-200/80">
                <?php foreach ($c2b_fees as $fee): ?>
                    <div class="p-4 hover:bg-slate-100/30 transition-colors">
                        <div class="grid grid-cols-3 divide-x divide-slate-200/80 text-center">
                            <div class="pr-2">
                                <span class="text-slate-500 block text-[9px] uppercase font-bold tracking-wider mb-1">Min Amount</span>
                                <strong class="text-slate-800 font-mono text-xs">KES <?= number_format($fee['min_amount'], 0) ?></strong>
                            </div>
                            <div class="px-2">
                                <span class="text-slate-500 block text-[9px] uppercase font-bold tracking-wider mb-1">Max Amount</span>
                                <strong class="text-slate-800 font-mono text-xs">KES <?= number_format($fee['max_amount'], 0) ?></strong>
                            </div>
                            <div class="pl-2">
                                <span class="text-slate-500 block text-[9px] uppercase font-bold tracking-wider mb-1">Charges Fee</span>
                                <strong class="text-amber-600 font-mono text-xs">
                                    <?php if ($fee['fee_type'] === 'percentage'): ?>
                                        <?= rtrim(rtrim(number_format($fee['fee_value'], 2), '0'), '.') ?>%
                                    <?php else: ?>
                                        KES <?= number_format($fee['fee_value'], 0) ?>
                                    <?php endif; ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php 
$footer_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; 
if (file_exists($footer_path)) include $footer_path;
?>

<script>
function switchTab(type) {
    const cardB2C = document.getElementById('cardB2C');
    const cardC2B = document.getElementById('cardC2B');
    const btnB2C = document.getElementById('btnB2C');
    const btnC2B = document.getElementById('btnC2B');

    if (type === 'b2c') {
        cardB2C.classList.remove('hidden');
        cardC2B.classList.add('hidden');
        
        btnB2C.className = "px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer bg-teal-600 text-white shadow-md";
        btnC2B.className = "px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer text-slate-500 hover:text-slate-900";
    } else {
        cardC2B.classList.remove('hidden');
        cardB2C.classList.add('hidden');
        
        btnC2B.className = "px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer bg-teal-600 text-white shadow-md";
        btnB2C.className = "px-3 py-1.5 rounded-sm text-xs font-bold transition cursor-pointer text-slate-500 hover:text-slate-900";
    }
}
</script>

</body>
</html>