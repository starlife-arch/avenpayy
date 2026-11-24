<?php
/**
 * Callback authentication.
 * Safaricom calls us with no signature, so every callback URL we hand out carries a secret token
 * (HMAC of the purpose using CALLBACK_SECRET). A forged callback without the token is rejected.
 */
require_once __DIR__ . '/../database_config/env.php';

function av_callback_token(string $purpose): string {
    $secret = (string) av_env('CALLBACK_SECRET');
    if ($secret === '') return '';
    return substr(hash_hmac('sha256', 'avenpay-callback:' . $purpose, $secret), 0, 32);
}

function av_base_url(): string {
    $u = rtrim((string) av_env('APP_URL', ''), '/');
    if ($u !== '') return $u;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    return ($https ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}

function av_callback_url(string $path, string $purpose): string {
    return av_base_url() . '/' . ltrim($path, '/') . '?k=' . av_callback_token($purpose);
}

function av_require_callback_token(string $purpose): void {
    $expected = av_callback_token($purpose);
    $given = (string) ($_GET['k'] ?? '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        if (!function_exists('av_log')) require_once __DIR__ . '/logger.php';
        av_log('callback_rejected.log', "Rejected '$purpose' callback from " . ($_SERVER['REMOTE_ADDR'] ?? '?') . ($expected === '' ? ' (CALLBACK_SECRET not configured)' : ''));
        http_response_code(403);
        header('Content-Type: application/json');
        exit(json_encode(['ResultCode' => 1, 'ResultDesc' => 'Forbidden']));
    }
}
