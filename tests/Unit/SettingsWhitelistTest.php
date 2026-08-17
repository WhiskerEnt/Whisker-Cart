<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Guards the "posted but silently dropped" bug class: every field a settings
 * form posts MUST appear in its controller's save whitelist, or "Save" throws
 * the value away with no error. v1.3.2 fixed nine such fields (shop logo,
 * homepage layout, store country, ...) — this test keeps the two sides in
 * sync forever, by reading the actual source files.
 */
class SettingsWhitelistTest extends TestCase
{
    public function testEverySettingsFormFieldIsWhitelistedInSettingsController(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/SettingsController.php');
        $view       = (string) file_get_contents(WK_ROOT . '/views/admin/settings.php');

        $whitelist = [];
        foreach (['general', 'checkout', 'email'] as $group) {
            $this->assertSame(
                1,
                preg_match("/'{$group}'\s*=>\s*\[(.*?)\]/s", $controller, $m),
                "could not locate the '{$group}' whitelist in SettingsController — update this test's parser"
            );
            preg_match_all("/'([a-z0-9_]+)'/", $m[1], $keys);
            foreach ($keys[1] as $key) {
                $whitelist[] = "{$group}_{$key}";
            }
        }
        $this->assertNotEmpty($whitelist);

        preg_match_all('/name="((?:general|checkout|email)_[a-z0-9_]+)"/', $view, $posted);
        $this->assertNotEmpty($posted[1], 'no posted settings fields found in views/admin/settings.php — update this test\'s parser');

        foreach (array_unique($posted[1]) as $field) {
            $this->assertContains(
                $field,
                $whitelist,
                "views/admin/settings.php posts '{$field}' but SettingsController::update() does not whitelist it — the value is silently dropped on save"
            );
        }
    }

    public function testEveryShippingSettingsFieldIsSavedByShippingController(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/ShippingController.php');
        $view       = (string) file_get_contents(WK_ROOT . '/views/admin/shipping/settings.php');

        $this->assertSame(
            1,
            preg_match('/\$fields\s*=\s*\[(.*?)\];/s', $controller, $m),
            'could not locate the $fields list in ShippingController::updateSettings — update this test\'s parser'
        );
        preg_match_all("/'([a-z0-9_]+)'/", $m[1], $keys);
        $whitelist = $keys[1];
        $this->assertNotEmpty($whitelist);

        preg_match_all('/name="shipping_([a-z0-9_]+)"/', $view, $posted);
        $this->assertNotEmpty($posted[1]);

        foreach (array_unique($posted[1]) as $key) {
            if (str_starts_with($key, 'carrier_rate_')) {
                continue; // dynamic per-carrier fields, handled by their own loop
            }
            $this->assertContains(
                $key,
                $whitelist,
                "views/admin/shipping/settings.php posts 'shipping_{$key}' but ShippingController::updateSettings() does not save it"
            );
        }
    }
}
