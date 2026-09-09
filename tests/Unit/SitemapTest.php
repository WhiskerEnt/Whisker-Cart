<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A sitemap is only useful if it matches the shop. It was written once, by a
 * button, so a store that added products after clicking it advertised a
 * sitemap holding nothing but its front page.
 */
class SitemapTest extends TestCase
{
    private function service(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Services/SeoService.php');
    }

    public function testItCoversEverythingPublic(): void
    {
        $src = $this->service();
        $fn = substr($src, strpos($src, 'public static function generateSitemap('));
        $fn = substr($fn, 0, strpos($fn, 'public static function writeSitemap('));

        foreach (['wk_categories', 'wk_products', 'wk_pages'] as $table) {
            $this->assertStringContainsString($table, $fn, "the sitemap must include {$table}");
        }
        // Only what a visitor can actually reach.
        $this->assertSame(3, substr_count($fn, 'is_active=1'), 'inactive rows must not be listed');
    }

    /** Every place a public URL appears or disappears must say so. */
    public function testEveryMutationMarksTheSitemapStale(): void
    {
        $expected = [
            'ProductController'  => ['store', 'update', 'delete'],
            'CategoryController' => ['store', 'update', 'delete'],
            'PageController'     => ['store', 'update', 'delete'],
            'ImportController'   => ['process'],
        ];

        foreach ($expected as $controller => $methods) {
            $src = (string) file_get_contents(WK_ROOT . "/app/Controllers/Admin/{$controller}.php");
            foreach ($methods as $method) {
                $start = strpos($src, "public function {$method}(Request \$request");
                $this->assertNotFalse($start, "{$controller}::{$method} not found");

                $next = strpos($src, "\n    public function ", $start + 10);
                $body = $next === false ? substr($src, $start) : substr($src, $start, $next - $start);

                $this->assertStringContainsString(
                    'markSitemapStale',
                    $body,
                    "{$controller}::{$method} changes what the public can reach but never marks the sitemap stale"
                );
            }
        }
    }

    /**
     * A bulk import saves a thousand products in one request. Rewriting the
     * whole file a thousand times would make the import crawl.
     */
    public function testTheRewriteHappensOncePerRequest(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('register_shutdown_function', $src, 'the work is deferred');
        $this->assertStringContainsString('if (self::$flushRegistered) return;', $src,
            'and the handler is only registered once, however many things change');
    }

    /** A sitemap that cannot be written must not take the page down with it. */
    public function testAFailedWriteIsSurvivable(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('@file_put_contents', $src);
        $this->assertStringContainsString('catch (\Throwable $e)', $src);
    }
}
