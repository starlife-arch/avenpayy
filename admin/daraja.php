<?php
require_once __DIR__ . '/../database_config/env.php';
// ============================================================
// ADMIN AUTHENTICATION
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header("Location: /admin/");
    exit();
}


// ============================================================
// DATABASE CONNECTION
// ============================================================
$db_paths = [
    __DIR__ . '/database_config/db.php',
    __DIR__ . '/../database_config/db.php',
    __DIR__ . '/db.php',
    __DIR__ . '/../db.php'
];

$db_loaded = false;

foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}


// ============================================================
// SECURE VAULT
// ============================================================
class SecureVault
{
    private const METHOD = 'aes-256-cbc';

    /** AvenPay uses only its own private VAULT_KEY. No PrintPay fallback. */
    private static function vaultKeys(): array
    {
        $key = trim((string) av_env('VAULT_KEY'));
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $key)) {
            throw new RuntimeException('VAULT_KEY must be a 64-character hexadecimal AvenPay key.');
        }
        return [$key];
    }

    private static function encryptionKey(): string
    {
        $keys = self::vaultKeys();
        if (!$keys) {
            throw new RuntimeException('No Daraja encryption key is configured.');
        }
        return $keys[0];
    }

    public static function encrypt(string $plainText): string
    {
        if ($plainText === '') return '';

        $ivLength = openssl_cipher_iv_length(self::METHOD);
        $iv = random_bytes($ivLength);
        $ciphertext = openssl_encrypt(
            $plainText,
            self::METHOD,
            self::encryptionKey(),
            0,
            $iv
        );
        if ($ciphertext === false) {
            throw new RuntimeException('Daraja credential encryption failed.');
        }
        return base64_encode($iv . $ciphertext);
    }

    public static function decrypt($data)
    {
        if (!is_string($data) || $data === '') return null;
        $decoded = base64_decode($data, true);
        if ($decoded === false) return null;

        $ivLength = openssl_cipher_iv_length(self::METHOD);
        if (strlen($decoded) <= $ivLength) return null;

        $iv = substr($decoded, 0, $ivLength);
        $ciphertext = substr($decoded, $ivLength);

        foreach (self::vaultKeys() as $key) {
            $plainText = openssl_decrypt($ciphertext, self::METHOD, $key, 0, $iv);
            if ($plainText !== false) return trim($plainText);
        }
        return null;
    }
}

// ============================================================
// POST / SAVE SETTINGS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    header('Content-Type: application/json; charset=utf-8');

    if (!$db_loaded || !isset($conn) || !($conn instanceof mysqli)) {
        echo json_encode([
            'success' => false,
            'message' => 'Database connection is not available.'
        ]);
        exit;
    }

    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);

    if (!is_array($data)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data received.'
        ]);
        exit;
    }

    try {

        $fields = [
            'consumer_key',
            'consumer_secret',
            'paybill',
            'passkey',
            'initiator_username',
            'initiator_password'
        ];

        $stmt = $conn->prepare("
            UPDATE daraja_settings
            SET encrypted_value = ?
            WHERE key_name = ?
        ");

        if (!$stmt) {
            throw new Exception(
                'Prepare failed: ' . $conn->error
            );
        }

        foreach ($fields as $key_name) {

            if (!array_key_exists($key_name, $data)) {
                continue;
            }

            $plain_value = trim((string)$data[$key_name]);

            if ($plain_value === '') {
                $encrypted_value = SecureVault::encrypt('');
            } else {
                $encrypted_value = SecureVault::encrypt($plain_value);
            }

            $stmt->bind_param(
                "ss",
                $encrypted_value,
                $key_name
            );

            if (!$stmt->execute()) {
                throw new Exception(
                    'Failed to update ' . $key_name
                );
            }
        }

        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'Settings saved successfully.'
        ]);

    } catch (Throwable $e) {

        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }

    exit;
}


// ============================================================
// LOAD EXISTING SETTINGS
// ============================================================
$existing_settings = [
    'consumer_key'       => '',
    'consumer_secret'    => '',
    'paybill'            => '',
    'passkey'            => '',
    'initiator_username' => '',
    'initiator_password' => ''
];

if ($db_loaded && isset($conn) && $conn instanceof mysqli) {

    $res = $conn->query("
        SELECT key_name, encrypted_value
        FROM daraja_settings
    ");

    if ($res) {

        while ($row = $res->fetch_assoc()) {

            $decrypted = SecureVault::decrypt(
                $row['encrypted_value']
            );

            if ($decrypted !== false) {
                $existing_settings[$row['key_name']] = $decrypted;
            }
        }
    }
}


// ============================================================
// PAGE CONFIG
// ============================================================
$page_title = "Daraja API Settings";


// ============================================================
// ADMIN FRAME HEADER
// ============================================================
include __DIR__ . '/admin-frame/header.php';
?>


<!-- ============================================================
     DARAJA SETTINGS (Flex Container for Sticky Footer)
============================================================ -->

<div class="min-h-[calc(100vh-140px)] flex flex-col justify-between max-w-4xl mx-auto px-2 sm:px-0 pb-6">

    <div class="space-y-3">

        <!-- HEADER CARD -->
        <div class="p-3 sm:p-4 rounded-md
                    bg-gradient-to-br from-emerald-500/10 via-white to-white
                    dark:bg-[#0f172a]
                    border border-emerald-300/70 dark:border-emerald-500/30
                    shadow-sm
                    flex flex-col sm:flex-row
                    items-start sm:items-center
                    justify-between gap-2">

            <div class="min-w-0">

                <h1 class="text-base sm:text-lg font-bold tracking-tight
                           text-slate-800 dark:text-slate-100
                           flex items-center gap-2">

                    <i class="iclass fa-solid fa-key
                            text-emerald-600 dark:text-emerald-400"></i>

                    <span class="text-black dark:text-teal-400">
                        Admin Daraja API Manager
                    </span>

                </h1>

                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">
                    Securely store and update Safaricom M-Pesa C2B, B2C, and B2B API credentials.
                </p>

            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <button
                    type="button"
                    onclick="toggleEditMode()"
                    id="editToggleBtn"
                    class="shrink-0 text-[11px]
                           bg-slate-800 hover:bg-slate-700
                           text-slate-200
                           px-3 py-1.5
                           rounded-md
                           transition
                           font-semibold
                           flex items-center gap-1.5
                           cursor-pointer">

                    <i class="iclass fa-solid fa-lock text-amber-400" id="editLockIcon"></i>
                    <span id="editToggleText">Unlock to Edit</span>
                </button>

                <button
                    type="button"
                    onclick="toggleViewer()"
                    class="shrink-0 text-[11px]
                           bg-slate-800 hover:bg-slate-700
                           text-slate-200
                           px-3 py-1.5
                           rounded-md
                           transition
                           font-semibold
                           flex items-center gap-1.5
                           cursor-pointer">

                    <i class="iclass fa-solid fa-eye text-teal-400"></i>
                    Toggle Values Panel
                </button>
            </div>

        </div>


        <!-- SETTINGS FORM -->
        <div class="p-4 sm:p-5
                    rounded-md
                    bg-white dark:bg-[#0f172a]
                    border border-slate-200 dark:border-white/10
                    shadow-sm">

            <form id="darajaForm">

                <!-- PRIMARY C2B SETTINGS -->
                <div class="mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5 mb-2">
                        <i class="iclass fa-solid fa-circle-check"></i> C2B Core Credentials
                    </span>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php
                        $c2b_fields = ['consumer_key', 'consumer_secret', 'paybill', 'passkey'];
                        foreach ($c2b_fields as $field):
                            $fullWidth = ($field === 'consumer_key' || $field === 'consumer_secret');
                            $inputType = ($field === 'consumer_secret' || $field === 'passkey') ? 'password' : 'text';
                        ?>
                            <div class="<?php echo $fullWidth ? 'md:col-span-2' : ''; ?>">
                                <label for="<?php echo $field; ?>"
                                       class="block text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                    <?php echo ucwords(str_replace('_', ' ', $field)); ?>
                                </label>
                                <input
                                    type="<?php echo $inputType; ?>"
                                    id="<?php echo $field; ?>"
                                    name="<?php echo $field; ?>"
                                    required
                                    readonly
                                    autocomplete="off"
                                    value="<?php echo htmlspecialchars($existing_settings[$field] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    class="w-full bg-slate-100 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-md px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition read-only:cursor-not-allowed read-only:opacity-90">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ADVANCED TOGGLE FOR B2C / B2B -->
                <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" onclick="toggleAdvancedFields()" class="text-xs font-semibold text-teal-600 dark:text-teal-400 flex items-center gap-2 cursor-pointer hover:underline">
                        <i class="iclass fa-solid fa-sliders" id="advancedChevron"></i>
                        <span id="advancedToggleLabel">Configure B2C & B2B Credentials</span>
                    </button>
                </div>

                <!-- ADVANCED B2C / B2B FIELDS (Hidden by default) -->
                <div id="advancedFieldsContainer" class="hidden mt-3 p-3 bg-slate-50 dark:bg-slate-900/40 rounded-md border border-slate-200 dark:border-slate-800 space-y-3">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 flex items-center gap-1.5">
                        <i class="iclass fa-solid fa-shield-cat"></i> B2C & B2B Payloads
                    </span>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php
                        $advanced_fields = ['initiator_username', 'initiator_password'];
                        foreach ($advanced_fields as $field):
                            $inputType = ($field === 'initiator_password') ? 'password' : 'text';
                        ?>
                            <div>
                                <label for="<?php echo $field; ?>"
                                       class="block text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                                    <?php echo ucwords(str_replace('_', ' ', $field)); ?>
                                </label>
                                <input
                                    type="<?php echo $inputType; ?>"
                                    id="<?php echo $field; ?>"
                                    name="<?php echo $field; ?>"
                                    readonly
                                    autocomplete="off"
                                    value="<?php echo htmlspecialchars($existing_settings[$field] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                    class="w-full bg-slate-100 dark:bg-slate-900/60 border border-slate-300 dark:border-slate-700 rounded-md px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition read-only:cursor-not-allowed read-only:opacity-90">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>


                <!-- SAVE BUTTON (Hidden until unlocked) -->
                <button
                    type="submit"
                    id="submitBtn"
                    class="hidden mt-4
                           w-full
                           bg-emerald-600
                           hover:bg-emerald-500
                           disabled:opacity-60
                           disabled:cursor-not-allowed
                           text-white
                           font-bold
                           py-2.5
                           px-4
                           rounded-md
                           text-[11px]
                           uppercase
                           tracking-wider
                           transition
                           cursor-pointer
                           shadow-sm
                           flex
                           items-center
                           justify-center
                           gap-2">

                    <i class="iclass fa-solid fa-shield-halved"></i>

                    Save & Encrypt Credentials

                </button>

            </form>


            <!-- RESPONSE -->
            <div
                id="responseMessage"
                class="hidden mt-3 p-3 rounded-md
                       text-xs font-bold text-center">
            </div>

        </div>


        <!-- VIEWER PANEL -->
        <div
            id="viewerPanel"
            class="hidden
                   p-4 sm:p-5
                   rounded-md
                   bg-white dark:bg-[#0f172a]
                   border border-slate-200 dark:border-white/10
                   shadow-sm">

            <h2 class="text-[11px]
                       font-bold
                       uppercase
                       tracking-wider
                       text-slate-800
                       dark:text-slate-200
                       flex items-center gap-2
                       mb-3">

                <i class="iclass fa-solid fa-lock-open text-amber-500"></i>

                Stored Real Values

            </h2>


            <div class="grid grid-cols-1 gap-2">

                <?php 
                $all_fields = array_merge($c2b_fields, $advanced_fields);
                foreach ($all_fields as $field): 
                    $isSensitive = in_array($field, ['consumer_secret', 'passkey', 'initiator_password']);
                    $val = $existing_settings[$field] ?? '';
                    $displayVal = ($val !== '') ? $val : 'Not set';
                ?>

                    <div
                        class="bg-slate-50 dark:bg-slate-900
                               p-2.5
                               rounded-md
                               border border-slate-200
                               dark:border-slate-800
                               flex items-center
                               justify-between
                               gap-2">

                        <div class="overflow-hidden min-w-0 flex-1">

                            <span
                                class="block
                                       text-[9px]
                                       uppercase
                                       font-bold
                                       text-slate-500">

                                <?php
                                echo ucwords(
                                    str_replace('_', ' ', $field)
                                );
                                ?>

                            </span>

                            <div class="flex items-center gap-2 mt-0.5">
                                <span
                                    id="val_<?php echo $field; ?>"
                                    data-raw="<?php echo htmlspecialchars($val, ENT_QUOTES, 'UTF-8'); ?>"
                                    class="block
                                           font-mono
                                           text-[11px]
                                           text-emerald-600
                                           dark:text-emerald-400
                                           <?php echo $isSensitive ? 'blur-sm select-none transition cursor-pointer hover:blur-none' : 'select-all'; ?>
                                           break-all"
                                    <?php if ($isSensitive): ?>
                                    title="Click or use eye icon to reveal"
                                    onclick="toggleValueMask('<?php echo $field; ?>', this)"
                                    <?php endif; ?>>
                                    <?php 
                                    if ($isSensitive && $val !== '') {
                                        echo str_repeat('•', max(12, min(24, strlen($val))));
                                    } else {
                                        echo htmlspecialchars($displayVal, ENT_QUOTES, 'UTF-8');
                                    }
                                    ?>
                                </span>
                            </div>

                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <?php if ($isSensitive && $val !== ''): ?>
                                <button
                                    type="button"
                                    onclick="toggleValueMask('<?php echo $field; ?>', document.getElementById('val_<?php echo $field; ?>'))"
                                    class="text-[10px]
                                           font-semibold
                                           bg-slate-100
                                           hover:bg-slate-200
                                           dark:bg-slate-800
                                           dark:hover:bg-slate-700
                                           text-slate-700
                                           dark:text-slate-200
                                           border border-slate-300
                                           dark:border-slate-700
                                           px-2.5
                                           py-1.5
                                           rounded-md
                                           transition-all
                                           cursor-pointer"
                                    title="Reveal/Hide Value">
                                    <i class="iclass fa-solid fa-eye text-teal-400" id="icon_mask_<?php echo $field; ?>"></i>
                                </button>
                            <?php endif; ?>

                            <button
                                type="button"
                                onclick="copyValue(
                                    'val_<?php echo $field; ?>',
                                    this
                                )"
                                class="text-[10px]
                                       font-semibold
                                       bg-slate-100
                                       hover:bg-slate-200
                                       dark:bg-slate-800
                                       dark:hover:bg-slate-700
                                       text-slate-700
                                       dark:text-slate-200
                                       border border-slate-300
                                       dark:border-slate-700
                                       px-3
                                       py-1.5
                                       rounded-md
                                       transition-all
                                       duration-200
                                       flex items-center gap-1.5
                                       cursor-pointer
                                       shadow-xs">

                                <i class="iclass fa-regular fa-copy text-emerald-500 dark:text-emerald-400"></i>
                                <span>Copy</span>

                            </button>
                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        </div>

    </div>

</div>


<script>

let isEditable = false;
let messageTimeout = null;

function toggleEditMode() {
    isEditable = !isEditable;
    
    const fields = [
        'consumer_key', 'consumer_secret', 'paybill', 'passkey',
        'initiator_username', 'initiator_password'
    ];
    const submitBtn = document.getElementById('submitBtn');
    const editLockIcon = document.getElementById('editLockIcon');
    const editToggleText = document.getElementById('editToggleText');

    fields.forEach(function(fieldId) {
        const input = document.getElementById(fieldId);
        if (input) {
            if (isEditable) {
                input.removeAttribute('readonly');
                input.classList.remove('bg-slate-100', 'dark:bg-slate-900/60');
                input.classList.add('bg-slate-50', 'dark:bg-slate-900');
            } else {
                input.setAttribute('readonly', 'true');
                input.classList.remove('bg-slate-50', 'dark:bg-slate-900');
                input.classList.add('bg-slate-100', 'dark:bg-slate-900/60');
            }
        }
    });

    if (isEditable) {
        submitBtn.classList.remove('hidden');
        editLockIcon.className = 'iclass fa-solid fa-lock-open text-emerald-400';
        editToggleText.textContent = 'Lock Editing';
    } else {
        submitBtn.classList.add('hidden');
        editLockIcon.className = 'iclass fa-solid fa-lock text-amber-400';
        editToggleText.textContent = 'Unlock to Edit';
    }
}

function toggleAdvancedFields() {
    const container = document.getElementById('advancedFieldsContainer');
    container.classList.toggle('hidden');
}

function showAutoDismissMessage(message, isSuccess) {
    const msg = document.getElementById('responseMessage');
    
    if (messageTimeout) {
        clearTimeout(messageTimeout);
    }

    msg.classList.remove('hidden');

    if (isSuccess) {
        msg.className =
            'mt-3 p-3 rounded-md text-xs font-bold text-center ' +
            'bg-emerald-500 text-white shadow-md ' +
            'dark:bg-emerald-600 border border-emerald-400';
        msg.textContent = message;
    } else {
        msg.className =
            'mt-3 p-3 rounded-md text-xs font-bold text-center ' +
            'bg-rose-500 text-white shadow-md ' +
            'dark:bg-rose-600 border border-rose-400';
        msg.textContent = message;
    }

    messageTimeout = setTimeout(() => {
        msg.classList.add('hidden');
    }, 4000);
}

function toggleViewer() {
    const panel = document.getElementById('viewerPanel');
    panel.classList.toggle('hidden');
}

function toggleValueMask(fieldId, el) {
    const rawVal = el.getAttribute('data-raw');
    if (!rawVal || rawVal === 'Not set') return;

    const iconEl = document.getElementById('icon_mask_' + fieldId);
    const isMasked = el.classList.contains('blur-sm');

    if (isMasked) {
        el.textContent = rawVal;
        el.classList.remove('blur-sm', 'select-none');
        el.classList.add('select-all');
        if (iconEl) iconEl.className = 'iclass fa-solid fa-eye-slash text-amber-400';
    } else {
        el.textContent = '••••••••••••';
        el.classList.add('blur-sm', 'select-none');
        el.classList.remove('select-all');
        if (iconEl) iconEl.className = 'iclass fa-solid fa-eye text-teal-400';
    }
}

async function copyValue(elementId, btnElement) {
    const element = document.getElementById(elementId);
    const rawText = element.getAttribute('data-raw') || element.textContent.trim();

    try {
        await navigator.clipboard.writeText(rawText);

        const iconEl = btnElement.querySelector('i');
        const textSpan = btnElement.querySelector('span');

        const originalIconClass = iconEl.className;
        const originalText = textSpan ? textSpan.textContent : '';

        iconEl.className = 'iclass fa-solid fa-check text-teal-400 dark:text-teal-300';
        if (textSpan) {
            textSpan.textContent = 'Copied!';
            textSpan.className = 'text-teal-400 dark:text-teal-300 font-semibold';
        }

        setTimeout(() => {
            iconEl.className = originalIconClass;
            if (textSpan) {
                textSpan.textContent = originalText;
                textSpan.className = '';
            }
        }, 1500);

    } catch (error) {
        alert('Unable to copy value.');
    }
}


document
    .getElementById('darajaForm')
    .addEventListener('submit', async function(e) {

        e.preventDefault();

        const btn = document.getElementById('submitBtn');
        const msg = document.getElementById('responseMessage');

        btn.disabled = true;

        btn.innerHTML = `
            <i class="iclass fa-solid fa-spinner animate-spin"></i>
            Saving...
        `;

        msg.classList.add('hidden');

        const payload = {
            consumer_key: document.getElementById('consumer_key').value,
            consumer_secret: document.getElementById('consumer_secret').value,
            paybill: document.getElementById('paybill').value,
            passkey: document.getElementById('passkey').value,
            initiator_username: document.getElementById('initiator_username').value,
            initiator_password: document.getElementById('initiator_password').value
        };

        try {
            const response = await fetch(
                window.location.href,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                }
            );

            const textResponse = await response.text();
            let result;

            try {
                result = JSON.parse(textResponse);
            } catch (error) {
                throw new Error('Server returned invalid JSON.');
            }

            if (result.success) {
                showAutoDismissMessage('✨ Settings successfully updated and encrypted!', true);

                Object.keys(payload).forEach(function(key) {
                    const viewer = document.getElementById('val_' + key);
                    if (viewer) {
                        const val = payload[key] !== '' ? payload[key] : 'Not set';
                        viewer.setAttribute('data-raw', val);
                        
                        const isSensitive = ['consumer_secret', 'passkey', 'initiator_password'].includes(key);
                        if (isSensitive && val !== 'Not set') {
                            viewer.textContent = '••••••••••••';
                            viewer.classList.add('blur-sm', 'select-none');
                            viewer.classList.remove('select-all');
                        } else {
                            viewer.textContent = val;
                            viewer.classList.remove('blur-sm', 'select-none');
                            viewer.classList.add('select-all');
                        }
                    }
                });

                toggleEditMode();

            } else {
                showAutoDismissMessage(result.message || 'Unable to save settings.', false);
            }

        } catch (error) {
            showAutoDismissMessage(error.message || 'An unexpected network error occurred.', false);

        } finally {
            btn.disabled = false;
            btn.innerHTML = `
                <i class="iclass fa-solid fa-shield-halved"></i>
                Save & Encrypt Credentials
            `;
        }
    });

</script>


<?php
// ============================================================
// ADMIN FRAME FOOTER (Sticky anchored via Flex container)
// ============================================================
include __DIR__ . '/admin-frame/footer.php';
?>