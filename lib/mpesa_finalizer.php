<?php
/**
 * Shared, idempotent finalizer for ordinary C2B STK transactions.
 * Callback delivery and browser/API status polling use the same row lock and queue writes.
 */
if (!function_exists('av_finalize_mpesa_transaction')) {
    function av_finalize_mpesa_transaction(
        mysqli $conn,
        string $checkoutRequestId,
        int $resultCode,
        string $receiptOrDescription = '',
        ?string $merchantRequestId = null
    ): array {
        if ($checkoutRequestId === '') {
            throw new InvalidArgumentException('Checkout request ID is required.');
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "SELECT id, user_id, settlement_id, status, processed, phone_number, amount
                 FROM mpesa_transactions
                 WHERE checkout_request_id = ?
                 LIMIT 1 FOR UPDATE"
            );
            if (!$stmt) throw new RuntimeException('Could not prepare transaction lookup.');
            $stmt->bind_param('s', $checkoutRequestId);
            $stmt->execute();
            $tx = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$tx) throw new RuntimeException('M-Pesa transaction was not found.');

            if ((int) ($tx['processed'] ?? 0) === 1) {
                $conn->commit();
                return [
                    'status' => strtoupper((string) ($tx['status'] ?? 'PENDING')),
                    'receipt' => (string) ($tx['mpesa_receipt_number'] ?? ''),
                    'result_code' => $tx['result_code'] ?? null,
                    'already_processed' => true
                ];
            }

            $success = ($resultCode === 0);
            $status = $success ? 'SUCCESS' : 'FAILED';
            $receipt = $receiptOrDescription !== '' ? $receiptOrDescription : null;
            $txId = (int) $tx['id'];
            $phone = (string) ($tx['phone_number'] ?? '');
            $amount = (float) ($tx['amount'] ?? 0);
            $settlementId = (int) ($tx['settlement_id'] ?? 0);

            $update = $conn->prepare(
                "UPDATE mpesa_transactions
                 SET status = ?, merchant_request_id = ?, mpesa_receipt_number = ?, result_code = ?, processed = 1
                 WHERE id = ? AND (processed IS NULL OR processed = 0)"
            );
            if (!$update) throw new RuntimeException('Could not prepare transaction finalization.');
            $update->bind_param('sssii', $status, $merchantRequestId, $receipt, $resultCode, $txId);
            $update->execute();
            if ($update->affected_rows !== 1) {
                $update->close();
                throw new RuntimeException('Transaction finalization did not update exactly one row.');
            }
            $update->close();

            $streamCode = $receipt ?? '';
            $stream = $conn->prepare(
                "INSERT IGNORE INTO mpesa_stream_queue
                    (transaction_id, mpesa_code, phone, amount, tx_status, stream_status)
                 VALUES (?, ?, ?, ?, ?, 'pending')"
            );
            if (!$stream) throw new RuntimeException('Could not prepare transaction stream queue.');
            $stream->bind_param('issds', $txId, $streamCode, $phone, $amount, $status);
            $stream->execute();
            $stream->close();

            $webhookQueueId = 0;
            if ($settlementId > 0) {
                $lookup = $conn->prepare(
                    "SELECT callback_url FROM settlements
                     WHERE id = ? AND is_active = 1
                       AND (expiry_date IS NULL OR expiry_date > NOW())
                     LIMIT 1"
                );
                if (!$lookup) throw new RuntimeException('Could not prepare merchant callback lookup.');
                $lookup->bind_param('i', $settlementId);
                $lookup->execute();
                $callbackUrl = trim((string) ($lookup->get_result()->fetch_assoc()['callback_url'] ?? ''));
                $lookup->close();

                if ($callbackUrl !== '' && filter_var($callbackUrl, FILTER_VALIDATE_URL)) {
                    $payload = json_encode([
                        'checkout_id' => $checkoutRequestId,
                        'status' => $status,
                        'mpesa_receipt' => $receipt,
                        'amount' => $amount,
                        'phone' => $phone,
                        'result_code' => $resultCode
                    ]);
                    $webhook = $conn->prepare(
                        "INSERT INTO webhook_queue (transaction_id, webhook_url, payload, status)
                         VALUES (?, ?, ?, 'PENDING')"
                    );
                    if (!$webhook) throw new RuntimeException('Could not prepare webhook queue insert.');
                    $webhook->bind_param('iss', $txId, $callbackUrl, $payload);
                    $webhook->execute();
                    $webhookQueueId = (int) $webhook->insert_id;
                    $webhook->close();
                }
            }

            $conn->commit();

            if ($webhookQueueId > 0) {
                av_deliver_mpesa_webhook($conn, $webhookQueueId);
            }

            return [
                'status' => $status,
                'receipt' => (string) ($receipt ?? ''),
                'result_code' => $resultCode,
                'already_processed' => false
            ];
        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $ignored) {}
            throw $e;
        }
    }

    function av_deliver_mpesa_webhook(mysqli $conn, int $queueId): void
    {
        $stmt = $conn->prepare("SELECT webhook_url, payload FROM webhook_queue WHERE id = ? LIMIT 1");
        if (!$stmt) return;
        $stmt->bind_param('i', $queueId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$item) return;

        $url = trim((string) ($item['webhook_url'] ?? ''));
        if (!filter_var($url, FILTER_VALIDATE_URL)) return;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => (string) ($item['payload'] ?? ''),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        $newStatus = is_string($response) && $httpCode >= 200 && $httpCode < 300 ? 'SENT' : 'FAILED';
        $update = $conn->prepare("UPDATE webhook_queue SET status = ? WHERE id = ?");
        if ($update) {
            $update->bind_param('si', $newStatus, $queueId);
            $update->execute();
            $update->close();
        }
        if ($newStatus === 'FAILED') {
            error_log('AvenPay merchant webhook delivery failed for queue ' . $queueId . ' (HTTP ' . $httpCode . '): ' . $curlError);
        }
    }
}
