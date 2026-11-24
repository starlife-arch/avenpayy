
<?php
require_once __DIR__ . '/../lib/callback_auth.php';
av_require_callback_token('bal_timeout');
// b2c/balance_timeout.php
// Receives Safaricom Account Balance API timeout callbacks.

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
    error_log('M-Pesa balance timeout callback received an empty body.');
    respond(400, 'Empty callback');
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    error_log('M-Pesa balance timeout callback contained invalid JSON.');
    respond(400, 'Invalid JSON');
}

$result = $data['Result'] ?? $data;

if (!is_array($result)) {
    respond(400, 'Invalid result');
}

$conversationId = $result['ConversationID'] ?? null;
$originatorId = $result['OriginatorConversationID'] ?? null;
$resultCode = isset($result['ResultCode'])
    ? (string) $result['ResultCode']
    : null;
$resultDesc = $result['ResultDesc'] ?? 'Request timed out';

$accountBalance = null;

try {
    $stmt = $conn->prepare(
        "INSERT INTO mpesa_balance_callbacks
        (
            callback_type,
            conversation_id,
            originator_conversation_id,
            result_code,
            result_desc,
            account_balance,
            raw_payload
        )
        VALUES (?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception('Unable to prepare timeout insert.');
    }

    $callbackType = 'balance_timeout';

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

    if (!$stmt->execute()) {
        throw new Exception('Unable to save timeout callback.');
    }

    $stmt->close();

    error_log(
        'M-Pesa balance timeout recorded. ConversationID: '
        . ($conversationId ?? 'not provided')
    );

    respond(200, 'Timeout callback received');

} catch (Throwable $e) {
    error_log('M-Pesa balance timeout processing error: ' . $e->getMessage());
    respond(500, 'Timeout processing failed');
}
