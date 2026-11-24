(function() {
    const scriptTag = document.currentScript;
    const apiKey = scriptTag ? scriptTag.getAttribute('data-key') : '';
    const defaultAmount = scriptTag ? scriptTag.getAttribute('data-amount') : '10';

    const backendApiBase = 'https://avenpay.co.ke';

    // Inject Tailwind CSS if not present
    if (!document.querySelector('script[src*="tailwindcss"]')) {
        const tw = document.createElement('script');
        tw.src = 'https://cdn.tailwindcss.com';
        document.head.appendChild(tw);
    }

    // Inject FontAwesome if not present
    if (!document.querySelector('link[href*="font-awesome"]')) {
        const fa = document.createElement('link');
        fa.rel = 'stylesheet';
        fa.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css';
        document.head.appendChild(fa);
    }

    // Create Widget Markup
    const container = document.getElementById('avenpay-checkout-container') || document.body;
    
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `
        <div class="max-w-xs w-full bg-slate-900 border border-slate-800 rounded-xl p-4 shadow-xl text-slate-100 font-sans">
            <div class="text-center mb-4">
                <h3 class="text-sm font-bold text-teal-400">Secure M-Pesa Checkout</h3>
            </div>
            <form id="embeddedPaymentForm" class="space-y-3">
                <div>
                    <label class="block text-[10px] font-semibold text-slate-300 mb-1">M-Pesa Phone</label>
                    <input type="text" id="embed_phone" placeholder="0712345678" required
                        class="w-full px-3 py-1.5 text-xs border border-slate-700 rounded bg-slate-800 text-slate-100 focus:outline-none focus:border-teal-400">
                </div>
                <div>
                    <label class="block text-[10px] font-semibold text-slate-300 mb-1">Amount (KES)</label>
                    <input type="number" id="embed_amount" value="${defaultAmount}" min="1" required
                        class="w-full px-3 py-1.5 text-xs border border-slate-700 rounded bg-slate-800 text-slate-100 focus:outline-none focus:border-teal-400">
                </div>
                <button type="submit" id="embedPayBtn"
                    class="w-full bg-teal-500 hover:bg-teal-600 text-slate-950 font-bold py-2 px-3 rounded text-xs transition flex items-center justify-center space-x-2 shadow">
                    <i class="fa-solid fa-lock text-[9px]"></i>
                    <span>Pay Now</span>
                </button>
            </form>
        </div>

        <!-- Modal -->
        <div id="embedPaymentModal" class="fixed inset-0 bg-black/70 backdrop-blur-sm flex items-center justify-center hidden z-50 p-4">
            <div class="bg-slate-900 border border-slate-700 rounded-2xl shadow-2xl max-w-sm w-full p-6 text-center text-slate-100">
                <div id="embedModalLoading" class="py-4">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-3 border-teal-500 border-t-transparent mb-3"></div>
                    <h4 class="text-sm font-bold">Check Your Phone</h4>
                    <p class="text-xs text-slate-400 mt-1">Enter your M-Pesa PIN to complete payment.</p>
                    <div class="mt-2 text-[10px] text-slate-500 font-mono" id="embedCheckoutIdDisplay">ID: ...</div>
                </div>
                <div id="embedModalSuccess" class="py-4 hidden">
                    <div class="h-10 w-10 bg-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center mx-auto mb-2 text-base">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <h4 class="text-sm font-bold">Payment Successful!</h4>
                    <p class="text-xs text-slate-400 mt-1 mb-3" id="embedSuccessReceipt">Receipt: N/A</p>
                    <button onclick="closeEmbedModal()" class="w-full bg-slate-800 hover:bg-slate-700 text-slate-100 py-1.5 rounded text-xs font-semibold">Done</button>
                </div>
                <div id="embedModalFailed" class="py-4 hidden">
                    <div class="h-10 w-10 bg-rose-500/20 text-rose-400 rounded-full flex items-center justify-center mx-auto mb-2 text-base">
                        <i class="fa-solid fa-xmark"></i>
                    </div>
                    <h4 class="text-sm font-bold">Payment Failed</h4>
                    <p class="text-xs text-slate-400 mt-1 mb-3" id="embedFailureReason">Transaction failed.</p>
                    <button onclick="closeEmbedModal()" class="w-full bg-slate-800 hover:bg-slate-700 text-slate-100 py-1.5 rounded text-xs font-semibold">Try Again</button>
                </div>
            </div>
        </div>
    `;
    container.appendChild(wrapper);

    let eventSource = null;
    let pollInterval = null;

    document.getElementById('embeddedPaymentForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const phone = document.getElementById('embed_phone').value;
        const amount = document.getElementById('embed_amount').value;
        const payBtn = document.getElementById('embedPayBtn');

        payBtn.disabled = true;
        payBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';

        const formData = new URLSearchParams();
        formData.append('phone_number', phone);
        formData.append('amount', amount);
        formData.append('x_api_key', apiKey);
        formData.append('mode', 'live');

        try {
            const response = await fetch(backendApiBase + '/api/stk_push.php', { method: 'POST', body: formData });
            const data = await response.json();

            if (response.ok && data.status === 'success') {
                const checkoutId = data.checkout_id;

                // Save pending locally
                const localSave = new URLSearchParams();
                localSave.append('checkout_id', checkoutId);
                localSave.append('phone', phone);
                localSave.append('amount', amount);
                await fetch('/api/save_pending.php', { method: 'POST', body: localSave }).catch(() => {});

                showEmbedModal(checkoutId);
                listenEmbedStream(checkoutId);
            } else {
                alert(data.message || 'Initialization failed.');
                resetEmbedBtn();
            }
        } catch (err) {
            alert('Network error occurred.');
            resetEmbedBtn();
        }
    });

    function showEmbedModal(checkoutId) {
        document.getElementById('embedPaymentModal').classList.remove('hidden');
        document.getElementById('embedModalLoading').classList.remove('hidden');
        document.getElementById('embedModalSuccess').classList.add('hidden');
        document.getElementById('embedModalFailed').classList.add('hidden');
        document.getElementById('embedCheckoutIdDisplay').innerText = 'ID: ' + checkoutId;
    }

    window.closeEmbedModal = function() {
        document.getElementById('embedPaymentModal').classList.add('hidden');
        if (eventSource) eventSource.close();
        if (pollInterval) clearInterval(pollInterval);
        resetEmbedBtn();
    }

    function resetEmbedBtn() {
        const payBtn = document.getElementById('embedPayBtn');
        payBtn.disabled = false;
        payBtn.innerHTML = '<i class="fa-solid fa-lock text-[9px]"></i><span>Pay Now</span>';
    }

    function listenEmbedStream(checkoutId) {
        setTimeout(() => {
            if (document.getElementById('embedPaymentModal').classList.contains('hidden')) return;
            eventSource = new EventSource(backendApiBase + '/api/check_status.php?mode=live');
            eventSource.onmessage = async function(e) {
                try {
                    const data = JSON.parse(e.data);
                    if (data.checkout_id === checkoutId) {
                        if (data.status === 'SUCCESS') {
                            cleanupEmbed();
                            const receipt = data.mpesa_receipt || data.mpesa_code || 'SUCCESS';
                            await syncSuccess(checkoutId, receipt);
                            showSuccess(receipt);
                        } else if (data.status === 'FAILED') {
                            cleanupEmbed();
                            showFailure(data.mpesa_code || 'Transaction failed.');
                        }
                    }
                } catch (err) {}
            };
        }, 3000);

        if (pollInterval) clearInterval(pollInterval);
        pollInterval = setInterval(async () => {
            if (document.getElementById('embedPaymentModal').classList.contains('hidden')) {
                clearInterval(pollInterval);
                return;
            }
            try {
                const res = await fetch(backendApiBase + '/api/check_status.php?check_status=' + encodeURIComponent(checkoutId));
                const data = await res.json();
                if (data.status === 'SUCCESS') {
                    clearInterval(pollInterval);
                    cleanupEmbed();
                    const receipt = data.mpesa_receipt || data.mpesa_code || 'SUCCESS';
                    await syncSuccess(checkoutId, receipt);
                    showSuccess(receipt);
                } else if (data.status === 'FAILED') {
                    clearInterval(pollInterval);
                    cleanupEmbed();
                    showFailure(data.mpesa_code || 'Transaction failed.');
                }
            } catch (err) {}
        }, 3000);
    }

    async function syncSuccess(checkoutId, receipt) {
        const updateData = new URLSearchParams();
        updateData.append('checkout_id', checkoutId);
        updateData.append('mpesa_code', receipt);
        await fetch('/api/update_success.php', { method: 'POST', body: updateData }).catch(() => {});
    }

    function cleanupEmbed() {
        if (eventSource) eventSource.close();
        if (pollInterval) clearInterval(pollInterval);
    }

    function showSuccess(receipt) {
        document.getElementById('embedModalLoading').classList.add('hidden');
        document.getElementById('embedModalSuccess').classList.remove('hidden');
        document.getElementById('embedSuccessReceipt').innerText = 'Receipt: ' + receipt;
    }

    function showFailure(reason) {
        document.getElementById('embedModalLoading').classList.add('hidden');
        document.getElementById('embedModalFailed').classList.remove('hidden');
        document.getElementById('embedFailureReason').innerText = reason;
    }
})();