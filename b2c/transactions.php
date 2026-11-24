<?php
// b2c/transactions.php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /login.php");
    exit();
}

$user_id = $_SESSION['user_id'];$user_wallet_id = '';

// 1. Fetch user wallet_id
$stmt =$conn->prepare("SELECT wallet_id FROM users WHERE id = ?");
if ($stmt) {$stmt->bind_param("i", $user_id);$stmt->execute();
    $res =$stmt->get_result()->fetch_assoc();
    if ($res) {
        $user_wallet_id =$res['wallet_id'] ?? '';
    }
    $stmt->close();
}

$transactions = [];

// 2. Fetch Payouts from mpesa_b2c_transactions (uses transaction_id)
if (!empty($user_wallet_id)) {
    $payoutQuery =$conn->prepare("
        SELECT 
            'Payout' as type,
            transaction_amount as amount,
            receiver_phone as phone,
            receiver_name as recipient,
            result_code,
            result_desc as description,
            COALESCE(completed_at, created_at) as tx_date,
            COALESCE(transaction_id, conversation_id, 'N/A') as tx_reference
        FROM mpesa_b2c_transactions 
        WHERE wallet_id = ?
    ");
    if ($payoutQuery) {$payoutQuery->bind_param("s", $user_wallet_id);$payoutQuery->execute();
        $payoutRes =$payoutQuery->get_result();
        while ($row =$payoutRes->fetch_assoc()) {
            $transactions[] =$row;
        }
        $payoutQuery->close();
    }
}

// 3. Fetch Top-Ups from b2c_topup_transactions (uses receipt_number)
$topupQuery =$conn->prepare("
    SELECT 
        'Top-Up' as type,
        amount,
        phone_number as phone,
        '' as recipient,
        CASE WHEN status = 'completed' THEN 0 ELSE 1 END as result_code,
        CONCAT('Status: ', status) as description,
        created_at as tx_date,
        COALESCE(receipt_number, checkout_request_id, 'N/A') as tx_reference
    FROM b2c_topup_transactions 
    WHERE user_id = ?
");
if ($topupQuery) {$topupQuery->bind_param("i", $user_id);$topupQuery->execute();
    $topupRes =$topupQuery->get_result();
    while ($row =$topupRes->fetch_assoc()) {
        $transactions[] =$row;
    }
    $topupQuery->close();
}

// Sort combined transactions by date descending
usort($transactions, function($a,$b) {
    return strtotime($b['tx_date']) - strtotime($a['tx_date']);
});
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
    <title>Payout History - AvenPay</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #ffffff;
            --card-bg: rgba(248, 250, 252, 0.95);
            --card-border: rgba(15, 23, 42, 0.08);
            --text-main: #0f172a;
            --text-muted: #475569;
            --accent-purple: #60f2ce;
        }
        body { background-color: var(--bg-color); color: var(--text-main); }
    </style>
</head>
<body class="min-h-screen flex flex-col font-sans antialiased">

<?php 
$header_path =$_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';
if (file_exists($header_path)) include$header_path;
?>

<main class="max-w-5xl mx-auto p-4 md:p-6 w-full flex-1 space-y-5">
    
    <!-- Top Header & Filters -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 pb-3 border-b border-slate-200/80">
        <div>
            <h1 class="text-sm font-black text-slate-900 tracking-wide flex items-center gap-2">
                <i class="iclass fa-solid fa-receipt text-teal-600"></i>
                <span id="pageTitleHeading">B2C Transaction History</span>
            </h1>
            <p class="text-[11px] text-slate-500 mt-0.5">Comprehensive audit log of all your wallet top-ups and paid-out disbursements</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <!-- Search Bar Input -->
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-2.5 pointer-events-none text-slate-500">
                    <i class="iclass fa-solid fa-magnifying-glass text-xs"></i>
                </span>
                <input type="text" id="searchInput" onkeyup="filterTransactions()" placeholder="Search ID or Phone..." class="bg-white border border-slate-200 text-slate-800 text-xs rounded-sm pl-8 pr-3 py-1.5 focus:outline-none focus:border-teal-500 w-44 md:w-52 transition">
            </div>

            <a href="/b2c/index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 px-3 py-1.5 rounded-sm text-xs font-bold transition flex items-center gap-1.5 shadow-md">
                <i class="iclass fa-solid fa-arrow-left"></i> Wallet
            </a>

            <!-- Toggle Filter Switcher -->
            <div class="flex items-center bg-slate-50 border border-slate-200 p-1 rounded-sm">
                <button type="button" id="btnAll" onclick="setTabFilter('all')" class="px-2.5 py-1 rounded-sm text-xs font-bold transition cursor-pointer bg-teal-600 text-white shadow-md">
                    All
                </button>
                <button type="button" id="btnTopup" onclick="setTabFilter('topup')" class="px-2.5 py-1 rounded-sm text-xs font-bold transition cursor-pointer text-slate-500 hover:text-slate-900">
                    Top-Ups
                </button>
                <button type="button" id="btnPayout" onclick="setTabFilter('payout')" class="px-2.5 py-1 rounded-sm text-xs font-bold transition cursor-pointer text-slate-500 hover:text-slate-900">
                    Paid Out
                </button>
            </div>
        </div>
    </div>

    <!-- Transactions Table Card -->
    <div class="bg-white border border-slate-200 rounded-sm shadow-xl backdrop-blur-md overflow-hidden">
        <div class="p-4 border-b border-slate-200/80 flex items-center justify-between">
            <h2 id="tableHeaderTitle" class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-list text-teal-600"></i> All Wallet Activity
            </h2>
            <span id="txCountBadge" class="text-[10px] text-slate-500 font-mono font-bold"><?= count($transactions) ?> entries</span>
        </div>

        <?php if (empty($transactions)): ?>
            <div class="p-12 text-center text-slate-500 text-xs">
                No B2C transactions recorded yet.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-white/60 border-b border-slate-200 text-[10px] text-slate-500 uppercase font-bold tracking-wider">
                            <th class="p-3">Type</th>
                            <th class="p-3">Phone / Recipient</th>
                            <th class="p-3">Transaction ID / Ref</th>
                            <th class="p-3">Amount</th>
                            <th class="p-3">Status</th>
                            <th class="p-3">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody id="txTableBody" class="divide-y divide-slate-200/80">
                        <?php foreach ($transactions as$tx): ?>
                            <tr class="tx-row hover:bg-slate-100/30 transition-colors" data-type="<?= strtolower($tx['type']) ?>" data-search="<?= strtolower(htmlspecialchars($tx['tx_reference'] . ' ' . $tx['phone'] . ' ' .$tx['recipient'])) ?>">
                                <td class="p-3 font-semibold">
                                    <?php if ($tx['type'] === 'Top-Up'): ?>
                                        <span class="text-teal-600 flex items-center gap-1.5"><i class="iclass fa-solid fa-cloud-arrow-down"></i> Topup</span>
                                    <?php else: ?>
                                        <span class="text-amber-600 flex items-center gap-1.5"><i class="iclass fa-solid fa-paper-plane"></i> Payout</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3">
                                    <div class="font-mono text-slate-800"><?= htmlspecialchars($tx['phone'] ?? 'N/A') ?></div>
                                    <?php if (!empty($tx['recipient'])): ?>
                                        <div class="text-[10px] text-slate-500"><?= htmlspecialchars($tx['recipient']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 font-mono text-slate-600">
                                    <?= htmlspecialchars($tx['tx_reference'] ?? 'N/A') ?>
                                </td>
                                <td class="p-3 font-mono font-bold <?= $tx['type'] === 'Top-Up' ? 'text-teal-400' : 'text-amber-400' ?>">
                                    KES <?= number_format($tx['amount'], 0) ?>
                                </td>
                                <td class="p-3">
                                    <?php if ((int)$tx['result_code'] === 0): ?>
                                        <span class="px-2 py-0.5 rounded-sm bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 font-semibold text-[10px]">Success</span>
                                    <?php else: ?>
                                        <span class="px-2 py-0.5 rounded-sm bg-amber-500/10 border border-amber-500/20 text-amber-600 font-semibold text-[10px]" title="<?= htmlspecialchars($tx['description']) ?>">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-slate-500 font-mono text-[11px]">
                                    <?= htmlspecialchars($tx['tx_date'] ?? 'N/A') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls Bar -->
            <div id="paginationContainer" class="p-3 border-t border-slate-200/80 flex items-center justify-between text-xs bg-white/40">
                <span id="paginationInfo" class="text-slate-500 text-[11px]">Showing 1 to 10 of entries</span>
                <div class="flex items-center gap-1.5">
                    <button type="button" id="prevPageBtn" onclick="changePage(-1)" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-sm font-bold transition disabled:opacity-40 disabled:cursor-not-allowed">
                        Prev
                    </button>
                    <span id="pageIndicator" class="px-3 py-1 bg-slate-50 border border-slate-200 text-slate-600 font-mono rounded-sm">1 / 1</span>
                    <button type="button" id="nextPageBtn" onclick="changePage(1)" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-sm font-bold transition disabled:opacity-40 disabled:cursor-not-allowed">
                        Next
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php 
$footer_path =$_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; 
if (file_exists($footer_path)) include$footer_path;
?>

<script>
let currentTab = 'all';
let currentPage = 1;
const rowsPerPage = 10;

function setTabFilter(type) {
    currentTab = type;
    currentPage = 1;

    const btnAll = document.getElementById('btnAll');
    const btnTopup = document.getElementById('btnTopup');
    const btnPayout = document.getElementById('btnPayout');
    const tableHeaderTitle = document.getElementById('tableHeaderTitle');

    const activeClass = "px-2.5 py-1 rounded-sm text-xs font-bold transition cursor-pointer bg-teal-600 text-white shadow-md";
    const inactiveClass = "px-2.5 py-1 rounded-sm text-xs font-bold transition cursor-pointer text-slate-500 hover:text-slate-900";

    btnAll.className = inactiveClass;
    btnTopup.className = inactiveClass;
    btnPayout.className = inactiveClass;

    if (type === 'all') {
        btnAll.className = activeClass;
        tableHeaderTitle.innerHTML = '<i class="iclass fa-solid fa-list text-teal-400"></i> All Wallet Activity';
    } else if (type === 'topup') {
        btnTopup.className = activeClass;
        tableHeaderTitle.innerHTML = '<i class="iclass fa-solid fa-cloud-arrow-down text-teal-400"></i> Top-Up History';
    } else if (type === 'payout') {
        btnPayout.className = activeClass;
        tableHeaderTitle.innerHTML = '<i class="iclass fa-solid fa-paper-plane text-amber-400"></i> Paid Out History';
    }

    filterTransactions();
}

function changePage(direction) {
    currentPage += direction;
    filterTransactions();
}

function filterTransactions() {
    const searchInput = document.getElementById('searchInput');
    const searchVal = (searchInput?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('.tx-row');
    const txCountBadge = document.getElementById('txCountBadge');
    const paginationInfo = document.getElementById('paginationInfo');
    const pageIndicator = document.getElementById('pageIndicator');
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');

    let visibleRows = [];

    rows.forEach(row => {
        const rowType = row.getAttribute('data-type'); // 'top-up' or 'payout'
        const rowSearchText = row.getAttribute('data-search');

        // Check Tab Filter match
        const matchesTab = (currentTab === 'all' || 
            (currentTab === 'topup' && rowType === 'top-up') || 
            (currentTab === 'payout' && rowType === 'payout'));

        // Check Search Input match (Transaction ID or Phone number)
        const matchesSearch = (searchVal === '' || rowSearchText.includes(searchVal));

        if (matchesTab && matchesSearch) {
            visibleRows.push(row);
            row.style.display = 'none'; // hidden initially for pagination slice
        } else {
            row.style.display = 'none';
        }
    });

    const totalVisible = visibleRows.length;
    const totalPages = Math.ceil(totalVisible / rowsPerPage) || 1;

    if (currentPage > totalPages) currentPage = totalPages;
    if (currentPage < 1) currentPage = 1;

    const startIdx = (currentPage - 1) * rowsPerPage;
    const endIdx = startIdx + rowsPerPage;

    // Display sliced items for current page
    for (let i = startIdx; i < endIdx && i < totalVisible; i++) {
        visibleRows[i].style.display = '';
    }

    // Update Counter Badges & Pagination Footers
    if (txCountBadge) {
        txCountBadge.innerText = `${totalVisible} entries`;
    }

    // Pagination controls are not rendered when the PHP page has no transactions.
    // Keep tab/search filtering safe in that empty state.
    if (paginationInfo) {
        if (totalVisible > 0) {
            paginationInfo.innerText = `Showing ${startIdx + 1} to ${Math.min(endIdx, totalVisible)} of ${totalVisible} entries`;
        } else {
            paginationInfo.innerText = `Showing 0 entries`;
        }
    }

    if (pageIndicator) {
        pageIndicator.innerText = `${currentPage} / ${totalPages}`;
    }
    if (prevPageBtn) {
        prevPageBtn.disabled = currentPage === 1;
    }
    if (nextPageBtn) {
        nextPageBtn.disabled = currentPage === totalPages || totalPages === 0;
    }
}

// Initial render call on page load
document.addEventListener("DOMContentLoaded", function () {
    filterTransactions();
});
</script>

</body>
</html>