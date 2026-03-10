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
            'currency'         => $data['currency'] ?? 'INR',
            'status'           => $data['status'] ?? 'initiated',
            'gateway_response' => json_encode($response),
        ]);
    }

    /**
     * Mark order as paid — with idempotency and status guard.
     * Skips if order is already paid/shipped/delivered (prevents double-crediting).
     * Optionally verifies the paid amount matches the order total.
     */
    protected function markOrderPaid(int $orderId, string $paymentId, ?float $paidAmount = null): void
    {
        // Idempotency: skip if already paid/processed
        $order = Database::fetch("SELECT id, status, payment_status, total FROM wk_orders WHERE id=?", [$orderId]);
        if (!$order) return;

        // Don't re-process orders that are already paid, shipped, or delivered
        if (in_array($order['payment_status'], ['captured']) || in_array($order['status'], ['paid', 'shipped', 'delivered'])) {
            return; // Already processed — idempotent skip
        }

        // Amount verification: if gateway provides paid amount, verify it matches order total
        if ($paidAmount !== null) {
            $expectedAmount = (float)$order['total'];
            // Allow 1 unit tolerance for rounding (e.g. ₹999.99 vs ₹1000.00)
            if (abs($paidAmount - $expectedAmount) > 1.00) {
                // Amount mismatch — log but don't mark as paid
                Database::query(
                    "UPDATE wk_payment_transactions SET status='failed', gateway_response=JSON_SET(COALESCE(gateway_response,'{}'), '$.amount_mismatch', ?) WHERE order_id=? AND gateway_code=? ORDER BY id DESC LIMIT 1",
                    [json_encode(['expected' => $expectedAmount, 'received' => $paidAmount]), $orderId, $this->code]
                );
                return;
            }
        }

        Database::update('wk_orders', [
            'payment_status' => 'captured', 'payment_id' => $paymentId,
            'payment_gateway' => $this->code, 'status' => 'paid',
        ], 'id=?', [$orderId]);

        // Update transaction record
        Database::query(
            "UPDATE wk_payment_transactions SET status='success', transaction_id=? WHERE order_id=? AND gateway_code=? AND status!='success' ORDER BY id DESC LIMIT 1",
            [$paymentId, $orderId, $this->code]
        );

        // Reduce stock (only once due to idempotency guard above)
        $items = Database::fetchAll("SELECT product_id, quantity FROM wk_order_items WHERE order_id=?", [$orderId]);
        foreach ($items as $item) {
            Database::query("UPDATE wk_products SET stock_quantity=GREATEST(0,stock_quantity-?) WHERE id=?",
                [$item['quantity'], $item['product_id']]);
        }
    }
}
