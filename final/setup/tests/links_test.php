<?php
// CLI test: payment_links INSERT/UPDATE statements (extracted from create_link.php/links.php) + link rules in lib/payment_links.php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/../../public_html/database_config/db.php';
require_once __DIR__ . '/../../public_html/lib/payment_links.php';
$conn->query("SET SESSION sql_mode=''");
$fail = 0; function check($n,$c){ global $fail; echo ($c?'PASS':'FAIL')."  $n\n"; if(!$c)$fail++; }
$conn->query("DELETE FROM payment_links WHERE slug LIKE 'test-%'");

// --- INSERT exactly as create_link.php does it
$src = file_get_contents(__DIR__ . '/../../public_html/create_link.php');
preg_match('/\$sql = "(INSERT INTO payment_links.*?)";/s', $src, $m); $sql = $m[1];
preg_match('/\$stmt->bind_param\("([a-z]+)"/', $src, $t); $types = $t[1];
$stmt = $conn->prepare($sql);
$uid=1; $key='k_test'; $slug='test-open'; $title='T'; $desc='A description'; $amt=0.0; $fixed=0; $logo=null; $file=null; $url=null; $mode='download'; $stype='download'; $min=50.0; $max=1000.0; $exp=null; $single=0;
$stmt->bind_param($types,$uid,$key,$slug,$title,$desc,$amt,$fixed,$logo,$file,$url,$mode,$stype,$min,$max,$exp,$single);
$stmt->execute();
$row = $conn->query("SELECT * FROM payment_links WHERE slug='test-open'")->fetch_assoc();
check('insert stores description as text', $row['description'] === 'A description');
check('insert stores min/max', (float)$row['min_amount']==50.0 && (float)$row['max_amount']==1000.0);
check('new link defaults to active', $row['status']==='active');

// --- UPDATE exactly as links.php does it
$src = file_get_contents(__DIR__ . '/../../public_html/links.php');
preg_match('/\$sql = "(UPDATE payment_links SET.*?)";/s', $src, $m); $usql=$m[1];
preg_match('/\$stmt->bind_param\("([a-z]+)", \$x_api_key, \$slug/', $src, $t); $utypes=$t[1];
$st=$conn->prepare($usql);
$link_id=(int)$row['id']; $user_id=1; $status='disabled'; $exp='2020-01-01 00:00:00'; $single=1; $amt=250.0; $fixed=1;
$st->bind_param($utypes,$key,$slug,$title,$desc,$amt,$fixed,$logo,$file,$url,$mode,$stype,$min,$max,$exp,$single,$status,$link_id,$user_id);
$st->execute();
$row = $conn->query("SELECT * FROM payment_links WHERE slug='test-open'")->fetch_assoc();
check('update applied (status/single_use/amount)', $row['status']==='disabled' && (int)$row['single_use']===1 && (float)$row['amount']==250.0);

// --- rules
check('disabled link is unavailable', av_link_unavailable_reason($conn,$row) !== '');
$row['status']='active'; check('expired link is unavailable', av_link_unavailable_reason($conn,$row) === 'This payment link has expired.');
$row['expires_at']=null; $row['single_use']=0; check('active link is available', av_link_unavailable_reason($conn,$row)==='');
[$a,$e]=av_link_resolve_amount($row, 1); check('fixed link ignores browser amount (charges 250)', $a===250 && $e==='');
$row['is_fixed']=0; $row['min_amount']=50; $row['max_amount']=1000;
check('open link below min rejected', av_link_resolve_amount($row,10)[1] !== '');
check('open link above max rejected', av_link_resolve_amount($row,5000)[1] !== '');
check('open link in range accepted', av_link_resolve_amount($row,500)===[500,'']);

// --- single use: a SUCCESS transaction closes the link
$row['single_use']=1; $lid=(int)$row['id'];
$conn->query("INSERT INTO mpesa_transactions (checkout_request_id, phone_number, amount, status, user_id, payment_link_id) VALUES ('ws_CO_TEST1','254700000000',100,'SUCCESS',1,$lid)");
check('single-use link closes after a successful payment', av_link_unavailable_reason($conn,$row) === 'This payment link has already been used.');
$conn->query("DELETE FROM mpesa_transactions WHERE checkout_request_id='ws_CO_TEST1'");
$conn->query("DELETE FROM payment_links WHERE slug LIKE 'test-%'");
echo $fail ? "\n$fail FAILED\n" : "\nALL PASSED\n"; exit($fail?1:0);
