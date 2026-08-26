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

    public static function customerCanCancel(array $order): bool
    {
        return in_array($order['status'] ?? '', self::CUSTOMER_CANCELLABLE, true);
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

    private static function fail(string $message): array
    {
        return [
            'success' => false, 'already' => false, 'stock_restored' => false,
            'refund' => null, 'message' => $message,
        ];
    }
}
