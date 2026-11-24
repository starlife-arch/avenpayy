<?php
require_once __DIR__ . '/../database_config/env.php';
// ============================================================
// ADMIN AUTHENTICATION
// ============================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: /admin/');
    exit();
}

// ============================================================
// DATABASE CONNECTION
// ============================================================
$db_paths = [
    __DIR__ . '/database_config/db.php',
    __DIR__ . '/../database_config/db.php',
    __DIR__ . '/db.php',
    __DIR__ . '/../db.php',
];

$db_loaded = false;

foreach ($db_paths as $path) {
    if (is_file($path)) {
        require_once $path;
        $db_loaded = true;
        break;
    }
}

$db_available = $db_loaded && isset($conn) && $conn instanceof mysqli;

// ============================================================
// SECURE VAULT
// Use the same key and method wherever values are decrypted.
// Replace this placeholder with a strong secret stored securely.
// ============================================================
class SecureVault
{
    private static function vaultKey(): string { return (string) av_env('VAULT_KEY'); }
    private const METHOD = 'aes-256-cbc';

    public static function encrypt(string $plainText): string
    {
        $ivLength = openssl_cipher_iv_length(self::METHOD);
        $iv = random_bytes($ivLength);

        $ciphertext = openssl_encrypt(
            $plainText,
            self::METHOD,
            self::vaultKey(),
            0,
            $iv
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $ciphertext);
    }

    public static function decrypt(?string $data)
    {
        if ($data === null || $data === '') {
            return false;
        }

        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            return false;
        }

        $ivLength = openssl_cipher_iv_length(self::METHOD);

        if (strlen($decoded) <= $ivLength) {
            return false;
        }

        return openssl_decrypt(
            substr($decoded, $ivLength),
            self::METHOD,
            self::vaultKey(),
            0,
            substr($decoded, 0, $ivLength)
        );
    }
}

// ============================================================
// SAVE SETTINGS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    if (!$db_available) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database connection is not available.',
        ]);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid request data.',
        ]);
        exit;
    }

    $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
    $shortcode = trim((string)($data['shortcode'] ?? ''));
    $initiatorName = trim((string)($data['initiator_name'] ?? ''));
    $consumerKey = trim((string)($data['consumer_key'] ?? ''));
    $consumerSecret = (string)($data['consumer_secret'] ?? '');
    $initiatorPassword = (string)($data['initiator_password'] ?? '');

    if ($shortcode === '' || $initiatorName === '' || $consumerKey === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Shortcode, initiator name, and consumer key are required.',
        ]);
        exit;
    }

    try {
        if ($id) {
            // Empty secret fields keep their currently stored values.
            if ($consumerSecret !== '' && $initiatorPassword !== '') {
                $encryptedSecret = SecureVault::encrypt($consumerSecret);
                $encryptedPassword = SecureVault::encrypt($initiatorPassword);

                $stmt = $conn->prepare(
                    "UPDATE daraja_b2c_settings
                     SET shortcode = ?, initiator_name = ?, consumer_key = ?,
                         consumer_secret = ?, initiator_password = ?,
                         updated_at = NOW()
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'sssssi',
                    $shortcode,
                    $initiatorName,
                    $consumerKey,
                    $encryptedSecret,
                    $encryptedPassword,
                    $id
                );
            } elseif ($consumerSecret !== '') {
                $encryptedSecret = SecureVault::encrypt($consumerSecret);

                $stmt = $conn->prepare(
                    "UPDATE daraja_b2c_settings
                     SET shortcode = ?, initiator_name = ?, consumer_key = ?,
                         consumer_secret = ?, updated_at = NOW()
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'ssssi',
                    $shortcode,
                    $initiatorName,
                    $consumerKey,
                    $encryptedSecret,
                    $id
                );
            } elseif ($initiatorPassword !== '') {
                $encryptedPassword = SecureVault::encrypt($initiatorPassword);

                $stmt = $conn->prepare(
                    "UPDATE daraja_b2c_settings
                     SET shortcode = ?, initiator_name = ?, consumer_key = ?,
                         initiator_password = ?, updated_at = NOW()
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'ssssi',
                    $shortcode,
                    $initiatorName,
                    $consumerKey,
                    $encryptedPassword,
                    $id
                );
            } else {
                $stmt = $conn->prepare(
                    "UPDATE daraja_b2c_settings
                     SET shortcode = ?, initiator_name = ?, consumer_key = ?,
                         updated_at = NOW()
                     WHERE id = ?"
                );
                $stmt->bind_param(
                    'sssi',
                    $shortcode,
                    $initiatorName,
                    $consumerKey,
                    $id
                );
            }
        } else {
            if ($consumerSecret === '' || $initiatorPassword === '') {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Enter both secret values for the first save.',
                ]);
                exit;
            }

            $encryptedSecret = SecureVault::encrypt($consumerSecret);
            $encryptedPassword = SecureVault::encrypt($initiatorPassword);

            $stmt = $conn->prepare(
                "INSERT INTO daraja_b2c_settings
                    (shortcode, initiator_name, consumer_key, consumer_secret,
                     initiator_password, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())"
            );
            $stmt->bind_param(
                'sssss',
                $shortcode,
                $initiatorName,
                $consumerKey,
                $encryptedSecret,
                $encryptedPassword
            );
        }

        if (!$stmt || !$stmt->execute()) {
            throw new RuntimeException('Could not save settings.');
        }

        $stmt->close();

        echo json_encode([
            'success' => true,
            'message' => 'B2C settings saved and encrypted successfully.',
        ]);
    } catch (Throwable $e) {
        error_log($e->getMessage());
        http_response_code(500);

        echo json_encode([
            'success' => false,
            'message' => 'Could not save B2C settings.',
        ]);
    }

    exit;
}

// ============================================================
// LOAD EXISTING SETTINGS
// ============================================================
$settings = [
    'id' => 0,
    'shortcode' => '',
    'initiator_name' => '',
    'consumer_key' => '',
    'consumer_secret' => '',
    'initiator_password' => '',
];

$error = '';

if (!$db_available) {
    $error = 'Database connection is not available.';
} else {
    try {
        $result = $conn->query(
            "SELECT id, shortcode, initiator_name, consumer_key,
                    consumer_secret, initiator_password
             FROM daraja_b2c_settings
             ORDER BY id ASC
             LIMIT 1"
        );

        if (!$result) {
            throw new RuntimeException('Could not load B2C settings.');
        }

        $row = $result->fetch_assoc();

        if ($row) {
            $settings['id'] = (int)$row['id'];
            $settings['shortcode'] = $row['shortcode'] ?? '';
            $settings['initiator_name'] = $row['initiator_name'] ?? '';
            $settings['consumer_key'] = $row['consumer_key'] ?? '';

            $secret = SecureVault::decrypt($row['consumer_secret'] ?? '');
            $password = SecureVault::decrypt($row['initiator_password'] ?? '');

            $settings['consumer_secret'] = $secret !== false
                ? $secret
                : (string)($row['consumer_secret'] ?? '');

            $settings['initiator_password'] = $password !== false
                ? $password
                : (string)($row['initiator_password'] ?? '');
        }

        $result->free();
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $error = 'Could not load B2C settings. Check the database table and columns.';
    }
}

$page_title = 'Production B2C Settings';
include __DIR__ . '/admin-frame/header.php';

$fields = [
    'shortcode',
    'initiator_name',
    'consumer_key',
    'consumer_secret',
    'initiator_password',
];

$sensitiveFields = [
    'consumer_secret',
    'initiator_password',
];

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>

<div class="min-h-[calc(100vh-140px)] flex flex-col justify-between max-w-4xl mx-auto px-2 sm:px-0 pb-6">
    <div class="space-y-3">

        <!-- HEADER CARD -->
        <div class="p-3 sm:p-4 rounded-md
                    bg-gradient-to-br from-emerald-500/10 via-white to-white
                    dark:bg-[#0f172a]
                    border border-emerald-300/70 dark:border-emerald-500/30
                    shadow-sm flex flex-col sm:flex-row
                    items-start sm:items-center justify-between gap-3">

            <div class="min-w-0">
                <h1 class="text-base sm:text-lg font-bold tracking-tight
                           text-slate-800 dark:text-slate-100
                           flex items-center gap-2">
                    <i class="iclass fa-solid fa-key text-emerald-600 dark:text-emerald-400"></i>
                    <span class="text-black dark:text-teal-400">Production B2C Settings</span>
                </h1>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">
                    Manage your shortcode, initiator, and Daraja API credentials.
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" onclick="toggleEditMode()" id="editToggleBtn"
                        class="shrink-0 text-[11px] bg-slate-800 hover:bg-slate-700
                               text-slate-200 px-3 py-1.5 rounded-md transition
                               font-semibold flex items-center gap-1.5 cursor-pointer">
                    <i class="iclass fa-solid fa-lock text-amber-400" id="editLockIcon"></i>
                    <span id="editToggleText">Unlock to Edit</span>
                </button>

                <button type="button" onclick="toggleViewer()"
                        class="shrink-0 text-[11px] bg-slate-800 hover:bg-slate-700
                               text-slate-200 px-3 py-1.5 rounded-md transition
                               font-semibold flex items-center gap-1.5 cursor-pointer">
                    <i class="iclass fa-solid fa-eye text-teal-400"></i>
                    Toggle Values Panel
                </button>
            </div>
        </div>

        <!-- SETTINGS FORM -->
        <div class="p-4 sm:p-5 rounded-md bg-white dark:bg-[#0f172a]
                    border border-slate-200 dark:border-white/10 shadow-sm">

            <?php if ($error !== ''): ?>
                <div class="mb-4 rounded-md bg-rose-100 p-3 text-xs font-semibold text-rose-800">
                    <?= h($error) ?>
                </div>
            <?php endif; ?>

            <form id="b2cForm">
                <input type="hidden" id="settings_id" value="<?= (int)$settings['id'] ?>">

                <div class="mb-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider
                                 text-emerald-600 dark:text-emerald-400
                                 flex items-center gap-1.5 mb-2">
                        <i class="iclass fa-solid fa-circle-check"></i>
                        B2C Credentials
                    </span>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <?php foreach ($fields as $field):
                            $isSensitive = in_array($field, $sensitiveFields, true);
                            $value = $settings[$field] ?? '';
                            $label = ucwords(str_replace('_', ' ', $field));
                        ?>
                            <div class="<?= $field === 'consumer_key' ? 'md:col-span-2' : '' ?>">
                                <label for="<?= h($field) ?>"
                                       class="block text-[10px] font-bold uppercase tracking-wider
                                              text-slate-700 dark:text-slate-300 mb-1">
                                    <?= h($label) ?>
                                </label>

                                <input
                                    type="<?= $isSensitive ? 'password' : 'text' ?>"
                                    id="<?= h($field) ?>"
                                    name="<?= h($field) ?>"
                                    <?= $field === 'shortcode' || $field === 'initiator_name' || $field === 'consumer_key' ? 'required' : '' ?>
                                    readonly
                                    autocomplete="off"
                                    placeholder="<?= $isSensitive && $settings['id'] ? 'Leave blank to keep saved value' : '' ?>"
                                    value="<?= $isSensitive ? '' : h($value) ?>"
                                    data-has-value="<?= $value !== '' ? '1' : '0' ?>"
                                    class="w-full bg-slate-100 dark:bg-slate-900/60
                                           border border-slate-300 dark:border-slate-700
                                           rounded-md px-3 py-2 text-xs font-mono
                                           text-slate-800 dark:text-slate-200
                                           focus:border-emerald-500 focus:ring-1
                                           focus:ring-emerald-500 outline-none transition
                                           read-only:cursor-not-allowed read-only:opacity-90">
                                <?php if ($isSensitive): ?>
                                    <p class="mt-1 text-[10px] text-slate-500">
                                        <?php if ($settings['id']): ?>
                                            Saved value is hidden. Leave blank to keep it unchanged.
                                        <?php else: ?>
                                            Required when saving settings for the first time.
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" id="submitBtn"
                        class="hidden mt-4 w-full bg-emerald-600 hover:bg-emerald-500
                               disabled:opacity-60 disabled:cursor-not-allowed
                               text-white font-bold py-2.5 px-4 rounded-md text-[11px]
                               uppercase tracking-wider transition cursor-pointer shadow-sm
                               items-center justify-center gap-2">
                    <i class="iclass fa-solid fa-shield-halved"></i>
                    Save B2C Settings
                </button>
            </form>

            <div id="responseMessage"
                 class="hidden mt-3 p-3 rounded-md text-xs font-bold text-center"
                 role="status"></div>
        </div>

        <!-- VALUES VIEWER -->
        <div id="viewerPanel"
             class="hidden p-4 sm:p-5 rounded-md bg-white dark:bg-[#0f172a]
                    border border-slate-200 dark:border-white/10 shadow-sm">

            <h2 class="text-[11px] font-bold uppercase tracking-wider
                       text-slate-800 dark:text-slate-200 flex items-center gap-2 mb-3">
                <i class="iclass fa-solid fa-lock-open text-amber-500"></i>
                Stored Values
            </h2>

            <div class="grid grid-cols-1 gap-2">
                <?php foreach ($fields as $field):
                    $value = (string)($settings[$field] ?? '');
                    $isSensitive = in_array($field, $sensitiveFields, true);
                    $display = $value !== '' ? $value : 'Not set';
                ?>
                    <div class="bg-slate-50 dark:bg-slate-900 p-2.5 rounded-md
                                border border-slate-200 dark:border-slate-800
                                flex items-center justify-between gap-2">
                        <div class="overflow-hidden min-w-0 flex-1">
                            <span class="block text-[9px] uppercase font-bold text-slate-500">
                                <?= h(ucwords(str_replace('_', ' ', $field))) ?>
                            </span>

                            <span id="val_<?= h($field) ?>"
                                  data-raw="<?= h($display) ?>"
                                  class="block font-mono text-[11px] text-emerald-600
                                         dark:text-emerald-400 break-all
                                         <?= $isSensitive && $value !== '' ? 'blur-sm select-none cursor-pointer' : 'select-all' ?>"
                                  <?= $isSensitive && $value !== ''
                                      ? 'onclick="toggleValueMask(\'' . h($field) . '\', this)"'
                                      : '' ?>>
                                <?php if ($isSensitive && $value !== ''): ?>
                                    ••••••••••••
                                <?php else: ?>
                                    <?= h($display) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div class="flex items-center gap-1.5 shrink-0">
                            <?php if ($isSensitive && $value !== ''): ?>
                                <button type="button"
                                        onclick="toggleValueMask('<?= h($field) ?>', document.getElementById('val_<?= h($field) ?>'))"
                                        class="text-[10px] font-semibold bg-slate-100 hover:bg-slate-200
                                               dark:bg-slate-800 dark:hover:bg-slate-700
                                               text-slate-700 dark:text-slate-200 border
                                               border-slate-300 dark:border-slate-700
                                               px-2.5 py-1.5 rounded-md transition cursor-pointer"
                                        title="Reveal or hide value">
                                    <i class="iclass fa-solid fa-eye text-teal-400"
                                       id="icon_mask_<?= h($field) ?>"></i>
                                </button>
                            <?php endif; ?>

                            <button type="button"
                                    onclick="copyValue('val_<?= h($field) ?>', this)"
                                    class="text-[10px] font-semibold bg-slate-100 hover:bg-slate-200
                                           dark:bg-slate-800 dark:hover:bg-slate-700
                                           text-slate-700 dark:text-slate-200 border
                                           border-slate-300 dark:border-slate-700
                                           px-3 py-1.5 rounded-md transition
                                           flex items-center gap-1.5 cursor-pointer">
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
const b2cFields = [
    'shortcode',
    'initiator_name',
    'consumer_key',
    'consumer_secret',
    'initiator_password'
];

const sensitiveFields = ['consumer_secret', 'initiator_password'];
let isEditable = false;
let messageTimeout = null;

function toggleEditMode() {
    isEditable = !isEditable;

    b2cFields.forEach(id => {
        const input = document.getElementById(id);
        input.readOnly = !isEditable;
        input.classList.toggle('bg-slate-100', !isEditable);
        input.classList.toggle('dark:bg-slate-900/60', !isEditable);
        input.classList.toggle('bg-slate-50', isEditable);
        input.classList.toggle('dark:bg-slate-900', isEditable);
    });

    document.getElementById('submitBtn').classList.toggle('hidden', !isEditable);
    document.getElementById('submitBtn').classList.toggle('flex', isEditable);
    document.getElementById('editLockIcon').className = isEditable
        ? 'iclass fa-solid fa-lock-open text-emerald-400'
        : 'iclass fa-solid fa-lock text-amber-400';
    document.getElementById('editToggleText').textContent = isEditable
        ? 'Lock Editing'
        : 'Unlock to Edit';
}

function toggleViewer() {
    document.getElementById('viewerPanel').classList.toggle('hidden');
}

function showMessage(message, success) {
    const box = document.getElementById('responseMessage');

    if (messageTimeout) clearTimeout(messageTimeout);

    box.textContent = message;
    box.className = success
        ? 'mt-3 p-3 rounded-md text-xs font-bold text-center bg-emerald-500 text-white shadow-md border border-emerald-400'
        : 'mt-3 p-3 rounded-md text-xs font-bold text-center bg-rose-500 text-white shadow-md border border-rose-400';

    messageTimeout = setTimeout(() => box.classList.add('hidden'), 4000);
}

function toggleValueMask(fieldId, element) {
    const value = element.dataset.raw;
    if (!value || value === 'Not set') return;

    const icon = document.getElementById('icon_mask_' + fieldId);
    const masked = element.classList.contains('blur-sm');

    if (masked) {
        element.textContent = value;
        element.classList.remove('blur-sm', 'select-none');
        element.classList.add('select-all');
        if (icon) icon.className = 'iclass fa-solid fa-eye-slash text-amber-400';
    } else {
        element.textContent = '••••••••••••';
        element.classList.add('blur-sm', 'select-none');
        element.classList.remove('select-all');
        if (icon) icon.className = 'iclass fa-solid fa-eye text-teal-400';
    }
}

async function copyValue(elementId, button) {
    const element = document.getElementById(elementId);
    const value = element.dataset.raw || '';

    if (!value || value === 'Not set') {
        showMessage('There is no value to copy.', false);
        return;
    }

    try {
        await navigator.clipboard.writeText(value);

        const icon = button.querySelector('i');
        const text = button.querySelector('span');
        const oldIcon = icon.className;
        const oldText = text.textContent;

        icon.className = 'iclass fa-solid fa-check text-teal-400';
        text.textContent = 'Copied!';

        setTimeout(() => {
            icon.className = oldIcon;
            text.textContent = oldText;
        }, 1500);
    } catch (error) {
        showMessage('Unable to copy value.', false);
    }
}

document.getElementById('b2cForm').addEventListener('submit', async event => {
    event.preventDefault();

    const button = document.getElementById('submitBtn');
    button.disabled = true;
    button.innerHTML = '<i class="iclass fa-solid fa-spinner animate-spin"></i> Saving...';

    const payload = {
        id: document.getElementById('settings_id').value,
        shortcode: document.getElementById('shortcode').value,
        initiator_name: document.getElementById('initiator_name').value,
        consumer_key: document.getElementById('consumer_key').value,
        consumer_secret: document.getElementById('consumer_secret').value,
        initiator_password: document.getElementById('initiator_password').value
    };

    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (!response.ok || !result.success) {
            throw new Error(result.message || 'Could not save settings.');
        }

        showMessage('Settings saved and encrypted successfully.', true);
        document.getElementById('consumer_secret').value = '';
        document.getElementById('initiator_password').value = '';

        // Reload to reflect the saved values and database ID.
        setTimeout(() => window.location.reload(), 700);
    } catch (error) {
        showMessage(error.message || 'An unexpected error occurred.', false);
    } finally {
        button.disabled = false;
        button.innerHTML = '<i class="iclass fa-solid fa-shield-halved"></i> Save B2C Settings';
    }
});
</script>

<?php include __DIR__ . '/admin-frame/footer.php'; ?>