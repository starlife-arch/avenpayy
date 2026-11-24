-- AvenPay: enforce one unique wallet ID per account.
-- NULL remains allowed for existing accounts that have not claimed an ID.
-- Run only after reviewing the existing wallet_id values in the live database.
ALTER TABLE users
    ADD UNIQUE KEY uq_users_wallet_id (wallet_id);
