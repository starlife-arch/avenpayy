<?php
// CLI test of the web-session PIN lockout in b2c/payout/withdraw.php (needs a throwaway DB; see tests/README)
$root = dirname(__DIR__, 2) . '/public_html';
$_SERVER['DOCUMENT_ROOT'] = $root; $_SERVER['REQUEST_METHOD'] = 'POST'; $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
putenv('AVENPAY_ENV_FILE=' . (getenv('AVENPAY_ENV_FILE') ?: '/tmp/avenv/avenpay.env'));
$mode = $argv[1] ?? 'setup';
require_once $root . '/database_config/env.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$c = new mysqli('localhost', av_env('DB_USER'), av_env('DB_PASS'), av_env('DB_NAME'));
if ($mode === 'setup') {
    $c->query("SET SESSION sql_mode=''");
    $c->query("DELETE FROM users WHERE id = 999002");
    $h = password_hash('1234', PASSWORD_DEFAULT);
    $c->query("INSERT INTO users (id, username, email, password, b2c_wallet_balance, b2c_withdrawal_pin, wallet_id) VALUES (999002,'pin_test','pin_test@example.com','x',500,'$h','WTEST999002')");
    exit;
}
if ($mode === 'cleanup') { $c->query("DELETE FROM users WHERE id = 999002"); exit; }
// mode = attempt <pin>
session_id('pintest' . getmypid()); session_start(); $_SESSION['user_id'] = 999002; session_write_close();
$_POST = ['pin' => $argv[2], 'phone' => '0712345678', 'amount' => '10'];
ob_start(); include $root . '/b2c/payout/withdraw.php';
