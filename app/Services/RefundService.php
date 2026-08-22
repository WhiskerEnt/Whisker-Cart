<?php
namespace App\Services;

use Core\Database;
use Core\PluginManager;

/**
 * WHISKER — Refunds
 *
 * Order of operations matters here. The gateway is asked to move the money
 * first, and only its answer decides what gets written down. Writing the
 * record first and reconciling afterwards would leave the shop showing a
 * refund the customer never received whenever the call fails.
 *
 * Every attempt is recorded, including failures, so the history of what was
 * tried survives. The reference is generated before the call and travels with
 * it as the idempotency key, so a retry of the same attempt cannot pay twice.
 */
class RefundService
{
    /** A refund the gateway never confirmed. Nothing further may be sent. */
    public const STATUS_UNKNOWN = 'unknown';

    /**
     * Money already returned to the customer: settled refunds, plus any whose
     * outcome we could not confirm. Unknown ones count against the balance so
     * an ambiguous attempt can never be quietly refunded a second time.
     */
    public static function refundedTotal(int $orderId): float
    {
        return (float) Database::fetchValue(
            "SELECT COALESCE(SUM(amount), 0) FROM wk_refunds
              WHERE order_id = ? AND status IN ('completed','pending','unknown')",
            [$orderId]
        );
    }

    /**
     * Money can only be sent back if it was taken in the first place.
     *
     * Checked as an allowlist rather than by excluding 'pending', so a status
     * added later cannot accidentally become refundable.
     */
    public static function isRefundable(array $order): bool
    {
        return in_array(
            $order['payment_status'] ?? '',
            ['captured', 'authorized', 'partially_refunded', 'refunded'],
            true
        );
    }

    public static function refundableAmount(array $order): float
    {
        if (!self::isRefundable($order)) return 0.0;
        return max(0.0, round((float) $order['total'] - self::refundedTotal((int) $order['id']), 2));
    }

    /** True while an unconfirmed attempt is outstanding. */
    public static function hasUnresolved(int $orderId): bool
    {
        return (bool) Database::fetchValue(
            "SELECT 1 FROM wk_refunds WHERE order_id = ? AND status = 'unknown' LIMIT 1",
            [$orderId]
        );
    }

    public static function forOrder(int $orderId): array
    {
        return Database::fetchAll(
            "SELECT * FROM wk_refunds WHERE order_id = ? ORDER BY created_at DESC, id DESC",
            [$orderId]
        );
    }

    /**
     * RFN-YYMMDD-XXXXXX. Unique in the table, so it doubles as the
     * idempotency key sent to the gateway.
     */
    public static function generateRef(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $ref = 'RFN-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $taken = Database::fetchValue("SELECT 1 FROM wk_refunds WHERE refund_ref = ?", [$ref]);
            if (!$taken) return $ref;
        }
        throw new \RuntimeException('Could not allocate a refund reference.');
    }

    /**
     * Issue a refund.
     *
     * $manual records money the shopkeeper already returned by hand — a bank
     * transfer, a crypto send — without contacting the gateway.
     *
     * @return array{success:bool, status:string, ref:?string, message:string}
     */
    public static function issue(array $order, float $amount, string $reason, ?int $adminId, bool $manual = false): array
    {
        $orderId  = (int) $order['id'];
        $currency = $order['currency'] ?: (Database::setting('general', 'currency') ?: 'INR');
        $amount   = round($amount, CurrencyService::decimals($currency));

        if ($amount <= 0) {
            return self::fail('Enter an amount greater than zero.');
        }
        if (!self::isRefundable($order)) {
            return self::fail('This order has not been paid, so there is nothing to refund.');
        }
        if (self::hasUnresolved($orderId)) {
            return self::fail(
                'An earlier refund on this order was never confirmed by the gateway. '
                . 'Check the gateway dashboard and resolve it before sending another.'
            );
        }

        $remaining = self::refundableAmount($order);
        if ($amount > $remaining) {
            return self::fail(sprintf(
                'That is more than the %s still available to refund on this order.',
                CurrencyService::format($remaining, $currency)
            ));
        }

        $ref = self::generateRef();

        if ($manual) {
            self::record($orderId, $ref, null, null, $amount, $currency, $reason, 'completed', true,
                'Recorded by hand — no gateway was contacted.', [], $adminId);
            self::syncOrder($order);
            self::notifyCustomer($order, $ref, $amount, $currency, 'completed', null, true);
            return ['success' => true, 'status' => 'completed', 'ref' => $ref,
                    'message' => 'Refund ' . $ref . ' recorded.'];
        }

        $gatewayCode = $order['payment_gateway'] ?? '';
        $paymentId   = $order['payment_id'] ?? '';
        if ($gatewayCode === '' || $paymentId === '') {
            return self::fail('This order has no gateway payment to refund against. Record it as a manual refund instead.');
        }

        $gateway = PluginManager::loadGateway($gatewayCode);
        if (!$gateway) {
            return self::fail('The ' . $gatewayCode . ' gateway is not installed, so it cannot be asked for a refund.');
        }

        // Ask the gateway first. Nothing is written until it answers.
        try {
            $result = $gateway->refund($paymentId, $amount, [
                'currency'        => $currency,
                'idempotency_key' => $ref,
                'reason'          => $reason,
            ]);
        } catch (\Throwable $e) {
            error_log('Whisker refund ' . $ref . ': ' . $e->getMessage());
            $result = ['success' => false, 'status' => self::STATUS_UNKNOWN, 'refund_id' => null,
                       'message' => 'The refund call did not complete. Check the gateway dashboard before trying again.'];
        }

        $status = $result['status'] ?? (($result['success'] ?? false) ? 'completed' : 'failed');
        $message = (string) ($result['message'] ?? '');

        self::record(
            $orderId, $ref, $gatewayCode, $result['refund_id'] ?? null,
            $amount, $currency, $reason, $status, false, $message, $result, $adminId
        );

        if ($status === 'completed' || $status === 'pending') {
            self::syncOrder($order);
            self::notifyCustomer($order, $ref, $amount, $currency, $status, $gatewayCode, false);
            return [
                'success' => true,
                'status'  => $status,
                'ref'     => $ref,
                'message' => $status === 'pending'
                    ? 'Refund ' . $ref . ' sent. The gateway is still processing it.'
                    : 'Refund ' . $ref . ' completed.',
            ];
        }

        return [
            'success' => false,
            'status'  => $status,
            'ref'     => $ref,
            'message' => $message !== '' ? $message : 'The gateway did not accept the refund.',
        ];
    }

    /**
     * Email the customer their refund reference.
     *
     * The money has already moved by this point, so a mail server problem
     * must not turn a successful refund into a reported failure.
     */
    private static function notifyCustomer(
        array $order, string $ref, float $amount, string $currency,
        string $status, ?string $gatewayCode, bool $manual
    ): void {
        try {
            EmailService::sendRefundConfirmation($order, [
                'refund_ref'   => $ref,
                'amount'       => $amount,
                'currency'     => $currency,
                'status'       => $status,
                'gateway_code' => $gatewayCode,
                'is_manual'    => $manual ? 1 : 0,
                'created_at'   => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('Whisker: refund email for ' . $ref . ' failed — ' . $e->getMessage());
        }
    }

    private static function record(
        int $orderId, string $ref, ?string $gatewayCode, ?string $gatewayRefundId,
        float $amount, string $currency, string $reason, string $status,
        bool $manual, string $message, array $raw, ?int $adminId
    ): void {
        Database::insert('wk_refunds', [
            'order_id'          => $orderId,
            'refund_ref'        => $ref,
            'gateway_code'      => $gatewayCode,
            'gateway_refund_id' => $gatewayRefundId,
            'amount'            => $amount,
            'currency'          => $currency,
            'reason'            => $reason !== '' ? mb_substr($reason, 0, 255) : null,
            'status'            => $status,
            'is_manual'         => $manual ? 1 : 0,
            'message'           => $message !== '' ? $message : null,
            'gateway_response'  => json_encode(self::redact($raw)),
            'admin_id'          => $adminId,
        ]);
    }

    private static function redact(array $raw): array
    {
        unset($raw['raw'], $raw['request']);
        return $raw;
    }

    /**
     * Bring the order's own status in line with what has been refunded.
     */
    private static function syncOrder(array $order): void
    {
        $orderId = (int) $order['id'];
        $refunded = self::refundedTotal($orderId);
        $total    = (float) $order['total'];
        $full     = $refunded >= round($total, 2) - 0.001;

        Database::update('wk_orders', [
            'payment_status' => $full ? 'refunded' : 'partially_refunded',
            'status'         => $full ? 'refunded' : ($order['status'] ?? 'paid'),
        ], 'id = ?', [$orderId]);
    }

    private static function fail(string $message): array
    {
        return ['success' => false, 'status' => 'failed', 'ref' => null, 'message' => $message];
    }
}
