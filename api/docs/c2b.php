<?php
if (file_exists(__DIR__ . '/database_config/db.php')) {
    require_once __DIR__ . '/database_config/db.php';
}

// Canonical public API origin. AvenPay does not require an api. subdomain.
$currentGatewayUrl = 'https://www.avenpay.co.ke/api/';

include $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#ffffff">

    <title>STK Push (C2B) API - AvenPay Docs</title>

    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <style>
        :root {
            color-scheme: light;
            --page: #f4f7fb;
            --surface: #ffffff;
            --ink: #172033;
            --muted: #475569;
            --line: #dbe3ed;
            --accent: #4fdcb9;
            --accent-soft: #e6f7f2;
            --code: #f1f5f9;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--page);
            color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system,
                BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .docs-shell {
            max-width: 1180px;
            margin: 0 auto;
            padding: 28px 24px 72px;
        }

        .hero {
            position: relative;
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 60%, #eef7f5 100%);
            color: #172033;
            padding: 38px;
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.14);
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 330px;
            height: 330px;
            right: -95px;
            top: -155px;
            border-radius: 50%;
            border: 1px solid rgba(15, 23, 42, 0.13);
            box-shadow:
                0 0 0 35px rgba(255, 255, 255, 0.035),
                0 0 0 75px rgba(255, 255, 255, 0.025);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 760px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #1a7f66;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
        }

        .hero h1 {
            margin-top: 18px;
            font-size: clamp(30px, 5vw, 48px);
            line-height: 1.08;
            letter-spacing: -0.045em;
            font-weight: 800;
        }

        .hero p {
            max-width: 680px;
            margin-top: 16px;
            color: #334155;
            font-size: 14px;
            line-height: 1.8;
        }

        .hero-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 22px;
        }

        .hero-pill {
            border: 1px solid rgba(15, 23, 42, 0.16);
            background: rgba(255, 255, 255, 0.9);
            color: #0f172a;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: 11px;
            font-weight: 700;
        }

        .layout {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            gap: 28px;
            margin-top: 28px;
            align-items: start;
        }

        .toc {
            position: sticky;
            top: 24px;
            padding: 18px 12px;
            background: rgba(248, 250, 252, 0.88);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.14);
        }

        .toc-title {
            padding: 0 10px 10px;
            color: #64748b;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        .toc a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border-radius: 9px;
            color: #334155;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: 0.18s ease;
        }

        .toc a:hover,
        .toc a.active {
            color: var(--accent);
            background: var(--accent-soft);
        }

        .toc-number {
            width: 20px;
            height: 20px;
            display: inline-grid;
            place-items: center;
            border-radius: 6px;
            background: #f1f5f9;
            color: #475569;
            font-size: 10px;
            font-weight: 800;
        }

        .toc a:hover .toc-number,
        .toc a.active .toc-number {
            background: #e2e8f0;
            color: var(--accent);
        }

        .docs-content {
            min-width: 0;
        }

        .section-card {
            margin-bottom: 20px;
            padding: 25px;
            background: linear-gradient(145deg, #f8fafc, #f8fafc);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.13);
            scroll-margin-top: 24px;
        }

        .section-heading {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            margin-bottom: 12px;
        }

        .section-icon {
            width: 36px;
            height: 36px;
            flex: 0 0 36px;
            display: grid;
            place-items: center;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            background: #e6f7f2;
            color: #087f67;
            font-size: 14px;
        }

        .section-heading h2 {
            color: var(--ink);
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.025em;
        }

        .section-heading p,
        .section-intro {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.75;
        }

        .section-intro {
            margin: 0 0 17px;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--line);
            border-radius: 12px;
        }

        table {
            width: 100%;
            min-width: 580px;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            padding: 12px 14px;
            background: #f1f5f9;
            color: #475569;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        td {
            padding: 13px 14px;
            border-top: 1px solid #e2e8f0;
            color: #334155;
            font-size: 12px;
            line-height: 1.6;
            vertical-align: top;
        }

        td strong {
            color: #2c486d;
        }

        code.inline {
            padding: 2px 5px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            background: #f1f5f9;
            color: #198066;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11px;
        }

        .method {
            display: inline-flex;
            align-items: center;
            border-radius: 6px;
            padding: 5px 8px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 10px;
            font-weight: 800;
        }

        .method-post {
            background: #dcfce7;
            color: #166534;
        }

        .method-get {
            background: #ccfbf1;
            color: #0f766e;
        }

        .endpoint-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            padding: 12px 14px;
            margin: 15px 0;
            border: 1px solid #e2e8f0;
            border-radius: 11px;
            background: #f8fafc;
        }

        .endpoint-bar code {
            overflow-wrap: anywhere;
            color: #2d496c;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11px;
        }

        .copy-button {
            flex: 0 0 auto;
            margin-left: auto;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            background: #f1f5f9;
            color: #475569;
            padding: 6px 9px;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        .copy-button:hover {
            border-color: #3d8c83;
            color: var(--accent);
        }

        .code-box {
            overflow: hidden;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: var(--code);
            color: #1e293b;
        }

        .code-tabs {
            display: flex;
            overflow-x: auto;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .code-tab {
            flex: 0 0 auto;
            padding: 11px 15px;
            border: 0;
            border-right: 1px solid #e2e8f0;
            background: transparent;
            color: #475569;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        .code-tab[aria-selected="true"] {
            background: var(--code);
            color: #198066;
        }

        .code-panel {
            position: relative;
            padding: 20px;
        }

        .code-panel pre {
            overflow-x: auto;
            margin: 0;
            padding-top: 22px;
            color: #1e293b;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 11px;
            line-height: 1.75;
            tab-size: 4;
        }

        .code-panel pre code {
            font-family: inherit;
        }

        .code-copy {
            position: absolute;
            top: 12px;
            right: 12px;
            z-index: 1;
            border: 1px solid #e2e8f0;
            border-radius: 7px;
            background: #f1f5f9;
            color: #334155;
            padding: 6px 9px;
            font-size: 10px;
            font-weight: 700;
            cursor: pointer;
        }

        .code-copy:hover {
            color: #0f172a;
            background: #e2e8f0;
        }

        .notice {
            display: flex;
            gap: 10px;
            margin-top: 14px;
            padding: 12px 14px;
            border: 1px solid #66552b;
            border-radius: 10px;
            background: #f1f5f9;
            color: #7e631b;
            font-size: 11px;
            line-height: 1.65;
        }

        .notice i {
            margin-top: 2px;
        }

        .response-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }

        .response-card {
            overflow: hidden;
            border: 1px solid var(--line);
            border-radius: 12px;
        }

        .response-title {
            padding: 10px 13px;
            background: #f1f5f9;
            color: #d1dae6;
            font-size: 11px;
            font-weight: 800;
        }

        .response-card pre {
            overflow-x: auto;
            margin: 0;
            padding: 14px;
            background: #ffffff;
            color: #1e293b;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 10px;
            line-height: 1.7;
        }

        .status-success {
            color: #16834a;
        }

        .status-pending {
            color: #8e660b;
        }

        button:focus-visible,
        a:focus-visible {
            outline: 3px solid #50dcb9;
            outline-offset: 3px;
        }

        @media (max-width: 760px) {
            .docs-shell {
                padding: 18px 14px 48px;
            }

            .hero {
                padding: 27px 22px;
                border-radius: 18px;
            }

            .layout {
                display: block;
                margin-top: 18px;
            }

            .toc {
                position: static;
                display: flex;
                gap: 5px;
                overflow-x: auto;
                margin-bottom: 16px;
                padding: 9px;
            }

            .toc-title {
                display: none;
            }

            .toc a {
                flex: 0 0 auto;
                padding: 8px;
                font-size: 10px;
            }

            .section-card {
                padding: 18px;
                border-radius: 15px;
            }

            .response-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
            }
        }
    </style>

<style id="avenpay-docs-readable-theme">
  html,body { background:#f4f7fb !important; color:#172033 !important; color-scheme:light; }
  main { color:#172033 !important; }
  main [class*="bg-[#"], main [class*="rgba(15,23,42"], main .code-block {
    background:#ffffff !important; color:#1e293b !important; border-color:#dbe3ed !important;
    box-shadow:0 8px 28px rgba(15,23,42,.06) !important;
  }
  main h1,main h2,main h3,main h4,main strong,main b { color:#172033 !important; }
  main p,main li,main td,main th { color:#475569 !important; opacity:1 !important; }
  main pre,main code { background:#f1f5f9 !important; color:#1e293b !important;
    border:1px solid #dbe3ed !important; text-shadow:none !important; }
  main pre { padding:1rem !important; border-radius:.65rem !important; overflow-x:auto !important; }
  main input,main textarea { background:#fff !important; color:#172033 !important; border-color:#cbd5e1 !important; }
</style>

</head>

<body>
    <main class="docs-shell">
        <header class="hero">
            <div class="hero-content">
                <div class="eyebrow">
                    <i class="fa-solid fa-circle-nodes" aria-hidden="true"></i>
                    Developer documentation
                </div>

                <h1>C2B Payments API</h1>

                <p>
                    Integrate M-Pesa STK Push payments into your application.
                    Initiate payment prompts, verify transaction status, and
                    receive callback notifications using the AvenPay API.
                </p>

                <div class="hero-pills">
                    <span class="hero-pill">M-Pesa STK Push</span>
                    <span class="hero-pill">REST API</span>
                    <span class="hero-pill">JSON &amp; Form Requests</span>
                </div>
            </div>
        </header>

        <div class="layout">
            <nav class="toc" aria-label="Documentation sections">
                <div class="toc-title">On this page</div>
                <a href="#endpoints"><span class="toc-number">00</span> Endpoints</a>
                <a href="#authentication"><span class="toc-number">01</span> Authentication</a>
                <a href="#stk-push"><span class="toc-number">02</span> STK Push</a>
                <a href="#status"><span class="toc-number">03</span> Check status</a>
                <a href="#callbacks"><span class="toc-number">04</span> Callbacks</a>
                <a href="#result-codes"><span class="toc-number">05</span> Result codes</a>
            </nav>

            <div class="docs-content">
                <section id="endpoints" class="section-card">
                    <div class="section-heading">
                        <div class="section-icon">
                            <i class="fa-solid fa-route" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h2>Endpoint overview</h2>
                            <p>All endpoints use your current host with the fixed <code class="inline">/api/</code> base path.</p>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Operation</th>
                                    <th>Method</th>
                                    <th>Endpoint</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>STK Push</strong></td>
                                    <td><span class="method method-post">POST</span></td>
                                    <td><code class="inline" data-endpoint="stk_push"></code></td>
                                    <td>Initiate a customer payment prompt.</td>
                                </tr>
                                <tr>
                                    <td><strong>Check status</strong></td>
                                    <td><span class="method method-get">GET</span></td>
                                    <td><code class="inline" data-status-endpoint></code></td>
                                    <td>Check a transaction's status.</td>
                                </tr>
                                <tr>
                                    <td><strong>Callback</strong></td>
                                    <td><span class="method method-post">POST</span></td>
                                    <td><code class="inline" data-endpoint="callback"></code></td>
                                    <td>Receive payment notifications.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section id="authentication" class="section-card">
                    <div class="section-heading">
                        <div class="section-icon">
                            <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h2>Authentication</h2>
                            <p>Keep your merchant API key private and make payment requests from your server.</p>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Type</th>
                                    <th>Location</th>
                                    <th>Purpose</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code class="inline">x_api_key</code></td>
                                    <td>String</td>
                                    <td>STK Push request body</td>
                                    <td>Authenticates your merchant account.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="notice">
                        <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                        <span>
                            Do not place production API keys in frontend JavaScript,
                            public repositories, or client apps. Load the key from
                            a secure server-side environment variable.
                        </span>
                    </div>
                </section>

                <section id="stk-push" class="section-card">
                    <div class="section-heading">
                        <div class="section-icon">
                            <i class="fa-solid fa-mobile-screen-button" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h2>Initiate an STK Push</h2>
                            <p>Ask the customer to authorize a payment on their phone.</p>
                        </div>
                    </div>

                    <p class="section-intro">
                        A successful initiation response only means the request
                        was accepted. Confirm the final payment result through
                        the status endpoint or a verified callback.
                    </p>

                    <div class="endpoint-bar">
                        <span class="method method-post">POST</span>
                        <code data-endpoint="stk_push"></code>
                        <button
                            type="button"
                            class="copy-button"
                            onclick="copyText(this.previousElementSibling.innerText, this)"
                        >Copy URL</button>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Parameter</th>
                                    <th>Type</th>
                                    <th>Required</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code class="inline">x_api_key</code></td>
                                    <td>String</td>
                                    <td>Yes</td>
                                    <td>Your merchant API key.</td>
                                </tr>
                                <tr>
                                    <td><code class="inline">phone_number</code></td>
                                    <td>String</td>
                                    <td>Yes</td>
                                    <td>Customer phone number, e.g. <code class="inline">0712345678</code> or <code class="inline">254712345678</code>.</td>
                                </tr>
                                <tr>
                                    <td><code class="inline">amount</code></td>
                                    <td>Integer</td>
                                    <td>Yes</td>
                                    <td>Payment amount in KES.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="code-box mt-4" data-tab-group="stk">
                        <div class="code-tabs" role="tablist" aria-label="STK Push examples">
                            <button type="button" class="code-tab" role="tab" aria-selected="true" data-tab="stk_php">PHP</button>
                            <button type="button" class="code-tab" role="tab" aria-selected="false" data-tab="stk_node">Node.js</button>
                            <button type="button" class="code-tab" role="tab" aria-selected="false" data-tab="stk_python">Python</button>
                            <button type="button" class="code-tab" role="tab" aria-selected="false" data-tab="stk_curl">cURL</button>
                        </div>

                        <div class="code-panel">
                            <button type="button" class="code-copy" onclick="copyCodeBlock(this)">Copy code</button>

                            <div id="stk_php" class="tab-content" role="tabpanel">
<pre><code>&lt;?php
$url = <?= json_encode($currentGatewayUrl . 'stk_push', JSON_UNESCAPED_SLASHES) ?>;

$payload = [
    'x_api_key'    =&gt; getenv('AVENPAY_API_KEY'),
    'phone_number' =&gt; '0712345678',
    'amount'       =&gt; 10,
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           =&gt; true,
    CURLOPT_POSTFIELDS     =&gt; http_build_query($payload),
    CURLOPT_RETURNTRANSFER =&gt; true,
    CURLOPT_CONNECTTIMEOUT =&gt; 10,
    CURLOPT_TIMEOUT        =&gt; 30,
]);

$response = curl_exec($ch);

if ($response === false) {
    throw new RuntimeException(curl_error($ch));
}

curl_close($ch);
echo $response;
?&gt;</code></pre>
                            </div>

                            <div id="stk_node" class="tab-content" role="tabpanel" hidden>
<pre><code>const axios = require('axios');

const url = <?= json_encode($currentGatewayUrl . 'stk_push', JSON_UNESCAPED_SLASHES) ?>;

axios.post(url, new URLSearchParams({
  x_api_key: process.env.AVENPAY_API_KEY,
  phone_number: '0712345678',
  amount: '10'
}).toString(), {
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
  timeout: 30000
})
.then(response =&gt; console.log(response.data))
.catch(error =&gt; console.error(error.response?.data || error.message));</code></pre>
                            </div>

                            <div id="stk_python" class="tab-content" role="tabpanel" hidden>
<pre><code>import os
import requests

url = <?= json_encode($currentGatewayUrl . 'stk_push', JSON_UNESCAPED_SLASHES) ?>

payload = {
    "x_api_key": os.environ["AVENPAY_API_KEY"],
    "phone_number": "0712345678",
    "amount": 10
}

response = requests.post(url, data=payload, timeout=30)
response.raise_for_status()
print(response.json())</code></pre>
                            </div>

                            <div id="stk_curl" class="tab-content" role="tabpanel" hidden>
<pre><code>curl --request POST \
  <?= json_encode($currentGatewayUrl . 'stk_push', JSON_UNESCAPED_SLASHES) ?> \
  --header "Content-Type: application/x-www-form-urlencoded" \
  --data-urlencode "x_api_key=$AVENPAY_API_KEY" \
  --data-urlencode "phone_number=0712345678" \
  --data-urlencode "amount=10"</code></pre>
                            </div>
                        </div>
                    </div>

                    <div class="notice">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <span>
                            Example initiation response:
                            <code class="inline">{"status":"success","checkout_id":"ws_CO_..."}</code>.
                            Use the actual response fields returned by your API.
                        </span>
                    </div>
                </section>

                <section id="status" class="section-card">
                    <div class="section-heading">
                        <div class="section-icon">
                            <i class="fa-solid fa-magnifying-glass-chart" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h2>Check transaction status</h2>
                            <p>Use the checkout ID returned by the STK Push request.</p>
                        </div>
                    </div>

                    <div class="endpoint-bar">
                        <span class="method method-get">GET</span>
                        <code data-status-endpoint></code>
                        <button
                            type="button"
                            class="copy-button"
                            onclick="copyText(this.previousElementSibling.innerText, this)"
                        >Copy URL</button>
                    </div>

                    <p class="section-intro">
                        Query parameter: <code class="inline">check_status</code>.
                        Respect your API's rate limits when polling.
                    </p>

                    <div class="code-box" data-tab-group="poll">
                        <div class="code-tabs" role="tablist" aria-label="Status check examples">
                            <button type="button" class="code-tab" role="tab" aria-selected="true" data-tab="poll_php">PHP</button>
                            <button type="button" class="code-tab" role="tab" aria-selected="false" data-tab="poll_node">Node.js</button>
                            <button type="button" class="code-tab" role="tab" aria-selected="false" data-tab="poll_python">Python</button>
                            <button type="button" class="code-tab" role="tab" aria-selected="false" data-tab="poll_curl">cURL</button>
                        </div>

                        <div class="code-panel">
                            <button type="button" class="code-copy" onclick="copyCodeBlock(this)">Copy code</button>

                            <div id="poll_php" class="tab-content" role="tabpanel">
<pre><code>&lt;?php
$checkoutId = 'ws_CO_070720261234567890';

$url = <?= json_encode($currentGatewayUrl . 'stk_push?check_status=', JSON_UNESCAPED_SLASHES) ?>
    . rawurlencode($checkoutId);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER =&gt; true,
    CURLOPT_CONNECTTIMEOUT =&gt; 10,
    CURLOPT_TIMEOUT        =&gt; 30,
]);

$response = curl_exec($ch);

if ($response === false) {
    throw new RuntimeException(curl_error($ch));
}

curl_close($ch);
echo $response;
?&gt;</code></pre>
                            </div>

                            <div id="poll_node" class="tab-content" role="tabpanel" hidden>
<pre><code>const axios = require('axios');

const url = <?= json_encode($currentGatewayUrl . 'stk_push', JSON_UNESCAPED_SLASHES) ?>;

axios.get(url, {
  params: { check_status: 'ws_CO_070720261234567890' },
  timeout: 30000
})
.then(response =&gt; console.log(response.data))
.catch(error =&gt; console.error(error.response?.data || error.message));</code></pre>
                            </div>

                            <div id="poll_python" class="tab-content" role="tabpanel" hidden>
<pre><code>import requests

url = <?= json_encode($currentGatewayUrl . 'stk_push', JSON_UNESCAPED_SLASHES) ?>

response = requests.get(
    url,
    params={"check_status": "ws_CO_070720261234567890"},
    timeout=30
)
response.raise_for_status()
print(response.json())</code></pre>
                            </div>

                            <div id="poll_curl" class="tab-content" role="tabpanel" hidden>
<pre><code>curl --get \
  <?= json_encode($currentGatewayUrl . 'stk_push', JSON_UNESCAPED_SLASHES) ?> \
  --data-urlencode "check_status=ws_CO_070720261234567890"</code></pre>
                            </div>
                        </div>
                    </div>

                    <div class="response-grid mt-4">
                        <div class="response-card">
                            <div class="response-title status-success">
                                <i class="fa-solid fa-circle-check mr-1" aria-hidden="true"></i>
                                Successful payment
                            </div>
<pre><code>{
  "id": "241",
  "status": "SUCCESS",
  "phone_number": "254712345678",
  "amount": "10.00",
  "mpesa_receipt_number": "TGR7XYZ890"
}</code></pre>
                        </div>

                        <div class="response-card">
                            <div class="response-title status-pending">
                                <i class="fa-solid fa-clock mr-1" aria-hidden="true"></i>
                                Pending payment
                            </div>
<pre><code>{
  "id": "241",
  "status": "PENDING",
  "phone_number": "254712345678",
  "amount": "10.00",
  "mpesa_receipt_number": null
}</code></pre>
                        </div>
                    </div>
                </section>

                <section id="callbacks" class="section-card">
                    <div class="section-heading">
                        <div class="section-icon">
                            <i class="fa-solid fa-arrow-right-arrow-left" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h2>Safaricom callback and merchant webhooks</h2>
                            <p>Safaricom calls AvenPay first; AvenPay then notifies your configured merchant webhook.</p>
                        </div>
                    </div>

                    <p class="section-intro">
                        Safaricom sends its STK callback to AvenPay at <code class="inline">https://www.avenpay.co.ke/api/callback.php</code>. This is an internal receiver protected by AvenPay callback authentication; merchants must not register it as their own callback endpoint. Set your own HTTPS webhook URL in the merchant settlement settings. AvenPay sends your server a POST with the JSON payload shown below and an <code class="inline">X-AvenPay-Signature</code> header.
                    </p>

                    <div class="endpoint-bar">
                        <span class="method method-post">POST</span>
                        <code>Safaricom → AvenPay: /api/callback.php (internal)</code>
                        <button
                            type="button"
                            class="copy-button"
                            onclick="copyText(this.previousElementSibling.innerText, this)"
                        >Copy URL</button>
                    </div>

                    <div class="code-box">
                        <div class="code-panel">
                            <button type="button" class="code-copy" onclick="copyCodeBlock(this)">Copy payload</button>
<pre><code>{
  "checkout_id": "ws_CO_070720261234567890",
  "status": "SUCCESS",
  "mpesa_receipt": "TGR7XYZ890",
  "amount": 10.00,
  "phone": "254712345678",
  "result_code": 0
}</code></pre>
                        </div>
                    </div>

                    <div class="notice">
                        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        <span>
                            Verify the <code class="inline">X-AvenPay-Signature</code> header using HMAC-SHA256 over <code class="inline">timestamp + "." + raw request body</code> and your settlement webhook secret. Reject stale timestamps, validate amount and receipt, and process each checkout ID idempotently before fulfilling an order. Keep the webhook secret private.
                        </span>
                    </div>
                </section>

                <section id="result-codes" class="section-card">
                    <div class="section-heading">
                        <div class="section-icon">
                            <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h2>Common result codes</h2>
                            <p>Example meanings often seen in M-Pesa STK integrations.</p>
                        </div>
                    </div>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th>Typical meaning</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code class="inline">0</code></td>
                                    <td class="status-success"><strong>SUCCESS</strong></td>
                                    <td>Transaction reported as successful. Verify receipt and amount before fulfilling an order.</td>
                                </tr>
                                <tr>
                                    <td><code class="inline">1032</code></td>
                                    <td><strong>FAILED</strong></td>
                                    <td>Commonly associated with a customer-cancelled STK request.</td>
                                </tr>
                                <tr>
                                    <td><code class="inline">1037</code></td>
                                    <td><strong>FAILED / TIMEOUT</strong></td>
                                    <td>Commonly associated with a request timeout or unreachable handset.</td>
                                </tr>
                                <tr>
                                    <td><code class="inline">2001</code></td>
                                    <td><strong>FAILED</strong></td>
                                    <td>Commonly associated with an invalid or rejected customer PIN.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="notice">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                        <span>
                            Confirm result-code meanings against the Safaricom
                            and AvenPay API versions you use. The deployed
                            gateway's documentation is authoritative.
                        </span>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>

    <script>
        const AVENPAY_API_BASE = <?= json_encode($currentGatewayUrl, JSON_UNESCAPED_SLASHES) ?>;

        function activateTab(group, panelId) {
            group.querySelectorAll('[role="tab"]').forEach(tab => {
                const isActive = tab.dataset.tab === panelId;
                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                tab.tabIndex = isActive ? 0 : -1;
            });

            group.querySelectorAll('.tab-content').forEach(panel => {
                panel.hidden = panel.id !== panelId;
            });
        }

        document.querySelectorAll('[data-tab-group]').forEach(group => {
            const tabs = Array.from(group.querySelectorAll('[role="tab"]'));

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    activateTab(group, tab.dataset.tab);
                });

                tab.addEventListener('keydown', event => {
                    if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                        return;
                    }

                    event.preventDefault();

                    const currentIndex = tabs.indexOf(tab);
                    let nextIndex = currentIndex;

                    if (event.key === 'ArrowRight') {
                        nextIndex = (currentIndex + 1) % tabs.length;
                    } else if (event.key === 'ArrowLeft') {
                        nextIndex = (currentIndex - 1 + tabs.length) % tabs.length;
                    } else if (event.key === 'Home') {
                        nextIndex = 0;
                    } else {
                        nextIndex = tabs.length - 1;
                    }

                    tabs[nextIndex].click();
                    tabs[nextIndex].focus();
                });
            });
        });

        async function copyText(text, button) {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                } else {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();

                    const copied = document.execCommand('copy');
                    textarea.remove();

                    if (!copied) {
                        throw new Error('Copy failed');
                    }
                }

                const oldText = button.textContent;
                button.textContent = 'Copied!';
                setTimeout(() => {
                    button.textContent = oldText;
                }, 1500);
            } catch (error) {
                alert('Copy failed. Please select and copy the text manually.');
            }
        }

        function copyCodeBlock(button) {
            const code = button.parentElement.querySelector('pre code')
                || button.parentElement.querySelector('pre');

            if (code) {
                copyText(code.innerText, button);
            }
        }

        document.querySelectorAll('[data-endpoint]').forEach(element => {
            element.textContent = AVENPAY_API_BASE + element.dataset.endpoint;
        });

        document.querySelectorAll('[data-status-endpoint]').forEach(element => {
            element.textContent = AVENPAY_API_BASE
                + 'stk_push?check_status={CHECKOUT_ID}';
        });

        const tocLinks = Array.from(document.querySelectorAll('.toc a'));
        const observedSections = tocLinks
            .map(link => document.querySelector(link.getAttribute('href')))
            .filter(Boolean);

        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) return;

                    tocLinks.forEach(link => {
                        link.classList.toggle(
                            'active',
                            link.getAttribute('href') === '#' + entry.target.id
                        );
                    });
                });
            }, {
                rootMargin: '-20% 0px -65% 0px'
            });

            observedSections.forEach(section => observer.observe(section));
        }
    </script>
</body>
</html>