<?php
namespace App\Services;

use Core\Database;

/**
 * WHISKER — Product search.
 *
 * Relevance-ranked search over the product catalogue using plain SQL, so it
 * works on any MySQL/MariaDB host with no search server to run.
 *
 * The query is split into words and every word must match somewhere on the
 * product (name, SKU, category or description). Each word then contributes a
 * score based on where and how well it matched, so "red shoe" ranks a product
 * named "Red Running Shoe" above one that merely mentions red in its
 * description. Results are ordered by that score.
 */
class SearchService
{
    /** Words shorter than this are ignored unless the whole query is short. */
    private const MIN_WORD = 2;
    /** Upper bound on words considered, so a pasted paragraph can't build a huge query. */
    private const MAX_WORDS = 6;

    /**
     * Split a raw query into searchable words.
     *
     * @return string[]
     */
    public static function tokenize(string $query): array
    {
        $query = trim(preg_replace('/\s+/u', ' ', $query));
        if ($query === '') return [];

        $words = [];
        foreach (explode(' ', $query) as $word) {
            $word = trim($word);
            if ($word === '') continue;
            if (mb_strlen($word) < self::MIN_WORD && count($words) > 0) continue;
            $words[] = $word;
            if (count($words) >= self::MAX_WORDS) break;
        }
        // A single short word (e.g. "tv") is still a valid search.
        if (!$words && $query !== '') $words[] = $query;
        return $words;
    }

    /** Escape LIKE wildcards so a literal % or _ can be searched for. */
    private static function esc(string $word): string
    {
        $bs = chr(92); // backslash
        return str_replace([$bs, '%', '_'], [$bs . $bs, $bs . '%', $bs . '_'], $word);
    }

    /**
     * Build the WHERE + score SQL for a set of words.
     *
     * The two parameter lists are kept separate because placeholders bind in
     * statement order: every score placeholder in the SELECT is filled before
     * the first WHERE placeholder.
     *
     * @return array{where:string, score:string, whereParams:array, scoreParams:array}
     */
    private static function buildMatch(array $words): array
    {
        $whereParts  = [];
        $scoreParts  = [];
        $whereParams = [];
        $scoreParams = [];

        foreach ($words as $word) {
            $e = self::esc($word);
            $exact    = $word;
            $prefix   = $e . '%';
            $contains = '%' . $e . '%';

            // Every word must appear somewhere on the product.
            $whereParts[] = '(p.name LIKE ? OR p.sku LIKE ? OR c.name LIKE ? OR p.description LIKE ?)';
            array_push($whereParams, $contains, $contains, $contains, $contains);

            // Where it matched decides how much it is worth.
            $scoreParts[] =
                '(CASE WHEN p.name = ? THEN 120 ' .
                '      WHEN p.name LIKE ? THEN 60 ' .
                '      WHEN p.name LIKE ? THEN 30 ELSE 0 END) + ' .
                '(CASE WHEN p.sku LIKE ? THEN 45 ELSE 0 END) + ' .
                '(CASE WHEN c.name LIKE ? THEN 15 ELSE 0 END) + ' .
                '(CASE WHEN p.description LIKE ? THEN 5 ELSE 0 END)';
            array_push($scoreParams, $exact, $prefix, $contains, $prefix, $contains, $contains);
        }

        return [
            'where'       => implode(' AND ', $whereParts),
            'score'       => implode(' + ', $scoreParts),
            'whereParams' => $whereParams,
            'scoreParams' => $scoreParams,
        ];
    }

    /**
     * Small, fast result set for the header type-ahead.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function suggest(string $query, int $limit = 8): array
    {
        $words = self::tokenize($query);
        if (!$words) return [];
        $limit = max(1, min(20, $limit));

        $m = self::buildMatch($words);
        $params = array_merge($m['scoreParams'], $m['whereParams']);

        try {
            return Database::fetchAll(
                "SELECT p.id, p.name, p.slug, p.price, p.sale_price, p.stock_quantity,
                        c.name AS category_name,
                        (SELECT image_path FROM wk_product_images
                          WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image,
                        ({$m['score']}) AS relevance
                   FROM wk_products p
                   LEFT JOIN wk_categories c ON c.id = p.category_id
                  WHERE p.is_active = 1 AND ({$m['where']})
                  ORDER BY relevance DESC, (p.stock_quantity > 0) DESC, p.is_featured DESC, p.name
                  LIMIT {$limit}",
                $params
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /** Total matching products, for the results page pager. */
    public static function count(string $query): int
    {
        $words = self::tokenize($query);
        if (!$words) return 0;
        $m = self::buildMatch($words);
        try {
            return (int) Database::fetchValue(
                "SELECT COUNT(*) FROM wk_products p
                   LEFT JOIN wk_categories c ON c.id = p.category_id
                  WHERE p.is_active = 1 AND ({$m['where']})",
                $m['whereParams']
            );
        } catch (\Exception $e) {
            return 0;
        }
    }

    /** One page of ranked results for the full search page. */
    public static function search(string $query, int $limit, int $offset): array
    {
        $words = self::tokenize($query);
        if (!$words) return [];
        $limit  = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $m = self::buildMatch($words);
        $params = array_merge($m['scoreParams'], $m['whereParams']);

        try {
            return Database::fetchAll(
                "SELECT p.*, c.name AS category_name,
                        (SELECT image_path FROM wk_product_images
                          WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image,
                        (SELECT COUNT(*) FROM wk_variant_combos
                          WHERE product_id = p.id AND is_active = 1) AS variant_count,
                        ({$m['score']}) AS relevance
                   FROM wk_products p
                   LEFT JOIN wk_categories c ON c.id = p.category_id
                  WHERE p.is_active = 1 AND ({$m['where']})
                  ORDER BY relevance DESC, (p.stock_quantity > 0) DESC, p.name
                  LIMIT {$limit} OFFSET {$offset}",
                $params
            );
        } catch (\Exception $e) {
            return [];
        }
    }
}
