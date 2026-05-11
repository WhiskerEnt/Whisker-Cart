<?php
require_once __DIR__ . '/../../core/PaymentGatewayInterface.php';
require_once __DIR__ . '/../../core/BaseGateway.php';

class CCAvenueGateway extends \Core\BaseGateway
{
    public function createOrder(array $order): array
    {
        $params = ['merchant_id'=>$this->cfg('merchant_id'), 'order_id'=>$order['order_number'],
            'amount'=>number_format($order['total'],2,'.',''), 'currency'=>$order['currency']??'INR',
            'redirect_url'=>\Core\View::url('webhook/ccavenue/callback'), 'cancel_url'=>\Core\View::url('checkout?cancelled=1'), 'language'=>'EN'];
        $enc = $this->encrypt(http_build_query($params));
        $this->logTransaction($order['id'], ['gateway_order_id'=>$order['order_number'], 'amount'=>$order['total'], 'status'=>'initiated', 'response'=>$params]);
        $url = $this->testMode ? 'https://test.ccavenue.com/transaction/transaction.do?command=initiateTransaction'
            : 'https://secure.ccavenue.com/transaction/transaction.do?command=initiateTransaction';
        return ['form_url'=>$url, 'encrypted_data'=>$enc, 'access_code'=>$this->cfg('access_code')];
    }

    public function verifyPayment(array $p): array
    {
        $dec = $this->decrypt($p['encResp']??''); parse_str($dec, $d);
        $ok = ($d['order_status']??'')==='Success';
        \Core\Database::query("UPDATE wk_payment_transactions SET status=?,transaction_id=? WHERE gateway_order_id=?",
            [$ok?'success':'failed', $d['tracking_id']??null, $d['order_id']??'']);
        return ['success'=>$ok, 'payment_id'=>$d['tracking_id']??null, 'order_id'=>$d['order_id']??null, 'amount'=>$d['amount']??null];
    }

    public function refund(string $paymentId, float $amount): array {
        return ['success'=>false, 'message'=>'Refund via CCAvenue dashboard'];
    }
    public function getPublicConfig(): array { return []; }

    public function webhook(\Core\Request $request): void
    {
        $result = $this->verifyPayment($request->all());
        if ($result['success'] && $result['order_id']) {
            $order = \Core\Database::fetch("SELECT id, total FROM wk_orders WHERE order_number=?", [$result['order_id']]);
            // CCAvenue returns amount in the decrypted response
            $paidAmount = isset($result['amount']) ? (float)$result['amount'] : null;
            if ($order) $this->markOrderPaid($order['id'], $result['payment_id'], $paidAmount);
            \Core\Response::redirect(\Core\View::url('order-success?order='.$result['order_id']));
        } else {
            \Core\Session::flash('error','Payment failed.');
            \Core\Response::redirect(\Core\View::url('checkout?failed=1'));
        }
    }

    private function encrypt(string $text): string {
        $wk = $this->cfg('working_key');
        if (empty($wk)) return '';
        $key=hex2bin(md5($wk)); $iv=str_repeat("\0",16);
        return bin2hex(openssl_encrypt($text,'AES-128-CBC',$key,OPENSSL_RAW_DATA,$iv));
    }
    private function decrypt(string $text): string {
        $wk = $this->cfg('working_key');
        if (empty($wk) || empty($text)) return '';
        $key=hex2bin(md5($wk)); $iv=str_repeat("\0",16);
        return openssl_decrypt(hex2bin($text),'AES-128-CBC',$key,OPENSSL_RAW_DATA,$iv) ?: '';
    }
}
