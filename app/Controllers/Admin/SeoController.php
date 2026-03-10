<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Session, Response};
use App\Services\SeoService;

class SeoController
{
    public function index(Request $request, array $params = []): void
    {
        $settings = SeoService::getSettings();

        $totalProducts = (int) Database::fetchValue("SELECT COUNT(*) FROM wk_products WHERE is_active = 1");
        $customMetaProducts = 0;
        try { $customMetaProducts = (int) Database::fetchValue("SELECT COUNT(*) FROM wk_products WHERE is_active = 1 AND meta_title IS NOT NULL AND meta_title != ''"); } catch (\Exception $e) {}

        $totalCategories = (int) Database::fetchValue("SELECT COUNT(*) FROM wk_categories WHERE is_active = 1");
        $customMetaCategories = 0;
        try { $customMetaCategories = (int) Database::fetchValue("SELECT COUNT(*) FROM wk_categories WHERE is_active = 1 AND meta_title IS NOT NULL AND meta_title != ''"); } catch (\Exception $e) {}

        $sitemapExists = file_exists(WK_ROOT . '/sitemap.xml');
        $sitemapDate   = $sitemapExists ? date('M j, Y g:i A', filemtime(WK_ROOT . '/sitemap.xml')) : null;
        $robotsExists  = file_exists(WK_ROOT . '/robots.txt');

        View::render('admin/seo/index', [
            'pageTitle'            => 'SEO Settings',
            'settings'             => $settings,
            'totalProducts'        => $totalProducts,
            'customMetaProducts'   => $customMetaProducts,
            'totalCategories'      => $totalCategories,
            'customMetaCategories' => $customMetaCategories,
            'sitemapExists'        => $sitemapExists,
            'sitemapDate'          => $sitemapDate,
            'robotsExists'         => $robotsExists,
        ], 'admin/layouts/main');
    }

    public function update(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/seo'));
            return;
        }

        $fields = ['site_meta_title','site_meta_description','site_meta_keywords','og_image','title_separator','title_format','twitter_handle','google_verification','bing_verification','robots_index','robots_follow','auto_generate_meta','sitemap_enabled','canonical_url','schema_org_enabled'];
        $checkboxes = ['robots_index','robots_follow','auto_generate_meta','sitemap_enabled','schema_org_enabled'];

        foreach ($fields as $f) {
            $val = in_array($f, $checkboxes) ? ($request->input($f) ? '1' : '0') : trim($request->input($f) ?? '');
            Database::query("INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('seo', ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", [$f, $val]);
        }

        Session::flash('success', 'SEO settings saved.');
        Response::redirect(View::url('admin/seo'));
    }

    public function generateSitemap(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('admin/seo')); return;
        }
        $ok = SeoService::writeSitemap();
        Session::flash($ok ? 'success' : 'error', $ok ? 'Sitemap generated at /sitemap.xml' : 'Failed to write sitemap.xml — check permissions.');
        Response::redirect(View::url('admin/seo'));
    }

    public function generateRobots(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('admin/seo')); return;
        }
        $ok = (bool) file_put_contents(WK_ROOT . '/robots.txt', SeoService::generateRobotsTxt());
        Session::flash($ok ? 'success' : 'error', $ok ? 'robots.txt generated.' : 'Failed to write robots.txt — check permissions.');
        Response::redirect(View::url('admin/seo'));
    }
}
