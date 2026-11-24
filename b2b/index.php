<?php
require_once __DIR__ . '/../lib/require_admin.php';
// ==========================================
// BACKEND API HANDLER (If POST request)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Enable error reporting for debugging
    error_reporting(0);
    ini_set('display_errors', 0);

    // 1. INCLUDE CENTRALIZED DATABASE CONNECTION
    $db_path = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
    if (!file_exists($db_path)) {
        echo json_encode(['success' => false, 'message' => 'Database config file not found at: ' . $db_path]);
        exit;
    }
    require_once $db_path;

    if (!isset($conn) || $conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed or not initialized in db.php']);
        exit;
    }

    // 2. CAPTURE INPUTS
    $inputData          = $_POST;
    $senderShortcode    = trim($inputData['sender_shortcode'] ?? '');
    $recipientShortcode = trim($inputData['recipient_shortcode'] ?? '');
    $amount             = trim($inputData['amount'] ?? '');
    $accountReference   = trim($inputData['account_reference'] ?? 'Payout');
    $payoutType         = trim($inputData['payout_type'] ?? 'PayBill'); // 'PayBill' or 'BuyGoods'

    if (empty($senderShortcode) || empty($recipientShortcode) || empty($amount)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
        exit;
    }

    // 3. FETCH CREDENTIALS FROM `daraja_b2c_settings`
    $stmt = $conn->prepare("SELECT consumer_key, consumer_secret, initiator_name, initiator_password FROM daraja_b2c_settings WHERE shortcode = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database query preparation failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("s", $senderShortcode);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $consumerKey        = $row['consumer_key'];
        $consumerSecret     = $row['consumer_secret'];
        $initiatorName      = $row['initiator_name'];
        $securityCredential = $row['initiator_password'];
    } else {
        echo json_encode(['success' => false, 'message' => 'Shortcode ' . htmlspecialchars($senderShortcode) . ' not found in daraja_b2c_settings database table.']);
        exit;
    }
    $stmt->close();

    // 4. GENERATE OAUTH ACCESS TOKEN (Live Production)
    $authUrl = 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
    $ch = curl_init($authUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . base64_encode("$consumerKey:$consumerSecret")],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);
    $authResponse = json_decode(curl_exec($ch), true);
    curl_close($ch);

    if (empty($authResponse['access_token'])) {
        echo json_encode(['success' => false, 'message' => 'OAuth Authentication Failed with Safaricom', 'debug' => $authResponse]);
        exit;
    }
    $accessToken = $authResponse['access_token'];

    // 5. CONFIGURE B2B PAYLOAD
    $commandID    = ($payoutType === 'BuyGoods') ? 'BusinessBuyGoods' : 'BusinessPayBill';
    $receiverType = ($payoutType === 'BuyGoods') ? '2' : '4'; // 2 for Till, 4 for Paybill
    $currentHost  = $_SERVER['HTTP_HOST'];
    require_once __DIR__ . '/../lib/callback_auth.php';
    $callbackUrl  = av_callback_url('/b2c/payout/b2b_result.php', 'b2b_result');

    $b2bUrl = 'https://api.safaricom.co.ke/mpesa/b2b/v1/paymentrequest';

    $payload = [
        'Initiator'              => $initiatorName,
        'SecurityCredential'     => $securityCredential,
        'CommandID'              => $commandID,
        'SenderIdentifierType'   => '4',
        'RecieverIdentifierType' => $receiverType,
        'Amount'                 => $amount,
        'PartyA'                 => $senderShortcode,
        'PartyB'                 => $recipientShortcode,
        'AccountReference'       => ($commandID === 'BusinessPayBill') ? $accountReference : 'N/A',
        'Remarks'                => 'B2B Payment',
        'QueueTimeOutURL'        => $callbackUrl,
        'ResultURL'              => $callbackUrl
    ];

    // 6. FIRE B2B REQUEST TO DARAJA
    $ch = curl_init($b2bUrl);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $conn->close();

    $decodedResponse = json_decode($response, true);
    
    echo json_encode([
        'success' => ($httpCode >= 200 && $httpCode < 300),
        'http_status' => $httpCode,
        'daraja_response' => $decodedResponse
    ]);
    exit;
}

// ==========================================
// FRONTEND HTML & SHORTCODE AUTO-POPULATION
// ==========================================
$db_path = $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';
$shortcodes = [];
if (file_exists($db_path)) {
    require_once $db_path;
    if (isset($conn) && !$conn->connect_error) {
        $res = $conn->query("SELECT shortcode, initiator_name FROM daraja_b2c_settings");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $shortcodes[] = $row;
            }
        }
        $conn->close();
    }
}
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
    <title>Admin B2B Payout - AvenPay</title>
    <!-- Tailwind CSS for modern visual polish -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex items-center justify-center p-4">

    <div class="w-full max-w-xl bg-white border border-slate-200 rounded-2xl shadow-2xl p-6 sm:p-8">
        <!-- Header -->
        <div class="flex items-center justify-between pb-6 mb-6 border-b border-slate-200">
            <div>
                <h1 class="text-xl font-bold tracking-tight text-slate-900">M-Pesa B2B Payout Engine</h1>
                <p class="text-xs text-slate-500 mt-1">Admin B2B payments via Safaricom Daraja API</p>
            </div>
            <span class="px-3 py-1 text-xs font-medium text-emerald-600 bg-emerald-950/50 border border-emerald-800/50 rounded-full">Production Live</span>
        </div>

        <!-- Payout Form -->
        <form id="b2bForm" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Sender Shortcode (Party A)</label>
                <select name="sender_shortcode" required class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:outline-none focus:border-emerald-500 transition">
                    <option value="">-- Select Your Shortcode --</option>
                    <?php foreach ($shortcodes as $sc): ?>
                        <option value="<?= htmlspecialchars($sc['shortcode']) ?>">
                            <?= htmlspecialchars($sc['shortcode']) ?> (Operator: <?= htmlspecialchars($sc['initiator_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($shortcodes)): ?>
                    <p class="text-xs text-amber-600 mt-1">⚠️ No shortcodes found in `daraja_b2c_settings`. Please check your database connection.</p>
                <?php endif; ?>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Destination Type</label>
                    <select name="payout_type" id="payoutType" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:outline-none focus:border-emerald-500 transition">
                        <option value="PayBill">Paybill Number</option>
                        <option value="BuyGoods">BuyGoods Till</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Recipient Shortcode (Party B)</label>
                    <input type="text" name="recipient_shortcode" required placeholder="Till or Paybill" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:outline-none focus:border-emerald-500 transition">
                </div>
            </div>

            <div id="accountRefWrapper">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Account Reference</label>
                <input type="text" name="account_reference" placeholder="Invoice or Account No." class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:outline-none focus:border-emerald-500 transition">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Amount (KES)</label>
                <input type="number" name="amount" min="1" required placeholder="10" class="w-full bg-slate-50 border border-slate-200 rounded-xl px-4 py-3 text-sm text-slate-900 focus:outline-none focus:border-emerald-500 transition">
            </div>

            <button type="submit" id="submitBtn" class="w-full mt-2 bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-semibold py-3.5 px-4 rounded-xl text-sm transition shadow-lg shadow-emerald-900/20 flex items-center justify-center space-x-2">
                <span>Dispatch B2B Payment</span>
            </button>
        </form>

        <!-- Response Result Box -->
        <div id="resultBox" class="hidden mt-6 p-4 rounded-xl text-xs font-mono bg-slate-50 border border-slate-200 overflow-x-auto max-h-60">
            <pre id="resultContent" class="text-slate-600"></pre>
        </div>
    </div>

    <script>
        // Toggle account reference visibility based on destination type
        const payoutTypeSelect = document.getElementById('payoutType');
        const accountRefWrapper = document.getElementById('accountRefWrapper');

        payoutTypeSelect.addEventListener('change', function() {
            if (this.value === 'BuyGoods') {
                accountRefWrapper.style.display = 'none';
            } else {
                accountRefWrapper.style.display = 'block';
            }
        });

        // AJAX Form Submission
        document.getElementById('b2bForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            const resultBox = document.getElementById('resultBox');
            const resultContent = document.getElementById('resultContent');

            btn.disabled = true;
            btn.innerHTML = `<svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-900 inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...`;
            
            resultBox.classList.add('hidden');

            try {
                const formData = new FormData(this);
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                resultBox.classList.remove('hidden');
                resultContent.textContent = JSON.stringify(data, null, 2);
            } catch (err) {
                resultBox.classList.remove('hidden');
                resultContent.textContent = 'Error executing request: ' + err.message;
            } finally {
                btn.disabled = false;
                btn.innerHTML = 'Dispatch B2B Payment';
            }
        });
    </script>
</body>
</html>