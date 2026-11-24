<?php
/**
 * Central Safaricom Daraja environment selector.
 * DARAJA_ENV must be "sandbox" or "production" in the private AvenPay env file.
 */
require_once __DIR__ . '/../database_config/env.php';

if (!function_exists('av_daraja_environment')) {
    function av_daraja_environment(): string
    {
        $environment = strtolower(trim((string) av_env('DARAJA_ENV', 'production')));
        return $environment === 'sandbox' ? 'sandbox' : 'production';
    }

    function av_daraja_base_url(): string
    {
        return av_daraja_environment() === 'sandbox'
            ? 'https://sandbox.safaricom.co.ke'
            : 'https://api.safaricom.co.ke';
    }

    function av_daraja_oauth_url(): string
    {
        return av_daraja_base_url() . '/oauth/v1/generate?grant_type=client_credentials';
    }

    function av_daraja_endpoint(string $path): string
    {
        return av_daraja_base_url() . '/' . ltrim($path, '/');
    }
}
