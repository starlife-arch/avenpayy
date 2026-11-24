<!-- Tailwind CSS & Font Awesome Icons -->
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<!-- Payment Form Container -->
<div class="max-w-md mx-auto bg-white rounded-2xl shadow-xl overflow-hidden border border-gray-100 my-8 font-sans">
    
    <!-- Gateway Header -->
    <div class="bg-gradient-to-r from-emerald-600 to-teal-700 p-6 text-white text-center relative">
        <div class="absolute top-4 right-4 bg-white/20 backdrop-blur-md px-2.5 py-1 rounded-full text-[10px] font-semibold uppercase tracking-wider flex items-center space-x-1">
            <i class="fa-solid fa-shield-halved text-xs"></i>
            <span>Live M-Pesa</span>
        </div>
        <div class="w-12 h-12 bg-slate-900/10 backdrop-blur-md rounded-full flex items-center justify-center mx-auto mb-3 text-xl shadow-inner">
            <i class="fa-solid fa-bolt text-emerald-800"></i>
        </div>
        <h3 class="text-xl font-bold tracking-tight">Express Checkout</h3>
        <p class="text-xs text-emerald-100 mt-1">Instant M-Pesa STK Push Payment</p>
    </div>

    <!-- Form Body -->
    <form id="paymentForm" class="p-6 space-y-5">
        <!-- Replace with your generated Settlement Account X-API-Key -->
        <input type="hidden" id="x_api_key" value="YOUR_SETTLEMENT_API_KEY_HERE">
        <input type="hidden" id="mode" value="live">

        <div>
            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">M-Pesa Phone Number</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-500">
                    <iclass class="fa-solid fa-phone text-sm"></iclass>
                </span>
                <input type="text" id="phone_number" placeholder="0712345678 or 2547..." required
                    class="w-full pl-11 pr-4 py-3 text-sm bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 outline-none transition font-medium text-gray-800">
            </div>
            <p class="text-[11px] text-gray-500 mt-1">Format: 07XXXXXXXX or 2547XXXXXXXX</p>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">Amount (KES)</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-500">
                    <iclass class="fa-solid fa-money-bill-wave text-sm"></iclass>
                </span>
                <input type="number" id="amount" placeholder="100" min="1" required
                    class="w-full pl-11 pr-4 py-3 text-sm bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-600 outline-none transition font-medium text-gray-800">
            </div>
        </div>

        <button type="submit" id="payBtn"
            class="w-full bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] text-white font-semibold py-3.5 px-4 rounded-xl transition duration-200 flex items-center justify-center space-x-2 shadow-lg shadow-emerald-600/20 cursor-pointer">
            <iclass class="fa-solid fa-lock text-xs text-emerald-800"></iclass>
            <span>Pay with M-Pesa</span>
        </button>
    </form>

    <!-- Trust Footer -->
    <div class="bg-gray-50 px-6 py-3.5 border-t border-gray-100 flex items-center justify-center space-x-2 text-gray-500 text-xs">
        <i class="fa-solid fa-shield-check text-emerald-600 text-sm"></i>
        <span class="font-medium text-gray-600">Secured by <span class="text-emerald-700 font-bold tracking-tight">AvenPay Secure Checkout</span></span>
    </div>
</div>

<!-- Real-Time Status Modal -->
<div id="paymentModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center hidden z-50 p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center transform transition-all">
        
        <!-- Loading State -->
        <div id="modalLoading" class="py-4">
            <div class="relative w-16 h-16 mx-auto mb-5">
                <div class="absolute inset-0 rounded-full border-4 border-emerald-100"></div>
                <div class="absolute inset-0 rounded-full border-4 border-emerald-600 border-t-transparent animate-spin"></div>
                <div class="absolute inset-0 flex items-center justify-center text-emerald-600">
                    <i class="fa-solid fa-mobile-screen-button text-lg"></i>
                </div>
            </div>
            <h4 class="text-xl font-bold text-gray-900">Check Your Phone</h4>
            <p class="text-sm text-gray-500 mt-2 leading-relaxed">An M-Pesa authorization prompt has been sent to your phone. Enter your PIN to complete the payment.</p>
            <div class="mt-5 inline-block bg-gray-50 border border-gray-100 px-3 py-1.5 rounded-lg text-xs text-gray-500 font-mono" id="checkoutIdDisplay">Checkout ID: ...</div>
        </div>

        <!-- Success State -->
        <div id="modalSuccess" class="py-4 hidden">
            <div class="h-16 w-16 bg-emerald-100 text-emerald-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl shadow-inner">
                <iclass class="fa-solid fa-check"></iclass>
            </div>
            <h4 class="text-xl font-bold text-gray-900">Payment Successful!</h4>
            <p class="text-sm text-gray-500 mt-1 mb-6" id="successReceiptText">Receipt: N/A</p>
            <button onclick="closeModal()" class="w-full bg-gray-900 hover:bg-gray-800 text-slate-900 py-3 rounded-xl text-sm font-semibold transition shadow-md">Done</button>
        </div>

        <!-- Failed State -->
        <div id="modalFailed" class="py-4 hidden">
            <div class="h-16 w-16 bg-rose-100 text-rose-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl shadow-inner">
                <iclass class="fa-solid fa-xmark"></iclass>
            </div>
            <h4 class="text-xl font-bold text-gray-900">Payment Failed</h4>
            <p class="text-sm text-gray-500 mt-1 mb-6" id="failureReasonText">Transaction cancelled or failed.</p>
            <button onclick="closeModal()" class="w-full bg-gray-900 hover:bg-gray-800 text-slate-900 py-3 rounded-xl text-sm font-semibold transition shadow-md">Try Again</button>
        </div>
    </div>
</div>

<script>
let eventSource = null;

document.getElementById('paymentForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const phone = document.getElementById('phone_number').value;
    const amount = document.getElementById('amount').value;
    const apiKey = document.getElementById('x_api_key').value;
    const mode = document.getElementById('mode').value;

    const payBtn = document.getElementById('payBtn');
    payBtn.disabled = true;
    payBtn.innerHTML = '<iclass="fa-solid fa-spinner fa-spin"></i> Processing Request...';

    const formData = new URLSearchParams();
    formData.append('phone_number', phone);
    formData.append('amount', amount);
    formData.append('x_api_key', apiKey);
    formData.append('mode', mode);

    try {
        const response = await fetch('https://avenpay.co.ke/api/stk_push.php', { method: 'POST', body: formData });
        const data = await response.json();

        if (data.status === 'success') {
            showModal(data.checkout_id);
            listenToTransactionStream(data.checkout_id);
        } else {
            alert(data.message || 'Initialization failed.');
            resetButton();
        }
    } catch (err) {
        alert('Network error occurred.');
        resetButton();
    }
});

function showModal(checkoutId) {
    document.getElementById('paymentModal').classList.remove('hidden');
    document.getElementById('modalLoading').classList.remove('hidden');
    document.getElementById('modalSuccess').classList.add('hidden');
    document.getElementById('modalFailed').classList.add('hidden');
    document.getElementById('checkoutIdDisplay').innerText = 'ID: ' + checkoutId;
}

function closeModal() {
    document.getElementById('paymentModal').classList.add('hidden');
    if (eventSource) eventSource.close();
    resetButton();
}

function resetButton() {
    const payBtn = document.getElementById('payBtn');
    payBtn.disabled = false;
    payBtn.innerHTML = '<iclass="fa-solid fa-lock text-xs text-emerald-200"></i><span>Pay with M-Pesa</span>';
}

function listenToTransactionStream(checkoutId) {
    eventSource = new EventSource('https://avenpay.co.ke/api/check_status.php?mode=live');

    eventSource.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            if (data.checkout_id === checkoutId || data.status) {
                if (data.status === 'SUCCESS') {
                    eventSource.close();
                    showSuccessState(data.mpesa_code);
                } else if (data.status === 'FAILED') {
                    eventSource.close();
                    showFailureState(data.mpesa_code || 'Transaction failed.');
                }
            }
        } catch (e) {
            console.error('Stream parse error:', e);
        }
    };

    eventSource.onerror = function() {
        if (eventSource) eventSource.close();
    };
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
</script>