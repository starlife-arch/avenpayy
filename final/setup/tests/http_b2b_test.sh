#!/bin/bash
# HTTP tests (PHP built-in server): callback authentication, atomic refunds, B2B validation, status endpoint.
cd "$(dirname "$0")"; ROOT="$(cd ../../public_html && pwd)"
: "${AVENPAY_ENV_FILE:?set AVENPAY_ENV_FILE}"
mysql_q() { php -r 'require "'$ROOT'/database_config/db.php"; $r=$conn->query($argv[1]); if($r instanceof mysqli_result){ foreach($r->fetch_all() as $row) echo implode("\t",$row),"\n"; }' "$1"; }
php b2b_seed.php seed
(php -S 127.0.0.1:8099 -t "$ROOT" >/tmp/phpserver.log 2>&1 &); sleep 2
PORT=http://127.0.0.1:8099; TK=$(php b2b_seed.php token b2b_result); fail=0
ok(){ if [ "$2" = "$3" ]; then echo "PASS  $1"; else echo "FAIL  $1 (got '$2', want '$3')"; fail=1; fi; }
has(){ case "$2" in *"$3"*) echo "PASS  $1";; *) echo "FAIL  $1 (got '$2')"; fail=1;; esac; }
bal(){ mysql_q "select b2c_wallet_balance from users where id=999003" | tr -d '\n'; }
FAIL='{"Result":{"ResultType":0,"ResultCode":2001,"ResultDesc":"The initiator information is invalid.","OriginatorConversationID":"O_AG_FAIL1","ConversationID":"AG_FAIL1"}}'
OK='{"Result":{"ResultType":0,"ResultCode":0,"ResultDesc":"The service request is processed successfully.","OriginatorConversationID":"O_AG_OK1","ConversationID":"AG_OK1","TransactionID":"SGX1234ABC"}}'
ok "start balance (500-105-55)" "$(bal)" "340.00"
ok "callback without token -> 403" "$(curl -s -o /dev/null -w '%{http_code}' -X POST -d "$FAIL" $PORT/b2c/payout/b2b_result.php)" "403"
ok "callback wrong token -> 403" "$(curl -s -o /dev/null -w '%{http_code}' -X POST -d "$FAIL" "$PORT/b2c/payout/b2b_result.php?k=deadbeef")" "403"
curl -s -o /dev/null -X POST -d "$FAIL" "$PORT/b2c/payout/b2b_result.php?k=$TK"; ok "failure callback refunds 105" "$(bal)" "445.00"
curl -s -o /dev/null -X POST -d "$FAIL" "$PORT/b2c/payout/b2b_result.php?k=$TK"; ok "duplicate failure callback does not refund twice" "$(bal)" "445.00"
curl -s -o /dev/null -X POST -d "$OK" "$PORT/b2c/payout/b2b_result.php?k=$TK"; ok "success callback keeps the debit" "$(bal)" "445.00"
H1="X-Wallet-Id: WB999003"; H2="X-Security-Credential: secret123"
msg(){ curl -s -X POST $PORT/b2c/payout/b2b_pay.php -H "$H1" -H "$H2" "$@"; }
has "bad command rejected" "$(msg -d 'party_b=174379&amount=50&command_id=Nope')" "command_id must be"
has "bad paybill rejected" "$(msg -d 'party_b=12&amount=50&command_id=BusinessPayBill&account_reference=X')" "valid Paybill or Till number"
has "paybill needs account" "$(msg -d 'party_b=174379&amount=50&command_id=BusinessPayBill')" "account reference"
ok "wrong credential denied" "$(curl -s -o /dev/null -w '%{http_code}' -X POST $PORT/b2c/payout/b2b_pay.php -H "$H1" -H 'X-Security-Credential: nope' -d 'party_b=174379&amount=50')" "401"
ok "validation failures never touch the wallet" "$(bal)" "445.00"
has "status endpoint reports the completed payment" "$(curl -s "$PORT/b2c/payout/b2b_status.php?conversation_id=AG_OK1" -H "$H1" -H "$H2")" '"state":"completed"'
ok "ledger equals balance" "$(mysql_q "select if(sum(amount)=(select b2c_wallet_balance from users where id=999003),'YES','NO') from wallet_ledger where user_id=999003" | tr -d '\n')" "YES"
pkill -f "[p]hp -S 127.0.0.1:8099"; php b2b_seed.php cleanup
[ $fail = 0 ] && echo "ALL PASSED" || { echo "SOME FAILED"; exit 1; }
