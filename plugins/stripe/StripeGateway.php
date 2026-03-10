<?php
require_once __DIR__ . '/../../core/PaymentGatewayInterface.php';
require_once __DIR__ . '/../../core/BaseGateway.php';

class StripeGateway extends \Core\BaseGateway
{
    public function createOrder(array $order): array
    {
        $resp = $this->api('/checkout/sessions', [
            'payment_method_types'=>['card'], 'mode'=>'payment',
            'line_items'=>[['price_data'=>['currency'=>strtolower($order['currency']??'usd'),
                'unit_amount'=>(int)($order['total']*100), 'product_data'=>['name'=>'Order '.$order['order_number']]],
                'quantity'=>1]],
            'success_url'=>\Core\View::url('order-success?order='.$order['order_number']),
            'cancel_url'=>\Core\View::url('checkout?cancelled=1'),
            'metadata'=>['order_id'=>$order['id']],
        ]);
        $this->logTransaction($order['id'], ['gateway_order_id'=>$resp['id']??null, 'amount'=>$order['total'], 'status'=>'initiated', 'response'=>$resp]);
        return ['session_id'=>$resp['id'], 'session_url'=>$resp['url']??null, 'publishable_key'=>$this->cfg('publishable_key')];
    }

    public function verifyPayment(array $p): array
    {
        $session = $this->api('/checkout/sessions/'.$p['session_id'], [], 'GET');
        $ok = ($session['payment_status']??'') === 'paid';
        return ['success'=>$ok, 'payment_id'=>$session['payment_intent']??null];
    }

    public function refund(string $paymentId, float $amount): array
    {
        $r = $this->api('/refunds', ['payment_intent'=>$paymentId, 'amount'=>(int)($amount*100)]);
        return ['success'=>isset($r['id']), 'refund_id'=>$r['id']??null];
    }

    public function getPublicConfig(): array { return ['publishable_key'=>$this->cfg('publishable_key')]; }

    public function webhook(\Core\Request $request): void
    {
        $rawBody = file_get_contents('php://input');
        $webhookSecret = $this->cfg('webhook_secret');

        // Verify Stripe webhook signature if secret is configured
        if (!empty($webhookSecret)) {
            $sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
            $timestamp = '';
            $signatures = [];

            // Parse Stripe signature header: t=timestamp,v1=signature
            foreach (explode(',', $sigHeader) as $part) {
                $pair = explode('=', trim($part), 2);
                if (count($pair) === 2) {
                    if ($pair[0] === 't') $timestamp = $pair[1];
                    if ($pair[0] === 'v1') $signatures[] = $pair[1];
                }
            }

            if (empty($timestamp) || empty($signatures)) {
                \Core\Response::json(['error' => 'Missing signature'], 403);
                return;
            }

            // Reject if timestamp is older than 5 minutes (replay attack prevention)
            if (abs(time() - (int)$timestamp) > 300) {
                \Core\Response::json(['error' => 'Timestamp too old'], 403);
                return;
            }

            $signedPayload = $timestamp . '.' . $rawBody;
            $expectedSig = hash_hmac('sha256', $signedPayload, $webhookSecret);

            $verified = false;
            foreach ($signatures as $sig) {
                if (hash_equals($expectedSig, $sig)) { $verified = true; break; }
            }

            if (!$verified) {
                \Core\Response::json(['error' => 'Invalid signature'], 403);
                return;
            }
        }

        $payload = json_decode($rawBody, true) ?? [];

        if (($payload['type'] ?? '') === 'checkout.session.completed') {
            $session = $payload['data']['object'] ?? [];
            $orderId = $session['metadata']['order_id'] ?? null;
            $paidAmount = isset($session['amount_total']) ? (float)$session['amount_total'] / 100 : null;
            if ($orderId) $this->markOrderPaid((int)$orderId, $session['payment_intent'] ?? '', $paidAmount);
        }

        \Core\Response::json(['status' => 'ok']);
    }

    private function api(string $ep, array $data=[], string $method='POST'): array
    {
        $ch = curl_init('https://api.stripe.com/v1' . $ep);
        $opts = [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->cfg('secret_key')],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        if ($method === 'POST') { $opts[CURLOPT_POST] = 1; $opts[CURLOPT_POSTFIELDS] = http_build_query($data); }
        curl_setopt_array($ch, $opts);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) return ['error' => $err];
        return json_decode($body, true) ?? [];
    }
}
