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
        $productSchema = \App\Services\SeoService::productSchema(array_merge($product, ['primary_image' => $primaryImage]));

        View::render('store/product', [
            'product'       => $product,
            'images'        => $images,
            'related'       => $related,
            'currency'      => $currency,
            'variants'      => $variants,
            'seoMeta'       => $seoMeta,
            'productSchema' => $productSchema,
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

    public function search(Request $request, array $params = []): void
    {
        $q = $request->clean('q') ?? '';
        $perPage = 12;
        $page = max(1, (int)($request->query('page') ?? 1));
        $products = [];
        $totalProducts = 0;
        $totalPages = 1;

        if (strlen($q) >= 2) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $q);
            $searchParam = "%{$escaped}%";

            $totalProducts = (int)Database::fetchValue(
                "SELECT COUNT(*) FROM wk_products p WHERE p.is_active=1 AND (p.name LIKE ? OR p.description LIKE ?)",
                [$searchParam, $searchParam]
            );
            $totalPages = max(1, (int)ceil($totalProducts / $perPage));
            $page = min($page, $totalPages);
            $offset = ($page - 1) * $perPage;

            $products = Database::fetchAll(
                "SELECT p.*, (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image,
                        (SELECT COUNT(*) FROM wk_variant_combos WHERE product_id=p.id AND is_active=1) AS variant_count
                 FROM wk_products p WHERE p.is_active=1 AND (p.name LIKE ? OR p.description LIKE ?)
                 ORDER BY p.name LIMIT {$perPage} OFFSET {$offset}",
                [$searchParam, $searchParam]
            );
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