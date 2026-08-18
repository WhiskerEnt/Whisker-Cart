<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Contract checks for the cookie consent banner. These read the shipped
 * source so the banner cannot quietly lose the properties that make it
 * lawful to use: an equally easy refusal, no cookies written before the
 * shopper answers, and a record of what was granted.
 */
class CookieConsentTest extends TestCase
{
    private function partial(): string
    {
        return (string) file_get_contents(WK_ROOT . '/views/store/partials/cookie-consent.php');
    }

    public function testBannerIsOffUntilTheStoreOwnerEnablesIt(): void
    {
        $this->assertMatchesRegularExpression(
            "/setting\('privacy',\s*'cookie_consent',\s*'0'\)\s*!==\s*'1'\)\s*return;/",
            $this->partial(),
            'the banner must default to off and return early when disabled'
        );
    }

    public function testRefusingIsAsEasyAsAccepting(): void
    {
        $src = $this->partial();
        $this->assertStringContainsString('data-consent="reject"', $src);
        $this->assertStringContainsString('data-consent="accept"', $src);

        // Both sit in the same action row, so neither can be buried a level down.
        $this->assertSame(
            1,
            preg_match('/<div class="wk-cookie-actions">(.*?)<\/div>/s', $src, $m),
            'could not locate the action row'
        );
        $this->assertStringContainsString('data-consent="reject"', $m[1]);
        $this->assertStringContainsString('data-consent="accept"', $m[1]);
    }

    public function testNoOptionalCategoryIsPreTicked(): void
    {
        $this->assertStringNotContainsString(
            'data-category="analytics" checked',
            $this->partial(),
            'optional categories must start off — pre-ticked consent is not consent'
        );
        $this->assertSame(
            0,
            preg_match('/<input type="checkbox"[^>]*data-category[^>]*checked/', $this->partial()),
            'optional categories must start off — pre-ticked consent is not consent'
        );
    }

    public function testConsentRecordCarriesVersionAndTimestamp(): void
    {
        $src = $this->partial();
        $this->assertStringContainsString('{v: VERSION, ts: Math.floor(Date.now() / 1000)}', $src);
        $this->assertStringContainsString('(v && v.v === VERSION) ? v : null', $src);
    }

    public function testCookieIsScopedAndNotReadableCrossSite(): void
    {
        $src = $this->partial();
        $this->assertStringContainsString('SameSite=Lax', $src);
        $this->assertStringContainsString("location.protocol === 'https:' ? ';Secure' : ''", $src);
    }

    public function testLayoutRendersTheBannerAndAReopenLink(): void
    {
        $layout = (string) file_get_contents(WK_ROOT . '/views/store/layouts/main.php');
        $this->assertStringContainsString("partials/cookie-consent.php", $layout);
        $this->assertStringContainsString('WhiskerConsent.reopen()', $layout);
    }

    public function testEveryOfferedCategoryIsDeclaredToTheScript(): void
    {
        $src = $this->partial();
        preg_match_all('/\$categories\[\'([a-z_]+)\'\]/', $src, $declared);
        $this->assertNotEmpty($declared[1], 'no categories found');
        $this->assertStringContainsString(
            'var OPTIONAL = <?= json_encode(array_keys($categories)) ?>',
            $src,
            'the script must be driven by the same category list the markup renders'
        );
    }
}
