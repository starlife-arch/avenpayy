<?php
require_once __DIR__ . '/../../lib/logger.php';
// b2c/token.php

function getAccessToken($config) {
    $consumerKey = trim($config['consumer_key']);
    $consumerSecret = trim($config['consumer_secret']);
    
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $config['token_url']);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    
    // Set to false temporarily if your server's SSL bundle is outdated, 
    // otherwise keep true for security
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true); 
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    
    if (curl_errno($curl)) {
        $error = curl_error($curl);
        curl_close($curl);
        throw new Exception("Token cURL Error: $error");
    }
    
    curl_close($curl);
    
    // Log token fetch attempt for debugging
    $logEntry = "[" . date('Y-m-d H:i:s') . "] Token HTTP Status: $status | Response: $response" . PHP_EOL;
    file_put_contents(av_log_path('b2c.log'), $logEntry, FILE_APPEND);
    
    if ($status !== 200) {
        throw new Exception("Failed to generate access token. HTTP Status: $status, Response: $response");
    }
    
    $result = json_decode($response);
    if (empty($result->access_token)) {
        throw new Exception("Access token missing in response JSON: $response");
    }
    
    return $result->access_token;
}