<?php
// token.php
session_start();
require_once __DIR__ . '/database_config/db.php';

// Access Control: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit();
}

// Load the current wallet ID for the authenticated account.
$user_id = (int) $_SESSION['user_id'];
if (empty($_SESSION['wallet_token_csrf'])) {
    $_SESSION['wallet_token_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['wallet_token_csrf'];
$message = '';
$messageType = 'error';

$userQuery = $conn->prepare("SELECT wallet_id FROM users WHERE id = ? LIMIT 1");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userData = $userQuery->get_result()->fetch_assoc();
$userQuery->close();

if (!$userData) {
    http_response_code(404);
    exit('Account not found.');
}
$currentWalletId = trim((string) ($userData['wallet_id'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentWalletId === '') {
    $postedToken = (string) ($_POST['csrf_token'] ?? '');
    $requestedWalletId = strtoupper(trim((string) ($_POST['wallet_id'] ?? '')));

    if (!hash_equals($csrfToken, $postedToken)) {
        $message = 'Your session security check failed. Refresh the page and try again.';
    } elseif (!preg_match('/^[A-Z0-9]{5,30}$/', $requestedWalletId)) {
        $message = 'Choose 5–30 letters or numbers. Spaces and symbols are not allowed.';
    } elseif (str_starts_with($requestedWalletId, 'RK')) {
        $message = 'Wallet IDs beginning with RK are reserved. Please choose another ID.';
    } else {
        // The unique database index is the final protection against simultaneous claims.
        $saveStmt = $conn->prepare("UPDATE users SET wallet_id = ? WHERE id = ? AND (wallet_id IS NULL OR wallet_id = '')");
        $saveStmt->bind_param("si", $requestedWalletId, $user_id);

        try {
            if ($saveStmt->execute() && $saveStmt->affected_rows === 1) {
                $currentWalletId = $requestedWalletId;
                $message = 'Your wallet ID has been created successfully.';
                $messageType = 'success';
            } else {
                $refreshStmt = $conn->prepare("SELECT wallet_id FROM users WHERE id = ? LIMIT 1");
                $refreshStmt->bind_param("i", $user_id);
                $refreshStmt->execute();
                $freshUser = $refreshStmt->get_result()->fetch_assoc();
                $refreshStmt->close();
                $currentWalletId = trim((string) ($freshUser['wallet_id'] ?? ''));
                $message = $currentWalletId !== ''
                    ? 'A wallet ID is already assigned to your account.'
                    : 'The wallet ID could not be saved. Please try again.';
                $messageType = $currentWalletId !== '' ? 'success' : 'error';
            }
        } catch (mysqli_sql_exception $e) {
            if ((int) $e->getCode() === 1062) {
                $message = 'That wallet ID is already taken. Please choose another one.';
            } else {
                error_log('Wallet ID assignment failed for user ' . $user_id . ': ' . $e->getMessage());
                $message = 'We could not create your wallet ID right now. Please try again later.';
            }
        }
        $saveStmt->close();
    }
}
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
    <title>Wallet Token - AvenPay</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .glass-card { background: rgba(248, 250, 252, 0.98); border: 1px solid rgba(15, 23, 42, 0.1); }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between">

    <div id="copyToast" class="fixed bottom-16 right-6 z-50 transform translate-y-10 opacity-0 transition-all duration-300 bg-white border border-emerald-500/30 text-emerald-700 px-3 py-1.5 rounded-lg shadow-xl flex items-center gap-1.5 text-[10px] font-medium">
        <i id="toastIcon" class="iclass fa-solid fa-circle-check text-emerald-600 text-[10px]"></i>
        <span id="toastText">Token copied!</span>
    </div>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php'; ?>

    <main class="flex-1 flex items-center justify-center p-4 my-6">
        <div class="w-full max-w-lg glass-card rounded-2xl p-6 shadow-2xl border border-slate-200">
            
            <div class="flex items-center space-x-3 mb-4 border-b border-slate-200 pb-4">
                <div class="w-10 h-10 rounded-xl bg-teal-500/10 border border-teal-500/30 flex items-center justify-center text-teal-600">
                    <i class="iclass fa-solid fa-id-card text-lg"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold text-slate-900 tracking-wide">Wallet Token</h1>
                    <p class="text-xs text-slate-500">Choose a unique wallet ID for your AvenPay account</p>
                </div>
            </div>

            <div class="mb-5 p-3 rounded-xl bg-teal-50 border border-teal-200 text-teal-900 text-xs space-y-1.5">
                <div class="flex items-start space-x-2">
                    <i class="iclass fa-solid fa-circle-info mt-0.5 text-teal-600"></i>
                    <span><strong>C2B Transactions:</strong> Money settled through this token credits directly to your B2C wallet account balance.</span>
                </div>
                <div class="flex items-start space-x-2">
                    <i class="iclass fa-solid fa-circle-info mt-0.5 text-teal-600"></i>
                    <span><strong>B2C Transactions:</strong> Used for assigning and processing withdrawals/disbursements from your account.</span>
                </div>
            </div>

            <?php if ($message !== ''): ?>
                <div class="mb-4 rounded-xl border px-3 py-3 text-xs font-semibold <?= $messageType === 'success' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-rose-200 bg-rose-50 text-rose-800' ?>" role="status">
                    <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>

            <?php if ($currentWalletId !== ''): ?>
                <div class="p-4 rounded-xl bg-white border border-slate-200 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <span class="block text-[11px] text-slate-500 font-medium">Your Wallet ID</span>
                        <span id="activeTokenDisplay" class="text-base font-mono font-bold text-teal-700 break-all"><?= htmlspecialchars($currentWalletId, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                    <button type="button" onclick="copyActiveToken()" title="Copy Wallet ID" class="shrink-0 w-9 h-9 rounded-lg bg-slate-100 border border-slate-300 text-slate-600 hover:text-teal-700 flex items-center justify-center transition text-xs cursor-pointer">
                        <i id="copyIcon" class="iclass fa-regular fa-copy"></i>
                    </button>
                </div>
                <div class="mt-4 text-center">
                    <p class="text-[11px] text-slate-500 italic">This wallet ID is permanently assigned to your account and cannot be changed or regenerated.</p>
                </div>
            <?php else: ?>
                <form method="POST" action="/wallet-token" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <label for="wallet_id" class="block text-xs font-bold text-slate-700">Choose your wallet ID</label>
                    <input id="wallet_id" name="wallet_id" type="text" minlength="5" maxlength="30" pattern="[A-Za-z0-9]{5,30}" autocomplete="off" autocapitalize="characters" required placeholder="e.g. AGONPAY2026" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-mono uppercase tracking-wide text-slate-900 focus:border-teal-600 focus:outline-none focus:ring-2 focus:ring-teal-100">
                    <p class="text-[11px] leading-relaxed text-slate-500">Use 5–30 letters or numbers. Your ID must be unique and cannot begin with RK. Once created, it is permanent.</p>
                    <button type="submit" class="w-full rounded-xl bg-teal-600 hover:bg-teal-700 px-4 py-3 text-sm font-bold text-white transition">Generate Token</button>
                </form>
            <?php endif; ?>

        </div>
    </main>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>

    <script>
        const activeToken = <?= json_encode($currentWalletId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        async function copyActiveToken() {
            if (!activeToken) return;

            let copied = false;
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(activeToken);
                    copied = true;
                }
            } catch (error) {
                copied = false;
            }

            // Fallback for browsers or permission policies that block Clipboard API.
            if (!copied) {
                const field = document.createElement('textarea');
                field.value = activeToken;
                field.setAttribute('readonly', '');
                field.style.position = 'fixed';
                field.style.opacity = '0';
                field.style.pointerEvents = 'none';
                document.body.appendChild(field);
                field.focus();
                field.select();
                field.setSelectionRange(0, field.value.length);
                try {
                    copied = document.execCommand('copy');
                } catch (error) {
                    copied = false;
                }
                field.remove();
            }

            const icon = document.getElementById('copyIcon');
            if (copied) {
                icon.className = 'iclass fa-solid fa-check text-emerald-600';
                showToast('Wallet ID copied to clipboard!', 'success');
            } else {
                icon.className = 'iclass fa-regular fa-copy';
                showToast('Copy was blocked. Select the Wallet ID and copy it manually.', 'error');
            }
            setTimeout(() => {
                icon.className = 'iclass fa-regular fa-copy';
            }, 2000);
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('copyToast');
            const toastText = document.getElementById('toastText');
            const toastIcon = document.getElementById('toastIcon');

            toastText.innerText = message;
            if (type === 'success') {
                toast.className = "fixed bottom-16 right-6 z-50 transform opacity-100 transition-all duration-300 bg-white border border-emerald-500/30 text-emerald-700 px-3 py-1.5 rounded-lg shadow-xl flex items-center gap-1.5 text-[10px] font-medium";
                toastIcon.className = "iclass fa-solid fa-circle-check text-emerald-600 text-[10px]";
            } else {
                toast.className = "fixed bottom-16 right-6 z-50 transform opacity-100 transition-all duration-300 bg-white border border-rose-500/30 text-rose-700 px-3 py-1.5 rounded-lg shadow-xl flex items-center gap-1.5 text-[10px] font-medium";
                toastIcon.className = "iclass fa-solid fa-triangle-exclamation text-rose-600 text-[10px]";
            }

            setTimeout(() => {
                toast.classList.add('translate-y-10', 'opacity-0');
            }, 5000);
        }
    </script>
</body>
</html>