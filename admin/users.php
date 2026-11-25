<?php
// /admin/users.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$dbPaths = [
    __DIR__ . '/database_config/db_stream.php',
    __DIR__ . '/database_config/db.php',
    __DIR__ . '/../database_config/db_stream.php',
    __DIR__ . '/../database_config/db.php',
    dirname(__DIR__) . '/database_config/db_stream.php',
    dirname(__DIR__) . '/database_config/db.php',
];

$dbLoaded = false;

foreach ($dbPaths as $dbPath) {
    if (is_file($dbPath)) {
        require_once $dbPath;
        $dbLoaded = true;
        break;
    }
}

if (!$dbLoaded || !isset($conn) || $conn->connect_error) {
    http_response_code(500);
    exit('Error: Database connection could not be established.');
}

require_once __DIR__ . '/../lib/notifications.php';

// Unique name avoids collisions with functions declared by admin frames.
if (!function_exists('adminUsersEscape')) {
    function adminUsersEscape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('adminUsersBindParams')) {
    function adminUsersBindParams(mysqli_stmt $stmt, string $types, array &$params): void
    {
        if ($types === '') {
            return;
        }

        $references = [$types];

        foreach ($params as $key => &$value) {
            $references[] = &$value;
        }

        call_user_func_array([$stmt, 'bind_param'], $references);
    }
}

// CSRF token for account status changes.
if (empty($_SESSION['users_csrf_token'])) {
    $_SESSION['users_csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['users_csrf_token'];

// Handle AJAX status change.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'toggle_status'
) {
    header('Content-Type: application/json; charset=utf-8');

    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $userId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);

    if (!hash_equals($csrfToken, $postedToken)) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid security token. Refresh the page and try again.',
        ]);
        exit();
    }

    if (!$userId || $userId < 1) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid user selected.',
        ]);
        exit();
    }

    $updateStmt = $conn->prepare("
        UPDATE users
        SET status = CASE
            WHEN LOWER(status) = 'active' THEN 'suspended'
            ELSE 'active'
        END
        WHERE id = ?
        LIMIT 1
    ");

    if (!$updateStmt) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Could not prepare the status update.',
        ]);
        exit();
    }

    $updateStmt->bind_param('i', $userId);
    $updated = $updateStmt->execute();
    $changedRows = $updateStmt->affected_rows;
    $updateStmt->close();

    if ($updated && $changedRows > 0) {
        av_notify_lifecycle((string)$targetUser['email'], (string)$targetUser['username'], $newStatus === 'suspended' ? 'Your AvenPay account has been suspended' : 'Your AvenPay account has been reactivated', $newStatus === 'suspended' ? 'Account suspended' : 'Account reactivated', $newStatus === 'suspended' ? 'An administrator has suspended access to your AvenPay account. Please contact support if you need assistance.' : 'An administrator has restored access to your AvenPay account.', ['Account status' => ucfirst($newStatus), 'Date' => date('d M Y H:i:s T')], 'security', 'Contact AvenPay Support', 'https://avenpay.co.ke/contact');
        echo json_encode([
            'status' => 'success',
            'message' => 'User status updated.',
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'User was not found or their status did not change.',
        ]);
    }

    exit();
}

// Filters and pagination.
$statusFilter = strtolower(trim((string) ($_GET['status'] ?? '')));
if (!in_array($statusFilter, ['', 'active', 'suspended'], true)) {
    $statusFilter = '';
}

$searchQuery = trim((string) ($_GET['search'] ?? ''));

$allowedLimits = [10, 25, 50, 100];
$limit = (int) ($_GET['limit'] ?? 10);

if (!in_array($limit, $allowedLimits, true)) {
    $limit = 10;
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Build user filters.
$whereParts = [];
$whereTypes = '';
$whereParams = [];

if ($statusFilter !== '') {
    $whereParts[] = 'LOWER(u.status) = ?';
    $whereTypes .= 's';
    $whereParams[] = $statusFilter;
}

if ($searchQuery !== '') {
    $whereParts[] = '(u.username LIKE ? OR u.email LIKE ? OR u.phone_number LIKE ?)';
    $whereTypes .= 'sss';

    $searchLike = '%' . $searchQuery . '%';
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
    $whereParams[] = $searchLike;
}

$whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

// Status counts.
$metrics = [
    'total_users' => 0,
    'active_users' => 0,
    'suspended_users' => 0,
];

$metricsResult = $conn->query("
    SELECT
        COUNT(*) AS total_users,
        COALESCE(SUM(CASE WHEN LOWER(status) = 'active' THEN 1 ELSE 0 END), 0) AS active_users,
        COALESCE(SUM(CASE WHEN LOWER(status) = 'suspended' THEN 1 ELSE 0 END), 0) AS suspended_users
    FROM users
");

if ($metricsResult) {
    $metrics = $metricsResult->fetch_assoc() ?: $metrics;
}

// Count matching users.
$countSql = "SELECT COUNT(*) AS total FROM users u $whereSql";
$countStmt = $conn->prepare($countSql);
$totalRows = 0;

if ($countStmt) {
    $countParams = $whereParams;
    adminUsersBindParams($countStmt, $whereTypes, $countParams);
    $countStmt->execute();

    $countRow = $countStmt->get_result()->fetch_assoc();
    $totalRows = (int) ($countRow['total'] ?? 0);
    $countStmt->close();
}

$totalPages = max(1, (int) ceil($totalRows / $limit));

if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// Join each user to their latest transaction and today's successful pay-in total.
// Users with the most recent transaction activity appear first.
$users = [];

$userSql = "
    SELECT
        u.id,
        u.username,
        u.email,
        u.phone_number,
        u.status,
        u.created_at,
        u.b2c_wallet_balance,
        u.total_b2c_withdrawn,
        activity.latest_activity,
        COALESCE(activity.successful_payins_today, 0) AS successful_payins_today
    FROM users u
    LEFT JOIN (
        SELECT
            user_id,
            MAX(transaction_date) AS latest_activity,
            SUM(
                CASE
                    WHEN DATE(transaction_date) = CURDATE()
                     AND LOWER(status) IN ('success', 'completed')
                    THEN amount
                    ELSE 0
                END
            ) AS successful_payins_today
        FROM mpesa_transactions
        GROUP BY user_id
    ) activity ON activity.user_id = u.id
    $whereSql
    ORDER BY
        (activity.latest_activity IS NULL) ASC,
        activity.latest_activity DESC,
        u.id DESC
    LIMIT ? OFFSET ?
";

$userStmt = $conn->prepare($userSql);

if (!$userStmt) {
    error_log('Users query prepare failed: ' . $conn->error);
} else {
    $userTypes = $whereTypes . 'ii';
    $userParams = $whereParams;
    $userParams[] = $limit;
    $userParams[] = $offset;

    adminUsersBindParams($userStmt, $userTypes, $userParams);
    $userStmt->execute();

    $userResult = $userStmt->get_result();

    while ($row = $userResult->fetch_assoc()) {
        $users[] = $row;
    }

    $userStmt->close();
}

$page_title = 'Users Management';
require_once __DIR__ . '/admin-frame/header.php';
?>

<div class="space-y-6 pb-10">
    <div id="alert-container"></div>

    <!-- Page heading -->
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200/80 dark:border-white/10 rounded p-5 shadow-xs flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h1 class="text-base font-bold text-slate-800 dark:text-slate-100">User Accounts</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                Users are ordered by latest transaction activity. Balances and pay-ins are shown per user.
            </p>
        </div>

        <div class="px-3.5 py-2 bg-slate-50 dark:bg-slate-800/60 border border-slate-200/60 dark:border-slate-700/60 rounded">
            <span class="text-[11px] text-slate-400 font-medium">Matching Accounts: </span>
            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">
                <?= number_format($totalRows) ?>
            </span>
        </div>
    </div>

    <!-- Account counts -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div class="bg-white dark:bg-[#0f172a] border border-slate-200/80 dark:border-white/10 rounded p-4 shadow-xs">
            <div class="text-[11px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400">
                All Accounts
            </div>
            <div class="mt-2 text-xl font-black text-slate-800 dark:text-slate-100">
                <?= number_format((int) $metrics['total_users']) ?>
            </div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] border border-emerald-500/20 rounded p-4 shadow-xs">
            <div class="text-[11px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400">
                Active Accounts
            </div>
            <div class="mt-2 text-xl font-black text-emerald-600 dark:text-emerald-400">
                <?= number_format((int) $metrics['active_users']) ?>
            </div>
        </div>

        <div class="bg-white dark:bg-[#0f172a] border border-rose-500/20 rounded p-4 shadow-xs">
            <div class="text-[11px] uppercase tracking-wider font-bold text-slate-500 dark:text-slate-400">
                Suspended Accounts
            </div>
            <div class="mt-2 text-xl font-black text-rose-600 dark:text-rose-400">
                <?= number_format((int) $metrics['suspended_users']) ?>
            </div>
        </div>
    </div>

    <!-- Filters and search -->
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200/80 dark:border-white/10 rounded p-4 shadow-xs">
        <form method="GET" action="<?= adminUsersEscape(basename($_SERVER['PHP_SELF'])) ?>"
              class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">

            <div class="flex flex-wrap items-center gap-2">
                <label for="status" class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                    Account status:
                </label>

                <select name="status" id="status"
                        class="px-3 py-2 text-xs font-semibold rounded border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-teal-500">
                    <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>
                        All accounts (<?= number_format((int) $metrics['total_users']) ?>)
                    </option>
                    <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>
                        Active (<?= number_format((int) $metrics['active_users']) ?>)
                    </option>
                    <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>
                        Suspended (<?= number_format((int) $metrics['suspended_users']) ?>)
                    </option>
                </select>

                <label for="limit" class="ml-1 text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">
                    Show:
                </label>

                <select name="limit" id="limit"
                        class="px-3 py-2 text-xs font-semibold rounded border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-teal-500">
                    <?php foreach ($allowedLimits as $allowedLimit): ?>
                        <option value="<?= $allowedLimit ?>" <?= $limit === $allowedLimit ? 'selected' : '' ?>>
                            <?= $allowedLimit ?> per page
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col sm:flex-row gap-2">
                <input
                    type="search"
                    name="search"
                    value="<?= adminUsersEscape($searchQuery) ?>"
                    placeholder="Search username, email, or phone..."
                    class="w-full sm:w-72 px-3 py-2 text-xs border border-slate-200 dark:border-slate-700 rounded bg-slate-50 dark:bg-slate-900 text-slate-900 dark:text-slate-100 focus:outline-none focus:ring-1 focus:ring-teal-500"
                >

                <button type="submit"
                        class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold rounded transition-colors">
                    Apply
                </button>

                <?php if ($searchQuery !== '' || $statusFilter !== '' || $limit !== 10): ?>
                    <a href="<?= adminUsersEscape(basename($_SERVER['PHP_SELF'])) ?>"
                       class="px-4 py-2 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 text-xs font-semibold rounded text-center transition-colors">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Users table -->
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200/80 dark:border-white/10 rounded shadow-xs overflow-hidden">
        <div class="overflow-x-auto w-full">
            <table class="w-full min-w-[1250px] text-left border-collapse">
                <thead class="bg-slate-50/70 dark:bg-slate-900/50 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Rank</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">User Account</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Phone</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">B2C Balance</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Withdrawn</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Successful Pay-ins Today</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Latest Activity</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Joined</th>
                        <th class="px-4 py-3 text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Action</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                    <?php if ($users): ?>
                        <?php foreach ($users as $index => $user): ?>
                            <?php
                            $isActive = strtolower((string) $user['status']) === 'active';
                            $rank = $offset + $index + 1;

                            $joined = '—';
                            if (!empty($user['created_at'])) {
                                $joinedTimestamp = strtotime((string) $user['created_at']);
                                if ($joinedTimestamp !== false) {
                                    $joined = date('d M Y', $joinedTimestamp);
                                }
                            }

                            $latestActivity = 'No activity';
                            if (!empty($user['latest_activity'])) {
                                $activityTimestamp = strtotime((string) $user['latest_activity']);
                                if ($activityTimestamp !== false) {
                                    $latestActivity = date('d M Y, H:i', $activityTimestamp);
                                }
                            }
                            ?>

                            <tr id="user-row-<?= (int) $user['id'] ?>"
                                class="hover:bg-slate-50/60 dark:hover:bg-slate-800/40 transition-colors">
                                <td class="px-4 py-3 align-middle">
                                    <span class="inline-flex min-w-7 justify-center rounded bg-slate-100 dark:bg-slate-800 px-2 py-1 font-bold text-slate-500 dark:text-slate-300">
                                        <?= $rank ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">
                                        <?= adminUsersEscape($user['username']) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        <?= adminUsersEscape($user['email']) ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5">
                                        User #<?= (int) $user['id'] ?>
                                    </div>
                                </td>

                                <td class="px-4 py-3 align-middle text-slate-600 dark:text-slate-300">
                                    <?= adminUsersEscape($user['phone_number'] ?? '—') ?>
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <span class="font-black text-teal-700 dark:text-teal-300">
                                        KES <?= number_format((float) ($user['b2c_wallet_balance'] ?? 0), 0) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <span class="font-bold text-teal-700 dark:text-teal-300">
                                        KES <?= number_format((float) ($user['total_b2c_withdrawn'] ?? 0), 0) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <span class="font-bold text-emerald-700 dark:text-emerald-400">
                                        KES <?= number_format((float) $user['successful_payins_today'], 0) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-3 align-middle text-slate-500 dark:text-slate-400">
                                    <?= adminUsersEscape($latestActivity) ?>
                                </td>

                                <td class="px-4 py-3 align-middle">
                                    <?php if ($isActive): ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[10px] font-bold text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            Active
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/10 px-2.5 py-1 text-[10px] font-bold text-rose-700 dark:text-rose-400 border border-rose-500/20">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            Suspended
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-3 align-middle text-slate-500 dark:text-slate-400">
                                    <?= adminUsersEscape($joined) ?>
                                </td>

                                <td class="px-4 py-3 align-middle text-right">
                                    <button
                                        type="button"
                                        onclick="toggleUserStatus(<?= (int) $user['id'] ?>, '<?= $isActive ? 'suspend' : 'activate' ?>')"
                                        class="inline-flex items-center gap-1.5 rounded px-3 py-1.5 text-[11px] font-bold border transition-colors <?= $isActive
                                            ? 'bg-rose-50 dark:bg-rose-500/10 hover:bg-rose-100 dark:hover:bg-rose-500/20 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-500/20'
                                            : 'bg-emerald-50 dark:bg-emerald-500/10 hover:bg-emerald-100 dark:hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-500/20' ?>"
                                    >
                                        <i class="fa-solid <?= $isActive ? 'fa-ban' : 'fa-circle-check' ?>"></i>
                                        <?= $isActive ? 'Suspend' : 'Activate' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="px-4 py-10 text-center text-slate-400 font-medium">
                                No user accounts found for these filters.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalRows > 0): ?>
            <?php
            $paginationParams = $_GET;
            unset($paginationParams['page']);
            $queryBase = http_build_query($paginationParams);
            $queryBase = $queryBase !== '' ? '&' . $queryBase : '';
            ?>

            <div class="px-4 py-3 bg-slate-50/50 dark:bg-slate-900/30 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="text-xs text-slate-500 dark:text-slate-400">
                    Showing
                    <span class="font-bold text-slate-700 dark:text-slate-200"><?= $offset + 1 ?></span>
                    to
                    <span class="font-bold text-slate-700 dark:text-slate-200"><?= min($offset + $limit, $totalRows) ?></span>
                    of
                    <span class="font-bold text-slate-700 dark:text-slate-200"><?= number_format($totalRows) ?></span>
                    users
                </div>

                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $queryBase ?>"
                           class="px-3 py-1.5 text-xs font-semibold rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);

                    for ($pageNumber = $startPage; $pageNumber <= $endPage; $pageNumber++):
                    ?>
                        <a href="?page=<?= $pageNumber ?><?= $queryBase ?>"
                           class="px-3 py-1.5 text-xs font-semibold rounded transition-colors <?= $pageNumber === $page
                                ? 'bg-teal-600 text-white shadow-xs'
                                : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' ?>">
                            <?= $pageNumber ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $queryBase ?>"
                           class="px-3 py-1.5 text-xs font-semibold rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
const usersCsrfToken = <?= json_encode($csrfToken) ?>;

function showUsersAlert(type, message) {
    const container = document.getElementById('alert-container');
    const isError = type === 'error';

    container.innerHTML = '';

    const banner = document.createElement('div');
    banner.className = isError
        ? 'p-4 rounded border text-xs font-semibold flex items-center justify-between bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-300'
        : 'p-4 rounded border text-xs font-semibold flex items-center justify-between bg-emerald-500/10 border-emerald-500/30 text-emerald-700 dark:text-emerald-300';

    const messageEl = document.createElement('span');
    messageEl.textContent = message;

    const closeButton = document.createElement('button');
    closeButton.type = 'button';
    closeButton.className = 'ml-4 opacity-70 hover:opacity-100';
    closeButton.textContent = '×';
    closeButton.addEventListener('click', () => banner.remove());

    banner.append(messageEl, closeButton);
    container.appendChild(banner);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

async function toggleUserStatus(userId, action) {
    const actionLabel = action === 'suspend' ? 'suspend' : 'activate';

    if (!confirm(`Are you sure you want to ${actionLabel} this user?`)) {
        return;
    }

    const formData = new URLSearchParams();
    formData.set('action', 'toggle_status');
    formData.set('user_id', String(userId));
    formData.set('csrf_token', usersCsrfToken);

    try {
        const response = await fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData.toString()
        });

        const data = await response.json();

        if (!response.ok || data.status !== 'success') {
            showUsersAlert('error', data.message || 'Could not update user status.');
            return;
        }

        showUsersAlert('success', data.message);
        window.setTimeout(() => window.location.reload(), 500);
    } catch (error) {
        showUsersAlert('error', 'Request failed. Refresh the page and try again.');
    }
}
</script>

<?php require_once __DIR__ . '/admin-frame/footer.php'; ?>