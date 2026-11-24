<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta name="theme-color" content="#ffffff">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Embed the Checkout Widget - AvenPay</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen p-6 md:p-12 font-sans">

<div class="max-w-4xl mx-auto space-y-12">
    
    <!-- Header -->
    <div class="border-b border-slate-200 pb-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h1 class="text-3xl font-extrabold tracking-tight text-teal-600">M-Pesa Checkout API & Docs</h1>
            <p class="text-xs text-slate-500 mt-1">Cross-Domain Payment Engine Integration</p>
        </div>
        <div class="bg-white border border-slate-200 px-3 py-1.5 rounded-full text-[11px] font-mono text-emerald-600 flex items-center space-x-2">
            <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>Engine: Live Mode Active</span>
        </div>
    </div>

    <!-- Section 1: Attractive Embedded Checkout Widget Code -->
    <section class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 shadow-xl space-y-6">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 bg-teal-500/10 text-teal-600 rounded-xl flex items-center justify-center border border-teal-500/20">
                <i class="fa-solid fa-credit-card"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-700">1. Attractive Glassmorphic Checkout Widget</h2>
                <p class="text-xs text-slate-500">Copy the code below to render the modern, high-conversion payment card on any webpage.</p>
            </div>
        </div>

        <div class="relative">
            <div class="flex justify-between items-center bg-slate-50 px-4 py-2 border border-slate-200 rounded-t-xl text-xs text-slate-500">
                <span class="font-mono"><i class="fa-solid fa-file-code mr-1"></i> Widget HTML Code</span>
                <button onclick="copyWidgetCode()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold px-3 py-1.5 rounded-lg text-[11px] transition flex items-center space-x-1.5 shadow">
                    <i class="fa-solid fa-copy"></i>
                    <span id="copyWidgetBtnText">Copy Widget HTML</span>
                </button>
            </div>
            <pre class="bg-slate-50/90 p-4 rounded-b-xl border-x border-b border-slate-200 font-mono text-xs text-teal-700 overflow-x-auto max-h-80"><code id="widgetHtmlCode">&lt;div class="max-w-md w-full mx-auto bg-gradient-to-b from-white via-white/95 to-slate-50 border border-slate-200/80 rounded-3xl p-6 md:p-8 shadow-[0_20px_50px_rgba(0,0,0,0.7)] relative overflow-hidden font-sans text-slate-100"&gt;
    &lt;div class="absolute -top-24 -right-24 w-48 h-48 bg-teal-500/15 rounded-full blur-3xl pointer-events-none"&gt;&lt;/div&gt;
    &lt;div class="absolute -bottom-24 -left-24 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"&gt;&lt;/div&gt;

    &lt;div class="text-center mb-6 relative z-10"&gt;
        &lt;div class="inline-flex items-center justify-center h-14 w-14 rounded-2xl bg-gradient-to-tr from-teal-500/20 to-emerald-500/10 text-teal-400 mb-3 text-xl shadow-inner border border-teal-500/30"&gt;
            &lt;i class="fa-solid fa-shield-halved"&gt;&lt;/i&gt;
        &lt;/div&gt;
        &lt;h2 class="text-xl font-extrabold tracking-tight text-slate-900"&gt;Secure M-Pesa Checkout&lt;/h2&gt;
        &lt;p class="text-xs text-slate-500 mt-1"&gt;Instant, automated STK Push payment&lt;/p&gt;
    &lt;/div&gt;

    &lt;div id="embedErrorBanner" class="hidden mb-5 p-3.5 bg-rose-500/10 border border-rose-500/30 rounded-2xl text-xs text-rose-600 flex items-start space-x-2.5 relative z-10"&gt;
        &lt;i class="fa-solid fa-circle-exclamation mt-0.5 shrink-0 text-sm"&gt;&lt;/i&gt;
        &lt;span id="embedErrorText" class="font-medium leading-relaxed"&gt;An error occurred.&lt;/span&gt;
    &lt;/div&gt;

    &lt;form id="checkoutForm" class="space-y-4 relative z-10"&gt;
        &lt;div&gt;
            &lt;label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5"&gt;X-API-KEY (Merchant Key)&lt;/label&gt;
            &lt;div class="relative flex items-center group"&gt;
                &lt;span class="absolute left-3.5 text-slate-500 group-focus-within:text-teal-600 transition text-xs"&gt;&lt;i class="fa-solid fa-key"&gt;&lt;/i&gt;&lt;/span&gt;
                &lt;input type="text" id="apiKey" placeholder="avp_live_..." required
                    class="w-full pl-10 pr-4 py-3 text-xs border border-slate-300/80 rounded-xl bg-slate-50/80 text-slate-800 placeholder-slate-600 focus:outline-none focus:border-teal-400 focus:ring-2 focus:ring-teal-400/20 font-mono transition shadow-inner"&gt;
            &lt;/div&gt;
        &lt;/div&gt;

        &lt;div&gt;
            &lt;label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5"&gt;M-Pesa Phone Number&lt;/label&gt;
            &lt;div class="relative flex items-center group"&gt;
                &lt;span class="absolute left-3.5 text-slate-500 group-focus-within:text-teal-600 transition text-xs"&gt;&lt;i class="fa-solid fa-phone"&gt;&lt;/i&gt;&lt;/span&gt;
                &lt;input type="text" id="phoneNumber" placeholder="0712345678" required
                    class="w-full pl-10 pr-4 py-3 text-xs border border-slate-300/80 rounded-xl bg-slate-50/80 text-slate-800 placeholder-slate-600 focus:outline-none focus:border-teal-400 focus:ring-2 focus:ring-teal-400/20 transition shadow-inner"&gt;
            &lt;/div&gt;
        &lt;/div&gt;

        &lt;div&gt;
            &lt;label class="block text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5"&gt;Amount (KES)&lt;/label&gt;
            &lt;div class="relative flex items-center group"&gt;
                &lt;span class="absolute left-3.5 text-slate-500 group-focus-within:text-teal-600 transition text-xs"&gt;&lt;i class="fa-solid fa-money-bill-wave"&gt;&lt;/i&gt;&lt;/span&gt;
                &lt;input type="number" id="amount" value="10" min="1" required
                    class="w-full pl-10 pr-4 py-3 text-xs border border-slate-300/80 rounded-xl bg-slate-50/80 text-slate-800 focus:outline-none focus:border-teal-400 focus:ring-2 focus:ring-teal-400/20 font-semibold transition shadow-inner"&gt;
            &lt;/div&gt;
        &lt;/div&gt;

        &lt;button type="submit" id="payBtn"
            class="w-full bg-gradient-to-r from-teal-400 to-emerald-400 hover:from-teal-300 hover:to-emerald-300 text-slate-950 font-extrabold py-3.5 px-4 rounded-xl text-xs transition-all duration-200 flex items-center justify-center space-x-2 shadow-lg shadow-teal-500/25 active:scale-[0.98] mt-2"&gt;
            &lt;i class="fa-solid fa-lock text-[10px]"&gt;&lt;/i&gt;
            &lt;span class="tracking-wide uppercase"&gt;Pay Now&lt;/span&gt;
        &lt;/button&gt;
    &lt;/form&gt;

    &lt;div class="text-center mt-6 relative z-10"&gt;
        &lt;span class="text-[10px] text-slate-500 flex items-center justify-center space-x-1.5"&gt;
            &lt;i class="fa-solid fa-bolt text-teal-600"&gt;&lt;/i&gt;
            &lt;span&gt;Secured & Powered by AvenPay Engine&lt;/span&gt;
        &lt;/span&gt;
    &lt;/div&gt;
&lt;/div&gt;</code></pre>
        </div>
    </section>

    <!-- Section 2: Full embed.js Source Code -->
    <section class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 shadow-xl space-y-6">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 bg-teal-500/10 text-teal-600 rounded-xl flex items-center justify-center border border-teal-500/20">
                <i class="fa-solid fa-file-js"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-700">2. Full embed.js Source Code</h2>
                <p class="text-xs text-slate-500">Save this script as <code class="text-teal-700 font-mono">embed.js</code> in your server root directory. It handles STK triggers, displays validation/backend errors, and handles real-time polling.</p>
            </div>
        </div>

        <div class="relative">
            <div class="flex justify-between items-center bg-slate-50 px-4 py-2 border border-slate-200 rounded-t-xl text-xs text-slate-500">
                <span class="font-mono"><i class="fa-solid fa-file-code mr-1"></i> embed.js Source</span>
                <button onclick="copyEmbedJsCode()" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-bold px-3 py-1.5 rounded-lg text-[11px] transition flex items-center space-x-1.5 shadow">
                    <i class="fa-solid fa-copy"></i>
                    <span id="copyJsBtnText">Copy Full JS</span>
                </button>
            </div>
            <pre class="bg-slate-50/90 p-4 rounded-b-xl border-x border-b border-slate-200 font-mono text-[11px] text-slate-600 overflow-x-auto max-h-96"><code id="fullEmbedJsCode">(function() {
    'use strict';
    const backendApiBase = 'https://avenpay.co.ke';

    if (!document.querySelector('script[src*="tailwindcss"]')) {
        const tw = document.createElement('script');
        tw.src = 'https://cdn.tailwindcss.com';
        document.head.appendChild(tw);
    }
    if (!document.querySelector('link[href*="font-awesome"]')) {
        const fa = document.createElement('link');
        fa.rel = 'stylesheet';
        fa.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
        document.head.appendChild(fa);
    }

    let activeEventSource = null;
    let activePollInterval = null;
    let paymentTimeout = null;

    document.addEventListener('submit', async function(e) {
        if (e.target && e.target.id === 'checkoutForm') {
            e.preventDefault();

            const apiKey = document.getElementById('apiKey').value.trim();
            const phone = document.getElementById('phoneNumber').value.trim();
            const amount = document.getElementById('amount').value.trim();
            const payBtn = document.getElementById('payBtn');
            const errorBanner = document.getElementById('embedErrorBanner');
            const errorText = document.getElementById('embedErrorText');

            if (errorBanner) errorBanner.classList.add('hidden');

            payBtn.disabled = true;
            payBtn.innerHTML = '&lt;i class="fa-solid fa-spinner fa-spin text-[10px]"&gt;&lt;/i&gt;&lt;span&gt;Processing...&lt;/span&gt;';

            const formData = new URLSearchParams();
            formData.append('phone_number', phone);
            formData.append('amount', amount);
            formData.append('x_api_key', apiKey);
            formData.append('mode', 'live');

            try {
                const response = await fetch(`${backendApiBase}/api/stk_push`, {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (response.ok && data.status === 'success') {
                    const checkoutId = data.checkout_id;
                    await fetch('/api/save_pending', {
                        method: 'POST',
                        body: new URLSearchParams({ checkout_id: checkoutId, phone, amount })
                    }).catch(() => {});

                    showModal(checkoutId);
                    monitorTransaction(checkoutId);
                } else {
                    throw new Error(data.message || 'Payment initialization failed.');
                }
            } catch (err) {
                if (errorBanner && errorText) {
                    errorText.innerText = err.message || 'Network connection error.';
                    errorBanner.classList.remove('hidden');
                } else {
                    alert(err.message || 'Network connection error.');
                }
                resetButton();
            }
        }
    });

    function showModal(checkoutId) {
        let modal = document.getElementById('paymentModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'paymentModal';
            modal.className = 'fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4 font-sans';
            modal.innerHTML = `
                &lt;div class="bg-slate-900 border border-slate-200 rounded-3xl shadow-2xl max-w-sm w-full p-6 text-center text-slate-100"&gt;
                    &lt;div id="modalLoading" class="py-4"&gt;
                        &lt;div class="inline-block animate-spin rounded-full h-10 w-10 border-3 border-teal-500 border-t-transparent mb-3"&gt;&lt;/div&gt;
                        &lt;h4 class="text-base font-bold text-white"&gt;Check Your Phone&lt;/h4&gt;
                        &lt;p class="text-xs text-slate-500 mt-1"&gt;M-Pesa prompt sent. Enter your PIN to complete.&lt;/p&gt;
                        &lt;div class="mt-3 text-[11px] text-slate-500 font-mono bg-slate-50 py-1.5 px-3 rounded-xl border border-slate-200 inline-block" id="checkoutIdDisplay"&gt;ID: ...&lt;/div&gt;
                    &lt;/div&gt;
                    &lt;div id="modalSuccess" class="py-4 hidden"&gt;
                        &lt;div class="h-12 w-12 bg-emerald-500/20 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-3 text-lg"&gt;
                            &lt;i class="fa-solid fa-check"&gt;&lt;/i&gt;
                        &lt;/div&gt;
                        &lt;h4 class="text-base font-bold text-white"&gt;Payment Successful!&lt;/h4&gt;
                        &lt;p class="text-xs text-slate-500 mt-1 mb-4 font-mono" id="successReceiptText"&gt;Receipt: N/A&lt;/p&gt;
                        &lt;button onclick="window.closePaymentModal()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-800 py-2.5 rounded-xl text-xs font-semibold transition"&gt;Done&lt;/button&gt;
                    &lt;/div&gt;
                    &lt;div id="modalFailed" class="py-4 hidden"&gt;
                        &lt;div class="h-12 w-12 bg-rose-500/20 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-3 text-lg"&gt;
                            &lt;i class="fa-solid fa-xmark"&gt;&lt;/i&gt;
                        &lt;/div&gt;
                        &lt;h4 class="text-base font-bold text-white"&gt;Payment Failed&lt;/h4&gt;
                        &lt;p class="text-xs text-slate-500 mt-1 mb-4" id="failureReasonText"&gt;Transaction failed or cancelled.&lt;/p&gt;
                        &lt;button onclick="window.closePaymentModal()" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-800 py-2.5 rounded-xl text-xs font-semibold transition"&gt;Try Again&lt;/button&gt;
                    &lt;/div&gt;
                &lt;/div&gt;`;
            document.body.appendChild(modal);
        }
        modal.classList.remove('hidden');
        document.getElementById('modalLoading').classList.remove('hidden');
        document.getElementById('modalSuccess').classList.add('hidden');
        document.getElementById('modalFailed').classList.add('hidden');
        document.getElementById('checkoutIdDisplay').innerText = 'ID: ' + checkoutId;

        if (paymentTimeout) clearTimeout(paymentTimeout);
        paymentTimeout = setTimeout(() => {
            if (!document.getElementById('paymentModal').classList.contains('hidden')) {
                window.closePaymentModal();
                alert('Request timed out.');
            }
        }, 90000);
    }

    window.closePaymentModal = function() {
        const modal = document.getElementById('paymentModal');
        if (modal) modal.classList.add('hidden');
        cleanupWorkers();
        resetButton();
    };

    function resetButton() {
        const payBtn = document.getElementById('payBtn');
        if (payBtn) {
            payBtn.disabled = false;
            payBtn.innerHTML = '&lt;i class="fa-solid fa-lock text-[10px]"&gt;&lt;/i&gt;&lt;span class="tracking-wide uppercase"&gt;Pay Now&lt;/span&gt;';
        }
    }

    function cleanupWorkers() {
        if (activeEventSource) { activeEventSource.close(); activeEventSource = null; }
        if (activePollInterval) { clearInterval(activePollInterval); activePollInterval = null; }
        if (paymentTimeout) { clearTimeout(paymentTimeout); paymentTimeout = null; }
    }

    function monitorTransaction(checkoutId) {
        setTimeout(() => {
            activeEventSource = new EventSource(`${backendApiBase}/api/check_status?mode=live`);
            activeEventSource.onmessage = async function(e) {
                try {
                    const data = JSON.parse(e.data);
                    if (data.checkout_id === checkoutId && data.status === 'SUCCESS') {
                        cleanupWorkers();
                        const receipt = data.mpesa_receipt || data.mpesa_code || 'SUCCESS';
                        await syncSuccess(checkoutId, receipt);
                        showSuccessState(receipt);
                    }
                } catch (err) {}
            };
        }, 2000);

        activePollInterval = setInterval(async () => {
            try {
                const res = await fetch(`${backendApiBase}/api/check_status?check_status=${encodeURIComponent(checkoutId)}`);
                const data = await res.json();
                if (data.status === 'SUCCESS') {
                    clearInterval(activePollInterval);
                    cleanupWorkers();
                    const receipt = data.mpesa_receipt || data.mpesa_code || 'SUCCESS';
                    await syncSuccess(checkoutId, receipt);
                    showSuccessState(receipt);
                } else if (data.status === 'FAILED') {
                    clearInterval(activePollInterval);
                    cleanupWorkers();
                    showFailureState(data.mpesa_code || 'Transaction failed.');
                }
            } catch (err) {}
        }, 3000);
    }

    async function syncSuccess(checkoutId, receipt) {
        await fetch('/api/update_success', {
            method: 'POST',
            body: new URLSearchParams({ checkout_id: checkoutId, mpesa_code: receipt })
        }).catch(() => {});
    }

    function showSuccessState(receipt) {
        document.getElementById('modalLoading').classList.add('hidden');
        document.getElementById('modalSuccess').classList.remove('hidden');
        document.getElementById('successReceiptText').innerText = 'M-Pesa Receipt: ' + receipt;
    }

    function showFailureState(reason) {
        document.getElementById('modalLoading').classList.add('hidden');
        document.getElementById('modalFailed').classList.remove('hidden');
        document.getElementById('failureReasonText').innerText = reason;
    }
})();</code></pre>
        </div>
    </section>

    <!-- Section 3: cURL API Documentation -->
    <section class="bg-white border border-slate-200 rounded-3xl p-6 md:p-8 shadow-xl space-y-6">
        <div class="flex items-center space-x-3">
            <div class="h-10 w-10 bg-teal-500/10 text-teal-600 rounded-xl flex items-center justify-center border border-teal-500/20">
                <i class="fa-solid fa-terminal"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-slate-700">3. cURL API Integration</h2>
                <p class="text-xs text-slate-500">Trigger STK pushes and check status directly via command line or backend HTTP requests.</p>
            </div>
        </div>

        <div class="space-y-3">
            <h3 class="text-sm font-semibold text-teal-700 flex items-center space-x-2">
                <span class="h-5 w-5 rounded-full bg-teal-500/20 text-teal-600 flex items-center justify-center text-[10px]">1</span>
                <span>Initiate Live STK Push</span>
            </h3>
            <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 font-mono text-xs text-teal-700 overflow-x-auto">
<span class="text-slate-500">curl -X POST "https://avenpay.co.ke/api/stk_push" \</span><br>
<span class="text-slate-600">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-d "phone_number=0712345678" \</span><br>
<span class="text-slate-600">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-d "amount=10" \</span><br>
<span class="text-slate-600">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-d "x_api_key=YOUR_API_KEY_HERE" \</span><br>
<span class="text-slate-600">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;-d "mode=live"</span>
            </div>
        </div>

        <div class="space-y-3 pt-4 border-t border-slate-200">
            <h3 class="text-sm font-semibold text-teal-700 flex items-center space-x-2">
                <span class="h-5 w-5 rounded-full bg-teal-500/20 text-teal-600 flex items-center justify-center text-[10px]">2</span>
                <span>Check Transaction Status</span>
            </h3>
            <div class="bg-slate-50 rounded-xl p-4 border border-slate-200 font-mono text-xs text-teal-700 overflow-x-auto">
<span class="text-slate-500">curl -X GET "https://avenpay.co.ke/api/check_status?check_status=ws_CO_29102026_123456789"</span>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <div class="text-center text-xs text-slate-500 pb-6">
        Powered by AvenPay Engine | Live Mode Operational
    </div>
</div>

<script>
    function copyWidgetCode() {
        const codeText = document.getElementById('widgetHtmlCode').innerText;
        navigator.clipboard.writeText(codeText).then(() => {
            const btnText = document.getElementById('copyWidgetBtnText');
            btnText.innerText = 'Copied Widget!';
            setTimeout(() => {
                btnText.innerText = 'Copy Widget HTML';
            }, 2000);
        });
    }

    function copyEmbedJsCode() {
        const jsText = document.getElementById('fullEmbedJsCode').innerText;
        navigator.clipboard.writeText(jsText).then(() => {
            const btnText = document.getElementById('copyJsBtnText');
            btnText.innerText = 'Copied JS!';
            setTimeout(() => {
                btnText.innerText = 'Copy Full JS';
            }, 2000);
        });
    }
</script>

</body>
</html>