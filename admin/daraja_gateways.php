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

if (!$db_loaded || !isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed. Please check your configuration path.");
}

// ============================================================
// ENCRYPTION FOR C2B STK SETTINGS
// Keep this key identical to the one used by the C2B STK setup
// code that decrypts the settings.
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
        return [hex2bin($key)];
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
// UPDATE EXISTING SETTINGS ONLY
// No settings rows are inserted.
// ============================================================
function syncGatewaySettings(mysqli $conn, array $gateway, string $target): void
{
    if ($target === 'b2c') {
        $stmt = $conn->prepare("
            UPDATE daraja_b2c_settings
            SET shortcode = ?,
                initiator_name = ?,
                consumer_key = ?,
                consumer_secret = ?,
                initiator_password = ?,
                updated_at = NOW()
            ORDER BY id ASC
            LIMIT 1
        ");
        if (!$stmt) {
            throw new Exception('Could not prepare B2C payout update: ' . $conn->error);
        }

        $shortcode = (string)($gateway['shortcode'] ?? '');
        $initiatorName = (string)($gateway['initiator_username'] ?? '');
        $consumerKey = (string)($gateway['consumer_key'] ?? '');
        $consumerSecret = SecureVault::encrypt((string)($gateway['consumer_secret'] ?? ''));
        $initiatorPassword = SecureVault::encrypt((string)($gateway['initiator_password'] ?? ''));

        if ($shortcode === '' || $consumerKey === '' || $consumerSecret === '' ||
            $initiatorName === '' || $initiatorPassword === '') {
            $stmt->close();
            throw new Exception('Complete the B2C shortcode, consumer credentials, initiator name and initiator password before activating this gateway.');
        }

        $stmt->bind_param('sssss', $shortcode, $initiatorName, $consumerKey, $consumerSecret, $initiatorPassword);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new Exception('Could not update B2C payout settings: ' . $error);
        }
        $stmt->close();

        // MySQL reports zero affected rows when values are unchanged. Only
        // report a missing setup when no legacy settings row exists at all.
        $check = $conn->query("SELECT id FROM daraja_b2c_settings ORDER BY id ASC LIMIT 1");
        if (!$check || !$check->fetch_assoc()) {
            if ($check) $check->free();
            throw new Exception('No B2C payout settings row exists. Complete the existing B2C setup before activating this gateway.');
        }
        $check->free();
        return;
    }

    if ($target !== 'c2b') {
        throw new Exception('Invalid settings destination.');
    }

    $values = [
        'consumer_key'    => (string)($gateway['consumer_key'] ?? ''),
        'consumer_secret' => (string)($gateway['consumer_secret'] ?? ''),
        'paybill'         => (string)($gateway['shortcode'] ?? ''),
        'passkey'         => (string)($gateway['passkey'] ?? ''),
    ];

    foreach ($values as $keyName => $value) {
        if ($value === '') {
            throw new Exception("The active C2B gateway is missing the required '{$keyName}' value.");
        }
    }

    // C2B credentials are encrypted with the AvenPay VAULT_KEY before storage.
    $stmt = $conn->prepare("
        INSERT INTO daraja_settings (key_name, encrypted_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE encrypted_value = VALUES(encrypted_value)
    ");
    if (!$stmt) {
        throw new Exception('Could not prepare C2B STK settings update: ' . $conn->error);
    }

    try {
        foreach ($values as $keyName => $value) {
            $encryptedValue = SecureVault::encrypt($value);
            $stmt->bind_param('ss', $keyName, $encryptedValue);
            if (!$stmt->execute()) {
                throw new Exception("Could not save C2B STK setting '{$keyName}': " . $stmt->error);
            }
        }
    } catch (Throwable $e) {
        $stmt->close();
        throw $e;
    }
    $stmt->close();
}

// ============================================================
// POST / AJAX HANDLER
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON data received.'
        ]);
        exit;
    }

    $action = $data['action'] ?? '';

    try {
        if ($action === 'save') {
            $id = (int)($data['id'] ?? 0);
            $gatewayName = trim((string)($data['gateway_name'] ?? ''));
            $shortcode = trim((string)($data['shortcode'] ?? ''));
            $consumerKey = trim((string)($data['consumer_key'] ?? ''));
            $consumerSecret = trim((string)($data['consumer_secret'] ?? ''));
            $passkey = trim((string)($data['passkey'] ?? ''));
            $initiatorUsername = trim((string)($data['initiator_username'] ?? ''));
            $initiatorPassword = trim((string)($data['initiator_password'] ?? ''));
            $syncTarget = (string)($data['sync_target'] ?? 'c2b');

            if ($gatewayName === '' || $shortcode === '') {
                throw new Exception('Gateway Name and Shortcode are required.');
            }

            if (!in_array($syncTarget, ['c2b', 'b2c'], true)) {
                throw new Exception('Select a valid setup destination.');
            }

            if ($id > 0) {
                $stmt = $conn->prepare("
                    UPDATE daraja_gateways
                    SET gateway_name = ?,
                        shortcode = ?,
                        consumer_key = ?,
                        consumer_secret = ?,
                        passkey = ?,
                        initiator_username = ?,
                        initiator_password = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                if (!$stmt) {
                    throw new Exception('Could not prepare gateway update: ' . $conn->error);
                }

                $stmt->bind_param(
                    'sssssssi',
                    $gatewayName,
                    $shortcode,
                    $consumerKey,
                    $consumerSecret,
                    $passkey,
                    $initiatorUsername,
                    $initiatorPassword,
                    $id
                );

                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    throw new Exception('Could not update gateway: ' . $error);
                }

                $stmt->close();

                $getGateway = $conn->prepare(
                    "SELECT * FROM daraja_gateways WHERE id = ?"
                );

                if (!$getGateway) {
                    throw new Exception('Could not retrieve gateway: ' . $conn->error);
                }

                $getGateway->bind_param('i', $id);
                $getGateway->execute();
                $result = $getGateway->get_result();
                $gateway = $result->fetch_assoc();
                $getGateway->close();

                if (!$gateway) {
                    throw new Exception('Gateway not found.');
                }

                if ($syncTarget === 'b2c') {
                    // B2C payout details can be updated for active or inactive gateways.
                    syncGatewaySettings($conn, $gateway, 'b2c');
                } elseif ((int)$gateway['is_active'] === 1) {
                    // C2B STK settings can only be updated from an active gateway.
                    syncGatewaySettings($conn, $gateway, 'c2b');
                }
            } else {
                $countResult = $conn->query(
                    "SELECT COUNT(*) AS cnt FROM daraja_gateways"
                );

                if (!$countResult) {
                    throw new Exception('Could not check existing gateways: ' . $conn->error);
                }

                $countRow = $countResult->fetch_assoc();
                $isActive = ((int)($countRow['cnt'] ?? 0) === 0) ? 1 : 0;
                $countResult->free();

                $stmt = $conn->prepare("
                    INSERT INTO daraja_gateways
                    (
                        gateway_name,
                        shortcode,
                        consumer_key,
                        consumer_secret,
                        passkey,
                        initiator_username,
                        initiator_password,
                        is_active,
                        created_at,
                        updated_at
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");

                if (!$stmt) {
                    throw new Exception('Could not prepare gateway insert: ' . $conn->error);
                }

                $stmt->bind_param(
                    'sssssssi',
                    $gatewayName,
                    $shortcode,
                    $consumerKey,
                    $consumerSecret,
                    $passkey,
                    $initiatorUsername,
                    $initiatorPassword,
                    $isActive
                );

                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    throw new Exception('Could not save gateway: ' . $error);
                }

                $newId = (int)$stmt->insert_id;
                $stmt->close();

                if ($isActive === 1) {
                    $getGateway = $conn->prepare(
                        "SELECT * FROM daraja_gateways WHERE id = ?"
                    );

                    if (!$getGateway) {
                        throw new Exception('Could not retrieve the new gateway: ' . $conn->error);
                    }

                    $getGateway->bind_param('i', $newId);
                    $getGateway->execute();
                    $result = $getGateway->get_result();
                    $gateway = $result->fetch_assoc();
                    $getGateway->close();

                    if ($gateway) {
                        if ($syncTarget === 'b2c') {
                            syncGatewaySettings($conn, $gateway, 'b2c');
                        } else {
                            syncGatewaySettings($conn, $gateway, 'c2b');
                        }
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'message' => 'Gateway saved successfully.'
            ]);
            exit;
        }

        if ($action === 'toggle_activate') {
            $id = (int)($data['id'] ?? 0);
            $targetStatus = (int)($data['status'] ?? 0);
            $syncTarget = (string)($data['sync_target'] ?? 'c2b');

            if ($id <= 0) {
                throw new Exception('Invalid Gateway ID.');
            }

            if (!in_array($syncTarget, ['c2b', 'b2c'], true)) {
                throw new Exception('Select a valid setup destination.');
            }

            if ($targetStatus === 1) {
                $conn->begin_transaction();

                try {
                    if (!$conn->query(
                        "UPDATE daraja_gateways SET is_active = 0"
                    )) {
                        throw new Exception(
                            'Could not deactivate existing gateways: ' . $conn->error
                        );
                    }

                    $stmt = $conn->prepare(
                        "UPDATE daraja_gateways SET is_active = 1 WHERE id = ?"
                    );

                    if (!$stmt) {
                        throw new Exception('Could not prepare gateway activation: ' . $conn->error);
                    }

                    $stmt->bind_param('i', $id);

                    if (!$stmt->execute()) {
                        $error = $stmt->error;
                        $stmt->close();
                        throw new Exception('Could not activate gateway: ' . $error);
                    }

                    $stmt->close();

                    $getGateway = $conn->prepare(
                        "SELECT * FROM daraja_gateways WHERE id = ? AND is_active = 1"
                    );

                    if (!$getGateway) {
                        throw new Exception('Could not retrieve gateway: ' . $conn->error);
                    }

                    $getGateway->bind_param('i', $id);
                    $getGateway->execute();
                    $result = $getGateway->get_result();
                    $gateway = $result->fetch_assoc();
                    $getGateway->close();

                    if (!$gateway) {
                        throw new Exception('Gateway not found.');
                    }

                    if ($syncTarget === 'b2c') {
                        syncGatewaySettings($conn, $gateway, 'b2c');
                    } else {
                        if ((int)$gateway['is_active'] !== 1) {
                            throw new Exception(
                                'C2B STK settings can only be updated from an active gateway.'
                            );
                        }
                        syncGatewaySettings($conn, $gateway, 'c2b');
                    }

                    $conn->commit();
                } catch (Throwable $e) {
                    $conn->rollback();
                    throw $e;
                }

                $message = 'Gateway activated and selected setup updated.';
            } else {
                $stmt = $conn->prepare(
                    "UPDATE daraja_gateways SET is_active = 0 WHERE id = ?"
                );

                if (!$stmt) {
                    throw new Exception('Could not prepare gateway deactivation: ' . $conn->error);
                }

                $stmt->bind_param('i', $id);

                if (!$stmt->execute()) {
                    $error = $stmt->error;
                    $stmt->close();
                    throw new Exception('Could not turn off gateway: ' . $error);
                }

                $stmt->close();
                $message = 'Gateway turned off successfully.';
            }

            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            exit;
        }

        if ($action === 'delete') {
            $id = (int)($data['id'] ?? 0);

            if ($id <= 0) {
                throw new Exception('Invalid Gateway ID.');
            }

            $check = $conn->prepare(
                "SELECT is_active FROM daraja_gateways WHERE id = ?"
            );

            if (!$check) {
                throw new Exception('Could not check gateway: ' . $conn->error);
            }

            $check->bind_param('i', $id);
            $check->execute();
            $result = $check->get_result();
            $row = $result->fetch_assoc();
            $check->close();

            if (!$row) {
                throw new Exception('Gateway not found.');
            }

            if ((int)$row['is_active'] === 1) {
                throw new Exception(
                    'Cannot delete the active gateway. Turn it off or activate another gateway first.'
                );
            }

            $stmt = $conn->prepare(
                "DELETE FROM daraja_gateways WHERE id = ?"
            );

            if (!$stmt) {
                throw new Exception('Could not prepare gateway deletion: ' . $conn->error);
            }

            $stmt->bind_param('i', $id);

            if (!$stmt->execute()) {
                $error = $stmt->error;
                $stmt->close();
                throw new Exception('Could not delete gateway: ' . $error);
            }

            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Gateway deleted successfully.'
            ]);
            exit;
        }

        throw new Exception('Unknown action.');
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }
}

// ============================================================
// FETCH GATEWAYS
// ============================================================
$gateways = [];
$res = $conn->query(
    "SELECT * FROM daraja_gateways ORDER BY is_active DESC, id DESC"
);

if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['dec_shortcode'] = $row['shortcode'] ?? '';
        $row['dec_consumer_key'] = $row['consumer_key'] ?? '';
        $row['dec_consumer_secret'] = $row['consumer_secret'] ?? '';
        $row['dec_passkey'] = $row['passkey'] ?? '';
        $row['dec_initiator_username'] = $row['initiator_username'] ?? '';
        $row['dec_initiator_password'] = $row['initiator_password'] ?? '';
        $gateways[] = $row;
    }
    $res->free();
}

$page_title = "Daraja Gateways Manager";
include __DIR__ . '/admin-frame/header.php';
?>

<div class="min-h-[calc(100vh-140px)] flex flex-col justify-between max-w-5xl mx-auto px-2 sm:px-0 pb-6">
    <div class="space-y-4">
        <div class="p-4 rounded-md bg-gradient-to-br from-emerald-500/10 via-white to-white dark:bg-[#0f172a] border border-emerald-300/70 dark:border-emerald-500/30 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
            <div>
                <h1 class="text-base sm:text-lg font-bold tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <i class="fa-solid fa-network-wired text-emerald-600 dark:text-emerald-400"></i>
                    <span class="text-black dark:text-teal-400">Daraja Gateways Manager</span>
                </h1>
                <p class="text-[11px] text-slate-600 dark:text-slate-400 mt-0.5">
                    Manage gateway credentials and choose C2B STK SETUP or B2C PAYOUT SETUP.
                    Inactive gateways may update B2C payout details; C2B STK details require an active gateway.
                </p>
            </div>

            <button type="button" onclick="openModal()"
                    class="shrink-0 text-xs bg-emerald-600 hover:bg-emerald-500 text-white px-3.5 py-2 rounded-md transition font-semibold flex items-center gap-2 cursor-pointer shadow-sm">
                <i class="fa-solid fa-plus"></i>
                Add New Gateway
            </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <?php if (empty($gateways)): ?>
                <div class="md:col-span-2 p-8 text-center bg-white dark:bg-[#0f172a] rounded-md border border-slate-200 dark:border-white/10 text-slate-500">
                    <i class="fa-solid fa-folder-open text-3xl mb-2 text-slate-400"></i>
                    <p class="text-xs">No Daraja gateways configured yet. Click "Add New Gateway" to get started.</p>
                </div>
            <?php else: ?>
                <?php foreach ($gateways as $gw): ?>
                    <div class="relative bg-white dark:bg-[#0f172a] rounded-md border <?php echo !empty($gw['is_active']) ? 'border-emerald-500 ring-1 ring-emerald-500/50 shadow-emerald-500/10' : 'border-slate-200 dark:border-white/10'; ?> p-4 shadow-sm flex flex-col justify-between transition">
                        <div>
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <h3 class="font-bold text-sm text-slate-800 dark:text-slate-100 flex items-center gap-1.5 truncate">
                                    <i class="fa-solid fa-server text-emerald-500"></i>
                                    <?php echo htmlspecialchars($gw['gateway_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </h3>

                                <div class="flex items-center gap-2">
                                    <?php if (!empty($gw['is_active'])): ?>
                                        <span class="inline-flex items-center gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-emerald-500/20">
                                            <i class="fa-solid fa-circle text-[7px] animate-pulse"></i> ACTIVE
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 bg-slate-500/10 text-slate-500 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-slate-500/20">
                                            INACTIVE
                                        </span>
                                    <?php endif; ?>

                                    <button type="button" onclick="toggleReveal(this)" title="Show or hide values"
                                            class="text-slate-400 hover:text-teal-400 transition cursor-pointer text-xs p-1">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="space-y-1.5 text-xs font-mono text-slate-600 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/60 p-2.5 rounded-md border border-slate-200 dark:border-slate-800">
                                <div class="flex justify-between items-center gap-2">
                                    <span class="text-slate-400 text-[10px] uppercase font-sans font-bold">Shortcode:</span>
                                    <span class="secret-view break-all text-right"
                                          data-masked="••••••••"
                                          data-real="<?php echo htmlspecialchars($gw['dec_shortcode'], ENT_QUOTES, 'UTF-8'); ?>">••••••••</span>
                                </div>

                                <div class="flex justify-between items-center gap-2">
                                    <span class="text-slate-400 text-[10px] uppercase font-sans font-bold">Consumer Key:</span>
                                    <span class="secret-view truncate max-w-[180px]"
                                          data-masked="<?php echo htmlspecialchars(substr($gw['dec_consumer_key'], 0, 12) . '...', ENT_QUOTES, 'UTF-8'); ?>"
                                          data-real="<?php echo htmlspecialchars($gw['dec_consumer_key'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars(substr($gw['dec_consumer_key'], 0, 12) . '...', ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>

                                <div class="flex justify-between items-center gap-2">
                                    <span class="text-slate-400 text-[10px] uppercase font-sans font-bold">Passkey:</span>
                                    <span class="secret-view truncate max-w-[180px]"
                                          data-masked="••••••••••••"
                                          data-real="<?php echo htmlspecialchars($gw['dec_passkey'], ENT_QUOTES, 'UTF-8'); ?>">••••••••••••</span>
                                </div>

                                <div class="flex justify-between items-center gap-2">
                                    <span class="text-slate-400 text-[10px] uppercase font-sans font-bold">Initiator Username:</span>
                                    <span class="secret-view truncate max-w-[180px]"
                                          data-masked="<?php echo $gw['dec_initiator_username'] !== '' ? '••••••••' : 'Not set'; ?>"
                                          data-real="<?php echo htmlspecialchars($gw['dec_initiator_username'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $gw['dec_initiator_username'] !== '' ? '••••••••' : 'Not set'; ?></span>
                                </div>

                                <div class="flex justify-between items-center gap-2">
                                    <span class="text-slate-400 text-[10px] uppercase font-sans font-bold">Initiator Password:</span>
                                    <span class="secret-view truncate max-w-[180px]"
                                          data-masked="<?php echo $gw['dec_initiator_password'] !== '' ? '••••••••••••' : 'Not set'; ?>"
                                          data-real="<?php echo htmlspecialchars($gw['dec_initiator_password'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $gw['dec_initiator_password'] !== '' ? '••••••••••••' : 'Not set'; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between gap-2">
                            <div>
                                <?php if (empty($gw['is_active'])): ?>
                                    <button type="button"
                                            onclick='confirmAction("Turn ON Gateway", <?php echo json_encode("Are you sure you want to turn ON " . $gw["gateway_name"] . "?"); ?>, () => toggleActivate(<?php echo (int)$gw['id']; ?>, 1))'
                                            class="text-[11px] bg-emerald-600/10 hover:bg-emerald-600 text-emerald-600 hover:text-white dark:text-emerald-400 px-3 py-1.5 rounded-md transition font-semibold flex items-center gap-1 cursor-pointer border border-emerald-500/30">
                                        <i class="fa-solid fa-power-off"></i> Turn ON
                                    </button>
                                <?php else: ?>
                                    <button type="button"
                                            onclick='confirmAction("Turn OFF Gateway", <?php echo json_encode("Are you sure you want to turn OFF " . $gw["gateway_name"] . "?"); ?>, () => toggleActivate(<?php echo (int)$gw['id']; ?>, 0))'
                                            class="text-[11px] bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white px-3 py-1.5 rounded-md transition font-semibold flex items-center gap-1 cursor-pointer border border-rose-500/30">
                                        <i class="fa-solid fa-power-off"></i> Turn OFF
                                    </button>
                                <?php endif; ?>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        onclick='editGateway(<?php echo htmlspecialchars(json_encode($gw, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, "UTF-8"); ?>)'
                                        class="text-[11px] bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 px-2.5 py-1.5 rounded-md transition font-semibold flex items-center gap-1 cursor-pointer">
                                    <i class="fa-solid fa-pen text-teal-400"></i> Edit
                                </button>

                                <?php if (empty($gw['is_active'])): ?>
                                    <button type="button"
                                            onclick='confirmAction("Delete Gateway", <?php echo json_encode("Are you sure you want to delete " . $gw["gateway_name"] . "? This cannot be undone."); ?>, () => deleteGateway(<?php echo (int)$gw['id']; ?>), "Delete")'
                                            class="text-[11px] bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white px-2.5 py-1.5 rounded-md transition font-semibold flex items-center gap-1 cursor-pointer">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ADD / EDIT MODAL -->
<div id="gatewayModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-3">
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded-lg shadow-xl w-full max-w-lg overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 bg-slate-50 dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800">
            <h3 id="modalTitle" class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-2">
                <i class="fa-solid fa-plus-circle text-emerald-500"></i> Add New Gateway
            </h3>
            <button type="button" onclick="closeModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer">
                <i class="fa-solid fa-xmark text-sm"></i>
            </button>
        </div>

        <form id="gatewayForm" class="p-4 space-y-3">
            <input type="hidden" id="gateway_id" name="id" value="">

            <div>
                <label for="gateway_name" class="block text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">Gateway Name</label>
                <input type="text" id="gateway_name" name="gateway_name" required
                       class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:border-emerald-500 outline-none">
            </div>

            <div>
                <label for="shortcode" class="block text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">Shortcode / Paybill</label>
                <input type="text" id="shortcode" name="shortcode" required
                       class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:border-emerald-500 outline-none">
            </div>

            <div>
                <label for="sync_target" class="block text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">
                    Setup to update
                </label>
                <select id="sync_target" name="sync_target" required
                        class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md px-3 py-2 text-xs text-slate-800 dark:text-slate-200 focus:border-emerald-500 outline-none">
                    <option value="c2b">C2B STK SETUP</option>
                    <option value="b2c">B2C PAYOUT SETUP</option>
                </select>
                <p class="text-[10px] text-slate-500 mt-1">Only existing setup details are updated.</p>
            </div>

            <div>
                <label for="consumer_key" class="block text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">Consumer Key</label>
                <input type="text" id="consumer_key" name="consumer_key" required
                       class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:border-emerald-500 outline-none">
            </div>

            <div>
                <label for="consumer_secret" class="block text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">Consumer Secret</label>
                <input type="password" id="consumer_secret" name="consumer_secret" required autocomplete="new-password"
                       class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:border-emerald-500 outline-none">
            </div>

            <div>
                <label for="passkey" class="block text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">Passkey</label>
                <input type="password" id="passkey" name="passkey" required autocomplete="new-password"
                       class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:border-emerald-500 outline-none">
                <p class="text-[10px] text-slate-500 mt-1">B2C payout setup does not use the passkey field.</p>
            </div>

            <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
                <p class="text-[10px] text-amber-600 dark:text-amber-400 font-semibold mb-2">
                    B2C / B2B initiator credentials — optional
                </p>

                <label for="initiator_username" class="block text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">Initiator Username</label>
                <input type="text" id="initiator_username" name="initiator_username" autocomplete="off"
                       class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md px-3 py-2 text-xs font-mono text-slate-800 dark:text-slate-200 focus:border-emerald-500 outline-none">
            </div>

            <div>
                <label for="initiator_password" class="block text-[10px] font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1">Initiator Password</label>
                <div class="relative">
                    <input type="password" id="initiator_password" name="initiator_password" autocomplete="new-password"
                           class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-md px-3 py-2 pr-10 text-xs font-mono text-slate-800 dark:text-slate-200 focus:border-emerald-500 outline-none">
                    <button type="button" onclick="toggleInitiatorPassword()" title="Show or hide initiator password"
                            class="absolute inset-y-0 right-2 text-slate-400 hover:text-teal-400 cursor-pointer">
                        <i id="initiatorPasswordEye" class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>

            <div id="modalMessage" class="hidden p-2.5 rounded-md text-xs font-bold text-center"></div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                <button type="button" onclick="closeModal()"
                        class="px-3 py-2 text-xs font-semibold bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-md cursor-pointer">
                    Cancel
                </button>
                <button type="submit" id="saveBtn"
                        class="px-4 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white rounded-md cursor-pointer shadow-sm">
                    Save Gateway
                </button>
            </div>
        </form>
    </div>
</div>

<!-- CONFIRMATION MODAL -->
<div id="confirmModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-3">
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded-lg shadow-xl w-full max-w-sm overflow-hidden text-center p-5">
        <div class="w-10 h-10 mx-auto mb-3 rounded-full bg-amber-500/10 text-amber-500 flex items-center justify-center text-lg">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 id="confirmTitle" class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Are you sure?</h3>
        <p id="confirmMessage" class="text-xs text-slate-600 dark:text-slate-400 mb-5 leading-relaxed"></p>
        <div class="flex items-center justify-center gap-2">
            <button type="button" onclick="closeConfirmModal()"
                    class="w-full px-3 py-2 text-xs font-semibold bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 rounded-md cursor-pointer">
                Cancel
            </button>
            <button type="button" id="confirmActionBtn"
                    class="w-full px-3 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white rounded-md cursor-pointer shadow-sm">
                Proceed
            </button>
        </div>
    </div>
</div>

<!-- ALERT MODAL -->
<div id="alertModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-3">
    <div class="bg-white dark:bg-[#0f172a] border border-slate-200 dark:border-white/10 rounded-lg shadow-xl w-full max-w-sm overflow-hidden text-center p-5">
        <div class="w-10 h-10 mx-auto mb-3 rounded-full bg-rose-500/10 text-rose-500 flex items-center justify-center text-lg">
            <i class="fa-solid fa-triangle-exclamation"></i>
        </div>
        <h3 id="alertTitle" class="text-sm font-bold text-slate-800 dark:text-slate-200 mb-1">Notice</h3>
        <p id="alertMessage" class="text-xs text-slate-600 dark:text-slate-400 mb-5 leading-relaxed"></p>
        <button type="button" onclick="closeAlertModal()"
                class="w-full px-3 py-2 text-xs font-bold bg-slate-800 dark:bg-slate-700 hover:bg-slate-700 text-white rounded-md cursor-pointer shadow-sm">
            OK
        </button>
    </div>
</div>

<script>
function showToast(message, type = 'success') {
    const existing = document.getElementById('appToast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.id = 'appToast';
    toast.className = `fixed top-5 right-5 z-50 px-4 py-3 rounded-md shadow-lg text-xs font-bold text-white flex items-center gap-2 ${type === 'success' ? 'bg-emerald-600' : 'bg-rose-600'}`;

    const icon = document.createElement('i');
    icon.className = `fa-solid ${type === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation'}`;

    const text = document.createElement('span');
    text.textContent = message;

    toast.append(icon, text);
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

function showAlert(title, message) {
    document.getElementById('alertTitle').textContent = title;
    document.getElementById('alertMessage').textContent = message;
    document.getElementById('alertModal').classList.remove('hidden');
}

function closeAlertModal() {
    document.getElementById('alertModal').classList.add('hidden');
}

let activeConfirmCallback = null;

function confirmAction(title, message, callback, btnText = 'Confirm') {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;

    const btn = document.getElementById('confirmActionBtn');
    btn.textContent = btnText;
    btn.className = btnText === 'Delete'
        ? 'w-full px-3 py-2 text-xs font-bold bg-rose-600 hover:bg-rose-500 text-white rounded-md cursor-pointer shadow-sm'
        : 'w-full px-3 py-2 text-xs font-bold bg-emerald-600 hover:bg-emerald-500 text-white rounded-md cursor-pointer shadow-sm';

    activeConfirmCallback = callback;
    document.getElementById('confirmModal').classList.remove('hidden');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.add('hidden');
    activeConfirmCallback = null;
}

document.getElementById('confirmActionBtn').addEventListener('click', function() {
    const callback = activeConfirmCallback;
    closeConfirmModal();
    if (typeof callback === 'function') callback();
});

function toggleInitiatorPassword() {
    const input = document.getElementById('initiator_password');
    const icon = document.getElementById('initiatorPasswordEye');
    const showing = input.type === 'text';

    input.type = showing ? 'password' : 'text';
    icon.className = showing ? 'fa-solid fa-eye' : 'fa-solid fa-eye-slash';
}

function resetForm() {
    document.getElementById('gatewayForm').reset();
    document.getElementById('gateway_id').value = '';
    document.getElementById('initiator_password').type = 'password';
    document.getElementById('initiatorPasswordEye').className = 'fa-solid fa-eye';
    document.getElementById('modalMessage').classList.add('hidden');
}

function openModal() {
    resetForm();
    document.getElementById('sync_target').value = 'c2b';
    document.getElementById('modalTitle').textContent = 'Add New Gateway';
    document.getElementById('gatewayModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('gatewayModal').classList.add('hidden');
}

function editGateway(gw) {
    resetForm();

    document.getElementById('gateway_id').value = gw.id || '';
    document.getElementById('gateway_name').value = gw.gateway_name || '';
    document.getElementById('shortcode').value = gw.dec_shortcode || '';
    document.getElementById('consumer_key').value = gw.dec_consumer_key || '';
    document.getElementById('consumer_secret').value = gw.dec_consumer_secret || '';
    document.getElementById('passkey').value = gw.dec_passkey || '';
    document.getElementById('initiator_username').value = gw.dec_initiator_username || '';
    document.getElementById('initiator_password').value = gw.dec_initiator_password || '';

    // Default to B2C when editing an inactive gateway.
    document.getElementById('sync_target').value =
        Number(gw.is_active) === 1 ? 'c2b' : 'b2c';

    document.getElementById('modalTitle').textContent =
        'Edit Gateway: ' + (gw.gateway_name || '');

    document.getElementById('gatewayModal').classList.remove('hidden');
}

function toggleReveal(btn) {
    const card = btn.closest('.relative');
    const spans = card.querySelectorAll('.secret-view');
    const icon = btn.querySelector('i');
    const revealing = icon.classList.contains('fa-eye');

    spans.forEach(span => {
        span.textContent = revealing
            ? (span.getAttribute('data-real') || 'Not set')
            : (span.getAttribute('data-masked') || '••••••••');
    });

    icon.classList.toggle('fa-eye', !revealing);
    icon.classList.toggle('fa-eye-slash', revealing);
    btn.classList.toggle('text-teal-400', revealing);
}

async function postAction(payload) {
    const response = await fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
    });

    const result = await response.json();

    if (!response.ok) {
        throw new Error(result.message || 'Request failed.');
    }

    return result;
}

async function toggleActivate(id, status) {
    // Activation updates C2B STK settings. The B2C setup can be updated
    // separately from the gateway edit form, including for inactive accounts.
    try {
        const result = await postAction({
            action: 'toggle_activate',
            id,
            status,
            sync_target: 'c2b'
        });

        if (result.success) {
            showToast(result.message);
            setTimeout(() => location.reload(), 800);
        } else {
            showAlert('Action Failed', result.message || 'Unable to update gateway.');
        }
    } catch (error) {
        showAlert('Action Failed', error.message || 'Could not complete the request.');
    }
}

async function deleteGateway(id) {
    try {
        const result = await postAction({ action: 'delete', id });

        if (result.success) {
            showToast(result.message);
            setTimeout(() => location.reload(), 800);
        } else {
            showAlert('Action Failed', result.message || 'Unable to delete gateway.');
        }
    } catch (error) {
        showAlert('Action Failed', error.message || 'Could not complete the request.');
    }
}

document.getElementById('gatewayForm').addEventListener('submit', async function(event) {
    event.preventDefault();

    const saveBtn = document.getElementById('saveBtn');
    const messageBox = document.getElementById('modalMessage');
    const originalText = saveBtn.textContent;

    saveBtn.disabled = true;
    saveBtn.textContent = 'Saving...';
    messageBox.classList.add('hidden');

    const formData = {
        action: 'save',
        id: document.getElementById('gateway_id').value,
        gateway_name: document.getElementById('gateway_name').value,
        shortcode: document.getElementById('shortcode').value,
        consumer_key: document.getElementById('consumer_key').value,
        consumer_secret: document.getElementById('consumer_secret').value,
        passkey: document.getElementById('passkey').value,
        initiator_username: document.getElementById('initiator_username').value,
        initiator_password: document.getElementById('initiator_password').value,
        sync_target: document.getElementById('sync_target').value
    };

    try {
        const result = await postAction(formData);

        if (!result.success) {
            throw new Error(result.message || 'Unable to save gateway.');
        }

        closeModal();
        showToast(result.message);
        setTimeout(() => location.reload(), 800);
    } catch (error) {
        messageBox.className = 'p-2.5 rounded-md text-xs font-bold text-center bg-rose-500/10 text-rose-600 border border-rose-500/20';
        messageBox.textContent = error.message || 'A network error occurred.';
        messageBox.classList.remove('hidden');
    } finally {
        saveBtn.disabled = false;
        saveBtn.textContent = originalText;
    }
});
</script>

<?php include __DIR__ . '/admin-frame/footer.php'; ?>