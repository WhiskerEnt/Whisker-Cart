<?php
/**
 * WHISKER — Front Controller
 * ALL requests enter here via .htaccess rewrite.
 */

// ── Define root ──────────────────────────────────
define('WK_ROOT', __DIR__);

// ── Check if installed ───────────────────────────
if (!file_exists(WK_ROOT . '/config/config.php')) {
    // Not installed yet — hand off to installer
    require WK_ROOT . '/install/index.php';
    exit;
}

// ── Load autoloader ──────────────────────────────
require_once WK_ROOT . '/core/autoload.php';

// ── Load configuration ───────────────────────────
$config = require WK_ROOT . '/config/config.php';

define('WK_BASE_URL',  $config['base_url']);
define('WK_BASE_PATH', $config['base_path'] ?? '/');
define('WK_DEBUG',     $config['debug'] ?? false);

// WK_VERSION is read from app/version.php — the single source of truth
// shipped with each release. Falls back to $config['version'] for old
// installs that don't have the file yet (the first update will create it).
// Final fallback is the hardcoded current version for the truly worst case.
$versionFile = WK_ROOT . '/app/version.php';
define('WK_VERSION', is_file($versionFile)
    ? (string) require $versionFile
    : (string) ($config['version'] ?? '1.3.0'));

// Timezone: the admin-editable DB setting wins; config.php holds the
// install-time value as fallback. Before this, Settings → Timezone saved
// happily and changed nothing (every date() kept the install-time zone).
$wkTimezone = $config['timezone'] ?? 'UTC';
try {
    $wkDbTimezone = \Core\Database::setting('general', 'timezone');
    if ($wkDbTimezone && in_array($wkDbTimezone, timezone_identifiers_list(), true)) {
        $wkTimezone = $wkDbTimezone;
    }
} catch (\Throwable $e) {
    // DB not reachable yet (e.g. mid-install) — config value stands.
}
date_default_timezone_set($wkTimezone);

if (WK_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ── Start session ────────────────────────────────
\Core\Session::start();

// ── Initialize router ────────────────────────────
$router  = new \Core\Router();
$request = new \Core\Request();

// ── Display-currency switch (global) ─────────────
// The storefront switcher appends ?currency=XXX to the CURRENT page. Handle
// it here for every route so switching currency keeps the visitor on the
// page they're on (product, shop, category, …) instead of bouncing them to
// the homepage, then redirect to the same URL with the param stripped.
$currencySwitch = $request->query('currency');
if ($currencySwitch !== null && $request->isGet() && !$request->isAjax()) {
    $allowed = array_keys(\App\Services\CurrencyService::currencies());
    if (in_array(strtoupper((string)$currencySwitch), $allowed, true)) {
        \Core\Session::set('wk_display_currency', strtoupper((string)$currencySwitch));
    }
    $rest = $_GET;
    unset($rest['currency']);
    $target = \Core\View::url(ltrim($request->path(), '/'));
    if (!empty($rest)) $target .= '?' . http_build_query($rest);
    \Core\Response::redirect($target);
}

// Register middleware
$router->registerMiddleware('auth',  \App\Middleware\AuthMiddleware::class);
$router->registerMiddleware('csrf',  \App\Middleware\CsrfMiddleware::class);
$router->registerMiddleware('guest', \App\Middleware\GuestMiddleware::class);

// ── Load routes ──────────────────────────────────
require WK_ROOT . '/config/routes.php';

// ── Dispatch ─────────────────────────────────────
$router->dispatch($request);
