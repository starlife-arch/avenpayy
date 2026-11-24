<?php
// CLI test: seeds one vault-encrypted value, rotates it with tools/rotate_vault_key.php, verifies the new key decrypts it.
require_once __DIR__ . '/../../public_html/database_config/db.php';
$old = 'old-key-placeholder-0123456789abcdef'; $new = bin2hex(random_bytes(32));
function enc($p,$k){ $iv=random_bytes(16); return base64_encode($iv.openssl_encrypt($p,'aes-256-cbc',$k,0,$iv)); }
function dec($d,$k){ $r=base64_decode($d,true); return openssl_decrypt(substr($r,16),'aes-256-cbc',$k,0,substr($r,0,16)); }
$conn->query("SET SESSION sql_mode=''");
$conn->query("DELETE FROM daraja_settings");
$e = enc('SECRET_VALUE_1', $old);
$conn->query("INSERT INTO daraja_settings (key_name, encrypted_value) VALUES ('consumer_key','$e')");
$cmd = 'php ' . escapeshellarg(__DIR__ . '/../../public_html/tools/rotate_vault_key.php') . ' --old=' . escapeshellarg($old) . ' --new=' . escapeshellarg($new);
echo shell_exec($cmd);
$before = $conn->query("SELECT encrypted_value v FROM daraja_settings")->fetch_assoc()['v'];
echo "after dry run unchanged: " . ($before === $e ? 'PASS' : 'FAIL') . "\n";
echo shell_exec($cmd . ' --apply');
$after = $conn->query("SELECT encrypted_value v FROM daraja_settings")->fetch_assoc()['v'];
echo "new key decrypts: " . (dec($after, $new) === 'SECRET_VALUE_1' ? 'PASS' : 'FAIL') . "\n";
echo "old key no longer decrypts: " . (dec($after, $old) !== 'SECRET_VALUE_1' ? 'PASS' : 'FAIL') . "\n";
$conn->query("DELETE FROM daraja_settings");
