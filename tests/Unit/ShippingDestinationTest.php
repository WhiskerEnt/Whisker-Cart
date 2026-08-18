<?php
namespace Tests\Unit;

use App\Services\CountryService;
use PHPUnit\Framework\TestCase;

/**
 * The country list and the rules about where a store will post to. The list
 * itself is asserted by value, and the enforcement path is checked against
 * the source, since a dropdown that hides a country is not the same as a
 * checkout that refuses it.
 */
class ShippingDestinationTest extends TestCase
{
    public function testListCoversTheIsoSet(): void
    {
        $all = CountryService::all();
        $this->assertGreaterThan(240, count($all), 'the ISO 3166-1 set has around 249 entries');

        foreach (['IN', 'US', 'GB', 'DE', 'JP', 'BR', 'ZA', 'AU', 'NZ', 'AE'] as $code) {
            $this->assertArrayHasKey($code, $all, "{$code} is missing from the country list");
        }
        foreach ($all as $code => $name) {
            $this->assertMatchesRegularExpression('/^[A-Z]{2}$/', $code, "bad country code: {$code}");
            $this->assertNotSame('', trim($name), "country {$code} has no name");
        }
    }

    public function testCodesAreResolvedToNames(): void
    {
        $this->assertSame('India', CountryService::name('IN'));
        $this->assertSame('Germany', CountryService::name('de'));
        $this->assertSame('United Kingdom', CountryService::name(' GB '));
    }

    /** An unknown code is echoed back rather than blanked, so nothing vanishes from an old order. */
    public function testUnknownCodeIsShownAsItself(): void
    {
        $this->assertSame('ZZ', CountryService::name('ZZ'));
        $this->assertFalse(CountryService::exists('ZZ'));
    }

    public function testEuGroupIsTheMemberStates(): void
    {
        $this->assertCount(27, CountryService::EU);
        foreach (CountryService::EU as $code) {
            $this->assertTrue(CountryService::exists($code), "EU member {$code} is not in the country list");
        }
        // Countries people often assume are members.
        foreach (['GB', 'CH', 'NO', 'IS', 'TR'] as $code) {
            $this->assertNotContains($code, CountryService::EU, "{$code} is not an EU member state");
        }
    }

    public function testCheckoutRefusesACountryTheStoreDoesNotShipTo(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CheckoutController.php');
        $this->assertStringContainsString(
            'CountryService::canShipTo(',
            $src,
            'hiding a country from the dropdown is not enough — the posted value must be checked'
        );

        // The check has to happen before the order is written.
        $checkAt = strpos($src, 'CountryService::canShipTo(');
        $insertAt = strpos($src, "Database::insert('wk_orders'");
        if ($insertAt !== false) {
            $this->assertLessThan($insertAt, $checkAt, 'the country check must precede the order insert');
        }
    }

    public function testAdminOnlyStoresKnownCodes(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/ShippingController.php');
        $this->assertStringContainsString('CountryService::exists($code)', $src);
        $this->assertStringContainsString("'ship_countries'", $src);
    }

    public function testDefaultsToDomestic(): void
    {
        $migration = (string) file_get_contents(WK_ROOT . '/sql/migrations/20260818_v140_shipping_destinations.sql');
        $this->assertStringContainsString("('shipping', 'ship_mode', 'domestic')", $migration);

        $schema = (string) file_get_contents(WK_ROOT . '/sql/schema.sql');
        $this->assertStringContainsString("('shipping', 'ship_mode', 'domestic')", $schema);
    }

    public function testOrderPageShowsBothCountries(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/admin/orders/show.php');
        $this->assertSame(
            2,
            preg_match_all('/\$country\(\$(?:billing|shipping_addr)\[.country.\]/', $view),
            'billing and shipping must both render a country'
        );
        $this->assertStringContainsString('CountryService::name(', $view, 'the stored ISO code must be resolved to a name');
    }
}
