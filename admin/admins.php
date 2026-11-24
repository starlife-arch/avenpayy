<?php
// admin/admins.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

// Authentication check for admin session
if (!isset($_SESSION['admin_id'])) {
    header("Location: /admin/");
    exit();
}

$page_title = "Admin Accounts";
$current_admin_id = (int)$_SESSION['admin_id'];

$success_message = "";
$error_message = "";

$edit_id = 0;
$edit_data = [
    'username' => '',
    'email'    => ''
];

// --- HANDLE EDIT POPULATE ---
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id, username, email FROM admins WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $edit_data = $res->fetch_assoc();
    }
    $stmt->close();
}

// --- HANDLE SAVE (CREATE / UPDATE) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_admin') {
    $id       = (int)($_POST['admin_id'] ?? 0);
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($email)) {
        $error_message = "Username and Email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        if ($id > 0) {
            // Update existing admin account
            if (!empty($password)) {
                // Update with new password
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE admins SET username = ?, email = ?, password = ? WHERE id = ?");
                $stmt->bind_param("sssi", $username, $email, $hashed_password, $id);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("UPDATE admins SET username = ?, email = ? WHERE id = ?");
                $stmt->bind_param("ssi", $username, $email, $id);
            }
            $msg = "Admin account updated successfully.";
        } else {
            // Create new admin account
            if (empty($password)) {
                $error_message = "Password is required for new admin accounts.";
            } else {
                // Check if username or email already exists
                $chk = $conn->prepare("SELECT id FROM admins WHERE username = ? OR email = ? LIMIT 1");
                $chk->bind_param("ss", $username, $email);
                $chk->execute();
                if ($chk->get_result()->num_rows > 0) {
                    $error_message = "Username or Email already in use.";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $conn->prepare("INSERT INTO admins (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->bind_param("sss", $username, $email, $hashed_password);
                    $msg = "New admin account created successfully.";
                }
                $chk->close();
            }
        }

        if (empty($error_message) && isset($stmt) && $stmt->execute()) {
            $_SESSION['success'] = $msg;
            header("Location: /admin/admins.php");
            exit();
        } elseif (empty($error_message)) {
            $error_message = "Database error: Could not save admin account.";
        }
        if (isset($stmt)) $stmt->close();
    }
}

// --- HANDLE DELETE ---
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $del_id = (int)$_GET['id'];
    
    // Prevent self-deletion
    if ($del_id === $current_admin_id) {
        $_SESSION['error'] = "Action denied: You cannot delete your own logged-in account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM admins WHERE id = ?");
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = "Admin account removed successfully.";
    }
    header("Location: /admin/admins.php");
    exit();
}

if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error_message = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Fetch all registered admins
$result_admins = $conn->query("SELECT id, username, email, created_at FROM admins ORDER BY id ASC");
$admins = $result_admins ? $result_admins->fetch_all(MYSQLI_ASSOC) : [];

// Include admin header via admin-frame folder
include_once __DIR__ . '/admin-frame/header.php'; 
?>

<!-- BREADCRUMB & HEADER TITLE -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5 pb-4 border-b border-inherit">
    <div>
        <h1 class="text-sm font-black tracking-wide text-slate-800 dark:text-white uppercase flex items-center gap-2">
            <i class="iclass fa-solid fa-users-gear text-teal-600 dark:text-teal-400"></i>
            <span>System Administrator Accounts</span>
        </h1>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">Manage root access credentials and administrative privileges</p>
    </div>
</div>

<?php if ($success_message): ?>
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 p-3 rounded-sm text-xs flex items-center gap-2 mb-4">
        <i class="iclass fa-solid fa-circle-check"></i>
        <span><?= htmlspecialchars($success_message) ?></span>
    </div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 p-3 rounded-sm text-xs flex items-center gap-2 mb-4">
        <i class="iclass fa-solid fa-triangle-exclamation"></i>
        <span><?= htmlspecialchars($error_message) ?></span>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- ADD / EDIT ADMIN FORM CARD -->
    <div class="lg:col-span-1 dash-card rounded-sm p-5 h-fit">
        <div class="flex items-center justify-between mb-4 pb-2 border-b border-inherit">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-2">
                <i class="iclass fa-solid <?= $edit_id > 0 ? 'fa-pen-to-square text-amber-500' : 'fa-user-plus text-teal-500' ?>"></i> 
                <?= $edit_id > 0 ? 'Edit Administrator' : 'Add New Administrator' ?>
            </h2>
            <?php if ($edit_id > 0): ?>
                <a href="/admin/admins.php" class="text-[10px] text-rose-500 hover:underline">Cancel</a>
            <?php endif; ?>
        </div>

        <form action="/admin/admins.php" method="POST" class="space-y-3.5">
            <input type="hidden" name="action" value="save_admin">
            <input type="hidden" name="admin_id" value="<?= $edit_id ?>">

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($edit_data['username']) ?>" placeholder="e.g. KentahKE" required class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-3 py-2 rounded-sm text-xs text-slate-800 dark:text-slate-100 outline-none focus:border-teal-500 transition">
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($edit_data['email']) ?>" placeholder="admin@avenpay.co.ke" required class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-3 py-2 rounded-sm text-xs text-slate-800 dark:text-slate-100 outline-none focus:border-teal-500 transition">
            </div>

            <div>
                <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-1">
                    <?= $edit_id > 0 ? 'New Password (Leave blank to keep current)' : 'Account Password' ?>
                </label>
                <input type="password" name="password" <?= $edit_id == 0 ? 'required' : '' ?> placeholder="••••••••••••" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-3 py-2 rounded-sm text-xs text-slate-800 dark:text-slate-100 font-mono outline-none focus:border-teal-500 transition">
            </div>

            <button type="submit" class="w-full mt-2 <?= $edit_id > 0 ? 'bg-amber-500 hover:bg-amber-600' : 'bg-teal-600 hover:bg-teal-500' ?> text-white font-bold py-2 px-4 rounded-sm transition cursor-pointer flex items-center justify-center gap-2 text-xs">
                <i class="iclass fa-solid <?= $edit_id > 0 ? 'fa-pen-to-square' : 'fa-save' ?>"></i> 
                <?= $edit_id > 0 ? 'Update Administrator' : 'Create Administrator' ?>
            </button>
        </form>
    </div>

    <!-- LIST OF REGISTERED ADMINS -->
    <div class="lg:col-span-2 dash-card rounded-sm shadow-xl overflow-hidden">
        <div class="p-4 border-b border-inherit flex items-center justify-between">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-800 dark:text-slate-200 flex items-center gap-2">
                <i class="iclass fa-solid fa-list text-teal-600 dark:text-teal-400"></i> Active Administrators
            </h2>
            <span class="text-[10px] bg-teal-500/10 border border-teal-500/20 text-teal-600 dark:text-teal-400 px-2 py-0.5 rounded-sm font-mono uppercase">
                <?= count($admins) ?> Accounts
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100/80 dark:bg-slate-950/60 text-slate-500 dark:text-slate-400 uppercase text-[10px] border-b border-inherit tracking-wider">
                    <tr>
                        <th class="p-3 pl-4">ID</th>
                        <th class="p-3">Administrator</th>
                        <th class="p-3">Email Address</th>
                        <th class="p-3">Registered Date</th>
                        <th class="p-3 text-center pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-inherit">
                    <?php foreach ($admins as $adm): ?>
                    <tr class="hover:bg-slate-100/50 dark:hover:bg-slate-900/40 transition-colors <?= $edit_id == $adm['id'] ? 'bg-teal-500/10' : '' ?>">
                        <td class="p-3 pl-4 font-mono text-slate-500 dark:text-slate-400">
                            #<?= $adm['id'] ?>
                        </td>
                        <td class="p-3 font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <div class="w-6 h-6 rounded-sm bg-teal-500/10 text-teal-600 dark:text-teal-400 border border-teal-500/20 flex items-center justify-center font-bold text-[10px]">
                                <?= strtoupper(substr($adm['username'], 0, 1)) ?>
                            </div>
                            <span><?= htmlspecialchars($adm['username']) ?></span>
                            <?php if ($adm['id'] === $current_admin_id): ?>
                                <span class="text-[9px] bg-teal-500/10 border border-teal-500/30 text-teal-600 dark:text-teal-400 px-1.5 py-0.2 rounded-sm font-bold uppercase">You</span>
                            <?php endif; ?>
                        </td>
                        <td class="p-3 text-slate-600 dark:text-slate-300 font-mono text-[11px]">
                            <?= htmlspecialchars($adm['email']) ?>
                        </td>
                        <td class="p-3 text-slate-500 dark:text-slate-400 font-mono text-[11px]">
                            <?= date('M d, Y H:i', strtotime($adm['created_at'])) ?>
                        </td>
                        <td class="p-3 pr-4 text-center flex items-center justify-center gap-1.5">
                            <a href="/admin/admins.php?action=edit&id=<?= $adm['id'] ?>" class="w-7 h-7 rounded-sm bg-teal-500/10 border border-teal-500/20 text-teal-600 dark:text-teal-400 hover:bg-teal-500/20 inline-flex items-center justify-center transition text-xs" title="Edit Admin">
                                <i class="iclass fa-solid fa-pen"></i>
                            </a>
                            <?php if ($adm['id'] !== $current_admin_id): ?>
                                <a href="/admin/admins.php?action=delete&id=<?= $adm['id'] ?>" onclick="return confirm('Permanently delete administrator account <?= htmlspecialchars($adm['username']) ?>?');" class="w-7 h-7 rounded-sm bg-rose-500/10 border border-rose-500/20 text-rose-500 hover:bg-rose-500/20 inline-flex items-center justify-center transition text-xs" title="Delete Admin">
                                    <i class="iclass fa-solid fa-trash"></i>
                                </a>
                            <?php else: ?>
                                <span class="w-7 h-7 rounded-sm bg-slate-200 dark:bg-slate-800 text-slate-400 inline-flex items-center justify-center text-xs opacity-50 cursor-not-allowed" title="Cannot delete current account">
                                    <i class="iclass fa-solid fa-lock"></i>
                                </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<?php 
// Include admin footer via admin-frame folder
include_once __DIR__ . '/admin-frame/footer.php'; 
?>