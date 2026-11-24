<?php
// docs/b2b.php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>B2B Payments API - AvenPay Docs</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <!-- FontAwesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #ffffff;
            --card-bg: rgba(248, 250, 252, 0.95);
            --card-border: rgba(15, 23, 42, 0.08);
        }
        body { background-color: var(--bg-color); color: #0f172a; }
    </style>

    <style id="avenpay-docs-readable-theme">
      html, body { background:#f4f7fb !important; color:#172033 !important; color-scheme:light; }
      main { color:#172033 !important; }
      main [class*="rgba(15,23,42"], main [class*="bg-[#05080f]"],
      main [class*="bg-slate-950"], main [class*="bg-gray-950"] {
        background:#ffffff !important; border-color:#dbe3ed !important;
        box-shadow:0 8px 28px rgba(15,23,42,.06) !important;
      }
      main h1, main h2, main h3, main h4, main strong, main b,
      main label { color:#172033 !important; }
      main p, main li, main td, main th, main label { opacity:1 !important; }
      main p { color:#475569 !important; }
      main pre, main code, main pre code {
        background:#f1f5f9 !important; color:#1e293b !important;
        border-color:#dbe3ed !important; text-shadow:none !important;
      }
      main pre { padding:1rem !important; border:1px solid #dbe3ed !important;
        border-radius:.65rem !important; overflow-x:auto !important; }
      main input, main textarea, main select {
        background:#ffffff !important; color:#172033 !important;
        border-color:#cbd5e1 !important;
      }
      main .text-slate-100, main .text-white { color:#172033 !important; }
      main .text-slate-200, main .text-slate-300 { color:#334155 !important; }
      main .text-slate-400, main .text-slate-500, main .text-slate-600 { color:#475569 !important; }
      main .bg-emerald-950, main .bg-teal-950 { background:#d1fae5 !important; }
      main .text-emerald-600, main .text-teal-600 { color:#087f67 !important; }
    </style>

</head>
<body class="min-h-screen flex flex-col font-sans antialiased">

<?php 
$header_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';
if (file_exists($header_path)) {
    include $header_path;
}
?>

<main class="max-w-4xl mx-auto p-4 md:p-8 w-full flex-1 space-y-8">
<div class="pb-6 border-b border-slate-200/80">
  <a href="/api/docs/index.php" class="text-[11px] text-teal-600">&larr; All APIs</a>
  <h1 class="text-2xl font-black text-slate-900 tracking-wide mt-2">B2B Payments API</h1>
  <p class="text-xs text-slate-500 mt-1">Pay a Paybill or a Till straight from your AvenPay wallet: suppliers, utilities, rent, subscriptions.</p>
</div>

<div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] p-6 rounded-sm shadow-xl space-y-4"><h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">How it works</h2>
<ol class="text-xs text-slate-600 leading-relaxed list-decimal pl-5 space-y-1">
<li>You call <code class="text-amber-700">POST /b2c/payout/b2b_pay</code>. We check your balance, PIN-free API credentials and daily limit, then <b>reserve</b> amount + fee in your wallet.</li>
<li>We send the payment to M-Pesa. If M-Pesa rejects it immediately, the reservation is released at once.</li>
<li>M-Pesa reports the final result a few seconds later. On success the reservation becomes the debit. On failure it is refunded automatically, exactly once.</li>
<li>Check the outcome with <code class="text-amber-700">GET /b2c/payout/b2b_status</code> or in your dashboard (Wallet &amp; Payouts).</li></ol>
<p class="text-xs text-slate-500">Fees are set per amount tier by AvenPay (B2B Payment tier) and are shown in your dashboard.</p></div>

<div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] p-6 rounded-sm shadow-xl space-y-4"><h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Authentication</h2>
<p class="text-xs text-slate-600">Send your wallet credentials as headers on every request. Keep the security credential on your server only, never in a browser or mobile app.</p>
<table class="w-full text-xs"><tbody><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">X-Wallet-ID</td><td class="p-2 text-slate-500">header</td><td class="p-2 text-slate-600">Yes</td><td class="p-2 text-slate-600">Your wallet ID (Wallet Token page).</td></tr><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">X-Security-Credential</td><td class="p-2 text-slate-500">header</td><td class="p-2 text-slate-600">Yes</td><td class="p-2 text-slate-600">Your B2C security credential.</td></tr><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">Idempotency-Key</td><td class="p-2 text-slate-500">header</td><td class="p-2 text-slate-600">Recommended</td><td class="p-2 text-slate-600">Any unique string up to 64 characters per payment. When idempotency support is enabled for your account, reuse the same key for retries; never create a new key while the first request may still be processing.</td></tr></tbody></table></div>

<div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] p-6 rounded-sm shadow-xl space-y-4"><h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Create a payment</h2>
<div class="text-[11px] font-mono"><span class="px-2 py-0.5 bg-teal-600 text-white rounded-sm font-bold">POST</span> <span class="text-slate-700">/b2c/payout/b2b_pay</span></div>
<p class="text-xs text-slate-500">JSON or form-encoded body.</p>
<table class="w-full text-xs"><thead class="text-slate-500 text-left"><tr><th class="p-2">Field</th><th class="p-2">Type</th><th class="p-2">Required</th><th class="p-2">Notes</th></tr></thead><tbody><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">command_id</td><td class="p-2 text-slate-500">string</td><td class="p-2 text-slate-600">Yes</td><td class="p-2 text-slate-600">BusinessPayBill (to a Paybill) or BusinessBuyGoods (to a Till). Default: BusinessPayBill.</td></tr><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">party_b</td><td class="p-2 text-slate-500">string</td><td class="p-2 text-slate-600">Yes</td><td class="p-2 text-slate-600">The Paybill or Till number: 5 to 7 digits.</td></tr><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">account_reference</td><td class="p-2 text-slate-500">string</td><td class="p-2 text-slate-600">Paybill only</td><td class="p-2 text-slate-600">Account number or reference at the Paybill. Max 20 characters.</td></tr><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">amount</td><td class="p-2 text-slate-500">integer</td><td class="p-2 text-slate-600">Yes</td><td class="p-2 text-slate-600">Whole KES. API minimum: 10.</td></tr><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">remarks</td><td class="p-2 text-slate-500">string</td><td class="p-2 text-slate-600">No</td><td class="p-2 text-slate-600">Free text note. Max 100 characters.</td></tr></tbody></table>
<div class="space-y-1.5"><div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">cURL</div><pre class="bg-[#05080f] border border-slate-200 rounded-sm p-3 text-[11px] text-slate-700 overflow-x-auto"><code>curl -X POST "https://api.avenpay.co.ke/b2c/payout/b2b_pay" \
  -H "X-Wallet-ID: WALLET_XYZ123" \
  -H "X-Security-Credential: your_security_credential" \
  -H "Idempotency-Key: inv-2026-0042" \
  -H "Content-Type: application/json" \
  -d '{"command_id":"BusinessPayBill","party_b":"247247","account_reference":"INV-0042","amount":2500}'</code></pre></div><div class="space-y-1.5"><div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Node.js</div><pre class="bg-[#05080f] border border-slate-200 rounded-sm p-3 text-[11px] text-slate-700 overflow-x-auto"><code>const res = await fetch("https://api.avenpay.co.ke/b2c/payout/b2b_pay", {
  method: "POST",
  headers: {
    "X-Wallet-ID": process.env.AVENPAY_WALLET_ID,
    "X-Security-Credential": process.env.AVENPAY_SECURITY_CREDENTIAL,
    "Idempotency-Key": "inv-2026-0042",
    "Content-Type": "application/json"
  },
  body: JSON.stringify({ command_id: "BusinessBuyGoods", party_b: "5123456", amount: 1200 })
});
console.log(await res.json());</code></pre></div><div class="space-y-1.5"><div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">PHP</div><pre class="bg-[#05080f] border border-slate-200 rounded-sm p-3 text-[11px] text-slate-700 overflow-x-auto"><code>&lt;?php
$ch = curl_init("https://api.avenpay.co.ke/b2c/payout/b2b_pay");
curl_setopt_array($ch, [
  CURLOPT_POST =&gt; true, CURLOPT_RETURNTRANSFER =&gt; true,
  CURLOPT_HTTPHEADER =&gt; ["X-Wallet-ID: WALLET_XYZ123", "X-Security-Credential: your_security_credential", "Idempotency-Key: inv-2026-0042", "Content-Type: application/json"],
  CURLOPT_POSTFIELDS =&gt; json_encode(["command_id" =&gt; "BusinessPayBill", "party_b" =&gt; "247247", "account_reference" =&gt; "INV-0042", "amount" =&gt; 2500]),
]);
echo curl_exec($ch);</code></pre></div><div class="space-y-1.5"><div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Success response</div><pre class="bg-[#05080f] border border-slate-200 rounded-sm p-3 text-[11px] text-slate-700 overflow-x-auto"><code>{ "status": "success",
  "conversation_id": "AG_20260101_0000abc",
  "message": "Business payment initiated successfully! A total of KES 2,525 has been reserved. You will be notified when the transfer completes." }</code></pre></div></div>

<div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] p-6 rounded-sm shadow-xl space-y-4"><h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Check a payment</h2>
<div class="text-[11px] font-mono"><span class="px-2 py-0.5 bg-slate-200 text-slate-900 rounded-sm font-bold">GET</span> <span class="text-slate-700">/b2c/payout/b2b_status?conversation_id=... or ?idempotency_key=...</span></div>
<p class="text-xs text-slate-500"><code class="text-amber-700">state</code> is <b>pending</b>, <b>completed</b> or <b>failed</b>. Poll every few seconds for up to a minute; do not poll faster than twice a second.</p>
<div class="space-y-1.5"><div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Request and response</div><pre class="bg-[#05080f] border border-slate-200 rounded-sm p-3 text-[11px] text-slate-700 overflow-x-auto"><code>curl "https://api.avenpay.co.ke/b2c/payout/b2b_status?idempotency_key=inv-2026-0042" \
  -H "X-Wallet-ID: WALLET_XYZ123" -H "X-Security-Credential: your_security_credential"

{ "status": "success",
  "payment": { "state": "completed", "conversation_id": "AG_20260101_0000abc", "command_id": "BusinessPayBill",
               "party_b": "247247", "account_reference": "INV-0042", "amount": 2500, "fee": 25,
               "mpesa_receipt": "SGX1234ABC", "description": "The service request is processed successfully.",
               "created_at": "2026-01-01 10:00:00", "completed_at": "2026-01-01 10:00:07" } }</code></pre></div></div>

<div class="bg-[rgba(15,23,42,0.95)] border border-[rgba(255,255,255,0.08)] p-6 rounded-sm shadow-xl space-y-4"><h2 class="text-sm font-black text-slate-900 uppercase tracking-wider">Errors</h2>
<table class="w-full text-xs"><tbody><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">400</td><td class="p-2 text-slate-500"></td><td class="p-2 text-slate-600">validation</td><td class="p-2 text-slate-600">Bad Paybill/Till number, missing account reference, insufficient wallet balance, daily limit would be exceeded.</td></tr><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">401 / 403</td><td class="p-2 text-slate-500"></td><td class="p-2 text-slate-600">auth</td><td class="p-2 text-slate-600">Missing or wrong X-Wallet-ID / X-Security-Credential.</td></tr><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">409</td><td class="p-2 text-slate-500"></td><td class="p-2 text-slate-600">duplicate key in flight</td><td class="p-2 text-slate-600">A second in-flight request with the same Idempotency-Key is rejected. If a prior submission is already stored, the API can instead return HTTP 200 with <code>duplicate: true</code>.</td></tr><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">429</td><td class="p-2 text-slate-500"></td><td class="p-2 text-slate-600">rate limit</td><td class="p-2 text-slate-600">More than 20 requests a minute. Retry after the Retry-After header.</td></tr><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">502</td><td class="p-2 text-slate-500"></td><td class="p-2 text-slate-600">provider unreachable</td><td class="p-2 text-slate-600">The provider connection failed before the request was sent; check the response before retrying.</td></tr><tr class="border-t border-slate-200"><td class="p-2 font-mono text-amber-700">202</td><td class="p-2 text-slate-500"></td><td class="p-2 text-slate-600">pending</td><td class="p-2 text-slate-600">If the provider outcome is uncertain, funds may remain reserved. Poll b2b_status and do not submit a new payment with a different key until the first outcome is known.</td></tr></tbody></table>
<p class="text-xs text-slate-500">Every error body looks like <code class="text-amber-700">{"status":"error","message":"..."}</code>.</p></div>
</main>

<?php 
$footer_path = $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; 
if (file_exists($footer_path)) {
    include $footer_path;
}
?>

</body>
</html>