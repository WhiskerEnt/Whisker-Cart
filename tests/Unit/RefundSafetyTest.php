<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The refund path is the only place in the cart that moves money out, so the
 * properties that stop it paying twice are asserted against the source rather
 * than left to review.
 */
class RefundSafetyTest extends TestCase
{
    private function service(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Services/RefundService.php');
    }

    public function testGatewayIsCalledBeforeAnythingIsWritten(): void
    {
        $src = $this->service();
        $call   = strpos($src, '$gateway->refund(');
        $insert = strpos($src, 'self::record(', $call === false ? 0 : 0);
        $this->assertNotFalse($call, 'the service must call the gateway');
        $this->assertNotFalse($insert, 'the service must record the attempt');

        // The first record() in issue() must come after the gateway call, so a
        // failed refund never leaves a record saying money was returned.
        $issue = substr($src, strpos($src, 'public static function issue('));
        $issue = substr($issue, 0, strpos($issue, 'private static function record('));
        $gatewayAt = strpos($issue, '$gateway->refund(');
        $recordAt  = strpos($issue, 'self::record(', $gatewayAt);
        $this->assertNotFalse($recordAt, 'the gateway result must be recorded after the call');
        $this->assertGreaterThan($gatewayAt, $recordAt);
    }

    public function testAnUnconfirmedRefundBlocksFurtherOnes(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('hasUnresolved', $src);
        $this->assertMatchesRegularExpression(
            "/status = 'unknown'/",
            $src,
            'unresolved refunds are the ones the gateway never confirmed'
        );
        // issue() must consult it before contacting the gateway.
        $issue = substr($src, strpos($src, 'public static function issue('));
        $this->assertLessThan(
            strpos($issue, '$gateway->refund('),
            strpos($issue, 'self::hasUnresolved('),
            'the unresolved check must happen before the gateway is called'
        );
    }

    public function testUnknownRefundsCountAgainstTheBalance(): void
    {
        // If they did not, an ambiguous attempt could be refunded again in full.
        $this->assertMatchesRegularExpression(
            "/status IN \('completed','pending','unknown'\)/",
            $this->service(),
            'money that may already have moved must reduce the refundable balance'
        );
    }

    public function testRefundCannotExceedWhatIsLeft(): void
    {
        $this->assertStringContainsString('$amount > $remaining', $this->service());
    }

    public function testEveryGatewayReportsAnAmbiguousCallAsUnknown(): void
    {
        foreach (['stripe' => 'StripeGateway', 'razorpay' => 'RazorpayGateway'] as $dir => $class) {
            $src = (string) file_get_contents(WK_ROOT . "/plugins/{$dir}/{$class}.php");
            $this->assertStringContainsString(
                'refundUnknown',
                $src,
                "{$class} must report a call it could not confirm as unknown, not failed"
            );
            $this->assertStringContainsString(
                'lastTransportError',
                $src,
                "{$class} must distinguish a transport failure from a rejection"
            );
        }
    }

    public function testStripeSendsAnIdempotencyKey(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/plugins/stripe/StripeGateway.php');
        $this->assertStringContainsString('Idempotency-Key: ', $src);
        $this->assertStringContainsString("\$options['idempotency_key']", $src);
    }

    public function testRefundReferenceIsUniqueAndPrefixed(): void
    {
        $src = $this->service();
        $this->assertStringContainsString("'RFN-' . date('ymd')", $src);
        $this->assertStringContainsString('random_bytes', $src);

        $schema = (string) file_get_contents(WK_ROOT . '/sql/migrations/20260818_v140_refunds.sql');
        $this->assertStringContainsString('UNIQUE KEY unique_refund_ref', $schema);
    }

    public function testAdminRefundRouteIsPostAndCsrfChecked(): void
    {
        $routes = (string) file_get_contents(WK_ROOT . '/config/routes.php');
        $this->assertStringContainsString("\$r->post('/orders/refund/{id}'", $routes);
        $this->assertSame(
            0,
            preg_match("/\\\$r->get\('\/orders\/refund/", $routes),
            'refunds must never be reachable by GET'
        );

        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/OrderController.php');
        $refund = substr($controller, strpos($controller, 'public function refund('));
        $refund = substr($refund, 0, strpos($refund, 'public function updateShipping('));
        $this->assertStringContainsString('Session::verifyCsrf', $refund);
        $this->assertLessThan(
            strpos($refund, 'RefundService::issue('),
            strpos($refund, 'Session::verifyCsrf'),
            'the token must be checked before any money moves'
        );
    }
}
