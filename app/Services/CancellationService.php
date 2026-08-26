<?php
namespace App\Services;

use Core\Database;

/**
 * WHISKER — Cancelling an order
 *
 * One place for what cancelling means, because it happens from two directions:
 * a customer cancelling their own order, and a shopkeeper cancelling from the
 * admin. Both restore stock, adjust the customer's totals, and — when the
 * shop has asked for it — send the money back.
 *
 * Stock only returns for orders that had not shipped. Once a parcel is with
 * the carrier the goods are out of the building, and adding them back would
 * overstate what is on the shelf; if the parcel comes back the shopkeeper
 * adjusts stock when it arrives.
 */
class CancellationService
{
    /** A customer may only cancel while the order is still in the shop's hands. */
    public const CUSTOMER_CANCELLABLE = ['pending', 'processing'];

    /** Cancelling these means the goods never left, so stock goes back. */
    private const STOCK_RETURNS_FROM = ['pending', 'processing', 'paid', 'payment_failed'];

    /** Payment states that mean money is actually held. */
    private const PAID = ['captured', 'authorized', 'partially_refunded'];

    public static function autoRefundEnabled(): bool
    {
        return Database::setting('checkout', 'auto_refund_on_cancel', '0') === '1';
    }

    /**
     * How long after placing an order a customer may still cancel it, in
     * minutes. Zero means no time limit, which is the default: the status
     * rules alone decide.
     */
    public static function cancelWindowMinutes(): int
    {
        return max(0, (int) Database::setting('checkout', 'cancel_window_minutes', '0'));
    }

    /**
     * The moment the window closes, or null when there is no limit or the
     * order carries no date to measure from.
     */
    public static function cancelDeadline(array $order): ?int
    {
        $minutes = self::cancelWindowMinutes();
        if ($minutes <= 0) return null;

        $placed = strtotime((string) ($order['created_at'] ?? ''));
        return $placed ? $placed + ($minutes * 60) : null;
    }

    /**
     * Whether the order is still inside its cancellation window.
     *
     * An order with no readable date is treated as still open rather than
     * closed — a missing timestamp is our problem, not the customer's.
     */
    public static function withinCancelWindow(array $order): bool
    {
        $deadline = self::cancelDeadline($order);
        return $deadline === null || time() <= $deadline;
    }

    public static function customerCanCancel(array $order): bool
    {
        return in_array($order['status'] ?? '', self::CUSTOMER_CANCELLABLE, true)
            && self::withinCancelWindow($order);
    }

    /**
     * Whether the storefront tells the customer when their window closes.
     * Some shops would rather not draw attention to it; the window still
     * applies either way.
     */
    public static function showsDeadline(): bool
    {
        return Database::setting('checkout', 'show_cancel_deadline', '1') === '1';
    }

    /**
     * The deadline to put in front of the customer, or null when there is
     * nothing to say — no window set, no date to measure from, or the shop
     * has asked not to show it.
     */
    public static function deadlineToShow(array $order): ?int
    {
        return self::showsDeadline() ? self::cancelDeadline($order) : null;
    }

    /**
     * Cancel an order.
     *
     * The status change is an atomic compare-and-set, so two requests racing
     * each other cannot both restore stock.
     *
     * @param array    $order    the order row as it currently stands
     * @param int|null $adminId  set when a shopkeeper is doing this, null for a customer
     * @param bool     $customer true when the customer initiated it, which limits
     *                           the statuses the order may be cancelled from
     *
     * @return array{
     *   success:bool, message:string, already:bool,
     *   stock_restored:bool, refund:?array
     * }
     */
    public static function cancel(array $order, ?int $adminId = null, bool $customer = false): array
    {
        $orderId = (int) $order['id'];
        $from    = (string) ($order['status'] ?? '');

        // The window applies to customers only. A shopkeeper cancelling on
        // their behalf is a decision, not a self-service action.
        if ($customer && !self::withinCancelWindow($order)) {
            return self::fail(self::windowClosedMessage());
        }

        $allowedFrom = $customer ? self::CUSTOMER_CANCELLABLE : self::everythingButCancelled();
        if (!in_array($from, $allowedFrom, true)) {
            if ($from === 'refunded') {
                return self::fail('This order has already been refunded.');
            }
            return self::fail($customer
                ? 'This order can no longer be cancelled.'
                : 'This order is already cancelled.');
        }

        $placeholders = implode(',', array_fill(0, count($allowedFrom), '?'));
        $params = array_merge([$orderId], $allowedFrom);

        $changed = Database::query(
            "UPDATE wk_orders SET status='cancelled' WHERE id=? AND status IN ({$placeholders})",
            $params
        )->rowCount();

        if ($changed === 0) {
            // Someone else got there first. The order IS cancelled, which is
            // what the caller wanted, so this is not an error — but nothing
            // else may run, or stock would be returned twice.
            return [
                'success' => true, 'already' => true, 'stock_restored' => false, 'refund' => null,
                'message' => 'This order was already cancelled.',
            ];
        }

        $stockRestored = false;
        if (in_array($from, self::STOCK_RETURNS_FROM, true)) {
            self::restoreStock($orderId);
            $stockRestored = true;
        }

        self::adjustCustomerTotals($order);

        $refund = self::refundIfAsked($order, $adminId);

        return [
            'success'        => true,
            'already'        => false,
            'stock_restored' => $stockRestored,
            'refund'         => $refund,
            'message'        => self::describe($stockRestored, $from, $refund),
        ];
    }

    /**
     * Cancelled and refunded are both ends of the road. Re-cancelling a
     * refunded order would write over that status, and take the customer's
     * order count down a second time for a cancellation that already happened.
     */
    private static function everythingButCancelled(): array
    {
        return ['pending', 'processing', 'paid', 'shipped', 'delivered', 'payment_failed'];
    }

    private static function restoreStock(int $orderId): void
    {
        try {
            $items = Database::fetchAll(
                "SELECT product_id, quantity, variant_combo_id FROM wk_order_items WHERE order_id=?",
                [$orderId]
            );
        } catch (\Exception $e) {
            $items = Database::fetchAll(
                "SELECT product_id, quantity FROM wk_order_items WHERE order_id=?",
                [$orderId]
            );
        }

        foreach ($items as $item) {
            Database::query(
                "UPDATE wk_products SET stock_quantity = stock_quantity + ? WHERE id=?",
                [$item['quantity'], $item['product_id']]
            );
            if (!empty($item['variant_combo_id'])) {
                try {
                    Database::query(
                        "UPDATE wk_variant_combos SET stock_quantity = stock_quantity + ? WHERE id=?",
                        [$item['quantity'], $item['variant_combo_id']]
                    );
                } catch (\Exception $e) {
                    // Variant table arrives with a migration.
                }
            }
        }
    }

    private static function adjustCustomerTotals(array $order): void
    {
        if (empty($order['customer_id'])) return;
        Database::query(
            "UPDATE wk_customers
                SET total_orders = GREATEST(0, total_orders - 1),
                    total_spent  = GREATEST(0, total_spent - ?)
              WHERE id=?",
            [$order['total'], $order['customer_id']]
        );
    }

    /**
     * Send the money back, when the shop has turned that on and there is money
     * to send. A refund that fails does not fail the cancellation — the order
     * is cancelled either way, and the shopkeeper is told what still needs
     * doing rather than being left with a half-cancelled order.
     */
    private static function refundIfAsked(array $order, ?int $adminId): ?array
    {
        if (!self::autoRefundEnabled()) return null;
        if (!in_array($order['payment_status'] ?? '', self::PAID, true)) return null;

        try {
            $outstanding = RefundService::refundableAmount($order);
            if ($outstanding <= 0) return null;

            return RefundService::issue(
                $order,
                $outstanding,
                'Order cancelled',
                $adminId
            );
        } catch (\Throwable $e) {
            error_log('Whisker: auto-refund on cancelling order ' . ($order['order_number'] ?? '?') . ' failed — ' . $e->getMessage());
            return [
                'success' => false,
                'status'  => 'failed',
                'ref'     => null,
                'message' => 'The order is cancelled, but the refund could not be sent. Refund it from the order page.',
            ];
        }
    }

    private static function describe(bool $stockRestored, string $from, ?array $refund): string
    {
        $parts = ['Order cancelled.'];

        if ($stockRestored) {
            $parts[] = 'Stock has been restored.';
        } elseif (in_array($from, ['shipped', 'delivered'], true)) {
            $parts[] = 'Stock was not restored, because the order had already ' . $from . '.';
        }

        if ($refund !== null) {
            $parts[] = $refund['success']
                ? ($refund['message'] ?? 'Refund sent.')
                : 'Refund not sent: ' . ($refund['message'] ?? 'the gateway refused it.');
        }

        return implode(' ', $parts);
    }

    /** Says how long the window was, so the answer is not just "no". */
    private static function windowClosedMessage(): string
    {
        $minutes = self::cancelWindowMinutes();
        $window = $minutes % 1440 === 0 && $minutes >= 1440
            ? (($minutes / 1440) . ' day' . ($minutes === 1440 ? '' : 's'))
            : ($minutes % 60 === 0
                ? (($minutes / 60) . ' hour' . ($minutes === 60 ? '' : 's'))
                : ($minutes . ' minutes'));

        return 'Orders can only be cancelled within ' . $window . ' of being placed. '
             . 'Get in touch and we will sort it out.';
    }

    private static function fail(string $message): array
    {
        return [
            'success' => false, 'already' => false, 'stock_restored' => false,
            'refund' => null, 'message' => $message,
        ];
    }
}
