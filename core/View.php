<?php
namespace Core;

/**
 * WHISKER — Template Renderer
 *
 * Usage:
 *   View::render('admin/dashboard', ['stats' => $stats], 'admin/layouts/main');
 *   View::render('store/home', ['products' => $products], 'store/layouts/main');
 *   View::partial('store/partials/product-card', ['product' => $p]);
 */
class View
{
    /**
     * Render a view, optionally wrapped in a layout.
     *
     * @param string      $view   Path relative to views/ (e.g. 'admin/dashboard')
     * @param array       $data   Variables to extract into the view
     * @param string|null $layout Layout path relative to views/ (e.g. 'admin/layouts/main')
     */
    public static function render(string $view, array $data = [], ?string $layout = null): void
    {
        $data['_flashes'] = Session::getFlashes();
        $data['_csrf']    = Session::csrfToken();

        // Render the view content
        $content = self::capture($view, $data);

        if ($layout) {
            // Wrap content inside layout
            $data['_content'] = $content;
            echo self::capture($layout, $data);
        } else {
            echo $content;
        }
    }

    /**
     * Render a partial template (no layout).
     */
    public static function partial(string $view, array $data = []): string
    {
        return self::capture($view, $data);
    }

    /**
     * Capture a view's output into a string.
     */
    private static function capture(string $view, array $data): string
    {
        $file = WK_ROOT . '/views/' . $view . '.php';

        if (!file_exists($file)) {
            throw new \RuntimeException("View not found: {$view} ({$file})");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return ob_get_clean();
    }

    /**
     * Helper: generate a URL path.
     */
    public static function url(string $path = ''): string
    {
        $base = defined('WK_BASE_URL') ? WK_BASE_URL : '';
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Helper: asset URL, with a version query for cache-busting.
     *
     * Appending ?v=<version> changes the URL on each release so returning
     * visitors fetch updated CSS/JS instead of stale cached copies. (Apache
     * ignores the query for static files, so the same asset is served.)
     */
    public static function asset(string $path): string
    {
        $rel = ltrim($path, '/');
        $url = self::url('assets/' . $rel);

        // Key the cache on the file's own modification time so an edited
        // stylesheet or script reaches browsers immediately, including
        // between releases when the version string has not moved.
        $file = dirname(__DIR__) . '/assets/' . $rel;
        $stamp = is_file($file) ? (string) filemtime($file) : '';
        if ($stamp === '') {
            $stamp = defined('WK_VERSION') ? (string) WK_VERSION : '';
        }
        return $stamp !== '' ? $url . '?v=' . rawurlencode($stamp) : $url;
    }

    /**
     * Helper: escape output.
     */
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Build a URL slug from a title.
     *
     * Lowercases before filtering — the character class only permits a-z, so
     * filtering first would strip every capital letter.
     */
    public static function slug(string $value): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
    }

    /**
     * Helper: format price.
     *
     * When no symbol is passed, the store's configured currency symbol is
     * used. Views must not hardcode a fallback symbol so non-default
     * currencies display correctly on every call site.
     */
    public static function price(float $amount, ?string $symbol = null): string
    {
        if ($symbol === null) {
            try {
                $symbol = Database::setting('general', 'currency_symbol') ?: '₹';
            } catch (\Throwable $e) {
                $symbol = '₹';
            }
        }
        return $symbol . number_format($amount, 2);
    }

    /**
     * Helper: validate a URL is safe to embed in href/src attributes.
     *
     * Returns an HTML-escaped, attribute-safe URL string. If the input has a
     * dangerous scheme (javascript:, vbscript:, data:, etc.) or is
     * protocol-relative, returns '#' so the link is inert. Use this whenever
     * an admin-controlled URL (tracking_url, logo_url, og_image,
     * external links from order notes) is rendered into HTML.
     */
    public static function safeUrl(string $url, bool $allowDataImage = false): string
    {
        $safe = self::isSafeUrl($url, $allowDataImage) ? $url : '#';
        return htmlspecialchars($safe, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Returns true if the URL is safe to render in href/src.
     * Accepts: http(s), mailto:, tel:, rooted-relative /paths, bare slugs.
     * Rejects: javascript:, vbscript:, file:, protocol-relative //...
     * Special case: data:image/* allowed only when $allowDataImage is true
     * (caller must pass true for <img src> contexts, false for href).
     */
    public static function isSafeUrl(string $url, bool $allowDataImage = false): bool
    {
        $u = trim($url);
        if ($u === '') return false;
        if (str_starts_with($u, '//')) return false;                     // protocol-relative — inert
        if (str_starts_with($u, '/')) return true;                       // rooted relative
        if (str_starts_with($u, '#')) return true;                       // fragment-only
        if (preg_match('#^([a-z][a-z0-9+.\-]*):#i', $u, $m)) {
            $scheme = strtolower($m[1]);
            if (in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) return true;
            if ($allowDataImage && $scheme === 'data' && preg_match('#^data:image/(png|jpe?g|gif|webp|svg\+xml);#i', $u)) {
                // Note: data:image/svg+xml CAN carry XSS. Most webmail clients
                // strip these; we allow because legitimate inline images are
                // common in email templates.
                return true;
            }
            return false;
        }
        return true;                                                     // bare slug — relative
    }
}
