-- One order per attempt, however many times the button is pressed.
--
-- A shopper on a slow connection taps "Pay" again when nothing appears to
-- happen. Without a key to recognise the second attempt by, that is a second
-- order and, with a gateway attached, a second charge. The unique index is
-- what enforces it: two requests racing each other cannot both insert.
ALTER TABLE wk_orders ADD COLUMN idempotency_key CHAR(64) NULL AFTER order_number;
ALTER TABLE wk_orders ADD UNIQUE KEY uniq_idempotency (idempotency_key);
