# AvenPay API reference

Base URL: `https://avenpay.co.ke` · All responses are JSON · Amounts are KES.
Errors always look like `{"status":"error","message":"..."}`.

| Product | What it does | Auth |
|---|---|---|
| **C2B (STK Push)** | Collect money: customer gets an M-Pesa prompt | `x_api_key` (settlement account key, `avp_live_...`) |
| **B2C** | Pay people (phone numbers) from your wallet | `X-Wallet-ID` + `X-Security-Credential` headers |
| **B2B** | Pay a Paybill or Till from your wallet | same headers as B2C |
| **Payment links** | Hosted checkout page `avenpay.co.ke/link/<slug>` | none for payers; managed in the dashboard |

---
## 1. C2B: STK Push

### Start a payment — `POST /api/stk_push`
| Field | Required | Notes |
|---|---|---|
| `x_api_key` | yes | Settlement-account key from the dashboard |
| `phone_number` | yes | `07XXXXXXXX`, `01XXXXXXXX` or `2547XXXXXXXX` |
| `amount` | yes | Whole KES |
| `mode` | no | `live` (default) or `sandbox` |

Success: `{"status":"success","checkout_id":"ws_CO_..."}`. The customer now sees the M-Pesa PIN prompt.

### Check a payment — `GET /api/stk_push?check_status=<checkout_id>`
Returns `{"status":"PENDING|SUCCESS|FAILED","mpesa_receipt":"...","amount":100,"result_code":0}`.
Prefer webhooks; poll only as a fallback (every 3–5 s, max ~2 minutes).

### Webhooks
Set the URL per settlement account in **Dashboard → Webhooks** (must be public `https://`; internal addresses are blocked).
When a payment changes state we `POST` JSON:
```json
{"checkout_id":"ws_CO_...","status":"SUCCESS","mpesa_receipt":"SGX1234ABC","amount":100,"phone":"2547XXXXXXXX","result_code":0}
```
Delivery is retried up to 5 times until your server answers 2xx. **Always verify the signature.**

Header: `X-AvenPay-Signature: t=<unix time>,v1=<hex>` where `v1 = HMAC_SHA256(secret, t + "." + raw_body)`.
Your signing secret (`whsec_...`) is shown next to each account on the Webhooks page.

```php
// PHP
$raw = file_get_contents('php://input');
parse_str(str_replace(',', '&', $_SERVER['HTTP_X_AVENPAY_SIGNATURE'] ?? ''), $p);
$ok = isset($p['t'], $p['v1'])
   && abs(time() - (int)$p['t']) < 300
   && hash_equals(hash_hmac('sha256', $p['t'] . '.' . $raw, getenv('AVENPAY_WEBHOOK_SECRET')), $p['v1']);
if (!$ok) { http_response_code(400); exit; }
```
```js
// Node (Express, use express.raw for the body)
const crypto = require('crypto');
const [t, v1] = req.get('X-AvenPay-Signature').split(',').map(x => x.split('=')[1]);
const expected = crypto.createHmac('sha256', process.env.AVENPAY_WEBHOOK_SECRET).update(`${t}.${req.body}`).digest('hex');
const ok = Math.abs(Date.now()/1000 - t) < 300 && crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(v1));
```

---
## 2. B2C: pay a phone number — `POST /b2c/payout/withdraw`
Headers: `X-Wallet-ID`, `X-Security-Credential`, optional `Idempotency-Key`.

| Field | Required | Notes |
|---|---|---|
| `phone` | yes | Recipient. `07…`, `01…` or `254…` |
| `amount` | yes | Whole KES, API minimum 10 |
| `command_id` | no | `BusinessPayment` (default), `SalaryPayment`, `PromotionPayment` |
| `remarks`, `occasion` | no | Free text |

How it works: amount + fee are **reserved** in your wallet first, then sent to M-Pesa. Immediate rejection releases the reservation;
the final result arrives from M-Pesa a few seconds later (failure ⇒ automatic refund, exactly once).

Other safeguards: `Idempotency-Key` (a repeated key never pays twice), daily limit (`B2C_DAILY_LIMIT`, default KES 150,000),
20 requests/minute, wallet-dashboard withdrawals need the PIN (locks 15 min after 5 wrong tries) and optionally an emailed code.

Related: `POST /b2c/walletbalance` (balance), `POST /b2c/api/v1/lookup_receiver` (registered name for a number),
`POST /b2c/topup/stk_push` (top up the wallet), `GET /b2c/payout/status` (payout status).

---
## 3. B2B: pay a Paybill or Till — `POST /b2c/payout/b2b_pay`
Same headers and safeguards as B2C.

| Field | Required | Notes |
|---|---|---|
| `command_id` | yes | `BusinessPayBill` (default) or `BusinessBuyGoods` |
| `party_b` | yes | Paybill/Till number, 5–7 digits |
| `account_reference` | Paybill only | ≤ 20 characters |
| `amount` | yes | Whole KES, API minimum 10 |

Check a payment: `GET /b2c/payout/b2b_status?conversation_id=...` or `?idempotency_key=...` →
`{"status":"success","payment":{"state":"pending|completed|failed","amount":2500,"fee":25,"mpesa_receipt":"...",...}}`.

Full examples (cURL / Node / PHP): `/api/docs/b2b` in the app.

Statuses/HTTP codes: `400` validation, insufficient balance or daily limit · `401/403` credentials · `409` same Idempotency-Key still running ·
`429` rate limit · `502` M-Pesa unreachable, **not charged**, safe to retry · `202` outcome unclear, funds stay reserved: poll status, never re-send with a new key.

---
## 4. Payment links
Dashboard → Links. Options: fixed or open amount (with min/max), expiry date, single use, disable/enable, success redirect or file download.
The customer page is `https://avenpay.co.ke/link/<slug>`. The amount and your API key are resolved on our server; the browser never receives your key.
A disabled, expired or already-used link shows "unavailable" and cannot start a payment.
