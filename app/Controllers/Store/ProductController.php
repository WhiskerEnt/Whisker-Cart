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

        $images = Database::fetchAll("SELECT * FROM wk_product_images WHERE product_id=? AND (alt_text='' OR alt_text IS NULL) ORDER BY is_primary DESC, sort_order", [$product['id']]);
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

        $products = Database::fetchAll(
            "SELECT p.*, (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
             FROM wk_products p WHERE p.category_id=? AND p.is_active=1 ORDER BY p.created_at DESC",
            [$cat['id']]
        );

        $seoMeta = \App\Services\SeoService::renderMeta([
            'name'             => $cat['name'],
            'meta_title'       => $cat['meta_title'] ?? null,
            'meta_description' => $cat['meta_description'] ?? null,
            'meta_keywords'    => $cat['meta_keywords'] ?? null,
            'description'      => $cat['description'] ?? '',
        ]);

        View::render('store/home', ['products'=>$products, 'siteName'=>$cat['name'], 'tagline'=>$cat['description']??'', 'currency'=>'₹', 'seoMeta'=>$seoMeta], 'store/layouts/main');
    }

    public function search(Request $request, array $params = []): void
    {
        $q = $request->clean('q') ?? '';
        $products = [];
        if (strlen($q) >= 2) {
            // Escape LIKE wildcards to prevent wildcard injection
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $q);
            $products = Database::fetchAll(
                "SELECT p.*, (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
                 FROM wk_products p WHERE p.is_active=1 AND (p.name LIKE ? OR p.description LIKE ?) ORDER BY p.name LIMIT 20",
                ["%{$escaped}%", "%{$escaped}%"]
            );
        }
        View::render('store/home', ['products'=>$products, 'siteName'=>"Search: {$q}", 'tagline'=>count($products).' results', 'currency'=>'₹'], 'store/layouts/main');
    }
}