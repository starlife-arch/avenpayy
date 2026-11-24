<?php
require_once __DIR__ . '/../lib/require_admin.php';
require_once __DIR__ . '/config.php';

// Set execution time to allow looping through multiple date chunks
set_time_limit(300);

$accessToken = getAccessToken();
if (!$accessToken) {
    die("<h3 style='color:red;'>Error: Could not retrieve Access Token. Check error.log.</h3>");
}

$url = 'https://api.safaricom.co.ke/pulltransactions/v1/query';

// Define the start date (Beginning of October 2026) up to current time
$startDate = new DateTime('2026-10-01 00:00:00');
$endDate   = new DateTime(); // Current date and time

echo "<h2>Full Month M-Pesa Pull Sync (October 2026)</h2>";
echo "<p><b>Paybill:</b> " . PAYBILL_NUMBER . " | <b>Range:</b> " . $startDate->format('Y-m-d') . " to " . $endDate->format('Y-m-d H:i:s') . "</p>";
echo "<hr>";

echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; width: 100%; text-align: left; font-family: Arial, sans-serif;'>";
echo "<tr style='background-color: #00703c; color: white;'>";
echo "<th>#</th>";
echo "<th>Receipt (Trans ID)</th>";
echo "<th>Sender / M-Pesa Name</th>";
echo "<th>Phone Number</th>";
echo "<th>Amount</th>";
echo "<th>Account / Ref</th>";
echo "<th>Transaction Date</th>";
echo "</tr>";

$totalSynced = 0;
$cursor = clone $startDate;

// Loop in 48-hour chunks to comply with Safaricom rules
while ($cursor < $endDate) {
    $chunkStart = clone $cursor;
    $chunkEnd   = clone $cursor;
    $chunkEnd->modify('+47 hours');

    if ($chunkEnd > $endDate) {
        $chunkEnd = clone $endDate;
    }

    $strStart = $chunkStart->format('Y-m-d H:i:s');
    $strEnd   = $chunkEnd->format('Y-m-d H:i:s');

    $requestData = [
        "ShortCode"   => (string)PAYBILL_NUMBER,
        "StartDate"   => $strStart,
        "EndDate"     => $strEnd,
        "OffSetValue" => "0"
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . trim($accessToken),
            'Content-Type: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_RETURNTRANSFER => true
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['ResponseCode']) && $result['ResponseCode'] === "1000" && !empty($result['Response'])) {
        foreach ($result['Response'] as $batch) {
            foreach ($batch as $tx) {
                $totalSynced++;
                $transId   = $tx['transactionId'] ?? 'N/A';
                $transType = $tx['transactiontype'] ?? '';
                $transTime = $tx['trxDate'] ?? '';
                $amount    = number_format((float)($tx['amount'] ?? 0), 2, '.', '');
                $billRef   = $tx['billreference'] ?? '';
                $msisdn    = $tx['msisdn'] ?? '';
                $sender    = $tx['sender'] ?? 'N/A';
                $rawJson   = json_encode($tx);

                echo "<tr>";
                echo "<td><b>{$totalSynced}</b></td>";
                echo "<td style='color: #04af84; font-weight: bold;'>{$transId}</td>";
                echo "<td><b>{$sender}</b></td>";
                echo "<td>{$msisdn}</td>";
                echo "<td style='color: green; font-weight: bold;'>KES {$amount}</td>";
                echo "<td>{$billRef}</td>";
                echo "<td>{$transTime}</td>";
                echo "</tr>";

                if (!empty($transId) && isset($conn)) {
                    try {
                        $stmt = $conn->prepare("INSERT INTO c2b_payments 
                            (trans_id, trans_type, trans_time, amount, shortcode, bill_ref, msisdn, first_name, raw_payload) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                                trans_id = VALUES(trans_id),
                                first_name = VALUES(first_name)");

                        $stmt->execute([
                            $transId,
                            $transType,
                            $transTime,
                            $amount,
                            PAYBILL_NUMBER,
                            $billRef,
                            $msisdn,
                            $sender,
                            $rawJson
                        ]);
                    } catch (Exception $e) {
                        logMessage('error.log', "Pull DB Error [{$transId}]: " . $e->getMessage());
                    }
                }
            }
        }
    }

    // Move cursor forward by 47 hours for next iteration
    $cursor->modify('+47 hours');
}

echo "</table>";

echo "<br><div style='background: #e8f5e9; border: 1px solid #4caf50; padding: 12px; border-radius: 5px; color: #2e7d32; font-family: Arial, sans-serif;'>";
echo "<b>Full Month Pull Completed!</b> Total transactions synced into database: <b>{$totalSynced}</b>";
echo "</div>";
?>