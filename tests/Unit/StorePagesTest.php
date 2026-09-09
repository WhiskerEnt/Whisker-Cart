<?php
namespace Tests\Unit;

use App\Services\StorePagesService as Pages;
use PHPUnit\Framework\TestCase;

/**
 * A shop could trade for months with no refund policy and hear about it first
 * from a chargeback. Nothing in the admin ever said which of the pages a shop
 * is expected to have were missing.
 */
class StorePagesTest extends TestCase
{
    private function controller(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/PageController.php');
    }

    // ── The list itself ──────────────────────────────────────────────────

    public function testEveryRecommendedPageSaysWhatItIsFor(): void
    {
        $pages = Pages::recommended();
        $this->assertNotEmpty($pages);

        foreach ($pages as $page) {
            $this->assertNotSame('', trim($page['slug']));
            $this->assertNotSame('', trim($page['title']));
            $this->assertGreaterThan(40, strlen($page['why']),
                "{$page['slug']} gives no reason to bother — a list of names is not advice");
            $this->assertArrayHasKey('used_by', $page);
        }
    }

    public function testSlugsAreUniqueAndUrlSafe(): void
    {
        $slugs = array_column(Pages::recommended(), 'slug');
        $this->assertSame($slugs, array_unique($slugs), 'two entries would fight over one page');

        foreach ($slugs as $slug) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
            $this->assertLessThanOrEqual(60, strlen($slug), 'wk_pages.slug is VARCHAR(60)');
        }
    }

    /** The ones other parts of the shop depend on should say so. */
    public function testPagesOtherFeaturesRelyOnAreMarked(): void
    {
        $byslug = [];
        foreach (Pages::recommended() as $p) $byslug[$p['slug']] = $p;

        $this->assertArrayHasKey('terms-and-conditions', $byslug);
        $this->assertNotNull($byslug['terms-and-conditions']['used_by'],
            'checkout asks shoppers to accept this and silently skips it when the page is absent');

        // And that dependency is real.
        $checkout = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CheckoutController.php');
        $this->assertStringContainsString("slug LIKE '%terms%'", $checkout);
    }

    // ── What the screen reports ──────────────────────────────────────────

    public function testEveryPageIsReportedAsOneOfThreeStates(): void
    {
        foreach (Pages::status() as $row) {
            $this->assertContains($row['state'], ['published', 'draft', 'missing']);
            $this->assertArrayHasKey('id', $row);
            if ($row['state'] === 'missing') $this->assertNull($row['id']);
        }
    }

    public function testTheSummaryCountsEveryPageExactlyOnce(): void
    {
        $summary = Pages::summary();
        $this->assertSame(
            count(Pages::recommended()),
            array_sum($summary),
            'a page is being counted twice or not at all'
        );
    }

    /** No database is not a crash; it is simply a shop with nothing yet. */
    public function testAMissingPagesTableReadsAsEverythingMissing(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Services/StorePagesService.php');
        $fn = substr($src, strpos($src, 'public static function status('));
        $this->assertStringContainsString('catch (\Exception $e)', $fn);
    }

    // ── Starting one off ─────────────────────────────────────────────────

    /**
     * A refund window invented by software and published unread is worse than
     * an empty page, because it looks settled.
     */
    public function testAStartedPageIsADraftAndSaysItIsUnfinished(): void
    {
        $body = $this->controller();
        $fn = substr($body, strpos($body, 'public function addRecommended('));

        $this->assertStringContainsString("'is_active' => 0,", $fn,
            'a template must never go live without being read');

        $starter = Pages::starter('refund-policy');
        $this->assertStringContainsString('This is a starting point', $starter);
        $this->assertStringContainsString('Nothing here is legal advice', $starter);
    }

    /** The blanks have to look like blanks. */
    public function testTheDecisionsAreLeftAsConspicuousPrompts(): void
    {
        foreach (['refund-policy', 'privacy-policy', 'shipping-policy', 'terms-and-conditions'] as $slug) {
            $starter = Pages::starter($slug);
            $this->assertGreaterThanOrEqual(4, preg_match_all('/\[[^\]]+\]/', $starter),
                "{$slug} reads as finished prose, which is what gets published unread");
        }
    }

    /** Whatever the shop is called, the text should call it that. */
    public function testTheShopsOwnNameIsFilledIn(): void
    {
        $starter = Pages::starter('terms-and-conditions');
        $this->assertStringNotContainsString('{shop}', $starter, 'the token was never replaced');
    }

    /** Content is stored through the same sanitiser as any other page. */
    public function testStarterContentGoesThroughTheSanitiser(): void
    {
        $fn = substr($this->controller(), strpos($this->controller(), 'public function addRecommended('));
        $this->assertStringContainsString('HtmlSanitizer::purify(', $fn);
    }

    // ── Not a way to create arbitrary pages ──────────────────────────────

    /** The slug arrives from the browser, so it is checked against the list. */
    public function testOnlyPagesOnTheListCanBeStarted(): void
    {
        $this->assertNotNull(Pages::find('refund-policy'));
        $this->assertNull(Pages::find('anything-else'));
        $this->assertNull(Pages::find(''));

        $fn = substr($this->controller(), strpos($this->controller(), 'public function addRecommended('));
        $this->assertStringContainsString('StorePagesService::find($slug)', $fn);
        $this->assertStringContainsString('That is not one of the recommended pages.', $fn);
    }

    public function testStartingOneTwiceOpensTheOneThatExists(): void
    {
        $fn = substr($this->controller(), strpos($this->controller(), 'public function addRecommended('));
        $this->assertStringContainsString('SELECT id FROM wk_pages WHERE slug = ?', $fn);
        $this->assertStringContainsString('admin/pages/edit/', $fn,
            'a second attempt would otherwise hit the unique constraint on slug');
    }

    public function testItIsProtectedAgainstForgery(): void
    {
        $fn = substr($this->controller(), strpos($this->controller(), 'public function addRecommended('));
        $this->assertStringContainsString('Session::verifyCsrf', $fn);
    }

    /** A new public URL means the sitemap is out of date. */
    public function testStartingAPageMarksTheSitemapStale(): void
    {
        $fn = substr($this->controller(), strpos($this->controller(), 'public function addRecommended('));
        $this->assertStringContainsString('markSitemapStale', $fn);
    }

    // ── The screen ───────────────────────────────────────────────────────

    /** Only what needs doing. A finished list is not a to-do list. */
    public function testThePanelShowsOnlyWhatIsUnfinished(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/admin/pages/index.php');
        $this->assertStringContainsString("if (\$r['state'] === 'published') continue;", $view);
        $this->assertStringContainsString('All of them are published', $view,
            'a shop that has done everything should be told so');
    }
}
