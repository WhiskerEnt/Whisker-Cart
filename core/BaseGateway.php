<?php
namespace Core;

abstract class BaseGateway implements PaymentGatewayInterface
{
    protected array $config;
    protected bool $testMode;
    protected string $code;

    public function __construct(string $gatewayCode)
    {
        $this->code = $gatewayCode;
        $row = Database::fetch(
            "SELECT config, is_test_mode FROM wk_payment_gateways WHERE gateway_code=?",
            [$gatewayCode]
        );
        $this->config = $row ? (json_decode($row['config'], true) ?? []) : [];
        $this->testMode = $row ? (bool)$row['is_test_mode'] : true;
    }

    protected function cfg(string $key): string
    {
        if ($this->testMode && isset($this->config["test_{$key}"])) {
            return $this->config["test_{$key}"];
        }
        return $this->config[$key] ?? '';
    }

    protected function logTransaction(int $orderId, array $data): int
    {
        // Redact sensitive keys before logging
        $response = $data['response'] ?? [];
        $redactKeys = ['key_secret', 'secret_key', 'webhook_secret', 'working_key', 'api_key', 'ipn_secret'];
        foreach ($redactKeys as $k) {
            if (isset($response[$k])) $response[$k] = '***REDACTED***';
        }

        return Database::insert('wk_payment_transactions', [
            'order_id'         => $orderId,
            'gateway_code'     => $this->code,
            'transaction_id'   => $data['transaction_id'] ?? null,
            'gateway_order_id' => $data['gateway_order_id'] ?? null,
            'amount'           => $data['amount'],
            // Fall back to the store's configured currency, not hardcoded INR —
            // gateways charge in the order currency, which is the store currency.
            'currency'         => $data['currency']
                ?? (Database::setting('general', 'currency') ?: 'INR'),
            'status'           => $data['status'] ?? 'initiated',
            'gateway_response' => json_encode($response),
        ]);
    }

    /**
     * Mark order as paid — with idempotency and amount verification.
     *
     * Stock is reserved at order creation (CheckoutController::process) and
     * released on cancellation/expiry. This method only transitions payment
     * status and updates the transaction log — it does NOT touch stock.
     *
     * Safe to call multiple times; subsequent calls are no-ops once the order
     * reaches a paid/shipped/delivered state.
     */
    public function markOrderPaid(int $orderId, string $paymentId, ?float $paidAmount = null): void
    {
        $order = Database::fetch(
            "SELECT id, status, payment_status, total FROM wk_orders WHERE id=?",
            [$orderId]
        );
        if (!$order) return;

        // Idempotency guard: skip if already paid/processed.
        if ($order['payment_status'] === 'captured'
            || in_array($order['status'], ['paid', 'shipped', 'delivered'], true)) {
            return;
        }

        // Amount verification: if the gateway provides the paid amount, it must
        // match the order total within a 1-unit rounding tolerance.
        if ($paidAmount !== null) {
            $expectedAmount = (float)$order['total'];
            if (abs($paidAmount - $expectedAmount) > 1.00) {
                Database::query(
                    "UPDATE wk_payment_transactions
                        SET status='failed',
                            gateway_response=JSON_SET(COALESCE(gateway_response,'{}'), '$.amount_mismatch', ?)
                      WHERE order_id=? AND gateway_code=?
                      ORDER BY id DESC LIMIT 1",
                    [
                        json_encode(['expected' => $expectedAmount, 'received' => $paidAmount]),
                        $orderId,
                        $this->code,
                    ]
                );
                return;
            }
        }

        // Finding 5: previously this was a plain UPDATE that left a race
        // window between the SELECT-based idempotency check and the write.
        // Concurrent webhook + verify-callback could both reach this point
        // and both run the UPDATE. End-state was the same (idempotent
        // values), but the conditional UPDATE makes "only one writer wins"
        // a property of the schema, not of timing luck.
        $affected = Database::query(
            "UPDATE wk_orders
                SET payment_status  = 'captured',
                    payment_id      = ?,
                    payment_gateway = ?,
                    status          = 'paid'
              WHERE id = ?
                AND payment_status != 'captured'
                AND status NOT IN ('paid','shipped','delivered')",
            [$paymentId, $this->code, $orderId]
        )->rowCount();

        if ($affected === 0) {
            // Another writer already captured this order. Don't bump the
            // transaction record either — the first winner did it.
            return;
        }

        Database::query(
            "UPDATE wk_payment_transactions
                SET status='success', transaction_id=?
              WHERE order_id=? AND gateway_code=? AND status!='success'
              ORDER BY id DESC LIMIT 1",
            [$paymentId, $orderId, $this->code]
        );
    }
}
