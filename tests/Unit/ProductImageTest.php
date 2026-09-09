<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pictures are the heaviest thing a shop sends and the thing most likely to
 * move the page about while it loads. Both are settled at upload, once.
 */
class ProductImageTest extends TestCase
{
    private function service(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Services/ImageService.php');
    }

    private function view(): string
    {
        return (string) file_get_contents(WK_ROOT . '/core/View.php');
    }

    // ── What happens to an upload ────────────────────────────────────────

    /** A photo off a phone is thousands of pixels wide and shown in a card. */
    public function testAnOversizedUploadIsBroughtDown(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('MAX_EDGE', $src);
        $this->assertStringContainsString('imagecopyresampled', $src);
        $this->assertStringContainsString('min(1, self::MAX_EDGE / max($width, $height))', $src,
            'the scale must never exceed 1 — enlarging a small picture makes it bigger and blurrier');
    }

    /** The original stays, so a browser without WebP loses nothing. */
    public function testTheWebpIsWrittenBesideTheOriginalNotOverIt(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('self::webpPath($path)', $src);
        $this->assertMatchesRegularExpression(
            '/webpPath\(string \$path\): string\s*\{\s*return preg_replace/',
            $src,
            'the WebP must be a sibling file, not a replacement'
        );
    }

    /** An animated GIF turned into a WebP this way would stop moving. */
    public function testAnimatedGifsAreLeftAlone(): void
    {
        $src = $this->service();
        $this->assertStringContainsString("\$ext !== 'gif' && function_exists('imagewebp')", $src,
            'a GIF would be flattened to a single frame');
    }

    /** A shop on a host without GD must still accept uploads. */
    public function testAHostWithoutGdStillWorks(): void
    {
        $src = $this->service();
        $this->assertStringContainsString("if (!extension_loaded('gd')", $src);
        $this->assertStringContainsString('$fallback()', $src,
            'without GD the upload should still succeed, just unprocessed');
    }

    /** Redrawing the pixels is what strips anything else hiding in the file. */
    public function testUploadsAreStillRedrawnToStripWhatIsHiddenInThem(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/ProductController.php');

        // Three ways an image gets in: saved against a product, held for a
        // product that does not exist yet, and a variant swatch. A path that
        // skips this is a path that stores whatever was uploaded.
        $this->assertSame(3, substr_count($controller, 'ImageService::process('),
            'an upload path stores the file without redrawing it');

        $this->assertStringNotContainsString('self::reencodeImage(', $controller,
            'the old re-encode is superseded and must not be left in use anywhere');
    }

    /** A transparent logo must not come back on a black square. */
    public function testTransparencyIsPreserved(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('imagesavealpha', $src);
        $this->assertStringContainsString('imagecolorallocatealpha', $src);
    }

    // ── What the page sends ──────────────────────────────────────────────

    /** Without a size the browser cannot hold the space, and the page jumps. */
    public function testEveryRenderedImageCarriesItsSize(): void
    {
        $view = $this->view();
        $fn = substr($view, strpos($view, 'public static function productImage('));

        $this->assertStringContainsString('ImageService::dimensions(', $fn);
        $this->assertStringContainsString('width="', $fn);
        $this->assertStringContainsString('height="', $fn);
    }

    /** The WebP is offered; the original is what a browser falls back to. */
    public function testTheOriginalRemainsTheFallback(): void
    {
        $fn = substr($this->view(), strpos($this->view(), 'public static function productImage('));
        $this->assertStringContainsString('<source type="image/webp"', $fn);
        $this->assertStringContainsString('</picture>', $fn);
        $this->assertStringContainsString('if (!$webp', $fn,
            'with no WebP the helper must still return a plain img');
    }

    /**
     * A gallery swaps the img's src by script. Wrapped in a picture, the
     * source keeps winning and the picture never changes.
     */
    public function testAScriptSwappedImageCanOptOutOfThePictureWrapper(): void
    {
        $fn = substr($this->view(), strpos($this->view(), 'public static function productImage('));
        $this->assertStringContainsString("!empty(\$opts['no_webp'])", $fn);

        $product = (string) file_get_contents(WK_ROOT . '/views/store/product.php');
        if (str_contains($product, "'id' => 'mainImg'")) {
            $call = substr($product, strpos($product, "'id' => 'mainImg'") - 220, 300);
            $this->assertStringContainsString('no_webp', $call,
                'the gallery image is wrapped in a picture, so changing it by script will not show');
        }
    }

    /** Only what is on screen at the start should compete for bandwidth. */
    public function testOffscreenImagesAreDeferred(): void
    {
        $fn = substr($this->view(), strpos($this->view(), 'public static function productImage('));
        $this->assertStringContainsString('loading="lazy"', $fn);
        $this->assertStringContainsString('fetchpriority="high"', $fn);

        // The carousel holds every featured product; only the first is visible.
        $home = (string) file_get_contents(WK_ROOT . '/views/store/home-v2.php');
        preg_match_all("/'eager' => ([^\]]+)\]/", $home, $m);
        foreach ($m[1] as $expr) {
            $this->assertStringNotContainsString('true', $expr,
                'every slide is marked high priority, so nine images compete for the first paint');
        }
    }

    /** Sizes recorded at upload, read back without a query per image. */
    public function testSizesAreLookedUpOnceForTheWholePage(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('static $loaded = false;', $src,
            'the lookup must not run a query per image');
        $this->assertStringContainsString('$dimensionCache', $src);
    }

    /** Existing shops have images from before any of this. */
    public function testThereIsAWayToCatchUpOlderImages(): void
    {
        $this->assertStringContainsString('public static function backfill(', $this->service());

        $migrations = glob(WK_ROOT . '/sql/migrations/*image_dimensions.sql');
        $this->assertNotEmpty($migrations, 'nothing adds the columns the sizes are stored in');
        $sql = (string) file_get_contents($migrations[0]);
        foreach (['width', 'height'] as $col) {
            $this->assertStringContainsString($col, $sql);
        }
    }
}
