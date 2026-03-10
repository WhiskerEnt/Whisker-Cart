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
define('WK_VERSION',   $config['version'] ?? '1.0.1');

date_default_timezone_set($config['timezone'] ?? 'UTC');

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

// Register middleware
$router->registerMiddleware('auth',  \App\Middleware\AuthMiddleware::class);
$router->registerMiddleware('csrf',  \App\Middleware\CsrfMiddleware::class);
$router->registerMiddleware('guest', \App\Middleware\GuestMiddleware::class);

// ── Load routes ──────────────────────────────────
require WK_ROOT . '/config/routes.php';

// ── Dispatch ─────────────────────────────────────
$router->dispatch($request);
