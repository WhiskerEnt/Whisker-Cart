<?php
namespace App\Services;

use Core\Database;

/**
 * WHISKER — Shipping zones
 *
 * A zone is a set of countries with its own rate. Zones carry the same fields
 * as the store-wide shipping settings, so the rate maths does not change —
 * only where the numbers are read from.
 *
 * A destination with no matching zone falls back to the store-wide settings,
 * which is exactly what a shop without zones already has. Creating a zone
 * therefore changes shipping for its countries and nothing else.
 *
 * The first matching zone wins, ordered by sort_order. Overlapping zones are
 * allowed rather than policed: a shopkeeper who puts DE in both "Germany" and
 * "Europe" almost certainly means the specific one, so ordering settles it.
 */
class ShippingZoneService
{
    /** Fields a zone supplies, and the setting key each corresponds to. */
    private const RATE_FIELDS = [
        'method', 'flat_rate', 'flat_rate_below', 'free_threshold',
        'per_item', 'per_item_cap', 'weight_base', 'weight_per_kg',
    ];

    /** @var array<string,?array>|null resolved zones, memoised per request */
    private static array $resolved = [];

    public static function all(): array
    {
        try {
            return Database::fetchAll("SELECT * FROM wk_shipping_zones ORDER BY sort_order, name");
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function find(int $id): ?array
    {
        try {
            return Database::fetch("SELECT * FROM wk_shipping_zones WHERE id = ?", [$id]) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /** @return string[] ISO codes in a zone */
    public static function codes(array $zone): array
    {
        $codes = array_filter(array_map(
            static fn($c) => strtoupper(trim((string) $c)),
            explode(',', (string) ($zone['countries'] ?? ''))
        ));
        return array_values(array_unique($codes));
    }

    /**
     * The zone covering a destination, or null when the store-wide settings apply.
     */
    public static function forCountry(string $countryCode): ?array
    {
        $code = strtoupper(trim($countryCode));
        if ($code === '') return null;
        if (array_key_exists($code, self::$resolved)) return self::$resolved[$code];

        $match = null;
        try {
            $zones = Database::fetchAll(
                "SELECT * FROM wk_shipping_zones WHERE is_active = 1 ORDER BY sort_order, id"
            );
            foreach ($zones as $zone) {
                if (in_array($code, self::codes($zone), true)) { $match = $zone; break; }
            }
        } catch (\Exception $e) {
            $match = null;
        }

        return self::$resolved[$code] = $match;
    }

    /**
     * Rate settings for a destination.
     *
     * Returns the same shape whichever source it came from, so callers do not
     * branch on whether a zone was involved.
     *
     * @return array{values:array<string,string>, zone:?array}
     */
    public static function ratesFor(string $countryCode): array
    {
        $zone = self::forCountry($countryCode);
        if (!$zone) {
            return ['values' => [], 'zone' => null];
        }

        $values = [];
        foreach (self::RATE_FIELDS as $field) {
            $v = $zone[$field] ?? null;
            // per_item_cap is nullable and means "no cap" when unset; the rest
            // are numeric columns that always carry a value.
            if ($v === null || $v === '') continue;
            $values[$field] = (string) $v;
        }
        return ['values' => $values, 'zone' => $zone];
    }

    /** Clears the per-request memo, for use after an admin edit. */
    public static function clearCache(): void
    {
        self::$resolved = [];
    }

    /** Countries already claimed by another zone, for a warning in the admin. */
    public static function overlaps(array $codes, ?int $exceptZoneId = null): array
    {
        $clash = [];
        foreach (self::all() as $zone) {
            if ($exceptZoneId !== null && (int) $zone['id'] === $exceptZoneId) continue;
            foreach (array_intersect($codes, self::codes($zone)) as $code) {
                $clash[$code] = $zone['name'];
            }
        }
        return $clash;
    }
}
