<?php
// 1. Initialize session and enforce authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

// 2. Safe Database Connection Loader
$db_paths = [
    __DIR__ . '/database_config/db_stream.php',
    __DIR__ . '/database_config/db.php',
    'database_config/db_stream.php',
    'database_config/db.php',
    '../database_config/db_stream.php',
    '../database_config/db.php',
    './db_stream.php',
    './db.php',
    '../db_stream.php',
    '../db.php'
];

$db_loaded = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}

if (!$db_loaded || !isset($conn) || $conn->connect_error) {
    die("Error: Database connection could not be established.");
}

// 3. Handle Filters, Search, Limit, and Pagination
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$allowed_limits = [10, 25, 50, 100];
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Base condition builder
$where_sql = "WHERE 1=1";
$types = "";
$params = [];

if ($status_filter === 'completed' || $status_filter === 'success') {
    $where_sql .= " AND (LOWER(t.status) = 'completed' OR LOWER(t.status) = 'success')";
} elseif ($status_filter === 'cancelled') {
    $where_sql .= " AND (LOWER(t.status) = 'cancelled' OR t.mpesa_receipt_number LIKE '%1032%' OR LOWER(t.mpesa_receipt_number) LIKE '%cancel%' OR LOWER(t.status) LIKE '%cancel%')";
} elseif ($status_filter === 'failed') {
    $where_sql .= " AND LOWER(t.status) = 'failed' AND LOWER(t.status) != 'cancelled' AND t.mpesa_receipt_number NOT LIKE '%1032%' AND LOWER(t.mpesa_receipt_number) NOT LIKE '%cancel%'";
}

if (!empty($search_query)) {
    $where_sql .= " AND (t.mpesa_receipt_number LIKE ? OR t.checkout_request_id LIKE ? OR t.phone_number LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $types .= "sssss";
    $like_term = '%' . $search_query . '%';
    array_push($params, $like_term, $like_term, $like_term, $like_term, $like_term);
}

// Get Total Count for Pagination
$count_query = "SELECT COUNT(*) as total FROM mpesa_transactions t LEFT JOIN users u ON t.user_id = u.id $where_sql";
$count_stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_rows = $count_stmt->get_result()->fetch_assoc()['total'] ?? 0;
$total_pages = max(1, ceil($total_rows / $limit));
if ($page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}
$count_stmt->close();

// Fetch Paginated Records
$query = "SELECT t.*, u.username, u.email FROM mpesa_transactions t LEFT JOIN users u ON t.user_id = u.id $where_sql ORDER BY t.transaction_date DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);

$query_types = $types . "ii";
$query_params = array_merge($params, [$limit, $offset]);

$stmt->bind_param($query_types, ...$query_params);
$stmt->execute();
$result = $stmt->get_result();
$transactions = [];
while ($row = $result->fetch_assoc()) {
    $transactions[] = $row;
}
$stmt->close();

// Fetch summary metrics
$metrics_query = "
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN LOWER(status) = 'success' OR LOWER(status) = 'completed' THEN 1 ELSE 0 END) as success_count,
        SUM(CASE WHEN LOWER(status) = 'cancelled' OR mpesa_receipt_number LIKE '%1032%' OR LOWER(mpesa_receipt_number) LIKE '%cancel%' THEN 1 ELSE 0 END) as cancelled_count,
        SUM(CASE WHEN LOWER(status) = 'failed' AND LOWER(status) != 'cancelled' AND mpesa_receipt_number NOT LIKE '%1032%' THEN 1 ELSE 0 END) as failed_count
    FROM mpesa_transactions
";
$metrics_res = $conn->query($metrics_query);
$metrics = $metrics_res ? $metrics_res->fetch_assoc() : ['total_count' => 0, 'success_count' => 0, 'cancelled_count' => 0, 'failed_count' => 0];

$page_title = "M-Pesa Transactions";
include __DIR__ . '/admin-frame/header.php';
?>

<div class="space-y-6 pb-10">
    <!-- Header Banner -->
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200/80 dark:border-white/10 rounded p-5 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-base font-bold text-slate-800 dark:text-slate-100">M-Pesa Transaction Logs</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Monitor live payin receipts, user deposits, and payment statuses.
            </p>
        </div>
        <div class="flex items-center">
            <div class="px-3.5 py-1.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 rounded">
                <span class="text-[11px] text-slate-400 font-medium">Total Volume: </span>
                <span class="text-xs font-bold text-slate-700 dark:text-slate-200"><?= number_format($metrics['total_count']) ?></span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Controls Card Bar -->
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200/80 dark:border-white/10 rounded p-4 shadow-xs flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        
        <!-- Professional Dropdown Selection Pop Filter Form -->
        <form method="GET" action="transactions.php" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="limit" value="<?= $limit ?>">
            <?php if (!empty($search_query)): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
            <?php endif; ?>
            
            <label for="status" class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Filter Status:</label>
            <div class="relative">
                <select name="status" id="status" onchange="this.form.submit()" class="appearance-none px-3.5 py-2 pr-8 text-xs font-semibold rounded border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-teal-500 cursor-pointer shadow-xs">
                    <option value="">All Transactions (<?= number_format($metrics['total_count']) ?>)</option>
                    <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed (<?= number_format($metrics['success_count']) ?>)</option>
                    <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled (<?= number_format($metrics['cancelled_count']) ?>)</option>
                    <option value="failed" <?= $status_filter === 'failed' ? 'selected' : '' ?>>Failed (<?= number_format($metrics['failed_count']) ?>)</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400">
                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                </div>
            </div>
        </form>

        <!-- Search Bar and Per-Page Selector Form -->
        <form method="GET" action="transactions.php" class="flex flex-wrap items-center gap-2.5">
            <?php if (!empty($status_filter)): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
            <?php endif; ?>
            
            <div class="relative">
                <select name="limit" onchange="this.form.submit()" class="appearance-none px-3 py-2 pr-8 text-xs font-semibold rounded border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 text-slate-700 dark:text-slate-300 focus:outline-none focus:ring-1 focus:ring-teal-500 cursor-pointer shadow-xs">
                    <?php foreach ($allowed_limits as $l): ?>
                        <option value="<?= $l ?>" <?= $limit === $l ? 'selected' : '' ?>><?= $l ?> per page</option>
                    <?php endforeach; ?>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-slate-400">
                    <i class="fa-solid fa-chevron-down text-[10px]"></i>
                </div>
            </div>

            <div class="relative flex-1 sm:w-64">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </span>
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Receipt, phone, user..." class="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 dark:border-slate-700 rounded bg-slate-50/50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-teal-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded shadow-xs transition-colors shrink-0">Search</button>
            <?php if (!empty($search_query) || !empty($status_filter) || $limit !== 10): ?>
                <a href="transactions.php" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded transition-colors shrink-0">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Data Table Card Container -->
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200/80 dark:border-white/10 rounded shadow-xs overflow-hidden">
        <div class="overflow-x-auto w-full">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50/70 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">ID</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">User Account</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Phone / Reference</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Amount</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Date & Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                    <?php if (count($transactions) > 0): ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-300 align-middle">#<?= htmlspecialchars($tx['id']) ?></td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-bold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($tx['username'] ?? 'Guest / Unlinked') ?></div>
                                    <div class="text-[10px] text-slate-400 mt-0.5"><?= htmlspecialchars($tx['email'] ?? '') ?></div>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($tx['phone_number'] ?? 'N/A') ?></div>
                                    <div class="font-mono text-[10px] text-slate-400 mt-0.5"><?= htmlspecialchars($tx['mpesa_receipt_number'] ?? $tx['checkout_request_id'] ?? 'N/A') ?></div>
                                </td>
                                <td class="px-4 py-3 font-black text-slate-900 dark:text-slate-100 align-middle">KES <?= number_format($tx['amount'], 2) ?></td>
                                <td class="px-4 py-3 align-middle">
                                    <?php 
                                        $status = strtolower($tx['status']);
                                        $receipt = strtolower($tx['mpesa_receipt_number'] ?? '');
                                        $is_cancelled = ($status === 'cancelled' || strpos($receipt, '1032') !== false || strpos($receipt, 'cancel') !== false);
                                    ?>
                                    <?php if ($status === 'completed' || $status === 'success'): ?>
                                        <span class="inline-flex items-center justify-start gap-1.5 w-28 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                            <i class="fa-solid fa-check text-[9px] shrink-0 w-3 text-center"></i> Completed
                                        </span>
                                    <?php elseif ($is_cancelled): ?>
                                        <span class="inline-flex items-center justify-start gap-1.5 w-28 px-2.5 py-1 rounded-full border border-amber-500/30 text-[10px] font-semibold bg-amber-500/10 text-amber-700 dark:text-amber-400" title="Cancelled">
                                            <i class="fa-solid fa-ban text-[9px] shrink-0 w-3 text-center"></i> Cancelled
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center justify-start gap-1.5 w-28 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20">
                                            <i class="fa-solid fa-xmark text-[9px] shrink-0 w-3 text-center"></i> Failed
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400 font-medium align-middle"><?= htmlspecialchars($tx['transaction_date']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400 font-medium">
                                No transaction records found matching your filter parameters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination Bar -->
        <?php if ($total_pages > 1 || $total_rows > 0): ?>
            <div class="px-4 py-3 bg-slate-50/50 dark:bg-slate-900/30 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Showing <span class="font-bold text-slate-700 dark:text-slate-200"><?= $total_rows > 0 ? $offset + 1 : 0 ?></span> to <span class="font-bold text-slate-700 dark:text-slate-200"><?= min($offset + $limit, $total_rows) ?></span> out of <span class="font-bold text-slate-700 dark:text-slate-200"><?= number_format($total_rows) ?></span> results
                </div>
                
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="transactions.php?page=<?= $page - 1 ?>&limit=<?= $limit ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>" 
                           class="px-3 py-1 text-xs font-semibold rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition-colors">
                           Prev
                        </a>
                    <?php endif; ?>

                    <?php 
                        $start_p = max(1, $page - 2);
                        $end_p = min($total_pages, $page + 2);
                        for ($i = $start_p; $i <= $end_p; $i++): 
                    ?>
                        <a href="transactions.php?page=<?= $i ?>&limit=<?= $limit ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>" 
                           class="px-3 py-1 text-xs font-semibold rounded transition-colors <?= $i === $page ? 'bg-teal-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="transactions.php?page=<?= $page + 1 ?>&limit=<?= $limit ?><?= !empty($status_filter) ? '&status=' . urlencode($status_filter) : '' ?><?= !empty($search_query) ? '&search=' . urlencode($search_query) : '' ?>" 
                           class="px-3 py-1 text-xs font-semibold rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition-colors">
                           Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
include __DIR__ . '/admin-frame/footer.php';
?>