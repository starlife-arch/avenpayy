<?php
/**
 * Wallet ledger. EVERY change to users.b2c_wallet_balance must go through av_wallet_apply():
 * it locks the wallet row, checks funds, writes an immutable ledger line and updates the balance
 * in one step. The UNIQUE (entry_type, ref_type, ref_id) key makes each movement idempotent:
 * the same payout can never be refunded twice and the same top-up can never be credited twice.
 *
 * @param float  $delta      signed amount (+ credit, - debit)
 * @param bool   $manageTx   true  = function opens/commits its own transaction
 *                           false = caller already opened one (function never commits)
 * @return bool  true if applied; false if duplicate (already applied) or insufficient funds
 */
function av_wallet_apply(mysqli $conn, int $userId, float $delta, string $entryType, string $refType, string $refId, string $note = '', bool $manageTx = true, ?string &$why = null): bool {
    $delta = round($delta, 2);
    if ($delta == 0.0) { $why = 'zero'; return false; }
    if ($manageTx) $conn->begin_transaction();
    try {
        $lk = $conn->prepare("SELECT b2c_wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $lk->bind_param("i", $userId);
        $lk->execute();
        $row = $lk->get_result()->fetch_assoc();
        $lk->close();
        if (!$row) { if ($manageTx) $conn->rollback(); $why = 'no_user'; return false; }

        $new = round((float)$row['b2c_wallet_balance'] + $delta, 2);
        if ($new < 0) { if ($manageTx) $conn->rollback(); $why = 'insufficient'; return false; }

        $ins = $conn->prepare("INSERT INTO wallet_ledger (user_id, entry_type, amount, balance_after, ref_type, ref_id, note) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("isddsss", $userId, $entryType, $delta, $new, $refType, $refId, $note);
        try {
            $ins->execute();
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) { $ins->close(); if ($manageTx) $conn->rollback(); $why = 'duplicate'; return false; }
            throw $e;
        }
        if ($ins->errno === 1062) { $ins->close(); if ($manageTx) $conn->rollback(); $why = 'duplicate'; return false; }
        $ins->close();

        $up = $conn->prepare("UPDATE users SET b2c_wallet_balance = ? WHERE id = ?");
        $up->bind_param("di", $new, $userId);
        $up->execute();
        $up->close();

        if ($manageTx) $conn->commit();
        return true;
    } catch (Throwable $e) {
        if ($manageTx) $conn->rollback();
        error_log('AvenPay av_wallet_apply failed: ' . $e->getMessage());
        $why = 'error';
        if (!$manageTx) throw $e;
        return false;
    }
}
