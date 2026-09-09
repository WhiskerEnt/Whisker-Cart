<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The cart had no page — only a drawer, and a /cart URL that answered with
 * JSON. A drawer cannot be linked to, shared, returned to with the back
 * button, or sent somebody in an email, which is why the recovery mail had to
 * point at the front page with a query string on it.
 */
class CartPageTest extends TestCase
{
    private function controller(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CartController.php');
    }

    private function routes(): string
    {
        return (string) file_get_contents(WK_ROOT . '/config/routes.php');
    }

    // ── The page and the data are different things ───────────────────────

    public function testTheCartUrlServesAPageAndTheDataMovedAside(): void
    {
        $routes = $this->routes();
        $this->assertMatchesRegularExpression("/get\('\/cart',\s*\[CartController::class, 'show'\]/", $routes);
        $this->assertMatchesRegularExpression("/get\('\/cart\/data',\s*\[CartController::class, 'data'\]/", $routes,
            'the drawer needs an endpoint that is still JSON');

        $src = $this->controller();
        $show = substr($src, strpos($src, 'public function show('), 900);
        $this->assertStringContainsString("View::render('store/cart'", $show, '/cart must render a page');
        $this->assertStringNotContainsString('Response::json', $show, '/cart must not answer with JSON any more');

        $data = substr($src, strpos($src, 'public function data('), 1800);
        $this->assertStringContainsString('Response::json', $data);
    }

    /** Nothing may still be asking the page URL for JSON. */
    public function testTheDrawerReadsTheDataEndpoint(): void
    {
        $js = (string) file_get_contents(WK_ROOT . '/assets/js/store.js');
        $this->assertStringContainsString("fetch(this.base('cart/data'))", $js);
        $this->assertStringNotContainsString("fetch(this.base('cart'))", $js,
            'the drawer would render a page as if it were data');
    }

    public function testThePageExistsAndCanBeCheckedOutFrom(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/cart.php');
        $this->assertStringContainsString("\$url('checkout')", $view);
        $this->assertStringContainsString("\$url('shop')", $view, 'an empty cart needs a way out of it');
        $this->assertStringContainsString('wkCartQty', $view);
        $this->assertStringContainsString('wkCartRemove', $view);
    }

    /** The page posts to the same endpoints the drawer does. */
    public function testThePageUsesTheExistingCartEndpoints(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/cart.php');
        $this->assertStringContainsString("wkCartPost('cart/update'", $view);
        $this->assertStringContainsString("wkCartPost('cart/remove'", $view);
        $this->assertStringContainsString('item_id', $view);

        // And through the helper that attaches the forgery token.
        $this->assertStringContainsString('WhiskerStore.cartFetch', $view,
            'posting without the CSRF helper would be rejected');
    }

    /** Dropping to nothing is a removal, not a quantity of zero. */
    public function testReducingBelowOneRemovesTheLine(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/cart.php');
        $this->assertStringContainsString('if (quantity < 1) return wkCartRemove(itemId);', $view);
    }

    // ── Free delivery ────────────────────────────────────────────────────

    /**
     * A shop with no threshold configured must not be made to imply one.
     * Saying "you are ₹0 away from free delivery" would be worse than silence.
     */
    public function testNoThresholdMeansNoPromise(): void
    {
        $src = $this->controller();
        $fn = substr($src, strpos($src, 'private static function freeShippingProgress('));
        $fn = substr($fn, 0, strpos($fn, 'private static function alsoLike('));

        $this->assertStringContainsString('if ($threshold <= 0) return null;', $fn);
        $this->assertStringContainsString('catch (\Throwable $e) {', $fn,
            'a shop with no zones configured must still be able to show its cart');

        $view = (string) file_get_contents(WK_ROOT . '/views/store/cart.php');
        $this->assertStringContainsString('<?php if ($freeShipping): ?>', $view,
            'the view must not render the bar when there is nothing to promise');
    }

    /** The bar is a progress indicator, so it has to say so. */
    public function testTheProgressBarIsAnnounced(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/cart.php');
        $this->assertStringContainsString('role="progressbar"', $view);
        $this->assertStringContainsString('aria-valuenow', $view);
        $this->assertStringContainsString('aria-valuemax="100"', $view);
    }

    // ── Suggestions ──────────────────────────────────────────────────────

    /**
     * Offering something already in the basket, or something that cannot be
     * bought, wastes the only few slots there are.
     */
    public function testSuggestionsExcludeWhatIsAlreadyThereAndWhatIsGone(): void
    {
        $src = $this->controller();
        $fn = substr($src, strpos($src, 'private static function alsoLike('));

        $this->assertStringContainsString('p.stock_quantity > 0', $fn, 'a sold-out suggestion is a dead end');
        $this->assertStringContainsString('p.id NOT IN', $fn, 'it would suggest what is already in the cart');
        $this->assertStringContainsString('p.is_active = 1', $fn);
        $this->assertStringContainsString('if (!$items) return [];', $fn,
            'an empty cart has nothing to base a suggestion on');
    }

    /** Product ids reach the query as parameters, never as text. */
    public function testTheSuggestionQueryIsParameterised(): void
    {
        $src = $this->controller();
        $fn = substr($src, strpos($src, 'private static function alsoLike('));
        $this->assertStringContainsString("array_fill(0, count(\$productIds), '?')", $fn);
        $this->assertStringContainsString('array_merge($productIds, $productIds)', $fn);
    }

    // ── Mobile ───────────────────────────────────────────────────────────

    /** Three columns on a phone left 129px for a name and a stepper. */
    public function testTheCartLineReflowsOnAPhone(): void
    {
        $css = (string) file_get_contents(WK_ROOT . '/assets/css/store.css');
        $this->assertStringContainsString('grid-template-areas: "img info total";', $css);
        $this->assertStringContainsString('grid-template-areas: "img info" "total total";', $css,
            'the line total needs its own row once the screen is narrow');
    }
}
