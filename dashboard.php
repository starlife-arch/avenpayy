<?php
// dashboard.php
session_start();
require_once 'database_config/db.php';

// Access control
if (!isset($_SESSION['user_id'])) {
    header("Location: /login");
    exit();
}

$user_id = (int) $_SESSION['user_id'];

// Fetch user details
$user_stmt = $conn->prepare("
    SELECT username, b2c_wallet_balance, total_b2c_withdrawn
    FROM users
    WHERE id = ?
");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc() ?: [];
$user_stmt->close();

$raw_name = $user_data['username'] ?? 'Merchant';
$name_parts = explode(' ', trim($raw_name));
$display_name = $name_parts[0] ?? 'Merchant';
$b2c_wallet_balance = (float) ($user_data['b2c_wallet_balance'] ?? 0);
$total_b2c_withdrawn = (float) ($user_data['total_b2c_withdrawn'] ?? 0);

// Fetch configured Paybill shortcode
$paybill_shortcode = '';

$paybill_stmt = $conn->prepare("
    SELECT shortcode
    FROM daraja_b2c_settings
    ORDER BY id DESC
    LIMIT 1
");

if ($paybill_stmt) {
    $paybill_stmt->execute();
    $paybill_data = $paybill_stmt->get_result()->fetch_assoc() ?: [];
    $paybill_shortcode = trim((string) ($paybill_data['shortcode'] ?? ''));
    $paybill_stmt->close();
}

// Aggregate transaction stats
$stmt = $conn->prepare("
    SELECT
        SUM(CASE WHEN status = 'SUCCESS' THEN amount ELSE 0 END) AS total_volume,
        SUM(CASE WHEN DATE(transaction_date) = CURDATE() AND status = 'SUCCESS' THEN amount ELSE 0 END) AS daily_volume,
        SUM(CASE WHEN MONTH(transaction_date) = MONTH(CURDATE())
                  AND YEAR(transaction_date) = YEAR(CURDATE())
                  AND status = 'SUCCESS' THEN amount ELSE 0 END) AS monthly_volume,
        COUNT(CASE WHEN status = 'SUCCESS' THEN 1 END) AS count_success,
        COUNT(CASE WHEN status != 'SUCCESS'
                    AND mpesa_receipt_number != 'Request Cancelled by user.' THEN 1 END) AS count_failed,
        COUNT(CASE WHEN mpesa_receipt_number = 'Request Cancelled by user.' THEN 1 END) AS count_cancelled,
        COUNT(*) AS count_total
    FROM mpesa_transactions
    WHERE user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

// Today's processed B2C withdrawals
$today_b2c = 0;
$b2c_stmt = $conn->prepare("
    SELECT SUM(transaction_amount) AS today_total
    FROM mpesa_b2c_transactions
    WHERE user_id = ?
      AND result_code = 0
      AND DATE(created_at) = CURDATE()
");

if ($b2c_stmt) {
    $b2c_stmt->bind_param("i", $user_id);
    $b2c_stmt->execute();
    $b2c_res = $b2c_stmt->get_result()->fetch_assoc() ?: [];
    $today_b2c = (float) ($b2c_res['today_total'] ?? 0);
    $b2c_stmt->close();
}

// Transaction status percentages
$total_tx_count = max((int) ($stats['count_total'] ?? 0), 1);
$pct_success = round(((int) ($stats['count_success'] ?? 0) / $total_tx_count) * 100, 1);
$pct_failed = round(((int) ($stats['count_failed'] ?? 0) / $total_tx_count) * 100, 1);
$pct_cancelled = round(((int) ($stats['count_cancelled'] ?? 0) / $total_tx_count) * 100, 1);

// Active settlements
$settle_count_stmt = $conn->prepare("
    SELECT COUNT(*) AS active_count
    FROM settlements
    WHERE user_id = ?
      AND is_active = 1
      AND LOWER(status) = 'active'
      AND (expiry_date IS NULL OR expiry_date > NOW())
");
$settle_count_stmt->bind_param("i", $user_id);
$settle_count_stmt->execute();
$settlement_data = $settle_count_stmt->get_result()->fetch_assoc() ?: [];
$total_active_settlements = (int) ($settlement_data['active_count'] ?? 0);
$settle_count_stmt->close();

// Webhook analytics
$webhook_stmt = $conn->prepare("
    SELECT
        COUNT(CASE WHEN w.status = 'SENT' THEN 1 END) AS webhook_sent,
        COUNT(CASE WHEN w.status != 'SENT' THEN 1 END) AS webhook_failed,
        COUNT(*) AS webhook_total
    FROM webhook_queue w
    JOIN mpesa_transactions t ON w.transaction_id = t.id
    WHERE t.user_id = ?
");
$webhook_stmt->bind_param("i", $user_id);
$webhook_stmt->execute();
$webhook_stats = $webhook_stmt->get_result()->fetch_assoc() ?: [];
$webhook_stmt->close();

$total_wh_count = max((int) ($webhook_stats['webhook_total'] ?? 0), 1);
$pct_wh_sent = round(((int) ($webhook_stats['webhook_sent'] ?? 0) / $total_wh_count) * 100, 1);
$pct_wh_failed = round(100 - $pct_wh_sent, 1);

// Pagination
$limit = 25;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max($page, 1);
$offset = ($page - 1) * $limit;

// Preserve the latest-50 transaction window
$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM (
        SELECT id
        FROM mpesa_transactions
        WHERE user_id = ?
        ORDER BY transaction_date DESC
        LIMIT 50
    ) AS limited_tx
");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$total_rows = (int) (($count_stmt->get_result()->fetch_assoc()['total'] ?? 0));
$count_stmt->close();

$total_pages = (int) ceil($total_rows / $limit);

if ($total_pages > 0 && $page > $total_pages) {
    $page = $total_pages;
    $offset = ($page - 1) * $limit;
}

require_once __DIR__ . '/frame/header.php';
?>

<style>
    .iclass {
        display: inline-flex;
        flex: 0 0 22px;
        width: 22px;
        height: 22px;
        align-items: center;
        justify-content: center;
        color: currentColor;
        line-height: 1;
    }

    .iclass > svg {
        display: block;
        width: 19px;
        height: 19px;
        fill: none !important;
        stroke: currentColor !important;
        stroke-width: 1.7;
        stroke-linecap: round;
        stroke-linejoin: round;
        shape-rendering: geometricPrecision;
    }

    .iclass > svg *,
    .iclass > svg path,
    .iclass > svg circle,
    .iclass > svg rect,
    .iclass > svg line,
    .iclass > svg polyline,
    .iclass > svg polygon,
    .iclass > svg ellipse {
        fill: none !important;
        stroke: currentColor !important;
        vector-effect: non-scaling-stroke;
    }

    .metric-icon .iclass {
        flex-basis: 24px;
        width: 24px;
        height: 24px;
    }

    .metric-icon .iclass > svg {
        width: 21px;
        height: 21px;
        stroke-width: 1.55;
        shape-rendering: geometricPrecision;
    }

    .iclass-small {
        flex-basis: 14px;
        width: 14px;
        height: 14px;
    }

    .iclass-small > svg {
        width: 13px;
        height: 13px;
    }

    .iclass-tiny {
        flex-basis: 11px;
        width: 11px;
        height: 11px;
    }

    .iclass-tiny > svg {
        width: 10px;
        height: 10px;
    }

    .search-icon {
        position: absolute;
        left: 12px;
        top: 7px;
    }
</style>

<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="mb-6">
        <!-- Welcome Banner -->
        <div class="relative bg-gradient-to-r from-white via-slate-100 to-white border border-slate-300/80 p-5 rounded-2xl shadow-xl mb-5 text-left overflow-hidden">
            <div class="absolute -right-12 -bottom-12 w-36 h-36 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <h1 class="text-lg font-bold text-slate-900 flex items-center relative z-10">
                <span class="iclass text-teal-600 mr-2.5" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M8 12V5a1.5 1.5 0 0 1 3 0v6-8a1.5 1.5 0 0 1 3 0v8-6a1.5 1.5 0 0 1 3 0v7-4a1.5 1.5 0 0 1 3 0v7a8 8 0 0 1-8 8h-1a7 7 0 0 1-5.5-2.7L3 16a1.5 1.5 0 0 1 2.3-1.9L8 17"/>
                    </svg>
                </span>
                Hello, <?= htmlspecialchars($display_name, ENT_QUOTES, 'UTF-8') ?>!
            </h1>

            <p class="text-slate-500 text-xs mt-1 ml-2.5 relative z-10">
                Welcome back to AvenPay. Monitor real-time collections and automated disbursements data.
            </p>

            <!-- Expandable Paybill Card -->
            <div class="relative z-10 mt-4 rounded-xl border border-slate-300/80 bg-slate-50/50 p-4">
                <button
                    type="button"
                    id="paybillToggle"
                    class="w-full flex items-center justify-between text-left"
                    aria-expanded="false"
                    aria-controls="paybillContent"
                >
                    <span>
                        <span class="block text-sm font-bold text-slate-900">Get your own Paybill</span>
                        <span class="block text-xs text-slate-500 mt-1">
                            click to view payment account details for your merchant wallet.
                        </span>
                    </span>

                    <span id="paybillArrow" class="ml-3 text-teal-600 transition-transform duration-200" aria-hidden="true">
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m6 9 6 6 6-6"/>
                        </svg>
                    </span>
                </button>

                <div id="paybillContent" class="hidden pt-4">
                    <?php if ($paybill_shortcode !== ''): ?>
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <div class="rounded-lg border border-slate-300 bg-white px-3 py-2">
                                <span class="block text-[10px] uppercase tracking-wide text-slate-500">Paybill number</span>
                                <span id="paybillNumber" class="text-sm font-bold text-teal-700">
                                    <?= htmlspecialchars($paybill_shortcode, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </div>

                            <button
                                type="button"
                                id="copyPaybillButton"
                                class="inline-flex items-center justify-center gap-2 rounded-lg bg-teal-500 px-3 py-2 text-xs font-bold text-slate-950 hover:bg-teal-400 transition"
                            >
                                <span class="iclass iclass-small" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <rect x="8" y="8" width="12" height="12" rx="2"/>
                                        <path d="M16 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h3"/>
                                    </svg>
                                </span>
                                <span id="copyPaybillText">Copy Paybill</span>
                            </button>
                        </div>
                    <?php else: ?>
                        <p class="text-xs text-amber-700">
                            Paybill details are not configured yet. Please contact support.
                        </p>
                    <?php endif; ?>

                    <div class="mt-3 border-t border-slate-200 pt-3">
                        <span class="block text-[10px] uppercase tracking-wide text-slate-500">Account number</span>
                        <p class="mt-1 text-xs text-slate-600">
                            Enter your <strong class="text-slate-900">wallet token</strong> as the account number.
                            Find your unique merchant wallet identifier in the dashboard menu.
                        </p>
                    </div>

                    <p id="copyPaybillMessage" class="hidden mt-2 text-xs text-emerald-600" role="status" aria-live="polite"></p>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <a href="/api/" class="bg-white/80 p-3.5 rounded-xl border border-slate-200 border-l-[4px] border-l-teal-400 hover:border-teal-500/60 hover:bg-slate-100/50 transition-all duration-200 flex items-center gap-2.5 shadow-md group">
                <div class="w-8 h-8 bg-teal-500/10 border border-teal-500/20 rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform">
                    <span class="iclass text-teal-600" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <rect x="6" y="6" width="12" height="12" rx="2"/>
                            <path d="M9 9h6v6H9zM9 2v4m6-4v4M9 18v4m6-4v4M2 9h4m-4 6h4m12-6h4m-4 6h4"/>
                        </svg>
                    </span>
                </div>
                <span class="text-slate-700 font-semibold text-xs group-hover:text-slate-900 transition-colors">Test Stk Push</span>
            </a>

            <a href="/api/docs" class="bg-white/80 p-3.5 rounded-xl border border-slate-200 border-l-[4px] border-l-amber-400 hover:border-amber-500/60 hover:bg-slate-100/50 transition-all duration-200 flex items-center gap-2.5 shadow-md group">
                <div class="w-8 h-8 bg-amber-500/10 border border-amber-500/20 rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform">
                    <span class="iclass text-amber-600" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M12 7c-2-2-5-2-9-2v14c4 0 7 0 9 2 2-2 5-2 9-2V5c-4 0-7 0-9 2Z"/>
                            <path d="M12 7v14"/>
                        </svg>
                    </span>
                </div>
                <span class="text-slate-700 font-semibold text-xs group-hover:text-slate-900 transition-colors">Documentation</span>
            </a>

            <a href="/settlement" class="bg-white/80 p-3.5 rounded-xl border border-slate-200 border-l-[4px] border-l-slate-400 hover:border-slate-500 hover:bg-slate-100/50 transition-all duration-200 flex items-center gap-2.5 shadow-md group">
                <div class="w-8 h-8 bg-slate-100 border border-slate-300 rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform">
                    <span class="iclass text-slate-600" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="m3 9 9-6 9 6H3Z"/>
                            <path d="M5 10v9m5-9v9m4-9v9m5-9v9M3 21h18M3 19h18"/>
                        </svg>
                    </span>
                </div>
                <span class="text-slate-700 font-semibold text-xs group-hover:text-slate-900 transition-colors">Add Settlement</span>
            </a>

            <a href="https://wa.me/254106624924" class="bg-white/80 p-3.5 rounded-xl border border-slate-200 border-l-[4px] border-l-emerald-400 hover:border-emerald-500/60 hover:bg-slate-100/50 transition-all duration-200 flex items-center gap-2.5 shadow-md group">
                <div class="w-8 h-8 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center justify-center group-hover:scale-105 transition-transform">
                    <span class="iclass text-emerald-600" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M3 13v-2a9 9 0 0 1 18 0v2"/>
                            <rect x="3" y="12" width="4" height="7" rx="2"/>
                            <rect x="17" y="12" width="4" height="7" rx="2"/>
                            <path d="M17 20a5 5 0 0 1-5 2h-2"/>
                        </svg>
                    </span>
                </div>
                <span class="text-slate-700 font-semibold text-xs group-hover:text-slate-900 transition-colors">Support</span>
            </a>
        </div>
    </div>

    <!-- Earnings & Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7 gap-3 mb-6">
        <div class="bg-gradient-to-br from-teal-950 via-white to-white p-3.5 rounded-xl border border-teal-500/40 border-l-[4px] border-l-teal-400 shadow-lg shadow-teal-950/30 flex justify-between items-center transition-all">
            <div>
                <span class="text-teal-700 text-[10px] font-bold block mb-1 tracking-wide">Today's Volume</span>
                <div class="text-slate-900 text-sm font-black leading-tight">KES <?= number_format((float) ($stats['daily_volume'] ?? 0), 0) ?></div>
                <span class="text-[9px] text-teal-600 font-medium block mt-1 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 bg-teal-400 rounded-full animate-pulse"></span> Collected today
                </span>
            </div>
            <div class="metric-icon w-10 h-10 bg-teal-500/20 border border-teal-400/40 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-teal-500/30">
                <span class="iclass text-teal-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <ellipse cx="9" cy="6" rx="6" ry="3"/>
                        <path d="M3 6v5c0 1.7 2.7 3 6 3 1 0 2-.1 2.8-.4M3 11v5c0 1.7 2.7 3 6 3"/>
                        <path d="M15 11c3.3 0 6 1.3 6 3s-2.7 3-6 3-6-1.3-6-3 2.7-3 6-3Z"/>
                        <path d="M9 14v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/>
                    </svg>
                </span>
            </div>
        </div>

        <div class="bg-gradient-to-br from-amber-950 via-white to-white p-3.5 rounded-xl border border-amber-500/40 border-l-[4px] border-l-amber-400 shadow-lg shadow-amber-950/30 flex justify-between items-center transition-all">
            <div>
                <span class="text-amber-700 text-[10px] font-bold block mb-1 tracking-wide">This Month Volume</span>
                <div class="text-slate-900 text-sm font-black leading-tight">KES <?= number_format((float) ($stats['monthly_volume'] ?? 0), 0) ?></div>
                <span class="text-[9px] text-amber-600 font-medium block mt-1">Current month</span>
            </div>
            <div class="metric-icon w-10 h-10 bg-amber-500/20 border border-amber-400/40 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-amber-500/30">
                <span class="iclass text-amber-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M4 6h15a2 2 0 0 1 2 2v12H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h13"/>
                        <path d="M21 11h-6a2 2 0 0 0 0 4h6"/>
                        <path d="M16 13h.01"/>
                    </svg>
                </span>
            </div>
        </div>

        <div class="bg-gradient-to-br from-emerald-950 via-white to-white p-3.5 rounded-xl border border-emerald-500/40 border-l-[4px] border-l-emerald-400 shadow-lg shadow-emerald-950/30 flex justify-between items-center transition-all">
            <div>
                <span class="text-emerald-700 text-[10px] font-bold block mb-1 tracking-wide">Total Volume</span>
                <div class="text-slate-900 text-sm font-black leading-tight">KES <?= number_format((float) ($stats['total_volume'] ?? 0), 0) ?></div>
                <span class="text-[9px] text-emerald-600 font-medium block mt-1">All-time volume</span>
            </div>
            <div class="metric-icon w-10 h-10 bg-emerald-500/20 border border-emerald-400/40 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-emerald-500/30">
                <span class="iclass text-emerald-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="16" rx="2"/>
                        <circle cx="12" cy="12" r="4"/>
                        <path d="M12 10v4m-2-2h4M6 8h1m10 8h1"/>
                    </svg>
                </span>
            </div>
        </div>

        <div class="bg-gradient-to-br from-teal-950 via-white to-white p-3.5 rounded-xl border border-teal-500/40 border-l-[4px] border-l-teal-300 shadow-lg shadow-teal-950/30 flex justify-between items-center transition-all">
            <div>
                <span class="text-teal-700 text-[10px] font-bold block mb-1 tracking-wide">B2C Wallet Balance</span>
                <div class="text-slate-900 text-sm font-black leading-tight">KES <?= number_format($b2c_wallet_balance, 0) ?></div>
                <span class="text-[9px] text-teal-600 font-medium block mt-1">Available for payouts</span>
            </div>
            <div class="metric-icon w-10 h-10 bg-teal-400/20 border border-teal-400/40 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-teal-500/30">
                <span class="iclass text-teal-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M7 7h13l-3-3m3 3-3 3M17 17H4l3 3m-3-3 3-3"/>
                        <path d="M12 10v4m2-2h-4"/>
                    </svg>
                </span>
            </div>
        </div>

        <div class="bg-gradient-to-br from-teal-950 via-white to-white p-3.5 rounded-xl border border-teal-500/40 border-l-[4px] border-l-teal-400 shadow-lg shadow-teal-950/30 flex justify-between items-center transition-all">
            <div>
                <span class="text-teal-700 text-[10px] font-bold block mb-1 tracking-wide">Today's B2C Paid Out</span>
                <div class="text-slate-900 text-sm font-black leading-tight">KES <?= number_format($today_b2c, 0) ?></div>
                <span class="text-[9px] text-teal-600 font-medium block mt-1 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 bg-teal-400 rounded-full animate-pulse"></span> Processed today
                </span>
            </div>
            <div class="metric-icon w-10 h-10 bg-teal-500/20 border border-teal-400/40 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-teal-500/30">
                <span class="iclass text-teal-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="m22 2-7 20-4-9-9-4 20-7Z"/>
                        <path d="M22 2 11 13"/>
                    </svg>
                </span>
            </div>
        </div>

        <div class="bg-gradient-to-br from-teal-950 via-white to-white p-3.5 rounded-xl border border-teal-500/40 border-l-[4px] border-l-teal-300 shadow-lg shadow-teal-950/30 flex justify-between items-center transition-all">
            <div>
                <span class="text-teal-700 text-[10px] font-bold block mb-1 tracking-wide">Total B2C Withdrawn</span>
                <div class="text-slate-900 text-sm font-black leading-tight">KES <?= number_format($total_b2c_withdrawn, 0) ?></div>
                <span class="text-[9px] text-teal-700 font-medium block mt-1">All-time payouts sent</span>
            </div>
            <div class="metric-icon w-10 h-10 bg-teal-500/20 border border-teal-400/40 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-teal-500/30">
                <span class="iclass text-teal-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M3 17 9 11l4 4 8-9"/>
                        <path d="M15 6h6v6"/>
                    </svg>
                </span>
            </div>
        </div>

        <div class="bg-gradient-to-br from-rose-950 via-white to-white p-3.5 rounded-xl border border-rose-500/40 border-l-[4px] border-l-rose-400 shadow-lg shadow-rose-950/30 flex justify-between items-center transition-all">
            <div>
                <span class="text-rose-700 text-[10px] font-bold block mb-1 tracking-wide">Active Settlements</span>
                <div class="text-slate-900 text-sm font-black leading-tight"><?= number_format($total_active_settlements) ?></div>
                <span class="text-[9px] text-rose-600 font-medium block mt-1">Non-expired &amp; active</span>
            </div>
            <div class="metric-icon w-10 h-10 bg-rose-500/20 border border-rose-400/40 rounded-lg flex items-center justify-center shrink-0 shadow-sm shadow-rose-500/30">
                <span class="iclass text-rose-700" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="m3 9 9-6 9 6H3Z"/>
                        <path d="M5 10v9m5-9v9m4-9v9m5-9v9M3 21h18M3 19h18"/>
                    </svg>
                </span>
            </div>
        </div>
    </div>

    <!-- Transaction Status and Webhook Analytics -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
        <div class="bg-white/80 border border-slate-200/90 border-l-[4px] border-l-teal-400 rounded-xl p-5 shadow-lg flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3.5 pb-2.5 border-b border-slate-200">
                    <h3 class="text-slate-900 font-bold text-xs flex items-center">
                        <span class="iclass text-teal-600 mr-2" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <path d="M12 3v9h9A9 9 0 1 1 12 3Z"/>
                                <path d="M15 3.5A9 9 0 0 1 20.5 9H15V3.5Z"/>
                            </svg>
                        </span>
                        Transaction Status Breakdown
                    </h3>
                    <span class="text-slate-500 text-[11px]">
                        Total: <strong class="text-slate-900"><?= number_format((int) ($stats['count_total'] ?? 0)) ?></strong>
                    </span>
                </div>

                <div class="space-y-3">
                    <div class="bg-slate-50/60 p-3 rounded-lg border border-slate-200/80">
                        <div class="flex justify-between text-xs mb-1.5">
                            <span class="text-emerald-600 font-semibold flex items-center gap-1.5">
                                <span class="iclass iclass-small" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="9"/>
                                        <path d="m8 12 3 3 5-6"/>
                                    </svg>
                                </span>
                                Successful
                            </span>
                            <span class="text-slate-900 font-bold"><?= number_format((int) ($stats['count_success'] ?? 0)) ?> (<?= $pct_success ?>%)</span>
                        </div>
                        <div class="w-full bg-slate-100/80 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-emerald-500 h-1.5 rounded-full transition-all duration-500" style="width: <?= $pct_success ?>%;"></div>
                        </div>
                    </div>

                    <div class="bg-slate-50/60 p-3 rounded-lg border border-slate-200/80">
                        <div class="flex justify-between text-xs mb-1.5">
                            <span class="text-rose-600 font-semibold flex items-center gap-1.5">
                                <span class="iclass iclass-small" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="9"/>
                                        <path d="m9 9 6 6m0-6-6 6"/>
                                    </svg>
                                </span>
                                Failed
                            </span>
                            <span class="text-slate-900 font-bold"><?= number_format((int) ($stats['count_failed'] ?? 0)) ?> (<?= $pct_failed ?>%)</span>
                        </div>
                        <div class="w-full bg-slate-100/80 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-rose-500 h-1.5 rounded-full transition-all duration-500" style="width: <?= $pct_failed ?>%;"></div>
                        </div>
                    </div>

                    <div class="bg-slate-50/60 p-3 rounded-lg border border-slate-200/80">
                        <div class="flex justify-between text-xs mb-1.5">
                            <span class="text-amber-600 font-semibold flex items-center gap-1.5">
                                <span class="iclass iclass-small" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <circle cx="12" cy="12" r="9"/>
                                        <path d="m6 6 12 12"/>
                                    </svg>
                                </span>
                                Cancelled by User
                            </span>
                            <span class="text-slate-900 font-bold"><?= number_format((int) ($stats['count_cancelled'] ?? 0)) ?> (<?= $pct_cancelled ?>%)</span>
                        </div>
                        <div class="w-full bg-slate-100/80 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-amber-500 h-1.5 rounded-full transition-all duration-500" style="width: <?= $pct_cancelled ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white/80 border border-slate-200/90 border-l-[4px] border-l-teal-400 rounded-xl p-5 shadow-lg flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3.5 pb-2.5 border-b border-slate-200">
                    <h3 class="text-slate-900 font-bold text-xs flex items-center">
                        <span class="iclass text-teal-600 mr-2" aria-hidden="true">
                            <svg viewBox="0 0 24 24">
                                <circle cx="12" cy="5" r="2"/>
                                <circle cx="5" cy="19" r="2"/>
                                <circle cx="19" cy="19" r="2"/>
                                <path d="M12 7v5M10.5 13 6 17m7.5-4 4 4"/>
                            </svg>
                        </span>
                        Webhook Delivery Analytics
                    </h3>
                    <span class="text-slate-500 text-[11px]">
                        Dispatched: <strong class="text-slate-900"><?= number_format((int) ($webhook_stats['webhook_total'] ?? 0)) ?></strong>
                    </span>
                </div>

                <div class="space-y-3">
                    <div class="bg-slate-50/60 p-3 rounded-lg border border-slate-200/80">
                        <div class="flex justify-between text-xs mb-1.5">
                            <span class="text-teal-600 font-semibold flex items-center gap-1.5">
                                <span class="iclass iclass-small" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="m22 2-7 20-4-9-9-4 20-7Z"/>
                                        <path d="M22 2 11 13"/>
                                    </svg>
                                </span>
                                Successfully Received
                            </span>
                            <span class="text-slate-900 font-bold"><?= number_format((int) ($webhook_stats['webhook_sent'] ?? 0)) ?> (<?= $pct_wh_sent ?>%)</span>
                        </div>
                        <div class="w-full bg-slate-100/80 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-teal-500 h-1.5 rounded-full transition-all duration-500" style="width: <?= $pct_wh_sent ?>%;"></div>
                        </div>
                    </div>

                    <div class="bg-slate-50/60 p-3 rounded-lg border border-slate-200/80">
                        <div class="flex justify-between text-xs mb-1.5">
                            <span class="text-rose-600 font-semibold flex items-center gap-1.5">
                                <span class="iclass iclass-small" aria-hidden="true">
                                    <svg viewBox="0 0 24 24">
                                        <path d="M10.3 4.3 2.6 18a2 2 0 0 0 1.7 3h15.4a2 2 0 0 0 1.7-3L13.7 4.3a2 2 0 0 0-3.4 0Z"/>
                                        <path d="M12 9v4m0 4h.01"/>
                                    </svg>
                                </span>
                                Failed Dispatches
                            </span>
                            <span class="text-slate-900 font-bold"><?= number_format((int) ($webhook_stats['webhook_failed'] ?? 0)) ?> (<?= $pct_wh_failed ?>%)</span>
                        </div>
                        <div class="w-full bg-slate-100/80 rounded-full h-1.5 overflow-hidden">
                            <div class="bg-rose-500 h-1.5 rounded-full transition-all duration-500" style="width: <?= $pct_wh_failed ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-2.5 text-[11px] text-slate-500 flex items-center justify-between border-t border-slate-200">
                <span class="text-slate-500">Callback Delivery Status</span>
                <span class="text-emerald-600 font-medium flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full"></span> Queue Active
                </span>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white/80 border border-slate-200/90 border-l-[4px] border-l-teal-400 rounded-xl p-5 shadow-lg">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-5 pb-3.5 border-b border-slate-200 gap-3">
            <div>
                <h3 class="text-slate-900 font-bold text-xs flex items-center">
                    <span class="iclass text-teal-600 mr-2.5" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M9 6h12M9 12h12M9 18h12"/>
                            <path d="M4 6h.01M4 12h.01M4 18h.01"/>
                        </svg>
                    </span>
                    Recent Transactions
                </h3>
                <p class="text-slate-500 text-[11px] mt-0.5">Showing your latest 50 M-Pesa activities</p>
            </div>

            <div class="relative w-full sm:w-60">
                <input
                    type="text"
                    id="txSearch"
                    onkeyup="filterTable()"
                    placeholder="Search reference or phone..."
                    class="w-full bg-slate-50 border border-slate-200 rounded-xl py-1.5 pl-9 pr-3 text-xs text-slate-900 focus:border-teal-500/80 outline-none transition-colors"
                >
                <span class="iclass iclass-small search-icon text-slate-500" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <circle cx="11" cy="11" r="7"/>
                        <path d="m16 16 5 5"/>
                    </svg>
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-slate-600 min-w-[850px]">
                <thead class="bg-slate-50/60 border-b border-slate-200">
                    <tr>
                        <th class="py-2.5 text-[11px] uppercase text-slate-500 border-r border-slate-200/80 pr-3 pl-2">Date</th>
                        <th class="py-2.5 text-[11px] uppercase text-slate-500 border-r border-slate-200/80 px-3">Phone Number</th>
                        <th class="py-2.5 text-[11px] uppercase text-slate-500 border-r border-slate-200/80 px-3">Reference</th>
                        <th class="py-2.5 text-[11px] uppercase text-slate-500 border-r border-slate-200/80 px-3">Reason</th>
                        <th class="py-2.5 text-[11px] uppercase text-slate-500 border-r border-slate-200/80 px-3">Amount</th>
                        <th class="py-2.5 text-[11px] uppercase text-slate-500 pl-3">Status</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-slate-200/70" id="txTable">
                    <?php
                    $tx_stmt = $conn->prepare("
                        SELECT phone_number, mpesa_receipt_number, amount, status, transaction_date
                        FROM mpesa_transactions
                        WHERE user_id = ?
                        ORDER BY transaction_date DESC
                        LIMIT ? OFFSET ?
                    ");
                    $tx_stmt->bind_param("iii", $user_id, $limit, $offset);
                    $tx_stmt->execute();
                    $transactions = $tx_stmt->get_result();

                    if ($transactions->num_rows > 0):
                        while ($row = $transactions->fetch_assoc()):
                            $status = strtoupper((string) ($row['status'] ?? ''));
                            $receipt = (string) ($row['mpesa_receipt_number'] ?? '');
                            $is_success = ($status === 'SUCCESS');
                            $is_cancelled = ($receipt === 'Request Cancelled by user.');

                            if ($is_success) {
                                $icon = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg>';
                                $color = 'text-emerald-600';
                            } elseif ($is_cancelled) {
                                $icon = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m6 6 12 12"/></svg>';
                                $color = 'text-amber-600';
                            } else {
                                $icon = '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m9 9 6 6m0-6-6 6"/></svg>';
                                $color = 'text-rose-600';
                            }

                            $timestamp = strtotime((string) ($row['transaction_date'] ?? ''));
                            $date = htmlspecialchars(
                                $timestamp ? date('d M Y, H:i', $timestamp) : '',
                                ENT_QUOTES,
                                'UTF-8'
                            );

                            $phone = !empty($row['phone_number'])
                                ? htmlspecialchars((string) $row['phone_number'], ENT_QUOTES, 'UTF-8')
                                : 'N/A';

                            if ($is_success) {
                                $reference = htmlspecialchars(
                                    $receipt !== '' ? $receipt : 'SUCCESS',
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                                $reason = 'Payment Completed Successfully.';
                            } elseif ($is_cancelled) {
                                $reference = '-';
                                $reason = 'Request Cancelled by user.';
                            } else {
                                $reference = '-';
                                $reason = !empty($receipt)
                                    ? htmlspecialchars($receipt, ENT_QUOTES, 'UTF-8')
                                    : 'Transaction Failed';
                            }

                            $safe_status = htmlspecialchars(
                                (string) ($row['status'] ?? ''),
                                ENT_QUOTES,
                                'UTF-8'
                            );
                            $amount = number_format((float) ($row['amount'] ?? 0), 0);
                    ?>
                        <tr class="tx-row border-b border-slate-200/50 hover:bg-slate-100/40 transition-colors">
                            <td class="py-3 text-xs text-slate-500 whitespace-nowrap border-r border-slate-200/40 pr-3 pl-2"><?= $date ?></td>
                            <td class="py-3 font-mono text-xs text-slate-600 tx-phone border-r border-slate-200/40 px-3"><?= $phone ?></td>
                            <td class="py-3 font-mono text-xs tx-code text-teal-600 border-r border-slate-200/40 px-3"><?= $reference ?></td>
                            <td class="py-3 text-xs text-slate-500 border-r border-slate-200/40 px-3"><?= $reason ?></td>
                            <td class="py-3 font-bold text-slate-900 text-xs border-r border-slate-200/40 px-3">KES <?= $amount ?></td>
                            <td class="py-3 <?= $color ?> font-bold text-xs uppercase pl-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="iclass iclass-small" aria-hidden="true"><?= $icon ?></span>
                                    <span><?= $safe_status ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php
                        endwhile;
                    else:
                    ?>
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500 text-xs">No transactions found yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="flex flex-col sm:flex-row items-center justify-between mt-5 pt-3.5 border-t border-slate-200 gap-3">
                <p class="text-xs text-slate-500">
                    Showing
                    <span class="text-slate-900 font-bold"><?= $total_rows > 0 ? $offset + 1 : 0 ?></span>
                    to
                    <span class="text-slate-900 font-bold"><?= min($offset + $limit, $total_rows) ?></span>
                    of
                    <span class="text-slate-900 font-bold"><?= $total_rows ?></span>
                    entries
                </p>

                <div class="flex items-center gap-2">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition flex items-center gap-1.5">
                            <span class="iclass iclass-tiny" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                            </span>
                            Prev
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-xl bg-slate-50 text-slate-600 text-xs font-bold cursor-not-allowed flex items-center gap-1.5 border border-slate-200">
                            <span class="iclass iclass-tiny" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6"/></svg>
                            </span>
                            Prev
                        </span>
                    <?php endif; ?>

                    <span class="text-xs text-slate-500 px-1.5 font-medium">
                        Page <span class="text-slate-900 font-bold"><?= $page ?></span>
                        of <span class="text-slate-900 font-bold"><?= $total_pages ?></span>
                    </span>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 text-xs font-bold hover:bg-slate-200 transition flex items-center gap-1.5">
                            Next
                            <span class="iclass iclass-tiny" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                            </span>
                        </a>
                    <?php else: ?>
                        <span class="px-3 py-1.5 rounded-xl bg-slate-50 text-slate-600 text-xs font-bold cursor-not-allowed flex items-center gap-1.5 border border-slate-200">
                            Next
                            <span class="iclass iclass-tiny" aria-hidden="true">
                                <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
                            </span>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function filterTable() {
    const input = document.getElementById('txSearch').value.toLowerCase();

    document.querySelectorAll('.tx-row').forEach(function (row) {
        const code = row.querySelector('.tx-code').innerText.toLowerCase();
        const phone = row.querySelector('.tx-phone').innerText.toLowerCase();

        row.style.display = (code.includes(input) || phone.includes(input)) ? '' : 'none';
    });
}

// Expand/collapse Paybill details
(function () {
    const toggle = document.getElementById('paybillToggle');
    const content = document.getElementById('paybillContent');
    const arrow = document.getElementById('paybillArrow');

    if (toggle && content && arrow) {
        toggle.addEventListener('click', function () {
            const expanded = toggle.getAttribute('aria-expanded') === 'true';

            toggle.setAttribute('aria-expanded', String(!expanded));
            content.classList.toggle('hidden', expanded);
            arrow.classList.toggle('rotate-180', !expanded);
        });
    }
})();

// Copy Paybill number
(function () {
    const button = document.getElementById('copyPaybillButton');
    const numberElement = document.getElementById('paybillNumber');
    const buttonText = document.getElementById('copyPaybillText');
    const message = document.getElementById('copyPaybillMessage');

    if (!button || !numberElement || !buttonText || !message) {
        return;
    }

    button.addEventListener('click', async function () {
        const shortcode = numberElement.textContent.trim();

        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(shortcode);
            } else {
                const temporaryInput = document.createElement('textarea');
                temporaryInput.value = shortcode;
                temporaryInput.style.position = 'fixed';
                temporaryInput.style.opacity = '0';
                document.body.appendChild(temporaryInput);
                temporaryInput.select();

                const copied = document.execCommand('copy');
                temporaryInput.remove();

                if (!copied) {
                    throw new Error('Copy failed');
                }
            }

            buttonText.textContent = 'Copied!';
            message.textContent = 'Paybill number copied. Use your wallet token as the account number.';
            message.classList.remove('hidden');

            window.setTimeout(function () {
                buttonText.textContent = 'Copy Paybill';
                message.classList.add('hidden');
            }, 2500);
        } catch (error) {
            message.textContent = 'Could not copy automatically. Please copy the Paybill number manually.';
            message.classList.remove('hidden');
        }
    });
})();
</script>

<?php require_once __DIR__ . '/frame/footer.php'; ?>