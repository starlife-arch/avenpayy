<?php
// security-credential.php
session_start();

// Safe Database Connection Loader
$db_paths = [
    __DIR__ . '/../database_config/db.php',
    '../database_config/db.php',
    $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php',
    'database_config/db.php'
];

$db_loaded = false;
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}

// Access Control: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Unauthorized session. Please log in again.']);
        exit();
    }
    header("Location: login.php");
    exit();
}

if (!$db_loaded || !isset($conn) || !($conn instanceof mysqli)) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection unavailable.']);
        exit();
    }
    die('Database connection unavailable.');
}

$user_id = $_SESSION['user_id'];

// Handle AJAX Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    while (ob_get_level()) {
        ob_end_clean();
    }
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? 'save_credential';

    // 1. Action: Verify 4-Digit Withdrawal PIN
    if ($action === 'verify_pin') {
        $enteredPin = trim($_POST['pin'] ?? '');

        if (!preg_match('/^\d{4}$/', $enteredPin)) {
            echo json_encode(['status' => 'error', 'message' => 'PIN must be strictly 4 numeric digits.']);
            exit();
        }

        $pinQuery = $conn->prepare("SELECT b2c_withdrawal_pin FROM users WHERE id = ?");
        $pinQuery->bind_param("i", $user_id);
        $pinQuery->execute();
        $userPinData = $pinQuery->get_result()->fetch_assoc();
        $pinQuery->close();

        $hashedPin = $userPinData['b2c_withdrawal_pin'] ?? null;

        if (empty($hashedPin)) {
            echo json_encode(['status' => 'error', 'message' => 'No PIN configured yet. Please visit your profile.']);
            exit();
        }

        if (password_verify($enteredPin, $hashedPin)) {
            echo json_encode(['status' => 'success', 'message' => 'PIN verified successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Incorrect PIN. Please try again.']);
        }
        exit();
    }

    // 2. Action: Save Generated Security Credential
    $credential = trim($_POST['b2c_security_credential'] ?? '');

    if (empty($credential)) {
        echo json_encode(['status' => 'error', 'message' => 'Please generate a security credential first.']);
        exit();
    }

    if (!preg_match('/^pk_0[A-Za-z0-9]{24,}$/', $credential)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid format. Must start with pk_0.']);
        exit();
    }

    // Check uniqueness across other users
    $checkStmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE b2c_security_credential = ? AND id != ?");
    $checkStmt->bind_param("si", $credential, $user_id);
    $checkStmt->execute();
    $checkStmt->bind_result($exists);
    $checkStmt->fetch();
    $checkStmt->close();

    if ($exists > 0) {
        echo json_encode(['status' => 'error', 'message' => 'This security credential is already in use. Please generate a new one.']);
        exit();
    }

    // Update user b2c_security_credential
    $stmt = $conn->prepare("UPDATE users SET b2c_security_credential = ? WHERE id = ?");
    $stmt->bind_param("si", $credential, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'B2C security credential successfully generated and assigned!', 'credential' => $credential]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    }
    $stmt->close();
    exit();
}

// Fetch logged-in user details & check if PIN and security credential exist
$userQuery = $conn->prepare("SELECT b2c_security_credential, b2c_withdrawal_pin FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userData = $userQuery->get_result()->fetch_assoc();
$userQuery->close();

$storedCredential = $userData['b2c_security_credential'] ?? '';
$hasCredential = !empty($storedCredential);
$hasPin = !empty($userData['b2c_withdrawal_pin']);
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
    <title>Security Credential - AvenPay</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .glass-card { background: rgba(248, 250, 252, 0.98); border: 1px solid rgba(15, 23, 42, 0.1); }
        
        /* Mask text visually without triggering browser password managers */
        .credential-masked {
            -webkit-text-security: disc !important;
            user-select: none;
        }

        .pin-hole {
            width: 44px;
            height: 48px;
            border-radius: 12px;
            border: 2px solid rgba(15, 23, 42, 0.12);
            background: rgba(248, 250, 252, 0.95);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 800;
            color: #08916f;
            transition: all 0.2s ease;
            position: relative;
        }

        .pin-hole.active-highlight {
            border-color: #0bcf9e;
            box-shadow: 0 0 14px rgba(11, 207, 158, 0.35);
            background: rgba(11, 207, 158, 0.08);
        }

        .pin-hole.has-cursor::before {
            content: '';
            width: 2px;
            height: 20px;
            background-color: #0bcf9e;
            animation: blink-cursor 1s infinite;
            position: absolute;
        }

        @keyframes blink-cursor {
            0%, 50% { opacity: 1; }
            50.1%, 100% { opacity: 0; }
        }

        .pin-hole.filled-dot::after {
            content: '';
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #0bcf9e;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between">

    <!-- BOTTOM-RIGHT FLOATING TOAST NOTIFICATION -->
    <div id="copyToast" class="fixed bottom-16 right-6 z-50 transform translate-y-10 opacity-0 transition-all duration-300 bg-white/95 border border-emerald-500/30 text-emerald-700 px-3 py-1.5 rounded-lg shadow-xl flex items-center gap-1.5 text-[10px] font-medium backdrop-blur-md">
        <i id="toastIcon" class="iclass fa-solid fa-circle-check text-emerald-600 text-[10px]"></i>
        <span id="toastText">Credential copied!</span>
    </div>

    <!-- HEADER INCLUSION -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php'; ?>

    <!-- MAIN CONTENT BODY -->
    <main class="flex-1 flex items-center justify-center p-4 my-6">
        <div class="w-full max-w-lg glass-card rounded-2xl p-6 shadow-2xl border border-slate-200">
            
            <div class="flex items-center space-x-3 mb-4 border-b border-slate-200 pb-4">
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-600">
                    <i class="iclass fa-solid fa-key text-lg"></i>
                </div>
                <div>
                    <h1 class="text-base font-bold text-slate-900 tracking-wide">B2C Security Credential</h1>
                    <p class="text-xs text-slate-500">Manage and generate your secure payout authorization key</p>
                </div>
            </div>

            <?php if (!$hasPin): ?>
                <div class="mb-5 p-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs space-y-2">
                    <div class="flex items-start space-x-2">
                        <i class="iclass fa-solid fa-triangle-exclamation mt-0.5 text-amber-600"></i>
                        <div>
                            <strong>Action Required:</strong> You must set up your 4-digit B2C Withdrawal PIN in your profile before generating or viewing your security credential.
                        </div>
                    </div>
                    <div class="pt-1">
                        <a href="/profile" class="inline-flex items-center gap-1.5 bg-amber-600 hover:bg-amber-500 text-white px-3 py-1.5 rounded-lg font-semibold text-[11px] transition">
                            <i class="iclass fa-solid fa-user-gear"></i> Visit Profile to Set PIN
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Notice explaining security warning -->
                <div class="mb-5 p-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-800 text-xs space-y-1.5">
                    <div class="flex items-start space-x-2">
                        <i class="iclass fa-solid fa-triangle-exclamation mt-0.5 text-amber-600"></i>
                        <span><strong>Important Notice:</strong> For security purposes, this credential starts with <strong>pk_0</strong> and is protected by your PIN. Copy it immediately upon generation.</span>
                    </div>
                </div>

                <!-- Active Security Credential Status Display -->
                <div class="mb-5 p-3.5 rounded-xl bg-white/80 border border-slate-200 flex items-center justify-between">
                    <div>
                        <span class="block text-[11px] text-slate-500 font-medium">Credential Status</span>
                        <span id="statusText" class="text-xs font-mono font-bold text-amber-600">
                            <?= $hasCredential ? 'Assigned & Protected' : 'Not Assigned' ?>
                        </span>
                    </div>
                    
                    <div id="statusBadge">
                        <?php if ($hasCredential): ?>
                            <span class="text-[10px] bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 px-2 py-0.5 rounded-md font-semibold">Active</span>
                        <?php else: ?>
                            <span class="text-[10px] bg-slate-100 text-slate-500 px-2 py-0.5 rounded-md font-semibold">None</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1.5">Security Credential</label>
                        <div class="flex gap-2">
                            <!-- type="text" + credential-masked CSS completely prevents Chrome Password Manager popups -->
                            <input type="text" id="credentialInput" readonly value="<?= htmlspecialchars($storedCredential) ?>" placeholder="Click Generate to create key..." autocomplete="off" autocorrect="off" spellcheck="false" data-lpignore="true" class="w-full bg-white border border-slate-300 rounded-xl px-4 py-2.5 text-amber-600 font-mono font-bold tracking-wider text-xs credential-masked">
                            
                            <button type="button" onclick="handleViewToggle()" title="Toggle View" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-600 hover:text-amber-600 px-3 py-2.5 rounded-xl font-semibold text-xs transition flex items-center gap-1 shrink-0 cursor-pointer">
                                <i id="eyeIcon" class="iclass fa-solid fa-eye"></i>
                            </button>

                            <button type="button" onclick="copyInputCredential()" title="Copy Generated Credential" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-600 hover:text-amber-600 px-3 py-2.5 rounded-xl font-semibold text-xs transition flex items-center gap-1 shrink-0 cursor-pointer">
                                <i id="copyIcon" class="iclass fa-regular fa-copy"></i>
                            </button>

                            <button type="button" onclick="generateAndSaveCredential()" class="bg-amber-600 hover:bg-amber-500 text-white px-4 py-2.5 rounded-xl font-semibold text-xs transition flex items-center gap-1.5 shrink-0 cursor-pointer">
                                <i class="iclass fa-solid fa-arrows-rotate"></i> Generate
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- PIN VERIFICATION MODAL -->
    <?php if ($hasPin): ?>
    <div id="pinModal" class="fixed inset-0 bg-slate-50/95 z-50 flex items-center justify-center p-4">
        <div class="w-full max-w-xs glass-card rounded-2xl p-6 border border-slate-200 flex flex-col items-center justify-center space-y-4 text-center">
            
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-600 text-xl">
                <i class="iclass fa-solid fa-lock"></i>
            </div>

            <div>
                <h3 class="text-sm font-bold text-slate-900 tracking-wide">Enter Withdrawal PIN</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">Authentication required to view security credential</p>
            </div>

            <div id="pinGateError" class="hidden w-full p-2.5 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-700 text-xs flex items-center justify-center gap-1.5">
                <i class="iclass fa-solid fa-triangle-exclamation text-rose-600 shrink-0"></i>
                <span id="pinGateErrorText">Incorrect PIN. Please try again.</span>
            </div>

            <!-- Dummy form tricks browser into ignoring password capture heuristics -->
            <form onsubmit="event.preventDefault();" autocomplete="off" class="space-y-4 w-full">
                <input type="text" name="fake_username" style="display:none !important;" tabindex="-1" />
                <input type="password" name="fake_password" style="display:none !important;" tabindex="-1" />

                <input type="text" id="gatePinInput" maxlength="4" inputmode="numeric" autocomplete="off" data-lpignore="true" readonly class="sr-only">

                <div onclick="unlockAndFocusGate()" class="flex items-center justify-center gap-2 cursor-pointer my-2">
                    <div id="gateHole0" class="pin-hole active-highlight has-cursor"></div>
                    <div id="gateHole1" class="pin-hole"></div>
                    <div id="gateHole2" class="pin-hole"></div>
                    <div id="gateHole3" class="pin-hole"></div>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- FOOTER INCLUSION -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>

    <script>
        let isUnlocked = false;
        const gateTimers = {};

        if (document.getElementById('gatePinInput')) {
            setupGateDigits();
            setTimeout(unlockAndFocusGate, 300);
        }

        function unlockAndFocusGate() {
            const input = document.getElementById('gatePinInput');
            if (!input) return;
            input.removeAttribute('readonly');
            input.focus();
            updateGateHighlight(input.value.length);
        }

        function updateGateHighlight(len) {
            for (let i = 0; i < 4; i++) {
                const hole = document.getElementById('gateHole' + i);
                if (!hole) continue;
                hole.classList.remove('active-highlight', 'has-cursor');
                if (!hole.classList.contains('filled-dot') && i === len) {
                    hole.classList.add('active-highlight', 'has-cursor');
                }
            }
        }

        function showGateError(message) {
            const errBox = document.getElementById('pinGateError');
            const errText = document.getElementById('pinGateErrorText');
            if (errBox && errText) {
                errText.innerText = message;
                errBox.classList.remove('hidden');
            }
        }

        function hideGateError() {
            const errBox = document.getElementById('pinGateError');
            if (errBox) errBox.classList.add('hidden');
        }

        function clearGateDigits() {
            const input = document.getElementById('gatePinInput');
            if (input) input.value = '';
            for (let i = 0; i < 4; i++) {
                const hole = document.getElementById('gateHole' + i);
                if (hole) {
                    hole.classList.remove('active-highlight', 'filled-dot', 'has-cursor');
                    hole.innerText = '';
                }
            }
            updateGateHighlight(0);
        }

        function setupGateDigits() {
            const input = document.getElementById('gatePinInput');

            input.addEventListener('input', function() {
                hideGateError();
                this.value = this.value.replace(/\D/g, '').slice(0, 4);
                const val = this.value;

                for (let i = 0; i < 4; i++) {
                    const hole = document.getElementById('gateHole' + i);
                    if (!hole) continue;
                    if (gateTimers[i]) clearTimeout(gateTimers[i]);

                    if (i < val.length) {
                        const char = val[i];
                        hole.classList.remove('has-cursor', 'filled-dot');
                        hole.classList.add('active-highlight');
                        hole.innerText = char;

                        gateTimers[i] = setTimeout(() => {
                            hole.innerText = '';
                            hole.classList.add('filled-dot');
                            updateGateHighlight(val.length);
                        }, 300);
                    } else {
                        hole.classList.remove('active-highlight', 'filled-dot', 'has-cursor');
                        hole.innerText = '';
                    }
                }
                updateGateHighlight(val.length);

                if (val.length === 4) {
                    setTimeout(() => verifyGatePin(val), 350);
                }
            });
        }

        function verifyGatePin(pin) {
            const formData = new URLSearchParams();
            formData.append('action', 'verify_pin');
            formData.append('pin', pin);

            fetch(window.location.pathname, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData.toString()
            })
            .then(async res => {
                const text = await res.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error("Server response error.");
                }
            })
            .then(data => {
                if (data.status === 'success') {
                    showToast('PIN verified successfully!', 'success');
                    isUnlocked = true;
                    document.getElementById('pinModal').classList.add('hidden');
                    const credentialEl = document.getElementById('credentialInput');
                    credentialEl.classList.remove('credential-masked');
                    document.getElementById('eyeIcon').className = 'iclass fa-solid fa-eye-slash';
                } else {
                    showGateError(data.message);
                    clearGateDigits();
                }
            })
            .catch(err => {
                showGateError(err.message || 'Server connection error.');
                clearGateDigits();
            });
        }

        function handleViewToggle() {
            if (!isUnlocked) {
                hideGateError();
                clearGateDigits();
                document.getElementById('pinModal').classList.remove('hidden');
                unlockAndFocusGate();
                return;
            }

            const credentialEl = document.getElementById('credentialInput');
            const eyeIcon = document.getElementById('eyeIcon');
            if (credentialEl.classList.contains('credential-masked')) {
                credentialEl.classList.remove('credential-masked');
                eyeIcon.className = 'iclass fa-solid fa-eye-slash';
            } else {
                credentialEl.classList.add('credential-masked');
                eyeIcon.className = 'iclass fa-solid fa-eye';
            }
        }

        function generateAndSaveCredential() {
            const chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
            let randomPart = '';
            for (let i = 0; i < 32; i++) {
                randomPart += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            const credential = 'pk_0' + randomPart;
            const inputEl = document.getElementById('credentialInput');
            inputEl.value = credential;
            
            // Unmask visually for immediate viewing upon generation
            inputEl.classList.remove('credential-masked');
            document.getElementById('eyeIcon').className = 'iclass fa-solid fa-eye-slash';
            isUnlocked = true;

            // Save via AJAX without reloading
            const formData = new URLSearchParams();
            formData.append('action', 'save_credential');
            formData.append('b2c_security_credential', credential);

            fetch('security-credential.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showToast(data.message, 'success');
                    document.getElementById('statusText').innerText = 'Assigned & Protected';
                    document.getElementById('statusBadge').innerHTML = '<span class="text-[10px] bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 px-2 py-0.5 rounded-md font-semibold">Active</span>';
                } else {
                    showToast(data.message, 'error');
                }
            })
            .catch(err => {
                showToast('An error occurred. Please try again.', 'error');
            });
        }

        function copyInputCredential() {
            if (!isUnlocked) {
                hideGateError();
                clearGateDigits();
                document.getElementById('pinModal').classList.remove('hidden');
                unlockAndFocusGate();
                return;
            }

            const val = document.getElementById('credentialInput').value;
            if (val) {
                navigator.clipboard.writeText(val);
                showToast('Copied credential to clipboard', 'success');
                const icon = document.getElementById('copyIcon');
                icon.className = 'iclass fa-solid fa-check text-emerald-600';
                setTimeout(() => {
                    icon.className = 'iclass fa-regular fa-copy';
                }, 2000);
            } else {
                showToast('No credential generated to copy yet!', 'error');
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('copyToast');
            const toastText = document.getElementById('toastText');
            const toastIcon = document.getElementById('toastIcon');

            toastText.innerText = message;
            if (type === 'success') {
                toast.className = "fixed bottom-16 right-6 z-50 transform opacity-100 transition-all duration-300 bg-white/95 border border-emerald-500/30 text-emerald-700 px-3 py-1.5 rounded-lg shadow-xl flex items-center gap-1.5 text-[10px] font-medium backdrop-blur-md";
                toastIcon.className = "iclass fa-solid fa-circle-check text-emerald-600 text-[10px]";
            } else {
                toast.className = "fixed bottom-16 right-6 z-50 transform opacity-100 transition-all duration-300 bg-white/95 border border-rose-500/30 text-rose-700 px-3 py-1.5 rounded-lg shadow-xl flex items-center gap-1.5 text-[10px] font-medium backdrop-blur-md";
                toastIcon.className = "iclass fa-solid fa-triangle-exclamation text-rose-600 text-[10px]";
            }

            setTimeout(() => {
                toast.classList.add('translate-y-10', 'opacity-0');
            }, 5000);
        }
    </script>
</body>
</html>