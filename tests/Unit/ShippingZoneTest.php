<?php
namespace Tests\Unit;

use App\Services\ShippingZoneService;
use PHPUnit\Framework\TestCase;

/**
 * Zones decide what a customer is charged for delivery, so the rules about
 * which zone applies and where its numbers come from are pinned down here.
 */
class ShippingZoneTest extends TestCase
{
    private function service(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Services/ShippingZoneService.php');
    }

    public function testCodesAreParsedAndNormalised(): void
    {
        $this->assertSame(
            ['DE', 'FR', 'IT'],
            ShippingZoneService::codes(['countries' => ' de , fr,IT ']),
            'stored codes are read case-insensitively and trimmed'
        );
        $this->assertSame([], ShippingZoneService::codes(['countries' => '']));
        $this->assertSame([], ShippingZoneService::codes([]));
    }

    public function testDuplicateCodesInOneZoneCollapse(): void
    {
        $this->assertSame(['DE'], ShippingZoneService::codes(['countries' => 'DE,de,DE']));
    }

    /**
     * A destination with no zone must fall through to the store-wide settings,
     * which is what every shop without zones already relies on.
     */
    public function testNoZoneMeansNoOverrides(): void
    {
        $rates = ShippingZoneService::ratesFor('ZZ');
        $this->assertNull($rates['zone']);
        $this->assertSame([], $rates['values'], 'nothing is overridden, so store-wide settings apply');

        $empty = ShippingZoneService::ratesFor('');
        $this->assertNull($empty['zone']);
        $this->assertSame([], $empty['values']);
    }

    public function testZoneValuesTakePrecedenceOverStoreSettings(): void
    {
        $checkout = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CheckoutController.php');
        $this->assertStringContainsString('ShippingZoneService::ratesFor(', $checkout);
        $this->assertStringContainsString(
            'if (array_key_exists($key, $zoneRates)) return $zoneRates[$key];',
            $checkout,
            'a zone value must be preferred over the store-wide setting for the same key'
        );
    }

    public function testTheDestinationReachesTheRateCalculation(): void
    {
        $checkout = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CheckoutController.php');
        $this->assertMatchesRegularExpression(
            '/calculateShipping\(\$cart, \(string\) \(\$address\[.country.\] \?\? .{2}\)\)/',
            $checkout,
            'shipping cannot be zoned if the destination never reaches the calculation'
        );
        $this->assertMatchesRegularExpression(
            '/function calculateShipping\(array \$cart, string \$countryCode = /',
            $checkout
        );
    }

    public function testFirstMatchWinsByOrder(): void
    {
        $src = $this->service();
        $this->assertMatchesRegularExpression(
            '/ORDER BY sort_order, id/',
            $src,
            'overlapping zones are settled by order, so the query must be ordered'
        );
        $this->assertStringContainsString('break;', $src, 'the first matching zone must win');
        $this->assertStringContainsString('is_active = 1', $src, 'a switched-off zone must not apply');
    }

    public function testAdminStoresOnlyKnownCountryCodes(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/ShippingController.php');
        $this->assertStringContainsString('CountryService::exists($code)', $controller);
    }

    /** A zone with no countries would silently never apply. */
    public function testAnEmptyZoneIsRefused(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/ShippingController.php');
        $this->assertStringContainsString('Choose at least one country for the zone', $controller);
    }

    /** Blank means "no cap", which is not a cap of zero. */
    public function testABlankPerItemCapIsStoredAsNull(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/ShippingController.php');
        $this->assertMatchesRegularExpression(
            "/'per_item_cap'\s*=>\s*\\\$cap === ''\s*\?\s*null/",
            $controller,
            'an empty cap field must store null, or per-item shipping would be capped at zero'
        );

        $migration = (string) file_get_contents(WK_ROOT . '/sql/migrations/20260818_v140_shipping_zones.sql');
        $this->assertMatchesRegularExpression(
            '/per_item_cap DECIMAL\(10,2\) DEFAULT NULL/',
            $migration,
            'the column must be nullable for that to be storable'
        );
    }

    public function testZoneTablesAreCreatedWithoutTouchingExistingRates(): void
    {
        $migration = (string) file_get_contents(WK_ROOT . '/sql/migrations/20260818_v140_shipping_zones.sql');
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS wk_shipping_zones', $migration);
        // Existing shops must not have their shipping silently changed. Only
        // statement-initial keywords count — ON UPDATE CURRENT_TIMESTAMP in a
        // column definition is not a write.
        $this->assertSame(
            0,
            preg_match('/^\s*(UPDATE|DELETE|ALTER)\s/im', $migration),
            'the zones migration must not rewrite any existing shipping settings'
        );
    }
}
