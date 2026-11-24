<?php
/** Request-level protections: same-origin check for session POSTs, hardened session cookie, rate limiting. */
require_once __DIR__ . '/logger.php';

function av_is_https(): bool {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
}

function av_security_bootstrap(): void {
    if (PHP_SAPI === 'cli' || empty($_SERVER['REMOTE_ADDR'])) return;

    // 1) Harden the session cookie (SameSite=Lax stops cross-site form posts from carrying the login)
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        setcookie(session_name(), session_id(), [
            'expires' => 0, 'path' => '/', 'secure' => av_is_https(), 'httponly' => true, 'samesite' => 'Lax',
        ]);
    }

    // 2) CSRF defence: a logged-in browser session may only POST from our own origin
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)
        && session_status() === PHP_SESSION_ACTIVE
        && (!empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']))) {
        $host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
        $src  = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
        if ($src !== '') {
            $srcHost = strtolower((string) parse_url($src, PHP_URL_HOST));
            $ok = ($srcHost === $host) || ($srcHost === 'www.' . $host) || ('www.' . $srcHost === $host);
            if (!$ok) {
                av_log('csrf_blocked.log', "Blocked cross-origin $method to " . ($_SERVER['REQUEST_URI'] ?? '') . " from $src");
                http_response_code(403);
                header('Content-Type: application/json');
                exit(json_encode(['status' => 'error', 'success' => false, 'message' => 'Cross-site request blocked.']));
            }
        }
    }
}

/** Fixed-window rate limiter (file based, no extra services needed). Returns true if allowed. */
function av_rate_limit(string $bucket, int $max, int $windowSec, ?string $who = null): bool {
    $who = $who ?? ($_SERVER['REMOTE_ADDR'] ?? 'cli');
    $dir = av_log_dir() . '/rl';
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $file = $dir . '/' . sha1($bucket . '|' . $who) . '.json';
    $now = time();
    $fh = @fopen($file, 'c+');
    if (!$fh) return true; // never block real users because the limiter itself failed
    flock($fh, LOCK_EX);
    $data = json_decode((string) stream_get_contents($fh), true);
    if (!is_array($data) || ($data['reset'] ?? 0) <= $now) $data = ['count' => 0, 'reset' => $now + $windowSec];
    $data['count']++;
    ftruncate($fh, 0); rewind($fh); fwrite($fh, json_encode($data));
    flock($fh, LOCK_UN); fclose($fh);
    if ($data['count'] > $max) { header('Retry-After: ' . max(1, $data['reset'] - $now)); return false; }
    return true;
}

function av_rate_limit_or_die(string $bucket, int $max, int $windowSec, ?string $who = null): void {
    if (PHP_SAPI === 'cli') return;
    if (!av_rate_limit($bucket, $max, $windowSec, $who)) {
        http_response_code(429);
        header('Content-Type: application/json');
        exit(json_encode(['status' => 'error', 'success' => false, 'message' => 'Too many requests. Please wait a moment and try again.']));
    }
}
