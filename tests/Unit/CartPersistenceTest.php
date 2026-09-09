<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A cart belonged to a browser session and nothing else. Add something on a
 * phone, sign in on a laptop, and the basket was empty — while the real one
 * sat in the database under that customer, where the abandoned-cart emails
 * could see it perfectly well.
 */
class CartPersistenceTest extends TestCase
{
    private function cart(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CartController.php');
    }

    private function method(string $name): string
    {
        $src = $this->cart();
        $start = strpos($src, "function {$name}(");
        $this->assertNotFalse($start, "{$name}() is missing");

        // Up to whatever is declared next, so a long method is not cut in half
        // and then asserted against its own first paragraph.
        $rest = substr($src, $start + 10);
        return preg_match('/^(.*?)\n    (?:private|public|protected)\s/s', $rest, $m)
            ? substr($src, $start, 10 + strlen($m[1]))
            : substr($src, $start);
    }

    /** Signed in, who you are decides the cart — not which browser you used. */
    public function testASignedInCustomerGetsTheirOwnCart(): void
    {
        $body = $this->method('getCart');

        $this->assertStringContainsString('$custId = Session::customerId();', $body);
        $this->assertStringContainsString('self::customerCart($custId, $sid)', $body,
            'the lookup still only asks about this session');
        $this->assertStringContainsString('self::mergeCarts(', $body);
        $this->assertStringContainsString('self::adoptCart(', $body);
    }

    /** Their newest cart, from any session but the one they are in. */
    public function testTheCustomersCartIsFoundByWhoTheyAre(): void
    {
        $body = $this->method('customerCart');

        $this->assertStringContainsString('customer_id = ?', $body);
        $this->assertStringContainsString("status = 'active'", $body, 'a finished cart must not come back');
        $this->assertStringContainsString('session_id <> ?', $body,
            'it would otherwise find the cart it is already looking at');
        $this->assertStringContainsString('ORDER BY updated_at DESC', $body,
            'the one they were last using is the one they mean');
        $this->assertStringContainsString('LIMIT 1', $body);
    }

    /** Moving a cart to this session beats copying it. */
    public function testAWaitingCartIsAdoptedNotDuplicated(): void
    {
        $body = $this->method('adoptCart');
        $this->assertStringContainsString('UPDATE wk_carts SET session_id = ? WHERE id = ?', $body);
        $this->assertStringNotContainsString('INSERT', $body);
    }

    // ── Merging ──────────────────────────────────────────────────────────

    /**
     * The same thing added on two devices is one line with a larger quantity,
     * not the same product listed twice.
     */
    public function testTheSameProductCombinesRatherThanRepeating(): void
    {
        $body = $this->method('mergeCarts');

        $this->assertStringContainsString('UPDATE wk_cart_items SET quantity = quantity + ?', $body);
        $this->assertStringContainsString('(variant_combo_id <=> ?)', $body,
            'plain equality never matches two NULL variants, so every plain product would duplicate');
    }

    /** Nobody walked away from a cart that was merged. */
    public function testTheSourceCartIsRetiredNotMarkedAbandoned(): void
    {
        $body = $this->method('mergeCarts');
        $this->assertStringContainsString("UPDATE wk_carts SET status = 'merged'", $body);
        $this->assertStringNotContainsString("status = 'abandoned'", $body,
            'marking it abandoned would send a recovery email about a basket they are holding');
    }

    public function testMergingACartIntoItselfDoesNothing(): void
    {
        $body = $this->method('mergeCarts');
        $this->assertStringContainsString('if ($fromId === $intoId) return', $body);
    }

    /** Whatever goes wrong, the basket in front of them survives it. */
    public function testAFailedMergeCostsNobodyTheirBasket(): void
    {
        $body = $this->method('mergeCarts');
        $this->assertStringContainsString('catch (\Exception $e)', $body);
        $this->assertStringContainsString("return ['id' => \$intoId];", $body);
    }

    // ── Signing out ──────────────────────────────────────────────────────

    /**
     * Signing out used to retire the cart. Now that a cart is found by
     * customer, that would throw away the thing this change exists to keep.
     */
    public function testSigningOutKeepsACustomersCartAndRetiresAGuestsOne(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/AccountController.php');
        $logout = substr($src, strpos($src, 'public function logout('), 1200);

        $this->assertStringContainsString('customer_id IS NULL', $logout,
            'a signed-in customer would lose their cart on the way out');
        $this->assertStringContainsString("status='abandoned'", $logout,
            'a guest cart has only the session to be found by, and that is going');
    }
}
