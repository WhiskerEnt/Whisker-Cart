-- 20260818_v133_order_item_variants.sql
--
-- Add the variant columns wk_order_items has always been missing.
--
-- CheckoutController::process inserts variant_combo_id + variant_label for any
-- cart line with a variant, and AccountController::cancelOrder and the two
-- expired-order stock sweeps SELECT variant_combo_id. None of those columns
-- existed in schema.sql, so on a standard install:
--   * buying ANY product with variants failed outright — the INSERT threw
--     "Unknown column 'variant_combo_id'" inside the order transaction, the
--     whole thing rolled back, and the customer was bounced back to checkout
--     with "Could not place order. Please try again." forever;
--   * cancelling an order flipped the status, then threw on the stock-restore
--     SELECT, 500ing the page and permanently losing the reserved stock;
--   * the 15-minute expired-order stock sweeps threw on the first row and were
--     swallowed by a blanket catch, so reserved stock was never released.
--
-- Plain ADD COLUMN (portable to real MySQL); the migration runner treats
-- "Duplicate column" as already-applied, so re-runs are safe.

ALTER TABLE wk_order_items ADD COLUMN variant_combo_id INT UNSIGNED DEFAULT NULL;

ALTER TABLE wk_order_items ADD COLUMN variant_label VARCHAR(255) DEFAULT NULL;
