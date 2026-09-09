<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Things the shop knew and never said: that adding to the cart worked, that
 * only two of something are left, and — for anyone arriving by keyboard —
 * that there is a way past the header.
 */
class ShopperFeedbackTest extends TestCase
{
    private function layout(): string
    {
        return (string) file_get_contents(WK_ROOT . '/views/store/layouts/main.php');
    }

    private function js(): string
    {
        return (string) file_get_contents(WK_ROOT . '/assets/js/store.js');
    }

    private function css(): string
    {
        return (string) file_get_contents(WK_ROOT . '/assets/css/store.css');
    }

    // ── Saying what happened ─────────────────────────────────────────────

    /** The cart changes without a reload, which is silent by default. */
    public function testThereIsSomewhereToAnnounceChanges(): void
    {
        $layout = $this->layout();
        $this->assertStringContainsString('id="wkAnnounce"', $layout);
        $this->assertStringContainsString('aria-live="polite"', $layout);
        $this->assertStringContainsString('aria-atomic="true"', $layout,
            'without this only the changed words are read, not the sentence');
        $this->assertStringContainsString('class="wk-sr-only"', $layout,
            'the region is for listening to, not looking at');
    }

    /** Hidden from the eye, not from the accessibility tree. */
    public function testTheRegionIsHiddenWithoutBeingRemoved(): void
    {
        $css = $this->css();
        $rule = substr($css, strpos($css, '.wk-sr-only {'), 260);

        $this->assertStringContainsString('clip: rect(0 0 0 0)', $rule);
        $this->assertStringNotContainsString('display: none', $rule,
            'display:none takes it out of the accessibility tree entirely');
        $this->assertStringNotContainsString('visibility: hidden', $rule);
    }

    /** The same message twice is not a change, and goes unread. */
    public function testARepeatedMessageIsStillAnnounced(): void
    {
        $js = $this->js();
        $fn = substr($js, strpos($js, 'announce(message) {'), 320);

        $this->assertStringContainsString("region.textContent = '';", $fn);
        $this->assertStringContainsString('setTimeout(', $fn,
            'clearing and setting in the same tick is one mutation, not two');
    }

    public function testAddingToTheCartSaysSoEitherWay(): void
    {
        $js = $this->js();
        $this->assertStringContainsString("this.announce('Added to your cart.')", $js);
        $this->assertStringContainsString('this.announce(data.message ||', $js,
            'a refusal matters more than a success and was the silent one');
    }

    // ── Getting past the header ──────────────────────────────────────────

    public function testAKeyboardCanSkipStraightToTheContent(): void
    {
        $layout = $this->layout();

        $skipAt = strpos($layout, 'class="wk-skip-link"');
        $bodyAt = strpos($layout, '<body');
        $headerAt = strpos($layout, '<header');

        $this->assertNotFalse($skipAt, 'there is no skip link');
        $this->assertGreaterThan($bodyAt, $skipAt);
        $this->assertLessThan($headerAt, $skipAt,
            'a skip link after the header skips nothing');

        $this->assertStringContainsString('href="#wk-main"', $layout);
        $this->assertStringContainsString('<main id="wk-main"', $layout,
            'the link has nothing to land on');
    }

    /** Out of the way until it is wanted, then plainly visible. */
    public function testTheSkipLinkRevealsItselfOnFocus(): void
    {
        $css = $this->css();
        $this->assertMatchesRegularExpression('/\.wk-skip-link \{[^}]*top: -\d+px/s', $css,
            'it should sit off the top of the page until focused');
        $this->assertMatchesRegularExpression('/\.wk-skip-link:focus \{[^}]*top: 0/', $css);
    }

    /** Landing on it should not draw a box around the whole page. */
    public function testTheContentLandmarkTakesFocusQuietly(): void
    {
        $this->assertStringContainsString('tabindex="-1"', $this->layout(),
            'a main element cannot receive focus from a fragment link without this');
        $this->assertStringContainsString('#wk-main:focus { outline: none; }', $this->css());
    }

    // ── How many are left ────────────────────────────────────────────────

    /**
     * Every product carries its own low_stock_threshold and the shop had only
     * ever shown it to itself, in the admin list.
     */
    public function testRunningLowIsSaidOutLoud(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/product.php');

        $this->assertStringContainsString("\$p['low_stock_threshold']", $view,
            'a fixed number would be wrong for most of the catalogue');
        $this->assertStringContainsString('Only <?= (int) $totalStock ?> left', $view);
    }

    /** Nothing left is out of stock, not nearly gone. */
    public function testTheWarningNeverAppearsForSomethingSoldOut(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/product.php');
        $this->assertStringContainsString('$totalStock > 0 && $lowAt > 0 && $totalStock <= $lowAt', $view,
            'zero stock would read as "only 0 left"');
    }

    /** A shop that sets the threshold to zero has opted out of saying it. */
    public function testAThresholdOfZeroTurnsItOff(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/product.php');
        $this->assertStringContainsString('$lowAt > 0', $view);
    }
}
