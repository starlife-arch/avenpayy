<?php
// profile.php
session_start();
require_once 'database_config/db.php';

// Access Control: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current user details
$userQuery = $conn->prepare("SELECT username, email, phone_number, status, created_at, b2c_withdrawal_pin FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userData = $userQuery->get_result()->fetch_assoc();
$userQuery->close();

$username   = htmlspecialchars($userData['username'] ?? 'User');
$email      = $userData['email'] ?? '';
$phone      = $userData['phone_number'] ?? '';
$status     = htmlspecialchars($userData['status'] ?? 'active');
$currentHashedPin = $userData['b2c_withdrawal_pin'] ?? null;
$hasPin     = !empty($currentHashedPin);

// Handle AJAX Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    // --- HANDLE PIN UPDATE / CHANGE ---
    if ($action === 'update_pin') {
        $currentPin = trim($_POST['current_withdrawal_pin'] ?? '');
        $pin        = trim($_POST['withdrawal_pin'] ?? '');
        $confirmPin = trim($_POST['confirm_withdrawal_pin'] ?? '');

        // If PIN is already set, require and verify current PIN
        if ($hasPin) {
            if (empty($currentPin) || !preg_match('/^\d{4}$/', $currentPin)) {
                echo json_encode(['status' => 'error', 'message' => 'Please enter your current 4-digit PIN.']);
                exit();
            }
            if (!password_verify($currentPin, $currentHashedPin)) {
                echo json_encode(['status' => 'error', 'message' => 'Current PIN is incorrect.']);
                exit();
            }
        }

        if (!preg_match('/^\d{4}$/', $pin)) {
            echo json_encode(['status' => 'error', 'message' => 'New PIN must be strictly 4 numeric digits.']);
            exit();
        }

        if ($pin !== $confirmPin) {
            echo json_encode(['status' => 'error', 'message' => 'New PIN confirmation does not match.']);
            exit();
        }

        $hashedPin = password_hash($pin, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET b2c_withdrawal_pin = ? WHERE id = ?");
        $stmt->bind_param("si", $hashedPin, $user_id);

        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Withdrawal PIN successfully updated!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
        }
        $stmt->close();
        exit();
    }

    // --- HANDLE EMAIL / PHONE UPDATE ---
    if ($action === 'update_contact') {
        $updateType = trim($_POST['update_type'] ?? ''); // 'email' or 'phone'
        $oldValue   = trim($_POST['old_value'] ?? '');
        $newValue   = trim($_POST['new_value'] ?? '');

        if ($updateType === 'email') {
            if ($oldValue !== $email) {
                echo json_encode(['status' => 'error', 'message' => 'Previous email confirmation does not match.']);
                exit();
            }
            if (!filter_var($newValue, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid email address format.']);
                exit();
            }
            $stmtUpdate = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $newValue, $user_id);
        } elseif ($updateType === 'phone') {
            if ($oldValue !== $phone) {
                echo json_encode(['status' => 'error', 'message' => 'Previous phone confirmation does not match.']);
                exit();
            }
            if (!preg_match('/^\+?\d{10,15}$/', $newValue)) {
                echo json_encode(['status' => 'error', 'message' => 'Invalid phone number format.']);
                exit();
            }
            $stmtUpdate = $conn->prepare("UPDATE users SET phone_number = ? WHERE id = ?");
            $stmtUpdate->bind_param("si", $newValue, $user_id);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid update type.']);
            exit();
        }

        if ($stmtUpdate->execute()) {
            echo json_encode(['status' => 'success', 'message' => ucfirst($updateType) . ' updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database update failed.']);
        }
        $stmtUpdate->close();
        exit();
    }
}

// Masking helper functions
function maskEmailShort($email) {
    if (empty($email)) return 'N/A';
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $name = $parts[0];
    $len = strlen($name);
    if ($len <= 3) {
        $masked = substr($name, 0, 1) . '***';
    } else {
        $masked = substr($name, 0, 2) . '****' . substr($name, -1);
    }
    return $masked . '@' . $parts[1];
}

function maskPhoneShort($phone) {
    if (empty($phone)) return 'N/A';
    $len = strlen($phone);
    if ($len <= 6) return '****';
    return substr($phone, 0, 3) . '****' . substr($phone, -3);
}

$maskedEmail = maskEmailShort($email);
$maskedPhone = maskPhoneShort($phone);
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
    <title>My Profile - AvenPay</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        .glass-card { background: rgba(248, 250, 252, 0.98); border: 1px solid rgba(15, 23, 42, 0.1); }
        input { transition: all 0.2s; }
        input:focus { border-color: #0bcf9e; outline: none; }

        /* Hole Digits UI Styling */
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
            color: #10b981;
            transition: all 0.2s ease;
            position: relative;
        }

        .pin-hole.active-highlight {
            border-color: #10b981;
            box-shadow: 0 0 14px rgba(16, 185, 129, 0.35);
            background: rgba(16, 185, 129, 0.08);
        }

        .pin-hole.has-cursor::before {
            content: '';
            width: 2px;
            height: 20px;
            background-color: #10b981;
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
            background-color: #10b981;
        }

        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }

        .processing-pulse {
            animation: pulse-ring 1.2s infinite ease-in-out;
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between">

    <!-- FLOATING TOAST NOTIFICATION -->
    <div id="copyToast" class="fixed bottom-16 right-6 z-50 transform translate-y-10 opacity-0 transition-all duration-300 bg-white border border-emerald-500/30 text-emerald-700 px-3 py-1.5 rounded-lg shadow-xl flex items-center gap-1.5 text-[10px] font-medium">
        <i id="toastIcon" class="iclass fa-solid fa-circle-check text-emerald-600 text-[10px]"></i>
        <span id="toastText">Success!</span>
    </div>

    <!-- HEADER INCLUSION -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php'; ?>

    <!-- MAIN CONTENT BODY -->
    <main class="flex-1 flex items-center justify-center p-4 my-6">
        <div class="w-full max-w-lg glass-card rounded-2xl p-6 shadow-2xl border border-slate-200 space-y-6">
            
            <!-- HEADER TITLE -->
            <div class="flex items-center space-x-3 pb-4 border-b border-slate-200">
                <div class="w-12 h-12 rounded-2xl bg-teal-500/10 border border-teal-500/30 flex items-center justify-center text-teal-600 font-black text-lg">
                    <?= strtoupper(substr($username, 0, 1)) ?>
                </div>
                <div>
                    <h1 class="text-base font-bold text-slate-900 tracking-wide"><?= $username ?></h1>
                    <p class="text-xs text-slate-500">Account Overview & Security Profile</p>
                </div>
            </div>

            <!-- USER INFORMATION DISPLAY -->
            <div class="space-y-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Personal Details</h2>
                
                <div class="grid grid-cols-1 gap-2.5">
                    <div class="p-3 rounded-xl bg-white border border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <i class="iclass fa-solid fa-user text-teal-600 w-4 text-center"></i>
                            <span class="text-xs text-slate-500">Username</span>
                        </div>
                        <span class="text-xs font-semibold text-slate-800 font-mono"><?= $username ?></span>
                    </div>

                    <div class="p-3 rounded-xl bg-white border border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <i class="iclass fa-solid fa-envelope text-teal-600 w-4 text-center"></i>
                            <span class="text-xs text-slate-500">Email Address</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-semibold text-slate-800 font-mono"><?= $maskedEmail ?></span>
                            <button onclick="openUpdateModal('email')" class="text-[11px] text-teal-600 hover:underline cursor-pointer">Change</button>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-white border border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <i class="iclass fa-solid fa-phone text-emerald-600 w-4 text-center"></i>
                            <span class="text-xs text-slate-500">Phone Number</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-semibold text-slate-800 font-mono"><?= $maskedPhone ?></span>
                            <button onclick="openUpdateModal('phone')" class="text-[11px] text-teal-600 hover:underline cursor-pointer">Change</button>
                        </div>
                    </div>

                    <div class="p-3 rounded-xl bg-white border border-slate-200 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <i class="iclass fa-solid fa-shield-halved text-amber-600 w-4 text-center"></i>
                            <span class="text-xs text-slate-500">Account Status</span>
                        </div>
                        <span class="text-[10px] bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 px-2 py-0.5 rounded-md font-bold uppercase"><?= $status ?></span>
                    </div>
                </div>
            </div>

            <!-- WITHDRAWAL PIN SECTION -->
            <div class="pt-4 border-t border-slate-200 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-600 flex items-center gap-2">
                            <i class="iclass fa-solid fa-key text-emerald-600"></i> B2C Withdrawal PIN
                        </h2>
                        <p class="text-[10px] text-slate-500 mt-0.5">Required for withdrawal authorizations.</p>
                    </div>
                    
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] <?= $hasPin ? 'text-emerald-400 bg-emerald-500/10 border border-emerald-500/30' : 'text-amber-400 bg-amber-500/10 border border-amber-500/30' ?> px-2 py-0.5 rounded-md font-semibold">
                            <?= $hasPin ? 'Active' : 'Not Set' ?>
                        </span>
                        <?php if ($hasPin): ?>
                            <!-- BUTTON TO OPEN CHANGE PIN CARD -->
                            <button onclick="openPinModal()" class="bg-slate-100 hover:bg-slate-200 border border-slate-300 text-teal-600 hover:text-teal-700 px-3 py-1 rounded-lg text-xs font-semibold transition cursor-pointer flex items-center gap-1.5">
                                <i class="iclass fa-solid fa-pen-to-square"></i> Change PIN
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- INITIAL PIN SET FORM (ONLY VISIBLE IF NO PIN IS SET YET) -->
                <?php if (!$hasPin): ?>
                    <form id="initialPinForm" onsubmit="saveWithdrawalPin(event)" autocomplete="off" class="space-y-4">
                        <input type="text" name="prevent_autofill_user" autocomplete="username" value="" style="display:none !important;" />
                        <input type="password" name="prevent_autofill_pass" autocomplete="current-password" value="" style="display:none !important;" />

                        <input type="password" id="rawPinInput" maxlength="4" inputmode="numeric" autocomplete="one-time-code" class="sr-only">
                        <input type="password" id="rawConfirmPinInput" maxlength="4" inputmode="numeric" autocomplete="one-time-code" class="sr-only">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label class="block text-[11px] text-slate-500">Enter 4-Digit PIN</label>
                                <div onclick="unlockAndFocus('rawPinInput', 'pinHole')" class="flex items-center gap-2 cursor-pointer">
                                    <div id="pinHole0" class="pin-hole active-highlight has-cursor"></div>
                                    <div id="pinHole1" class="pin-hole"></div>
                                    <div id="pinHole2" class="pin-hole"></div>
                                    <div id="pinHole3" class="pin-hole"></div>
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label class="block text-[11px] text-slate-500">Confirm 4-Digit PIN</label>
                                <div onclick="unlockAndFocus('rawConfirmPinInput', 'confirmHole')" class="flex items-center gap-2 cursor-pointer">
                                    <div id="confirmHole0" class="pin-hole"></div>
                                    <div id="confirmHole1" class="pin-hole"></div>
                                    <div id="confirmHole2" class="pin-hole"></div>
                                    <div id="confirmHole3" class="pin-hole"></div>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-2.5 rounded-xl font-semibold text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-emerald-950/50">
                            <i class="iclass fa-solid fa-floppy-disk"></i> Save Withdrawal PIN
                        </button>
                    </form>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- DEDICATED CHANGE PIN MODAL CARD -->
    <div id="changePinModal" class="fixed inset-0 bg-slate-50/95 z-50 flex items-center justify-center hidden p-4">
        <div class="w-full max-w-md glass-card rounded-2xl p-6 border border-slate-200 space-y-5">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <div class="flex items-center gap-2">
                    <i class="iclass fa-solid fa-shield-keyhole text-emerald-600"></i>
                    <h3 class="text-sm font-bold text-slate-900 uppercase tracking-wider">Change Withdrawal PIN</h3>
                </div>
                <button onclick="closePinModal()" class="text-slate-500 hover:text-rose-600 text-xs cursor-pointer"><i class="iclass fa-solid fa-xmark text-base"></i></button>
            </div>

            <!-- IN-POPUP ERROR NOTICE -->
            <div id="pinModalError" class="hidden p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-700 text-xs flex items-center gap-2">
                <i class="iclass fa-solid fa-triangle-exclamation text-rose-600 shrink-0"></i>
                <span id="pinModalErrorText">Error message here</span>
            </div>

            <form id="changePinForm" onsubmit="saveWithdrawalPin(event)" autocomplete="off" class="space-y-4">
                <input type="text" name="prevent_autofill_user" autocomplete="username" value="" style="display:none !important;" />
                <input type="password" name="prevent_autofill_pass" autocomplete="current-password" value="" style="display:none !important;" />

                <input type="password" id="modalRawCurrentPinInput" maxlength="4" inputmode="numeric" autocomplete="one-time-code" class="sr-only">
                <input type="password" id="modalRawPinInput" maxlength="4" inputmode="numeric" autocomplete="one-time-code" class="sr-only">
                <input type="password" id="modalRawConfirmPinInput" maxlength="4" inputmode="numeric" autocomplete="one-time-code" class="sr-only">

                <!-- CURRENT PIN -->
                <div class="p-3 rounded-xl bg-white border border-slate-200 space-y-1.5">
                    <label class="block text-[11px] font-semibold text-amber-600">Current 4-Digit PIN</label>
                    <div onclick="unlockAndFocus('modalRawCurrentPinInput', 'mCurrentHole')" class="flex items-center gap-2 cursor-pointer">
                        <div id="mCurrentHole0" class="pin-hole active-highlight has-cursor"></div>
                        <div id="mCurrentHole1" class="pin-hole"></div>
                        <div id="mCurrentHole2" class="pin-hole"></div>
                        <div id="mCurrentHole3" class="pin-hole"></div>
                    </div>
                </div>

                <!-- NEW PIN & CONFIRM PIN -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                    <div class="space-y-1.5">
                        <label class="block text-[11px] text-slate-500">New 4-Digit PIN</label>
                        <div onclick="unlockAndFocus('modalRawPinInput', 'mPinHole')" class="flex items-center gap-2 cursor-pointer">
                            <div id="mPinHole0" class="pin-hole"></div>
                            <div id="mPinHole1" class="pin-hole"></div>
                            <div id="mPinHole2" class="pin-hole"></div>
                            <div id="mPinHole3" class="pin-hole"></div>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-[11px] text-slate-500">Confirm New PIN</label>
                        <div onclick="unlockAndFocus('modalRawConfirmPinInput', 'mConfirmHole')" class="flex items-center gap-2 cursor-pointer">
                            <div id="mConfirmHole0" class="pin-hole"></div>
                            <div id="mConfirmHole1" class="pin-hole"></div>
                            <div id="mConfirmHole2" class="pin-hole"></div>
                            <div id="mConfirmHole3" class="pin-hole"></div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white py-2.5 rounded-xl font-semibold text-xs transition flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-emerald-950/50">
                    <i class="iclass fa-solid fa-floppy-disk"></i> Confirm & Update PIN
                </button>
            </form>
        </div>
    </div>

    <!-- PROCESSING OVERLAY MODAL FOR PIN SAVING -->
    <div id="processingModal" class="fixed inset-0 bg-slate-50/90 z-50 flex items-center justify-center hidden p-4">
        <div class="glass-card rounded-2xl p-6 border border-slate-200 flex flex-col items-center justify-center space-y-3 w-64 text-center">
            <div class="w-14 h-14 rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-600 text-xl processing-pulse">
                <i class="iclass fa-solid fa-shield-halved"></i>
            </div>
            <span class="text-xs font-bold text-slate-900">Updating Withdrawal PIN...</span>
            <span class="text-[10px] text-slate-500">Applying changes to your account</span>
        </div>
    </div>

    <!-- UPDATE MODAL FOR EMAIL / PHONE -->
    <div id="updateModal" class="fixed inset-0 bg-slate-50/95 z-50 flex items-center justify-center hidden p-4">
        <div class="w-full max-w-sm glass-card rounded-2xl p-6 border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                <h3 id="modalTitle" class="text-sm font-bold text-slate-900 uppercase tracking-wider">Update Details</h3>
                <button onclick="closeUpdateModal()" class="text-slate-500 hover:text-rose-600 text-xs cursor-pointer"><i class="iclass fa-solid fa-xmark"></i></button>
            </div>

            <!-- IN-POPUP ERROR NOTICE -->
            <div id="updateModalError" class="hidden p-3 rounded-xl bg-rose-950/60 border border-rose-500/40 text-rose-700 text-xs flex items-center gap-2">
                <i class="iclass fa-solid fa-triangle-exclamation text-rose-600 shrink-0"></i>
                <span id="updateModalErrorText">Error message here</span>
            </div>

            <form onsubmit="submitContactUpdate(event)" autocomplete="off" class="space-y-3">
                <input type="hidden" id="updateType" name="update_type">

                <div>
                    <label id="oldLabel" class="block text-[11px] text-slate-500 mb-1">Confirm Previous Value</label>
                    <input type="text" id="oldValueInput" autocomplete="off" required class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-slate-700 text-xs font-mono">
                </div>

                <div>
                    <label id="newLabel" class="block text-[11px] text-slate-500 mb-1">Enter New Value</label>
                    <input type="text" id="newValueInput" autocomplete="off" required class="w-full bg-white border border-slate-300 rounded-xl px-3.5 py-2 text-teal-600 text-xs font-mono font-bold">
                </div>

                <button type="submit" class="w-full bg-teal-600 hover:bg-teal-500 text-white py-2.5 rounded-xl font-semibold text-xs transition cursor-pointer shadow-lg">
                    Confirm & Update
                </button>
            </form>
        </div>
    </div>

    <!-- FOOTER INCLUSION -->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>

    <!-- JAVASCRIPT CONTROLLERS -->
    <script>
        function showToast(message, isError = false) {
            const toast = document.getElementById('copyToast');
            const toastText = document.getElementById('toastText');
            const toastIcon = document.getElementById('toastIcon');

            toastText.innerText = message;
            if (isError) {
                toast.className = "fixed bottom-16 right-6 z-50 transform translate-y-0 opacity-100 transition-all duration-300 bg-white border border-rose-500/40 text-rose-700 px-3 py-1.5 rounded-lg shadow-xl flex items-center gap-1.5 text-[10px] font-medium";
                toastIcon.className = "iclass fa-solid fa-circle-exclamation text-rose-600 text-[10px]";
            } else {
                toast.className = "fixed bottom-16 right-6 z-50 transform translate-y-0 opacity-100 transition-all duration-300 bg-white border border-emerald-500/30 text-emerald-700 px-3 py-1.5 rounded-lg shadow-xl flex items-center gap-1.5 text-[10px] font-medium";
                toastIcon.className = "iclass fa-solid fa-circle-check text-emerald-600 text-[10px]";
            }

            setTimeout(() => {
                toast.classList.remove('translate-y-0', 'opacity-100');
                toast.classList.add('translate-y-10', 'opacity-0');
            }, 4000);
        }

        // --- PIN HOLE DIGIT SYSTEM ---
        function unlockAndFocus(inputId, holePrefix) {
            const input = document.getElementById(inputId);
            if (input) {
                input.focus();
            }
        }

        function setupPinInput(inputId, holePrefix) {
            const input = document.getElementById(inputId);
            if (!input) return;

            input.addEventListener('input', function() {
                let val = this.value.replace(/\D/g, '').slice(0, 4);
                this.value = val;

                for (let i = 0; i < 4; i++) {
                    const hole = document.getElementById(holePrefix + i);
                    if (!hole) continue;

                    hole.classList.remove('active-highlight', 'has-cursor', 'filled-dot');
                    if (i < val.length) {
                        hole.innerText = '';
                        hole.classList.add('filled-dot');
                    } else {
                        hole.innerText = '';
                        if (i === val.length) {
                            hole.classList.add('active-highlight', 'has-cursor');
                        }
                    }
                }
            });

            input.addEventListener('focus', function() {
                let val = this.value;
                let activeIdx = Math.min(val.length, 3);
                const hole = document.getElementById(holePrefix + activeIdx);
                if (hole) {
                    hole.classList.add('active-highlight', 'has-cursor');
                }
            });

            input.addEventListener('blur', function() {
                for (let i = 0; i < 4; i++) {
                    const hole = document.getElementById(holePrefix + i);
                    if (hole) hole.classList.remove('active-highlight', 'has-cursor');
                }
            });
        }

        // Initialize Pin Inputs on Load
        document.addEventListener('DOMContentLoaded', () => {
            setupPinInput('rawPinInput', 'pinHole');
            setupPinInput('rawConfirmPinInput', 'confirmHole');
            setupPinInput('modalRawCurrentPinInput', 'mCurrentHole');
            setupPinInput('modalRawPinInput', 'mPinHole');
            setupPinInput('modalRawConfirmPinInput', 'mConfirmHole');
        });

        // Modal Controls
        function openPinModal() {
            document.getElementById('changePinModal').classList.remove('hidden');
        }
        function closePinModal() {
            document.getElementById('changePinModal').classList.add('hidden');
            document.getElementById('pinModalError').classList.add('hidden');
        }

        function openUpdateModal(type) {
            document.getElementById('updateModal').classList.remove('hidden');
            document.getElementById('updateType').value = type;
            document.getElementById('updateModalError').classList.add('hidden');
            document.getElementById('oldValueInput').value = '';
            document.getElementById('newValueInput').value = '';

            if (type === 'email') {
                document.getElementById('modalTitle').innerText = 'Update Email Address';
                document.getElementById('oldLabel').innerText = 'Confirm Current Email Address';
                document.getElementById('newLabel').innerText = 'Enter New Email Address';
                document.getElementById('newValueInput').type = 'email';
            } else {
                document.getElementById('modalTitle').innerText = 'Update Phone Number';
                document.getElementById('oldLabel').innerText = 'Confirm Current Phone Number';
                document.getElementById('newLabel').innerText = 'Enter New Phone Number';
                document.getElementById('newValueInput').type = 'tel';
            }
        }
        function closeUpdateModal() {
            document.getElementById('updateModal').classList.add('hidden');
        }

        // AJAX SUBMISSIONS
        function saveWithdrawalPin(event) {
            event.preventDefault();
            const isModal = !document.getElementById('changePinModal').classList.contains('hidden');
            
            let currentPin = '', pin = '', confirmPin = '';
            if (isModal) {
                currentPin = document.getElementById('modalRawCurrentPinInput').value;
                pin = document.getElementById('modalRawPinInput').value;
                confirmPin = document.getElementById('modalRawConfirmPinInput').value;
            } else {
                pin = document.getElementById('rawPinInput').value;
                confirmPin = document.getElementById('rawConfirmPinInput').value;
            }

            if (pin.length !== 4 || confirmPin.length !== 4) {
                if (isModal) {
                    document.getElementById('pinModalError').classList.remove('hidden');
                    document.getElementById('pinModalErrorText').innerText = 'PINs must be exactly 4 digits.';
                } else {
                    showToast('PINs must be exactly 4 digits.', true);
                }
                return;
            }

            if (pin !== confirmPin) {
                if (isModal) {
                    document.getElementById('pinModalError').classList.remove('hidden');
                    document.getElementById('pinModalErrorText').innerText = 'New PIN and confirmation do not match.';
                } else {
                    showToast('New PIN and confirmation do not match.', true);
                }
                return;
            }

            document.getElementById('processingModal').classList.remove('hidden');

            const formData = new URLSearchParams();
            formData.append('action', 'update_pin');
            formData.append('current_withdrawal_pin', currentPin);
            formData.append('withdrawal_pin', pin);
            formData.append('confirm_withdrawal_pin', confirmPin);

            fetch('profile.php', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: formData.toString()
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('processingModal').classList.add('hidden');
                if (data.status === 'success') {
                    showToast(data.message);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    if (isModal) {
                        document.getElementById('pinModalError').classList.remove('hidden');
                        document.getElementById('pinModalErrorText').innerText = data.message;
                    } else {
                        showToast(data.message, true);
                    }
                }
            })
            .catch(err => {
                document.getElementById('processingModal').classList.add('hidden');
                showToast('A network error occurred.', true);
            });
        }

        function submitContactUpdate(event) {
            event.preventDefault();
            const updateType = document.getElementById('updateType').value;
            const oldValue = document.getElementById('oldValueInput').value;
            const newValue = document.getElementById('newValueInput').value;

            const formData = new URLSearchParams();
            formData.append('action', 'update_contact');
            formData.append('update_type', updateType);
            formData.append('old_value', oldValue);
            formData.append('new_value', newValue);

            fetch('profile.php', {
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
                    closeUpdateModal();
                    showToast(data.message);
                    setTimeout(() => location.reload(), 1200);
                } else {
                    document.getElementById('updateModalError').classList.remove('hidden');
                    document.getElementById('updateModalErrorText').innerText = data.message;
                }
            })
            .catch(err => {
                document.getElementById('updateModalError').classList.remove('hidden');
                document.getElementById('updateModalErrorText').innerText = 'A network error occurred.';
            });
        }
    </script>
</body>
</html>