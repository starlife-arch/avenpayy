<?php
require_once __DIR__ . '/../lib/callback_auth.php';
av_require_callback_token('bal_result');
// b2c/balance_result.php
// Receives Safaricom Account Balance API callbacks and updates the single latest record.

header('Content-Type: application/json; charset=utf-8');

require_once $_SERVER['DOCUMENT_ROOT'] . '/database_config/db.php';

function respond($status, $message)
{
    http_response_code($status);
    echo json_encode(array('Result' => $message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, 'POST required');
}

$raw = file_get_contents('php://input');

if ($raw === false || trim($raw) === '') {
    error_log('M-Pesa balance callback received an empty body.');
    respond(400, 'Empty callback');
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    error_log('M-Pesa balance callback contained invalid JSON.');
    respond(400, 'Invalid JSON');
}

// Safaricom normally wraps the result in Result.
$result = $data['Result'] ?? $data;

if (!is_array($result)) {
    error_log('M-Pesa balance callback has an invalid result structure.');
    respond(400, 'Invalid result');
}

$conversationId = $result['ConversationID'] ?? null;
$originatorId = $result['OriginatorConversationID'] ?? null;
$resultCode = isset($result['ResultCode'])
    ? (string) $result['ResultCode']
    : null;
$resultDesc = $result['ResultDesc'] ?? null;

$accountBalance = null;

// Extract AccountBalance from ResultParameters.
$parameters = $result['ResultParameters']['ResultParameter'] ?? [];

if (is_array($parameters)) {
    foreach ($parameters as $parameter) {
        if (
            is_array($parameter) &&
            ($parameter['Key'] ?? '') === 'AccountBalance'
        ) {
            $value = $parameter['Value'] ?? null;

            if (is_scalar($value)) {
                $accountBalance = (string) $value;
            } elseif ($value !== null) {
                $accountBalance = json_encode($value);
            }

            break;
        }
    }
}

try {
    $callbackType = 'balance_result';

    // 1. Check if a callback row already exists
    $checkRes = $conn->query("SELECT id FROM mpesa_balance_callbacks ORDER BY id DESC LIMIT 1");

    if ($checkRes && $row = $checkRes->fetch_assoc()) {
        // UPDATE the existing latest record (Replaces data, avoids filling up the table)
        $updateId = $row['id'];
        $stmt = $conn->prepare(
            "UPDATE mpesa_balance_callbacks 
             SET callback_type = ?, 
                 conversation_id = ?, 
                 originator_conversation_id = ?, 
                 result_code = ?, 
                 result_desc = ?, 
                 account_balance = ?, 
                 raw_payload = ?, 
                 created_at = NOW()
             WHERE id = ?"
        );

        if (!$stmt) {
            throw new Exception('Unable to prepare callback update.');
        }

        $stmt->bind_param(
            'sssssssi',
            $callbackType,
            $conversationId,
            $originatorId,
            $resultCode,
            $resultDesc,
            $accountBalance,
            $raw,
            $updateId
        );
    } else {
        // INSERT only if table is completely empty
        $stmt = $conn->prepare(
            "INSERT INTO mpesa_balance_callbacks
            (
                callback_type,
                conversation_id,
                originator_conversation_id,
                result_code,
                result_desc,
                account_balance,
                raw_payload,
                created_at
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        if (!$stmt) {
            throw new Exception('Unable to prepare callback insert.');
        }

        $stmt->bind_param(
            'sssssss',
            $callbackType,
            $conversationId,
            $originatorId,
            $resultCode,
            $resultDesc,
            $accountBalance,
            $raw
        );
    }

    if (!$stmt->execute()) {
        throw new Exception('Unable to save/update balance callback.');
    }

    $stmt->close();

    error_log(
        'M-Pesa balance result updated. ConversationID: '
        . ($conversationId ?? 'not provided')
        . '; ResultCode: ' . ($resultCode ?? 'not provided')
    );

    respond(200, 'Callback received and updated');

} catch (Throwable $e) {
    error_log('M-Pesa balance result processing error: ' . $e->getMessage());
    respond(500, 'Callback processing failed');
}