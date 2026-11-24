<?php
/** Admin-only gate: include at the very top of any page/endpoint that moves money or changes settings. */
if (PHP_SAPI === 'cli') { return; }
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    header('Content-Type: text/plain');
    exit('Forbidden');
}
