<?php
/** Cron/maintenance scripts: command line, a logged-in admin, or a request carrying the private cron token. */
require_once __DIR__ . '/callback_auth.php';
function av_cron_guard(): void {
    if (PHP_SAPI === 'cli') return;
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    if (!empty($_SESSION['admin_id'])) return;
    av_require_callback_token('cron');
}
