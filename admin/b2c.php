<?php
// /admin/b2c_transactions.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/');
    exit();
}

// Database connection loader
$db_paths = [
    __DIR__ . '/../database_config/db.php',
    $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php',
    '../database_config/db.php',
    'database_config/db.php',
];

$db_loaded = false;

foreach ($db_paths as $path) {
    if (is_file($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}

if (
    !$db_loaded ||
    !isset($conn) ||
    !($conn instanceof mysqli)
) {
    die('Database connection failed.');
}

$page_title = 'B2C Withdrawals';

$search = trim((string) ($_GET['search'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 15;

$escape = static function ($value): string {
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
};

$totalRows = 0;
$totalPages = 0;
$searchTerm = '%' . addcslashes($search, '\\%_') . '%';

// Count records (including search on username and email)
if ($search !== '') {
    $countStmt = $conn->prepare("
        SELECT COUNT(t.id)
        FROM mpesa_b2c_transactions t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.receiver_phone LIKE ?
           OR t.conversation_id LIKE ?
           OR t.originator_conversation_id LIKE ?
           OR t.transaction_id LIKE ?
           OR t.receiver_name LIKE ?
           OR u.username LIKE ?
           OR u.email LIKE ?
    ");

    if (!$countStmt) {
        die('Unable to prepare withdrawal count query.');
    }

    $countStmt->bind_param(
        'sssssss',
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    );

    if (!$countStmt->execute()) {
        $countStmt->close();
        die('Unable to count withdrawal records.');
    }

    $countResult = $countStmt->get_result();
    $totalRows = (int) $countResult->fetch_row()[0];
    $countStmt->close();
} else {
    $countResult = $conn->query(
        'SELECT COUNT(*) FROM mpesa_b2c_transactions'
    );

    if (!$countResult) {
        die('Unable to count withdrawal records.');
    }

    $totalRows = (int) $countResult->fetch_row()[0];
    $countResult->free();
}

$totalPages = (int) ceil($totalRows / $limit);

if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $limit;

// Fetch withdrawals with User Details (username and email)
if ($search !== '') {
    $queryStmt = $conn->prepare("
        SELECT t.*, u.username, u.email
        FROM mpesa_b2c_transactions t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.receiver_phone LIKE ?
           OR t.conversation_id LIKE ?
           OR t.originator_conversation_id LIKE ?
           OR t.transaction_id LIKE ?
           OR t.receiver_name LIKE ?
           OR u.username LIKE ?
           OR u.email LIKE ?
        ORDER BY t.id DESC
        LIMIT ? OFFSET ?
    ");

    if (!$queryStmt) {
        die('Unable to prepare withdrawal query.');
    }

    $queryStmt->bind_param(
        'sssssssii',
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $limit,
        $offset
    );
} else {
    $queryStmt = $conn->prepare("
        SELECT t.*, u.username, u.email
        FROM mpesa_b2c_transactions t
        LEFT JOIN users u ON t.user_id = u.id
        ORDER BY t.id DESC
        LIMIT ? OFFSET ?
    ");

    if (!$queryStmt) {
        die('Unable to prepare withdrawal query.');
    }

    $queryStmt->bind_param('ii', $limit, $offset);
}

if (!$queryStmt->execute()) {
    $queryStmt->close();
    die('Unable to retrieve withdrawal records.');
}

$result = $queryStmt->get_result();

include 'admin-frame/header.php';
?>

<div class="space-y-4">

    <!-- Header -->
    <div class="p-4 sm:p-5 rounded-md bg-gradient-to-br from-teal-500/10 via-white to-white border border-teal-300/80 dark:bg-[#0f172a] dark:border-teal-500/30 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-2">
                <i class="fa-solid fa-money-bill-transfer text-teal-400"></i>
                B2C Withdrawals
            </h1>
            <p class="text-xs font-medium text-slate-600 dark:text-slate-400">
                Monitor M-Pesa B2C withdrawals, user accounts, and result descriptions.
            </p>
        </div>

        <div class="text-xs font-semibold text-slate-500 dark:text-slate-400">
            Total records: <?= number_format($totalRows) ?>
        </div>
    </div>

    <!-- Search -->
    <div class="bg-white dark:bg-[#0f172a] p-3 rounded-md border border-slate-200 dark:border-white/10 shadow-xs">
        <form method="GET" action="" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
            <div class="relative flex-1 sm:max-w-md">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </span>

                <input
                    type="text"
                    name="search"
                    value="<?= $escape($search) ?>"
                    placeholder="Search username, email, phone, name, transaction ID..."
                    class="w-full pl-9 pr-3 py-2 text-xs rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-1 focus:ring-teal-500"
                >
            </div>

            <button
                type="submit"
                class="px-4 py-2 bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold rounded-md"
            >
                Search
            </button>

            <?php if ($search !== ''): ?>
                <a
                    href="b2c_transactions.php"
                    class="px-3 py-2 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-md text-center"
                >
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Withdrawals Table -->
    <div class="bg-white dark:bg-[#0f172a] rounded-md border border-slate-200 dark:border-white/10 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/50 border-b border-slate-200 dark:border-white/10 text-[11px] font-bold text-slate-500 uppercase tracking-wider">
                        <th class="p-3">ID</th>
                        <th class="p-3">User / Account</th>
                        <th class="p-3">Phone Number</th>
                        <th class="p-3">Receiver</th>
                        <th class="p-3">Amount (KES)</th>
                        <th class="p-3">Status / Result</th>
                        <th class="p-3">Transaction Reference</th>
                        <th class="p-3">Timestamp</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200 dark:divide-white/10 text-xs">
                    <?php if ($result && $result->num_rows > 0): ?>

                        <?php while ($row = $result->fetch_assoc()): ?>
                            <?php
                            $id = (int) ($row['id'] ?? 0);

                            $username = trim((string) ($row['username'] ?? ''));
                            if ($username === '') {
                                $username = 'Unknown User';
                            }

                            $email = trim((string) ($row['email'] ?? ''));
                            if ($email === '') {
                                $email = 'No email registered';
                            }

                            $phone = $row['receiver_phone'] ?? 'N/A';
                            $receiver = $row['receiver_name'] ?? 'N/A';

                            $amount = (float) (
                                $row['transaction_amount'] ?? 0
                            );

                            $status = trim(
                                (string) ($row['result_desc'] ?? '')
                            );

                            if ($status === '') {
                                $status = 'N/A';
                            }

                            $reference = $row['transaction_id']
                                ?? $row['conversation_id']
                                ?? $row['originator_conversation_id']
                                ?? 'N/A';

                            if ($reference === '') {
                                $reference = 'N/A';
                            }

                            $date = $row['completed_at']
                                ?? $row['created_at']
                                ?? 'N/A';
                            ?>

                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30 transition-colors">
                                <td class="p-3 font-semibold text-slate-700 dark:text-slate-300">
                                    #<?= $id ?>
                                </td>

                                <!-- User Username & Email Column -->
                                <td class="p-3">
                                    <div class="font-bold text-slate-900 dark:text-slate-100">
                                        <?= $escape($username) ?>
                                    </div>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                        <?= $escape($email) ?>
                                    </div>
                                </td>

                                <td class="p-3 font-medium text-slate-800 dark:text-slate-200 whitespace-nowrap">
                                    <?= $escape($phone) ?>
                                </td>

                                <td class="p-3 text-slate-700 dark:text-slate-300">
                                    <?= $escape($receiver) ?>
                                </td>

                                <td class="p-3 font-black text-slate-900 dark:text-slate-100 whitespace-nowrap">
                                    KES <?= number_format($amount, 2) ?>
                                </td>

                                <td class="p-3 text-slate-700 dark:text-slate-300 min-w-56">
                                    <?= $escape($status) ?>
                                </td>

                                <td class="p-3 font-mono text-[11px] text-slate-500 dark:text-slate-400 break-all">
                                    <?= $escape($reference) ?>
                                </td>

                                <td class="p-3 text-slate-500 dark:text-slate-400 whitespace-nowrap">
                                    <?= $escape($date) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="p-6 text-center text-slate-400 font-medium">
                                No withdrawal records found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="p-3 bg-slate-50 dark:bg-slate-900/50 border-t border-slate-200 dark:border-white/10 flex items-center justify-between gap-3">
                <span class="text-xs text-slate-500">
                    Page <strong><?= $page ?></strong>
                    of <strong><?= $totalPages ?></strong>
                </span>

                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?>
                        <a
                            href="?search=<?= urlencode($search) ?>&amp;page=<?= $page - 1 ?>"
                            class="px-3 py-1 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded text-xs font-bold text-slate-700 dark:text-slate-200"
                        >
                            Previous
                        </a>
                    <?php endif; ?>

                    <?php if ($page < $totalPages): ?>
                        <a
                            href="?search=<?= urlencode($search) ?>&amp;page=<?= $page + 1 ?>"
                            class="px-3 py-1 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded text-xs font-bold text-slate-700 dark:text-slate-200"
                        >
                            Next
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$queryStmt->close();
include 'admin-frame/footer.php';
?>