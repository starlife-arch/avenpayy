#!/bin/bash
# usage: AVENPAY_ENV_FILE=/path/test.env bash run_all.sh
cd "$(dirname "$0")"
: "${AVENPAY_ENV_FILE:?set AVENPAY_ENV_FILE to a TEST environment file}"
set -e
for t in ledger_test links_test rotate_test webhooks_test payout_review_test; do echo "== $t"; php $t.php | tail -3; done
echo "== withdraw_pin_test"; php withdraw_pin_test.php setup
for i in 1 2 3 4 5 6; do php withdraw_pin_test.php attempt 0000 2>/dev/null | grep -o '"message":"[^"]*"' | cut -c1-60; done
php withdraw_pin_test.php cleanup
echo "== http_b2b_test"; bash http_b2b_test.sh
