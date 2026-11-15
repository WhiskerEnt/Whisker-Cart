<?php
namespace App\Services;

use Core\Database;

/**
 * Which products lead the homepage.
 *
 * The shopkeeper chooses them by ticking "Show in homepage slider" on a
 * product. A shop that has not chosen any still gets a slider: the remaining
 * places are filled from what the shop already has, in this order, with
 * products that have a photo ahead of those without at every step:
 *
 *   chosen -> best-sellers -> recently added -> on sale -> a daily pick
 *
 * The daily pick is random but seeded by the date, so the homepage does not
 * change between two visits on the same day.
 */
final class ShowcaseService
{
    /** Places filled automatically when the shopkeeper has chosen fewer. */
    public const SLOTS = 6;

    /** Chosen products shown at most, so a shop that ticks everything still gets a slider. */
    public const MAX_CHOSEN = 12;

    /** "Recently added" means within this many days. */
    public const RECENT_DAYS = 60;

    /** Order states that mean the goods were actually sold. */
    private const SOLD = "'paid','processing','shipped','delivered'";

    private static ?array $cache = null;

    /**
     * @return array{products: array, chosen: int, auto: array<string,int>}
     *         the products in slider order, each with a `showcase_source`;
     *         how many the shopkeeper chose; and how many each automatic
     *         step contributed
     */
    public static function pick(): array
    {
        if (self::$cache !== null) return self::$cache;

        $picked = [];
        $auto = ['bestseller' => 0, 'recent' => 0, 'sale' => 0, 'daily' => 0];

        $chosen = self::fetch('p.is_featured = 1', 'p.created_at DESC', [], self::MAX_CHOSEN, $picked, false);
        foreach ($chosen as $p) $picked[$p['id']] = $p + ['showcase_source' => 'chosen'];

        $steps = [
            'bestseller' => [
                'p.id IN (SELECT oi.product_id FROM wk_order_items oi JOIN wk_orders o ON o.id = oi.order_id
                          WHERE o.status IN (' . self::SOLD . ') AND oi.product_id IS NOT NULL)',
                '(SELECT SUM(oi2.quantity) FROM wk_order_items oi2 JOIN wk_orders o2 ON o2.id = oi2.order_id
                   WHERE oi2.product_id = p.id AND o2.status IN (' . self::SOLD . ')) DESC',
                [],
            ],
            'recent' => ['p.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)', 'p.created_at DESC', [self::RECENT_DAYS]],
            'sale'   => ['p.sale_price > 0 AND p.sale_price < p.price', '(p.price - p.sale_price) / p.price DESC', []],
            'daily'  => ['1 = 1', 'RAND(TO_DAYS(CURDATE()))', []],
        ];

        foreach ($steps as $source => [$where, $order, $params]) {
            $room = self::SLOTS - count($picked);
            if ($room <= 0) break;
            foreach (self::fetch($where, $order, $params, $room, $picked, true) as $p) {
                $picked[$p['id']] = $p + ['showcase_source' => $source];
                $auto[$source]++;
            }
        }

        return self::$cache = [
            'products' => array_values($picked),
            'chosen'   => count($chosen),
            'auto'     => $auto,
        ];
    }

    /** How many slides were filled automatically. */
    public static function autoCount(array $pick): int
    {
        return array_sum($pick['auto']);
    }

    /** One sentence for the shopkeeper on where the slider's products come from. */
    public static function describe(array $pick): string
    {
        $auto = self::autoCount($pick);
        if (!$pick['products']) {
            return 'The homepage slider is empty because the shop has no products in stock yet.';
        }
        if ($auto === 0) {
            return 'The homepage slider shows the ' . $pick['chosen'] . ' product' . ($pick['chosen'] === 1 ? '' : 's')
                . ' you chose.';
        }

        $names = ['bestseller' => 'best-selling', 'recent' => 'recently added', 'sale' => 'on sale', 'daily' => "today's pick"];
        $parts = [];
        foreach ($pick['auto'] as $source => $n) {
            if ($n > 0) $parts[] = $n . ' ' . $names[$source];
        }
        $from = count($parts) > 1
            ? implode(', ', array_slice($parts, 0, -1)) . ' and ' . end($parts)
            : $parts[0];

        return $pick['chosen'] > 0
            ? 'The homepage slider shows the ' . $pick['chosen'] . ' product' . ($pick['chosen'] === 1 ? '' : 's')
                . ' you chose, then ' . $from . ' to fill it.'
            : 'The homepage slider is choosing for you: ' . $from . '.';
    }

    /** Forget the pick, so a test or an admin preview sees fresh data. */
    public static function reset(): void
    {
        self::$cache = null;
    }

    /**
     * Products for one step, photos first, skipping any already picked. Steps
     * that fill automatically never offer something that cannot be bought.
     */
    private static function fetch(string $where, string $order, array $params, int $limit, array $picked, bool $inStockOnly): array
    {
        $exclude = '';
        if ($picked) {
            $exclude = ' AND p.id NOT IN (' . implode(',', array_map('intval', array_keys($picked))) . ')';
        }
        $stock = $inStockOnly ? ' AND p.stock_quantity > 0' : '';

        try {
            return Database::fetchAll(
                "SELECT p.*, c.name AS category_name,
                        (SELECT image_path FROM wk_product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image,
                        (SELECT COUNT(*) FROM wk_variant_combos WHERE product_id = p.id AND is_active = 1) AS variant_count
                   FROM wk_products p
                   LEFT JOIN wk_categories c ON c.id = p.category_id
                  WHERE p.is_active = 1 AND ({$where}){$stock}{$exclude}
                  ORDER BY NOT EXISTS (SELECT 1 FROM wk_product_images WHERE product_id = p.id AND is_primary = 1), {$order}
                  LIMIT " . max(1, $limit),
                $params
            );
        } catch (\Throwable $e) {
            error_log('Whisker: homepage slider step failed: ' . $e->getMessage());
            return [];
        }
    }
}
