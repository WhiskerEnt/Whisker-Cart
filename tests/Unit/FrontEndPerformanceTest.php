<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The two things that decide how quickly a shop appears: what has to arrive
 * before the browser can draw anything, and whether the space each thing will
 * occupy is known before it lands.
 */
class FrontEndPerformanceTest extends TestCase
{
    private function css(string $file): string
    {
        return (string) file_get_contents(WK_ROOT . '/assets/css/' . $file);
    }

    /**
     * A stylesheet @import is not discovered until the file containing it has
     * been fetched and parsed, so it delays the first paint by a whole extra
     * round trip that the browser had no way to start early.
     */
    public function testNoStylesheetImportsAnotherOverTheNetwork(): void
    {
        foreach (['store.css', 'admin.css'] as $file) {
            $this->assertDoesNotMatchRegularExpression(
                '/^\s*@import\s+url\(/m',
                $this->css($file),
                "{$file} pulls in another stylesheet over the network, which blocks the first paint twice over"
            );
        }
    }

    /** The font comes from another origin, so the connection is opened early. */
    public function testTheFontOriginsArePreconnected(): void
    {
        $layout = (string) file_get_contents(WK_ROOT . '/views/store/layouts/main.php');
        $this->assertStringContainsString('rel="preconnect" href="https://fonts.googleapis.com"', $layout);
        $this->assertStringContainsString('rel="preconnect" href="https://fonts.gstatic.com" crossorigin', $layout,
            'the file host is a second origin and needs its own connection');
    }

    /**
     * The page must draw before the font arrives. Loading it as print media and
     * switching on load is what keeps it out of the critical path — with a
     * noscript copy so it still arrives without JavaScript.
     */
    public function testTheFontDoesNotBlockTheFirstPaint(): void
    {
        $layout = (string) file_get_contents(WK_ROOT . '/views/store/layouts/main.php');

        // The href is a PHP variable, so the assertion is on the shape of the
        // tag and on what that variable holds.
        $this->assertMatchesRegularExpression(
            '/\$wkFonts\s*=\s*\'https:\/\/fonts\.googleapis\.com/',
            $layout,
            'the font URL is not where this test expects it'
        );
        $this->assertMatchesRegularExpression(
            '/<link rel="stylesheet" href="[^"]*wkFonts[^"]*" media="print" onload=/',
            $layout,
            'the font stylesheet blocks rendering'
        );
        $this->assertMatchesRegularExpression('/<noscript><link rel="stylesheet" href="[^"]*wkFonts/', $layout,
            'without JavaScript the font would never load at all');
    }

    /** Drawn in a fallback first, so the fallback has to be a near match. */
    public function testTheFallbackFontIsNotJustSansSerif(): void
    {
        $css = $this->css('store.css');
        preg_match('/--font:\s*([^;]+);/', $css, $m);
        $this->assertNotEmpty($m, 'there is no font stack');

        $stack = array_map('trim', explode(',', $m[1]));
        $this->assertGreaterThan(2, count($stack),
            'the text is drawn in the fallback before the real font arrives, so a bare sans-serif moves the page when it swaps');
        $this->assertStringContainsString('Nunito', $stack[0]);
    }

    /**
     * The hero image is the largest thing on the front page. Until it loads its
     * box is empty, and everything below it moves when it arrives — unless the
     * space is held open.
     */
    public function testTheHeroImageHasItsSpaceReservedAtEverySize(): void
    {
        $css = $this->css('store.css');

        $block = substr($css, strpos($css, '.wk-hero-slide-img {'));
        $block = substr($block, 0, strpos($block, '}'));
        $this->assertStringContainsString('min-height', $block,
            'the hero image box collapses until the picture loads');

        // The mobile rule caps the image shorter, so it needs its own reservation.
        $mobile = substr($css, strpos($css, '@media (max-width: 768px)'));
        $mobile = substr($mobile, 0, 900);
        $this->assertMatchesRegularExpression(
            '/\.wk-hero-slide-img\s*\{[^}]*min-height/',
            $mobile,
            'on a phone the hero image is capped shorter but its space is not reserved'
        );
    }

    /** Product cards hold their shape before their pictures arrive. */
    public function testProductImagesReserveTheirShape(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.wk-product-img\s*\{[^}]*aspect-ratio/',
            $this->css('store.css'),
            'a grid of product cards reflows as each picture loads'
        );
    }

    // ── Accessibility ────────────────────────────────────────────────────

    /** A dot with no text in it has to say what it does. */
    public function testCarouselDotsAreNamed(): void
    {
        $js = (string) file_get_contents(WK_ROOT . '/assets/js/store.js');
        $block = substr($js, strpos($js, "createElement('button')"));
        $block = substr($block, 0, 600);

        $this->assertStringContainsString('aria-label', $block,
            'the carousel dots are announced as unnamed buttons');
        $this->assertStringContainsString('aria-selected', $block,
            'nothing says which slide is the current one');
    }

    /** Ten pixels is not something a thumb can hit. */
    public function testCarouselDotsAreBigEnoughToTap(): void
    {
        $css = $this->css('store.css');
        $block = substr($css, strpos($css, '.wk-carousel-dot {'));
        $block = substr($block, 0, strpos($block, '}'));

        preg_match('/width:\s*(\d+)px/', $block, $w);
        preg_match('/height:\s*(\d+)px/', $block, $h);
        $this->assertGreaterThanOrEqual(24, (int) ($w[1] ?? 0), 'the tap target is smaller than 24px');
        $this->assertGreaterThanOrEqual(24, (int) ($h[1] ?? 0), 'the tap target is smaller than 24px');

        // The dot itself stays small; it is drawn inside the larger button.
        $this->assertStringContainsString('.wk-carousel-dot::after', $css,
            'the visible dot should be drawn inside the hit area, not be the hit area');
    }

    /**
     * The accent is chosen to look right as a fill. As small text on a pale
     * page the same colour is too light to read, so text uses a darker tone.
     */
    public function testEveryThemeHasADarkerAccentForText(): void
    {
        $css = $this->css('store.css');

        $fills = preg_match_all('/--wk-purple:\s*#[0-9a-f]{6};/i', $css);
        $inks  = preg_match_all('/--wk-purple-ink:\s*#[0-9a-f]{6};/i', $css);
        $this->assertSame($fills, $inks, 'a theme defines an accent with no readable text tone to go with it');

        preg_match_all('/--wk-purple-ink:\s*(#[0-9a-f]{6});/i', $css, $m);
        foreach ($m[1] as $hex) {
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($hex, '#ffffff'),
                "{$hex} is too light to read as text on a white background");
        }
    }

    /** Links must not be left using the lighter fill colour for their text. */
    public function testAccentTextUsesTheInkTone(): void
    {
        $css = $this->css('store.css');

        // The `color` property only. border-color and background-color both
        // end in "color" and are legitimate uses of the fill tone — it is
        // text that needs the darker one.
        preg_match_all('/^[^\n]*(?<![-\w])color:\s*var\(--wk-purple\)[^\n]*$/m', $css, $m);

        foreach ($m[0] as $line) {
            $this->assertStringContainsString('wk-footer', $line,
                "this rule paints text in the fill colour, which is too light to read: " . trim($line));
        }
    }

    private function contrast(string $a, string $b): float
    {
        $lum = static function (string $hex): float {
            $hex = ltrim($hex, '#');
            $out = 0.0;
            foreach ([[0, 0.2126], [2, 0.7152], [4, 0.0722]] as [$i, $weight]) {
                $c = hexdec(substr($hex, $i, 2)) / 255;
                $c = $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
                $out += $c * $weight;
            }
            return $out;
        };
        $l1 = $lum($a); $l2 = $lum($b);
        return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
    }
}
