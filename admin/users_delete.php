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

// 3. Handle On-Page AJAX POST Actions (Delete User, Suspend/Active User Status Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // --- ACTION: Toggle User Status (Active / Suspended) ---
    if ($_POST['action'] === 'toggle_user_status') {
        $user_id = (int)$_POST['id'];
        $new_status = trim($_POST['status']); // e.g., 'active' or 'suspended'
        
        if (!in_array($new_status, ['active', 'suspended'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid status parameter.']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? LIMIT 1");
        $stmt->bind_param("si", $new_status, $user_id);

        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => "User #{$user_id} status updated to " . strtoupper($new_status) . ".",
                'new_status' => $new_status
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update user status.']);
        }
        $stmt->close();
        exit();
    }

    // --- ACTION: Delete User and Cascade Delete Associated Settlement Accounts ---
    if ($_POST['action'] === 'delete_user') {
        $delete_id = (int)$_POST['delete_id'];

        if ($delete_id === (int)$_SESSION['admin_id']) {
            echo json_encode(['status' => 'error', 'message' => 'Operation denied: You cannot delete your own admin account.']);
            exit();
        }

        $conn->begin_transaction();

        try {
            // 1. Delete associated settlements first
            $del_settlements = $conn->prepare("DELETE FROM settlements WHERE user_id = ?");
            $del_settlements->bind_param("i", $delete_id);
            $del_settlements->execute();
            $del_settlements->close();

            // 2. Delete the user account record
            $del_user = $conn->prepare("DELETE FROM users WHERE id = ? LIMIT 1");
            $del_user->bind_param("i", $delete_id);
            $del_user->execute();
            $del_user->close();

            $conn->commit();

            echo json_encode([
                'status' => 'success',
                'message' => "User account #{$delete_id} and all associated settlement accounts were successfully deleted.",
                'id' => $delete_id
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['status' => 'error', 'message' => 'Failed to delete user and records: ' . $e->getMessage()]);
        }
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

// 5. Handle Filters, Search, Limit, and Pagination for Users
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$allowed_limits = [10, 25, 50, 100];
if (!in_array($limit, $allowed_limits)) {
    $limit = 10;
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

// Base condition builder for users table
$where_sql = "WHERE 1=1";
$types = "";
$params = [];

if ($status_filter === 'active') {
    $where_sql .= " AND LOWER(status) = 'active'";
} elseif ($status_filter === 'suspended') {
    $where_sql .= " AND LOWER(status) = 'suspended'";
}

if (!empty($search_query)) {
    $where_sql .= " AND (username LIKE ? OR email LIKE ? OR phone_number LIKE ?)";
    $types .= "sss";
    $like_term = '%' . $search_query . '%';
    array_push($params, $like_term, $like_term, $like_term);
}

// Get Total Count for Pagination
$count_query = "SELECT COUNT(*) as total FROM users $where_sql";
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

// Fetch Paginated User Records with settlement counts via subquery
$query = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM settlements s WHERE s.user_id = u.id) as settlements_count 
    FROM users u 
    $where_sql 
    ORDER BY u.created_at DESC 
    LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($query);

$query_types = $types . "ii";
$query_params = array_merge($params, [$limit, $offset]);

$stmt->bind_param($query_types, ...$query_params);
$stmt->execute();
$result = $stmt->get_result();
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

// Fetch summary metrics for user statuses
$metrics_query = "
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN LOWER(status) = 'active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN LOWER(status) = 'suspended' THEN 1 ELSE 0 END) as suspended_count
    FROM users
";
$metrics_res = $conn->query($metrics_query);
$metrics = $metrics_res ? $metrics_res->fetch_assoc() : ['total_count' => 0, 'active_count' => 0, 'suspended_count' => 0];

$page_title = "User Directory Management";
include __DIR__ . '/admin-frame/header.php';
?>

<div class="space-y-6 pb-10">
    <!-- Dynamic Notification Banner Area -->
    <div id="alert-container"></div>

    <!-- Header Banner -->
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200/80 dark:border-white/10 rounded p-5 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-base font-bold text-slate-800 dark:text-slate-100">User Directory & Status Management</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Manage registered user accounts, toggle suspension status with an interactive toggle switch, trespass accounts, or purge users along with their linked settlements.
            </p>
        </div>
        <div class="flex items-center">
            <div class="px-3.5 py-1.5 bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 rounded">
                <span class="text-[11px] text-slate-400 font-medium">Total Users: </span>
                <span id="total-users-count" class="text-xs font-bold text-slate-700 dark:text-slate-200"><?= number_format($metrics['total_count']) ?></span>
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
                    <option value="">All Users (<?= number_format($metrics['total_count']) ?>)</option>
                    <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active (<?= number_format($metrics['active_count']) ?>)</option>
                    <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended (<?= number_format($metrics['suspended_count']) ?>)</option>
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
                <input type="text" name="search" value="<?= htmlspecialchars($search_query) ?>" placeholder="Username, email, phone..." class="w-full pl-9 pr-3 py-2 text-xs border border-slate-200 dark:border-slate-700 rounded bg-slate-50/50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-teal-500">
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
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">User Identity</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Contact Phone</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Settlements Count</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status & Toggle</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-xs" id="users-table-body">
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $u): ?>
                            <?php 
                                $user_status = strtolower($u['status'] ?? 'active');
                                $isActive = ($user_status === 'active');
                            ?>
                            <tr id="user-row-<?= $u['id'] ?>" class="hover:bg-slate-50/50 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3 font-medium text-slate-700 dark:text-slate-300 align-middle">#<?= htmlspecialchars($u['id']) ?></td>
                                <td class="px-4 py-3 align-middle">
                                    <div class="font-bold text-slate-900 dark:text-slate-100"><?= htmlspecialchars($u['username']) ?></div>
                                    <div class="text-[10px] text-slate-400 mt-0.5"><?= htmlspecialchars($u['email']) ?></div>
                                </td>
                                <td class="px-4 py-3 font-mono text-slate-600 dark:text-slate-300 align-middle">
                                    <?= htmlspecialchars($u['phone_number'] ?? 'Not Provided') ?>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <span class="px-2.5 py-1 bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 rounded font-bold text-[11px]">
                                        <?= (int)$u['settlements_count'] ?> Account<?= (int)$u['settlements_count'] == 1 ? '' : 's' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-middle">
                                    <button type="button" onclick="toggleUserStatus(<?= $u['id'] ?>, <?= $isActive ? 1 : 0 ?>)" 
                                            class="inline-flex items-center justify-start gap-1.5 w-32 px-2.5 py-1 rounded-full text-[10px] font-bold cursor-pointer transition-all border shadow-xs <?= $isActive ? 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border-emerald-500/30' : 'bg-rose-500/10 hover:bg-rose-500/20 text-rose-700 dark:text-rose-400 border-rose-500/30' ?>"
                                            title="Click to toggle Active/Suspended">
                                        <i class="iclass fa-solid <?= $isActive ? 'fa-toggle-on text-emerald-600' : 'fa-toggle-off text-rose-600' ?> text-xs shrink-0 w-3 text-center"></i>
                                        <span><?= ucfirst($user_status) ?></span>
                                    </button>
                                </td>
                                <td class="px-4 py-3 align-middle text-right">
                                    <div class="inline-flex items-center gap-1.5 justify-end">
                                        <a href="?action=impersonate&user_id=<?= $u['id'] ?>" onclick="return confirm('Are you sure you want to trespass into user #<?= $u['id'] ?> dashboard?')" class="px-2.5 py-1.5 bg-teal-50 dark:bg-teal-500/10 hover:bg-teal-100 text-teal-600 dark:text-teal-400 border border-teal-200 dark:border-teal-500/20 rounded text-[11px] font-bold transition-colors" title="Trespass User Dashboard">
                                            <i class="iclass fa-solid fa-user-secret text-[10px]"></i>
                                        </a>
                                        <button type="button" onclick="deleteUser(<?= $u['id'] ?>, <?= (int)$u['settlements_count'] ?>)" class="px-2.5 py-1.5 bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-100 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-500/20 rounded text-[11px] font-bold transition-colors cursor-pointer" title="Delete User & Cascade Settlements">
                                            <i class="iclass fa-solid fa-trash-can text-[10px]"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="no-records-row">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400 font-medium">
                                No user accounts found matching your filter criteria.
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
// Toggle User Status (Active / Suspended) via AJAX with toggle switch styling
function toggleUserStatus(id, currentIsActive) {
    const newStatus = currentIsActive === 1 ? 'suspended' : 'active';
    const formData = new URLSearchParams();
    formData.append('action', 'toggle_user_status');
    formData.append('id', id);
    formData.append('status', newStatus);

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
            showAlert('success', data.message);
            setTimeout(() => { location.reload(); }, 500);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error toggling user status:', error);
        showAlert('error', error.message);
    });
}

// Delete User Account and Cascade Delete Associated Settlement Accounts
function deleteUser(id, settlementsCount) {
    let warningMsg = 'Are you sure you want to delete user account #' + id + '?';
    if (settlementsCount > 0) {
        warningMsg += '\n\nWARNING: This user has ' + settlementsCount + ' linked settlement account(s) which will also be permanently deleted.';
    }

    if (!confirm(warningMsg)) {
        return;
    }

    const formData = new URLSearchParams();
    formData.append('action', 'delete_user');
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
            const row = document.getElementById('user-row-' + id);
            if (row) {
                row.remove();
            }

            const totalCountEl = document.getElementById('total-users-count');
            if (totalCountEl) {
                let currentCount = parseInt(totalCountEl.innerText.replace(/,/g, '')) || 1;
                totalCountEl.innerText = Math.max(0, currentCount - 1).toLocaleString();
            }

            showAlert('success', data.message);

            const tbody = document.getElementById('users-table-body');
            if (tbody && tbody.children.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400 font-medium">
                            No user accounts found matching your filter criteria.
                        </td>
                    </tr>
                `;
            }
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting user:', error);
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