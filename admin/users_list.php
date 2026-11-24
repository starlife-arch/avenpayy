<?php
// 1. Initialize session and enforce admin authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
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

// 3. Handle On-Page AJAX POST Actions (Delete and Status Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // --- ACTION: Toggle Active Status ---
    if ($_POST['action'] === 'toggle_status') {
        $settlement_id = (int)$_POST['id'];
        $new_is_active = (int)$_POST['is_active']; // 1 or 0
        $new_status = ($new_is_active === 1) ? 'active' : 'inactive';

        $stmt = $conn->prepare("UPDATE settlements SET is_active = ?, status = ? WHERE id = ? LIMIT 1");
        $stmt->bind_param("isi", $new_is_active, $new_status, $settlement_id);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => "Settlement #{$settlement_id} status updated to " . strtoupper($new_status) . ".",
                'is_active' => $new_is_active,
                'status_text' => $new_status
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update account status.']);
        }
        $stmt->close();
        exit();
    }

    // --- ACTION: Delete Settlement Account ---
    if ($_POST['action'] === 'delete') {
        $delete_id = (int)$_POST['delete_id'];

        $del_stmt = $conn->prepare("DELETE FROM settlements WHERE id = ? LIMIT 1");
        $del_stmt->bind_param("i", $delete_id);

        if ($del_stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => "Settlement account #{$delete_id} was successfully deleted.",
                'id' => $delete_id
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete settlement account.']);
        }
        $del_stmt->close();
        exit();
    }
}

// 4. Handle Impersonate / Trespass Action via Explicit GET Parameter (?action=impersonate&user_id=X)
if (isset($_GET['action']) && $_GET['action'] === 'impersonate' && isset($_GET['user_id'])) {
    $target_user_id = (int)$_GET['user_id'];
    
    $chk_stmt = $conn->prepare("SELECT id, username, email FROM users WHERE id = ? LIMIT 1");
    $chk_stmt->bind_param("i", $target_user_id);
    $chk_stmt->execute();
    $target_user = $chk_stmt->get_result()->fetch_assoc();
    $chk_stmt->close();
    
    if ($target_user) {
        $_SESSION['user_id'] = $target_user['id'];
        $_SESSION['username'] = $target_user['username'];
        $_SESSION['email'] = $target_user['email'];
        $_SESSION['logged_in'] = true;
        $_SESSION['is_impersonating'] = true;
        
        header("Location: /dashboard.php");
        exit();
    }
}

// 5. Handle Filters, Search, Limit, and Pagination for Settlements
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$allowed_limits = [10, 25, 50, 100];
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Base condition builder joining settlements and users tables
$where_sql = "WHERE 1=1";
$types = "";
$params = [];

if ($status_filter === 'active') {
    $where_sql .= " AND (s.is_active = 1 AND (s.expiry_date IS NULL OR s.expiry_date >= NOW()))";
} elseif ($status_filter === 'inactive') {
    $where_sql .= " AND (s.is_active = 0 AND (s.expiry_date IS NULL OR s.expiry_date >= NOW()))";
} elseif ($status_filter === 'expired') {
    $where_sql .= " AND (LOWER(s.status) = 'expired' OR (s.expiry_date IS NOT NULL AND s.expiry_date < NOW()))";
}

if (!empty($search_query)) {
    $where_sql .= " AND (s.merchant_name LIKE ? OR s.shortcode LIKE ? OR s.account_number LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $types .= "sssss";
    $like_term = '%' . $search_query . '%';
    array_push($params, $like_term, $like_term, $like_term, $like_term, $like_term);
}

// Get Total Count for Pagination
$count_query = "SELECT COUNT(*) as total FROM settlements s LEFT JOIN users u ON s.user_id = u.id $where_sql";
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

// Fetch Paginated Settlement Records with Associated User Info
$query = "SELECT s.*, u.username, u.email, u.phone_number FROM settlements s LEFT JOIN users u ON s.user_id = u.id $where_sql ORDER BY s.created_at DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($query);

$query_types = $types . "ii";
$query_params = array_merge($params, [$limit, $offset]);

$stmt->bind_param($query_types, ...$query_params);
$stmt->execute();
$result = $stmt->get_result();
$settlements = [];
while ($row = $result->fetch_assoc()) {
    $settlements[] = $row;
}
$stmt->close();

// Fetch summary metrics for settlement statuses
$metrics_query = "
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN is_active = 1 AND (expiry_date IS NULL OR expiry_date >= NOW()) THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN is_active = 0 AND (expiry_date IS NULL OR expiry_date >= NOW()) THEN 1 ELSE 0 END) as inactive_count,
        SUM(CASE WHEN LOWER(status) = 'expired' OR (expiry_date IS NOT NULL AND expiry_date < NOW()) THEN 1 ELSE 0 END) as expired_count
    FROM settlements
";
$metrics_res = $conn->query($metrics_query);
$metrics = $metrics_res ? $metrics_res->fetch_assoc() : ['total_count' => 0, 'active_count' => 0, 'inactive_count' => 0, 'expired_count' => 0];

$page_title = "Settlement Accounts Management";
include __DIR__ . '/admin-frame/header.php';
?>

<div class="space-y-6 pb-10">
    <!-- Dynamic Notification Banner Area -->
    <div id="alert-container"></div>

    <!-- Header Banner -->
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200/80 dark:border-white/10 rounded p-5 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-base font-bold text-slate-800 dark:text-slate-100">Settlement Accounts Directory</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Monitor user settlement accounts, track expiry countdowns, delete accounts on-page, and access user dashboards via icons.
            </p>
        </div>
        <div class="flex items-center">
            <div class="px-3.5 py-1.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 rounded">
                <span class="text-[11px] text-slate-400 font-medium">Total Accounts: </span>
                <span id="total-accounts-count" class="text-xs font-bold text-slate-700 dark:text-slate-200"><?= number_format($metrics['total_count']) ?></span>
            </div>
        </div>
    </div>

    <!-- Filter & Search Controls Card Bar -->
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200/80 dark:border-white/10 rounded p-4 shadow-xs flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        
        <!-- Professional Dropdown Selection Filter Form -->
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="limit" value="<?= $limit ?>">
            <?php if (!empty($search_query)): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
            <?php endif; ?>
            
            <label for="status" class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Filter Status:</label>
            <div class="relative">
                <select name="status" id="status" onchange="this.form.submit()" class="appearance-none px-3.5 py-2 pr-8 text-xs font-semibold rounded border border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-teal-500 cursor-pointer shadow-xs">
                    <option value="">All Accounts (<?= number_format($metrics['total_count']) ?>)</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active (<?= number_format($metrics['active_count']) ?>)</option>
                    <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive (<?= number_format($metrics['inactive_count']) ?>)</option>
                    <option value="expired" <?= $status_filter === 'expired' ? 'selected' : '' ?>>Expired (<?= number_format($metrics['expired_count']) ?>)</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2.5 text-slate-400">
                    <i class="iclass fa-solid fa-chevron-down text-[10px]"></i>
                </div>
            </div>
        </form>

        <!-- Search Bar and Per-Page Selector Form -->
        <form method="GET" class="flex flex-wrap items-center gap-2.5">
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
                    <i class="iclass fa-solid fa-chevron-down text-[10px]"></i>
                </div>
            </div>

            <div class="relative flex-1 sm:w-64">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <i class="iclass fa-solid fa-magnifying-glass text-xs"></i>
                </span>
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Merchant, shortcode, user..." class="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 dark:border-slate-700 rounded bg-slate-50/50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-teal-500">
            </div>
            <button type="submit" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded shadow-xs transition-colors shrink-0">Search</button>
            <?php if (!empty($search_query) || !empty($status_filter) || $limit !== 10): ?>
                <a href="<?= htmlspecialchars(basename($_SERVER['PHP_SELF'])) ?>" class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded transition-colors shrink-0">Reset</a>
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
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Merchant / Details</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status & Toggle</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Expiry & Countdown</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-xs" id="settlements-table-body">
                    <?php if (count($settlements) > 0): ?>
                        <?php foreach ($settlements as $s): ?>
                            <?php 
                                $isActive = ($s['is_active'] == 1);
                                $raw_status = strtolower($s['status'] ?? '');
                                $exp_date = $s['expiry_date'] ?? null;
                                $is_expired = ($raw_status === 'expired' || ($exp_date && strtotime($exp_date) < time()));
                            ?>
                            <tr id="settlement-row-<?= $s['id'] ?>" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-300 align-middle">#<?= htmlspecialchars($s['id']) ?></td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-bold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($s['username'] ?? 'Unlinked') ?></div>
                                    <div class="text-[10px] text-slate-400 mt-0.5"><?= htmlspecialchars($s['email'] ?? '') ?></div>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-bold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($s['merchant_name']) ?></div>
                                    <div class="font-mono text-[10px] text-teal-600 dark:text-teal-400 mt-0.5">
                                        <?= ($s['account_number'] ? 'Acc: ' . htmlspecialchars($s['account_number']) : 'Till/Shortcode: ' . htmlspecialchars($s['shortcode'])) ?>
                                        <span class="text-slate-400 uppercase ml-1">(<?= htmlspecialchars($s['type']) ?>)</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <?php if ($is_expired): ?>
                                        <span class="inline-flex items-center justify-start gap-1.5 w-32 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20">
                                            <i class="iclass fa-solid fa-triangle-exclamation text-[9px] shrink-0 w-3 text-center"></i> Expired
                                        </span>
                                    <?php else: ?>
                                        <button type="button" onclick="toggleSettlementStatus(<?= $s['id'] ?>, <?= $isActive ? 1 : 0 ?>)" 
                                                class="inline-flex items-center justify-start gap-1.5 w-32 px-2.5 py-1 rounded-full text-[10px] font-bold cursor-pointer transition-all border shadow-xs <?= $isActive ? 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border-emerald-500/30' : 'bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-400 border-amber-500/30' ?>"
                                                title="Click to toggle Active/Inactive">
                                            <i class="iclass fa-solid <?= $isActive ? 'fa-toggle-on text-emerald-600' : 'fa-toggle-off text-amber-600' ?> text-xs shrink-0 w-3 text-center"></i>
                                            <span><?= $isActive ? 'Active' : 'Inactive' ?></span>
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-slate-600 dark:text-slate-300 align-middle">
                                    <?php if ($is_expired || !$isActive): ?>
                                        <span class="text-slate-400 dark:text-slate-500 font-semibold"><i class="iclass fa-solid fa-ban mr-1"></i>No active plan</span>
                                    <?php elseif (!empty($s['expiry_date'])): ?>
                                        <?php 
                                            $now = time();
                                            $expiry_timestamp = strtotime($s['expiry_date']);
                                            $diff_seconds = $expiry_timestamp - $now;
                                            $days_remaining = ceil($diff_seconds / (60 * 60 * 24));
                                        ?>
                                        <div class="font-semibold text-slate-800 dark:text-slate-200"><?= htmlspecialchars($s['expiry_date']) ?></div>
                                        <div class="text-[10px] mt-0.5">
                                            <?php if ($days_remaining > 0): ?>
                                                <span class="text-emerald-600 dark:text-emerald-400 font-bold"><i class="iclass fa-solid fa-hourglass-half mr-1"></i><?= $days_remaining ?> day<?= $days_remaining == 1 ? '' : 's' ?> remaining</span>
                                            <?php elseif ($days_remaining === 0): ?>
                                                <span class="text-amber-600 dark:text-amber-400 font-bold"><i class="iclass fa-solid fa-triangle-exclamation mr-1"></i>Expires today</span>
                                            <?php else: ?>
                                                <span class="text-rose-600 dark:text-rose-400 font-bold"><i class="iclass fa-solid fa-clock-rotate-left mr-1"></i>Expired <?= abs($days_remaining) ?> day<?= abs($days_remaining) == 1 ? '' : 's' ?> ago</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-emerald-600 dark:text-emerald-400 font-semibold"><i class="iclass fa-solid fa-infinity mr-1"></i>Lifetime / No Expiry</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 align-middle text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <?php if (!empty($s['user_id'])): ?>
                                            <a href="?action=impersonate&user_id=<?= $s['user_id'] ?>" onclick="return confirm('Are you sure you want to trespass into user #<?= $s['user_id'] ?> dashboard?')" class="px-2.5 py-1.5 bg-teal-50 dark:bg-teal-500/10 hover:bg-teal-100 text-teal-600 dark:text-teal-400 border border-teal-200 dark:border-teal-500/20 rounded text-[11px] font-bold transition-colors" title="Trespass User Dashboard">
                                                <i class="iclass fa-solid fa-user-secret text-[10px]"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" onclick="deleteSettlement(<?= $s['id'] ?>)" class="px-2.5 py-1.5 bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-100 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-500/20 rounded text-[11px] font-bold transition-colors cursor-pointer" title="Delete Account">
                                            <i class="iclass fa-solid fa-trash-can text-[10px]"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="no-records-row">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400 font-medium">
                                No settlement accounts found matching your filter parameters.
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
                    <?php 
                        $pagination_query_params = $_GET;
                        unset($pagination_query_params['page']);
                        $query_string_base = http_build_query($pagination_query_params);
                        $query_string_base = $query_string_base ? '&' . $query_string_base : '';
                    ?>
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $query_string_base ?>" 
                           class="px-3 py-1 text-xs font-semibold rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition-colors">
                            Prev
                        </a>
                    <?php endif; ?>

                    <?php 
                        $start_p = max(1, $page - 2);
                        $end_p = min($total_pages, $page + 2);
                        for ($i = $start_p; $i <= $end_p; $i++): 
                    ?>
                        <a href="?page=<?= $i ?><?= $query_string_base ?>" 
                           class="px-3 py-1 text-xs font-semibold rounded transition-colors <?= $i === $page ? 'bg-teal-600 text-white shadow-xs' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $query_string_base ?>" 
                           class="px-3 py-1 text-xs font-semibold rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 transition-colors">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Toggle Status via AJAX using window.location.pathname so it works dynamically regardless of file name
function toggleSettlementStatus(id, currentIsActive) {
    const newIsActive = currentIsActive === 1 ? 0 : 1;
    const formData = new URLSearchParams();
    formData.append('action', 'toggle_status');
    formData.append('id', id);
    formData.append('is_active', newIsActive);

    fetch(window.location.pathname, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData.toString()
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("Server returned non-JSON response (Session likely expired):", text);
            throw new Error("Session expired or server error. Please refresh the page.");
        }
    })
    .then(data => {
        if (data.status === 'success') {
            showAlert('success', data.message);
            setTimeout(() => { location.reload(); }, 500);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error toggling status:', error);
        showAlert('error', error.message);
    });
}

// Delete Settlement Account via AJAX dynamically posting to the current admin route
function deleteSettlement(id) {
    if (!confirm('Are you sure you want to delete settlement account #' + id + '?')) {
        return;
    }

    const formData = new URLSearchParams();
    formData.append('action', 'delete');
    formData.append('delete_id', id);

    fetch(window.location.pathname, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData.toString()
    })
    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error("Server returned non-JSON response:", text);
            throw new Error("Session expired or server error. Please refresh the page.");
        }
    })
    .then(data => {
        if (data.status === 'success') {
            const row = document.getElementById('settlement-row-' + id);
            if (row) {
                row.remove();
            }

            const totalCountEl = document.getElementById('total-accounts-count');
            if (totalCountEl) {
                let currentCount = parseInt(totalCountEl.innerText.replace(/,/g, '')) || 1;
                totalCountEl.innerText = Math.max(0, currentCount - 1).toLocaleString();
            }

            showAlert('success', data.message);

            const tbody = document.getElementById('settlements-table-body');
            if (tbody && tbody.children.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400 font-medium">
                            No settlement accounts found matching your filter parameters.
                        </td>
                    </tr>
                `;
            }
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting account:', error);
        showAlert('error', error.message);
    });
}

function showAlert(type, message) {
    const alertContainer = document.getElementById('alert-container');
    const isError = type === 'error';
    alertContainer.innerHTML = `
        <div class="p-4 rounded border text-xs font-semibold flex items-center justify-between ${isError ? 'bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-300' : 'bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300'} transition-all">
            <div class="flex items-center gap-2">
                <i class="iclass fa-solid ${isError ? 'fa-circle-exclamation' : 'fa-circle-check'}"></i>
                <span>${message}</span>
            </div>
            <button onclick="this.parentElement.remove();" class="text-current opacity-70 hover:opacity-100"><i class="iclass fa-solid fa-xmark"></i></button>
        </div>
    `;
}
</script>

<?php 
include __DIR__ . '/admin-frame/footer.php';
?>