# AvenPay: deployment guide (cPanel)

## What you have
```
public_html/   the application. Upload its CONTENTS to your site's public_html
setup/         NOT uploaded: install/migration SQL, env template, tests, brand files, these docs
```

## 0. Treat the old credentials as burned
The zips you shared contained live secrets. Before going live, create NEW ones for: the database user, every Daraja consumer key/secret/passkey/initiator password
(Safaricom portal), Google and GitHub OAuth secrets, the `no-reply@` mailbox password (it was hardcoded in two files), and all admin passwords
(a default admin account with a plaintext password was hardcoded in `admin/setup.php`, which is deleted: change that password everywhere it was used).

## 1. Server
- PHP **8.1+** (8.3 recommended) with `mysqli, curl, openssl, mbstring, fileinfo` (cPanel > Select PHP Version). MariaDB (the cPanel default).
- HTTPS with a valid certificate on avenpay.co.ke (AutoSSL is fine). M-Pesa will only call HTTPS URLs.

## 2. Private config (secrets live OUTSIDE public_html)
1. File Manager > your home folder (the one that contains `public_html`) > create `avenpay.env` from `setup/avenpay.env.example`, then set permission **600**.
2. Fill it in. Generate secrets with `php -r "echo bin2hex(random_bytes(32));"` (cPanel Terminal) or any password generator (64 chars):
   `VAULT_KEY`, `CALLBACK_SECRET`. **`CALLBACK_SECRET` is mandatory**: without it every M-Pesa callback is rejected (by design).
3. Errors and app logs are written to `../avenpay_logs/` (also outside public_html, auto-rotated at 5 MB).

## 3. Database
**Fresh install:** create a database + user in cPanel, then phpMyAdmin > Import `setup/avenpay_install.sql` (already includes both migrations).
**Existing database (you are keeping data):** take a backup first, then run `setup/migrations/001_...sql` and `002_...sql` once, in that order.

### Existing database: vault key
Your old code encrypted stored Daraja credentials with the **placeholder key** `your-very-secret-long-random-key-here` (hardcoded in 17 files).
1. Put that exact old key in `VAULT_KEY` so everything still decrypts, and confirm the site works.
2. Then rotate: generate a NEW key, and from cPanel Terminal in `public_html`:
   `php tools/rotate_vault_key.php --old="OLD" --new="NEW"` (dry run) then again with `--apply`. Put the NEW key in `avenpay.env`.

## 4. First admin
cPanel Terminal, in `public_html`:  `php tools/create_admin.php --username=YOURNAME --email=you@avenpay.co.ke`
(prints a temporary password; change it after first login). No Terminal? Add a one-off cron job running the same command, then delete the job.

## 5. Safaricom / Daraja
- STK Push, B2C **and B2B (Business Pay Bill / Business Buy Goods)** must be enabled for your shortcode and initiator role in the Safaricom portal.
- Callback URLs are now generated with a private token (`?k=...`) per request; nothing to register for STK/B2C/B2B.
  Requests already in flight when you switch over will have their callbacks rejected, so deploy at a quiet time.
- Production certificate files (`ProductionCertificate.cer`, `hakikisha/cert.cer`) are Safaricom's public certificates and are included.

## 6. Cron jobs (cPanel > Cron Jobs). Use PHP, not wget:
| Schedule | Command |
|---|---|
| every minute | `/usr/local/bin/php /home/USER/public_html/api/dispatch_webhooks.php` |
| same schedule as before | `/usr/local/bin/php /home/USER/public_html/cron/pull_transactions.php` and `.../cron/process_b2c_pull.php` |
| daily | `/usr/local/bin/php /home/USER/public_html/tools/reconcile.php` (emails you if a wallet doesn't match its ledger or a payout is stuck) |

(`USER` = your cPanel username; if `php` is at another path, cPanel shows it in the command hint.)
If you can only call URLs: `php tools/cron_token.php` prints the token to append as `?k=...`.
The 116 MB `cron/error.log` came from `pull_transactions.php` logging every API page; that line is removed and logs now rotate.

## 7. Before taking money
1. Admin > **Fee Tiers**: add B2C and **B2B** tiers (no tier = fee 0).
2. Admin > Daraja / B2C settings: enter the NEW credentials.
3. Admin > **Stuck Payouts** page: bookmark it. Payouts with no final M-Pesa result show up here after 15 minutes.
4. Create the `support@avenpay.co.ke` and `no-reply@avenpay.co.ke` mailboxes; fill the `[LEGAL BUSINESS NAME]` / `[BUSINESS ADDRESS]` placeholders in `terms.php` and `privacy.php`
   and have the legal text reviewed (Kenya Data Protection Act: register with the ODPC).
5. Contact details: the phone/WhatsApp number **+254748600179** is hardcoded on the landing page (`index.html`) and in `privacy.php`. Keep it only if it is yours; otherwise search and replace it.
6. Optional: `WITHDRAW_OTP=1` after checking that a password-reset email actually arrives.

## 8. Smoke test (use small amounts)
register > create settlement + API key > create a payment link > pay it with your own phone (STK) > see it in Transactions >
top up the wallet > withdraw 10 KES to your phone (B2C) > pay a Till/Paybill 10 KES (B2B) > webhook arrives with a valid signature.

## Known limits / next steps
- Live M-Pesa calls could not be tested here (no Safaricom access). Every other flow has automated tests in `setup/tests` (see `tests/README.md`).
- Merchant dashboard doesn't list B2B payments yet (API + admin Stuck Payouts do); webhook notifications for B2C/B2B results are not built.
- Paid file downloads on payment links use a public file URL (unguessable but shareable).
- Auto-refund on an M-Pesa *queue timeout* is intentionally off (could pay twice). Those payouts appear in Stuck Payouts.
- `terms.php` / `privacy.php` are templates, not legal advice.
