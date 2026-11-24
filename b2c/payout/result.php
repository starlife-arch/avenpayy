<?php
require_once __DIR__ . '/../../lib/ledger.php';
require_once __DIR__ . '/../../lib/callback_auth.php';
av_require_callback_token('b2c_result');
require_once __DIR__ . '/../../lib/logger.php';
// b2c/payout/result.php
require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

// Dynamic Fee Calculator helper for calculating refund amounts (Amount + Fee)
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

$callbackResponse = file_get_contents('php://input');
$logFile = av_log_path('b2c_responses.log');
$timestamp = date('Y-m-d H:i:s');

if (!empty($callbackResponse)) {
    file_put_contents($logFile, "$timestamp - $callbackResponse\n", FILE_APPEND);
    
    $data = json_decode($callbackResponse, true);
    
    if (isset($data['Result'])) {
        $result = $data['Result'];
        
        // FIX: Capture ResultCode safely as string or int (handles "SFC_IC0003", 2040, 0, etc.)
        $rawResultCode = $result['ResultCode'] ?? 0;
        $resultCode = is_numeric($rawResultCode) ? (int)$rawResultCode : trim($rawResultCode);
        
        $resultDesc = trim($result['ResultDesc'] ?? '');
        $conversationID = trim($result['ConversationID'] ?? '');
        $originatorConversationID = trim($result['OriginatorConversationID'] ?? '');
        
        // Root Level Transaction ID fallback
        $rootTransactionId = trim($result['TransactionID'] ?? '');
        if ($rootTransactionId === 'UJG1000000' || $rootTransactionId === 'UJK0000000' || $rootTransactionId === 'UJG0000000' || $rootTransactionId === 'UJN0000000') {
            $rootTransactionId = null;
        }

        $utility       = null;
        $working       = null;
        $charges       = null;
        $merchant      = null;
        $airtime       = null;
        $settlement    = null;
        $transactionId = $rootTransactionId;
        $receiverName  = null;
        $completedAt   = null;

        // Parse ResultParameters array safely
        if (isset($result['ResultParameters']['ResultParameter'])) {
            $params = $result['ResultParameters']['ResultParameter'];
            
            if (isset($params['Key'])) {
                $params = [$params];
            }

            foreach ($params as $param) {
                $key   = $param['Key'] ?? $param[0] ?? '';
                $value = $param['Value'] ?? $param[1] ?? '';

                if ($key === 'TransactionReceipt' && !empty($value)) {
                    $transactionId = trim($value);
                }

                if ($key === 'ReceiverPartyPublicName' && !empty($value)) {
                    $valStr = trim((string)$value);
                    if (strpos($valStr, ' - ') !== false) {
                        $parts = explode(' - ', $valStr, 2);
                        $receiverName = isset($parts[1]) ? trim($parts[1]) : $valStr;
                    } else {
                        $receiverName = $valStr;
                    }
                }

                if ($key === 'TransactionCompletedDateTime' || $key === 'BOCompletedTime') {
                    $dt = DateTime::createFromFormat('d.m.Y H:i:s', $value);
                    if (!$dt) {
                        $dt = DateTime::createFromFormat('YmdHis', $value);
                    }
                    if ($dt) {
                        $completedAt = $dt->format('Y-m-d H:i:s');
                    }
                }

                if ($key === 'B2CUtilityAccountAvailableFunds') $utility = (float)$value;
                if ($key === 'B2CWorkingAccountAvailableFunds') $working = (float)$value;
                if ($key === 'B2CChargesPaidAccountAvailableFunds') $charges = (float)$value;
            }
        }

        if (isset($conn) && $conn) {
            // FIX: Determine failure via non-zero code OR specific text error descriptions (like "Receiver party is invalid")
            $isFailure = ($resultCode !== 0 && $resultCode !== '0') || 
                         (stripos($resultDesc, 'Receiver party is invalid') !== false) || 
                         (stripos($resultDesc, 'can\'t be supported') !== false) || 
                         (stripos($resultDesc, 'insufficient') !== false) ||
                         (strpos($resultCode, 'SFC_') === 0);

            if ($isFailure) {
                // FAILURE: Fetch transaction details to refund user (Amount + Fee) and update status to FAILED
                $lookupStmt = $conn->prepare("
                    SELECT id, user_id, transaction_amount, result_code 
                    FROM mpesa_b2c_transactions 
                    WHERE conversation_id = ? OR originator_conversation_id = ? 
                    LIMIT 1
                ");
                if ($lookupStmt) {
                    $lookupStmt->bind_param("ss", $conversationID, $originatorConversationID);
                    $lookupStmt->execute();
                    $txRes = $lookupStmt->get_result()->fetch_assoc();
                    $lookupStmt->close();

                    if ($txRes) {
                        $txId = $txRes['id'];
                        $userId = $txRes['user_id'];
                        $amount = floatval($txRes['transaction_amount']);
                        $currentDbResultCode = $txRes['result_code'];

                        // FIX: Refund if result_code is unconfirmed (1000, NULL, or empty)
                        if ($currentDbResultCode === null || $currentDbResultCode === '' || (int)$currentDbResultCode === 1000) {
                            $withdrawalFee = getTransactionFee($conn, 'b2c_withdrawal', $amount);
                            $totalRefund = $amount + $withdrawalFee;

                            $conn->begin_transaction();
                            try {
                                // Atomically claim the pending row FIRST. If a duplicate callback already
                                // processed it, affected_rows is 0 and we skip the refund (no double refund).
                                $dbCode = is_numeric($resultCode) ? (int)$resultCode : 9999;
                                $dbDesc = $resultDesc . (is_numeric($resultCode) ? '' : ' [code: ' . $resultCode . ']');
                                $failStmt = $conn->prepare("
                                    UPDATE mpesa_b2c_transactions
                                    SET result_code = ?,
                                        result_desc = ?,
                                        raw_response = CONCAT(IFNULL(raw_response, ''), '\n[CALLBACK: ', ? , ']'),
                                        completed_at = NOW()
                                    WHERE id = ? AND result_code = 1000
                                ");
                                $failStmt->bind_param("issi", $dbCode, $dbDesc, $callbackResponse, $txId);
                                $failStmt->execute();
                                $claimedRefund = $failStmt->affected_rows > 0;
                                $failStmt->close();

                                if ($claimedRefund) {
                                    av_wallet_apply($conn, (int)$userId, (float)$totalRefund, 'payout_release', 'b2c_tx', (string)$txId, 'Payout failed: ' . substr((string)$resultDesc, 0, 120), false);
                                    $refundStmt = $conn->prepare("UPDATE users SET total_b2c_withdrawn = GREATEST(0, total_b2c_withdrawn - ?) WHERE id = ?");
                                    $refundStmt->bind_param("di", $amount, $userId);
                                    $refundStmt->execute();
                                    $refundStmt->close();
                                }

                                $conn->commit();
                            } catch (Exception $e) {
                                $conn->rollback();
                            }
                        }
                    }
                }
            } else {
                // SUCCESS: Build dynamic update query for successful transactions
                $updateFields = ["result_code = ?", "result_desc = ?"];
                $types = "ss"; // result_code can be string/int, result_desc is string
                $paramsArr = [$resultCode, $resultDesc];

                if (!empty($transactionId)) {
                    $updateFields[] = "transaction_id = ?";
                    $types .= "s";
                    $paramsArr[] = $transactionId;
                }
                if (!empty($receiverName)) {
                    $updateFields[] = "receiver_name = ?";
                    $types .= "s";
                    $paramsArr[] = $receiverName;
                }
                if (!empty($completedAt)) {
                    $updateFields[] = "completed_at = ?";
                    $types .= "s";
                    $paramsArr[] = $completedAt;
                }
                if ($utility !== null) {
                    $updateFields[] = "utility_balance = ?";
                    $types .= "d";
                    $paramsArr[] = $utility;
                }
                if ($working !== null) {
                    $updateFields[] = "working_balance = ?";
                    $types .= "d";
                    $paramsArr[] = $working;
                }
                if ($charges !== null) {
                    $updateFields[] = "charges_balance = ?";
                    $types .= "d";
                    $paramsArr[] = $charges;
                }

                // Append raw response log
                $updateFields[] = "raw_response = CONCAT(IFNULL(raw_response, ''), '\n[CALLBACK: ', ? , ']')";
                $types .= "s";
                $paramsArr[] = $callbackResponse;

                // WHERE parameters
                $paramsArr[] = $conversationID;
                $paramsArr[] = $originatorConversationID;
                $types .= "ss";

                $sql = "UPDATE mpesa_b2c_transactions SET " . implode(", ", $updateFields) . " WHERE conversation_id = ? OR originator_conversation_id = ? ORDER BY id DESC LIMIT 1";

                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $bindParams = [$types];
                    foreach ($paramsArr as $k => $v) {
                        $bindParams[] = &$paramsArr[$k];
                    }
                    call_user_func_array([$stmt, 'bind_param'], $bindParams);
                    
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}

http_response_code(200);
header('Content-Type: application/json');
echo json_encode(["ResultCode" => 0, "ResultDesc" => "Success"]);