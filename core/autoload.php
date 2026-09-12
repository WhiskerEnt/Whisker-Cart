<?php
/**
 * WHISKER — Custom Autoloader
 * Maps namespaces to directories. Zero dependencies.
 *
 * Namespace mapping (the same map composer.json declares as PSR-4):
 *   App\Controllers\Admin\*  → app/Controllers/Admin/*.php
 *   App\Middleware\*          → app/Middleware/*.php
 *   App\Services\*            → app/Services/*.php
 *   Core\*                    → core/*.php
 */

spl_autoload_register(function (string $class) {

    // Namespace prefix → directory mapping
    // WK_ROOT is defined in index.php before autoloader is loaded
    $root = defined('WK_ROOT') ? WK_ROOT : dirname(__DIR__);

    $map = [
        'App\\'  => $root . '/app/',
        'Core\\' => $root . '/core/',
    ];

    foreach ($map as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($class, $prefix, $len) === 0) {
            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});
