<?php
ob_start();
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: /login.php"); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

$user_id = $_SESSION['user_id'];
$error_message = "";

// --- HANDLE UPDATE LINK (ALL-IN-ONE EDIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    $link_id = (int)$_POST['link_id'];
    $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($_POST['slug']));
    
    function uploadFile($file_data, $folder) {
        require_once __DIR__ . '/lib/safe_upload.php';
        $kind = (strpos($folder, 'logos') !== false) ? 'image' : 'document';
        return av_safe_upload($file_data, $folder, $kind);
    }

    // Fetch existing data to preserve files if not re-uploaded
    $stmt_old = $conn->prepare("SELECT logo_path, file_path FROM payment_links WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt_old->bind_param("ii", $link_id, $user_id);
    $stmt_old->execute();
    $old_data = $stmt_old->get_result()->fetch_assoc();

    $logo_path = uploadFile($_FILES['logo'], 'uploads/logos/') ?: ($old_data['logo_path'] ?? NULL);
    $mode = $_POST['redirect_mode']; 
    $success_url = ($mode === 'redirect') ? $_POST['success_url'] : NULL;
    $file_path = ($mode !== 'redirect') ? (uploadFile($_FILES['attached_file'], 'uploads/files/') ?: ($old_data['file_path'] ?? NULL)) : NULL;
    $success_type = ($mode === 'redirect') ? 'url' : 'download';

    $is_fixed = isset($_POST['is_fixed']) ? 1 : 0;
    $amount = !empty($_POST['amount']) ? (float)$_POST['amount'] : 0.00;
    
    // Unlimited length description logic
    $rawDescription = isset($_POST['description']) ? trim($_POST['description']) : '';
    $description = ($rawDescription !== '') ? $rawDescription : 'Your Digital Payment Hub';

    $title = $_POST['title'];
    $x_api_key = $_POST['x_api_key'];
    $min_amount = (($_POST['min_amount'] ?? '') !== '') ? (float)$_POST['min_amount'] : null;
    $max_amount = (($_POST['max_amount'] ?? '') !== '') ? (float)$_POST['max_amount'] : null;
    $expires_raw = trim($_POST['expires_at'] ?? '');
    $expires_at = ($expires_raw !== '' && strtotime($expires_raw)) ? date('Y-m-d H:i:s', strtotime($expires_raw)) : null;
    $single_use = isset($_POST['single_use']) ? 1 : 0;
    $link_status = (($_POST['status'] ?? 'active') === 'disabled') ? 'disabled' : 'active';
    require_once __DIR__ . '/lib/payment_links.php';
    if (!av_owned_api_key($conn, (string)$x_api_key, (int)$user_id)) { http_response_code(403); exit('Invalid settlement account selected.'); }

    $sql = "UPDATE payment_links SET 
                x_api_key = ?, 
                slug = ?, 
                title = ?, 
                description = ?, 
                amount = ?, 
                is_fixed = ?, 
                logo_path = ?, 
                file_path = ?, 
                success_url = ?, 
                redirect_mode = ?, 
                success_type = ?, 
                min_amount = ?, 
                max_amount = ?, 
                expires_at = ?, 
                single_use = ?, 
                status = ? 
            WHERE id = ? AND user_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssssdisssssddsisii", $x_api_key, $slug, $title, $description, $amount, $is_fixed, $logo_path, $file_path, $success_url, $mode, $success_type, $min_amount, $max_amount, $expires_at, $single_use, $link_status, $link_id, $user_id);
    
    try {
        if ($stmt->execute()) {
            header("Location: /links");
            exit;
        }
    } catch (mysqli_sql_exception $e) {
        $error_message = ($e->getCode() == 1062) ? "The Link Path '" . htmlspecialchars($slug) . "' is already taken." : "Database operation failed.";
    }
}

// --- HANDLE DELETION ---
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    $__ref = strtolower((string) parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_HOST));
    if ($__ref === '' || preg_replace('/^www\./', '', $__ref) !== preg_replace('/^www\./', '', strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '')))) { http_response_code(403); exit('Blocked: delete must be started from the dashboard.'); }
    $stmt = $conn->prepare("DELETE FROM payment_links WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $_GET['id'], $user_id);
    $stmt->execute();
    header("Location: /links");
    exit;
}

// --- FETCH DATA ---
$stmt = $conn->prepare("SELECT * FROM payment_links WHERE user_id = ? ORDER BY id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$links = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Fetch settlements for the edit modal dropdown
$stmt_set = $conn->prepare("SELECT x_api_key, merchant_name FROM settlements WHERE user_id = ? AND is_active = 1");
$stmt_set->bind_param("i", $user_id);
$stmt_set->execute();
$settlements = $stmt_set->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<style>
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

<main class="max-w-5xl mx-auto p-4 md:p-6 relative min-h-screen">
    <div class="relative z-10">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-200/80">
            <div>
                <h1 class="text-sm font-black text-slate-900 tracking-wide flex items-center gap-2">
                    <i class="iclass fa-solid fa-link text-teal-600"></i>
                    <span>Your Payment Links</span>
                </h1>
                <p class="text-[11px] text-slate-500 mt-0.5">Manage digital payment collection links & checkouts</p>
            </div>
            <a href="/create_link" class="self-start sm:self-auto bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2 rounded-xl transition cursor-pointer flex items-center gap-2 text-xs shadow-lg shadow-teal-500/20 active:scale-[0.99]">
                <i class="iclass fa-solid fa-plus text-slate-950"></i>
                <span>Create New Link</span>
            </a>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-rose-500/10 border border-rose-500/30 text-rose-600 p-3 rounded-xl mb-4 text-xs flex items-center gap-2">
                <i class="iclass fa-solid fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Table Container -->
        <div class="bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 rounded-2xl shadow-xl overflow-hidden ring-1 ring-slate-200">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600 min-w-[700px]">
                    <thead class="bg-slate-50/60 text-slate-500 uppercase text-[10px] border-b border-slate-200/80 tracking-wider">
                        <tr>
                            <th class="p-3.5 pl-4">Title</th>
                            <th class="p-3.5">Link Path</th>
                            <th class="p-3.5">Amount</th>
                            <th class="p-3.5 text-center pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80">
                        <?php if (empty($links)): ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-500 text-xs">
                                    <i class="iclass fa-solid fa-link text-3xl text-slate-600 mb-2 block"></i>
                                    No payment links created yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($links as $link): 
                                $full_url = "https://avenpay.co.ke/link/" . htmlspecialchars($link['slug']);
                            ?>
                            <tr class="hover:bg-slate-100/40 transition-colors">
                                <td class="p-3.5 pl-4 font-bold text-slate-900"><?= htmlspecialchars($link['title']) ?></td>
                                <td class="p-3.5 text-teal-600 font-mono"><?= $full_url ?></td>
                                <td class="p-3.5 font-semibold"><?= $link['is_fixed'] ? 'KES ' . number_format($link['amount'], 0) : '<span class="text-slate-400 font-normal">Variable</span>' ?></td>
                                <td class="p-3.5 pr-4 flex justify-center items-center gap-2.5">
                                    <!-- Copy Link -->
                                    <button onclick="navigator.clipboard.writeText('<?= $full_url ?>'); this.innerHTML='<i class=\'fa-solid fa-check text-emerald-400\'></i>'; setTimeout(() => this.innerHTML='<i class=\'fa-solid fa-copy\'></i>', 1500);" class="w-7 h-7 rounded-lg bg-slate-100/80 border border-slate-300/80 text-slate-500 hover:text-slate-900 flex items-center justify-center transition text-xs" title="Copy Link">
                                        <i class="iclass fa-solid fa-copy"></i>
                                    </button>
                                    <!-- Visit Live Link -->
                                    <a href="/link/<?= htmlspecialchars($link['slug']) ?>" target="_blank" class="w-7 h-7 rounded-lg bg-teal-500/10 border border-teal-500/20 text-teal-600 hover:bg-teal-500/20 flex items-center justify-center transition text-xs" title="View Link">
                                        <i class="iclass fa-solid fa-arrow-up-right-from-square"></i>
                                    </a>
                                    <!-- Open Edit Modal Button -->
                                    <button type="button" onclick="openEditModal(<?= htmlspecialchars(json_encode($link), ENT_QUOTES, 'UTF-8') ?>)" class="w-7 h-7 rounded-lg bg-amber-500/10 border border-amber-500/20 text-amber-600 hover:bg-amber-500/20 flex items-center justify-center transition text-xs" title="Edit Link">
                                        <i class="iclass fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <!-- Delete Link with Confirmation -->
                                    <a href="/links?action=delete&id=<?= $link['id'] ?>" onclick="return confirm('Are you sure you want to delete this payment link?');" class="w-7 h-7 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-600 hover:bg-rose-500/20 flex items-center justify-center transition text-xs" title="Delete Link">
                                        <i class="iclass fa-solid fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- EDIT MODAL POPUP -->
<div id="editModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
    <div class="bg-white border border-slate-200 rounded-2xl w-full max-w-lg p-6 max-h-[90vh] overflow-y-auto shadow-2xl relative ring-1 ring-slate-200">
        
        <!-- LOADING OVERLAY -->
        <div id="uploadLoader" class="absolute inset-0 bg-slate-50/90 backdrop-blur-sm z-10 flex flex-col items-center justify-center hidden rounded-2xl">
            <i class="iclass fa-solid fa-circle-notch fa-spin text-teal-600 text-3xl mb-2"></i>
            <p class="text-slate-900 font-bold text-xs">Uploading files & saving changes...</p>
            <p class="text-slate-500 text-[10px] mt-0.5">Please wait a moment.</p>
        </div>

        <div class="flex justify-between items-center mb-4 pb-3 border-b border-slate-200/80">
            <h2 class="text-xs font-black text-slate-900 uppercase tracking-wider flex items-center gap-2">
                <i class="iclass fa-solid fa-pen-to-square text-teal-600"></i>
                <span>Edit Payment Link</span>
            </h2>
            <button onclick="closeEditModal()" class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 hover:text-slate-900 flex items-center justify-center text-xs transition">
                <i class="iclass fa-solid fa-xmark"></i>
            </button>
        </div>

        <form action="/links" method="POST" enctype="multipart/form-data" autocomplete="off" class="space-y-3.5" onsubmit="showLoader()">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="link_id" id="edit_link_id">

            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Link Path</label>
                <div class="flex items-center border border-slate-200 hover:border-slate-300 bg-slate-50/80 rounded-xl overflow-hidden transition">
                    <span class="pl-3.5 text-slate-500 text-[11px]">avenpay.co.ke/link/</span>
                    <input type="text" name="slug" id="edit_slug" required class="w-full bg-transparent border-0 text-slate-900 text-xs py-2.5 pl-1 pr-3 outline-none font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Title</label>
                    <input type="text" name="title" id="edit_title" required class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2.5 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 transition">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Amount (KES)</label>
                    <input type="number" name="amount" step="0.01" id="edit_amount" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2.5 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 transition font-mono">
                </div>
            </div>

            <label class="flex items-center text-slate-600 font-semibold cursor-pointer text-xs select-none">
                <input type="checkbox" name="is_fixed" id="edit_is_fixed" value="1" class="mr-2 h-4 w-4 rounded accent-teal-500"> Lock Amount
            </label>

            <div class="grid grid-cols-2 gap-3 mb-3">
                <div><label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Min amount</label><input type="number" name="min_amount" id="edit_min_amount" min="1" step="1" class="w-full bg-slate-50/80 border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700"></div>
                <div><label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Max amount</label><input type="number" name="max_amount" id="edit_max_amount" min="1" step="1" class="w-full bg-slate-50/80 border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700"></div>
                <div><label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Expires</label><input type="datetime-local" name="expires_at" id="edit_expires_at" class="w-full bg-slate-50/80 border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700"></div>
                <div><label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Status</label><select name="status" id="edit_status" class="w-full bg-slate-50/80 border border-slate-200 rounded-lg px-3 py-2 text-xs text-slate-700"><option value="active">Active</option><option value="disabled">Disabled</option></select></div>
                <label class="col-span-2 flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="single_use" id="edit_single_use" value="1" class="h-4 w-4"> Single use</label>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Description (Unlimited Length)</label>
                <textarea name="description" id="edit_description" rows="3" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2.5 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 transition resize-y" placeholder="Enter detailed description, instructions, or notes..."></textarea>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Brand Logo (Optional)</label>
                <input type="file" name="logo" accept="image/*" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2 rounded-xl text-xs text-slate-500 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-600 hover:file:bg-slate-200 transition">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">After Payment Action</label>
                <select name="redirect_mode" id="edit_redirect_mode" onchange="toggleModalInputs()" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2.5 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 transition mb-2">
                    <option value="redirect">Redirect to Web URL</option>
                    <option value="download">Force Download</option>
                    <option value="inline">Open/View Inline</option>
                </select>
                <div id="modal_url_field">
                    <input type="url" name="success_url" id="edit_success_url" placeholder="https://..." class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2.5 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 transition">
                </div>
                <div id="modal_file_field" style="display: none;">
                    <input type="file" name="attached_file" accept=".pdf,.zip" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2 rounded-xl text-xs text-slate-500 file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-100 file:text-slate-600 hover:file:bg-slate-200 transition">
                </div>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Settlement Account</label>
                <select name="x_api_key" id="edit_x_api_key" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2.5 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 transition">
                    <?php foreach ($settlements as $s): ?>
                        <option value="<?= htmlspecialchars($s['x_api_key']) ?>"><?= htmlspecialchars($s['merchant_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex gap-2 pt-3">
                <button type="button" onclick="closeEditModal()" class="w-1/3 bg-slate-100 border border-slate-300/80 hover:bg-slate-200 py-2.5 font-bold text-slate-600 rounded-xl text-xs transition">Cancel</button>
                <button type="submit" class="w-2/3 bg-teal-500 hover:bg-teal-400 py-2.5 font-black text-slate-950 rounded-xl text-xs transition shadow-lg shadow-teal-500/20">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(link) {
    document.getElementById('edit_link_id').value = link.id;
    document.getElementById('edit_slug').value = link.slug;
    document.getElementById('edit_title').value = link.title;
    document.getElementById('edit_amount').value = link.amount;
    document.getElementById('edit_min_amount').value = link.min_amount || '';
    document.getElementById('edit_max_amount').value = link.max_amount || '';
    document.getElementById('edit_expires_at').value = link.expires_at ? link.expires_at.replace(' ', 'T').slice(0, 16) : '';
    document.getElementById('edit_single_use').checked = (link.single_use == 1);
    document.getElementById('edit_status').value = link.status || 'active';
    document.getElementById('edit_is_fixed').checked = (link.is_fixed == 1);
    document.getElementById('edit_description').value = link.description || '';
    document.getElementById('edit_redirect_mode').value = link.redirect_mode || 'redirect';
    document.getElementById('edit_success_url').value = link.success_url || '';
    document.getElementById('edit_x_api_key').value = link.x_api_key;

    toggleModalInputs();
    document.getElementById('editModal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
}

function toggleModalInputs() {
    const mode = document.getElementById('edit_redirect_mode').value;
    document.getElementById('modal_url_field').style.display = (mode === 'redirect') ? 'block' : 'none';
    document.getElementById('modal_file_field').style.display = (mode !== 'redirect') ? 'block' : 'none';
}

function showLoader() {
    document.getElementById('uploadLoader').classList.remove('hidden');
}
</script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>