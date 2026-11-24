<?php
/**
 * Finalize one subscription STK transaction exactly once.
 * Transaction status and settlement activation are committed atomically.
 */
if (!function_exists('av_finalize_subscription_payment')) {
    function av_finalize_subscription_payment(
        mysqli $conn,
        string $checkoutRequestId,
        int $resultCode,
        string $receiptNumber = '',
        string $resultDescription = ''
    ): array {
        if ($checkoutRequestId === '') {
            throw new InvalidArgumentException('Checkout request ID is required.');
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "SELECT id, user_id, amount, settlement_id, transaction_desc, status, processed
                 FROM mpesa_transactions
                 WHERE checkout_request_id = ?
                 LIMIT 1 FOR UPDATE"
            );
            if (!$stmt) throw new RuntimeException('Could not prepare transaction lookup.');
            $stmt->bind_param('s', $checkoutRequestId);
            $stmt->execute();
            $tx = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$tx) throw new RuntimeException('Subscription transaction was not found.');

            $status = strtoupper((string) ($tx['status'] ?? 'PENDING'));
            if ((int) ($tx['processed'] ?? 0) === 1 || in_array($status, ['SUCCESS', 'FAILED'], true)) {
                $conn->commit();
                return ['status' => $status, 'already_processed' => true];
            }
            if ($status !== 'PENDING') {
                throw new RuntimeException('Transaction is not in a finalizable pending state.');
            }

            $txId = (int) $tx['id'];
            if ($resultCode === 0) {
                $userId = (int) ($tx['user_id'] ?? 0);
                $settlementId = (int) ($tx['settlement_id'] ?? 0);
                $amount = (float) ($tx['amount'] ?? 0);
                if ($userId < 1 || $settlementId < 1 || $amount <= 0) {
                    throw new RuntimeException('Transaction is missing valid settlement, owner, or amount details.');
                }

                $settle = $conn->prepare(
                    "SELECT expiry_date FROM settlements
                     WHERE id = ? AND user_id = ? LIMIT 1 FOR UPDATE"
                );
                if (!$settle) throw new RuntimeException('Could not prepare settlement lookup.');
                $settle->bind_param('ii', $settlementId, $userId);
                $settle->execute();
                $settlement = $settle->get_result()->fetch_assoc();
                $settle->close();
                if (!$settlement) throw new RuntimeException('The related settlement account was not found.');

                // New transactions carry their exact paid duration in the description.
                // Keep amount-based fallbacks for historical PrintPay/AvenPay payments.
                $description = (string) ($tx['transaction_desc'] ?? '');
                $durationDays = 0;
                $months = 0;
                if (preg_match('/\\b(\\d{1,4})\\s+DAYS?\\b/i', $description, $match)) {
                    $durationDays = max(1, min(3650, (int) $match[1]));
                } elseif (preg_match('/\\b(\\d{1,3})\\s+MONTHS?\\b/i', $description, $match)) {
                    $months = max(1, min(120, (int) $match[1]));
                } elseif ($amount >= 3500) {
                    // Legacy transactions created before the new pricing schedule.
                    $months = 12;
                } elseif ($amount >= 950) {
                    $months = 3;
                } elseif ($amount >= 200) {
                    $months = 1;
                } else {
                    // Legacy monthly price was KES 350; the new fixed monthly plan
                    // is explicitly tagged "1 MONTH" and will not reach this branch.
                    $months = max(1, (int) round($amount / 350));
                }

                $expiry = !empty($settlement['expiry_date']) ? strtotime($settlement['expiry_date']) : false;
                $base = ($expiry !== false && $expiry > time()) ? $expiry : time();
                $newExpiry = $durationDays > 0
                    ? date('Y-m-d H:i:s', strtotime('+' . $durationDays . ' days', $base))
                    : date('Y-m-d H:i:s', strtotime('+' . $months . ' months', $base));

                $activate = $conn->prepare(
                    "UPDATE settlements
                     SET is_active = 1, status = 'active', expiry_date = ?
                     WHERE id = ? AND user_id = ?"
                );
                if (!$activate) throw new RuntimeException('Could not prepare settlement activation.');
                $activate->bind_param('sii', $newExpiry, $settlementId, $userId);
                $activate->execute();
                if ($activate->affected_rows !== 1) {
                    $activate->close();
                    throw new RuntimeException('Settlement activation did not update exactly one account.');
                }
                $activate->close();

                $receipt = $receiptNumber !== '' ? $receiptNumber : null;
                $update = $conn->prepare(
                    "UPDATE mpesa_transactions
                     SET status = 'SUCCESS', result_code = 0, processed = 1, mpesa_receipt_number = ?
                     WHERE id = ? AND status = 'PENDING' AND (processed IS NULL OR processed = 0)"
                );
                if (!$update) throw new RuntimeException('Could not prepare successful transaction update.');
                $update->bind_param('si', $receipt, $txId);
                $update->execute();
                if ($update->affected_rows !== 1) {
                    $update->close();
                    throw new RuntimeException('Transaction success state was not saved.');
                }
                $update->close();
                $conn->commit();
                return ['status' => 'SUCCESS', 'already_processed' => false];
            }

            $update = $conn->prepare(
                "UPDATE mpesa_transactions
                 SET status = 'FAILED', result_code = ?, processed = 1, mpesa_receipt_number = NULL
                 WHERE id = ? AND status = 'PENDING' AND (processed IS NULL OR processed = 0)"
            );
            if (!$update) throw new RuntimeException('Could not prepare failed transaction update.');
            $update->bind_param('ii', $resultCode, $txId);
            $update->execute();
            if ($update->affected_rows !== 1) {
                $update->close();
                throw new RuntimeException('Transaction failure state was not saved.');
            }
            $update->close();
            $conn->commit();
            return ['status' => 'FAILED', 'already_processed' => false];
        } catch (Throwable $e) {
            try { $conn->rollback(); } catch (Throwable $ignored) {}
            throw $e;
        }
    }
}
