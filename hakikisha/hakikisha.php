<?php
// hakikisha.php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/token.php';

// --- AJAX ENDPOINT HANDLER ---
if (isset($_GET['verify_shortcode'])) {
    header('Content-Type: application/json');
    
    $identifier = trim($_GET['verify_shortcode']);
    $type       = trim($_GET['type'] ?? '4'); // '4' for Paybill, '2' for Till

    if (empty($identifier)) {
        echo json_encode([
            "ResponseCode" => "5000", 
            "ResponseMessage" => "Paybill or Till number is required"
        ]);
        exit;
    }

    try {
        $accessToken = getAccessToken();
        
        if (empty($accessToken)) {
            echo json_encode([
                "ResponseCode" => "5000", 
                "ResponseMessage" => "Failed to retrieve access token from token.php"
            ]);
            exit;
        }

        $url = MPESA_BASE_URL . '/sfcverify/v1/query/info';$payload = [
            "IdentifierType" => (string)$type,    // Dynamically handles Paybill (4) or Till (2)
            "Identifier"     => (string)$identifier
        ];
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL,$url);
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
            $errData = json_decode($response, true);
            echo json_encode([
                "ResponseCode" => "5000",
                "ResponseMessage" => "Gateway Error: " . ($errData['errorMessage'] ?? 'HTTP Status ' .$httpCode)
            ]);
            exit;
        }

        echo $response;

    } catch (Exception $e) {
        echo json_encode([
            "ResponseCode" => "5000",
            "ResponseMessage" => $e->getMessage()
        ]);
    }
    exit;
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
    <title>Account Name Lookup - AvenPay</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-custom { border: none; border-radius: 16px; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.05); background: #ffffff; }
        .btn-green { background-color: #198754; color: white; border-radius: 10px; font-weight: 600; transition: all 0.2s ease-in-out; }
        .btn-green:hover { background-color: #157347; color: white; transform: translateY(-1px); }
        .form-control-custom, .form-select-custom { border-radius: 10px; padding: 12px 16px; border: 1px solid #0f172a; }
        .form-control-custom:focus, .form-select-custom:focus { border-color: #198754; box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.15); }
    </style>
</head>
<body>

<div class="container d-flex justify-content-center align-items-center min-vh-100">
    <div class="col-md-6 col-lg-5">
        <div class="card card-custom p-4 p-md-5">
            
            <!-- Card Header -->
            <div class="text-center mb-4">
                <div class="mb-3 text-success fs-1">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h4 class="fw-bold text-dark mb-1">M-Pesa Hakikisha</h4>
                <p class="text-muted small">Instantly verify any Paybill or Buy Goods Till before sending funds.</p>
            </div>

            <!-- Account Type Selector -->
            <div class="mb-3">
                <label for="identifierType" class="form-label fw-semibold text-secondary small">Account Type</label>
                <select id="identifierType" class="form-select form-select-custom">
                    <option value="4" selected>Paybill (Business PayBill)</option>
                    <option value="2">Buy Goods / Till Number</option>
                </select>
            </div>

            <!-- Input Form Section -->
            <div class="mb-3">
                <label for="shortcodeInput" class="form-label fw-semibold text-secondary small">Shortcode / Number</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 rounded-start-3"><i class="fa-solid fa-building-columns text-muted"></i></span>
                    <input type="text" id="shortcodeInput" class="form-control form-control-custom border-start-0" placeholder="e.g. 4022787" autocomplete="off">
                </div>
            </div>

            <button id="verifyBtn" class="btn btn-green btn-lg w-100 py-3 mt-2 shadow-sm">
                <i class="fa-solid fa-magnifying-glass me-2"></i> Verify Shortcode
            </button>

            <!-- Dynamic Result Card Slot -->
            <div id="resultCard" class="mt-4" style="display: none;"></div>

        </div>
    </div>
</div>

<!-- Frontend JavaScript Interaction Layer -->
<script>
document.getElementById('verifyBtn').addEventListener('click', function() {
    const shortcode = document.getElementById('shortcodeInput').value.trim();
    const type = document.getElementById('identifierType').value;
    const resultCard = document.getElementById('resultCard');
    const btn = document.getElementById('verifyBtn');

    if (!shortcode) {
        alert('Please enter a Paybill or Till number to verify.');
        return;
    }

    // UI State: Loading
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Checking Safaricom...';
    resultCard.style.display = 'block';
    resultCard.innerHTML = `
        <div class="p-3 rounded-4 bg-light text-center border">
            <span class="text-muted small">Querying live infrastructure...</span>
        </div>
    `;

    // AJAX Request to Backend Script
    fetch(`?verify_shortcode=${encodeURIComponent(shortcode)}&type=${type}`)
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-2"></i> Verify Shortcode';

            if (data.ResponseCode === '4000') {
                resultCard.innerHTML = `
                    <div class="p-4 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-25">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fa-solid fa-circle-check text-success fs-5 me-2"></i>
                            <h6 class="fw-bold text-success mb-0">Organization Verified</h6>
                        </div>
                        <hr class="text-success opacity-25 my-2">
                        <ul class="list-unstyled mb-0 small text-dark">
                            <li class="mb-2 d-flex justify-content-between">
                                <span class="text-muted">Name:</span>
                                <strong class="text-success text-end">${data.OrganizationName}</strong>
                            </li>
                            <li class="mb-2 d-flex justify-content-between">
                                <span class="text-muted">Short Code:</span>
                                <strong>${data.OrganizationShortCode}</strong>
                            </li>
                            <li class="d-flex justify-content-between">
                                <span class="text-muted">Charge Profile ID:</span>
                                <span class="badge bg-success">${data.ChargeProfileID}</span>
                            </li>
                        </ul>
                    </div>
                `;
            } else {
                resultCard.innerHTML = `
                    <div class="p-4 rounded-4 bg-danger bg-opacity-10 border border-danger border-opacity-25">
                        <div class="d-flex align-items-center mb-2">
                            <i class="fa-solid fa-circle-xmark text-danger fs-5 me-2"></i>
                            <h6 class="fw-bold text-danger mb-0">Verification Failed</h6>
                        </div>
                        <p class="small text-danger mb-0">${data.ResponseMessage || data.DetailedMessage || 'Unable to resolve the given shortcode.'}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-magnifying-glass me-2"></i> Verify Shortcode';
            resultCard.innerHTML = `
                <div class="p-3 rounded-4 bg-danger bg-opacity-10 border border-danger text-danger text-center small">
                    A network or application error occurred. Please check console logs.
                </div>
            `;
            console.error('Error:', error);
        });
});
</script>

</body>
</html>
