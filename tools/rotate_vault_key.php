<?php
// Re-encrypt stored Daraja credentials under a NEW vault key.
//   Dry run : php tools/rotate_vault_key.php --old="OLD KEY" --new="NEW KEY"
//   Apply   : php tools/rotate_vault_key.php --old="OLD KEY" --new="NEW KEY" --apply
// Take a database backup first. After --apply, set VAULT_KEY=<NEW KEY> in avenpay.env.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../database_config/db.php';
$o = getopt('', ['old:', 'new:', 'apply', 'encrypt-plaintext']);
if (empty($o['old']) || empty($o['new'])) { fwrite(STDERR, "Usage: php tools/rotate_vault_key.php --old=KEY --new=KEY [--apply] [--encrypt-plaintext]\n"); exit(1); }
if (strlen($o['new']) < 32) { fwrite(STDERR, "New key must be at least 32 characters (use: php -r \"echo bin2hex(random_bytes(32));\").\n"); exit(1); }
$apply = isset($o['apply']); $encPlain = isset($o['encrypt-plaintext']);
const METHOD = 'aes-256-cbc';

function v_decrypt(string $data, string $key): ?string {
    $raw = base64_decode($data, true);
    if ($raw === false) return null;
    $ivLen = openssl_cipher_iv_length(METHOD);
    if (strlen($raw) <= $ivLen) return null;
    $out = openssl_decrypt(substr($raw, $ivLen), METHOD, $key, 0, substr($raw, 0, $ivLen));
    return $out === false ? null : $out;
}
function v_encrypt(string $plain, string $key): string {
    $iv = random_bytes(openssl_cipher_iv_length(METHOD));
    return base64_encode($iv . openssl_encrypt($plain, METHOD, $key, 0, $iv));
}

$targets = [
    'daraja_b2c_settings'  => ['consumer_key', 'consumer_secret', 'initiator_password'],
    'daraja_gateways'      => ['consumer_key', 'consumer_secret', 'passkey', 'initiator_password'],
    'daraja_settings'      => ['encrypted_value'],
    'user_daraja_settings' => ['encrypted_value'],
];
$totals = ['rotated' => 0, 'plaintext' => 0, 'empty' => 0];
if ($apply) $conn->begin_transaction();
try {
    foreach ($targets as $table => $cols) {
        $res = $conn->query("SELECT id, `" . implode('`,`', $cols) . "` FROM `$table`");
        while ($row = $res->fetch_assoc()) {
            foreach ($cols as $c) {
                $val = (string)($row[$c] ?? '');
                if ($val === '') { $totals['empty']++; continue; }
                $plain = v_decrypt($val, $o['old']);
                if ($plain === null) {
                    $totals['plaintext']++;
                    echo "  [$table#{$row['id']}.$c] not decryptable with the old key (plaintext or different key)" . ($encPlain ? " -> encrypting as plaintext" : " -> left untouched") . "\n";
                    if (!$encPlain) continue;
                    $plain = $val;
                }
                $new = v_encrypt(trim($plain), $o['new']);
                if (v_decrypt($new, $o['new']) !== trim($plain)) throw new RuntimeException("Self-check failed on $table#{$row['id']}.$c");
                $totals['rotated']++;
                if ($apply) {
                    $st = $conn->prepare("UPDATE `$table` SET `$c` = ? WHERE id = ?");
                    $st->bind_param("si", $new, $row['id']);
                    $st->execute(); $st->close();
                }
            }
        }
    }
    if ($apply) $conn->commit();
} catch (Throwable $e) {
    if ($apply) $conn->rollback();
    fwrite(STDERR, "ABORTED, nothing changed: " . $e->getMessage() . "\n"); exit(1);
}
echo ($apply ? "APPLIED" : "DRY RUN") . ": rotated={$totals['rotated']} plaintext/undecryptable={$totals['plaintext']} empty={$totals['empty']}\n";
if ($apply) echo "Now set VAULT_KEY to the NEW key in avenpay.env.\n"; else echo "Re-run with --apply to write the changes.\n";
