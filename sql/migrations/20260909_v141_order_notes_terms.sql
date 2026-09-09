-- What the shopper asked for, and what they agreed to.
--
-- wk_orders.notes is a JSON blob the shopkeeper maintains (carrier, tracking),
-- so a delivery instruction from the customer needs somewhere of its own
-- rather than sharing a column with data written by a different hand.
--
-- The acceptance is stored as the moment it happened. A boolean would say
-- that someone once agreed to something; a timestamp says when, which is what
-- the question is actually about if it is ever asked.
ALTER TABLE wk_orders ADD COLUMN customer_note TEXT NULL AFTER notes;
ALTER TABLE wk_orders ADD COLUMN terms_accepted_at DATETIME NULL AFTER customer_note;
