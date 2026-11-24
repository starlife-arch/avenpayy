<?php
// b2c/encrypt.php

function generateSecurityCredential($plainPassword, $certPath) {
    if (!file_exists($certPath)) {
        throw new Exception("Safaricom public certificate missing at: " . $certPath);
    }
    
    $pubKey = file_get_contents($certPath);
    $encrypted = '';
    
    // Encrypt using OpenSSL RSA public key
    if (openssl_public_encrypt($plainPassword, $encrypted, $pubKey, OPENSSL_PKCS1_PADDING)) {
        return base64_encode($encrypted);
    }
    
    throw new Exception("Encryption of security credential failed: " . openssl_error_string());
}