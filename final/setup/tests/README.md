# Tests
Run against a THROWAWAY database (never production): they create and delete rows with ids 99900x.

1. Create an empty test DB, import `../avenpay_install.sql`.
2. Create a file `test.env` with DB_HOST/DB_USER/DB_PASS/DB_NAME (test DB), `VAULT_KEY`, `CALLBACK_SECRET`, `APP_URL`, `B2C_DAILY_LIMIT=1000`.
3. `AVENPAY_ENV_FILE=/path/test.env bash run_all.sh`

| Test | Proves |
|---|---|
| ledger_test | credits/debits are idempotent, no overdraw, no double refund, ledger sum = balance |
| withdraw_pin_test | PIN lockout after 5 wrong tries |
| links_test | payment-link insert/update SQL, expiry, single-use, min/max, fixed-amount enforcement |
| rotate_test | vault key rotation re-encrypts, dry-run changes nothing |
| webhooks_test | signature format/verification, SSRF guard blocks private/metadata/loopback targets |
| payout_review_test | admin "failed + refund" refunds exactly once |
| http_b2b_test.sh | forged callbacks rejected (403), atomic refund on duplicate callbacks, B2B validation, status endpoint, over real HTTP |
