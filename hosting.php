<?php
// Include your existing setup logic
if (file_exists('database_config/db.php')) {
    require_once 'database_config/db.php';
}
include $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';

// Dynamic Domain Logic
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$scriptUri = $_SERVER['REQUEST_URI'];
$baseDir = substr($scriptUri, 0, strrpos($scriptUri, '/')) . '/';
$currentGatewayUrl = $protocol . $host . $baseDir;
?>

<main class="flex-grow p-4 md:p-6 relative flex items-center justify-center">
    <!-- Ambient Background Glow Accents -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-4xl w-full mx-auto relative z-10 space-y-8 py-4">

        <!-- Header Section -->
        <section class="border-b border-slate-200/80 pb-6 space-y-2">
            <h1 class="text-sm font-black text-slate-900 tracking-wide flex items-center gap-2">
                <i class="iclass fa-solid fa-code text-teal-600"></i>
                <span>AvenPay API Gateway & Hosting</span>
            </h1>
            <p class="text-xs text-slate-500 max-w-2xl mt-0.5 leading-relaxed">
                Integrate secure M-Pesa STK push checkouts. Select your hosting plan below to activate your merchant account and receive your unique API credentials.
            </p>
        </section>

        <!-- Integration Endpoint -->
        <section class="bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 p-5 rounded-2xl shadow-xl ring-1 ring-slate-200 relative overflow-hidden">
            <h3 class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-2.5">Integration Endpoint</h3>
            <div class="flex items-center gap-3 bg-slate-50/80 px-3.5 py-3 rounded-xl font-mono text-xs border border-slate-200/80">
                <code id="baseUrlText" class="text-teal-600 break-all"><?php echo $currentGatewayUrl . 'api/docs'; ?></code>
                <button onclick="copyTextById('baseUrlText', this)" class="ml-auto w-7 h-7 rounded-lg bg-slate-100/80 border border-slate-300/80 text-slate-500 hover:text-slate-900 flex items-center justify-center transition cursor-pointer shrink-0 text-xs" title="Copy Endpoint">
                    <i class="iclass fa-regular fa-copy"></i>
                </button>
            </div>
        </section>

        <!-- Pricing Plans Section -->
        <section class="space-y-6">
            <div class="text-center space-y-1">
                <h2 class="text-sm md:text-base font-black text-slate-900 tracking-wide uppercase">Choose Your Growth Plan</h2>
                <p class="text-xs text-slate-500">All plans include <span class="text-teal-600 font-bold">Unlimited STK Push</span>, Real-time Webhooks, and Payment Links. Save 5% quarterly or 15% yearly; custom day and month durations are available.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Monthly Plan -->
                <div class="bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 p-5 rounded-2xl shadow-xl hover:border-slate-300 transition-all flex flex-col justify-between relative overflow-hidden ring-1 ring-slate-200">
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600">Monthly</h3>
                        <div class="my-3"><span class="text-2xl font-black text-slate-900">KES 200</span><span class="text-slate-500 text-xs ml-1 font-mono">/mo</span></div>
                        <ul class="text-slate-500 text-xs space-y-2 mb-6">
                            <li class="flex items-center"><i class="iclass fa-solid fa-check text-teal-600 mr-2 text-[10px]"></i> Unlimited STK Push</li>
                            <li class="flex items-center"><i class="iclass fa-solid fa-check text-teal-600 mr-2 text-[10px]"></i> Real-time Webhooks</li>
                            <li class="flex items-center"><i class="iclass fa-solid fa-check text-teal-600 mr-2 text-[10px]"></i> Payment Links</li>
                        </ul>
                    </div>
                    <a href="/settlement" class="block w-full text-center bg-slate-100/80 border border-slate-300/80 hover:bg-slate-200 text-slate-700 hover:text-slate-900 py-2.5 rounded-xl text-xs font-bold transition">Get Started</a>
                </div>

                <!-- Quarterly Plan (Best Value) -->
                <div class="bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-teal-500/50 p-5 rounded-2xl relative shadow-2xl flex flex-col justify-between ring-1 ring-teal-500/20">
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-teal-500 text-slate-950 text-[9px] font-black px-3 py-1 rounded-full uppercase tracking-widest shadow-md">Best Value</div>
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-900">Quarterly</h3>
                        <div class="my-3"><span class="text-2xl font-black text-slate-900">KES 570</span><span class="text-slate-500 text-xs ml-1 font-mono">/3mo</span></div>
                        <ul class="text-slate-600 text-xs space-y-2 mb-6">
                            <li class="flex items-center"><i class="iclass fa-solid fa-check text-teal-600 mr-2 text-[10px]"></i> Unlimited STK Push</li>
                            <li class="flex items-center"><i class="iclass fa-solid fa-check text-teal-600 mr-2 text-[10px]"></i> Real-time Webhooks</li>
                            <li class="flex items-center"><i class="iclass fa-solid fa-check text-teal-600 mr-2 text-[10px]"></i> Payment Links</li>
                        </ul>
                    </div>
                    <a href="/settlement" class="block w-full text-center bg-teal-500 hover:bg-teal-400 text-slate-950 py-2.5 rounded-xl text-xs font-black transition shadow-lg shadow-teal-500/20">Select Quarterly</a>
                </div>

                <!-- Yearly Plan -->
                <div class="bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 p-5 rounded-2xl shadow-xl hover:border-slate-300 transition-all flex flex-col justify-between relative overflow-hidden ring-1 ring-slate-200">
                    <div>
                        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-600">Yearly</h3>
                        <div class="my-3"><span class="text-2xl font-black text-slate-900">KES 2,040</span><span class="text-slate-500 text-xs ml-1 font-mono">/yr</span></div>
                        <ul class="text-slate-500 text-xs space-y-2 mb-6">
                            <li class="flex items-center"><i class="iclass fa-solid fa-check text-teal-600 mr-2 text-[10px]"></i> Unlimited STK Push</li>
                            <li class="flex items-center"><i class="iclass fa-solid fa-check text-teal-600 mr-2 text-[10px]"></i> Real-time Webhooks</li>
                            <li class="flex items-center"><i class="iclass fa-solid fa-check text-teal-600 mr-2 text-[10px]"></i> Payment Links</li>
                        </ul>
                    </div>
                    <a href="/settlement" class="block w-full text-center bg-slate-100/80 border border-slate-300/80 hover:bg-slate-200 text-slate-700 hover:text-slate-900 py-2.5 rounded-xl text-xs font-bold transition">Select Yearly</a>
                </div>
            </div>
        </section>

    </div>
</main>

<script>
    function copyTextById(elementId, btnElement) {
        const textContent = document.getElementById(elementId).innerText;
        navigator.clipboard.writeText(textContent).then(() => {
            const initialHtml = btnElement.innerHTML;
            btnElement.innerHTML = "<i class='iclass fa-solid fa-check text-emerald-400'></i>";
            setTimeout(() => { btnElement.innerHTML = initialHtml; }, 2000);
        });
    }
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>