<?php
require_once __DIR__ . '/database_config/env.php';
// daraja.php
session_start();
require_once 'database_config/db.php';

// Access Control: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$successMessage = '';

// Check and clear success message from session
if (isset($_SESSION['success_message'])) {
    $successMessage = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Secret Encryption Key (Must match your SecureVault class definition)
define('VAULT_ENCRYPTION_KEY', (string) av_env('VAULT_KEY'));

function encryptData($data) {
    if (empty($data)) return '';
    $method = 'aes-256-cbc';
    $ivLength = openssl_cipher_iv_length($method);
    $iv = openssl_random_pseudo_bytes($ivLength);
    $ciphertext = openssl_encrypt($data, $method, VAULT_ENCRYPTION_KEY, 0, $iv);
    return base64_encode($iv . $ciphertext);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $use_custom = isset($_POST['use_custom_mpesa']) ? 1 : 0;
    
    // 1. Update user toggle setting
    $stmt = $conn->prepare("UPDATE users SET use_custom_mpesa = ? WHERE id = ?");
    $stmt->bind_param("ii", $use_custom, $user_id);
    $stmt->execute();
    $stmt->close();

    // 2. Process and save keys if provided
    $fields = ['consumer_key', 'consumer_secret', 'paybill', 'passkey'];
    
    foreach ($fields as $keyName) {
        if (isset($_POST[$keyName]) && trim($_POST[$keyName]) !== '') {
            $plainValue = trim($_POST[$keyName]);
            $encryptedValue = encryptData($plainValue);

            $checkStmt = $conn->prepare("SELECT id FROM user_daraja_settings WHERE user_id = ? AND key_name = ?");
            $checkStmt->bind_param("is", $user_id, $keyName);
            $checkStmt->execute();
            $res = $checkStmt->get_result();

            if ($res->num_rows > 0) {
                $updateStmt = $conn->prepare("UPDATE user_daraja_settings SET encrypted_value = ? WHERE user_id = ? AND key_name = ?");
                $updateStmt->bind_param("sis", $encryptedValue, $user_id, $keyName);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                $insertStmt = $conn->prepare("INSERT INTO user_daraja_settings (user_id, key_name, encrypted_value) VALUES (?, ?, ?)");
                $insertStmt->bind_param("iss", $user_id, $keyName, $encryptedValue);
                $insertStmt->execute();
                $insertStmt->close();
            }
            $checkStmt->close();
        }
    }

    $_SESSION['success_message'] = "Daraja credentials configuration updated successfully.";
    header("Location: /daraja");
    exit();
}

// Fetch current user settings for the toggle
$userQuery = $conn->prepare("SELECT use_custom_mpesa FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userData = $userQuery->get_result()->fetch_assoc();
$useCustomMpesa = $userData['use_custom_mpesa'] ?? 0;
$userQuery->close();

// Fetch existing keys
$existingKeysQuery = $conn->prepare("SELECT key_name FROM user_daraja_settings WHERE user_id = ?");
$existingKeysQuery->bind_param("i", $user_id);
$existingKeysQuery->execute();
$existingKeysResult = $existingKeysQuery->get_result();
$configuredKeys = [];
while ($row = $existingKeysResult->fetch_assoc()) {
    $configuredKeys[$row['key_name']] = true;
}
$existingKeysQuery->close();
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
    <title>M-Pesa Credentials - AvenPay</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #f8fafc inset !important;
            -webkit-text-fill-color: #0f172a !important;
            caret-color: #0f172a !important;
            transition: background-color 5000s ease-in-out 0s;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #ffffff; }
        ::-webkit-scrollbar-thumb { background: #f1f5f9; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #e2e8f0; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col font-sans antialiased">

<?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php'; ?>

<main class="flex-1 flex items-center justify-center p-4 md:p-6 overflow-y-auto relative">
    <!-- Ambient Background Glow Accents -->
    <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

    <div class="max-w-lg w-full my-auto py-2 relative z-10">
        
        <div class="bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 backdrop-blur-xl rounded-2xl p-6 md:p-7 shadow-2xl relative overflow-hidden ring-1 ring-slate-200">

            <!-- Card Header -->
            <div class="flex items-center justify-between mb-5 pb-4 border-b border-slate-200/80">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-teal-500/20 to-teal-500/10 border border-teal-500/30 flex items-center justify-center text-teal-300 shadow-inner">
                        <i class="iclass fa-solid fa-key text-sm"></i>
                    </div>
                    <div>
                        <h1 class="text-sm font-black text-slate-900 tracking-wide">Daraja API Configuration</h1>
                        <p class="text-[11px] text-slate-500 mt-0.5">Manage custom Safaricom credentials or system fallback</p>
                    </div>
                </div>
            </div>

            <?php if (!empty($successMessage)): ?>
                <div id="successNotice" class="mb-4 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 p-3 rounded-xl text-xs flex items-center justify-between transition-opacity duration-500 shadow-lg shadow-emerald-950/20">
                    <div class="flex items-center gap-2">
                        <i class="iclass fa-solid fa-circle-check text-xs"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($successMessage); ?></span>
                    </div>
                    <button onclick="document.getElementById('successNotice').remove()" class="text-emerald-600 hover:text-emerald-800 cursor-pointer">
                        <i class="iclass fa-solid fa-xmark text-xs"></i>
                    </button>
                </div>
            <?php endif; ?>

            <form id="darajaForm" method="POST" class="space-y-4">
                <!-- Toggle Custom Credentials -->
                <div class="bg-slate-50/70 border border-slate-200/80 rounded-xl p-3.5 flex items-center justify-between shadow-inner">
                    <div>
                        <h2 class="text-[11px] font-bold text-slate-700 tracking-wide">Use Custom Credentials</h2>
                        <p class="text-[10px] text-slate-500 mt-0.5">Automatically fallback to system default when disabled.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" id="useCustomToggle" name="use_custom_mpesa" value="1" <?php echo $useCustomMpesa ? 'checked' : ''; ?> class="sr-only peer">
                        <div class="w-10 h-6 bg-slate-100 border border-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-slate-300 after:content-[''] after:absolute after:top-[3px] after:left-[3px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4.5 after:w-4.5 after:transition-all peer-checked:bg-teal-500 peer-checked:border-teal-400"></div>
                    </label>
                </div>

                <div class="space-y-3 pt-1">
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="consumer_key" class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider">Consumer Key</label>
                            <?php if (isset($configuredKeys['consumer_key'])): ?>
                                <span class="text-[9px] bg-emerald-500/10 text-emerald-600 font-semibold px-2 py-0.5 rounded-full border border-emerald-500/20 flex items-center gap-1">
                                    <i class="iclass fa-solid fa-check text-[8px]"></i> Configured
                                </span>
                            <?php endif; ?>
                        </div>
                        <input type="password" id="consumer_key" name="consumer_key" value="" placeholder="<?php echo isset($configuredKeys['consumer_key']) ? 'Leave blank to keep existing' : 'Enter Consumer Key'; ?>" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 rounded-xl px-3.5 py-2.5 text-xs text-slate-700 placeholder-slate-500 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 transition font-mono shadow-inner">
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <label for="consumer_secret" class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider">Consumer Secret</label>
                            <?php if (isset($configuredKeys['consumer_secret'])): ?>
                                <span class="text-[9px] bg-emerald-500/10 text-emerald-600 font-semibold px-2 py-0.5 rounded-full border border-emerald-500/20 flex items-center gap-1">
                                    <i class="iclass fa-solid fa-check text-[8px]"></i> Configured
                                </span>
                            <?php endif; ?>
                        </div>
                        <input type="password" id="consumer_secret" name="consumer_secret" value="" placeholder="<?php echo isset($configuredKeys['consumer_secret']) ? 'Leave blank to keep existing' : 'Enter Consumer Secret'; ?>" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 rounded-xl px-3.5 py-2.5 text-xs text-slate-700 placeholder-slate-500 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 transition font-mono shadow-inner">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label for="paybill" class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider">Paybill / Shortcode</label>
                                <?php if (isset($configuredKeys['paybill'])): ?>
                                    <span class="text-[9px] bg-emerald-500/10 text-emerald-600 font-semibold px-2 py-0.5 rounded-full border border-emerald-500/20 flex items-center gap-1">
                                        <i class="iclass fa-solid fa-check text-[8px]"></i> Configured
                                    </span>
                                <?php endif; ?>
                            </div>
                            <input type="text" id="paybill" name="paybill" value="" placeholder="<?php echo isset($configuredKeys['paybill']) ? 'Keep existing' : 'e.g. 174379'; ?>" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 rounded-xl px-3.5 py-2.5 text-xs text-slate-700 placeholder-slate-500 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 transition font-mono shadow-inner">
                        </div>

                        <div>
                            <div class="flex justify-between items-center mb-1">
                                <label for="passkey" class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider">Passkey</label>
                                <?php if (isset($configuredKeys['passkey'])): ?>
                                    <span class="text-[9px] bg-emerald-500/10 text-emerald-600 font-semibold px-2 py-0.5 rounded-full border border-emerald-500/20 flex items-center gap-1">
                                        <i class="iclass fa-solid fa-check text-[8px]"></i> Configured
                                    </span>
                                <?php endif; ?>
                            </div>
                            <input type="password" id="passkey" name="passkey" value="" placeholder="<?php echo isset($configuredKeys['passkey']) ? 'Keep existing' : 'Enter passkey'; ?>" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 rounded-xl px-3.5 py-2.5 text-xs text-slate-700 placeholder-slate-500 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 transition font-mono shadow-inner">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full mt-4 bg-teal-500 hover:bg-teal-400 text-slate-950 font-black py-3 px-4 rounded-xl transition cursor-pointer flex items-center justify-center gap-2 text-xs shadow-lg shadow-teal-500/20 active:scale-[0.99]">
                    <i class="iclass fa-solid fa-floppy-disk text-slate-950"></i> Save Configuration
                </button>
            </form>
        </div>

    </div>
</main>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>

<script>
window.addEventListener('DOMContentLoaded', () => {
    const notice = document.getElementById('successNotice');
    if (notice) {
        setTimeout(() => {
            notice.style.opacity = '0';
            setTimeout(() => {
                notice.remove();
            }, 500);
        }, 4000);
    }
});
</script>

</body>
</html>