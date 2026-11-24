<?php
session_start();
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

$slug = isset($_GET['slug']) ? preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$_GET['slug'])) : null;
$link_data = null;

if ($slug) {
    $stmtLink = $conn->prepare("SELECT * FROM payment_links WHERE slug = ? LIMIT 1");
    $stmtLink->bind_param("s", $slug);
    $stmtLink->execute();
    $link_data = $stmtLink->get_result()->fetch_assoc();
    $stmtLink->close();
}


if ($link_data) {
    require_once __DIR__ . '/lib/payment_links.php';
    $__why = av_link_unavailable_reason($conn, $link_data);
    if ($__why !== '') {
        http_response_code(410);
        echo '<!DOCTYPE html><html lang="en"><head>    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta name="theme-color" content="#ffffff">
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Link unavailable - AvenPay</title></head><body style="font-family:system-ui,sans-serif;background:#f8fafc;color:#1e293b;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0"><div style="text-align:center;padding:24px"><h2 style="margin:0 0 8px">Payment link unavailable</h2><p style="color:#475569">' . htmlspecialchars($__why) . '</p></div></body></html>';
        exit();
    }
}

if (!$link_data) {
    header("Location: /about");
    exit();
}

$raw_logo = $link_data['logo_path'] ?? '';$display_logo = !empty($raw_logo) ? (strpos($raw_logo, '/') === 0 ? $raw_logo : '/' .$raw_logo) : '/logo.png';

$has_custom_desc = !empty($link_data['description']);$description = $has_custom_desc ? $link_data['description'] : 'Your Secure Digital Payment Hub';
$alignment_class =$has_custom_desc ? 'text-left' : 'text-center';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($link_data['title'] ?? 'Payment') ?> | AvenPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .spinner { width: 36px; height: 36px; border: 3px solid rgba(11, 207, 158, 0.2); border-top: 3px solid #0bcf9e; border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 16px auto; }
        input:-webkit-autofill, input:-webkit-autofill:hover, input:-webkit-autofill:focus {
            -webkit-text-fill-color: #0f172a;
            -webkit-box-shadow: 0 0 0px 1000px #f8fafc inset !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #ffffff; }
        ::-webkit-scrollbar-thumb { background: #f1f5f9; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #e2e8f0; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex items-center justify-center p-4 relative">

    <!-- Ambient Background Glow Accents -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="relative z-10 w-full max-w-md">
        
        <!-- Main Card -->
        <div class="bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6 ring-1 ring-slate-200 relative overflow-hidden">
            
            <!-- Branding Header -->
            <div class="text-center space-y-3">
                <div class="relative inline-block">
                    <img src="<?= htmlspecialchars($display_logo) ?>" class="w-20 h-20 rounded-xl mx-auto object-cover border border-slate-300/80 shadow-lg shadow-teal-500/10" alt="Logo" onerror="this.src='/logo.png'">
                    <span class="absolute -bottom-1 -right-1 bg-teal-500 text-slate-950 p-1.5 rounded-full text-[10px] flex items-center justify-center shadow-md">
                        <i class="iclass fa-solid fa-shield-halved"></i>
                    </span>
                </div>
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 tracking-tight"><?= htmlspecialchars($link_data['title']) ?></h1>
                    
                    <!-- Full Description Container with Dynamic Alignment -->
                    <div class="mt-2 text-slate-500 text-xs sm:text-sm leading-relaxed <?= $alignment_class ?>">
                        <div id="descText" class="whitespace-pre-line break-words max-h-36 overflow-hidden transition-all duration-300">
                            <?= htmlspecialchars($description) ?>
                        </div>
                        <button type="button" id="toggleDescBtn" onclick="toggleDescription()" class="hidden text-teal-600 hover:text-teal-700 font-bold text-[11px] mt-1 uppercase tracking-wider focus:outline-none">
                            Read More <i class="iclass fa-solid fa-chevron-down text-[9px] ml-0.5"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Payment Form -->
            <form id="payForm" autocomplete="off" class="space-y-4">
                <input type="hidden" name="slug" value="<?= htmlspecialchars($slug) ?>">
                
                <div class="space-y-1">
                    <label class="text-[11px] font-bold uppercase tracking-wider text-slate-600">M-Pesa Phone Number</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500 text-xs font-mono pointer-events-none">
                            <i class="iclass fa-solid fa-phone text-teal-600 text-xs"></i>
                        </span>
                        <input type="text" name="phone_number" placeholder="07XXXXXXXX or 01XXXXXXXX" required
                               class="w-full bg-slate-50/80 pl-10 pr-4 py-3 border border-slate-200/80 hover:border-slate-300 rounded-xl text-xs text-slate-900 placeholder-slate-500 outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 transition-all font-mono">
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-[11px] font-bold uppercase tracking-wider text-slate-600">Amount (KES)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500 text-xs font-mono pointer-events-none">
                            <i class="iclass fa-solid fa-tag text-teal-600 text-xs"></i>
                        </span>
                        <input type="number" name="amount" step="1" value="<?= htmlspecialchars((int)$link_data['amount']) ?>" 
                               <?= ($link_data['is_fixed'] == 1) ? 'readonly class="w-full bg-slate-50/40 pl-10 pr-4 py-3 border border-slate-200/80 rounded-xl text-xs text-slate-500 cursor-not-allowed font-mono font-bold"' : 'class="w-full bg-slate-50/80 pl-10 pr-4 py-3 border border-slate-200/80 hover:border-slate-300 rounded-xl text-xs text-slate-900 outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 transition-all font-mono font-bold"' ?> required>
                    </div>
                </div>

                <button type="submit" id="btn" class="w-full mt-2 py-3.5 bg-teal-500 hover:bg-teal-400 text-slate-950 text-xs font-black uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-teal-500/20 cursor-pointer flex items-center justify-center gap-2 active:scale-[0.99]">
                    <i class="iclass fa-solid fa-lock text-[11px]"></i> Pay Now
                </button>
            </form>

            <!-- Status Card -->
            <div id="statusCard" class="hidden rounded-xl p-5 text-sm transition-all"></div>

        </div>

        <!-- Footer Info -->
        <div class="text-center mt-6 space-y-1">
            <p class="text-[11px] text-slate-500 uppercase tracking-widest font-semibold flex items-center justify-center gap-1.5">
                <i class="iclass fa-solid fa-bolt text-teal-600"></i> Powered by AvenPay Secure Checkout
            </p>
        </div>

    </div>

<script>
// Helper function to format text (Bold asterisks & Clickable https:// links)
function formatDescriptionText(text) {
    if (!text) return '';

    let safeText = text
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");

    safeText = safeText.replace(/\*(.*?)\*/g, '<strong class="font-bold text-white">$1</strong>');
    safeText = safeText.replace(/(https:\/\/[^\s]+)/g, '<a href="$1" target="_blank" class="text-teal-400 hover:underline break-all">$1</a>');

    return safeText;
}

window.addEventListener('DOMContentLoaded', () => {
    const descEl = document.getElementById('descText');
    const toggleBtn = document.getElementById('toggleDescBtn');
    
    if (descEl) {
        let rawContent = descEl.innerHTML.trim();
        descEl.innerHTML = formatDescriptionText(rawContent);

        if (descEl.scrollHeight > 100 && toggleBtn) {
            toggleBtn.classList.remove('hidden');
        }
    }
});

let isExpanded = false;
function toggleDescription() {
    const descEl = document.getElementById('descText');
    const toggleBtn = document.getElementById('toggleDescBtn');
    isExpanded = !isExpanded;
    if (isExpanded) {
        descEl.style.maxHeight = descEl.scrollHeight + 'px';
        toggleBtn.innerHTML = `Read Less <i class="iclass fa-solid fa-chevron-up text-[9px] ml-0.5"></i>`;
    } else {
        descEl.style.maxHeight = '9rem';
        toggleBtn.innerHTML = `Read More <i class="iclass fa-solid fa-chevron-down text-[9px] ml-0.5"></i>`;
    }
}

const payForm = document.getElementById('payForm');
const statusCard = document.getElementById('statusCard');

payForm.addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btn');
    if(btn.disabled) return;
    
    btn.disabled = true;
    btn.innerHTML = `<i class="iclass fa-solid fa-spinner fa-spin"></i> Processing...`;
    
    statusCard.style.display = "block";
    statusCard.className = "rounded-xl p-5 bg-white/90 border border-slate-200 text-center space-y-2 shadow-xl";
    statusCard.innerHTML = '<div class="spinner"></div><div class="text-xs text-slate-600 font-medium">STK push sent. Please check your phone and enter your M-Pesa PIN...</div>';
    
    fetch('/api/stk_push.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json())
    .then(d => {
        if(d.status === 'success') {
            pollStatus(d.checkout_id);
        } else {
            statusCard.className = "rounded-xl p-5 bg-rose-500/10 border border-rose-500/30 text-rose-700 text-center text-xs";
            statusCard.innerHTML = `<i class="iclass fa-solid fa-triangle-exclamation mr-1.5"></i> Error: ${d.message}`;
            btn.disabled = false;
            btn.innerHTML = `<i class="iclass fa-solid fa-lock text-[11px]"></i> Pay Now`;
        }
    })
    .catch(() => {
        statusCard.className = "rounded-xl p-5 bg-rose-500/10 border border-rose-500/30 text-rose-700 text-center text-xs";
        statusCard.innerHTML = `<i class="iclass fa-solid fa-triangle-exclamation mr-1.5"></i> Network error occurred. Please try again.`;
        btn.disabled = false;
        btn.innerHTML = `<i class="iclass fa-solid fa-lock text-[11px]"></i> Pay Now`;
    });
});

function pollStatus(id) {
    let i = setInterval(() => {
        fetch('/api/stk_push.php?check_status=' + encodeURIComponent(id))
        .then(r => r.json())
        .then(d => {
            if((d.status||'').toUpperCase() === 'SUCCESS') {
                clearInterval(i);
                
                const config = <?= json_encode([
                    'mode' => $link_data['redirect_mode'],
                    'file' => $link_data['file_path'],
                    'url'  => $link_data['success_url']
                ]) ?>;

                const hasRedirect = (config.mode === 'download' && config.file) || 
                                    (config.mode === 'inline' && config.file) || 
                                    config.url;

                let actionText = "Proceed";
                let actionIcon = "fa-arrow-right";
                if (config.mode === 'download') {
                    actionText = "Download File";
                    actionIcon = "fa-download";
                } else if (config.mode === 'inline') {
                    actionText = "Open Link / File";
                    actionIcon = "fa-external-link-alt";
                } else if (config.url) {
                    actionText = "Continue to Destination";
                    actionIcon = "fa-arrow-right";
                }

                const formattedAmount = Math.round(parseFloat(d.amount));

                if (hasRedirect) {
                    statusCard.className = "rounded-xl p-5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-center space-y-3 shadow-xl";
                    statusCard.innerHTML = `
                        <div class="w-10 h-10 bg-emerald-500/20 text-emerald-400 rounded-xl flex items-center justify-center mx-auto text-sm border border-emerald-500/30">
                            <i class="iclass fa-solid fa-check"></i>
                        </div>
                        <div class="font-bold text-xs text-white uppercase tracking-wider">Payment Confirmed!</div>
                        <div class="text-xs bg-slate-50/80 p-3 rounded-xl border border-emerald-500/20 text-slate-300 font-mono space-y-1">
                            <div>Amount: KES ${formattedAmount}</div>
                            <div>Receipt: <span class="text-emerald-400 font-bold">${d.mpesa_receipt}</span></div>
                        </div>
                        <button type="button" id="actionBtn" class="w-full py-3 bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-black uppercase tracking-widest rounded-xl transition-all shadow-lg shadow-emerald-500/20 cursor-pointer flex items-center justify-center gap-2">
                            <i class="iclass fa-solid ${actionIcon} text-[11px]"></i> ${actionText}
                        </button>
                    `;

                    document.getElementById('actionBtn').addEventListener('click', () => {
                        if (config.mode === 'download' && config.file) {
                            const a = document.createElement('a'); a.href = config.file; a.download = ''; a.click();
                        } else if (config.mode === 'inline' && config.file) {
                            window.open(config.file, '_blank');
                        }
                        if (config.url) {
                            window.location.href = config.url;
                        }
                    });
                } else {
                    statusCard.className = "rounded-xl p-5 bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 text-center space-y-3 shadow-xl";
                    statusCard.innerHTML = `
                        <div class="w-10 h-10 bg-emerald-500/20 text-emerald-600 rounded-xl flex items-center justify-center mx-auto text-sm border border-emerald-500/30">
                            <i class="iclass fa-solid fa-check"></i>
                        </div>
                        <div class="font-bold text-xs text-slate-900 uppercase tracking-wider">Payment Successful!</div>
                        <div class="text-xs bg-slate-50/80 p-3 rounded-xl border border-emerald-500/20 text-slate-600 font-mono space-y-1">
                            <div>Amount: KES ${formattedAmount}</div>
                            <div>Receipt: <span class="text-emerald-400 font-bold">${d.mpesa_receipt}</span></div>
                        </div>
                    `;
                    document.getElementById('btn').disabled = false;
                    document.getElementById('btn').innerHTML = `<i class="iclass fa-solid fa-lock text-[11px]"></i> Pay Now`;
                }

            } else if ((d.status||'').toUpperCase() === 'FAILED') {
                clearInterval(i);
                statusCard.className = "rounded-xl p-5 bg-rose-500/10 border border-rose-500/30 text-rose-700 text-center text-xs space-y-2 shadow-xl";
                statusCard.innerHTML = `
                    <div class="w-10 h-10 bg-rose-500/20 text-rose-600 rounded-xl flex items-center justify-center mx-auto text-sm border border-rose-500/30">
                        <i class="iclass fa-solid fa-xmark"></i>
                    </div>
                    <div class="font-bold text-slate-900 uppercase tracking-wider text-xs">Transaction Failed</div>
                    <div>Please try again or check your M-Pesa balance.</div>
                `;
                document.getElementById('btn').disabled = false;
                document.getElementById('btn').innerHTML = `<i class="iclass fa-solid fa-lock text-[11px]"></i> Pay Now`;
            }
        });
    }, 3000);
}
</script>
</body>
</html>