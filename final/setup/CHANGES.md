# What changed from the zip you started with

## Critical holes closed
| Problem found | Fix |
|---|---|
| `api/transfer.php` fired a real B2C payout from the shortcode for anyone who opened it | deleted |
| `b2b/index.php` and the hakikisha test script could trigger real B2B payments with no login | admin-only gate / deleted |
| `api/update_status.php`, `api/deactivate.php`: anyone could switch any merchant's account on/off | deleted (unused) |
| Payment-link checkout page printed the merchant's live API key in its HTML, and the amount was only a read-only field | key and amount now resolved on the server from the link |
| Any file type could be uploaded and executed (`.php` in uploads) | allow-list, MIME check, random names, PHP disabled in uploads |
| Payout paid via M-Pesa first, wallet debited after: two parallel requests were both paid | funds reserved under a row lock before M-Pesa is called; refunded if rejected |
| Callbacks (STK, B2C result/timeout, B2B, top-up) accepted from anyone | private token in every callback URL, checked with a constant-time compare |
| Duplicate result callbacks could refund twice; failure codes that were text broke the refund | atomic claim + ledger uniqueness; non-numeric codes handled |
| Queue-timeout callback auto-refunded (double pay if success arrived later) | no auto-refund; shows in Admin > Stuck Payouts |
| Merchant webhooks were unsigned, called any URL incl. internal addresses, with TLS checks off | HMAC-signed, https-only, private/metadata addresses blocked, TLS verified |
| Encryption key was the placeholder, copied into 17 files; DB, OAuth and SMTP passwords hardcoded; default admin password in `admin/setup.php` | all secrets in `avenpay.env` outside public_html; vault-key rotation tool; setup.php removed, CLI admin creator |
| No brute-force protection on PIN/login | PIN lock (5 tries/15 min), rate limits on login/register/forgot/withdraw/API |
| Debug scripts, logs and merchant files shipped in the web root; 116 MB unbounded log | removed; logs private and rotating |
| Corrupted `||` made `admin/fetch_admin_stats.php` a PHP parse error | fixed |

## New
- **Wallet ledger** (`wallet_ledger`): every balance change is a ledger line; idempotent; daily `tools/reconcile.php`.
- **B2B** (pay Paybill/Till from the wallet): API `b2b_pay`, `b2b_status`, dashboard tab, result/timeout handlers, fee tier, docs page.
- **Payment links**: expiry, single use, min/max, enable/disable; ownership check on the settlement account.
- **Withdrawals**: PIN lockout, daily limit, `Idempotency-Key`, optional emailed code.
- **Admin**: Stuck Payouts page (resolve with one-time refund), B2B fee tier.
- **Rebrand**: AvenPay name/domain everywhere, one teal-on-navy palette (purple/cyan/blue accents removed), new logo + favicon + manifest, consistent page titles, `avp_live_` key prefix, header `X-AvenPay-Signature`.
- **Docs**: `/api/docs` portal (C2B, B2C, B2B) and `API_REFERENCE.md` including webhook verification code.
- **Tests**: 17 automated checks + the PIN/HTTP suites in `setup/tests`.
