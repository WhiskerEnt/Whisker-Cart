<?php
require_once __DIR__ . '/../../core/PaymentGatewayInterface.php';
require_once __DIR__ . '/../../core/BaseGateway.php';

class RazorpayGateway extends \Core\BaseGateway
{
    public function createOrder(array $order): array
    {
        $amount = (int)($order['total'] * 100);
        $resp = $this->api('/orders', ['amount'=>$amount, 'currency'=>$order['currency']??'INR', 'receipt'=>$order['order_number']]);
        $this->logTransaction($order['id'], ['gateway_order_id'=>$resp['id']??null, 'amount'=>$order['total'], 'status'=>'initiated', 'response'=>$resp]);
        return ['gateway_order_id'=>$resp['id'], 'key_id'=>$this->cfg('key_id'), 'amount'=>$amount];
    }

    public function verifyPayment(array $p): array
    {
        $sig = hash_hmac('sha256', $p['razorpay_order_id'].'|'.$p['razorpay_payment_id'], $this->cfg('key_secret'));
        $ok = hash_equals($sig, $p['razorpay_signature']??'');
        if ($ok) {
            \Core\Database::query("UPDATE wk_payment_transactions SET status='success',transaction_id=? WHERE gateway_order_id=?",
                [$p['razorpay_payment_id'], $p['razorpay_order_id']]);
        }
        return ['success'=>$ok, 'payment_id'=>$p['razorpay_payment_id']??null];
    }

    public function refund(string $paymentId, float $amount): array
    {
        $r = $this->api("/payments/{$paymentId}/refund", ['amount'=>(int)($amount*100)]);
        return ['success'=>isset($r['id']), 'refund_id'=>$r['id']??null];
    }

    public function getPublicConfig(): array { return ['key_id'=>$this->cfg('key_id')]; }

    public function webhook(\Core\Request $request): void
    {
        $rawBody = file_get_contents('php://input');
        if (empty($rawBody)) {
            \Core\Response::json(['error' => 'Empty body'], 400);
            return;
        }

        $webhookSecret = $this->cfg('webhook_secret');

        // Verify webhook signature if secret is configured
        if (!empty($webhookSecret)) {
            $expectedSig = hash_hmac('sha256', $rawBody, $webhookSecret);
            $receivedSig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
            if (!hash_equals($expectedSig, $receivedSig)) {
                \Core\Response::json(['error' => 'Invalid signature'], 403);
                return;
            }
        }

        $payload = json_decode($rawBody, true) ?? [];
        $event = $payload['event'] ?? '';

        if ($event === 'payment.captured') {
            $payment = $payload['payload']['payment']['entity'] ?? [];
            $paymentId = $payment['id'] ?? null;
            $orderId = $payment['order_id'] ?? null;
            $paidAmount = isset($payment['amount']) ? (float)$payment['amount'] / 100 : null; // Convert paise to rupees

            if ($paymentId && $orderId) {
                $txn = \Core\Database::fetch("SELECT order_id FROM wk_payment_transactions WHERE gateway_order_id=?", [$orderId]);
                if ($txn) $this->markOrderPaid($txn['order_id'], $paymentId, $paidAmount);
            }
        }

        \Core\Response::json(['status' => 'ok']);
    }

    private function api(string $ep, array $data): array
    {
        $ch = curl_init('https://api.razorpay.com/v1' . $ep);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_USERPWD => $this->cfg('key_id') . ':' . $this->cfg('key_secret'),
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
