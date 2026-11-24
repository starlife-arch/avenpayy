<?php
require_once __DIR__ . '/../lib/cron_guard.php';
av_cron_guard();
require_once __DIR__ . '/../lib/ledger.php';
require_once __DIR__ . '/../lib/logger.php';
require_once __DIR__ . '/../database_config/env.php';
// Pull in centralized database configuration layer from root
require_once __DIR__ . '/../database_config/db.php';

// Define Path to Logs Directory
define('LOGS_DIR', av_log_dir());

/**
 * Logger Helper Function
 */
function logMessage($filename, $message) {
    if (!is_dir(LOGS_DIR)) {
        mkdir(LOGS_DIR, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[{$timestamp}] " . (is_array($message) || is_object($message) ? json_encode($message) : $message) . PHP_EOL;
    file_put_contents(av_log_path($filename), $formattedMessage, FILE_APPEND | LOCK_EX);
}

function getAccessTokenForCredentials($consumerKey, $consumerSecret) {
    $url = av_daraja_oauth_url();
    $credentials = base64_encode($consumerKey . ':' . $consumerSecret);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ['Authorization: Basic ' . $credentials],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200) {
        logMessage('error.log', "OAuth HTTP Code: {$httpCode} | Response: {$response} | cURL Error: {$curlError}");
        return null;
    }

    $result = json_decode($response, true);
    return $result['access_token'] ?? null;
}

// 1. CONCURRENCY LOCK: Prevent multiple cron instances from overlapping
$lockFile = sys_get_temp_dir() . '/b2c_pull_cron.lock';
$lockHandle = fopen($lockFile, 'c+');

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    logMessage('error.log', "B2C Pull Cron Skipped: Previous instance is still running.");
    exit("Cron already running.");
}

// Set execution time limit
set_time_limit(600); // 10 minutes max for multiple gateways

// Dynamic Fee Calculator
function getTransactionFee($conn, $serviceType, $amount) {
    $stmt = $conn->prepare("
        SELECT fee_type, fee_value 
        FROM transaction_fees 
        WHERE service_type = ? AND is_active = 1 AND ? >= min_amount AND ? <= max_amount 
        LIMIT 1
    ");
    if ($stmt) {
        $stmt->bind_param("sdd", $serviceType, $amount, $amount);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($res) {
            return ($res['fee_type'] === 'percentage') 
                ? ($amount * floatval($res['fee_value'])) / 100 
                : floatval($res['fee_value']);
        }
    }
    return 0.00;
}

// 2. Fetch gateways from daraja_gateways with valid initiator credentials present (ignoring is_active status)
$gatewaysResult = $conn->query("
    SELECT shortcode, consumer_key, consumer_secret, initiator_username, initiator_password 
    FROM daraja_gateways 
    WHERE initiator_username IS NOT NULL AND initiator_username != '' 
      AND initiator_password IS NOT NULL AND initiator_password != ''
");

if (!$gatewaysResult || $gatewaysResult->num_rows === 0) {
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit("No gateways with valid initiator credentials found in daraja_gateways.");
}

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    echo "<h2>M-Pesa Multi-Gateway Logs & Wallet Reconciliation</h2><hr>";
} else {
    logMessage('cron.log', "M-Pesa Multi-Gateway Pull Sync started.");
}

// Loop through each configured gateway record
while ($configRow = $gatewaysResult->fetch_assoc()) {
    $paybillNumber = trim($configRow['shortcode']);
     // daraja_gateways stores these gateway credentials as plaintext; do not attempt decryption.
    $consumerKey = trim((string)$configRow['consumer_key']);
    $consumerSecret = trim((string)$configRow['consumer_secret']);
    $initiatorUser = trim($configRow['initiator_username']);
    $initiatorPass = trim((string)$configRow['initiator_password']);

    if (empty($paybillNumber) || empty($consumerKey) || empty($consumerSecret)) {
        logMessage('error.log', "Skipping gateway {$paybillNumber} due to incomplete credentials.");
        continue;
    }

    $accessToken = getAccessTokenForCredentials($consumerKey, $consumerSecret);
    if (!$accessToken) {
        logMessage('error.log', "Pull Cron Error: Could not retrieve Access Token for gateway/shortcode {$paybillNumber}.");
        continue;
    }

    $url = av_daraja_endpoint('pulltransactions/v1/query');

    // Safely retrieve last transaction time for THIS specific gateway shortcode
    $startDate = null;
    $stmtMax = $conn->prepare("SELECT MAX(trans_time) as last_time FROM paybill_logs WHERE shortcode = ?");
    if ($stmtMax) {
        $stmtMax->bind_param("s", $paybillNumber);
        $stmtMax->execute();
        $resMax = $stmtMax->get_result()->fetch_assoc();
        $stmtMax->close();
        
        if ($resMax && !empty($resMax['last_time'])) {
            try {
                $cleanTime = rtrim(trim($resMax['last_time']), '+-');
                $startDate = new DateTime($cleanTime);
                $startDate->modify('-10 minutes');
            } catch (Exception $e) {
                $startDate = null;
            }
        }
    }

    if (!$startDate) {
        $startDate = new DateTime();
        $startDate->modify('-48 hours');
    }

    $endDate = new DateTime(); 
    $maxPastLimit = new DateTime();
    $maxPastLimit->modify('-48 hours');
    if ($startDate < $maxPastLimit) {
        $startDate = $maxPastLimit;
    }

    if (!$isCli) {
        echo "<h3>Syncing Gateway Shortcode: {$paybillNumber}</h3>";
        echo "<p><b>Range:</b> " . $startDate->format('Y-m-d H:i:s') . " to " . $endDate->format('Y-m-d H:i:s') . "</p>";
        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%; text-align: left; font-family: Arial, sans-serif; margin-bottom: 20px;'>";
        echo "<tr style='background-color: #00703c; color: white;'>";
        echo "<th>#</th><th>Receipt (Trans ID)</th><th>Sender</th><th>Phone</th><th>Gross Amount</th><th>Fee</th><th>Net Credited</th><th>Account / Wallet ID</th><th>Date</th><th>Status</th>";
        echo "</tr>";
    }

    $totalSynced = 0;
    $totalCredited = 0;
    $cursor = clone $startDate;

    while ($cursor < $endDate) {
        $chunkStart = clone $cursor;
        $chunkEnd   = clone $cursor;
        $chunkEnd->modify('+47 hours');

        if ($chunkEnd > $endDate) {
            $chunkEnd = clone $endDate;
        }

        $strStart = $chunkStart->format('Y-m-d H:i:s');
        $strEnd   = $chunkEnd->format('Y-m-d H:i:s');
        $offset = 0;
        $hasNext = true;

        while ($hasNext) {
            $requestData = [
                "ShortCode"   => (string)$paybillNumber,
                "StartDate"   => $strStart,
                "EndDate"     => $strEnd,
                "OffSetValue" => (string)$offset
            ];

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . trim($accessToken),
                    'Content-Type: application/json'
                ],
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($requestData),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            $result = json_decode($response, true);


            if ($httpCode !== 200 || (isset($result['ResponseCode']) && $result['ResponseCode'] !== "1000")) {
                logMessage('error.log', "Pull API Issue [Gateway: {$paybillNumber}] HTTP {$httpCode} | Error: {$curlError} | Response: {$response}");
            }

            if (isset($result['ResponseCode']) && $result['ResponseCode'] === "1000" && !empty($result['Response'])) {
                foreach ($result['Response'] as $batch) {
                    if (!is_array($batch)) continue;

                    foreach ($batch as $tx) {
                        $totalSynced++;
                        $transId    = $tx['transactionId'] ?? $tx['TransID'] ?? 'N/A';
                        $transType  = $tx['transactiontype'] ?? 'c2b-paybill';
                        $transTime  = $tx['trxDate'] ?? $tx['TransTime'] ?? date('Y-m-d H:i:s');
                        $grossAmount = floatval($tx['amount'] ?? $tx['TransAmount'] ?? 0);
                        $billRef    = trim($tx['billreference'] ?? $tx['BillRefNumber'] ?? '');
                        $msisdn     = $tx['msisdn'] ?? $tx['MSISDN'] ?? '';
                        $sender     = $tx['sender'] ?? 'N/A';
                        $firstName  = $tx['firstname'] ?? $sender;
                        $middleName = $tx['middlename'] ?? null;
                        $lastName   = $tx['lastname'] ?? null;
                        $rawJson    = json_encode($tx);

                        $topupFee = getTransactionFee($conn, 'c2b_topup', $grossAmount);
                        $netCreditAmount = $grossAmount - $topupFee;

                        $walletCreditedStatus = "<span style='color: orange;'>Wallet not available</span>";

                        if (!empty($transId) && $transId !== 'N/A' && isset($conn)) {
                            try {
                                $stmt = $conn->prepare("INSERT INTO paybill_logs 
                                    (trans_id, trans_type, trans_time, amount, shortcode, bill_ref, msisdn, first_name, middle_name, last_name, raw_payload) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE 
                                        trans_id = VALUES(trans_id),
                                        first_name = VALUES(first_name)");

                                $stmt->bind_param("sssssssssss", $transId, $transType, $transTime, $grossAmount, $paybillNumber, $billRef, $msisdn, $firstName, $middleName, $lastName, $rawJson);
                                $stmt->execute();
                                $stmt->close();

                                $stmtUser = $conn->prepare("SELECT id FROM users WHERE wallet_id = ? LIMIT 1");
                                $stmtUser->bind_param("s", $billRef);
                                $stmtUser->execute();
                                $resUser = $stmtUser->get_result();
                                $userRow = $resUser->fetch_assoc();
                                $stmtUser->close();

                                if ($userRow) {
                                    $stmtCheck = $conn->prepare("SELECT id FROM b2c_topup_transactions WHERE receipt_number = ? LIMIT 1");
                                    $stmtCheck->bind_param("s", $transId);
                                    $stmtCheck->execute();
                                    $resCheck = $stmtCheck->get_result();
                                    $existingTopup = $resCheck->fetch_assoc();
                                    $stmtCheck->close();

                                    if (!$existingTopup) {
                                        $userId = $userRow['id'];

                                        $insertTopup = $conn->prepare("
                                            INSERT INTO b2c_topup_transactions 
                                            (user_id, wallet_id, checkout_request_id, merchant_request_id, phone_number, amount, status, receipt_number, result_code, result_desc, created_at) 
                                            VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, 0, 'Auto-credited via Gateway Pull Sync', NOW())
                                        ");
                                        $checkoutReqId = 'PULL_' . $transId;
                                        $merchantReqId = 'PULL_API';
                                        $insertTopup->bind_param("issssds", $userId, $billRef, $checkoutReqId, $merchantReqId, $msisdn, $grossAmount, $transId);
                                        $insertTopup->execute();
                                        $insertTopup->close();

                                        if ($netCreditAmount > 0) {
                                            av_wallet_apply($conn, (int)$userId, (float)$netCreditAmount, 'topup_credit', 'pull', (string)$transId, 'Gateway pull credit ' . $transId);
                                        }

                                        $totalCredited++;
                                        $walletCreditedStatus = "<b style='color: green;'>Credited Net: KES " . number_format($netCreditAmount, 2) . " (Fee: KES " . number_format($topupFee, 2) . ")</b>";
                                    } else {
                                        $walletCreditedStatus = "<span style='color: blue;'>Already Processed</span>";
                                    }
                                } else {
                                    $walletCreditedStatus = "<span style='color: orange;'>Wallet not available</span>";
                                }

                            } catch (Exception $e) {
                                logMessage('error.log', "Gateway Pull DB Error [{$transId}]: " . $e->getMessage());
                                $walletCreditedStatus = "<span style='color: red;'>DB Error</span>";
                            }
                        }

                        if (!$isCli) {
                            echo "<tr>";
                            echo "<td><b>{$totalSynced}</b></td>";
                            echo "<td style='color: #04af84; font-weight: bold;'>{$transId}</td>";
                            echo "<td><b>{$sender}</b></td>";
                            echo "<td>{$msisdn}</td>";
                            echo "<td>KES " . number_format($grossAmount, 2) . "</td>";
                            echo "<td style='color: #d9534f;'>KES " . number_format($topupFee, 2) . "</td>";
                            echo "<td style='color: green; font-weight: bold;'>KES " . number_format($netCreditAmount, 2) . "</td>";
                            echo "<td>{$billRef}</td>";
                            echo "<td>{$transTime}</td>";
                            echo "<td>{$walletCreditedStatus}</td>";
                            echo "</tr>";
                        }
                    }
                }
            }

            $hasNext = isset($result['HasNext']) && $result['HasNext'] === true;
            if ($hasNext) {
                $offset += 100;
            } else {
                break;
            }
        }

        $cursor->modify('+47 hours');
    }

    if (!$isCli) {
        echo "</table>";
    } else {
        logMessage('cron.log', "Gateway {$paybillNumber} Sync finished. Logged: {$totalSynced}, Credited: {$totalCredited}");
    }
}

if (!$isCli) {
    echo "<div style='background: #e8f5e9; border: 1px solid #4caf50; padding: 12px; border-radius: 5px; color: #2e7d32; font-family: Arial, sans-serif;'>";
    echo "<b>All Gateways Synchronized Successfully (Active and Inactive Checked)!</b>";
    echo "</div>";
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);