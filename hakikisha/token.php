<?php
require_once __DIR__ . '/config.php';

function getAccessToken() {
    $url = MPESA_BASE_URL . '/oauth/v1/generate?grant_type=client_credentials';
    
    $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
    
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    
    $result = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if ($status !== 200) {
        throw new Exception("Failed to generate access token: " . $result);
    }
    
    $data = json_decode($result);
    return $data->access_token ?? null;
}