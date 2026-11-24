<?php
// CLI test: webhook signing + SSRF guard (no network needed)
require_once __DIR__ . '/../../public_html/lib/webhooks.php';
$fail=0; function check($n,$c){ global $fail; echo ($c?'PASS':'FAIL')."  $n\n"; if(!$c)$fail++; }
$s1 = av_webhook_secret(7, 3); $s2 = av_webhook_secret(8, 3);
check('secret has whsec_ prefix and is stable', strpos($s1,'whsec_')===0 && $s1 === av_webhook_secret(7,3));
check('different accounts get different secrets', $s1 !== $s2);
$body = '{"checkout_id":"ws_CO_1","status":"SUCCESS"}'; $sig = av_webhook_signature($s1, $body, 1700000000);
check('signature format t=...,v1=...', preg_match('/^t=1700000000,v1=[a-f0-9]{64}$/', $sig) === 1);
preg_match('/v1=([a-f0-9]+)/', $sig, $m);
check('merchant can verify with HMAC(t.body)', hash_equals(hash_hmac('sha256', '1700000000.'.$body, $s1), $m[1]));
check('tampered body fails verification', !hash_equals(hash_hmac('sha256', '1700000000.'.$body.'x', $s1), $m[1]));
foreach (['http://127.0.0.1/x' => 'loopback http', 'https://127.0.0.1/x' => 'loopback', 'https://localhost/x' => 'localhost', 'https://10.0.0.5/x' => 'private 10/8',
          'https://192.168.1.10/x' => 'private 192.168', 'https://169.254.169.254/latest/meta-data' => 'cloud metadata', 'https://172.16.0.1/x' => 'private 172.16',
          'ftp://example.com/x' => 'ftp scheme', 'https://user:pw@example.com/x' => 'credentials in URL', 'not a url' => 'garbage', 'https://[::1]/x' => 'ipv6 loopback'] as $u => $why)
    check("blocks $why", is_string(av_webhook_target($u)));
$ok = av_webhook_target('https://93.184.216.34/hook');
check('allows a public https address', is_array($ok) && $ok['ip'] === '93.184.216.34' && $ok['port'] === 443);
[$code,$err] = av_webhook_send('https://127.0.0.1/hook', $body, $s1);
check('send() refuses to call internal addresses', $code === 0 && strpos($err, 'Blocked:') === 0);
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n"; exit($fail?1:0);
