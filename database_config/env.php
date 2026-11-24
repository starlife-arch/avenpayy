<?php
/**
 * AvenPay environment loader.
 * Secrets live in avenpay.env ONE LEVEL ABOVE the web root (e.g. /home/<user>/avenpay.env),
 * never inside public_html. Real environment variables take priority.
 */
if (!function_exists('av_env')) {
    function av_env(string $key, $default = '') {
        static $vars = null;
        if ($vars === null) {
            $vars = [];
            $web  = dirname(__DIR__);
            $file = getenv('AVENPAY_ENV_FILE') ?: dirname($web) . '/avenpay.env';
            if (is_readable($file)) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
                    [$k, $v] = explode('=', $line, 2);
                    $vars[trim($k)] = trim(trim($v), "\"'");
                }
            }
        }
        $real = getenv($key);
        if ($real !== false && $real !== '') return $real;
        return $vars[$key] ?? $default;
    }

    // Production error handling: never show errors to visitors, log them privately.
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $logDir = dirname(dirname(__DIR__)) . '/avenpay_logs';
    if (is_dir($logDir) || @mkdir($logDir, 0750, true)) {
        ini_set('error_log', $logDir . '/php-error.log');
    }
}
