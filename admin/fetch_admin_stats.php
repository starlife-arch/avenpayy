<?php
// /admin/fetch_admin_stats.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database connection unavailable']);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

$daily_volume = 0.00;
$monthly_volume = 0.00;
$total_successful_amount = 0.00;
$total_registered_users = 0;
$total_webhooks_sent = 0;
$total_active_settlements = 0;
$total_users_b2c_balance = 0.00;
$today_b2c_payouts = 0.00;
$total_b2c_payouts = 0.00;
$today_b2c_topups = 0.00;
$total_b2c_topups = 0.00;

// Fee metrics
$total_topup_fees = 0.00;
$total_payout_fees = 0.00;
$total_revenue_fees = 0.00;

// 1. C2B Gateway Payins Volume
$stmt =$conn->prepare("
    SELECT 
        SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END) as total_volume,
        SUM(CASE WHEN DATE(transaction_date) = CURDATE() AND status = 'success' THEN amount ELSE 0 END) as daily_volume,
        SUM(CASE WHEN MONTH(transaction_date) = MONTH(CURDATE()) AND YEAR(transaction_date) = YEAR(CURDATE()) AND status = 'success' THEN amount ELSE 0 END) as monthly_volume
    FROM mpesa_transactions
");
if ($stmt) {
    $stmt->execute();$stats = $stmt->get_result()->fetch_assoc();$total_successful_amount = (float)($stats['total_volume'] ?? 0);$daily_volume = (float)($stats['daily_volume'] ?? 0);$monthly_volume = (float)($stats['monthly_volume'] ?? 0);$stmt->close();
}

// 2. User Accounts Count & Sum of B2C Balances
$user_res =$conn->query("SELECT COUNT(*) AS total_users, SUM(b2c_wallet_balance) AS sum_balances FROM users");
if ($user_res && $row =$user_res->fetch_assoc()) {
    $total_registered_users = (int)($row['total_users'] ?? 0);
    $total_users_b2c_balance = (float)($row['sum_balances'] ?? 0.00);
}

// 3. B2C Processed Payouts & Dynamic Fee Calculation from `transaction_fees` table (service_type = 'b2c_withdrawal')
$payout_stmt =$conn->prepare("
    SELECT 
        t.transaction_amount,
        (
            SELECT f.fee_ivalue FROM transaction_fees f 
            WHERE f.service_type = 'b2c_withdrawal' 
              AND f.is_active = 1 
              AND t.transaction_amount BETWEEN f.min_amount AND f.max_amount 
            LIMIT 1
        ) as matched_fee
    FROM mpesa_b2c_transactions t
    WHERE t.result_code = 0
");
// Alternatively, calculate via direct SQL join/subquery aggregation for peak efficiency:
$payout_agg =$conn->query("
    SELECT 
        SUM(transaction_amount) as total_payouts,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN transaction_amount ELSE 0 END) as today_payouts,
        SUM(
            COALESCE(
                (SELECT f.fee_ivalue FROM transaction_fees f 
                 WHERE f.service_type = 'b2c_withdrawal' AND f.is_active = 1 
                   AND t.transaction_amount BETWEEN f.min_amount AND f.max_amount 
                 LIMIT 1), 
            0)
        ) as total_payout_fees
    FROM mpesa_b2c_transactions t
    WHERE t.result_code = 0
");

// Fallback safety check if `fee_ivalue` vs `fee_value` column naming
$payout_agg_alt =$conn->query("
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

if ($payout_agg_alt && $prow =$payout_agg_alt->fetch_assoc()) {
    $total_b2c_payouts = (float)($prow['total_payouts'] ?? 0.00);
    $today_b2c_payouts = (float)($prow['today_payouts'] ?? 0.00);
    $total_payout_fees = (float)($prow['total_payout_fees'] ?? 0.00);
}

// 4. B2C Top-ups & Dynamic Fee Calculation from `transaction_fees` table (service_type = 'c2b_topup')
$topup_agg =$conn->query("
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

if ($topup_agg && $trow =$topup_agg->fetch_assoc()) {
    $total_b2c_topups = (float)($trow['total_topups'] ?? 0.00);
    $today_b2c_topups = (float)($trow['today_topups'] ?? 0.00);
    $total_topup_fees = (float)($trow['total_topup_fees'] ?? 0.00);
}

// 5. Total Fees Revenue Collected
$total_revenue_fees = $total_topup_fees +$total_payout_fees;

// 6. Sent Webhooks Counter
$wh_res =$conn->query("SELECT COUNT(*) AS total_sent FROM webhook_queue WHERE status = 'SENT'");
if ($wh_res && $row =$wh_res->fetch_assoc()) {
    $total_webhooks_sent = (int)($row['total_sent'] ?? 0);
}

// 7. Active Settlements Counter
$set_res =$conn->query("SELECT COUNT(*) AS active_settlements FROM settlements WHERE is_active = 1 AND (expiry_date IS NULL OR expiry_date >= CURDATE())");
if ($set_res && $row =$set_res->fetch_assoc()) {
    $total_active_settlements = (int)($row['active_settlements'] ?? 0);
}

echo json_encode([
    'status' => 'success',
    'daily_volume' => $daily_volume,
    'monthly_volume' => $monthly_volume,
    'total_volume' => $total_successful_amount,
    'total_users' => $total_registered_users,
    'total_webhooks_sent' => $total_webhooks_sent,
    'total_active_settlements' => $total_active_settlements,
    'total_users_b2c_balance' => $total_users_b2c_balance,
    'today_b2c_payouts' => $today_b2c_payouts,
    'total_b2c_payouts' => $total_b2c_payouts,
    'today_b2c_topups' => $today_b2c_topups,
    'total_b2c_topups' => $total_b2c_topups,
    'total_topup_fees' => $total_topup_fees,
    'total_payout_fees' => $total_payout_fees,
    'total_revenue_fees' => $total_revenue_fees
]);
exit;