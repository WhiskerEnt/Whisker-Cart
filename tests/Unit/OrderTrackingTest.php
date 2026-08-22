<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guest order tracking.
 *
 * The lookup itself needs a database, so these tests pin the rules that can be
 * checked without one: the pairing contract, the single shared failure message,
 * and that the route is wired and CSRF-protected.
 */
class OrderTrackingTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/TrackController.php');
    }

    public function testLookupRequiresBothNumberAndEmail(): void
    {
        // An empty half must never reach the database.
        $this->assertNull(\App\Controllers\Store\TrackController::findOrder('', 'a@b.com'));
        $this->assertNull(\App\Controllers\Store\TrackController::findOrder('WK-1', ''));
        $this->assertNull(\App\Controllers\Store\TrackController::findOrder('   ', '   '));
    }

    /**
     * The query must filter on the order number AND the email. Matching on the
     * number alone would expose one shopper's order to anyone who guessed it.
     */
    public function testQueryMatchesOnBothColumns(): void
    {
        $src = $this->source();
        $this->assertSame(
            1,
            preg_match('/WHERE\s+order_number\s*=\s*\?\s+AND\s+LOWER\(customer_email\)\s*=\s*\?/i', $src),
            'the lookup must require order_number AND customer_email'
        );
    }

    /**
     * A wrong pair and an order that does not exist must be answered
     * identically, so the form cannot confirm which order numbers or email
     * addresses are real.
     */
    public function testOneSharedMessageForEveryFailedLookup(): void
    {
        $src = $this->source();
        $this->assertSame(1, preg_match("/private const NOT_FOUND = '([^']+)'/", $src, $m));
        // It is used for the not-found case, and nothing else contradicts it.
        $this->assertSame(
            1,
            preg_match_all('/self::NOT_FOUND/', $src),
            'exactly one code path should answer a failed lookup'
        );
        $this->assertStringNotContainsStringIgnoringCase('no order with that number', $m[1]);
        $this->assertStringNotContainsStringIgnoringCase('email is not', $m[1]);
    }

    public function testLookupIsRateLimited(): void
    {
        $src = $this->source();
        $this->assertStringContainsString("RateLimiter::attempt('order_track'", $src);
        $this->assertSame(1, preg_match('/MAX_ATTEMPTS\s*=\s*(\d+)/', $src, $m));
        $this->assertLessThanOrEqual(20, (int)$m[1], 'attempt cap should stay low enough to blunt enumeration');
    }

    public function testRoutesAreRegisteredAndPostIsCsrfProtected(): void
    {
        $routes = (string) file_get_contents(WK_ROOT . '/config/routes.php');
        $this->assertSame(1, preg_match("#\\\$router->get\('/track'#", $routes), 'GET /track missing');
        $this->assertSame(1, preg_match("#\\\$router->post\('/track'.*'csrf'#", $routes), 'POST /track must be CSRF-protected');
    }

    public function testTrackingViewEscapesOrderData(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/track.php');
        // Order data is merchant/customer supplied; it must not be echoed raw.
        $this->assertStringContainsString('$e($order[\'order_number\'])', $view);
        $this->assertStringContainsString('safeUrl($notes[\'tracking_url\'])', $view);
    }
}
