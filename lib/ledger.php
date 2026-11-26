<?php
/**
 * B2C wallet ledger. Balance adjustments use av_wallet_apply(); held-fund transfers use
 * av_wallet_hold_apply(). Both lock the wallet row and commit the wallet state with an
 * immutable ledger row. Admin operations also carry a globally unique operation key and actor ID.
 *
 * @param float  $delta      signed amount (+ credit, - debit)
 * @param bool   $manageTx   true  = function opens/commits its own transaction
 *                           false = caller already opened one (function never commits)
 * @return bool  true if applied; false if duplicate (already applied) or insufficient funds
 */
function av_wallet_apply(mysqli $conn, int $userId, float $delta, string $entryType, string $refType, string $refId, string $note = '', bool $manageTx = true, ?string &$why = null, ?int $adminId = null): bool {
    $delta = round($delta, 2);
    if ($delta == 0.0) { $why = 'zero'; return false; }
    if ($manageTx) $conn->begin_transaction();
    try {
        $lk = $conn->prepare("SELECT b2c_wallet_balance, on_hold_amount FROM users WHERE id = ? FOR UPDATE");
        $lk->bind_param("i", $userId);
        $lk->execute();
        $row = $lk->get_result()->fetch_assoc();
        $lk->close();
        if (!$row) { if ($manageTx) $conn->rollback(); $why = 'no_user'; return false; }

        $new = round((float)$row['b2c_wallet_balance'] + $delta, 2);
        $heldAfter = round((float)($row['on_hold_amount'] ?? 0), 2);
        if ($new < 0) { if ($manageTx) $conn->rollback(); $why = 'insufficient'; return false; }

        $adminOperationKey = $adminId !== null ? $refId : null;
        $ins = $conn->prepare("INSERT INTO wallet_ledger (user_id, admin_id, admin_operation_key, entry_type, amount, balance_after, held_after, ref_type, ref_id, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("iissdddsss", $userId, $adminId, $adminOperationKey, $entryType, $delta, $new, $heldAfter, $refType, $refId, $note);
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

/**
 * Move value between the available and held portions of a B2C wallet.
 * This is not a credit/debit: the customer's total owned funds remain unchanged.
 * The wallet row, immutable ledger entry, and held amount update commit atomically.
 */
function av_wallet_hold_apply(mysqli $conn, int $userId, float $amount, string $action, string $refId, string $note, int $adminId, ?string &$why = null): bool {
    $amount = round($amount, 2);
    if ($amount <= 0 || !in_array($action, ['hold', 'release'], true)) {
        $why = 'invalid';
        return false;
    }

    $entryType = $action === 'hold' ? 'admin_hold' : 'admin_release';
    $delta = $action === 'hold' ? -$amount : $amount;
    $conn->begin_transaction();
    try {
        $lk = $conn->prepare("SELECT b2c_wallet_balance, on_hold_amount FROM users WHERE id = ? FOR UPDATE");
        $lk->bind_param("i", $userId);
        $lk->execute();
        $row = $lk->get_result()->fetch_assoc();
        $lk->close();

        if (!$row) {
            $conn->rollback();
            $why = 'no_user';
            return false;
        }

        $balance = round((float)$row['b2c_wallet_balance'], 2);
        $held = round((float)($row['on_hold_amount'] ?? 0), 2);
        if ($action === 'hold' && $balance < $amount) {
            $conn->rollback();
            $why = 'insufficient';
            return false;
        }
        if ($action === 'release' && $held < $amount) {
            $conn->rollback();
            $why = 'insufficient_held';
            return false;
        }

        $newBalance = round($balance + $delta, 2);
        $newHeld = round($held - $delta, 2);
        $refType = 'admin_hold';
        $adminOperationKey = $refId;
        $ins = $conn->prepare("INSERT INTO wallet_ledger (user_id, admin_id, admin_operation_key, entry_type, amount, balance_after, held_after, ref_type, ref_id, note) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("iissdddsss", $userId, $adminId, $adminOperationKey, $entryType, $delta, $newBalance, $newHeld, $refType, $refId, $note);
        try {
            $ins->execute();
        } catch (mysqli_sql_exception $e) {
            if ((int)$e->getCode() === 1062) {
                $ins->close();
                $conn->rollback();
                $why = 'duplicate';
                return false;
            }
            throw $e;
        }
        $ins->close();

        $up = $conn->prepare("UPDATE users SET b2c_wallet_balance = ?, on_hold_amount = ? WHERE id = ?");
        $up->bind_param("ddi", $newBalance, $newHeld, $userId);
        $up->execute();
        $up->close();

        $conn->commit();
        return true;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('AvenPay av_wallet_hold_apply failed: ' . $e->getMessage());
        $why = 'error';
        return false;
    }
}
