<?php
require_once __DIR__ . '/config.php';

function generateSecurityCredential() {
    $certPath = __DIR__ . '/cert.cer';
    
    if (!file_exists($certPath)) {
        throw new Exception("Safaricom public certificate (cert.cer) missing in hakikisha folder.");
    }
    
    $pubKey = file_get_contents($certPath);
    
    if (!$pubKey) {
        throw new Exception("Could not read the certificate file.");
    }
    
    $encrypted = '';
    if (!openssl_public_encrypt(MPESA_INITIATOR_PASSWORD, $encrypted, $pubKey, OPENSSL_PKCS1_PADDING)) {
        throw new Exception("Encryption failed: " . openssl_error_string());
    }
    
    return base64_encode($encrypted);
}