<?php
namespace Core;

/**
 * WHISKER — Plugin Manager
 *
 * Scans the plugins/ directory for installed plugins.
 * Each plugin must have a plugin.json manifest.
 *
 * Plugin manifest example (plugin.json):
 * {
 *   "name": "Razorpay",
 *   "code": "razorpay",
 *   "version": "1.0.0",
 *   "author": "Whisker",
 *   "type": "payment_gateway",
 *   "class": "RazorpayGateway",
 *   "description": "Accept payments via Razorpay"
 * }
 *
 * ── SECURITY MODEL (L17) ──────────────────────────────────────────────
 * Plugins are TRUSTED CODE. The `class` field in plugin.json is loaded
 * with require_once and instantiated, so anyone who can drop a file
 * into plugins/ effectively has full app privileges. This is the same
 * trust model as WordPress's wp-content/plugins/ directory and is
 * acceptable while:
 *   - Plugin installation requires FTP/SSH access (not a web upload UI)
 *   - The bundled plugins ship from the same source as core
 *
 * If a "Install plugin from URL" or "Plugin marketplace" feature is
 * ever added, this module MUST be hardened first:
 *   - Verify manifest signatures (e.g. Ed25519 over the canonicalized JSON)
 *   - Refuse unsigned plugins by default
 *   - Sandbox plugin execution (process isolation or at least namespace
 *     restrictions / capability checks)
 * Without those, a remote-install feature becomes a one-click RCE
 * vector. Flag any PR touching this file that involves a remote source.
 */
class PluginManager
{
    private static array $plugins  = [];
    private static array $gateways = [];
    private static bool  $loaded   = false;

    /**
     * Scan the plugins/ directory and load all plugin manifests.
     */
    public static function discover(): void
    {
        if (self::$loaded) return;

        $pluginsDir = WK_ROOT . '/plugins';
        if (!is_dir($pluginsDir)) return;

        $dirs = glob($pluginsDir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $dir) {
            $manifest = $dir . '/plugin.json';
            if (!file_exists($manifest)) continue;

            $data = json_decode(file_get_contents($manifest), true);
            if (!$data || empty($data['code'])) continue;

            $data['_dir']  = $dir;
            $data['_path'] = basename($dir);
            self::$plugins[$data['code']] = $data;

            // Auto-categorize by type
            if (($data['type'] ?? '') === 'payment_gateway') {
                self::$gateways[$data['code']] = $data;
            }
        }

        self::$loaded = true;
    }

    /**
     * Get all discovered plugins.
     */
    public static function all(): array
    {
        self::discover();
        return self::$plugins;
    }

    /**
     * Get all payment gateway plugins.
     */
    public static function gateways(): array
    {
        self::discover();
        return self::$gateways;
    }

    /**
     * Get a specific plugin manifest.
     */
    public static function get(string $code): ?array
    {
        self::discover();
        return self::$plugins[$code] ?? null;
    }

    /**
     * Instantiate a gateway class from a plugin.
     * Requires the plugin file and returns a new instance.
     */
    public static function loadGateway(string $code): ?object
    {
        self::discover();
        $plugin = self::$gateways[$code] ?? null;
        if (!$plugin) return null;

        $classFile = $plugin['_dir'] . '/' . $plugin['class'] . '.php';
        if (!file_exists($classFile)) return null;

        require_once $classFile;
        $className = $plugin['class'];

        return new $className($code);
    }

    /**
     * Register plugin routes into the router.
     */
    public static function registerRoutes(Router $router): void
    {
        self::discover();
        foreach (self::$plugins as $code => $plugin) {
            $routesFile = $plugin['_dir'] . '/routes.php';
            if (file_exists($routesFile)) {
                // Plugin routes get a /webhook/{code} prefix
                $router->group(['prefix' => '/webhook/' . $code], function ($r) use ($routesFile) {
                    require $routesFile;
                });
            }
        }
    }

    /**
     * Check if a plugin is installed.
     */
    public static function exists(string $code): bool
    {
        self::discover();
        return isset(self::$plugins[$code]);
    }
}
