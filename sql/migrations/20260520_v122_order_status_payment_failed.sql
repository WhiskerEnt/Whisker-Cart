-- 20260520_v122_order_status_payment_failed.sql
--
-- Add 'payment_failed' to wk_orders.status ENUM.
--
-- Why: CheckoutController writes status='payment_failed' when gateway init
-- fails, and DashboardController's expired-order sweep reads that value
-- ("status IN ('pending','payment_failed')"). The original schema.sql ENUM
-- didn't include it, so on MySQL hosts with strict mode the write would
-- be rejected outright; on loose mode it silently coerces to empty string
-- and orphans the order from both UIs that filter by status.
--
-- Discovered during the Finding 27 fix when the OrderController status
-- whitelist needed to mirror the ENUM. Brings the schema in line with
-- existing code paths.
--
-- Forward-only: a row whose status is currently a coerced empty string
-- won't be repaired by this migration (we don't know its intended state).
-- Admins can manually correct stuck orders via the status dropdown once
-- the ENUM accepts the value.

ALTER TABLE wk_orders
    MODIFY COLUMN status ENUM(
        'pending', 'processing', 'paid', 'shipped',
        'delivered', 'cancelled', 'refunded', 'payment_failed'
    ) DEFAULT 'pending';
