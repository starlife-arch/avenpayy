<?php
ob_start(); // Starts buffering
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id'])) { header("Location: /login.php"); exit; }

require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
require_once __DIR__ . '/lib/webhooks.php';

$user_id = $_SESSION['user_id'];
$successMessage = '';

// Check for redirect status message
if (isset($_GET['status']) && $_GET['status'] === 'success') {
    $successMessage = "Webhook URL updated successfully.";
} elseif (isset($_GET['status']) && $_GET['status'] === 'invalid') {
    $successMessage = "Not saved: " . htmlspecialchars((string)($_GET['msg'] ?? 'invalid URL'), ENT_QUOTES, 'UTF-8');
}

// Handle AJAX Request for Live URL Testing with strict AvenPay documentation validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'test_webhook') {
    header('Content-Type: application/json');
    $test_url = filter_var($_POST['test_url'], FILTER_VALIDATE_URL);
    
    // 1. Enforce strict HTTPS URL validation
    if (!$test_url || !preg_match('/^https:\/\//i', $test_url)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid URL. Must be a secure public URL starting with https://']);
        exit;
    }

    $guard = av_webhook_target($test_url);
    if (is_string($guard)) { echo json_encode(['status' => 'error', 'message' => $guard]); exit; }
    $test_acct = intval($_POST['account_id'] ?? 0);
    $test_secret = $test_acct ? av_webhook_secret($test_acct, (int)$user_id) : 'whsec_test';

    // 2. Build official AvenPay test payload structure matching callback documentation
    $test_payload = json_encode([
        'event' => 'webhook.test',
        'checkout_id' => 'TEST_CHK_' . time(),
        'status' => 'SUCCESS',
        'mpesa_receipt' => 'TEST_REC998877',
        'amount' => '100.00',
        'phone' => '254700000000',
        'result_code' => 0
    ]);

    $ch = curl_init($test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $test_payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-AvenPay-Signature: ' . av_webhook_signature($test_secret, $test_payload)
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Prevent open redirects
    $__t = av_webhook_target($test_url);
    curl_setopt($ch, CURLOPT_RESOLVE, [$__t['host'] . ':' . $__t['port'] . ':' . $__t['ip']]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        echo json_encode(['status' => 'error', 'message' => 'Connection failed: Unable to reach endpoint.']);
    } elseif ($http_status === 200) {
        // 3. Validate that the endpoint response matches AvenPay documentation expectations (must return valid JSON acknowledgment)
        $decoded_response = json_decode($response, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            echo json_encode(['status' => 'success', 'message' => "Success! Endpoint matches AvenPay specs and responded with HTTP 200 OK."]);
        } else {
            echo json_encode(['status' => 'warning', 'message' => "Endpoint returned HTTP 200, but response body is not valid JSON as required by AvenPay docs."]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => "Endpoint failed AvenPay specifications. Returned HTTP status: $http_status (Expected 200 OK)."]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_webhook'])) {
    $account_id = intval($_POST['account_id']);
    $url = filter_var($_POST['webhook_url'], FILTER_SANITIZE_URL);
    if ($url !== '') {
        $guard = av_webhook_target($url);
        if (is_string($guard)) { header('Location: webhook.php?status=invalid&msg=' . rawurlencode($guard)); exit; }
    }

    $updateStmt = $conn->prepare("UPDATE settlements SET webhook_url = ? WHERE id = ? AND user_id = ?");
    $updateStmt->bind_param("sii", $url, $account_id, $user_id);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Redirect using PRG pattern to prevent form resubmission on refresh
    header("Location: webhook.php?status=success");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <link rel="icon" href="/brand/avenpay-icon.svg?v=3" sizes="any">
    <link rel="icon" href="/icon.png?v=3" type="image/png">
    <link rel="apple-touch-icon" href="/icon.png?v=3">
    <meta name="theme-color" content="#ffffff">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Webhooks - AvenPay</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #ffffff; }
    </style>
</head>
<body class="text-slate-200 flex flex-col min-h-screen selection:bg-teal-500 selection:text-white">

    <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/frame/header.php'; ?>

    <main class="flex-grow max-w-4xl w-full mx-auto p-6 md:p-8 mt-6">
        
        <!-- Page Header -->
        <div class="mb-8 border-b border-slate-200/80 pb-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-3">
                    <div class="w-10 h-10 rounded-md bg-teal-500/10 border border-teal-500/20 flex items-center justify-center text-teal-600">
                        <i class="fa-solid fa-code-branch text-lg"></i>
                    </div>
                    Webhook Configuration
                </h1>
                <p class="text-slate-500 mt-1.5 text-xs md:text-sm">Configure real-time event listeners for your production settlement accounts.</p>
            </div>
        </div>

        <!-- Success Notification Banner -->
        <?php if (!empty($successMessage)): ?>
            <div id="successNotice" class="mb-6 bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 p-4 rounded-md text-xs md:text-sm flex items-center justify-between transition-all duration-500 shadow-xl shadow-emerald-500/5 backdrop-blur-md">
                <div class="flex items-center gap-3">
                    <div class="w-7 h-7 rounded bg-emerald-500/20 flex items-center justify-center text-emerald-600 shrink-0">
                        <i class="fa-solid fa-circle-check text-sm"></i>
                    </div>
                    <span class="font-medium"><?php echo htmlspecialchars($successMessage); ?></span>
                </div>
                <button onclick="document.getElementById('successNotice').remove()" class="text-emerald-600 hover:text-emerald-800 cursor-pointer p-1">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>
        <?php endif; ?>

        <!-- Info Callout Box -->
        <div class="bg-gradient-to-r from-teal-950/40 via-white/60 to-white/80 border border-teal-500/20 p-5 rounded-md mb-8 flex items-start gap-4 relative overflow-hidden shadow-xl">
            <div class="absolute top-0 left-0 bottom-0 w-1 bg-teal-500"></div>
            <div class="text-teal-600 text-lg mt-0.5 shrink-0">
                <i class="fa-solid fa-circle-info"></i>
            </div>
            <div class="text-xs md:text-sm text-slate-600 leading-relaxed">
                <h3 class="font-bold text-teal-700 mb-1">How Webhook Delivery Works</h3>
                Upon a successful transaction completion, our server engine securely performs an asynchronous <span class="font-mono text-teal-700 bg-teal-950 px-1.5 py-0.5 rounded border border-teal-500/30">POST</span> request matching AvenPay documentation standards. Ensure your server responds with a <span class="font-mono text-emerald-600 bg-emerald-950 px-1.5 py-0.5 rounded border border-emerald-500/30">200 OK</span> status code and a valid JSON response.
            </div>
        </div>

        <!-- Accounts & Webhook List -->
        <div class="space-y-4">
            <?php
            $stmt = $conn->prepare("SELECT id, merchant_name, webhook_url FROM settlements WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                echo '
                <div class="bg-white/50 border border-slate-200/80 rounded-md p-12 text-center shadow-xl">
                    <div class="w-12 h-12 rounded-md bg-slate-100/50 border border-slate-300/50 flex items-center justify-center text-slate-500 mx-auto mb-3">
                        <i class="fa-solid fa-store-slash text-lg"></i>
                    </div>
                    <h3 class="text-sm font-bold text-slate-600">No Settlement Accounts Found</h3>
                    <p class="text-xs text-slate-500 mt-1">You need an active settlement profile to configure webhooks.</p>
                </div>';
            }

            while ($row = $result->fetch_assoc()) {
                $merchantName = htmlspecialchars($row['merchant_name']);
                $webhookUrl = htmlspecialchars($row['webhook_url'] ?? '');
                $accountId = intval($row['id']);

                echo "
                <div class='bg-white/90 border border-slate-200/90 rounded-md p-5 md:p-6 hover:border-slate-300 transition-all shadow-2xl relative overflow-hidden group'>
                    <div class='absolute top-0 left-0 w-full h-[2px] bg-gradient-to-r from-transparent via-teal-500/40 to-transparent opacity-0 group-hover:opacity-100 transition'></div>
                    <form method='POST' class='flex flex-col gap-4'>
                        <input type='hidden' name='account_id' value='{$accountId}'>
                        
                        <div class='flex flex-col md:flex-row md:items-center justify-between gap-4'>
                            <div class='flex items-center gap-3'>
                                <div>
                                    <h3 class='font-black text-slate-900 text-base md:text-lg tracking-tight'>{$merchantName}</h3>
                                <p class='text-[10px] text-slate-500 mt-1'>Signing secret: <code class='text-amber-700 select-all'>" . htmlspecialchars(av_webhook_secret((int)$accountId, (int)$user_id)) . "</code></p>
                                    <span class='text-[10px] text-slate-500 uppercase tracking-widest font-bold'>Settlement Account</span>
                                </div>
                            </div>
                        </div>

                        <div class='flex flex-col md:flex-row items-stretch md:items-center gap-3'>
                            <div class='relative flex-1'>
                                <span class='absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500'>
                                    <i class='fa-solid fa-link text-xs'></i>
                                </span>
                                <input type='url' id='url_{$accountId}' name='webhook_url' value='{$webhookUrl}' 
                                       placeholder='https://your-domain.com/callback' 
                                       class='w-full bg-slate-50/80 border border-slate-200 rounded-md pl-10 pr-3.5 py-3 text-xs md:text-sm text-slate-700 placeholder-slate-600 focus:outline-none focus:border-teal-500 focus:ring-1 focus:ring-teal-500 transition font-mono'>
                            </div>

                            <div class='flex items-center gap-2 shrink-0'>
                                <button type='button' onclick='testWebhookUrl({$accountId})' class='bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-300/60 py-3 px-4 rounded-md text-xs md:text-sm font-bold transition cursor-pointer flex items-center justify-center gap-2 shadow-sm'>
                                    <i class='fa-solid fa-satellite-dish text-teal-600 text-xs'></i> Test Link
                                </button>
                                <button type='submit' name='update_webhook' class='bg-teal-600 hover:bg-teal-500 active:bg-teal-700 py-3 px-5 rounded-md text-xs md:text-sm font-bold text-white transition cursor-pointer flex items-center justify-center gap-2 shadow-md shadow-teal-600/20'>
                                    <i class='fa-solid fa-floppy-disk text-teal-800 text-xs'></i> Save
                                </button>
                            </div>
                        </div>
                        <div id='test_result_{$accountId}' class='text-xs font-medium hidden mt-1'></div>
                    </form>
                </div>";
            }
            $stmt->close();
            ?>
        </div>
    </main>

    <?php require_once $_SERVER['DOCUMENT_ROOT'] . '/frame/footer.php'; ?>

    <script>
    // AJAX Live Webhook Test Function matching AvenPay standards
    function testWebhookUrl(accountId) {
        const urlInput = document.getElementById('url_' + accountId);
        const resultDiv = document.getElementById('test_result_' + accountId);
        const targetUrl = urlInput.value.trim();

        resultDiv.classList.remove('hidden', 'text-emerald-600', 'text-rose-600', 'text-amber-600');
        
        if (!targetUrl) {
            resultDiv.classList.add('text-rose-600');
            resultDiv.innerHTML = "<i class='fa-solid fa-triangle-exclamation mr-1'></i> Please enter a URL first.";
            return;
        }

        resultDiv.classList.add('text-amber-600');
        resultDiv.innerHTML = "<i class='fa-solid fa-spinner fa-spin mr-1'></i> Testing AvenPay specs...";

        const formData = new URLSearchParams();
        formData.append('action', 'test_webhook');
        formData.append('test_url', targetUrl);

        fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            resultDiv.classList.remove('text-amber-600');
            if (data.status === 'success') {
                resultDiv.classList.add('text-emerald-600');
                resultDiv.innerHTML = `<i class='fa-solid fa-circle-check mr-1'></i> ${data.message}`;
            } else if (data.status === 'warning') {
                resultDiv.classList.add('text-amber-600');
                resultDiv.innerHTML = `<i class='fa-solid fa-triangle-exclamation mr-1'></i> ${data.message}`;
            } else {
                resultDiv.classList.add('text-rose-600');
                resultDiv.innerHTML = `<i class='fa-solid fa-circle-xmark mr-1'></i> ${data.message}`;
            }
        })
        .catch(error => {
            resultDiv.classList.remove('text-amber-600');
            resultDiv.classList.add('text-rose-600');
            resultDiv.innerHTML = "<i class='fa-solid fa-circle-xmark mr-1'></i> Test request failed.";
        });
    }

    // Automatically fade out success banner after 5 seconds
    window.addEventListener('DOMContentLoaded', () => {
        const notice = document.getElementById('successNotice');
        if (notice) {
            setTimeout(() => {
                notice.style.opacity = '0';
                setTimeout(() => {
                    notice.remove();
                    if (window.history.replaceState) {
                        const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                        window.history.replaceState({path: cleanUrl}, '', cleanUrl);
                    }
                }, 500);
            }, 5000);
        }
    });
    </script>
</body>
</html>
