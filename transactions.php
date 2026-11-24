<?php
// transactions.php
session_start();
require_once 'database_config/db.php';

// Access control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Filter parameters
$search = trim($_GET['search'] ?? '');
$status_filter = trim($_GET['status'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

// Build dynamic WHERE clause
$whereClauses = ["user_id = ?"];
$params = [$user_id];
$types = "i";

if ($status_filter !== '') {
    $whereClauses[] = "status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($date_from !== '') {
    $whereClauses[] = "DATE(transaction_date) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if ($date_to !== '') {
    $whereClauses[] = "DATE(transaction_date) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

if ($search !== '') {
    $whereClauses[] = "(mpesa_receipt_number LIKE ? OR phone_number LIKE ?)";
    $searchWildcard = "%" . $search . "%";
    $params[] = $searchWildcard;
    $params[] = $searchWildcard;
    $types .= "ss";
}

$whereSql = implode(" AND ", $whereClauses);

// Pagination configuration (50 per page)
$limit = 50;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Get total transactions count for pagination matching filters
$count_sql = "SELECT COUNT(*) as total FROM mpesa_transactions WHERE " . $whereSql;
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_rows / $limit);
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

require_once __DIR__ . '/frame/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta name="theme-color" content="#ffffff">
    <meta charset="UTF-8">
    <title>Transactions - AvenPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus {
            -webkit-text-fill-color: #0f172a;
            -webkit-box-shadow: 0 0 0px 1000px #f8fafc inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #ffffff; }
        ::-webkit-scrollbar-thumb { background: #f1f5f9; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #e2e8f0; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">

<main class="flex-grow p-4 md:p-6 relative">
    <!-- Ambient Background Glow Accents -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-6xl mx-auto relative z-10 space-y-6">
        <!-- Header Section -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-200/80">
            <div>
                <h1 class="text-sm font-black text-slate-900 tracking-wide flex items-center gap-2">
                    <i class="iclass fa-solid fa-receipt text-teal-600"></i>
                    <span>Transactions History</span>
                </h1>
                <p class="text-[11px] text-slate-500 mt-0.5">View and filter all processed M-Pesa payments</p>
            </div>
            <div class="bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 px-4 py-2 rounded-xl shadow-xl flex items-center gap-3">
                <div class="w-7 h-7 rounded-lg bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-600 text-xs">
                    <i class="iclass fa-solid fa-hashtag"></i>
                </div>
                <div>
                    <p class="text-[9px] uppercase font-bold text-slate-500 tracking-wider">Total Records</p>
                    <p class="text-xs font-mono font-bold text-slate-900"><?= number_format($total_rows) ?></p>
                </div>
            </div>
        </div>

        <!-- Filter & Search Panel -->
        <div class="bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 rounded-2xl p-4 shadow-xl ring-1 ring-slate-200">
            <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <!-- Search Box -->
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Search Ref / Phone</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 text-xs">
                            <i class="iclass fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Receipt or phone..." 
                               class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 pl-9 pr-3.5 py-2.5 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 transition font-mono">
                    </div>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Status</label>
                    <select name="status" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2.5 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 transition">
                        <option value="">All Statuses</option>
                        <option value="SUCCESS" <?= $status_filter === 'SUCCESS' ? 'selected' : '' ?>>Success</option>
                        <option value="FAILED" <?= $status_filter === 'FAILED' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>

                <!-- Date From -->
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>" 
                           class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 transition font-mono">
                </div>

                <!-- Date To & Submit -->
                <div class="flex items-end gap-2">
                    <div class="flex-grow">
                        <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">To Date</label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>" 
                               class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 transition font-mono">
                    </div>
                    <button type="submit" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2.5 rounded-xl transition text-xs shadow-lg shadow-teal-500/20 cursor-pointer h-[34px] flex items-center justify-center">
                        <i class="iclass fa-solid fa-filter"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Transactions Table Container -->
        <div class="bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 rounded-2xl shadow-xl overflow-hidden ring-1 ring-slate-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 min-w-[850px]">
                    <thead class="bg-slate-50/60 text-slate-500 uppercase text-[10px] border-b border-slate-200/80 tracking-wider">
                        <tr>
                            <th class="p-3.5 pl-4">Date & Time</th>
                            <th class="p-3.5">Phone Number</th>
                            <th class="p-3.5">Reference</th>
                            <th class="p-3.5">Reason / Message</th>
                            <th class="p-3.5">Amount</th>
                            <th class="p-3.5 pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80">
                        <?php
                        $tx_sql = "SELECT phone_number, mpesa_receipt_number, amount, status, transaction_date FROM mpesa_transactions WHERE " . $whereSql . " ORDER BY transaction_date DESC LIMIT ? OFFSET ?";
                        $params[] = $limit;
                        $params[] = $offset;
                        $types .= "ii";

                        $tx_stmt = $conn->prepare($tx_sql);
                        $tx_stmt->bind_param($types, ...$params);
                        $tx_stmt->execute();
                        $transactions = $tx_stmt->get_result();

                        if ($transactions->num_rows > 0) {
                            while ($row = $transactions->fetch_assoc()) {
                                $is_success = ($row['status'] === 'SUCCESS');
                                $icon = $is_success ? '<i class="iclass fa-solid fa-circle-check"></i>' : '<i class="iclass fa-solid fa-circle-xmark"></i>';
                                $statusClass = $is_success ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 border-rose-500/20';
                                $date = date('d M Y, H:i', strtotime($row['transaction_date']));
                                $phone = !empty($row['phone_number']) ? htmlspecialchars($row['phone_number']) : 'N/A';
                                
                                if ($is_success) {
                                    $reference = htmlspecialchars($row['mpesa_receipt_number'] ?? 'SUCCESS');
                                    $reason = 'Payment Completed Successfully.';
                                } else {
                                    $reference = '-';
                                    $reason = !empty($row['mpesa_receipt_number']) ? htmlspecialchars($row['mpesa_receipt_number']) : 'Transaction Failed';
                                }
                                
                                echo "<tr class='hover:bg-slate-100/40 transition-colors'>
                                    <td class='p-3.5 pl-4 text-slate-500 whitespace-nowrap font-mono text-[11px]'>{$date}</td>
                                    <td class='p-3.5 font-mono text-slate-600'>{$phone}</td>
                                    <td class='p-3.5 font-mono text-teal-600 font-bold'>{$reference}</td>
                                    <td class='p-3.5 text-slate-500 max-w-[220px] truncate' title='{$reason}'>{$reason}</td>
                                    <td class='p-3.5 font-mono font-bold text-slate-900'>KES " . number_format($row['amount'], 2) . "</td>
                                    <td class='p-3.5 pr-4'>
                                        <span class='px-2 py-0.5 rounded-full text-[9px] font-semibold uppercase tracking-wide border {$statusClass} flex items-center gap-1.5 w-max'>
                                            {$icon} <span>{$row['status']}</span>
                                        </span>
                                    </td>
                                </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='p-8 text-center text-slate-500 text-xs'>
                                <i class='iclass fa-solid fa-receipt text-3xl text-slate-600 mb-2 block'></i>
                                No matching transactions found.
                            </td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <?php if ($total_pages > 1): ?>
                <div class="flex flex-col sm:flex-row items-center justify-between p-4 bg-slate-50/60 border-t border-slate-200/80 gap-3">
                    <p class="text-[11px] text-slate-500">
                        Showing <span class="text-slate-900 font-bold"><?= $total_rows > 0 ? $offset + 1 : 0 ?></span> to <span class="text-slate-900 font-bold"><?= min($offset + $limit, $total_rows) ?></span> of <span class="text-slate-900 font-bold"><?= $total_rows ?></span> entries
                    </p>
                    <div class="flex items-center gap-2">
                        <?php 
                            // Preserve existing GET parameters in pagination links
                            $queryParams = $_GET;
                            unset($queryParams['page']);
                            $queryString = http_build_query($queryParams);
                            $queryPrefix = $queryString ? '&' . $queryString : '';
                        ?>

                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?><?= $queryPrefix ?>" class="px-3 py-1.5 rounded-xl bg-slate-100/80 border border-slate-300/80 text-slate-700 text-xs font-bold hover:bg-slate-200 hover:text-slate-900 transition flex items-center gap-1.5">
                                <i class="iclass fa-solid fa-chevron-left text-[9px]"></i> Prev
                            </a>
                        <?php else: ?>
                            <span class="px-3 py-1.5 rounded-xl bg-white/50 border border-slate-200 text-slate-600 text-xs font-bold cursor-not-allowed flex items-center gap-1.5">
                                <i class="iclass fa-solid fa-chevron-left text-[9px]"></i> Prev
                            </span>
                        <?php endif; ?>

                        <span class="text-xs text-slate-500 px-2 font-medium">
                            Page <span class="text-slate-900 font-bold"><?= $page ?></span> of <span class="text-slate-900 font-bold"><?= $total_pages ?></span>
                        </span>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?><?= $queryPrefix ?>" class="px-3 py-1.5 rounded-xl bg-slate-100/80 border border-slate-300/80 text-slate-700 text-xs font-bold hover:bg-slate-200 hover:text-slate-900 transition flex items-center gap-1.5">
                                Next <i class="iclass fa-solid fa-chevron-right text-[9px]"></i>
                            </a>
                        <?php else: ?>
                            <span class="px-3 py-1.5 rounded-xl bg-white/50 border border-slate-200 text-slate-600 text-xs font-bold cursor-not-allowed flex items-center gap-1.5">
                                Next <i class="iclass fa-solid fa-chevron-right text-[9px]"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/frame/footer.php'; ?>
</body>
</html>