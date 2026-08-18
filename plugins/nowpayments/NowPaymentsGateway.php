<?php
require_once __DIR__ . '/../../core/PaymentGatewayInterface.php';
require_once __DIR__ . '/../../core/BaseGateway.php';

class NowPaymentsGateway extends \Core\BaseGateway
{
    private function apiUrl(): string {
        return $this->testMode ? 'https://api-sandbox.nowpayments.io/v1' : 'https://api.nowpayments.io/v1';
    }

    public function createOrder(array $order): array
    {
        $resp = $this->api('/invoice', ['price_amount'=>$order['total'], 'price_currency'=>strtolower($order['currency']??'usd'),
            'order_id'=>$order['order_number'], 'order_description'=>'Order '.$order['order_number'],
            'ipn_callback_url'=>\Core\View::url('webhook/nowpayments/callback'),
            'success_url'=>\Core\View::url('order-success?order='.$order['order_number']),
            'cancel_url'=>\Core\View::url('checkout?cancelled=1')]);
        $this->logTransaction($order['id'], ['gateway_order_id'=>$resp['id']??null, 'amount'=>$order['total'], 'status'=>'initiated', 'response'=>$resp]);
        return ['invoice_url'=>$resp['invoice_url']??null, 'invoice_id'=>$resp['id']??null];
    }

    public function verifyPayment(array $p): array
    {
        $ok = ($p['payment_status']??'')==='finished';
        if ($ok) {
            \Core\Database::query("UPDATE wk_payment_transactions SET status='success',transaction_id=? WHERE gateway_order_id=?",
                [$p['payment_id']??null, $p['order_id']??'']);
        }
        return ['success'=>$ok, 'payment_id'=>$p['payment_id']??null];
    }

    public function testConnection(): array
    {
        $key = $this->cfg('api_key');
        if ($key === '') return ['success' => false, 'message' => 'No API key saved.'];

        $up = $this->probe('https://api.nowpayments.io/v1/status');
        if ($up['status'] !== 200) return ['success' => false, 'message' => 'NOWPayments API is not reachable right now.'];

        $r = $this->probe('https://api.nowpayments.io/v1/balance', ['x-api-key: ' . $key]);
        if ($r['status'] === 200) return ['success' => true, 'message' => 'Connected to NOWPayments.'];
        if ($r['status'] === 401 || $r['status'] === 403) return ['success' => false, 'message' => 'NOWPayments rejected the API key.'];
        return ['success' => false, 'message' => 'NOWPayments returned HTTP ' . $r['status'] . '.'];
    }

    public function refund(string $paymentId, float $amount): array {
        return ['success'=>false, 'message'=>'Crypto refunds handled manually'];
    }
    public function getPublicConfig(): array { return []; }

    public function webhook(\Core\Request $request): void
    {
        // Rate-limit by source IP. See RazorpayGateway::webhook comment.
        if (!\Core\RateLimiter::attempt('webhook_nowpayments', $request->ip(), 300, 300)) {
            \Core\Response::json(['error' => 'Rate limited'], 429);
            return;
        }

        $rawBody = file_get_contents('php://input');
        $ipnSecret = $this->cfg('ipn_secret');

        // Webhook signature verification is mandatory. Refuse to process if the
        // secret is not configured — an unsigned endpoint would let any caller
        // mark orders as paid.
        if (empty($ipnSecret)) {
            \Core\Response::json(['error' => 'Webhook not configured'], 503);
            return;
        }

        $receivedSig = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'] ?? '';
        if (empty($receivedSig)) {
            \Core\Response::json(['error' => 'Missing signature'], 403);
            return;
        }

        // NOWPayments signs the sorted JSON payload with HMAC-SHA512.
        $payload = json_decode($rawBody, true) ?? [];
        ksort($payload);
        $expectedSig = hash_hmac('sha512', json_encode($payload, JSON_UNESCAPED_UNICODE), $ipnSecret);
        if (!hash_equals($expectedSig, $receivedSig)) {
            \Core\Response::json(['error' => 'Invalid signature'], 403);
            return;
        }

        $result = $this->verifyPayment($payload);
        if ($result['success']) {
            $order = \Core\Database::fetch("SELECT id, total FROM wk_orders WHERE order_number=?", [$payload['order_id'] ?? '']);
            // Use price_amount (fiat) for verification, not actually_paid (crypto)
            $paidAmount = isset($payload['price_amount']) ? (float)$payload['price_amount'] : null;
            if ($order) $this->markOrderPaid($order['id'], $result['payment_id'], $paidAmount);
        }
        \Core\Response::json(['status' => 'ok']);
    }

    private function api(string $ep, array $data): array
    {
        $ch = curl_init($this->apiUrl() . $ep);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'x-api-key: ' . $this->cfg('api_key')],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) return ['error' => $err];
        return json_decode($body, true) ?? [];
    }
}
