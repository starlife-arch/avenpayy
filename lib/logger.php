<?php
/** Private, size-capped logging. Logs live OUTSIDE the web root (../avenpay_logs). */
if (!function_exists('av_log_dir')) {
    function av_log_dir(): string {
        static $dir = null;
        if ($dir !== null) return $dir;
        $d = dirname(dirname(__DIR__)) . '/avenpay_logs';
        if (!is_dir($d)) @mkdir($d, 0750, true);
        if (!is_dir($d) || !is_writable($d)) {
            $d = sys_get_temp_dir() . '/avenpay_logs';
            if (!is_dir($d)) @mkdir($d, 0750, true);
        }
        return $dir = $d;
    }
    /** Path for a named log file; rotates it when it passes 5 MB (keeps one .1 backup). */
    function av_log_path(string $name): string {
        $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name));
        $p = av_log_dir() . '/' . $name;
        if (is_file($p) && @filesize($p) > 5 * 1024 * 1024) { @rename($p, $p . '.1'); }
        return $p;
    }
    function av_log(string $name, $message): void {
        if (is_array($message) || is_object($message)) $message = json_encode($message);
        @file_put_contents(av_log_path($name), '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
