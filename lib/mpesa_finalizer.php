<?php
/**
 * Shared, idempotent finalizer for ordinary STK transactions.
 *
 * The legacy PrintPay schema installs AFTER UPDATE triggers on mpesa_transactions.
 * Those triggers create mpesa_stream_queue and webhook_queue rows. Do not insert
 * into either queue here: doing so duplicates notifications and bypasses the
 * signed, SSRF-guarded dispatcher in api/dispatch_webhooks.php.
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
                "SELECT id, user_id, settlement_id, status, processed, phone_number,
                        amount, mpesa_receipt_number, result_code
                 FROM mpesa_transactions
                 WHERE checkout_request_id = ?
                 LIMIT 1 FOR UPDATE"
            );
            if (!$stmt) {
                throw new RuntimeException('Could not prepare transaction lookup: ' . $conn->error);
            }
            $stmt->bind_param('s', $checkoutRequestId);
            $stmt->execute();
            $tx = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$tx) {
                throw new RuntimeException('M-Pesa transaction was not found.');
            }

            if ((int) ($tx['processed'] ?? 0) === 1) {
                // STK Query can confirm ResultCode=0 without returning MpesaReceiptNumber.
                // If the later Safaricom callback includes the receipt, enrich the row
                // instead of discarding it merely because the query finalized first.
                $storedReceipt = trim((string) ($tx['mpesa_receipt_number'] ?? ''));
                $incomingReceipt = ($resultCode === 0) ? trim($receiptOrDescription) : '';
                if ($storedReceipt === '' && $incomingReceipt !== '') {
                    $enrich = $conn->prepare(
                        "UPDATE mpesa_transactions
                         SET mpesa_receipt_number = ?,
                             merchant_request_id = COALESCE(?, merchant_request_id)
                         WHERE id = ? AND status = 'SUCCESS'"
                    );
                    if (!$enrich) {
                        throw new RuntimeException('Could not prepare receipt enrichment: ' . $conn->error);
                    }
                    $enrichTxId = (int) $tx['id'];
                    $enrich->bind_param('ssi', $incomingReceipt, $merchantRequestId, $enrichTxId);
                    $enrich->execute();
                    $enrich->close();
                    $storedReceipt = $incomingReceipt;
                }
                $conn->commit();
                return [
                    'status' => strtoupper((string) ($tx['status'] ?? 'PENDING')),
                    'receipt' => $storedReceipt,
                    'result_code' => $tx['result_code'] ?? null,
                    'already_processed' => true
                ];
            }

            $status = ($resultCode === 0) ? 'SUCCESS' : 'FAILED';
            $receipt = $receiptOrDescription !== '' ? $receiptOrDescription : null;
            $txId = (int) $tx['id'];

            $update = $conn->prepare(
                "UPDATE mpesa_transactions
                 SET status = ?, merchant_request_id = ?, mpesa_receipt_number = ?,
                     result_code = ?, processed = 1
                 WHERE id = ? AND (processed IS NULL OR processed = 0)"
            );
            if (!$update) {
                throw new RuntimeException('Could not prepare transaction finalization: ' . $conn->error);
            }
            $update->bind_param('sssii', $status, $merchantRequestId, $receipt, $resultCode, $txId);
            $update->execute();
            if ($update->affected_rows !== 1) {
                $update->close();
                throw new RuntimeException('Transaction finalization did not update exactly one row.');
            }
            $update->close();

            // Queue rows are created by the database's existing PrintPay-compatible
            // AFTER UPDATE triggers. The dispatcher signs and safely delivers webhooks.
            $conn->commit();

            return [
                'status' => $status,
                'receipt' => (string) ($receipt ?? ''),
                'result_code' => $resultCode,
                'already_processed' => false
            ];
        } catch (Throwable $e) {
            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
            }
            throw $e;
        }
    }
}
