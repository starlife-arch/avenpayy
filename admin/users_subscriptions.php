<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

// --- ABSOLUTE WEB ROOT DATABASE LINK ---
$dbPath = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
$dbLoaded = false;

if (file_exists($dbPath)) {
    require_once $dbPath;
    $dbLoaded = true;
} else {
    // Fallback search if DOCUMENT_ROOT fails
    $fallbackPaths = [
        __DIR__ . '/database_config/db.php',
        __DIR__ . '/../database_config/db.php',
        __DIR__ . '/../../database_config/db.php',
    ];
    foreach ($fallbackPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $dbLoaded = true;
            break;
        }
    }
}

if (!$dbLoaded || !isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    http_response_code(500);
    die('Error: Database connection could not be established.');
}

function jsonResponse(string $status, string $message, array $extra = []): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        array_merge(['status' => $status, 'message' => $message], $extra),
        JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function jsValue($value): string
{
    return json_encode(
        (string)$value,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
    );
}

/* --------------------------------------------------------------------------
   AJAX actions
-------------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = trim((string)$_POST['action']);

    if ($action === 'manage_settlement_plan') {
        $settlementId = filter_input(INPUT_POST, 'settlement_id', FILTER_VALIDATE_INT);
        $planAction = trim((string)($_POST['plan_action'] ?? ''));

        if (!$settlementId || $settlementId < 1) {
            jsonResponse('error', 'Invalid target settlement account ID.');
        }

        $check = $conn->prepare('SELECT expiry_date FROM settlements WHERE id = ? LIMIT 1');
        if (!$check) {
            jsonResponse('error', 'Could not verify the settlement account.');
        }

        $check->bind_param('i', $settlementId);
        $check->execute();
        $account = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$account) {
            jsonResponse('error', 'Settlement account not found.');
        }

        if ($planAction === 'deactivate' || $planAction === 'lifetime') {
            if ($planAction === 'deactivate') {
                $sql = "UPDATE settlements
                        SET expiry_date = NULL, status = 'inactive', is_active = 0
                        WHERE id = ? LIMIT 1";
                $successMessage = "Plan deactivated for settlement account #{$settlementId}.";
                $errorMessage = 'Failed to deactivate settlement plan.';
            } else {
                $sql = "UPDATE settlements
                        SET expiry_date = NULL, status = 'active', is_active = 1
                        WHERE id = ? LIMIT 1";
                $successMessage = "Settlement account #{$settlementId} activated with Lifetime Access.";
                $errorMessage = 'Failed to set lifetime access.';
            }

            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                jsonResponse('error', $errorMessage);
            }

            $stmt->bind_param('i', $settlementId);
            $success = $stmt->execute();
            $stmt->close();

            jsonResponse(
                $success ? 'success' : 'error',
                $success ? $successMessage : $errorMessage
            );
        }

        if ($planAction !== 'renew') {
            jsonResponse('error', 'Invalid plan management action specified.');
        }

        $plan = strtolower(trim((string)($_POST['plan'] ?? '1 month')));
        $customDuration = filter_var(
            $_POST['custom_duration'] ?? 1,
            FILTER_VALIDATE_INT
        );
        $customUnit = strtolower(trim((string)($_POST['custom_unit'] ?? 'days')));

        $dayPlans = [
            '1 day' => 1,
            '3 days' => 3,
            '7 days' => 7,
            '14 days' => 14,
            '30 days' => 30,
        ];

        $monthPlans = [
            '1 month' => 1,
            '3 months' => 3,
            '1 year' => 12,
        ];

        if (isset($dayPlans[$plan])) {
            $duration = $dayPlans[$plan];
            $unit = 'days';
        } elseif (isset($monthPlans[$plan])) {
            $duration = $monthPlans[$plan];
            $unit = 'months';
        } elseif ($plan === 'custom') {
            if ($customDuration === false || $customDuration < 1 || $customDuration > 1200) {
                jsonResponse('error', 'Custom duration must be between 1 and 1200.');
            }

            if (!in_array($customUnit, ['days', 'months'], true)) {
                jsonResponse('error', 'Invalid custom duration unit.');
            }

            $duration = $customDuration;
            $unit = $customUnit;
        } else {
            jsonResponse('error', 'Invalid subscription plan selected.');
        }

        try {
            $today = new DateTimeImmutable('today');
            $baseDate = $today;
            $existingExpiry = $account['expiry_date'] ?? null;

            if (!empty($existingExpiry)) {
                $expiryDate = new DateTimeImmutable($existingExpiry);
                if ($expiryDate >= $today) {
                    $baseDate = $expiryDate;
                }
            }

            $newExpiry = $baseDate
                ->modify('+' . $duration . ' ' . $unit)
                ->format('Y-m-d');
        } catch (Throwable $e) {
            jsonResponse('error', 'Could not calculate the new expiry date.');
        }

        $startDate = $today->format('Y-m-d');
        $monthsToAdd = ($unit === 'months') ? $duration : 0;

        $stmt = $conn->prepare(
            "UPDATE settlements
             SET start_date = ?,
                 expiry_date = ?,
                 status = 'active',
                 is_active = 1,
                 months_count = COALESCE(months_count, 0) + ?
             WHERE id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            jsonResponse('error', 'Could not prepare the plan update.');
        }

        $stmt->bind_param(
            'ssii',
            $startDate,
            $newExpiry,
            $monthsToAdd,
            $settlementId
        );

        $success = $stmt->execute();
        $stmt->close();

        jsonResponse(
            $success ? 'success' : 'error',
            $success
                ? "Plan activated for settlement account #{$settlementId}. New expiry: {$newExpiry}."
                : 'Failed to renew settlement plan.',
            ['expiry_date' => $success ? $newExpiry : null]
        );
    }

    if ($action === 'delete_settlement') {
        $deleteId = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);

        if (!$deleteId || $deleteId < 1) {
            jsonResponse('error', 'Invalid settlement account ID.');
        }

        if ($deleteId === 1) {
            jsonResponse(
                'error',
                'Operation denied: Primary gateway settlement account (#1) cannot be deleted.'
            );
        }

        $stmt = $conn->prepare('DELETE FROM settlements WHERE id = ? LIMIT 1');
        if (!$stmt) {
            jsonResponse('error', 'Could not prepare the delete request.');
        }

        $stmt->bind_param('i', $deleteId);
        $success = $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        if (!$success) {
            jsonResponse('error', 'Failed to delete settlement account.');
        }

        if (!$deleted) {
            jsonResponse('error', 'Settlement account not found.');
        }

        jsonResponse(
            'success',
            "Settlement account #{$deleteId} was successfully deleted.",
            ['id' => $deleteId]
        );
    }

    jsonResponse('error', 'Unknown action.');
}

/* --------------------------------------------------------------------------
   Search, pagination, and records
-------------------------------------------------------------------------- */
$searchQuery = trim((string)($_GET['search'] ?? ''));
$allowedLimits = [10, 25, 50, 100];
$limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT) ?: 10;

if (!in_array($limit, $allowedLimits, true)) {
    $limit = 10;
}

$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$offset = ($page - 1) * $limit;

$whereSql = "
    WHERE s.merchant_name IS NOT NULL
      AND s.merchant_name != ''
      AND (
          s.shortcode REGEXP '^[0-9]+$'
          OR s.type IN ('till', 'paybill', 'till_number', 'paybill_number')
      )
";

$types = '';
$params = [];

if ($searchQuery !== '') {
    $whereSql .= "
        AND (
            s.id LIKE ?
            OR s.merchant_name LIKE ?
            OR s.shortcode LIKE ?
            OR s.x_api_key LIKE ?
            OR u.username LIKE ?
            OR u.email LIKE ?
            OR u.phone_number LIKE ?
        )
    ";

    $types = 'sssssss';
    $likeTerm = '%' . $searchQuery . '%';
    $params = array_fill(0, 7, $likeTerm);
}

$countSql = "
    SELECT COUNT(*) AS total
    FROM settlements s
    LEFT JOIN users u ON s.user_id = u.id
    {$whereSql}
";

$countStmt = $conn->prepare($countSql);
if (!$countStmt) {
    http_response_code(500);
    die('Could not prepare the settlement count query.');
}

if ($params) {
    $countStmt->bind_param($types, ...$params);
}

$countStmt->execute();
$totalRows = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int)ceil($totalRows / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$query = "
    SELECT s.*, u.username, u.email, u.phone_number AS user_phone
    FROM settlements s
    LEFT JOIN users u ON s.user_id = u.id
    {$whereSql}
    ORDER BY s.id DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($query);
if (!$stmt) {
    http_response_code(500);
    die('Could not prepare the settlement query.');
}

$queryTypes = $types . 'ii';
$queryParams = array_merge($params, [$limit, $offset]);
$stmt->bind_param($queryTypes, ...$queryParams);
$stmt->execute();

$result = $stmt->get_result();
$settlements = [];

while ($row = $result->fetch_assoc()) {
    $settlements[] = $row;
}
$stmt->close();

$metricsQuery = "
    SELECT COUNT(*) AS total_count
    FROM settlements
    WHERE merchant_name IS NOT NULL
      AND merchant_name != ''
      AND (
          shortcode REGEXP '^[0-9]+$'
          OR type IN ('till', 'paybill', 'till_number', 'paybill_number')
      )
";

$metricsResult = $conn->query($metricsQuery);
$metrics = $metricsResult
    ? $metricsResult->fetch_assoc()
    : ['total_count' => 0];

$pageTitle = 'Configured Settlement Accounts Administration';
include __DIR__ . '/admin-frame/header.php';

$paginationParams = ['limit' => $limit];
if ($searchQuery !== '') {
    $paginationParams['search'] = $searchQuery;
}

function pageUrl(int $page, array $params): string
{
    $params['page'] = $page;
    return '?' . http_build_query($params);
}
?>

<div class="mx-auto max-w-7xl space-y-6 px-4 pb-12 sm:px-6">
    <div id="alert-container" aria-live="polite"></div>

    <section class="relative flex flex-col gap-4 overflow-hidden rounded-xl border border-slate-800 bg-gradient-to-r from-slate-950 via-teal-950 to-slate-900 p-6 text-white shadow-xl md:flex-row md:items-center md:justify-between">
        <div class="pointer-events-none absolute -bottom-12 -right-10 h-48 w-48 rounded-full bg-teal-500/10 blur-2xl"></div>

        <div class="relative z-10 flex items-center gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-teal-500 to-teal-600 text-lg shadow-lg shadow-teal-500/30">
                <i class="iclass fa-solid fa-wallet" aria-hidden="true"></i>
            </div>
            <div>
                <h1 class="text-base font-bold tracking-tight sm:text-lg">Configured Settlement Accounts</h1>
                <p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-300">
                    Manage merchant settlement accounts, copy API keys, and control subscription access.
                </p>
            </div>
        </div>

        <div class="relative z-10 inline-flex items-center gap-2 self-start rounded-lg border border-white/10 bg-white/10 px-4 py-2 shadow-inner md:self-auto">
            <span class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"></span>
            <span class="text-[11px] font-medium text-slate-300">Configured accounts:</span>
            <span class="text-xs font-extrabold"><?= number_format((int)$metrics['total_count']) ?></span>
        </div>
    </section>

    <section class="rounded-xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-[#0f172a]">
        <form method="GET" class="flex w-full flex-col items-center gap-3 sm:flex-row">
            <div class="w-full sm:w-auto">
                <label for="limit" class="sr-only">Results per page</label>
                <select id="limit" name="limit" onchange="this.form.submit()"
                    class="w-full cursor-pointer rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 sm:w-auto">
                    <?php foreach ($allowedLimits as $allowedLimit): ?>
                        <option value="<?= $allowedLimit ?>" <?= $limit === $allowedLimit ? 'selected' : '' ?>>
                            <?= $allowedLimit ?> per page
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="relative w-full flex-1">
                <label for="search" class="sr-only">Search settlement accounts</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                    <i class="iclass fa-solid fa-magnifying-glass text-xs" aria-hidden="true"></i>
                </span>
                <input id="search" type="search" name="search" value="<?= h($searchQuery) ?>"
                    placeholder="Search ID, merchant, shortcode, API key, username, email, or phone..."
                    class="w-full rounded-lg border border-slate-200 bg-slate-50/50 py-2.5 pl-10 pr-4 text-xs text-slate-900 shadow-sm focus:outline-none focus:ring-2 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
            </div>

            <div class="flex w-full items-center gap-2 sm:w-auto">
                <button type="submit"
                    class="flex-1 rounded-lg bg-gradient-to-r from-teal-600 to-teal-600 px-5 py-2.5 text-xs font-semibold text-white shadow-md shadow-teal-500/20 transition hover:from-teal-700 hover:to-teal-700 sm:flex-none">
                    Search
                </button>
                <?php if ($searchQuery !== '' || $limit !== 10): ?>
                    <a href="<?= h(basename($_SERVER['PHP_SELF'])) ?>"
                        class="flex-1 rounded-lg bg-slate-100 px-4 py-2.5 text-center text-xs font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 sm:flex-none">
                        Reset
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-md dark:border-white/10 dark:bg-[#0f172a]">
        <div class="w-full overflow-x-auto">
            <table class="w-full min-w-[1050px] table-fixed border-collapse text-left">
                <colgroup>
                    <col style="width: 75px">
                    <col style="width: 240px">
                    <col style="width: 280px">
                    <col style="width: 180px">
                    <col style="width: 210px">
                    <col style="width: 150px">
                </colgroup>
                <thead class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:border-slate-800 dark:bg-slate-900/70 dark:text-slate-400">
                    <tr>
                        <th class="px-4 py-3.5">ID</th>
                        <th class="px-4 py-3.5">Settlement Account</th>
                        <th class="px-4 py-3.5">Owner / User</th>
                        <th class="px-4 py-3.5">API Key</th>
                        <th class="px-4 py-3.5">Plan Status</th>
                        <th class="px-4 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>

                <tbody id="settlements-table-body" class="divide-y divide-slate-200 text-xs dark:divide-slate-800">
                <?php if ($settlements): ?>
                    <?php foreach ($settlements as $settlement): ?>
                        <?php
                        $id = (int)$settlement['id'];
                        $apiKey = (string)($settlement['x_api_key'] ?? '');
                        $merchantName = (string)($settlement['merchant_name'] ?? 'Not Specified');
                        $shortcode = (string)($settlement['shortcode'] ?? 'N/A');
                        $type = (string)($settlement['type'] ?? '');
                        $ownerName = (string)($settlement['username'] ?? 'System / Unassigned');
                        $userPhone = (string)($settlement['user_phone'] ?? 'N/A');
                        $userEmail = (string)($settlement['email'] ?? 'N/A');
                        $accountStatus = strtolower((string)($settlement['status'] ?? 'inactive'));
                        $expiry = $settlement['expiry_date'] ?? null;

                        if ($apiKey === '' || $accountStatus !== 'active') {
                            $planText = 'No Active Plan';
                            $planClass = 'border-slate-500/20 bg-slate-500/10 text-slate-600 dark:text-slate-400';
                        } elseif (empty($expiry)) {
                            $planText = 'Lifetime Access';
                            $planClass = 'border-teal-500/30 bg-teal-500/10 font-bold text-teal-700 dark:text-teal-300';
                        } else {
                            $expiryTimestamp = strtotime((string)$expiry);

                            if ($expiryTimestamp !== false && $expiryTimestamp >= strtotime('today')) {
                                $daysLeft = (int)ceil(($expiryTimestamp - time()) / 86400);
                                $planText = "Expires in {$daysLeft}d ({$expiry})";
                                $planClass = 'border-emerald-500/30 bg-emerald-500/10 font-semibold text-emerald-700 dark:text-emerald-300';
                            } else {
                                $planText = "Expired ({$expiry})";
                                $planClass = 'border-rose-500/30 bg-rose-500/10 font-semibold text-rose-700 dark:text-rose-300';
                            }
                        }
                        ?>
                        <tr id="settlement-row-<?= $id ?>" class="group transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-800/50">
                            <td class="truncate px-4 py-3.5 align-middle font-mono font-bold text-slate-700 dark:text-slate-300">
                                #<?= $id ?>
                            </td>

                            <td class="truncate px-4 py-3.5 align-middle">
                                <div class="flex items-center gap-1.5 truncate font-bold text-slate-900 dark:text-slate-100">
                                    <i class="iclass fa-solid fa-building-columns shrink-0 text-xs text-teal-600 dark:text-teal-400" aria-hidden="true"></i>
                                    <span class="truncate" title="<?= h($merchantName) ?>"><?= h($merchantName) ?></span>
                                </div>
                                <div class="mt-1 truncate font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                    <span>Code: <?= h($shortcode) ?></span>
                                    <?php if ($type !== ''): ?>
                                        <span class="text-slate-300 dark:text-slate-600"> · </span>
                                        <span class="font-semibold uppercase text-teal-600 dark:text-teal-400"><?= h($type) ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td class="truncate px-4 py-3.5 align-middle">
                                <div class="flex items-center gap-2 truncate font-bold text-slate-900 dark:text-slate-100">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-gradient-to-tr from-teal-600 to-teal-500 text-[9px] font-extrabold text-white">
                                        <?= h(strtoupper(substr($ownerName, 0, 1))) ?>
                                    </span>
                                    <span class="truncate" title="<?= h($ownerName) ?>"><?= h($ownerName) ?></span>
                                </div>
                                <div class="mt-1 truncate pl-8 text-[11px] text-slate-400">
                                    <span><i class="iclass fa-solid fa-phone mr-0.5 text-[9px]" aria-hidden="true"></i><?= h($userPhone) ?></span>
                                    <span class="px-1 text-slate-300 dark:text-slate-600">·</span>
                                    <span><i class="iclass fa-solid fa-envelope mr-0.5 text-[9px]" aria-hidden="true"></i><?= h($userEmail) ?></span>
                                </div>
                            </td>

                            <td class="truncate px-4 py-3.5 align-middle">
                                <?php if ($apiKey !== ''): ?>
                                    <div class="inline-flex max-w-full items-center gap-2 rounded-md border border-slate-200 bg-slate-100 px-2.5 py-1.5 font-mono text-[11px] text-slate-700 shadow-sm dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-300">
                                        <span class="truncate" title="<?= h($apiKey) ?>"><?= h(substr($apiKey, 0, 10)) ?>...</span>
                                        <button type="button" onclick="copyApiKey(<?= h(jsValue($apiKey)) ?>, this)"
                                            class="shrink-0 rounded border border-slate-200 bg-white p-1 text-slate-400 transition hover:text-teal-600 dark:border-slate-700 dark:bg-slate-800 dark:hover:text-teal-400"
                                            title="Copy full API key" aria-label="Copy API key">
                                            <i class="iclass fa-regular fa-copy text-xs" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <span class="text-[11px] italic text-slate-400">Not configured</span>
                                <?php endif; ?>
                            </td>

                            <td class="truncate px-4 py-3.5 align-middle">
                                <span class="inline-flex max-w-full items-center gap-1.5 truncate rounded-md border px-3 py-1 text-[11px] <?= h($planClass) ?>">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current"></span>
                                    <span class="truncate"><?= h($planText) ?></span>
                                </span>
                            </td>

                            <td class="px-4 py-3.5 text-right align-middle">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <button type="button"
                                        onclick="openPlanModal(<?= $id ?>, <?= h(jsValue($merchantName)) ?>)"
                                        class="inline-flex items-center gap-1 rounded-md border border-teal-200 bg-teal-50 px-2.5 py-1.5 text-xs font-bold text-teal-600 shadow-sm transition hover:bg-teal-100 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-400"
                                        title="Manage settlement plan">
                                        <i class="iclass fa-solid fa-calendar-days text-[11px]" aria-hidden="true"></i>
                                        Plan
                                    </button>
                                    <button type="button" onclick="deleteSettlement(<?= $id ?>)"
                                        class="rounded-md border border-rose-200 bg-rose-50 p-2 text-xs font-bold text-rose-600 shadow-sm transition hover:bg-rose-100 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-400"
                                        title="Delete account" aria-label="Delete settlement account">
                                        <i class="iclass fa-solid fa-trash-can text-xs" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-slate-400">
                            <div class="flex flex-col items-center justify-center gap-2">
                                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-400 dark:bg-slate-800">
                                    <i class="iclass fa-solid fa-folder-open" aria-hidden="true"></i>
                                </span>
                                <p class="font-medium">No configured settlement accounts found.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex flex-col items-center justify-between gap-4 border-t border-slate-200 bg-slate-50/50 px-6 py-4 dark:border-slate-800 dark:bg-slate-900/50 sm:flex-row">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Showing
                    <strong class="text-slate-700 dark:text-slate-300"><?= $totalRows ? $offset + 1 : 0 ?></strong>
                    to
                    <strong class="text-slate-700 dark:text-slate-300"><?= min($offset + $limit, $totalRows) ?></strong>
                    of
                    <strong class="text-slate-700 dark:text-slate-300"><?= number_format($totalRows) ?></strong>
                    entries
                </p>

                <nav class="flex items-center gap-1.5" aria-label="Pagination">
                    <a href="<?= h(pageUrl(1, $paginationParams)) ?>"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>"
                       aria-label="First page">
                        <i class="iclass fa-solid fa-angles-left text-[10px]" aria-hidden="true"></i>
                    </a>

                    <a href="<?= h(pageUrl(max(1, $page - 1), $paginationParams)) ?>"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 <?= $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">
                        <i class="iclass fa-solid fa-angle-left text-[10px]" aria-hidden="true"></i> Prev
                    </a>

                    <span class="rounded-lg border border-teal-500/30 bg-teal-50 px-3 py-1.5 text-xs font-bold text-teal-600 dark:bg-teal-500/10 dark:text-teal-400">
                        Page <?= $page ?> of <?= $totalPages ?>
                    </span>

                    <a href="<?= h(pageUrl(min($totalPages, $page + 1), $paginationParams)) ?>"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 <?= $page >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>">
                        Next <i class="iclass fa-solid fa-angle-right text-[10px]" aria-hidden="true"></i>
                    </a>

                    <a href="<?= h(pageUrl($totalPages, $paginationParams)) ?>"
                       class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 <?= $page >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>"
                       aria-label="Last page">
                        <i class="iclass fa-solid fa-angles-right text-[10px]" aria-hidden="true"></i>
                    </a>
                </nav>
            </div>
        <?php endif; ?>
    </section>
</div>

<!-- Plan management modal -->
<div id="planModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
    <div class="w-full max-w-md space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-2xl dark:border-slate-800 dark:bg-[#0f172a]">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3.5 dark:border-slate-800">
            <div class="min-w-0">
                <h2 id="modalTitle" class="truncate text-sm font-bold text-slate-800 dark:text-slate-100">Manage Settlement Plan</h2>
                <p class="mt-0.5 text-[11px] text-slate-400">Update the subscription timeline or access level.</p>
            </div>
            <button type="button" onclick="closePlanModal()"
                class="ml-3 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-400 transition hover:text-slate-700 dark:bg-slate-800 dark:hover:text-slate-200"
                aria-label="Close modal">
                <i class="iclass fa-solid fa-xmark text-sm" aria-hidden="true"></i>
            </button>
        </div>

        <input type="hidden" id="modalSettlementId">

        <div class="space-y-4">
            <div>
                <label for="modalPlanAction" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-300">Action</label>
                <select id="modalPlanAction" onchange="handleModalActionChange()"
                    class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                    <option value="renew">Renew / Extend Plan</option>
                    <option value="lifetime">Set Lifetime Access</option>
                    <option value="deactivate">Clear Plan / Deactivate</option>
                </select>
            </div>

            <div id="planDurationWrapper" class="space-y-4">
                <div>
                    <label for="modalPlanPackage" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-300">Subscription package</label>
                    <select id="modalPlanPackage" onchange="toggleCustomMonths()"
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                        <option value="1 day">1 Day</option>
                        <option value="3 days">3 Days</option>
                        <option value="7 days">7 Days</option>
                        <option value="14 days">14 Days</option>
                        <option value="30 days">30 Days</option>
                        <option value="1 month">1 Month (350 KES)</option>
                        <option value="3 months">3 Months (950 KES)</option>
                        <option value="1 year">1 Year (3,500 KES)</option>
                        <option value="custom">Custom Duration</option>
                    </select>
                </div>

                <div id="customMonthsField" class="hidden">
                    <label for="modalCustomMonths" class="mb-1.5 block text-xs font-bold text-slate-700 dark:text-slate-300">Custom duration</label>
                    <div class="flex gap-2">
                        <input type="number" id="modalCustomMonths" min="1" max="1200" value="1"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs text-slate-900 focus:outline-none focus:ring-2 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                        <select id="modalCustomUnit"
                            class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100">
                            <option value="days">Days</option>
                            <option value="months">Months</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-2.5 border-t border-slate-100 pt-4 dark:border-slate-800">
            <button type="button" onclick="closePlanModal()"
                class="rounded-lg bg-slate-100 px-4 py-2.5 text-xs font-semibold text-slate-600 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300">
                Cancel
            </button>
            <button type="button" id="savePlanButton" onclick="submitPlanAction()"
                class="rounded-lg bg-gradient-to-r from-teal-600 to-teal-600 px-5 py-2.5 text-xs font-semibold text-white shadow-md shadow-teal-500/20 transition hover:from-teal-700 hover:to-teal-700">
                Save Changes
            </button>
        </div>
    </div>
</div>

<script>
const currentUrl = window.location.pathname + window.location.search;

function showAlert(type, message) {
    const container = document.getElementById('alert-container');
    const isError = type === 'error';

    const alert = document.createElement('div');
    alert.className = [
        'mb-4 flex items-center justify-between rounded-lg border p-4 text-xs font-semibold shadow-lg',
        isError
            ? 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300'
            : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
    ].join(' ');

    const text = document.createElement('span');
    text.textContent = message;

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'ml-4 p-1 opacity-70 hover:opacity-100';
    close.setAttribute('aria-label', 'Dismiss alert');
    close.innerHTML = '<i class="iclass fa-solid fa-xmark" aria-hidden="true"></i>';
    close.addEventListener('click', () => alert.remove());

    alert.append(text, close);
    container.replaceChildren(alert);

    window.setTimeout(() => alert.remove(), 6000);
}

async function postAction(data) {
    const response = await fetch(currentUrl, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams(data).toString()
    });

    const text = await response.text();

    try {
        return JSON.parse(text);
    } catch {
        throw new Error('Server returned an invalid response. Refresh the page and try again.');
    }
}

async function copyApiKey(apiKey, button) {
    try {
        await navigator.clipboard.writeText(apiKey);
        const icon = button.querySelector('i');

        if (icon) {
            icon.className = 'iclass fa-solid fa-check text-xs text-emerald-600';
            window.setTimeout(() => {
                icon.className = 'iclass fa-regular fa-copy text-xs';
            }, 1500);
        }

        showAlert('success', 'API key copied to clipboard.');
    } catch (error) {
        showAlert('error', 'Could not copy the API key. Check clipboard permissions.');
    }
}

function openPlanModal(settlementId, merchantName) {
    document.getElementById('modalSettlementId').value = settlementId;
    document.getElementById('modalTitle').textContent = 'Manage Plan: ' + merchantName;

    const modal = document.getElementById('planModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    handleModalActionChange();
}

function closePlanModal() {
    const modal = document.getElementById('planModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function handleModalActionChange() {
    const isRenew = document.getElementById('modalPlanAction').value === 'renew';
    document.getElementById('planDurationWrapper').classList.toggle('hidden', !isRenew);
}

function toggleCustomMonths() {
    const isCustom = document.getElementById('modalPlanPackage').value === 'custom';
    document.getElementById('customMonthsField').classList.toggle('hidden', !isCustom);
}

async function submitPlanAction() {
    const button = document.getElementById('savePlanButton');
    const planAction = document.getElementById('modalPlanAction').value;
    const plan = document.getElementById('modalPlanPackage').value;
    const customDuration = document.getElementById('modalCustomMonths').value;
    const customUnit = document.getElementById('modalCustomUnit').value;

    if (planAction === 'renew' && plan === 'custom') {
        const duration = Number(customDuration);

        if (!Number.isInteger(duration) || duration < 1 || duration > 1200) {
            showAlert('error', 'Enter a custom duration between 1 and 1200.');
            return;
        }
    }

    button.disabled = true;
    button.textContent = 'Saving...';

    try {
        const data = await postAction({
            action: 'manage_settlement_plan',
            settlement_id: document.getElementById('modalSettlementId').value,
            plan_action: planAction,
            plan,
            custom_duration: customDuration,
            custom_unit: customUnit
        });

        if (data.status !== 'success') {
            showAlert('error', data.message || 'Could not update the plan.');
            return;
        }

        closePlanModal();
        showAlert('success', data.message);
        window.setTimeout(() => window.location.reload(), 700);
    } catch (error) {
        showAlert('error', error.message || 'An error occurred while updating the plan.');
    } finally {
        button.disabled = false;
        button.textContent = 'Save Changes';
    }
}

async function deleteSettlement(id) {
    if (!window.confirm(`Are you sure you want to delete settlement account #${id}?`)) {
        return;
    }

    try {
        const data = await postAction({
            action: 'delete_settlement',
            delete_id: id
        });

        if (data.status !== 'success') {
            showAlert('error', data.message || 'Could not delete the account.');
            return;
        }

        document.getElementById('settlement-row-' + id)?.remove();
        showAlert('success', data.message);
    } catch (error) {
        showAlert('error', error.message || 'An error occurred while deleting the account.');
    }
}

document.getElementById('planModal').addEventListener('click', function (event) {
    if (event.target === this) {
        closePlanModal();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closePlanModal();
    }
});
</script>

<?php
include __DIR__ . '/admin-frame/footer.php';
?>