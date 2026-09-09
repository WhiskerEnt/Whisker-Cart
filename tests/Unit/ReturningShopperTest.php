<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The things a shop offers somebody who has been before: buying the same
 * order again, picking up where they left off, seeing where they are, and
 * getting a proper look at what they are buying.
 */
class ReturningShopperTest extends TestCase
{
    private function account(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/AccountController.php');
    }

    private function product(): string
    {
        return (string) file_get_contents(WK_ROOT . '/views/store/product.php');
    }

    private function reorder(): string
    {
        $src = $this->account();
        $start = strpos($src, 'public function reorder(');
        $this->assertNotFalse($start, 'there is no way to reorder');
        return substr($src, $start, 2600);
    }

    // ── Ordering it again ────────────────────────────────────────────────

    public function testAPastOrderCanBePutBackInTheBasket(): void
    {
        $routes = (string) file_get_contents(WK_ROOT . '/config/routes.php');
        $this->assertStringContainsString(
            "\$router->post('/account/order/{id}/reorder', [AccountController::class, 'reorder'], ['csrf']);",
            $routes
        );

        $view = (string) file_get_contents(WK_ROOT . '/views/store/account/order-detail.php');
        $this->assertStringContainsString("/reorder", $view, 'nothing links to it');
        $this->assertStringContainsString('csrfField()', $view);
    }

    /** Somebody else's order is not a shopping list. */
    public function testOnlyYourOwnOrderCanBeReordered(): void
    {
        $body = $this->reorder();
        $this->assertMatchesRegularExpression('/WHERE id = \? AND customer_id = \?/', $body);
        $this->assertStringContainsString('Response::notFound();', $body);
        $this->assertStringContainsString('Session::verifyCsrf', $body);
    }

    /**
     * Reordering is buying now. Quoting the price from the old order would be
     * a promise the checkout could not keep.
     */
    public function testItUsesTodaysPriceAndStockNotTheOrdersOwn(): void
    {
        $body = $this->reorder();

        $this->assertStringContainsString('CartController::addLine(', $body,
            'a second implementation would eventually disagree about what things cost');
        $this->assertStringNotContainsString("\$item['unit_price']", $body,
            'the price on the order is history, not an offer');

        // And the shared path prices from the product, not from its caller.
        $cart = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CartController.php');
        $fn = substr($cart, strpos($cart, 'public static function addLine('), 1800);
        $this->assertStringContainsString("\$product['sale_price'] ?: \$product['price']", $fn);
        $this->assertStringContainsString('$stockAvailable <= 0', $fn);
    }

    /** Both callers go through it, or the sharing was pointless. */
    public function testTheAddButtonUsesTheSamePath(): void
    {
        $cart = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CartController.php');
        $add = substr($cart, strpos($cart, 'public function add(Request'), 600);
        $this->assertStringContainsString('self::addLine(', $add);
    }

    /** Quietly dropping an item is how somebody discovers it at checkout. */
    public function testAnythingNoLongerAvailableIsNamed(): void
    {
        $body = $this->reorder();
        $this->assertStringContainsString('$skipped[]', $body);
        $this->assertStringContainsString("implode(', ', \$skipped)", $body);
        $this->assertStringContainsString('Nothing from that order is available', $body,
            'a reorder where everything is gone should say so, not show an empty cart');
    }

    // ── Knowing where you are ────────────────────────────────────────────

    /** The trail was published for search engines and shown to nobody. */
    public function testTheProductPageShowsTheTrailItAlreadyPublishes(): void
    {
        $view = $this->product();

        $this->assertStringContainsString('aria-label="Breadcrumb"', $view);
        $this->assertStringContainsString('aria-current="page"', $view,
            'the page you are on is not a link and should say so');

        // The same trail the structured data is built from.
        $seo = (string) file_get_contents(WK_ROOT . '/app/Services/SeoService.php');
        $this->assertStringContainsString('breadcrumbSchema', $seo);
        $this->assertStringContainsString("\$url('category/' . urlencode(\$catSlug))", $view,
            'the category crumb should lead to the category');
    }

    /** A category with no slug cannot be linked to, but can still be named. */
    public function testACategoryWithNoSlugIsShownWithoutALink(): void
    {
        $view = $this->product();
        $this->assertStringContainsString('<?php if ($catSlug): ?>', $view);
        $this->assertStringContainsString('<?php else: ?>', $view);
    }

    // ── Looking properly ─────────────────────────────────────────────────

    public function testTheProductImageOpensLarger(): void
    {
        $view = $this->product();
        $this->assertStringContainsString("id=\"wkLightbox\"", $view);
        $this->assertStringContainsString("main.setAttribute('role', 'button')", $view);
        $this->assertStringContainsString("main.setAttribute('tabindex', '0')", $view,
            'a picture that only opens on click cannot be reached by keyboard');
    }

    public function testTheLargerViewCanBeLeftTheWayItWasOpened(): void
    {
        $view = $this->product();
        $this->assertStringContainsString("e.key === 'Escape'", $view);
        $this->assertStringContainsString("e.key === 'Enter' || e.key === ' '", $view);
        $this->assertStringContainsString('if (lastFocus) lastFocus.focus();', $view,
            'closing it should put focus back where it was, not at the top of the page');
        $this->assertStringContainsString("document.body.style.overflow = '';", $view,
            'the page would stay unscrollable after closing');
    }

    // ── Where they left off ──────────────────────────────────────────────

    /**
     * A remembered price goes stale and shows somebody a number the shop will
     * not honour, so no price is remembered.
     */
    public function testRecentlyViewedRemembersNoPrices(): void
    {
        $view = $this->product();
        $block = substr($view, strpos($view, "var KEY = 'wk_recent_v1'"), 900);

        $this->assertStringContainsString('slug:', $block);
        $this->assertStringContainsString('name:', $block);
        $this->assertStringContainsString('image:', $block);
        $this->assertStringNotContainsString('price', $block, 'a stored price is a stale price');
    }

    /** It is one person's browsing on one device. */
    public function testItIsKeptInTheBrowserNotOnTheServer(): void
    {
        $view = $this->product();
        $this->assertStringContainsString('localStorage.setItem', $view);

        $routes = (string) file_get_contents(WK_ROOT . '/config/routes.php');
        $this->assertStringNotContainsString('recently-viewed', $routes,
            'this needs no endpoint, and an endpoint would make it the shop\'s business');
    }

    public function testThePageYouAreOnIsNotListedAsRecentlyViewed(): void
    {
        $view = $this->product();
        $this->assertStringContainsString("x.slug !== me.slug", $view);
    }

    /** Storage can be off. That is not worth breaking a product page over. */
    public function testStorageBeingUnavailableIsSurvivable(): void
    {
        $view = $this->product();
        $block = substr($view, strpos($view, "var KEY = 'wk_recent_v1'"));

        $this->assertGreaterThanOrEqual(2, substr_count($block, 'catch (e)'),
            'both the read and the write have to tolerate storage being refused');
    }
}
