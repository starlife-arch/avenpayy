<?php
// 1. Initialize session and enforce authentication check
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: /admin/");
    exit();
}

// 2. Safe Database Connection Loader
$db_paths = [
    __DIR__ . '/database_config/db.php',
    'database_config/db.php',
    '../database_config/db.php',
    './db.php',
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

// 3. Fetch initial dynamic stats from database for fast initial render
$daily_volume = 0.00;
$monthly_volume = 0.00;
$total_successful_amount = 0.00;
$total_registered_users = 0;
$total_webhooks_sent = 0;
$total_active_settlements = 0;

// B2C specific metrics
$admin_b2c_balance = 0.00;
$working_balance = 0.00;
$charges_balance = 0.00;
$total_users_b2c_balance = 0.00;
$today_b2c_payouts = 0.00;
$total_b2c_payouts = 0.00;
$today_b2c_topups = 0.00;
$total_b2c_topups = 0.00;

// Fee & Revenue metrics
$total_topup_fees = 0.00;
$total_payout_fees = 0.00;
$total_revenue_fees = 0.00;

// C2B Chart metrics counters
$count_success = 0;
$count_cancelled = 0;
$count_failed = 0;

// B2C Chart metrics counters
$b2c_count_success = 0;
$b2c_count_cancelled = 0;
$b2c_count_failed = 0;

if ($db_loaded && isset($conn)) {
    // Aggregate Transaction Volume Stats (C2B Gateway Payins)
    $stmt = $conn->prepare("
        SELECT 
            SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as total_volume,
            SUM(CASE WHEN DATE(transaction_date) = CURDATE() AND status = 'success' THEN amount ELSE 0 END) as daily_volume,
            SUM(CASE WHEN MONTH(transaction_date) = MONTH(CURDATE()) AND YEAR(transaction_date) = YEAR(CURDATE()) AND status = 'success' THEN amount ELSE 0 END) as monthly_volume
        FROM mpesa_transactions
    ");
    if ($stmt) {
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();
        $total_successful_amount = (float)($stats['total_volume'] ?? 0);
        $daily_volume = (float)($stats['daily_volume'] ?? 0);
        $monthly_volume = (float)($stats['monthly_volume'] ?? 0);
        $stmt->close();
    }

    // Total Registered Users & Sum of B2C Balances
    $user_query = "SELECT COUNT(*) AS total_users, SUM(b2c_wallet_balance) AS sum_balances FROM users";
    $user_result = $conn->query($user_query);
    if ($user_result && $row = $user_result->fetch_assoc()) {
        $total_registered_users = (int)($row['total_users'] ?? 0);
        $total_users_b2c_balance = (float)($row['sum_balances'] ?? 0.00);
    }

    // Processed B2C Payouts & Dynamic Fee Calculation from transaction_fees table
    $payout_agg = $conn->query("
        SELECT 
            SUM(transaction_amount) as total_payouts,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN transaction_amount ELSE 0 END) as today_payouts,
            SUM(
                COALESCE(
                    (SELECT f.fee_value FROM transaction_fees f 
                     WHERE f.service_type = 'b2c_withdrawal' AND f.is_active = 1 
                       AND t.transaction_amount BETWEEN f.min_amount AND f.max_amount 
                     LIMIT 1), 
                0)
            ) as total_payout_fees
        FROM mpesa_b2c_transactions t
        WHERE t.result_code = 0
    ");
    if ($payout_agg && $prow = $payout_agg->fetch_assoc()) {
        $total_b2c_payouts = (float)($prow['total_payouts'] ?? 0.00);
        $today_b2c_payouts = (float)($prow['today_payouts'] ?? 0.00);
        $total_payout_fees = (float)($prow['total_payout_fees'] ?? 0.00);
    }

    // Processed B2C Top-ups & Dynamic Fee Calculation from transaction_fees table
    $topup_agg = $conn->query("
        SELECT 
            SUM(amount) as total_topups,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN amount ELSE 0 END) as today_topups,
            SUM(
                COALESCE(
                    (SELECT f.fee_value FROM transaction_fees f 
                     WHERE f.service_type = 'c2b_topup' AND f.is_active = 1 
                       AND t.amount BETWEEN f.min_amount AND f.max_amount 
                     LIMIT 1), 
                0)
            ) as total_topup_fees
        FROM b2c_topup_transactions t
        WHERE t.status = 'COMPLETED' OR t.status = 'SUCCESS' OR t.result_code = 0
    ");
    if ($topup_agg && $trow = $topup_agg->fetch_assoc()) {
        $total_b2c_topups = (float)($trow['total_topups'] ?? 0.00);
        $today_b2c_topups = (float)($trow['today_topups'] ?? 0.00);
        $total_topup_fees = (float)($trow['total_topup_fees'] ?? 0.00);
    }

    // Total Fee Revenue Aggregation
    $total_revenue_fees = $total_topup_fees + $total_payout_fees;

    // Fetch latest verified Admin B2C Account balances
    $bal_cb_query = "SELECT account_balance FROM mpesa_balance_callbacks ORDER BY id DESC LIMIT 1";
    $cb_res = $conn->query($bal_cb_query);
    if ($cb_res && $cb_row = $cb_res->fetch_assoc()) {
        $rawBalStr = $cb_row['account_balance'] ?? '';
        if (!empty($rawBalStr)) {
            $accounts = explode('&', $rawBalStr);
            foreach ($accounts as $block) {
                $parts = explode('|', $block);
                if (count($parts) >= 2) {
                    $type = trim($parts[0]);
                    $amt = 0.00;
                    for ($p = 1; $p < count($parts); $p++) {
                        if (is_numeric($parts[$p]) && floatval($parts[$p]) >= 0) {
                            $amt = (float)$parts[$p];
                            break;
                        }
                    }
                    if (stripos($type, 'Utility') !== false) $admin_b2c_balance = $amt;
                    if (stripos($type, 'Working') !== false) $working_balance = $amt;
                    if (stripos($type, 'Charges') !== false) $charges_balance = $amt;
                }
            }
        }
    }

    // Total Sent Webhooks
    $webhook_query = "SELECT COUNT(*) AS total_sent FROM webhook_queue WHERE status = 'SENT'";
    $webhook_result = $conn->query($webhook_query);
    if ($webhook_result && $row = $webhook_result->fetch_assoc()) {
        $total_webhooks_sent = (int)($row['total_sent'] ?? 0);
    }

    // Total Active Settlements
    $settle_query = "SELECT COUNT(*) AS active_settlements FROM settlements WHERE is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())";
    $settle_result = $conn->query($settle_query);
    if ($settle_result && $row = $settle_result->fetch_assoc()) {
        $total_active_settlements = (int)($row['active_settlements'] ?? 0);
    }

    // C2B Chart breakdown counts
    $chart_query = "
        SELECT 
            SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN status = 'cancelled' OR mpesa_receipt_number LIKE '%1032%' OR mpesa_receipt_number LIKE '%cancel%' THEN 1 ELSE 0 END) as cancelled_count,
            SUM(CASE WHEN status != 'success' AND status != 'cancelled' AND mpesa_receipt_number NOT LIKE '%1032%' AND mpesa_receipt_number NOT LIKE '%cancel%' THEN 1 ELSE 0 END) as failed_count
        FROM mpesa_transactions
    ";
    $chart_result = $conn->query($chart_query);
    if ($chart_result && $crow = $chart_result->fetch_assoc()) {
        $count_success = (int)($crow['success_count'] ?? 0);
        $count_cancelled = (int)($crow['cancelled_count'] ?? 0);
        $count_failed = (int)($crow['failed_count'] ?? 0);
    }

    // B2C Chart breakdown counts
    $b2c_chart_query = "
        SELECT 
            SUM(CASE WHEN result_code = 0 THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN result_code = 1032 OR result_code = 1 THEN 1 ELSE 0 END) as cancelled_count,
            SUM(CASE WHEN result_code != 0 AND result_code != 1032 AND result_code != 1 THEN 1 ELSE 0 END) as failed_count
        FROM mpesa_b2c_transactions
    ";
    $b2c_chart_result = $conn->query($b2c_chart_query);
    if ($b2c_chart_result && $b2c_crow = $b2c_chart_result->fetch_assoc()) {
        $b2c_count_success = (int)($b2c_crow['success_count'] ?? 0);
        $b2c_count_cancelled = (int)($b2c_crow['cancelled_count'] ?? 0);
        $b2c_count_failed = (int)($b2c_crow['failed_count'] ?? 0);
    }
}

// 4. Set page title and load header
$page_title = "Dashboard";
include 'admin-frame/header.php';
?>

<!-- Include Chart.js for the Pie Chart -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- Dashboard Main View -->
<div class="space-y-4">
    <!-- Welcome Banner Card -->
    <div class="p-4 sm:p-5 rounded-md bg-gradient-to-br from-teal-500/10 via-white to-white border border-teal-300/80 dark:bg-[#0f172a] dark:border-teal-500/30 shadow-xs flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div class="space-y-0.5">
            <h1 class="text-lg font-bold tracking-tight text-slate-800 dark:text-slate-100 flex flex-wrap items-center">
                <span class="text-black dark:font-black dark:tracking-widest dark:text-teal-400">Welcome back,&nbsp;</span>
                <span class="text-black dark:font-black dark:tracking-widest dark:text-teal-400"><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?>! 👋</span>
            </h1>
            <p class="text-xs font-medium text-slate-600 dark:text-slate-400">
                Live overview of gateway activity, B2C transfers, and collected tariff fee revenue.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-sm bg-emerald-500/15 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 text-[11px] font-semibold">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                Active
            </span>
        </div>
    </div>

    <!-- C2B Collections & Gateway Operations Section Header -->
    <div class="pt-1 pb-1 flex items-center justify-between">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 flex items-center gap-2">
            <i class="iclass fa-solid fa-coins text-teal-400"></i> C2B Collections & Gateway Operations
        </h3>
    </div>

    <!-- Quick Stats Grid (C2B Collections) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
        <!-- Today Payins -->
        <div class="p-4 rounded-md bg-gradient-to-br from-teal-500/10 via-white to-white border border-teal-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-4">
            <div class="space-y-0.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-teal-900 dark:text-teal-300 block">Today's Payins</span>
                <div id="valDailyPayins" class="text-xl font-black text-teal-950 dark:text-slate-100">KES <?php echo number_format($daily_volume, 0); ?></div>
                <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Successful daily sum</p>
            </div>
            <div class="w-10 h-10 rounded-sm bg-teal-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-coins text-xs"></i>
            </div>
        </div>

        <!-- Monthly Payins -->
        <div class="p-4 rounded-md bg-gradient-to-br from-amber-500/10 via-white to-white border border-amber-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-4">
            <div class="space-y-0.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-900 dark:text-amber-300 block">This Month Payins</span>
                <div id="valMonthlyPayins" class="text-xl font-black text-amber-950 dark:text-slate-100">KES <?php echo number_format($monthly_volume, 0); ?></div>
                <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Successful monthly sum</p>
            </div>
            <div class="w-10 h-10 rounded-sm bg-amber-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-wallet text-xs"></i>
            </div>
        </div>

        <!-- Total Volume -->
        <div class="p-4 rounded-md bg-gradient-to-br from-emerald-500/10 via-white to-white border border-emerald-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-4">
            <div class="space-y-0.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300 block">Total Volume</span>
                <div id="valTotalVolume" class="text-xl font-black text-emerald-950 dark:text-slate-100">KES <?php echo number_format($total_successful_amount, 0); ?></div>
                <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">All-time successful sum</p>
            </div>
            <div class="w-10 h-10 rounded-sm bg-emerald-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-vault text-xs"></i>
            </div>
        </div>

        <!-- Registered Users -->
        <div class="p-4 rounded-md bg-gradient-to-br from-teal-500/10 via-white to-white border border-teal-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-4">
            <div class="space-y-0.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-teal-900 dark:text-teal-300 block">Users</span>
                <div id="valUsers" class="text-xl font-black text-teal-950 dark:text-slate-100"><?php echo number_format($total_registered_users, 0); ?></div>
                <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Total accounts created</p>
            </div>
            <div class="w-10 h-10 rounded-sm bg-teal-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-users text-xs"></i>
            </div>
        </div>

        <!-- Webhooks Sent -->
        <div class="p-4 rounded-md bg-gradient-to-br from-teal-500/10 via-white to-white border border-teal-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-4">
            <div class="space-y-0.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-teal-900 dark:text-teal-300 block">Webhooks Sent</span>
                <div id="valWebhooks" class="text-xl font-black text-teal-950 dark:text-slate-100"><?php echo number_format($total_webhooks_sent, 0); ?></div>
                <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Users Callbacks</p>
            </div>
            <div class="w-10 h-10 rounded-sm bg-teal-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-paper-plane text-xs"></i>
            </div>
        </div>

        <!-- Active Settlements -->
        <div class="p-4 rounded-md bg-gradient-to-br from-rose-500/10 via-white to-white border border-rose-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-4">
            <div class="space-y-0.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-rose-900 dark:text-rose-300 block">Active Settlements</span>
                <div id="valSettlements" class="text-xl font-black text-rose-950 dark:text-slate-100"><?php echo number_format($total_active_settlements, 0); ?></div>
                <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Non-expired accounts</p>
            </div>
            <div class="w-10 h-10 rounded-sm bg-rose-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-building-columns text-xs"></i>
            </div>
        </div>
    </div>

    <!-- Revenue & Deducted Fees Section Header -->
    <div class="pt-2 pb-1 flex items-center justify-between">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 flex items-center gap-2">
            <i class="iclass fa-solid fa-hand-holding-dollar text-emerald-400"></i> Gateway Fee Revenue & Deductions
        </h3>
    </div>

    <!-- Revenue Metrics Grid (3 Fee Revenue Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Top-up Fees Collected -->
        <div class="p-4 rounded-md bg-gradient-to-br from-amber-500/10 via-white to-white border border-amber-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-4">
            <div class="space-y-0.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-amber-900 dark:text-amber-300 block">Top-up Fees Revenue</span>
                <div id="valTopupFees" class="text-xl font-black text-amber-950 dark:text-slate-100">KES <?php echo number_format($total_topup_fees, 0); ?></div>
                <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Deducted from successful C2B top-ups</p>
            </div>
            <div class="w-10 h-10 rounded-sm bg-amber-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-coins text-xs"></i>
            </div>
        </div>

        <!-- Withdrawal Fees Collected -->
        <div class="p-4 rounded-md bg-gradient-to-br from-orange-500/10 via-white to-white border border-orange-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-4">
            <div class="space-y-0.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-orange-900 dark:text-orange-300 block">Withdrawal Fees Revenue</span>
                <div id="valPayoutFees" class="text-xl font-black text-orange-950 dark:text-slate-100">KES <?php echo number_format($total_payout_fees, 0); ?></div>
                <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Deducted from successful B2C payouts</p>
            </div>
            <div class="w-10 h-10 rounded-sm bg-orange-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-file-invoice-dollar text-xs"></i>
            </div>
        </div>

        <!-- Total Revenue Fees Earned -->
        <div class="p-4 rounded-md bg-gradient-to-br from-teal-500/10 via-white to-white border border-teal-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-4">
            <div class="space-y-0.5">
                <span class="text-[11px] font-bold uppercase tracking-wider text-teal-900 dark:text-teal-300 block">Total Fees Revenue</span>
                <div id="valTotalRevenueFees" class="text-xl font-black text-teal-950 dark:text-slate-100">KES <?php echo number_format($total_revenue_fees, 0); ?></div>
                <p class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Combined tariff fees earned</p>
            </div>
            <div class="w-10 h-10 rounded-sm bg-teal-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-chart-line text-xs"></i>
            </div>
        </div>
    </div>

    <!-- B2C Financial Metrics Section Header -->
    <div class="pt-2 pb-1 flex items-center justify-between">
        <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 flex items-center gap-2">
            <i class="iclass fa-solid fa-money-bill-transfer text-teal-400"></i> B2C Wallet, Top-ups & Payout Operations
        </h3>
    </div>

    <!-- B2C Metrics Grid (8 Cards) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8 gap-3">
        <!-- Today Topups -->
        <div class="p-3.5 rounded-md bg-gradient-to-br from-lime-500/10 via-white to-white border border-lime-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-3">
            <div class="space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-lime-900 dark:text-lime-300 block">Today Topups</span>
                <div id="valTodayTopups" class="text-lg font-black text-lime-950 dark:text-slate-100">
                    KES <?php echo number_format($today_b2c_topups, 0); ?>
                </div>
                <p class="text-[9px] font-medium text-slate-500 dark:text-slate-400">Deposited today</p>
            </div>
            <div class="w-9 h-9 rounded-sm bg-lime-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-circle-arrow-down text-xs"></i>
            </div>
        </div>

        <!-- Total Topups -->
        <div class="p-3.5 rounded-md bg-gradient-to-br from-green-500/10 via-white to-white border border-green-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-3">
            <div class="space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-green-900 dark:text-green-300 block">Total Topups</span>
                <div id="valTotalTopups" class="text-lg font-black text-green-950 dark:text-slate-100">
                    KES <?php echo number_format($total_b2c_topups, 0); ?>
                </div>
                <p class="text-[9px] font-medium text-slate-500 dark:text-slate-400">All-time topups</p>
            </div>
            <div class="w-9 h-9 rounded-sm bg-green-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-piggy-bank text-xs"></i>
            </div>
        </div>

        <!-- Today Processed Payouts -->
        <div class="p-3.5 rounded-md bg-gradient-to-br from-emerald-500/10 via-white to-white border border-emerald-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-3">
            <div class="space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-900 dark:text-emerald-300 block">Today Payouts</span>
                <div id="valTodayPayouts" class="text-lg font-black text-emerald-950 dark:text-slate-100">
                    KES <?php echo number_format($today_b2c_payouts, 0); ?>
                </div>
                <p class="text-[9px] font-medium text-slate-500 dark:text-slate-400">Disbursed today</p>
            </div>
            <div class="w-9 h-9 rounded-sm bg-emerald-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-calendar-day text-xs"></i>
            </div>
        </div>

        <!-- Total Processed Payouts -->
        <div class="p-3.5 rounded-md bg-gradient-to-br from-teal-500/10 via-white to-white border border-teal-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-3">
            <div class="space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-teal-900 dark:text-teal-300 block">Total Payouts</span>
                <div id="valTotalPayouts" class="text-lg font-black text-teal-950 dark:text-slate-100">
                    KES <?php echo number_format($total_b2c_payouts, 0); ?>
                </div>
                <p class="text-[9px] font-medium text-slate-500 dark:text-slate-400">All-time payouts</p>
            </div>
            <div class="w-9 h-9 rounded-sm bg-teal-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-paper-plane text-xs"></i>
            </div>
        </div>

        <!-- Admin Utility Balance -->
        <div class="p-3.5 rounded-md bg-gradient-to-br from-teal-500/10 via-white to-white border border-teal-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-3">
            <div class="space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-teal-900 dark:text-teal-300 block">Utility A/C</span>
                <div id="valUtility" class="text-lg font-black text-teal-950 dark:text-slate-100">
                    KES <?php echo number_format($admin_b2c_balance, 0); ?>
                </div>
                <p class="text-[9px] font-medium text-slate-500 dark:text-slate-400">B2C Disbursal Funds</p>
            </div>
            <div class="w-9 h-9 rounded-sm bg-teal-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-landmark text-xs"></i>
            </div>
        </div>

        <!-- Working Account Balance -->
        <div class="p-3.5 rounded-md bg-gradient-to-br from-teal-500/10 via-white to-white border border-teal-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-3">
            <div class="space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-teal-900 dark:text-teal-300 block">Working A/C</span>
                <div id="valWorking" class="text-lg font-black text-teal-950 dark:text-slate-100">
                    KES <?php echo number_format($working_balance, 0); ?>
                </div>
                <p class="text-[9px] font-medium text-slate-500 dark:text-slate-400">Settlement Wallet</p>
            </div>
            <div class="w-9 h-9 rounded-sm bg-teal-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-briefcase text-xs"></i>
            </div>
        </div>

        <!-- Charges Account Balance -->
        <div class="p-3.5 rounded-md bg-gradient-to-br from-teal-500/10 via-white to-white border border-teal-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-3">
            <div class="space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-teal-900 dark:text-teal-300 block">Charges A/C</span>
                <div id="valCharges" class="text-lg font-black text-teal-950 dark:text-slate-100">
                    KES <?php echo number_format($charges_balance, 0); ?>
                </div>
                <p class="text-[9px] font-medium text-slate-500 dark:text-slate-400">Tariff Fee Balance</p>
            </div>
            <div class="w-9 h-9 rounded-sm bg-teal-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-receipt text-xs"></i>
            </div>
        </div>

        <!-- Total Users B2C Wallet Balances -->
        <div class="p-3.5 rounded-md bg-gradient-to-br from-teal-500/10 via-white to-white border border-teal-300/80 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-between gap-3">
            <div class="space-y-0.5">
                <span class="text-[10px] font-bold uppercase tracking-wider text-teal-900 dark:text-teal-300 block">Users B2C Bal</span>
                <div id="valUsersB2C" class="text-lg font-black text-teal-950 dark:text-slate-100">
                    KES <?php echo number_format($total_users_b2c_balance, 0); ?>
                </div>
                <p class="text-[9px] font-medium text-slate-500 dark:text-slate-400">Aggregated wallets</p>
            </div>
            <div class="w-9 h-9 rounded-sm bg-teal-600 text-white shadow-xs flex items-center justify-center shrink-0">
                <i class="iclass fa-solid fa-wallet text-xs"></i>
            </div>
        </div>
    </div>

    <!-- Analytics Section: Transaction Status Pie Chart with C2B/B2C Toggle -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="p-5 rounded-md bg-white border border-slate-200 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex flex-col justify-between space-y-4">
            <div>
                <div class="flex items-center justify-between gap-2 flex-wrap mb-2">
                    <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-2">
                        <i class="iclass fa-solid fa-chart-pie text-teal-500"></i> Transaction Outcomes
                    </h3>
                    
                    <!-- Segmented Toggle Switch -->
                    <div class="inline-flex p-0.5 bg-slate-100 dark:bg-slate-800/80 rounded-md border border-slate-200 dark:border-slate-700">
                        <button id="btnToggleC2B" onclick="switchOutcomeMode('C2B')" class="px-2.5 py-1 text-[11px] font-bold rounded-sm transition-all bg-teal-500 text-slate-950 shadow-xs">
                            C2B
                        </button>
                        <button id="btnToggleB2C" onclick="switchOutcomeMode('B2C')" class="px-2.5 py-1 text-[11px] font-bold rounded-sm transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200">
                            B2C
                        </button>
                    </div>
                </div>

                <p id="outcomeSubtext" class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Visual breakdown comparing C2B successful payments against cancellations and system failures.
                </p>
            </div>

            <div class="space-y-2 text-xs font-semibold">
                <div class="flex items-center justify-between p-2 rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20">
                    <span id="labelSuccess">Successful C2B Payins</span>
                    <span id="valSuccessCount" class="font-black"><?php echo number_format($count_success); ?></span>
                </div>
                <div class="flex items-center justify-between p-2 rounded bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20">
                    <span id="labelCancelled">Cancelled Transactions</span>
                    <span id="valCancelledCount" class="font-black"><?php echo number_format($count_cancelled); ?></span>
                </div>
                <div class="flex items-center justify-between p-2 rounded bg-rose-500/10 text-rose-700 dark:text-rose-400 border border-rose-500/20">
                    <span id="labelFailed">Failed / Insufficient Funds</span>
                    <span id="valFailedCount" class="font-black"><?php echo number_format($count_failed); ?></span>
                </div>
            </div>
        </div>

        <div class="lg:col-span-2 p-5 rounded-md bg-white border border-slate-200 dark:bg-[#0f172a] dark:border-white/10 shadow-xs flex items-center justify-center">
            <div class="w-full max-w-xs h-64">
                <canvas id="transactionPieChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Completely Silent Real-Time Stream Script (All Cards Updated Every 1s) -->
<script>
    // Outcome Datasets
    const outcomeData = {
        C2B: {
            subtext: "Visual breakdown comparing C2B successful payments against cancellations and system failures.",
            labelSuccess: "Successful C2B Payins",
            labelCancelled: "Cancelled Transactions",
            labelFailed: "Failed / DNS / Network",
            success: <?php echo $count_success; ?>,
            cancelled: <?php echo $count_cancelled; ?>,
            failed: <?php echo $count_failed; ?>
        },
        B2C: {
            subtext: "Visual breakdown comparing B2C payout disbursements against cancellations and account errors.",
            labelSuccess: "Successful B2C Payouts",
            labelCancelled: "Cancelled / Declined",
            labelFailed: "Failed / Insufficient Funds",
            success: <?php echo $b2c_count_success; ?>,
            cancelled: <?php echo $b2c_count_cancelled; ?>,
            failed: <?php echo $b2c_count_failed; ?>
        }
    };

    let pieChartInstance = null;
    let currentMode = 'C2B';

    function switchOutcomeMode(mode) {
        currentMode = mode;
        const data = outcomeData[mode];

        const btnC2B = document.getElementById('btnToggleC2B');
        const btnB2C = document.getElementById('btnToggleB2C');

        if (mode === 'C2B') {
            btnC2B.className = "px-2.5 py-1 text-[11px] font-bold rounded-sm transition-all bg-teal-500 text-slate-950 shadow-xs";
            btnB2C.className = "px-2.5 py-1 text-[11px] font-bold rounded-sm transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200";
        } else {
            btnB2C.className = "px-2.5 py-1 text-[11px] font-bold rounded-sm transition-all bg-teal-500 text-slate-950 shadow-xs";
            btnC2B.className = "px-2.5 py-1 text-[11px] font-bold rounded-sm transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200";
        }

        document.getElementById('outcomeSubtext').textContent = data.subtext;
        document.getElementById('labelSuccess').textContent = data.labelSuccess;
        document.getElementById('labelCancelled').textContent = data.labelCancelled;
        document.getElementById('labelFailed').textContent = data.labelFailed;

        document.getElementById('valSuccessCount').textContent = Number(data.success).toLocaleString();
        document.getElementById('valCancelledCount').textContent = Number(data.cancelled).toLocaleString();
        document.getElementById('valFailedCount').textContent = Number(data.failed).toLocaleString();

        if (pieChartInstance) {
            pieChartInstance.data.datasets[0].data = [data.success, data.cancelled, data.failed];
            pieChartInstance.update();
        }
    }

    // Silent background polling stream for ALL dashboard cards every 1 second
    function startSilentGlobalStream() {
        const triggerSync = () => {
            fetch('/admin/fetch_admin_balance.php?action=sync').catch(() => {});
        };

        // 1. Fire live Daraja sync request once on load and every 15s safely
        triggerSync();
        setInterval(triggerSync, 15000);

        // 2. Poll every 1 second (1000ms) to update ALL cards silently without page reloads
        setInterval(() => {
            // Update Multi-Wallet Balances
            fetch('/admin/fetch_admin_balance.php')
                .then(res => res.json())
                .then(bal => {
                    if (bal.status === 'success' && bal.has_balance) {
                        const uEl = document.getElementById('valUtility');
                        const wEl = document.getElementById('valWorking');
                        const cEl = document.getElementById('valCharges');

                        if (uEl && bal.utility !== null) uEl.textContent = 'KES ' + Number(bal.utility).toLocaleString('en-US', {maximumFractionDigits: 0});
                        if (wEl && bal.working !== null) wEl.textContent = 'KES ' + Number(bal.working).toLocaleString('en-US', {maximumFractionDigits: 0});
                        if (cEl && bal.charges !== null) cEl.textContent = 'KES ' + Number(bal.charges).toLocaleString('en-US', {maximumFractionDigits: 0});
                    }
                })
                .catch(() => {});

            // Update C2B Payins, Topups, Payouts, Fees Revenue, Users, Webhooks, and Settlements
            fetch('/admin/fetch_admin_stats.php')
                .then(res => res.json())
                .then(stats => {
                    if (stats.status === 'success') {
                        const setCardVal = (id, prefix, val) => {
                            const el = document.getElementById(id);
                            if (el && val !== undefined) {
                                el.textContent = prefix + Number(val).toLocaleString('en-US', {maximumFractionDigits: 0});
                            }
                        };

                        setCardVal('valDailyPayins', 'KES ', stats.daily_volume);
                        setCardVal('valMonthlyPayins', 'KES ', stats.monthly_volume);
                        setCardVal('valTotalVolume', 'KES ', stats.total_volume);
                        setCardVal('valUsers', '', stats.total_users);
                        setCardVal('valWebhooks', '', stats.total_webhooks_sent);
                        setCardVal('valSettlements', '', stats.total_active_settlements);
                        setCardVal('valUsersB2C', 'KES ', stats.total_users_b2c_balance);
                        setCardVal('valTodayPayouts', 'KES ', stats.today_b2c_payouts);
                        setCardVal('valTotalPayouts', 'KES ', stats.total_b2c_payouts);
                        setCardVal('valTodayTopups', 'KES ', stats.today_b2c_topups);
                        setCardVal('valTotalTopups', 'KES ', stats.total_b2c_topups);
                        setCardVal('valTopupFees', 'KES ', stats.total_topup_fees);
                        setCardVal('valPayoutFees', 'KES ', stats.total_payout_fees);
                        setCardVal('valTotalRevenueFees', 'KES ', stats.total_revenue_fees);
                    }
                })
                .catch(() => {});
        }, 1000);
    }

    document.addEventListener("DOMContentLoaded", function () {
        // Start silent background updating
        startSilentGlobalStream();

        // Pie Chart Initialization
        const ctx = document.getElementById('transactionPieChart').getContext('2d');
        pieChartInstance = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Success', 'Cancelled', 'Failed / Error'],
                datasets: [{
                    data: [
                        outcomeData[currentMode].success, 
                        outcomeData[currentMode].cancelled, 
                        outcomeData[currentMode].failed
                    ],
                    backgroundColor: ['#10b981', '#f59e0b', '#f43f5e'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: document.documentElement.classList.contains('dark') ? '#cbd5e1' : '#475569',
                            font: { size: 11, family: 'inherit' },
                            boxWidth: 12,
                            padding: 16
                        }
                    }
                },
                cutout: '65%'
            }
        });
    });
</script>

<?php 
include 'admin-frame/footer.php';
?>