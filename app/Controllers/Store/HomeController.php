<?php
namespace App\Controllers\Store;
use Core\{Request, View, Database, Session};

class HomeController
{
    /** Items per page for product listings */
    private const PER_PAGE = 12;

    public function index(Request $request, array $params = []): void
    {
        // Handle currency switch
        $switchCurrency = $request->query('currency');
        if ($switchCurrency) {
            $allowed = array_keys(\App\Services\CurrencyService::currencies());
            if (in_array(strtoupper($switchCurrency), $allowed)) {
                Session::set('wk_display_currency', strtoupper($switchCurrency));
            }
            \Core\Response::redirect(\Core\View::url(''));
            return;
        }

        $featured = Database::fetchAll(
            "SELECT p.*, c.name AS category_name,
                    (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image,
                    (SELECT COUNT(*) FROM wk_variant_combos WHERE product_id=p.id AND is_active=1) AS variant_count
             FROM wk_products p
             LEFT JOIN wk_categories c ON c.id=p.category_id
             WHERE p.is_active=1
             ORDER BY p.is_featured DESC, p.created_at DESC LIMIT 12"
        );
        $siteName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store';
        $tagline = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_tagline'") ?: '';
        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

        $seoMeta = \App\Services\SeoService::renderMeta([]);

        // Get categories for filter nav (with product count and first product image)
        $categories = Database::fetchAll(
            "SELECT c.id, c.name, c.slug, c.description,
                    (SELECT COUNT(*) FROM wk_products p
                     JOIN wk_categories sc ON sc.id=p.category_id AND sc.is_active=1
                     WHERE (sc.id=c.id OR sc.parent_id=c.id) AND p.is_active=1) AS product_count,
                    (SELECT pi.image_path FROM wk_products p2
                     JOIN wk_categories sc2 ON sc2.id=p2.category_id AND sc2.is_active=1
                     JOIN wk_product_images pi ON pi.product_id=p2.id AND pi.is_primary=1
                     WHERE (sc2.id=c.id OR sc2.parent_id=c.id) AND p2.is_active=1 LIMIT 1) AS cover_image
             FROM wk_categories c WHERE c.is_active=1 AND c.parent_id IS NULL
             ORDER BY c.sort_order, c.name"
        );

        // Get on-sale products for sale section
        $saleProducts = Database::fetchAll(
            "SELECT p.*, c.name AS category_name,
                    (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image,
                    (SELECT COUNT(*) FROM wk_variant_combos WHERE product_id=p.id AND is_active=1) AS variant_count,
                    ROUND((1 - p.sale_price / p.price) * 100) AS discount_pct
             FROM wk_products p
             LEFT JOIN wk_categories c ON c.id=p.category_id
             WHERE p.is_active=1 AND p.sale_price IS NOT NULL AND p.sale_price > 0 AND p.sale_price < p.price AND p.price > 0 AND p.stock_quantity > 0
             ORDER BY discount_pct DESC LIMIT 8"
        );

        // Hero settings
        $heroTitle = Database::setting('general', 'hero_title') ?? $siteName;
        $heroSubtitle = Database::setting('general', 'hero_subtitle') ?? $tagline;
        $heroCta = Database::setting('general', 'hero_cta') ?? 'Shop Now';

        // Homepage layout: v1 (classic) or v2 (modern with categories/sale)
        $homeLayout = Database::setting('general', 'homepage_style') ?? 'v2';
        $viewName = in_array($homeLayout, ['v1', 'v2']) ? 'store/home-' . $homeLayout : 'store/home-v2';

        View::render($viewName, [
            'products'     => $featured,
            'siteName'     => $siteName,
            'tagline'      => $tagline,
            'currency'     => $currency,
            'seoMeta'      => $seoMeta,
            'categories'   => $categories,
            'saleProducts' => $saleProducts,
            'heroTitle'    => $heroTitle,
            'heroSubtitle' => $heroSubtitle,
            'heroCta'      => $heroCta,
            'isHomepage'   => true,
        ], 'store/layouts/main');
    }

    /**
     * /shop — Browse all products with pagination, sorting, and category filter
     */
    public function shop(Request $request, array $params = []): void
    {
        $page = max(1, (int)($request->query('page') ?? 1));
        $sort = $request->query('sort') ?? 'newest';
        $categorySlug = $request->query('category') ?? '';
        $perPage = self::PER_PAGE;
        $offset = ($page - 1) * $perPage;

        // Build query
        $where = ['p.is_active=1'];
        $queryParams = [];

        // Category filter
        $currentCategory = null;
        if ($categorySlug) {
            $currentCategory = Database::fetch("SELECT id, name, slug, description FROM wk_categories WHERE slug=? AND is_active=1", [$categorySlug]);
            if ($currentCategory) {
                // Include subcategories
                $childIds = Database::fetchAll("SELECT id FROM wk_categories WHERE parent_id=? AND is_active=1", [$currentCategory['id']]);
                $catIds = array_merge([$currentCategory['id']], array_column($childIds, 'id'));
                $placeholders = implode(',', array_fill(0, count($catIds), '?'));
                $where[] = "p.category_id IN ({$placeholders})";
                $queryParams = array_merge($queryParams, $catIds);
            }
        }

        $whereClause = implode(' AND ', $where);

        // Sort
        $orderBy = match ($sort) {
            'price_low'  => 'COALESCE(p.sale_price, p.price) ASC',
            'price_high' => 'COALESCE(p.sale_price, p.price) DESC',
            'name_az'    => 'p.name ASC',
            'name_za'    => 'p.name DESC',
            'oldest'     => 'p.created_at ASC',
            default      => 'p.created_at DESC', // newest
        };

        // Count total
        $totalProducts = (int)Database::fetchValue(
            "SELECT COUNT(*) FROM wk_products p WHERE {$whereClause}",
            $queryParams
        );
        $totalPages = max(1, (int)ceil($totalProducts / $perPage));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $perPage;

        // Fetch products
        $products = Database::fetchAll(
            "SELECT p.*, c.name AS category_name,
                    (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image,
                    (SELECT COUNT(*) FROM wk_variant_combos WHERE product_id=p.id AND is_active=1) AS variant_count
             FROM wk_products p
             LEFT JOIN wk_categories c ON c.id=p.category_id
             WHERE {$whereClause}
             ORDER BY {$orderBy}
             LIMIT {$perPage} OFFSET {$offset}",
            $queryParams
        );

        // Get all categories for filter
        $categories = Database::fetchAll(
            "SELECT c.id, c.name, c.slug, COUNT(p.id) AS product_count
             FROM wk_categories c
             LEFT JOIN wk_products p ON p.category_id=c.id AND p.is_active=1
             WHERE c.is_active=1 AND c.parent_id IS NULL
             GROUP BY c.id
             ORDER BY c.sort_order, c.name"
        );

        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

        $pageTitle = $currentCategory ? $currentCategory['name'] : 'Shop All Products';
        $seoMeta = \App\Services\SeoService::renderMeta([
            'name'             => $pageTitle,
            'meta_description' => $currentCategory['description'] ?? "Browse our collection of products",
        ]);

        View::render('store/shop', [
            'products'        => $products,
            'categories'      => $categories,
            'currentCategory' => $currentCategory,
            'currency'        => $currency,
            'sort'            => $sort,
            'page'            => $page,
            'totalPages'      => $totalPages,
            'totalProducts'   => $totalProducts,
            'pageTitle'       => $pageTitle,
            'seoMeta'         => $seoMeta,
            'isHomepage'      => false,
        ], 'store/layouts/main');
    }
}