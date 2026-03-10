<?php
namespace App\Controllers\Store;
use Core\{Request, View, Database};

class HomeController
{
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

        View::render('store/home', [
            'products' => $featured,
            'siteName' => $siteName,
            'tagline'  => $tagline,
            'currency' => $currency,
            'seoMeta'  => $seoMeta,
        ], 'store/layouts/main');
    }
}