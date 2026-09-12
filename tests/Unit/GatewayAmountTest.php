<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once WK_ROOT . '/plugins/stripe/StripeGateway.php';
require_once WK_ROOT . '/plugins/razorpay/RazorpayGateway.php';

/**
 * A gateway that records what it would have sent instead of calling out.
 * The real constructor reads the gateway row from the database, so it is
 * bypassed here.
 */
class RecordingStripeGateway extends \StripeGateway
{
    public array $sent = [];
    public array $reply = [];

    public function __construct()
    {
        $this->config = ['secret_key' => 'sk_test_x', 'publishable_key' => 'pk_test_x'];
        $this->testMode = false;
        $this->code = 'stripe';
    }

    protected function api(string $ep, array $data = [], string $method = 'POST', array $headers = []): array
    {
        $this->sent[] = ['endpoint' => $ep, 'data' => $data, 'method' => $method];
        return $this->reply;
    }

    protected function logTransaction(int $orderId, array $data): int
    {
        return 1;
    }
}

class RecordingRazorpayGateway extends \RazorpayGateway
{
    public array $sent = [];

    public function __construct()
    {
        $this->config = ['key_id' => 'rzp_test_x', 'key_secret' => 'x'];
        $this->testMode = false;
        $this->code = 'razorpay';
    }

    protected function api(string $ep, array $data, array $headers = []): array
    {
        $this->sent[] = ['endpoint' => $ep, 'data' => $data];
        return ['id' => 'order_x'];
    }

    protected function logTransaction(int $orderId, array $data): int
    {
        return 1;
    }
}

/**
 * The amount a gateway is asked to charge must be the order total, in that
 * currency's own minor unit. Order totals arrive from the database as
 * decimal strings, which is where binary floating point does its damage.
 */
class GatewayAmountTest extends TestCase
{
    private function order(string $total, string $currency): array
    {
        return ['id' => 7, 'order_number' => 'WK-7', 'total' => $total, 'currency' => $currency];
    }

    private function stripeLineItem(array $sent): array
    {
        return $sent['data']['line_items'][0]['price_data'];
    }

    public function testStripeIsAskedForTheExactTotal(): void
    {
        foreach ([['0.29', 'USD', 29], ['1.13', 'USD', 113], ['1049.99', 'INR', 104999], ['8.87', 'EUR', 887]] as [$total, $currency, $minor]) {
            $gateway = new RecordingStripeGateway();
            $gateway->reply = ['id' => 'cs_x', 'url' => 'https://checkout.stripe.com/x'];
            $gateway->createOrder($this->order($total, $currency));

            $price = $this->stripeLineItem($gateway->sent[0]);
            $this->assertSame($minor, $price['unit_amount'], "{$total} {$currency} was not sent as {$minor}");
            $this->assertSame(strtolower($currency), $price['currency']);
        }
    }

    public function testStripeChargesWholeYenNotHundredths(): void
    {
        $gateway = new RecordingStripeGateway();
        $gateway->reply = ['id' => 'cs_x', 'url' => 'https://checkout.stripe.com/x'];
        $gateway->createOrder($this->order('5000', 'JPY'));

        $price = $this->stripeLineItem($gateway->sent[0]);
        $this->assertSame(5000, $price['unit_amount']);
        $this->assertSame('jpy', $price['currency']);
    }

    public function testStripeReadsThePaidAmountInTheSessionCurrency(): void
    {
        foreach ([[2999, 'usd', 29.99], [5000, 'jpy', 5000.0], [104999, 'inr', 1049.99]] as [$minor, $currency, $amount]) {
            $gateway = new RecordingStripeGateway();
            $gateway->reply = [
                'payment_status' => 'paid',
                'payment_intent' => 'pi_x',
                'amount_total'   => $minor,
                'currency'       => $currency,
                'metadata'       => ['order_id' => 7],
            ];
            $result = $gateway->verifyPayment(['session_id' => 'cs_x', 'order_id' => 7]);

            $this->assertTrue($result['success']);
            $this->assertSame($amount, $result['amount'], "{$minor} {$currency} was not read back as {$amount}");
        }
    }

    public function testRazorpayIsAskedForTheExactTotal(): void
    {
        foreach ([['0.29', 'INR', 29], ['1049.99', 'INR', 104999], ['5000', 'JPY', 5000]] as [$total, $currency, $minor]) {
            $gateway = new RecordingRazorpayGateway();
            $result = $gateway->createOrder($this->order($total, $currency));

            $this->assertSame($minor, $gateway->sent[0]['data']['amount'], "{$total} {$currency} was not sent as {$minor}");
            $this->assertSame($currency, $gateway->sent[0]['data']['currency']);
            $this->assertSame($minor, $result['amount'], 'the browser must be handed the same amount the order was created with');
        }
    }

    public function testNoGatewayHardcodesAFallbackCurrency(): void
    {
        foreach (glob(WK_ROOT . '/plugins/*/*Gateway.php') as $file) {
            $src = (string) file_get_contents($file);
            $this->assertSame(
                0,
                preg_match('/\?\?\s*[\'"](usd|inr|eur|gbp)[\'"]/i', $src),
                basename($file) . ' falls back to a fixed currency instead of the store setting'
            );
        }
    }
}
