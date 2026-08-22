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

    /**
     * Check the saved credentials against the provider.
     *
     * Gateways override this with a cheap authenticated call. The default
     * reports that the gateway cannot be checked automatically, so a provider
     * without a suitable endpoint does not look broken.
     *
     * @return array{success:bool, message:string}
     */
    public function testConnection(): array
    {
        return ['success' => false, 'message' => 'This gateway cannot be checked automatically. Verify the details in your provider dashboard.'];
    }

    /**
     * Small HTTP helper for credential checks.
     *
     * @return array{status:int, body:string, error:string}
     */
    protected function probe(string $url, array $headers = [], ?string $basicAuth = null): array
    {
        $ch = curl_init($url);
        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($headers) $opts[CURLOPT_HTTPHEADER] = $headers;
        if ($basicAuth !== null) $opts[CURLOPT_USERPWD] = $basicAuth;
        curl_setopt_array($ch, $opts);
        $body   = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);
        return ['status' => $status, 'body' => $body, 'error' => $error];
    }

    /**
     * Shape a gateway refund reply into the form RefundService expects.
     *
     * The case that matters is a call that never completed — a timeout or a
     * dropped connection. The gateway may well have processed the refund, so
     * reporting failure invites a retry that refunds a second time. Those come
     * back as 'unknown', which stops the process and asks a human to check.
     */
    protected function refundUnknown(string $why): array
    {
        return [
            'success'   => false,
            'status'    => 'unknown',
            'refund_id' => null,
            'message'   => 'Could not confirm the refund with the gateway (' . $why . '). '
                         . 'Check the gateway dashboard before trying again.',
        ];
    }

    protected function refundFailed(string $message): array
    {
        return ['success' => false, 'status' => 'failed', 'refund_id' => null, 'message' => $message];
    }

    protected function refundOk(?string $refundId, string $status = 'completed', string $message = ''): array
    {
        return ['success' => true, 'status' => $status, 'refund_id' => $refundId, 'message' => $message];
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

        // Conditional UPDATE so concurrent webhook + verify-callback writers
        // cannot both capture the order — "only one writer wins" is enforced
        // by the WHERE clause, not by timing.
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
