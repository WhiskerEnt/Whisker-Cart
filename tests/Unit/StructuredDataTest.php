<?php
namespace Tests\Unit;

use App\Services\SeoService;
use PHPUnit\Framework\TestCase;

/**
 * Structured data is read by machines and shown to people in search results,
 * so the rules here are about not claiming things that are not true: no
 * ratings without reviews, no answers without questions, no half-built trail.
 */
class StructuredDataTest extends TestCase
{
    private function service(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Services/SeoService.php');
    }

    private function decode(string $html): ?array
    {
        if (!preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m)) return null;
        return json_decode($m[1], true);
    }

    // ── Breadcrumbs ──────────────────────────────────────────────────────

    public function testABreadcrumbIsNumberedInOrder(): void
    {
        $html = SeoService::breadcrumbSchema([
            ['name' => 'Home', 'url' => 'https://shop.test/'],
            ['name' => 'Jeans', 'url' => 'https://shop.test/category/jeans'],
            ['name' => 'Relaxed Fit', 'url' => null],
        ]);
        $data = $this->decode($html);

        $this->assertSame('BreadcrumbList', $data['@type']);
        $this->assertCount(3, $data['itemListElement']);
        $this->assertSame([1, 2, 3], array_column($data['itemListElement'], 'position'));

        // The page itself is the end of the trail and needs no link.
        $this->assertArrayNotHasKey('item', $data['itemListElement'][2]);
        $this->assertSame('https://shop.test/category/jeans', $data['itemListElement'][1]['item']);
    }

    /** A trail of one step is not a trail. */
    public function testASingleCrumbIsNotPublished(): void
    {
        $this->assertSame('', SeoService::breadcrumbSchema([['name' => 'Home', 'url' => '/']]));
        $this->assertSame('', SeoService::breadcrumbSchema([]));
    }

    public function testEmptyCrumbsAreDropped(): void
    {
        $html = SeoService::breadcrumbSchema([
            ['name' => 'Home', 'url' => '/'],
            ['name' => '', 'url' => '/nowhere'],
            ['name' => 'Thing', 'url' => null],
        ]);
        $data = $this->decode($html);
        $this->assertCount(2, $data['itemListElement'], 'a crumb with no name is not a crumb');
        $this->assertSame([1, 2], array_column($data['itemListElement'], 'position'),
            'and the numbering closes up behind it');
    }

    // ── FAQ ──────────────────────────────────────────────────────────────

    public function testTheFaqBecomesQuestionsAndAnswers(): void
    {
        $data = $this->decode(SeoService::faqSchema([
            ['q' => 'Does it shrink?', 'a' => 'Not if you wash cold.'],
            ['q' => 'True to size?', 'a' => 'Yes.'],
        ]));

        $this->assertSame('FAQPage', $data['@type']);
        $this->assertCount(2, $data['mainEntity']);
        $this->assertSame('Question', $data['mainEntity'][0]['@type']);
        $this->assertSame('Not if you wash cold.', $data['mainEntity'][0]['acceptedAnswer']['text']);
    }

    /** Half a pair is not a question worth publishing. */
    public function testIncompleteFaqEntriesAreNotPublished(): void
    {
        $this->assertSame('', SeoService::faqSchema([]));
        $this->assertSame('', SeoService::faqSchema([['q' => 'Lonely question', 'a' => '']]));
        $this->assertSame('', SeoService::faqSchema([['q' => '', 'a' => 'Lonely answer']]));

        $partial = $this->decode(SeoService::faqSchema([
            ['q' => 'Good', 'a' => 'Answer'],
            ['q' => 'Bad', 'a' => ''],
        ]));
        $this->assertCount(1, $partial['mainEntity']);
    }

    // ── The shop itself ──────────────────────────────────────────────────

    /** Store details the shopkeeper filled in should reach search engines. */
    public function testTheOrganisationCarriesWhatTheAdminEntered(): void
    {
        $src = $this->service();
        $fn = substr($src, strpos($src, 'public static function organizationSchema('));
        $fn = substr($fn, 0, strpos($fn, 'public static function websiteSchema('));

        foreach (['site_name', 'logo_url', 'store_phone', 'contact_email', 'store_tax_id'] as $setting) {
            $this->assertStringContainsString("'{$setting}'", $fn, "{$setting} is never published");
        }
        $this->assertStringContainsString('postalAddress()', $fn);
        $this->assertStringContainsString('socialProfiles()', $fn, 'the shop profiles belong in sameAs');
    }

    /** A relative logo path is meaningless to anything reading the markup. */
    public function testTheLogoIsPublishedAsAnAbsoluteUrl(): void
    {
        $src = $this->service();
        $fn = substr($src, strpos($src, 'public static function organizationSchema('));
        $fn = substr($fn, 0, strpos($fn, 'public static function websiteSchema('));
        $this->assertStringContainsString("preg_match('#^https?://#i', \$logo)", $fn);
    }

    /** A phone number or a mailto is not a social profile. */
    public function testOnlyRealProfilesReachSameAs(): void
    {
        $src = $this->service();
        $fn = substr($src, strpos($src, 'private static function socialProfiles('));
        $this->assertStringContainsString("str_starts_with(\$link['url'], 'http')", $fn);
        $this->assertStringContainsString("wa.me/", $fn, 'a WhatsApp link is a way to contact, not a profile');
    }

    /** The search box markup has to point at a search that exists. */
    public function testTheSearchActionMatchesTheRealSearchUrl(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('/search?q={search_term_string}', $src);

        $routes = (string) file_get_contents(WK_ROOT . '/config/routes.php');
        $this->assertStringContainsString("\$router->get('/search'", $routes);

        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/ProductController.php');
        $search = substr($controller, strpos($controller, 'public function search('));
        $this->assertStringContainsString("query('q')", substr($search, 0, 400),
            'the search page must read the parameter the markup promises');
    }

    /** Turning schema off must turn all of it off, not some. */
    public function testTheSwitchGovernsEveryKind(): void
    {
        $src = $this->service();
        foreach (['organizationSchema', 'websiteSchema', 'breadcrumbSchema', 'faqSchema', 'productSchema'] as $method) {
            $fn = substr($src, strpos($src, "function {$method}("));
            $this->assertStringContainsString(
                "schema_org_enabled",
                substr($fn, 0, 400),
                "{$method}() ignores the setting that is supposed to disable it"
            );
        }
    }

    /** Repeating the shop's identity on every page says nothing new. */
    public function testTheShopIdentityIsPublishedOnTheFrontPageOnly(): void
    {
        $layout = (string) file_get_contents(WK_ROOT . '/views/store/layouts/main.php');
        $this->assertStringContainsString('organizationSchema()', $layout);
        $this->assertStringContainsString("rtrim(\$wkPath, '/') === \$wkBase", $layout,
            'the front page is the only place it belongs');
    }
}
