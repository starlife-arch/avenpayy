<?php
/** Payment-link rules: active/expired/single-use + amount limits. One place, used by the page and the STK endpoint. */

/** @return string '' if the link can take a payment now, otherwise a customer-facing reason */
function av_link_unavailable_reason(mysqli $conn, array $L): string {
    if (($L['status'] ?? 'active') !== 'active') return 'This payment link has been disabled.';
    if (!empty($L['expires_at']) && strtotime($L['expires_at']) < time()) return 'This payment link has expired.';
    if (!empty($L['single_use']) && av_link_has_column($conn, 'mpesa_transactions', 'payment_link_id')) {
        $st = $conn->prepare("SELECT 1 FROM mpesa_transactions WHERE payment_link_id = ? AND (status = 'SUCCESS' OR (status = 'PENDING' AND transaction_date > (NOW() - INTERVAL 2 MINUTE))) LIMIT 1");
        $id = (int)$L['id'];
        $st->bind_param("i", $id);
        $st->execute();
        $used = (bool)$st->get_result()->fetch_row();
        $st->close();
        if ($used) return 'This payment link has already been used.';
    }
    return '';
}

/** Returns [amountToCharge, error]. Fixed links ignore whatever the browser sent. */
function av_link_resolve_amount(array $L, int $postedAmount): array {
    if ((int)($L['is_fixed'] ?? 0) === 1) return [(int) round((float)$L['amount']), ''];
    if ($postedAmount < 1) return [0, 'Enter a valid amount.'];
    if (isset($L['min_amount']) && $L['min_amount'] !== null && $postedAmount < (float)$L['min_amount']) return [0, 'Minimum amount for this link is KES ' . number_format((float)$L['min_amount'])];
    if (isset($L['max_amount']) && $L['max_amount'] !== null && $postedAmount > (float)$L['max_amount']) return [0, 'Maximum amount for this link is KES ' . number_format((float)$L['max_amount'])];
    return [$postedAmount, ''];
}

function av_link_has_column(mysqli $conn, string $table, string $col): bool {
    static $c = [];
    $k = "$table.$col";
    if (isset($c[$k])) return $c[$k];
    $st = $conn->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $st->bind_param("ss", $table, $col); $st->execute();
    $c[$k] = (bool)$st->get_result()->fetch_row(); $st->close();
    return $c[$k];
}

/** Settlement key must belong to the logged-in merchant. */
function av_owned_api_key(mysqli $conn, string $key, int $userId): bool {
    $st = $conn->prepare("SELECT 1 FROM settlements WHERE x_api_key = ? AND user_id = ? LIMIT 1");
    $st->bind_param("si", $key, $userId); $st->execute();
    $ok = (bool)$st->get_result()->fetch_row(); $st->close();
    return $ok;
}
