<?php
namespace App\Services;

use Core\Database;

/**
 * WHISKER — SEO Service
 * Meta tag generation, auto-generation, Open Graph, Twitter Cards,
 * JSON-LD schema, sitemap.xml, and robots.txt generation.
 */
class SeoService
{
    private static ?array $globalSettings = null;

    // ── Settings Cache ────────────────────────────
    public static function getSettings(): array
    {
        if (self::$globalSettings === null) {
            self::$globalSettings = [];
            try {
                $rows = Database::fetchAll("SELECT setting_key, setting_value FROM wk_settings WHERE setting_group = 'seo'");
                foreach ($rows as $row) {
                    self::$globalSettings[$row['setting_key']] = $row['setting_value'];
                }
            } catch (\Exception $e) {}
        }
        return self::$globalSettings;
    }

    public static function getSetting(string $key, ?string $default = null): ?string
    {
        return self::getSettings()[$key] ?? $default;
    }

    // ── Title Builder ─────────────────────────────
    public static function buildTitle(?string $pageTitle = null): string
    {
        $siteName = self::getSetting('site_meta_title')
            ?: (Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store');

        if (empty($pageTitle)) return $siteName;

        $format = self::getSetting('title_format', '{page} {sep} {site}');
        $sep    = self::getSetting('title_separator', ' — ');

        return str_replace(['{page}', '{sep}', '{site}'], [$pageTitle, $sep, $siteName], $format);
    }

    // ── Auto-Generate Meta Description ────────────
    public static function autoDescription(string $content, int $maxLength = 155): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($content)));
        if (mb_strlen($text) <= $maxLength) return $text;

        $truncated = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($truncated, ' ');
        if ($lastSpace !== false && $lastSpace > ($maxLength * 0.7)) {
            $truncated = mb_substr($truncated, 0, $lastSpace);
        }
        return rtrim($truncated, '.,;:!? ') . '...';
    }

    // ── Auto-Generate Keywords ────────────────────
    public static function autoKeywords(string $name, ?string $categoryName = null, ?string $description = null, int $max = 10): string
    {
        $parts = [$name];
        if ($categoryName) $parts[] = $categoryName;

        if ($description) {
            $text = preg_replace('/[^a-zA-Z0-9\s]/', '', strtolower(strip_tags($description)));
            $words = array_filter(explode(' ', $text), fn($w) => mb_strlen($w) > 3 && !in_array($w, self::stopWords()));
            $freq = array_count_values($words);
            arsort($freq);
            $parts = array_merge($parts, array_slice(array_keys($freq), 0, $max - count($parts)));
        }
        return implode(', ', array_unique(array_map('trim', $parts)));
    }

    // ── Build & Render Meta Tags ──────────────────
    public static function renderMeta(array $opts = []): string
    {
        $auto = self::getSetting('auto_generate_meta', '1') === '1';

        // Title
        $title = $opts['meta_title'] ?? null;
        if (empty($title) && $auto && !empty($opts['name'])) $title = $opts['name'];
        $fullTitle = self::buildTitle($title);

        // Description
        $desc = $opts['meta_description'] ?? null;
        if (empty($desc) && $auto) {
            if (!empty($opts['short_description'])) $desc = self::autoDescription($opts['short_description']);
            elseif (!empty($opts['description'])) $desc = self::autoDescription($opts['description']);
        }
        $desc = $desc ?: self::getSetting('site_meta_description', '');

        // Keywords
        $kw = $opts['meta_keywords'] ?? null;
        if (empty($kw) && $auto && !empty($opts['name'])) {
            $kw = self::autoKeywords($opts['name'], $opts['category_name'] ?? null, $opts['description'] ?? null);
        }
        $kw = $kw ?: self::getSetting('site_meta_keywords', '');

        // Image
        $image = $opts['og_image'] ?? $opts['image'] ?? self::getSetting('og_image', '');
        if ($image && !str_starts_with($image, 'http')) {
            $base = self::baseUrl();
            if (str_starts_with($image, 'storage/')) {
                $image = rtrim($base, '/') . '/' . $image;
            } else {
                $image = rtrim($base, '/') . '/storage/uploads/products/' . ltrim($image, '/');
            }
        }

        // Canonical
        $canonical = $opts['canonical'] ?? self::currentUrl();

        // Robots
        $ri = self::getSetting('robots_index', '1') === '1';
        $rf = self::getSetting('robots_follow', '1') === '1';
        $robots = ($ri ? 'index' : 'noindex') . ', ' . ($rf ? 'follow' : 'nofollow');

        $ogType = $opts['type'] ?? 'website';
        $ogTitle = $title ?: $fullTitle;

        $h = fn($v) => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');

        $html = [];
        $html[] = '<title>' . $h($fullTitle) . '</title>';
        if ($desc) $html[] = '<meta name="description" content="' . $h($desc) . '">';
        if ($kw) $html[] = '<meta name="keywords" content="' . $h($kw) . '">';
        $html[] = '<meta name="robots" content="' . $robots . '">';
        if ($canonical) $html[] = '<link rel="canonical" href="' . $h($canonical) . '">';

        // Verification
        $gv = self::getSetting('google_verification');
        $bv = self::getSetting('bing_verification');
        if ($gv) $html[] = '<meta name="google-site-verification" content="' . $h($gv) . '">';
        if ($bv) $html[] = '<meta name="msvalidate.01" content="' . $h($bv) . '">';

        // Open Graph
        $html[] = '<meta property="og:type" content="' . $ogType . '">';
        $html[] = '<meta property="og:title" content="' . $h($ogTitle) . '">';
        if ($desc) $html[] = '<meta property="og:description" content="' . $h($desc) . '">';
        if ($canonical) $html[] = '<meta property="og:url" content="' . $h($canonical) . '">';
        if ($image) $html[] = '<meta property="og:image" content="' . $h($image) . '">';

        // Twitter Card
        $html[] = '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . '">';
        $tw = self::getSetting('twitter_handle');
        if ($tw) $html[] = '<meta name="twitter:site" content="@' . $h(ltrim($tw, '@')) . '">';
        $html[] = '<meta name="twitter:title" content="' . $h($ogTitle) . '">';
        if ($desc) $html[] = '<meta name="twitter:description" content="' . $h($desc) . '">';
        if ($image) $html[] = '<meta name="twitter:image" content="' . $h($image) . '">';

        return implode("\n    ", $html);
    }

    // ── Product JSON-LD Schema ────────────────────
    public static function productSchema(array $product, ?string $baseUrl = null): string
    {
        if (self::getSetting('schema_org_enabled', '1') !== '1') return '';

        $baseUrl = $baseUrl ?: (self::baseUrl());
        $price = $product['sale_price'] ?? $product['price'] ?? 0;
        $inStock = ($product['stock_quantity'] ?? 0) > 0;

        $schema = [
            '@context' => 'https://schema.org', '@type' => 'Product',
            'name' => $product['name'],
            'description' => strip_tags($product['description'] ?? $product['short_description'] ?? ''),
            'sku' => $product['sku'] ?? '',
            'url' => rtrim($baseUrl, '/') . '/product/' . ($product['slug'] ?? ''),
            'offers' => [
                '@type' => 'Offer',
                'price' => number_format((float)$price, 2, '.', ''),
                'priceCurrency' => $product['currency'] ?? 'INR',
                'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
            ],
        ];

        if (!empty($product['primary_image'])) {
            $schema['image'] = rtrim($baseUrl, '/') . '/storage/uploads/products/' . ltrim($product['primary_image'], '/');
        }

        // JSON_HEX_TAG/AMP/APOS/QUOT prevent the JSON-LD payload from being
        // able to break out of the <script> block via "</script>", inline
        // event handlers, or attribute-context injection — relevant because
        // product names and descriptions are admin-controlled but free-text.
        return '<script type="application/ld+json">'
            . json_encode(
                $schema,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
                    | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            )
            . '</script>';
    }

    // ── Sitemap Generator ─────────────────────────
    public static function generateSitemap(?string $baseUrl = null): string
    {
        $baseUrl = rtrim($baseUrl ?: (self::baseUrl()), '/');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // Homepage
        $xml .= self::sitemapUrl($baseUrl . '/', 'daily', '1.0');

        // Categories
        foreach (Database::fetchAll("SELECT slug, updated_at FROM wk_categories WHERE is_active=1 ORDER BY sort_order") as $c) {
            $xml .= self::sitemapUrl($baseUrl . '/category/' . $c['slug'], 'weekly', '0.8', $c['updated_at']);
        }

        // Products
        foreach (Database::fetchAll("SELECT slug, updated_at FROM wk_products WHERE is_active=1 ORDER BY created_at DESC") as $p) {
            $xml .= self::sitemapUrl($baseUrl . '/product/' . $p['slug'], 'weekly', '0.7', $p['updated_at']);
        }

        // Pages
        try {
            foreach (Database::fetchAll("SELECT slug, updated_at FROM wk_pages WHERE is_active=1") as $pg) {
                $xml .= self::sitemapUrl($baseUrl . '/page/' . $pg['slug'], 'monthly', '0.5', $pg['updated_at']);
            }
        } catch (\Exception $e) {}

        return $xml . '</urlset>';
    }

    public static function writeSitemap(?string $rootPath = null): bool
    {
        $rootPath = $rootPath ?? (defined('WK_ROOT') ? WK_ROOT : dirname(__DIR__, 2));
        return (bool) file_put_contents($rootPath . '/sitemap.xml', self::generateSitemap());
    }

    // ── robots.txt Generator ──────────────────────
    public static function generateRobotsTxt(?string $baseUrl = null): string
    {
        $baseUrl = rtrim($baseUrl ?: (self::baseUrl()), '/');

        $txt = "User-agent: *\nAllow: /\n\nDisallow: /admin/\nDisallow: /api/\nDisallow: /config/\nDisallow: /includes/\nDisallow: /install/\nDisallow: /cart\nDisallow: /checkout\nDisallow: /account/\n";

        if (self::getSetting('sitemap_enabled', '1') === '1') {
            $txt .= "\nSitemap: {$baseUrl}/sitemap.xml\n";
        }
        return $txt;
    }

    // ── Base URL Helper ─────────────────────────
    private static function baseUrl(): string
    {
        // Use the app constant set in index.php from config.php
        if (defined('WK_BASE_URL') && WK_BASE_URL) {
            return rtrim(WK_BASE_URL, '/');
        }
        // Fallback: try database
        $url = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='base_url'");
        if ($url) return rtrim($url, '/');
        // Last resort: detect from server
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    private static function sitemapUrl(string $loc, string $freq, string $pri, ?string $date = null): string
    {
        $xml = "  <url>\n    <loc>" . htmlspecialchars($loc) . "</loc>\n";
        if ($date) $xml .= "    <lastmod>" . date('Y-m-d', strtotime($date)) . "</lastmod>\n";
        return $xml . "    <changefreq>{$freq}</changefreq>\n    <priority>{$pri}</priority>\n  </url>\n";
    }

    private static function currentUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    }

    private static function stopWords(): array
    {
        return ['the','and','for','are','but','not','you','all','can','had','her','was','one','our','out','has','have','been','from','this','that','with','they','will','each','make','like','just','over','such','take','than','them','very','some','into','most','only','come','made','also','more','your','what','when','which','their','said','about','would','there','could','other','these','those','does','being','here','where','after','should','product','high','quality','best','great','good','available','perfect','ideal'];
    }
}