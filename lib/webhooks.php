<?php
/**
 * Outgoing merchant webhooks: signed, and pinned to a public address (no calls into our own network).
 *
 * Header sent:  X-AvenPay-Signature: t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">
 * Secret:       per settlement account, shown to the merchant on the Webhooks page (whsec_...).
 */
require_once __DIR__ . '/../database_config/env.php';

function av_webhook_secret(int $settlementId, int $userId): string {
    return 'whsec_' . substr(hash_hmac('sha256', "webhook:$settlementId:$userId", (string) av_env('VAULT_KEY') . '|webhooks'), 0, 40);
}

function av_webhook_signature(string $secret, string $body, ?int $ts = null): string {
    $ts = $ts ?? time();
    return 't=' . $ts . ',v1=' . hash_hmac('sha256', $ts . '.' . $body, $secret);
}

/** @return array|string  [host, port, ip, scheme, url] when the URL is safe to call, otherwise a human-readable error */
function av_webhook_target(string $url) {
    $p = parse_url(trim($url));
    if (!$p || empty($p['host']) || empty($p['scheme'])) return 'Enter a full URL such as https://example.com/webhook.';
    $scheme = strtolower($p['scheme']);
    if (!in_array($scheme, ['https', 'http'], true)) return 'Only https:// URLs are allowed.';
    if ($scheme === 'http' && av_env('WEBHOOK_ALLOW_HTTP', '0') !== '1') return 'Webhook URLs must start with https://.';
    if (isset($p['user']) || isset($p['pass'])) return 'Credentials inside the URL are not allowed.';
    $host = strtolower($p['host']);
    $port = (int)($p['port'] ?? ($scheme === 'https' ? 443 : 80));
    if ($host === 'localhost' || substr($host, -6) === '.local' || substr($host, -9) === '.internal') return 'That host is not publicly reachable.';
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
    if (!$ips) return 'We could not resolve that domain.';
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return 'That address points to a private or reserved network.';
    }
    return ['host' => $host, 'port' => $port, 'ip' => $ips[0], 'scheme' => $scheme, 'url' => $url];
}

/** @return array [httpCode, errorString, responseBody] */
function av_webhook_send(string $url, string $body, string $secret): array {
    $t = av_webhook_target($url);
    if (is_string($t)) return [0, 'Blocked: ' . $t, ''];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'User-Agent: AvenPay-Webhooks/1.0', 'X-AvenPay-Signature: ' . av_webhook_signature($secret, $body)],
        CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_RESOLVE => [$t['host'] . ':' . $t['port'] . ':' . $t['ip']], // connect only to the address we just checked
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, $err, (string) $resp];
}
