<?php
// token.php
session_start();
require_once 'database_config/db.php';

// Access Control: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch logged-in user wallet details
$userQuery = $conn->prepare("SELECT wallet_id FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userData = $userQuery->get_result()->fetch_assoc();
$userQuery->close();

$currentWalletId = $userData['wallet_id'] ?? null;

// Auto-generate unique wallet token if user doesn't have one yet
if (empty($currentWalletId)) {
    $isUnique = false;
    while (!$isUnique) {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $randomPart = '';
        for ($i = 0; $i < 9; $i++) {
            $randomPart .= $chars[random_int(0, strlen($chars) - 1)];
        }
        $generatedToken = 'RK0' . $randomPart;

        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE wallet_id = ?");
        $checkStmt->bind_param("s", $generatedToken);
        $checkStmt->execute();
        $checkStmt->bind_result($count);
        $checkStmt->fetch();
        $checkStmt->close();

        if ($count == 0) {
            $isUnique = true;
        }
    }

    $updateStmt = $conn->prepare("UPDATE users SET wallet_id = ? WHERE id = ?");
    $updateStmt->bind_param("si", $generatedToken, $user_id);
    if ($updateStmt->execute()) {
        $currentWalletId = $generatedToken;
    }
    $updateStmt->close();
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
                    <p class="text-xs text-slate-500">Your unique account wallet token assigned automatically</p>
                </div>
            </div>

            <div class="mb-5 p-3 rounded-xl bg-teal-950/40 border border-teal-500/30 text-teal-800 text-xs space-y-1.5">
                <div class="flex items-start space-x-2">
                    <i class="iclass fa-solid fa-circle-info mt-0.5 text-teal-600"></i>
                    <span><strong>C2B Transactions:</strong> Money settled through this token credits directly to your B2C wallet account balance.</span>
                </div>
                <div class="flex items-start space-x-2">
                    <i class="iclass fa-solid fa-circle-info mt-0.5 text-teal-600"></i>
                    <span><strong>B2C Transactions:</strong> Used for assigning and processing withdrawals/disbursements from your account.</span>
                </div>
            </div>

            <div class="p-4 rounded-xl bg-white border border-slate-200 flex items-center justify-between">
                <div>
                    <span class="block text-[11px] text-slate-500 font-medium">Your Wallet Token ID</span>
                    <span id="activeTokenDisplay" class="text-sm font-mono font-bold text-teal-600"><?= htmlspecialchars($currentWalletId ?? 'Generating...') ?></span>
                </div>
                
                <div class="flex items-center gap-2">
                    <?php if (!empty($currentWalletId)): ?>
                        <button type="button" onclick="copyActiveToken()" title="Copy Token" class="w-8 h-8 rounded-lg bg-slate-100 border border-slate-300 text-slate-500 hover:text-teal-600 flex items-center justify-center transition text-xs cursor-pointer">
                            <i id="copyIcon" class="iclass fa-regular fa-copy"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-4 text-center">
                <p class="text-[11px] text-slate-500 italic">This token is permanently bound to your account and cannot be regenerated.</p>
            </div>

        </div>
    </main>

    <?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>

    <script>
        const activeToken = "<?= htmlspecialchars($currentWalletId ?? '') ?>";

        function copyActiveToken() {
            if (activeToken) {
                navigator.clipboard.writeText(activeToken);
                showToast('Wallet token copied to clipboard!', 'success');
                const icon = document.getElementById('copyIcon');
                icon.className = 'iclass fa-solid fa-check text-emerald-600';
                setTimeout(() => {
                    icon.className = 'iclass fa-regular fa-copy';
                }, 2000);
            }
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