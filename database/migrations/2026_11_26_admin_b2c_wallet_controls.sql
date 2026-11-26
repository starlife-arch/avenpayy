-- Admin B2C wallet controls: immutable actor attribution and held-balance snapshots.
-- Run once after the existing wallet_ledger migration has been installed.
ALTER TABLE wallet_ledger
    ADD COLUMN IF NOT EXISTS admin_id INT NULL DEFAULT NULL AFTER user_id,
    ADD COLUMN IF NOT EXISTS held_after DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER balance_after;

-- Seed the latest existing ledger row per wallet with the current held amount.
-- Earlier rows predate held-balance auditing and remain unchanged.
UPDATE wallet_ledger l
JOIN (
    SELECT user_id, MAX(id) AS latest_id
    FROM wallet_ledger
    GROUP BY user_id
) latest ON latest.latest_id = l.id
JOIN users u ON u.id = l.user_id
SET l.held_after = COALESCE(u.on_hold_amount, 0.00);
