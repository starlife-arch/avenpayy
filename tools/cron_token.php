<?php
// Prints the token for cron jobs that must call a URL (if you cannot run php from cPanel cron):
//   php tools/cron_token.php   ->   https://avenpay.co.ke/api/dispatch_webhooks.php?k=<token>
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../lib/callback_auth.php';
$t = av_callback_token('cron');
if ($t === '') { fwrite(STDERR, "CALLBACK_SECRET is not set in avenpay.env\n"); exit(1); }
echo $t, "\n";
