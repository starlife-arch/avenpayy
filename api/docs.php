<?php
// Include database configuration
if (file_exists('database_config/db.php')) {
    require_once 'database_config/db.php';
}
include $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';

// Dynamic Domain & Environment Sensing Logic for Documentation Base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$scriptUri = $_SERVER['REQUEST_URI'];

// Strip the current filename to find the base directory path
$baseDir = substr($scriptUri, 0, strrpos($scriptUri, '/')) . '/';
$currentGatewayUrl = $protocol . $host . $baseDir;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta name="theme-color" content="#ffffff">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Documentation - AvenPay</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Hardware-accelerated transitions */
        .sidebar-transition {
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        /* Gear spinning utility */
        @keyframes spin-slow {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .animate-spin-hover:hover {
            animation: spin-slow 3s linear infinite;
        }
        /* Custom scrollbar profiles for code windows */
        .custom-scrollbar::-webkit-scrollbar {
            height: 6px;
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f8fafc;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #e2e8f0;
            border-radius: 3px;
        }
    </style>
</head>


    <div id="sidebarBackdrop" onclick="toggleSidebarLayout(false)" class="fixed inset-0 bg-slate-50/50 z-40 transition-opacity duration-300" style="display: none; opacity: 0;"></div>

    <main class="flex-1 p-4 md:p-8 max-w-5xl w-full mx-auto space-y-10">

        <div class="space-y-2 border-b border-slate-200 pb-5">
            <h1 class="text-2xl md:text-3xl font-extrabold tracking-tight text-slate-900 flex items-center gap-3">
                <i class="fa-solid fa-book text-teal-600 text-xl md:text-2xl"></i> Developer API Documentation
            </h1>
            <p class="text-slate-500 text-xs md:text-sm max-w-3xl">
                Welcome to the AvenPay multi-tenant payment gateway. Integrate instantaneous secure M-Pesa STK push checkout procedures, automated backend status querying, and fast processing listeners.
            </p>
        </div>

<div class="bg-white border-l-4 border-teal-500 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
    <div class="text-xs md:text-sm font-semibold text-slate-600 break-all">
        Gateway Endpoint URL: 
        <code id="baseUrlText" class="ml-1 mt-2 sm:mt-0 block sm:inline-block bg-slate-50 text-teal-600 px-2 py-1 font-mono font-normal text-xs border border-slate-200"><?php echo $currentGatewayUrl; ?></code>
    </div>
    
    <button onclick="copyTextById('baseUrlText', this)" class="flex-shrink-0 bg-slate-100 border border-slate-300 text-slate-600 px-3 py-1.5 text-xs font-bold hover:bg-slate-200 hover:text-slate-900 transition cursor-pointer flex items-center justify-center gap-1.5">
        <i class="fa-regular fa-copy"></i> Copy URL
    </button>
</div>

        <section class="space-y-3">
            <h2 class="text-lg font-bold text-slate-900 tracking-wide border-b border-slate-200 pb-1.5">1. Authentication Security Parameters</h2>
            <p class="text-xs md:text-sm text-slate-500">
                Every API transaction executing against production hooks routes through server validating schemas using your personal gateway profile signature. Send this signature in your transaction execution payloads:
            </p>
            <div class="bg-white/50 border border-slate-200/80 overflow-hidden">
                <table class="w-full border-collapse text-xs md:text-sm text-left">
                    <thead>
                        <tr class="bg-white text-slate-600 border-b border-slate-200 font-semibold">
                            <th class="p-3">Requirement Field</th>
                            <th class="p-3">Data Variable Typology</th>
                            <th class="p-3">Payload Location</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-500 divide-y divide-slate-200/60">
                        <tr>
                            <td class="p-3 font-mono font-bold text-rose-600">x_api_key</td>
                            <td class="p-3 font-mono">String</td>
                            <td class="p-3">HTTP Body Data Parameter</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-bold text-slate-900 tracking-wide border-b border-slate-200 pb-1.5">2. Initialize Payment Prompt (`stk_push`)</h2>
            <p class="text-xs md:text-sm text-slate-500">
                Triggers an automated transaction interface directly on your user's smartphone handset screen matching standard merchant billing routines.
            </p>

            <div class="flex items-center gap-2 bg-white p-3 font-mono text-xs border border-slate-200 overflow-x-auto custom-scrollbar">
                <span class="bg-teal-500/10 text-teal-600 border border-teal-500/20 px-2 py-0.5 font-bold text-[10px]">POST</span>
                <span class="text-slate-600 font-medium"><?php echo $currentGatewayUrl; ?>stk_push</span>
            </div>

            <div class="space-y-2">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Required Field Protocol</h3>
                <div class="bg-white/50 border border-slate-200/80 overflow-hidden">
                    <table class="w-full border-collapse text-xs md:text-sm text-left">
                        <thead>
                            <tr class="bg-white text-slate-600 border-b border-slate-200 font-semibold">
                                <th class="p-3">Parameter</th>
                                <th class="p-3">Type</th>
                                <th class="p-3">Mandate</th>
                                <th class="p-3">Description Map</th>
                            </tr>
                        </thead>
                        <tbody class="text-slate-500 divide-y divide-slate-200/60">
                            <tr>
                                <td class="p-3 font-mono font-bold text-rose-600">x_api_key</td>
                                <td class="p-3 font-mono">String</td>
                                <td class="p-3"><span class="bg-rose-500/10 text-rose-600 px-2 py-0.5 border border-rose-500/20 text-[10px] font-bold">REQUIRED</span></td>
                                <td class="p-3">Your private live production gateway authorization hash.</td>
                            </tr>
                            <tr>
                                <td class="p-3 font-mono font-bold text-rose-600">phone_number</td>
                                <td class="p-3 font-mono">String</td>
                                <td class="p-3"><span class="bg-rose-500/10 text-rose-600 px-2 py-0.5 border border-rose-500/20 text-[10px] font-bold">REQUIRED</span></td>
                                <td class="p-3">Safaricom subscriber identity. Supports formats: <code>07XXXXXXXX</code>, <code>01XXXXXXXX</code>, or <code>254XXXXXXXX</code>.</td>
                            </tr>
                            <tr>
                                <td class="p-3 font-mono font-bold text-rose-600">amount</td>
                                <td class="p-3 font-mono">Integer</td>
                                <td class="p-3"><span class="bg-rose-500/10 text-rose-600 px-2 py-0.5 border border-rose-500/20 text-[10px] font-bold">REQUIRED</span></td>
                                <td class="p-3">Total KES numeric capacity volume values to collect. Minimum: <code>1</code>.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-2">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Source Code</h3>
                <div class="border border-slate-200 flex flex-col bg-white">
                    <ul class="flex bg-slate-50 border-b border-slate-200 font-mono text-xs" id="stkTabsMenu">
                        <li class="px-4 py-2.5 cursor-pointer text-teal-600 font-bold border-r border-slate-200 bg-white" id="btn_stk_php" onclick="switchTab('stkTabsMenu', 'stk_php')">PHP</li>
                        <li class="px-4 py-2.5 cursor-pointer text-slate-500 border-r border-slate-200 hover:text-slate-700 hover:bg-white/40" id="btn_stk_node" onclick="switchTab('stkTabsMenu', 'stk_node')">Node.js</li>
                        <li class="px-4 py-2.5 cursor-pointer text-slate-500 border-r border-slate-200 hover:text-slate-700 hover:bg-white/40" id="btn_stk_python" onclick="switchTab('stkTabsMenu', 'stk_python')">Python</li>
                        <li class="px-4 py-2.5 cursor-pointer text-slate-500 hover:text-slate-700 hover:bg-white/40" id="btn_stk_curl" onclick="switchTab('stkTabsMenu', 'stk_curl')">cURL</li>
                    </ul>

                    <div class="p-4 relative">
                        <button onclick="copyCodeBlock(this)" class="absolute top-3 right-3 bg-slate-100/80 border border-slate-300 text-slate-500 hover:text-slate-900 px-2.5 py-1 text-[11px] font-bold transition cursor-pointer"><i class="fa-regular fa-copy"></i> Copy</button>
                        
                        <div id="stk_php" class="tab-content font-mono text-xs md:text-sm text-emerald-600 overflow-x-auto custom-scrollbar whitespace-pre"><pre><code>&lt;?php
$gatewayUrl = "<?php echo $currentGatewayUrl; ?>stk_push";

$payload = [
    'x_api_key'    => 'YOUR_MERCHANT_X_API_KEY',
    'phone_number' => '0712345678',
    'amount'       => 10
];

$ch = curl_init($gatewayUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
curl_close($ch);

echo $result;
?&gt;</code></pre></div>

                        <div id="stk_node" class="tab-content hidden font-mono text-xs md:text-sm text-teal-700 overflow-x-auto custom-scrollbar whitespace-pre"><pre><code>const axios = require('axios');
const querystring = require('querystring');

const url = '<?php echo $currentGatewayUrl; ?>stk_push';
const data = {
    x_api_key: 'YOUR_MERCHANT_X_API_KEY',
    phone_number: '0712345678',
    amount: 10
};

axios.post(url, querystring.stringify(data), {
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
})
.then(response => console.log(response.data))
.catch(error => console.error(error));</code></pre></div>

                        <div id="stk_python" class="tab-content hidden font-mono text-xs md:text-sm text-amber-700 overflow-x-auto custom-scrollbar whitespace-pre"><pre><code>import requests

url = "<?php echo $currentGatewayUrl; ?>stk_push"
payload = {
    "x_api_key": "YOUR_MERCHANT_X_API_KEY",
    "phone_number": "0712345678",
    "amount": 10
}

response = requests.post(url, data=payload)
print(response.json())</code></pre></div>

                        <div id="stk_curl" class="tab-content hidden font-mono text-xs md:text-sm text-slate-600 overflow-x-auto custom-scrollbar whitespace-pre"><pre><code>curl -X POST "<?php echo $currentGatewayUrl; ?>stk_push" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "x_api_key=YOUR_MERCHANT_X_API_KEY" \
  -d "phone_number=0712345678" \
  -d "amount=10"</code></pre></div>
                    </div>
                </div>
            </div>

            <div class="space-y-2">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Response Return</h3>
                <div class="border border-slate-200 bg-white p-4 relative font-mono text-xs md:text-sm text-teal-700 overflow-x-auto custom-scrollbar">
                    <button onclick="copyCodeBlock(this)" class="absolute top-3 right-3 bg-slate-100/80 border border-slate-300 text-slate-500 hover:text-slate-900 px-2.5 py-1 text-[11px] font-bold transition cursor-pointer"><i class="fa-regular fa-copy"></i> Copy</button>
<pre><code>{
  "status": "success",
  "checkout_id": "ws_CO_070720261234567890"
}</code></pre>
                </div>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-lg font-bold text-slate-900 tracking-wide border-b border-slate-200 pb-1.5">3. Transaction Verification Polling (`check_status`)</h2>
            <p class="text-xs md:text-sm text-slate-500">
                Allows external script nodes to dynamically audit structural data parameters on demands via native handshake transaction reference channels.
            </p>

            <div class="flex items-center gap-2 bg-white p-3 font-mono text-xs border border-slate-200 overflow-x-auto custom-scrollbar">
                <span class="bg-teal-500/10 text-teal-600 border border-teal-500/20 px-2 py-0.5 font-bold text-[10px]">GET</span>
                <span class="text-slate-600 font-medium"><?php echo $currentGatewayUrl; ?>stk_push?check_status={CHECKOUT_ID}</span>
            </div>

            <div class="space-y-2">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Status Request Execution Syntaxes</h3>
                <div class="border border-slate-200 flex flex-col bg-white">
                    <ul class="flex bg-slate-50 border-b border-slate-200 font-mono text-xs" id="pollTabsMenu">
                        <li class="px-4 py-2.5 cursor-pointer text-teal-600 font-bold border-r border-slate-200 bg-white" id="btn_poll_php" onclick="switchTab('pollTabsMenu', 'poll_php')">PHP</li>
                        <li class="px-4 py-2.5 cursor-pointer text-slate-500 border-r border-slate-200 hover:text-slate-700 hover:bg-white/40" id="btn_poll_node" onclick="switchTab('pollTabsMenu', 'poll_node')">Node.js</li>
                        <li class="px-4 py-2.5 cursor-pointer text-slate-500 border-r border-slate-200 hover:text-slate-700 hover:bg-white/40" id="btn_poll_python" onclick="switchTab('pollTabsMenu', 'poll_python')">Python</li>
                        <li class="px-4 py-2.5 cursor-pointer text-slate-500 hover:text-slate-700 hover:bg-white/40" id="btn_poll_curl" onclick="switchTab('pollTabsMenu', 'poll_curl')">cURL</li>
                    </ul>

                    <div class="p-4 relative">
                        <button onclick="copyCodeBlock(this)" class="absolute top-3 right-3 bg-slate-100/80 border border-slate-300 text-slate-500 hover:text-slate-900 px-2.5 py-1 text-[11px] font-bold transition cursor-pointer"><i class="fa-regular fa-copy"></i> Copy</button>
                        
                        <div id="poll_php" class="tab-content font-mono text-xs md:text-sm text-emerald-600 overflow-x-auto custom-scrollbar whitespace-pre"><pre><code>&lt;?php
$checkoutID = "ws_CO_070720261234567890";
$queryUrl = "<?php echo $currentGatewayUrl; ?>stk_push?check_status=" . $checkoutID;

$response = file_get_contents($queryUrl);
echo $response;
?&gt;</code></pre></div>

                        <div id="poll_node" class="tab-content hidden font-mono text-xs md:text-sm text-teal-700 overflow-x-auto custom-scrollbar whitespace-pre"><pre><code>const axios = require('axios');

const checkoutID = 'ws_CO_070720261234567890';
const queryUrl = `<?php echo $currentGatewayUrl; ?>stk_push?check_status=${checkoutID}`;

axios.get(queryUrl)
.then(response => console.log(response.data))
.catch(error => console.error(error));</code></pre></div>

                        <div id="poll_python" class="tab-content hidden font-mono text-xs md:text-sm text-amber-700 overflow-x-auto custom-scrollbar whitespace-pre"><pre><code>import requests

checkout_id = "ws_CO_070720261234567890"
query_url = f"<?php echo $currentGatewayUrl; ?>stk_push?check_status={checkout_id}"

response = requests.get(query_url)
print(response.json())</code></pre></div>

                        <div id="poll_curl" class="tab-content hidden font-mono text-xs md:text-sm text-slate-600 overflow-x-auto custom-scrollbar whitespace-pre"><pre><code>curl -X GET "<?php echo $currentGatewayUrl; ?>stk_push?check_status=ws_CO_070720261234567890"</code></pre></div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="space-y-2">
                    <h4 class="text-xs font-bold text-emerald-600 uppercase tracking-wider flex items-center gap-1.5"><i class="fa-solid fa-circle-check"></i> Success Completed Payment</h4>
                    <div class="border border-slate-200 bg-white p-4 font-mono text-xs text-slate-600 overflow-x-auto custom-scrollbar">
<pre><code>{
  "id": "241",
  "status": "SUCCESS",
  "phone_number": "254712345678",
  "amount": "10.00",
  "mpesa_receipt_number": "TGR7XYZ890"
}</code></pre>
                    </div>
                </div>

                <div class="space-y-2">
                    <h4 class="text-xs font-bold text-amber-600 uppercase tracking-wider flex items-center gap-1.5"><i class="fa-solid fa-clock"></i> Pending Status Check</h4>
                    <div class="border border-slate-200 bg-white p-4 font-mono text-xs text-slate-600 overflow-x-auto custom-scrollbar">
<pre><code>{
  "id": "241",
  "status": "PENDING",
  "phone_number": "254712345678",
  "amount": "10.00",
  "mpesa_receipt_number": null
}</code></pre>
                    </div>
                </div>
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-bold text-slate-900 tracking-wide border-b border-slate-200 pb-1.5">4. Instant Callback Notifications (`Webhooks`)</h2>
            <p class="text-xs md:text-sm text-slate-500">
                Provide a dynamic URL listener target path within your dashboard configuration pane. The transaction engine will execute an external automated inbound JSON <code>POST</code> payload the moment confirmation values resolve from Safaricom API endpoints.
            </p>
            
            
         <div class="flex items-center gap-2 bg-white p-3 font-mono text-xs border border-slate-200 overflow-x-auto custom-scrollbar">
                <span class="bg-teal-500/10 text-teal-600 border border-teal-500/20 px-2 py-0.5 font-bold text-[10px]">GET</span>
                <span class="text-slate-600 font-medium"><?php echo $currentGatewayUrl; ?>callback</span>
            </div>
            
            <div class="space-y-2">
                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">Inbound Received JSON Post Blueprint</h3>
                <div class="border border-slate-200 bg-white p-4 relative font-mono text-xs md:text-sm text-teal-700 overflow-x-auto custom-scrollbar">
                    <button onclick="copyCodeBlock(this)" class="absolute top-3 right-3 bg-slate-100/80 border border-slate-300 text-slate-500 hover:text-slate-900 px-2.5 py-1 text-[11px] font-bold transition cursor-pointer"><i class="fa-regular fa-copy"></i> Copy Payload</button>
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
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-bold text-slate-900 tracking-wide border-b border-slate-200 pb-1.5">5. Standard Result Code</h2>
            <div class="bg-white/50 border border-slate-200/80 overflow-hidden">
                <table class="w-full border-collapse text-xs md:text-sm text-left">
                    <thead>
                        <tr class="bg-white text-slate-600 border-b border-slate-200 font-semibold">
                            <th class="p-3">Response Result Code</th>
                            <th class="p-3">Status Map Mapping</th>
                            <th class="p-3">Core Operational System Context Reason</th>
                        </tr>
                    </thead>
                    <tbody class="text-slate-500 divide-y divide-slate-200/60">
                        <tr>
                            <td class="p-3 font-mono text-emerald-600 font-bold">0</td>
                            <td class="p-3 font-semibold text-emerald-600">SUCCESS</td>
                            <td class="p-3 text-slate-500">Checkout confirmed, credentials validated, and settlement completed successfully.</td>
                        </tr>
                        <tr>
                            <td class="p-3 font-mono text-rose-600 font-bold">1032</td>
                            <td class="p-3 font-semibold text-rose-600">FAILED</td>
                            <td class="p-3 text-slate-500">The mobile handset interface overlay validation request was explicitly cancelled by the subscriber.</td>
                        </tr>
                        <tr>
                            <td class="p-3 font-mono text-rose-600 font-bold">1037</td>
                            <td class="p-3 font-semibold text-rose-600">FAILED</td>
                            <td class="p-3 text-slate-500">The pipeline timed out due to systemic connectivity degradation on the end-user's SIM stack.</td>
                        </tr>
                        <tr>
                            <td class="p-3 font-mono text-rose-600 font-bold">2001</td>
                            <td class="p-3 font-semibold text-rose-600">FAILED</td>
                            <td class="p-3 text-slate-500">Credential rejection failure. The customer inputted an invalid security access profile PIN code.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

    </main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php';?>
    </footer>
    <script>
        function toggleSidebarLayout(openState) {
            const sidebar = document.getElementById('sidebarMenu');
            const backdrop = document.getElementById('sidebarBackdrop');

            if (!sidebar || !backdrop) {
                console.error("DOM Error: Sidebar elements missing.");
                return;
            }

            if (openState) {
                backdrop.style.display = 'block';
                setTimeout(() => {
                    sidebar.classList.remove('-translate-x-full');
                    backdrop.style.opacity = '1';
                }, 20);
            } else {
                sidebar.classList.add('-translate-x-full');
                backdrop.style.opacity = '0';
                setTimeout(() => {
                    backdrop.style.display = 'none';
                }, 300);
            }
        }

        function switchTab(menuId, contentId) {
            const menu = document.getElementById(menuId);
            const container = menu.parentElement;
            
            // Unset current active attributes
            Array.from(menu.children).forEach(li => {
                li.className = "px-4 py-2.5 cursor-pointer text-slate-500 border-r border-slate-200 hover:text-slate-700 hover:bg-white/40";
            });
            
            // Mask nonactive tab wrapper data parameters
            const codeBlocks = container.querySelectorAll('.tab-content');
            codeBlocks.forEach(block => block.classList.add('hidden'));
            
            // Assign active classes
            document.getElementById(contentId).classList.remove('hidden');
            const clickedTab = event.currentTarget;
            clickedTab.className = "px-4 py-2.5 cursor-pointer text-teal-600 font-bold border-r border-slate-200 bg-white";
        }

        function copyCodeBlock(btnElement) {
            const container = btnElement.parentElement;
            const codeEl = container.querySelector('pre code') || container.querySelector('pre');
            navigator.clipboard.writeText(codeEl.innerText).then(() => {
                showSuccessNotification(btnElement);
            });
        }

        function copyTextById(elementId, btnElement) {
            const textContent = document.getElementById(elementId).innerText;
            navigator.clipboard.writeText(textContent).then(() => {
                showSuccessNotification(btnElement);
            });
        }

        function showSuccessNotification(btn) {
            const initialHtml = btn.innerHTML;
            btn.innerHTML = "<i class='fa-solid fa-check text-emerald-400'></i> Copied!";
            setTimeout(() => {
                btn.innerHTML = initialHtml;
            }, 2000);
        }
    </script>
</body>
</html>
