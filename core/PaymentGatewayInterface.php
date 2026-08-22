<?php
namespace Core;

/**
 * All payment gateway plugins must implement this interface.
 */
interface PaymentGatewayInterface
{
    public function createOrder(array $order): array;
    public function verifyPayment(array $payload): array;
    /**
     * Refund a captured payment.
     *
     * $options carries 'currency', 'idempotency_key' and 'reason'. Implementations
     * return: success (bool), status (completed|pending|failed|unknown),
     * refund_id (string|null) and message (string).
     *
     * A call that did not complete must report status 'unknown', never 'failed' —
     * the caller treats unknown as "money may have moved" and stops.
     */
    public function refund(string $paymentId, float $amount, array $options = []): array;
    public function getPublicConfig(): array;
    public function webhook(\Core\Request $request): void;
}
