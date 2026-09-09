<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A shopper on a slow connection presses "Pay" again when nothing appears to
 * happen. Nothing stopped that becoming a second order, and with a gateway
 * attached, a second charge.
 */
class OrderIdempotencyTest extends TestCase
{
    private function controller(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CheckoutController.php');
    }

    private function process(): string
    {
        $src = $this->controller();
        $start = strpos($src, 'public function process(Request $request');
        return substr($src, $start, 2000);
    }

    /** The check comes before anything is created or charged. */
    public function testARepeatedAttemptIsRecognisedBeforeAnyWorkIsDone(): void
    {
        $body = $this->process();

        $attempt = strpos($body, 'self::attemptKey($request)');
        $cart    = strpos($body, '$this->getCartData()');

        $this->assertNotFalse($attempt, 'nothing identifies the attempt');
        $this->assertLessThan($cart, $attempt,
            'the repeat is only noticed after the order has started being built');

        $this->assertStringContainsString('self::orderForAttempt($attemptKey)', $body);
        $this->assertStringContainsString("Response::redirect(View::url('order-success?order=", $body,
            'a repeat should land on the order it already placed');
    }

    /** Only a token this shop minted counts; anything else is ignored. */
    public function testTheAttemptTokenIsValidatedNotTrusted(): void
    {
        $src = $this->controller();
        $fn = substr($src, strpos($src, 'private static function attemptKey('), 400);

        $this->assertStringContainsString("preg_match('/^[a-f0-9]{64}\$/', \$key)", $fn,
            'a caller could otherwise send any string and claim an existing order');
        $this->assertStringContainsString("return preg_match", $fn);
    }

    /**
     * The database is what actually enforces this. Two requests racing each
     * other both pass the lookup; only one survives the insert.
     */
    public function testTheDatabaseRefusesTheSecondOrder(): void
    {
        $files = glob(WK_ROOT . '/sql/migrations/*order_idempotency.sql');
        $this->assertNotEmpty($files, 'nothing adds the column');

        $sql = (string) file_get_contents($files[0]);
        $this->assertStringContainsString('idempotency_key', $sql);
        $this->assertMatchesRegularExpression('/ADD UNIQUE KEY/i', $sql,
            'without a unique index two racing requests both insert');
    }

    /** The loser of that race gets the order, not an error and not a second one. */
    public function testTheLoserOfTheRaceIsGivenTheOrderThatWon(): void
    {
        $src = $this->controller();
        $block = substr($src, strpos($src, "\$orderId = Database::insert('wk_orders', \$orderData);"), 900);

        $this->assertStringContainsString('$twin = self::orderForAttempt($attemptKey);', $block);
        $this->assertStringContainsString('order-success?order=', $block);
        $this->assertStringContainsString("unset(\$orderData['idempotency_key']);", $block,
            'on a database without the column the order must still be placeable');
    }

    /** A shop that has not run the migration must keep taking orders. */
    public function testAMissingColumnDoesNotStopCheckout(): void
    {
        $src = $this->controller();
        $fn = substr($src, strpos($src, 'private static function orderForAttempt('), 700);
        $this->assertStringContainsString('catch (\Exception $e)', $fn);
        $this->assertStringContainsString('return null;', $fn);
    }

    // ── The form ─────────────────────────────────────────────────────────

    public function testTheFormCarriesAFreshTokenEachTimeItIsRendered(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/checkout.php');
        $this->assertStringContainsString('name="order_attempt"', $view);
        $this->assertStringContainsString('bin2hex(random_bytes(32))', $view,
            'a predictable token could be used to claim somebody else\'s order');
    }

    /** The button going quiet is what the shopper actually sees. */
    public function testThePayButtonDisarmsOnTheFirstPress(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/checkout.php');
        $this->assertStringContainsString("form.addEventListener('submit'", $view);
        $this->assertStringContainsString('btn.disabled = true;', $view);
        $this->assertStringContainsString('if (btn.disabled) return;', $view,
            'a second submit event would otherwise still go through');
    }

    /**
     * Coming back to this page from history restores it as it was left — with
     * a dead button — unless it is put back.
     */
    public function testTheButtonComesBackWhenThePageDoes(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/checkout.php');
        $this->assertStringContainsString("addEventListener('pageshow'", $view);
        $this->assertStringContainsString('e.persisted', $view);
        $this->assertStringContainsString('btn.disabled = false;', $view);
    }

    /** Waiting with no explanation is what makes people press again. */
    public function testTheWaitIsExplainedAndAnnounced(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/checkout.php');
        $this->assertStringContainsString('aria-live="polite"', $view);
        $this->assertStringContainsString('Placing your order', $view);
    }
}
