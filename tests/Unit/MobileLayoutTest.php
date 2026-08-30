<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Most shoppers arrive on a phone, where the screen is about 375 points wide
 * and the only pointer is a thumb. What works with a mouse and a wide window
 * does not follow from that.
 */
class MobileLayoutTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(WK_ROOT . '/assets/css/store.css');
    }

    private function js(): string
    {
        return (string) file_get_contents(WK_ROOT . '/assets/js/store.js');
    }

    // ── Dragging the carousel ────────────────────────────────────────────

    /** The gesture people try first on a carousel is a swipe. */
    public function testTheCarouselCanBeDragged(): void
    {
        $js = $this->js();
        $block = substr($js, strpos($js, 'initCarousel()'));
        $block = substr($block, 0, strpos($block, 'confetti()'));

        foreach (['pointerdown', 'pointermove', 'pointerup', 'pointercancel'] as $event) {
            $this->assertStringContainsString("addEventListener('{$event}'", $block,
                "the carousel does not handle {$event}, so it cannot be dragged");
        }
    }

    /** Dragging sideways must not stop the page scrolling up and down. */
    public function testVerticalScrollingSurvivesAHorizontalDrag(): void
    {
        $this->assertStringContainsString("touchAction = 'pan-y'", $this->js(),
            'the track claims every direction, so the page cannot be scrolled from over it');
        $this->assertMatchesRegularExpression(
            '/\.wk-carousel-track\s*\{[^}]*touch-action:\s*pan-y/',
            $this->css(),
            'the stylesheet should say the same, for the first touch before the script runs'
        );
    }

    /** A slide is a link. Letting go after a swipe is not a tap. */
    public function testASwipeDoesNotFollowTheLinkUnderIt(): void
    {
        $js = $this->js();
        $block = substr($js, strpos($js, 'initCarousel()'));
        $block = substr($block, 0, strpos($block, 'confetti()'));

        $this->assertStringContainsString("addEventListener('click'", $block);
        $this->assertStringContainsString('e.preventDefault(); e.stopPropagation();', $block,
            'a drag that ends over a product would open that product');
        $this->assertStringContainsString('}, true);', $block,
            'the guard has to run before the link does, so it must capture');
    }

    /** A thumb wobbles. A few pixels is not a swipe. */
    public function testATinyMovementIsNotTreatedAsASwipe(): void
    {
        $js = $this->js();
        $this->assertMatchesRegularExpression('/Math\.abs\(dx\) > Math\.min\(60, width \* 0\.15\)/', $js,
            'any movement at all would change slide');
    }

    /** Autoplay must not fight a finger. */
    public function testAutoplayStopsWhileDragging(): void
    {
        $js = $this->js();
        $begin = substr($js, strpos($js, 'const beginDrag'), 400);
        $this->assertStringContainsString('stop();', $begin);
    }

    // ── Room to read ─────────────────────────────────────────────────────

    /**
     * The contact bar reserves a strip down one side of the page. On a phone
     * that strip is a sixth of the screen, so there it folds into its button.
     */
    public function testThePhoneDoesNotReserveAColumnForTheContactBar(): void
    {
        $css = $this->css();
        $mobile = substr($css, strrpos($css, '/* ── Phones'));

        $this->assertStringContainsString('.wk-social-inner { display: none; }', $mobile,
            'the bar stays open on a phone and keeps its column');
        $this->assertStringContainsString('.wk-social.is-open .wk-social-inner { display: flex; }', $mobile,
            'folded away with no way to open it');
        $this->assertMatchesRegularExpression(
            '/body\.wk-has-social-left\s+\.wk-container,\s*\n\s*body\.wk-has-social-right \.wk-container \{ padding-left: 16px; padding-right: 16px; \}/',
            $mobile,
            'the page still holds a gutter open for a bar that is no longer there'
        );
    }

    /** The button that opens it has to exist whatever the shopkeeper chose. */
    public function testTheContactToggleIsAlwaysInTheMarkup(): void
    {
        $partial = (string) file_get_contents(WK_ROOT . '/views/store/partials/social-bar.php');

        $toggle = strpos($partial, 'class="wk-social-toggle"');
        $this->assertNotFalse($toggle, 'there is no toggle button');

        // It must not sit behind the admin's collapse setting any more.
        $before = substr($partial, 0, $toggle);
        $this->assertStringNotContainsString('<?php if ($collapsed): ?>', $before,
            'a shop set to always-show renders no toggle, so a phone cannot fold the bar away');
    }

    /** Two cards side by side, until the screen is genuinely too narrow. */
    public function testProductsAreTwoUpOnATypicalPhone(): void
    {
        $css = $this->css();
        $this->assertStringContainsString('.wk-product-grid { grid-template-columns: repeat(2, 1fr);', $css,
            'the tablet-and-down rule that puts two cards on a row is gone');
        // 375 and 390 are the common widths; one column starts below those.
        $this->assertStringContainsString('@media (max-width: 340px) { .wk-product-grid { grid-template-columns: 1fr; } }', $css,
            'a 375px phone gets a single column, which wastes half the screen');
    }

    /** Three stacked rows of header is a screenful before any product. */
    public function testTheHeaderCollapsesToIconsOnAPhone(): void
    {
        $css = $this->css();
        $mobile = substr($css, strrpos($css, '/* ── Phones'));
        $this->assertStringContainsString('.wk-btn-label { display: none; }', $mobile);

        $layout = (string) file_get_contents(WK_ROOT . '/views/store/layouts/main.php');
        $this->assertSame(3, substr_count($layout, 'wk-btn-label'),
            'the cart and both account labels need wrapping for the header to fold');
    }

    /** Type set for a desktop column is small on a phone. */
    public function testTheSmallestLabelsAreLiftedOnAPhone(): void
    {
        $mobile = substr($this->css(), strrpos($this->css(), '/* ── Phones'));
        foreach (['.wk-product-cat', '.wk-product-badge'] as $sel) {
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($sel, '/') . '\s*\{\s*font-size:\s*12px/',
                $mobile,
                "{$sel} stays at 11px on a phone"
            );
        }
    }

    /** A form laid out in two columns has nowhere to put them on a phone. */
    public function testSideBySideFieldsStackOnAPhone(): void
    {
        $mobile = substr($this->css(), strrpos($this->css(), '/* ── Phones'));
        $this->assertStringContainsString('.wk-phone-field { grid-template-columns: minmax(0, 1fr); }', $mobile,
            'the country picker and the number share a line too narrow for either');
    }

    // ── Layouts that have to be able to collapse ─────────────────────────

    /**
     * A column count written onto the element cannot be changed by a media
     * query, so every one of these is a layout that stays two or three columns
     * wide on a 375px screen. They belong in the stylesheet.
     */
    public function testNoStorefrontViewHardCodesItsColumns(): void
    {
        $offenders = [];
        $dir = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(WK_ROOT . '/views/store', \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($dir as $file) {
            if ($file->getExtension() !== 'php') continue;
            $src = (string) file_get_contents($file->getPathname());

            preg_match_all('/style="[^"]*grid-template-columns:\s*([^;"]+)/i', $src, $m);
            foreach ($m[1] as $value) {
                // auto-fill and auto-fit already adapt to the space available.
                if (stripos($value, 'auto-fi') !== false) continue;
                if (preg_match('/fr\s+[\d.]*fr|repeat\(\s*[2-9]/', $value)) {
                    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(WK_ROOT) + 1));
                    $offenders[] = "{$rel}: {$value}";
                }
            }
        }

        $this->assertSame([], $offenders,
            "these layouts cannot collapse on a phone:\n  " . implode("\n  ", $offenders));
    }

    /** The product page put an 80px picture beside the details on a phone. */
    public function testTheProductPageStacksOnAPhone(): void
    {
        $product = (string) file_get_contents(WK_ROOT . '/views/store/product.php');
        $this->assertStringContainsString('class="wk-product-layout"', $product);
        $this->assertStringContainsString('class="wk-buy-row"', $product);

        $mobile = substr($this->css(), strrpos($this->css(), '/* ── Product page'));
        $this->assertStringContainsString('.wk-product-layout { grid-template-columns: minmax(0, 1fr)', $mobile);
        $this->assertStringContainsString('.wk-buy-row .wk-buy-cta { flex: 1 0 100%;', $mobile,
            'the buy button stays wedged beside the quantity stepper');
    }

    /**
     * Sizing on the element beats the stylesheet, so the buy button could not
     * be widened for a phone while it carried its own flex value.
     */
    public function testTheBuyButtonIsSizedFromTheStylesheet(): void
    {
        $product = (string) file_get_contents(WK_ROOT . '/views/store/product.php');
        $button = substr($product, strpos($product, 'id="addToCartBtn"'), 300);

        $this->assertStringNotContainsString('flex:1', $button,
            'an inline flex value here cannot be overridden for a narrow screen');
        $this->assertStringContainsString('wk-buy-cta', $button);
    }

    /** A panel wider than the phone it opens on hangs off the side. */
    public function testTheChatPanelFitsTheScreen(): void
    {
        $css = $this->css();
        $this->assertStringContainsString('.wk-chat-window', $css);
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 430px\) \{\s*\.wk-chat-window \{\s*width: auto !important/',
            $css,
            'the panel keeps its fixed 380px on a 375px screen'
        );

        $layout = (string) file_get_contents(WK_ROOT . '/views/store/layouts/main.php');
        $this->assertStringContainsString('class="wk-chat-window"', $layout,
            'the rule has nothing to attach to');
    }
}
