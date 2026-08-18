<?php
namespace App\Controllers\Store;
use Core\{Request, View, Database, Response};

class ProductController
{
    public function show(Request $request, array $params = []): void
    {
        $product = Database::fetch(
            "SELECT p.*, c.name AS category_name FROM wk_products p
             LEFT JOIN wk_categories c ON c.id=p.category_id
             WHERE p.slug=? AND p.is_active=1", [$params['slug']]
        );
        if (!$product) { Response::notFound(); return; }

        $images = Database::fetchAll("SELECT * FROM wk_product_images WHERE product_id=? AND (alt_text='' OR alt_text IS NULL OR is_primary=1) ORDER BY is_primary DESC, sort_order", [$product['id']]);
        $related = Database::fetchAll(
            "SELECT p.*, (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
             FROM wk_products p WHERE p.category_id=? AND p.id!=? AND p.is_active=1 ORDER BY RAND() LIMIT 4",
            [$product['category_id'], $product['id']]
        );
        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

        // Get variant data
        $variants = \App\Services\VariantService::getForProduct($product['id']);

        // Reviews. The table arrives with a migration, so the service answers
        // with empties rather than throwing on a store that has not migrated.
        $reviewsOn   = \App\Services\ReviewService::enabled();
        $reviewStats = $reviewsOn
            ? \App\Services\ReviewService::stats((int) $product['id'])
            : ['count' => 0, 'average' => 0.0, 'breakdown' => []];
        $reviews     = $reviewsOn ? \App\Services\ReviewService::forProduct((int) $product['id']) : [];

        // SEO meta tags
        $primaryImage = !empty($images) ? ($images[0]['image_path'] ?? null) : null;
        $seoMeta = \App\Services\SeoService::renderMeta([
            'name'              => $product['name'],
            'meta_title'        => $product['meta_title'] ?? null,
            'meta_description'  => $product['meta_description'] ?? null,
            'meta_keywords'     => $product['meta_keywords'] ?? null,
            'description'       => $product['description'] ?? '',
            'short_description' => $product['short_description'] ?? '',
            'image'             => $product['og_image'] ?? $primaryImage,
            'type'              => 'product',
            'category_name'     => $product['category_name'] ?? null,
        ]);
        $productSchema = \App\Services\SeoService::productSchema(array_merge($product, [
            'primary_image' => $primaryImage,
            // Feeds aggregateRating, so search results can show stars.
            'rating_count'   => $reviewStats['count'],
            'rating_average' => $reviewStats['average'],
        ]));

        View::render('store/product', [
            'product'       => $product,
            'images'        => $images,
            'related'       => $related,
            'currency'      => $currency,
            'variants'      => $variants,
            'seoMeta'       => $seoMeta,
            'productSchema' => $productSchema,
            'reviewsOn'     => $reviewsOn,
            'reviewStats'   => $reviewStats,
            'reviews'       => $reviews,
            'reviewPolicy'  => \App\Services\ReviewService::policy(),
        ], 'store/layouts/main');
    }

    public function category(Request $request, array $params = []): void
    {
        $cat = Database::fetch("SELECT * FROM wk_categories WHERE slug=? AND is_active=1", [$params['slug']]);
        if (!$cat) { Response::notFound(); return; }

        $perPage = 12;
        $page = max(1, (int)($request->query('page') ?? 1));
        $sort = $request->query('sort') ?? 'newest';

        // Include subcategories
        $childIds = Database::fetchAll("SELECT id FROM wk_categories WHERE parent_id=? AND is_active=1", [$cat['id']]);
        $catIds = array_merge([$cat['id']], array_column($childIds, 'id'));
        $placeholders = implode(',', array_fill(0, count($catIds), '?'));

        $orderBy = match ($sort) {
            'price_low'  => 'COALESCE(p.sale_price, p.price) ASC',
            'price_high' => 'COALESCE(p.sale_price, p.price) DESC',
            'name_az'    => 'p.name ASC',
            'name_za'    => 'p.name DESC',
            'oldest'     => 'p.created_at ASC',
            default      => 'p.created_at DESC',
        };

        $totalProducts = (int)Database::fetchValue(
            "SELECT COUNT(*) FROM wk_products p WHERE p.category_id IN ({$placeholders}) AND p.is_active=1",
            $catIds
        );
        $totalPages = max(1, (int)ceil($totalProducts / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        $products = Database::fetchAll(
            "SELECT p.*, (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image,
                    (SELECT COUNT(*) FROM wk_variant_combos WHERE product_id=p.id AND is_active=1) AS variant_count
             FROM wk_products p WHERE p.category_id IN ({$placeholders}) AND p.is_active=1
             ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}",
            $catIds
        );

        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

        $seoMeta = \App\Services\SeoService::renderMeta([
            'name'             => $cat['name'],
            'meta_title'       => $cat['meta_title'] ?? null,
            'meta_description' => $cat['meta_description'] ?? null,
            'meta_keywords'    => $cat['meta_keywords'] ?? null,
            'description'      => $cat['description'] ?? '',
        ]);

        // Get all categories for shop sidebar
        $categories = Database::fetchAll(
            "SELECT c.id, c.name, c.slug, COUNT(p.id) AS product_count
             FROM wk_categories c
             LEFT JOIN wk_products p ON p.category_id=c.id AND p.is_active=1
             WHERE c.is_active=1 AND c.parent_id IS NULL
             GROUP BY c.id ORDER BY c.sort_order, c.name"
        );

        View::render('store/shop', [
            'products'        => $products,
            'categories'      => $categories,
            'currentCategory' => $cat,
            'currency'        => $currency,
            'sort'            => $sort,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'totalProducts'   => $totalProducts,
            'pageTitle'       => $cat['name'],
            'seoMeta'         => $seoMeta,
            'isHomepage'      => false,
        ], 'store/layouts/main');
    }

    /**
     * JSON endpoint for the header type-ahead.
     *
     * Returns a handful of ranked matches with everything the dropdown needs
     * to render a row, so the browser makes one request per keystroke burst.
     */
    public function suggest(Request $request, array $params = []): void
    {
        $q = trim((string)($request->query('q') ?? ''));
        if ($q === '' || mb_strlen($q) < 1) {
            \Core\Response::json(['query' => $q, 'results' => []]);
            return;
        }
        if (mb_strlen($q) > 100) $q = mb_substr($q, 0, 100);

        // Public endpoint hit on every keystroke — cap per-IP volume.
        if (!\Core\RateLimiter::attempt('search_suggest', $request->ip(), 120, 60)) {
            \Core\Response::json(['query' => $q, 'results' => [], 'throttled' => true], 429);
            return;
        }

        $rows = \App\Services\SearchService::suggest($q, 8);

        $results = [];
        foreach ($rows as $r) {
            $price = ($r['sale_price'] !== null && $r['sale_price'] > 0 && $r['sale_price'] < $r['price'])
                ? $r['sale_price'] : $r['price'];
            $results[] = [
                'name'     => $r['name'],
                'category' => $r['category_name'] ?? '',
                'url'      => View::url('product/' . rawurlencode($r['slug'])),
                'image'    => $r['image'] ? View::url('storage/uploads/products/' . $r['image']) : null,
                'price'    => \App\Services\CurrencyService::displayPrice((float)$price),
                'in_stock' => ((int)$r['stock_quantity']) > 0,
            ];
        }

        \Core\Response::json([
            'query'   => $q,
            'results' => $results,
            'more_url' => View::url('search?q=' . rawurlencode($q)),
        ]);
    }

    public function search(Request $request, array $params = []): void
    {
        // Keep the query raw (no clean()/HTML-escaping): product names are stored
        // raw, so the LIKE must match against the unescaped value. The view
        // htmlspecialchars() $q at render time.
        $q = trim((string)($request->input('q') ?? $request->query('q') ?? ''));
        $perPage = 12;
        $page = max(1, (int)($request->query('page') ?? 1));
        $products = [];
        $totalProducts = 0;
        $totalPages = 1;

        if (strlen($q) >= 2) {
            $totalProducts = \App\Services\SearchService::count($q);
            $totalPages = max(1, (int)ceil($totalProducts / $perPage));
            $page = min($page, $totalPages);
            $products = \App\Services\SearchService::search($q, $perPage, ($page - 1) * $perPage);
        }

        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

        View::render('store/shop', [
            'products'        => $products,
            'categories'      => [],
            'currentCategory' => null,
            'currency'        => $currency,
            'sort'            => 'relevance',
            'page'            => $page,
            'totalPages'      => $totalPages,
            'totalProducts'   => $totalProducts,
            'pageTitle'       => "Search: {$q}",
            'seoMeta'         => '',
            'isHomepage'      => false,
            'searchQuery'     => $q,
        ], 'store/layouts/main');
    }
}