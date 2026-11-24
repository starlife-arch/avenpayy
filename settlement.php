<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// --- HAKIKISHA AJAX ENDPOINT HANDLER (MUST BE AT THE VERY TOP) ---
if (isset($_GET['verify_shortcode'])) {
    error_reporting(0);
    @ini_set('display_errors', 0);
    
    header('Content-Type: application/json');
    
    $identifier = trim($_GET['verify_shortcode']);
    $type        = trim($_GET['type'] ?? '4'); // '4' for Paybill, '2' for Till

    if (empty($identifier)) {
        echo json_encode([
            "ResponseCode" => "5000", 
            "ResponseMessage" => "Please check the shortcode and try again."
        ]);
        exit;
    }

    try {
        require_once __DIR__ . '/hakikisha/config.php';
        require_once __DIR__ . '/hakikisha/token.php';

        $accessToken = getAccessToken();
        
        if (empty($accessToken)) {
            echo json_encode([
                "ResponseCode" => "5000", 
                "ResponseMessage" => "Please check the shortcode and try again."
            ]);
            exit;
        }

        $url = MPESA_BASE_URL . '/sfcverify/v1/query/info';
        $payload = [
            "IdentifierType" => (string)$type,
            "Identifier"     => (string)$identifier
        ];
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($httpCode !== 200) {
            echo json_encode([
                "ResponseCode" => "5000",
                "ResponseMessage" => "Please check the shortcode and try again."
            ]);
            exit;
        }

        echo $response;

    } catch (Exception $e) {
        echo json_encode([
            "ResponseCode" => "5000",
            "ResponseMessage" => "Please check the shortcode and try again."
        ]);
    }
    exit;
}

// Normal page access checks below
if (!isset($_SESSION['user_id'])) { header("Location: /login.php"); exit; }
require_once __DIR__ . '/frame/header.php'; 
require_once __DIR__ . '/database_config/db.php'; 

$user_id = $_SESSION['user_id'];
$countStmt = $conn->prepare("SELECT COUNT(*) as total FROM settlements WHERE user_id = ?");
$countStmt->bind_param("i", $user_id);
$countStmt->execute();
$totalAccounts = $countStmt->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta name="theme-color" content="#ffffff">
    <meta charset="UTF-8">
    <title>Settlement Accounts - AvenPay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
</head>
<body class="bg-slate-50 text-slate-800 flex flex-col min-h-screen">

    <!-- Notification Toasts -->
    <?php if (isset($_SESSION['success']) || isset($_SESSION['error'])): 
        $message = $_SESSION['success'] ?? $_SESSION['error'];
        $isSuccess = isset($_SESSION['success']);
    ?>
        <div id="toast" class="fixed top-5 right-5 z-[100] <?= $isSuccess ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-400' : 'bg-rose-500/10 border-rose-500/30 text-rose-400' ?> border px-4 py-3 rounded-xl shadow-xl text-xs flex items-center gap-2 backdrop-blur-md transition-opacity duration-500">
            <i class="fa-solid <?= $isSuccess ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
            <span><?= htmlspecialchars($message) ?></span>
        </div>
        <script>
            setTimeout(() => {
                const toast = document.getElementById('toast');
                if(toast) {
                    toast.style.opacity = '0';
                    setTimeout(() => toast.remove(), 500); 
                }
            }, 4000);
        </script>
        <?php unset($_SESSION['success']); unset($_SESSION['error']); endif; ?>

    <main class="flex-grow p-4 md:p-6 relative">
        <!-- Ambient Background Glow Accents -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>

        <div class="max-w-5xl mx-auto relative z-10">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6 pb-4 border-b border-slate-200/80">
                <div>
                    <h1 class="text-sm font-black text-slate-900 tracking-wide flex items-center gap-2">
                        <i class="fa-solid fa-building-columns text-teal-600"></i>
                        <span>Settlement Accounts</span>
                    </h1>
                    <p class="text-[11px] text-slate-500 mt-0.5">Manage paybills, tills, and bank routing accounts</p>
                </div>
                <button onclick="resetModal()" class="self-start sm:self-auto bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-4 py-2 rounded-xl transition cursor-pointer flex items-center gap-2 text-xs shadow-lg shadow-teal-500/20 active:scale-[0.99]">
                    <i class="fa-solid fa-plus text-slate-950"></i>
                    <span>New Settlement</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php
            $stmt = $conn->prepare("SELECT * FROM settlements WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $user_id);$stmt->execute();
            $data =$stmt->get_result();

            if ($data->num_rows === 0) {
                echo "<div class='col-span-full bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 rounded-2xl p-8 text-center text-slate-400 text-xs shadow-xl'>
                        <i class='fa-solid fa-building-columns text-3xl text-slate-600 mb-2'></i>
                        <p>No settlement accounts configured yet.</p>
                      </div>";
            }

            while ($row =$data->fetch_assoc()) {
                $isActive = ($row['is_active'] == 1);
                $status = strtolower($row['status'] ?? '');
                
                if ($status === 'expired') {$statusLabel = 'Expired';
                    $statusClass = 'bg-rose-500/10 text-rose-600 border-rose-500/20';
                } elseif ($isActive) {
                    $statusLabel = 'Active';$statusClass = 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20';
                } else {
                    $statusLabel = 'Inactive';$statusClass = 'bg-amber-500/10 text-amber-600 border-amber-500/20';
                }

                $isTestMode = (!$isActive && isset($row['stk_test_count']) &&$row['stk_test_count'] > 0);
                $safeRowJson = htmlspecialchars(json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');

                $merchantName = trim($row['merchant_name']);
                $words = explode(' ',$merchantName);
                if (count($words) > 3) {
                    $merchantName = implode(' ', array_slice($words, 0, 2));
                }

                echo "
                <div class='bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 rounded-2xl p-4 shadow-xl flex flex-col justify-between hover:border-slate-300/80 transition group relative overflow-hidden ring-1 ring-slate-200'>
                    <div class='absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-teal-500/40 via-teal-500/40 to-emerald-500/40'></div>
                    <div>
                        <div class='flex justify-between items-start mb-3 pb-3 border-b border-slate-200/80'>
                            <div class='flex items-center gap-2.5'>
                                <div class='w-8 h-8 rounded-xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-600 text-xs shrink-0'>
                                    <i class='fa-solid fa-building-columns'></i>
                                </div>
                                <h3 class='text-xs font-bold text-slate-900 truncate max-w-[140px]'>".htmlspecialchars($merchantName)."</h3>
                            </div>
                            <div class='flex flex-col items-end gap-1'>
                                <span class='px-2 py-0.5 rounded-full text-[9px] font-semibold tracking-wide border {$statusClass}'>{$statusLabel}</span>
                                " . ($isTestMode ? "<span class='px-2 py-0.5 rounded-full text-[9px] font-semibold border bg-slate-100/80 text-slate-500 border-slate-300'>Test Mode</span>" : "") . "
                            </div>
                        </div>
                        
                        <div class='flex items-center gap-2 mb-1.5'>
                            <span class='text-[10px] text-slate-500 font-medium'>".($row['account_number'] ? 'Acc No :' : 'Till No :')."</span>
                            <span class='text-xs font-mono text-teal-600 font-bold'>".($row['account_number'] ?:$row['shortcode'])."</span>
                        </div>";

                        if (!empty($row['expiry_date'])) {
                            $now = time();$expiryTime = strtotime($row['expiry_date']);$diffSeconds = $expiryTime -$now;
                            $remainingDays = ceil($diffSeconds / (60 * 60 * 24));
                            $formattedDate = date('jS, M Y',$expiryTime);

                            if ($remainingDays > 0) {
                                $timeText =$remainingDays . " day" . ($remainingDays == 1 ? "" : "s") . " left ({$formattedDate})";
                                $timeColor = "text-emerald-600";
                            } elseif ($remainingDays == 0) {$timeText = "Expires today ({$formattedDate})";
                                $timeColor = "text-amber-600";
                            } else {
                                $timeText = "Expired " . abs($remainingDays) . " day" . (abs($remainingDays) == 1 ? "" : "s") . " ago ({$formattedDate})";
                                $timeColor = "text-rose-600";
                            }

                            echo "<p class='text-[10px] text-slate-500 mb-3'>Status: <span class='font-semibold {$timeColor}'>{$timeText}</span></p>";
                        } else {
                            echo "<p class='text-[10px] text-slate-500 mb-3'>Status: <span class='font-semibold text-emerald-400'>No Expiry Plan</span></p>";
                        }

                        echo "
                    </div>
                    <div class='pt-3 border-t border-slate-200/80 flex flex-col gap-1.5'>
                        <div class='flex gap-1.5'>";
                        
                        if ($isActive) {
                            echo "
                                <button onclick='editSettlement({$safeRowJson})' class='text-[11px] bg-slate-100/80 border border-slate-300/80 px-2.5 py-1.5 rounded-xl hover:bg-slate-200 hover:text-slate-900 flex-1 font-bold text-slate-600 transition'>Edit</button>
                                <button onclick='openApiModal({$row['id']}, ".(!empty($row['x_api_key']) ? 'true' : 'false').")' class='text-[11px] bg-teal-500/10 border border-teal-500/20 px-2.5 py-1.5 rounded-xl text-teal-600 hover:bg-teal-500/20 flex-1 font-bold transition'>Get API Key</button>
                            </div>
                            <button type='button' onclick=\"showConfirm('Press confirm to renew this account', () => window.location.href='activate?id={$row['id']}')\" class='text-center text-[11px] bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1.5 rounded-xl text-emerald-600 hover:bg-emerald-500/20 w-full font-bold transition'>Renew Subscription</button>";
                        } else {
                            echo "
                                <button onclick='openApiModal({$row['id']}, ".(!empty($row['x_api_key']) ? 'true' : 'false').")' class='text-[11px] bg-teal-500/10 border border-teal-500/20 px-2.5 py-1.5 rounded-xl text-teal-600 hover:bg-teal-500/20 flex-1 font-bold transition'>Test API Key</button>
                                <button onclick='editSettlement({$safeRowJson})' class='text-[11px] bg-slate-100/80 border border-slate-300/80 px-2.5 py-1.5 rounded-xl hover:bg-slate-200 hover:text-slate-900 flex-1 font-bold text-slate-600 transition'>Edit</button>
                            </div>
                            <div class='flex gap-1.5'>
                                <button type='button' onclick=\"showConfirm('Press confirm to proceed', () => window.location.href='activate?id={$row['id']}')\" class='text-center text-[11px] bg-emerald-500/10 border border-emerald-500/20 px-2.5 py-1.5 rounded-xl text-emerald-600 hover:bg-emerald-500/20 flex-1 font-bold transition'>Activate</button>";
                                if ($totalAccounts > 1) {
                                    echo "<button type='button' onclick=\"showConfirm('Deleting this account?', () => window.location.href='delete_settlement.php?id={$row['id']}')\" class='text-center text-[11px] bg-rose-500/10 border border-rose-500/20 px-2.5 py-1.5 rounded-xl text-rose-600 hover:bg-rose-500/20 flex-1 font-bold transition'>Delete</button>";
                                }
                            echo "</div>";
                        }
                        echo "
                    </div>
                </div>";
            }
            ?>
            </div>
        </div>
    </main>

    <?php require_once __DIR__ . '/frame/footer.php'; ?>

    <!-- Custom Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-[100] hidden p-4">
        <div class="bg-white border border-slate-200/80 p-6 rounded-2xl shadow-2xl text-center w-full max-w-xs relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-amber-400 to-rose-400"></div>
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-600 mx-auto mb-3">
                <i class="fa-solid fa-triangle-exclamation text-sm"></i>
            </div>
            <h2 class="text-xs font-extrabold text-slate-900 mb-1">Confirm Action</h2>
            <p id="confirmMessage" class="text-slate-500 text-[11px] mb-5"></p>
            <div class="flex gap-2">
                <button id="confirmCancel" class="flex-1 py-2 rounded-xl bg-slate-100 text-slate-600 font-bold text-xs hover:bg-slate-200 transition">Cancel</button>
                <button id="confirmAction" class="flex-1 py-2 rounded-xl bg-rose-600 text-white font-bold text-xs hover:bg-rose-500 transition shadow-lg shadow-rose-950/30">Confirm</button>
            </div>
        </div>
    </div>

    <!-- API Modal -->
    <div id="apiModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 hidden p-4">
        <div class="bg-white border border-slate-200/80 p-6 rounded-2xl shadow-2xl w-full max-w-sm relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-teal-400 to-teal-400"></div>
            <div class="flex items-center gap-2.5 mb-4 pb-3 border-b border-slate-200/80">
                <div class="w-8 h-8 rounded-xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-600 text-xs">
                    <i class="fa-solid fa-key"></i>
                </div>
                <h2 class="text-xs font-extrabold text-slate-900">API Credentials</h2>
            </div>
            <div id="apiContent" class="space-y-3"></div>
            <button onclick="document.getElementById('apiModal').classList.add('hidden')" class="mt-4 w-full py-2.5 rounded-xl bg-slate-100/80 border border-slate-300/80 text-slate-600 font-bold text-xs hover:bg-slate-200 hover:text-slate-900 transition">Close</button>
        </div>
    </div>

    <!-- Settlement Modal -->
    <div id="settlementModal" class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 hidden p-4">
        <div class="bg-gradient-to-b from-white/90 via-white/95 to-slate-50/90 border border-slate-200/80 p-6 rounded-2xl shadow-2xl w-full max-w-md relative overflow-hidden ring-1 ring-slate-200">
            <div class="absolute top-0 left-0 right-0 h-1 bg-gradient-to-r from-teal-400 via-teal-400 to-emerald-400"></div>
            <div class="flex items-center gap-2.5 mb-5 pb-3 border-b border-slate-200/80">
                <div class="w-8 h-8 rounded-xl bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-600 text-xs">
                    <i class="fa-solid fa-building-columns"></i>
                </div>
                <h2 id="modalTitle" class="text-xs font-black text-slate-900">Add Account</h2>
            </div>
            <form id="settlementForm" action="process_settlement.php" method="POST" class="space-y-3">
                <input type="hidden" name="id" id="hiddenId">
                <input type="hidden" name="type" id="hiddenType" value="paybill">
                
                <div class="flex gap-1 p-1 bg-slate-50/80 border border-slate-200/80 rounded-xl mb-3">
                    <button type="button" onclick="setMode('bank_account')" class="flex-1 py-1.5 rounded-lg text-[11px] font-bold transition" id="btn-bank_account">Bank</button>
                    <button type="button" onclick="setMode('paybill')" class="flex-1 py-1.5 rounded-lg text-[11px] font-bold transition" id="btn-paybill">Paybill</button>
                    <button type="button" onclick="setMode('till')" class="flex-1 py-1.5 rounded-lg text-[11px] font-bold transition" id="btn-till">Till</button>
                </div>

                <div id="bankTrigger" class="mb-3" style="display:none;">
                    <div onclick="openBankList()" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 p-2.5 rounded-xl text-slate-500 cursor-pointer flex justify-between items-center text-xs transition">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-building-columns text-teal-600 text-xs"></i> 
                            <span id="selectedBankText" class="text-[11px]">Search Bank</span>
                        </span>
                        <i class="fa-solid fa-chevron-down text-slate-500 text-[10px]"></i>
                    </div>
                </div>
                
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Shortcode / Paybill / Till</label>
                    <input type="text" name="shortcode" id="m_code" placeholder="Enter Shortcode" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2.5 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 transition font-mono" required>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Organisation Name</label>
                    <input type="text" name="merchant_name" id="m_name" placeholder="Organisation Name" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2.5 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 transition" required>
                </div>
                
                <div id="accNumberContainer">
                    <label class="block text-[11px] font-bold text-slate-600 uppercase tracking-wider mb-1">Account Number</label>
                    <input type="text" name="account_number" id="m_acc" placeholder="Account Number" class="w-full bg-slate-50/80 border border-slate-200/80 hover:border-slate-300 px-3.5 py-2.5 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 transition font-mono">
                </div>

                <!-- Hakikisha Inline Notice Container -->
                <div id="hakikishaResult" class="text-xs hidden"></div>

                <div class="flex gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('settlementModal').classList.add('hidden')" class="w-1/3 py-2.5 rounded-xl bg-slate-100 border border-slate-300/80 text-slate-600 font-bold text-xs hover:bg-slate-200 transition">Cancel</button>
                    <button type="submit" class="w-2/3 py-2.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs transition shadow-lg shadow-emerald-500/10">Save Account</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enhanced Attractive Bank Selection Overlay -->
    <div id="bankListOverlay" class="fixed inset-0 bg-black/90 backdrop-blur-md z-[60] hidden p-4 flex items-center justify-center">
        <div class="max-w-md w-full bg-gradient-to-b from-white via-white to-slate-50 border border-slate-200 rounded-2xl p-6 shadow-2xl relative overflow-hidden ring-1 ring-teal-500/20">
            <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-teal-400 via-teal-400 to-emerald-400"></div>
            
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-teal-500/10 border border-teal-500/30 flex items-center justify-center text-teal-600 text-xs">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <div>
                        <h3 class="text-xs font-black text-slate-900 uppercase tracking-wider">Select Bank Paybill</h3>
                        <p class="text-[10px] text-slate-500">Choose from verified bank routing paybills</p>
                    </div>
                </div>
                <button onclick="closeBankList()" class="w-7 h-7 rounded-lg bg-slate-100 text-slate-500 hover:text-slate-900 flex items-center justify-center text-xs transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="relative mb-3.5">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-teal-600 text-xs">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </div>
                <input type="text" id="bankSearch" onkeyup="filterBanks()" placeholder="Search bank name or shortcode..." class="w-full bg-slate-50 border border-slate-200/90 hover:border-slate-300 p-3 pl-9 rounded-xl text-slate-900 text-xs outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500/30 transition font-medium">
            </div>

            <div id="bankList" class="space-y-2 max-h-[340px] overflow-y-auto pr-1">
                <?php 
                    $res =$conn->query("SELECT bank_name, paybill_number FROM bank_paybills ORDER BY bank_name ASC");
                    while($row =$res->fetch_assoc()) {
                        echo "<button type='button' onclick=\"selectBank('{$row['bank_name']}', '{$row['paybill_number']}')\" class='bank-item w-full text-left p-3.5 bg-slate-50/80 hover:bg-gradient-to-r hover:from-teal-950/40 hover:to-white border border-slate-200/80 hover:border-teal-500/40 rounded-xl text-white transition flex justify-between items-center group shadow-sm'>
                                <div class='flex flex-col gap-0.5'>
                                    <span class='b-name font-bold text-xs text-slate-700 group-hover:text-teal-700 transition'>{$row['bank_name']}</span>
                                    <span class='text-[10px] text-slate-500 font-mono'>Shortcode: <strong class='b-code text-teal-600 font-bold'>{$row['paybill_number']}</strong></span>
                                </div>
                                <span class='text-emerald-600 text-xs font-mono font-bold bg-emerald-500/10 px-2.5 py-1 rounded-lg border border-emerald-500/20 shadow-inner group-hover:border-emerald-500/40 transition'>
                                    {$row['paybill_number']}
                                </span>
                              </button>";
                    }
                ?>
            </div>

            <button onclick="closeBankList()" class="mt-4 w-full py-2.5 bg-slate-100/80 border border-slate-300/80 rounded-xl text-slate-600 font-bold text-xs hover:bg-slate-200 hover:text-slate-900 transition">Cancel</button>
        </div>
    </div>

    <script>
        function showConfirm(message, callback) {
            const modal = document.getElementById('confirmModal');
            document.getElementById('confirmMessage').innerText = message;
            modal.classList.remove('hidden');
            document.getElementById('confirmAction').onclick = () => { modal.classList.add('hidden'); callback(); };
            document.getElementById('confirmCancel').onclick = () => modal.classList.add('hidden');
        }

        function openApiModal(id, hasKey, isNew = false, newKey = null) {
            let content = document.getElementById('apiContent');
            if (isNew && newKey) {
                content.innerHTML = `
                    <p class='text-xs text-slate-400 mb-2'>Copy your new API key below. It will not be shown again.</p>
                    <input type='text' id='apiKeyField' readonly value='${newKey}' class='w-full bg-slate-50/80 p-3 rounded-xl border border-slate-200 font-mono text-teal-400 text-xs mb-3 focus:outline-none'>
                    <button onclick='copyToClipboard(this)' class='w-full py-2.5 bg-teal-500 hover:bg-teal-400 text-slate-950 font-black rounded-xl text-xs transition shadow-lg shadow-teal-500/20'>Copy to Clipboard</button>
                `;
            } else if (hasKey) {
                content.innerHTML = `<p class='text-xs text-slate-500 mb-3'>This account already has an API key. Generating a new one will invalidate the old key.</p>
                                     <a href='generate_api_key.php?id=${id}' class='block w-full py-2.5 bg-rose-500/10 border border-rose-500/20 text-rose-600 rounded-xl text-center font-bold text-xs hover:bg-rose-500/20 transition'>Generate New Key</a>`;
            } else {
                content.innerHTML = `<p class='text-xs text-slate-400 mb-3'>Generate a new API key for this settlement account.</p>
                                     <a href='generate_api_key.php?id=${id}' class='block w-full py-2.5 bg-teal-500 hover:bg-teal-400 text-slate-950 font-black rounded-xl text-center text-xs transition shadow-lg shadow-teal-500/20'>Generate Key</a>`;
            }
            document.getElementById('apiModal').classList.remove('hidden');
        }

        function copyToClipboard(btn) {
            let copyText = document.getElementById("apiKeyField");
            copyText.select();
            document.execCommand("copy");
            let originalText = btn.innerText;
            btn.innerText = "Copied!";
            setTimeout(() => { btn.innerText = originalText; }, 2000);
        }

        <?php if (isset($_SESSION['new_api_key'])): ?>
            window.addEventListener('DOMContentLoaded', () => {
                openApiModal(null, true, true, "<?= $_SESSION['new_api_key'] ?>");
            });
            <?php unset($_SESSION['new_api_key']); ?>
        <?php endif; ?>

        let currentActiveMode = 'paybill';

        function setMode(mode) {
            currentActiveMode = mode;
            let dbType = (mode === 'bank_account') ? 'paybill' : mode;
            document.getElementById('hiddenType').value = dbType;
            
            document.getElementById('bankTrigger').style.display = (mode === 'bank_account') ? 'block' : 'none';
            
            let accInput = document.getElementById('m_acc');
            let accContainer = document.getElementById('accNumberContainer');
            if (mode === 'till') { 
                accContainer.style.display = 'none'; 
                accInput.removeAttribute('required'); 
            } else { 
                accContainer.style.display = 'block'; 
                accInput.setAttribute('required', 'required'); 
            }
            
            ['bank_account', 'paybill', 'till'].forEach(m => {
                let el = document.getElementById('btn-'+m);
                if(el) el.className = 'flex-1 py-1.5 rounded-lg text-[11px] font-bold transition ' + (mode === m ? 'bg-teal-500/20 border border-teal-500/30 text-teal-700' : 'text-slate-500 hover:text-slate-700');
            });
        }
        
        function resetModal() { 
            document.getElementById('settlementForm').reset(); 
            document.getElementById('hiddenId').value = ''; 
            document.getElementById('modalTitle').innerText = "Add Account";
            setMode('paybill');
            document.getElementById('hakikishaResult').classList.add('hidden');
            document.getElementById('selectedBankText').innerText = "Search Bank";
            document.getElementById('settlementModal').classList.remove('hidden');
        }

        function editSettlement(row) {
            document.getElementById('hiddenId').value = row.id;
            document.getElementById('modalTitle').innerText = "Edit Account";
            document.getElementById('m_name').value = row.merchant_name;
            document.getElementById('m_code').value = row.shortcode;
            document.getElementById('m_acc').value = row.account_number || '';
            document.getElementById('selectedBankText').innerText = row.merchant_name;
            
            let mode = (row.type === 'paybill' && row.account_number !== null) ? 'bank_account' : row.type;
            setMode(mode);
            document.getElementById('settlementModal').classList.remove('hidden');
        }

        function openBankList() { document.getElementById('bankListOverlay').classList.remove('hidden'); }
        function closeBankList() { document.getElementById('bankListOverlay').classList.add('hidden'); }
        
        function selectBank(name, code) {
            document.getElementById('selectedBankText').innerText = name;
            document.getElementById('m_name').value = name;
            document.getElementById('m_code').value = code;
            closeBankList();
            verifyShortcodeLive();
        }

        function filterBanks() {
            let input = document.getElementById('bankSearch').value.toLowerCase();
            document.querySelectorAll('.bank-item').forEach(item => {
                let name = item.querySelector('.b-name').innerText.toLowerCase();
                let code = item.querySelector('.b-code').innerText.toLowerCase();
                item.style.display = (name.includes(input) || code.includes(input)) ? '' : 'none';
            });
        }

        // --- CONTEXT-AWARE VERIFICATION ROUTINE WITH AUTO-SWITCH TO TILL ---
        let verificationTimeout = null;

        document.getElementById('m_code').addEventListener('input', function() {
            clearTimeout(verificationTimeout);
            let code = this.value.trim();

            if (code.length >= 5) {
                verificationTimeout = setTimeout(() => {
                    verifyShortcodeLive();
                }, 600);
            }
        });

        function verifyShortcodeLive() {
            let code = document.getElementById('m_code').value.trim();
            let resultBox = document.getElementById('hakikishaResult');

            if (!code || code.length < 5) {
                return;
            }

            resultBox.classList.remove('hidden');
            resultBox.className = "text-xs p-2.5 rounded-xl bg-slate-50/80 border border-slate-200 text-slate-500";
            resultBox.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-1"></i> Checking...';

            let initialTargetType = (currentActiveMode === 'till') ? '2' : '4';

            fetchCodeVerification(code, initialTargetType, function(success, data) {
                if (success) {
                    applySuccess(data, currentActiveMode);
                } else {
                    if (currentActiveMode !== 'till') {
                        fetchCodeVerification(code, '2', function(successTill, dataTill) {
                            if (successTill) {
                                applySuccess(dataTill, 'till');
                            } else {
                                showVerificationError(resultBox);
                            }
                        });
                    } else {
                        fetchCodeVerification(code, '4', function(successPaybill, dataPaybill) {
                            if (successPaybill) {
                                applySuccess(dataPaybill, 'paybill');
                            } else {
                                showVerificationError(resultBox);
                            }
                        });
                    }
                }
            });
        }

        function fetchCodeVerification(code, typeVal, callback) {
            fetch(window.location.pathname + `?verify_shortcode=${encodeURIComponent(code)}&type=${typeVal}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.ResponseCode === '4000') {
                        callback(true, data);
                    } else {
                        callback(false, data);
                    }
                })
                .catch(() => {
                    callback(false, null);
                });
        }

        function applySuccess(data, detectedType) {
            let resultBox = document.getElementById('hakikishaResult');
            document.getElementById('m_name').value = data.OrganizationName;
            setMode(detectedType);

            resultBox.className = "text-xs p-2.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 font-medium";
            resultBox.innerHTML = `<strong>${data.OrganizationName}</strong>`;
        }

        function showVerificationError(resultBox) {
            resultBox.className = "text-xs p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 font-medium";
            resultBox.innerHTML = '<i class="fa-solid fa-triangle-exclamation mr-1"></i> Please check the shortcode and try again.';
        }
    </script>
</body>
</html> 