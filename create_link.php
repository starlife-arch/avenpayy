<?php
// Include buffering and session checks
ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: /login.php"); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower($_POST['slug'] ?? ''));
    
    function uploadFile($file_data, $folder) {
        require_once __DIR__ . '/lib/safe_upload.php';
        $kind = (strpos($folder, 'logos') !== false) ? 'image' : 'document';
        return av_safe_upload($file_data, $folder, $kind);
    }

    $logo_path = uploadFile($_FILES['logo'], 'uploads/logos/');
    $mode = $_POST['redirect_mode'] ?? 'redirect'; 
    $success_url = ($mode === 'redirect') ? ($_POST['success_url'] ?? NULL) : NULL;
    $file_path = ($mode !== 'redirect') ? uploadFile($_FILES['attached_file'], 'uploads/files/') : NULL;
    $success_type = ($mode === 'redirect') ? 'url' : 'download';

    $is_fixed = isset($_POST['is_fixed']) ? 1 : 0;
    $amount = !empty($_POST['amount']) ? (float)$_POST['amount'] : 0.00;
    
    // Explicitly capture and check description input
    $rawDescription = isset($_POST['description']) ? trim($_POST['description']) : '';
    $description = ($rawDescription !== '') ? $rawDescription : 'Your Digital Payment Hub';

    $title = $_POST['title'] ?? '';
    $x_api_key = $_POST['x_api_key'] ?? '';
    $min_amount = (($_POST['min_amount'] ?? '') !== '') ? (float)$_POST['min_amount'] : null;
    $max_amount = (($_POST['max_amount'] ?? '') !== '') ? (float)$_POST['max_amount'] : null;
    $expires_raw = trim($_POST['expires_at'] ?? '');
    $expires_at = ($expires_raw !== '' && strtotime($expires_raw)) ? date('Y-m-d H:i:s', strtotime($expires_raw)) : null;
    $single_use = isset($_POST['single_use']) ? 1 : 0;
    $link_status = (($_POST['status'] ?? 'active') === 'disabled') ? 'disabled' : 'active';
    require_once __DIR__ . '/lib/payment_links.php';
    if (!av_owned_api_key($conn, (string)$x_api_key, (int)$user_id)) { http_response_code(403); exit('Invalid settlement account selected.'); }

    $sql = "INSERT INTO payment_links (user_id, x_api_key, slug, title, description, amount, is_fixed, logo_path, file_path, success_url, redirect_mode, success_type, min_amount, max_amount, expires_at, single_use) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        $error_message = "Prepare failed: " . $conn->error;
    } else {
        $stmt->bind_param("issssdisssssddsi", $user_id, $x_api_key, $slug, $title, $description, $amount, $is_fixed, $logo_path, $file_path, $success_url, $mode, $success_type, $min_amount, $max_amount, $expires_at, $single_use);
        
        try {
            if ($stmt->execute()) {
                header("Location: links.php");
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $error_message = ($e->getCode() == 1062) ? "The Link Path '" . htmlspecialchars($slug) . "' is already taken." : "Database operation failed: " . $e->getMessage();
        }
    }
}

$stmt = $conn->prepare("SELECT x_api_key, merchant_name FROM settlements WHERE user_id = ? AND is_active = 1");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$settlements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
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
    <title>Create Payment Link - AvenPay</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .glass-card { background: rgba(248, 250, 252, 0.95); border: 1px solid rgba(15, 23, 42, 0.08); backdrop-filter: blur(16px); }
        .custom-input { background-color: #ffffff !important; border: 1px solid #e2e8f0; color: #0f172a; width: 100%; padding: 11px 14px; border-radius: 0.75rem; outline: none; transition: all 0.2s; }
        .custom-input:focus { border-color: #0bcf9e; box-shadow: 0 0 0 2px rgba(11, 207, 158, 0.15); }
        input[type="file"]::file-selector-button { background-color: #f1f5f9; color: #1e293b; border: none; padding: 6px 12px; border-radius: 0.5rem; cursor: pointer; margin-right: 12px; transition: background 0.2s; }
        input[type="file"]::file-selector-button:hover { background-color: #e2e8f0; color: #0f172a; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans antialiased min-h-screen flex flex-col justify-between">

<main class="flex-1 flex items-center justify-center p-4 md:p-8 my-8">
    <div class="max-w-xl w-full glass-card rounded-2xl p-6 md:p-8 shadow-2xl relative overflow-hidden">
        
        <!-- LOADING OVERLAY -->
        <div id="uploadLoader" class="absolute inset-0 bg-slate-50/90 backdrop-blur-md z-20 flex flex-col items-center justify-center hidden">
            <div class="w-12 h-12 rounded-2xl bg-teal-500/10 border border-teal-500/30 flex items-center justify-center text-teal-600 mb-3 animate-pulse">
                <i class="fa-solid fa-circle-notch fa-spin text-xl"></i>
            </div>
            <p class="text-slate-900 font-bold text-sm tracking-wide">Generating payment link & uploading files...</p>
            <p class="text-slate-500 text-xs mt-1">Please keep this window open.</p>
        </div>

        <!-- Header -->
        <div class="flex items-center space-x-3 mb-6 border-b border-slate-200 pb-5">
            <div class="w-10 h-10 rounded-xl bg-teal-500/10 border border-teal-500/30 flex items-center justify-center text-teal-600">
                <i class="fa-solid fa-link text-base"></i>
            </div>
            <div>
                <h1 class="text-base font-bold text-slate-900 tracking-wide">Create Payment Link</h1>
                <p class="text-xs text-slate-500 mt-0.5">Generate customized checkout flows & digital file delivery</p>
            </div>
        </div>
        
        <?php if ($error_message): ?>
            <div id="errorBox" class="mb-5 bg-rose-950/50 border border-rose-500/30 text-rose-700 p-3.5 rounded-xl text-xs flex items-center gap-2.5 shadow-lg shadow-rose-950/20 transition-opacity duration-500">
                <i class="fa-solid fa-triangle-exclamation text-rose-600 shrink-0"></i>
                <span class="font-medium"><?= htmlspecialchars($error_message) ?></span>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" autocomplete="off" class="space-y-4" onsubmit="showLoader()">
            
            <!-- Link Path Slug -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Link Path (Slug)</label>
                <div class="flex items-center border border-slate-200 bg-slate-50 rounded-xl overflow-hidden focus-within:border-teal-500 transition">
                    <span class="pl-4 pr-1 text-slate-500 text-xs font-mono">avenpay.co.ke/link/</span>
                    <input type="text" name="slug" required placeholder="e.g. premium-ebook" class="custom-input rounded-l-none border-0 font-mono text-xs text-teal-600">
                </div>
            </div>

            <!-- Title & Amount -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Title</label>
                    <input type="text" name="title" required placeholder="Product or Service Name" class="custom-input text-xs">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">Amount (KES)</label>
                    <input type="number" name="amount" step="0.01" placeholder="0.00" class="custom-input text-xs font-mono">
                </div>
            </div>

            <!-- Lock Amount Checkbox -->
            <div class="flex items-center bg-white/60 border border-slate-200/80 rounded-xl p-3">
                <label class="relative flex items-center cursor-pointer select-none">
                    <input type="checkbox" name="is_fixed" value="1" class="peer sr-only">
                    <div class="w-4 h-4 bg-slate-50 border border-slate-300 rounded peer-checked:bg-teal-500 peer-checked:border-teal-400 flex items-center justify-center transition">
                        <i class="fa-solid fa-check text-[10px] text-slate-950 opacity-0 peer-checked:opacity-100"></i>
                    </div>
                    <span class="ml-2.5 text-xs font-medium text-slate-600">Lock Amount (Prevent users from editing the price at checkout)</span>
                </label>
            </div>

            <!-- Description -->
            <div class="grid grid-cols-2 gap-3 mb-3">
                <div><label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Min amount (open links)</label><input type="number" name="min_amount" min="1" step="1" placeholder="optional" class="custom-input text-xs"></div>
                <div><label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Max amount (open links)</label><input type="number" name="max_amount" min="1" step="1" placeholder="optional" class="custom-input text-xs"></div>
                <div class="col-span-2"><label class="block text-[10px] font-bold uppercase tracking-wider text-slate-500 mb-1">Expires (optional)</label><input type="datetime-local" name="expires_at" class="custom-input text-xs"></div>
                <label class="col-span-2 flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="single_use" value="1" class="h-4 w-4"> Single use - closes after the first successful payment</label>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Description</label>
                <textarea name="description" rows="2" class="custom-input text-xs resize-none" placeholder="Your Digital Payment Hub"></textarea>
            </div>

            <!-- Brand Logo -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Brand Logo (Optional)</label>
                <input type="file" name="logo" accept="image/*" class="custom-input text-xs text-slate-500">
            </div>

            <!-- After Payment Action -->
            <div class="space-y-3 pt-1">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1.5">After Payment Action</label>
                    <select name="redirect_mode" id="redirect_mode" onchange="toggleInputs()" class="custom-input text-xs">
                        <option value="redirect">Redirect to Web URL</option>
                        <option value="download">Force Download File</option>
                        <option value="inline">Open/View File Inline</option>
                    </select>
                </div>
                <div id="url_field">
                    <input type="url" name="success_url" placeholder="https://your-success-page.com" class="custom-input text-xs font-mono">
                </div>
                <div id="file_field" style="display: none;">
                    <input type="file" name="attached_file" accept=".pdf,.zip,.png,.jpg,.jpeg" class="custom-input text-xs text-slate-500">
                </div>
            </div>

            <!-- Settlement Account Selection -->
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1.5">Settlement Account</label>
                <select name="x_api_key" required class="custom-input text-xs">
                    <?php foreach ($settlements as $s): ?>
                        <option value="<?= htmlspecialchars($s['x_api_key']) ?>"><?= htmlspecialchars($s['merchant_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="w-full mt-2 bg-gradient-to-r from-teal-600 to-teal-600 hover:from-teal-500 hover:to-teal-500 text-white font-black py-3 px-4 rounded-xl transition cursor-pointer flex items-center justify-center gap-2 text-xs shadow-lg shadow-teal-950/50 active:scale-[0.99]">
                <i class="fa-solid fa-circle-plus text-xs"></i> Create Payment Link
            </button>
        </form>
    </div>
</main>

<script>
    const errorBox = document.getElementById('errorBox');
    if (errorBox) {
        setTimeout(() => {
            errorBox.style.opacity = "0";
            setTimeout(() => errorBox.style.display = "none", 500);
        }, 5000);
    }

    function toggleInputs() {
        const mode = document.getElementById('redirect_mode').value;
        document.getElementById('url_field').style.display = (mode === 'redirect') ? 'block' : 'none';
        document.getElementById('file_field').style.display = (mode !== 'redirect') ? 'block' : 'none';
    }

    function showLoader() {
        document.getElementById('uploadLoader').classList.remove('hidden');
    }
</script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>
</body>
</html>