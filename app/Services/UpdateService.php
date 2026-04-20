<?php
namespace App\Services;

use Core\Database;

/**
 * WHISKER — Update Service
 *
 * Checks api.lohit.me for new versions.
 * Owner decides whether to update — never forced.
 *
 * Flow:
 * 1. Dashboard loads → checks cache → if stale, pings update API
 * 2. If new version available → shows notification banner
 * 3. Owner clicks "Update Now" → downloads ZIP → extracts → overwrites files
 * 4. Owner can dismiss or disable update checks entirely
 */
class UpdateService
{
    private const UPDATE_API = 'https://api.lohit.me/whisker/update-check';
    private const CHECK_INTERVAL = 86400; // Check once per day (seconds)

    /**
     * Check if an update is available. Returns update info or null.
     * Caches result for 24 hours to avoid spamming the API.
     */
    public static function check(): ?array
    {
        // Check if update checks are disabled by owner
        $disabled = Database::setting('general', 'disable_update_check');
        if ($disabled === '1') return null;

        // Check cache
        $cacheKey = 'update_check';
        try {
            $cached = Database::fetch(
                "SELECT setting_value FROM wk_settings WHERE setting_group='system_cache' AND setting_key=?",
                [$cacheKey]
            );
            if ($cached) {
                $data = json_decode($cached['setting_value'], true);
                if ($data && ($data['checked_at'] ?? 0) > (time() - self::CHECK_INTERVAL)) {
                    return $data['update'] ?? null;
                }
            }
        } catch (\Exception $e) {}

        // Ping update API
        $update = self::fetchUpdate();

        // Cache result
        try {
            $cacheData = json_encode([
                'checked_at' => time(),
                'update' => $update,
            ]);
            Database::query(
                "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system_cache', ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$cacheKey, $cacheData]
            );
        } catch (\Exception $e) {}

        return $update;
    }

    /**
     * Fetch latest version info from update API.
     */
    private static function fetchUpdate(): ?array
    {
        $payload = json_encode([
            'current_version' => defined('WK_VERSION') ? WK_VERSION : '1.0.0',
            'php_version' => PHP_VERSION,
            'domain' => $_SERVER['HTTP_HOST'] ?? 'unknown',
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nContent-Length: " . strlen($payload) . "\r\n",
                'content' => $payload,
                'timeout' => 5,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $body = @file_get_contents(self::UPDATE_API, false, $ctx);
        if (!$body) return null;

        $data = json_decode($body, true);
        if (!$data || !isset($data['latest_version'])) return null;

        // Compare versions
        if (version_compare($data['latest_version'], WK_VERSION, '<=')) {
            return null; // Already up to date
        }

        return [
            'version'     => $data['latest_version'],
            'download_url' => $data['download_url'] ?? null,
            'changelog'   => $data['changelog'] ?? '',
            'release_date' => $data['release_date'] ?? '',
            'severity'    => $data['severity'] ?? 'normal',
            'size'        => $data['size'] ?? '',
            'sha256'      => $data['sha256'] ?? null,
        ];
    }

    /**
     * Download and apply update.
     * Returns ['success' => bool, 'message' => string]
     */
    public static function applyUpdate(string $downloadUrl, ?string $expectedHash = null): array
    {
        // SECURITY: Only allow downloads from lohit.me
        $host = parse_url($downloadUrl, PHP_URL_HOST);
        if (!$host || !str_ends_with($host, 'lohit.me')) {
            return ['success' => false, 'message' => 'Updates can only be downloaded from lohit.me.'];
        }

        // SECURITY: Must be HTTPS
        $scheme = parse_url($downloadUrl, PHP_URL_SCHEME);
        if ($scheme !== 'https') {
            return ['success' => false, 'message' => 'Update URL must use HTTPS.'];
        }

        // Check disk space (need at least 50MB free)
        $freeSpace = @disk_free_space(WK_ROOT);
        if ($freeSpace !== false && $freeSpace < 50 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Not enough disk space (need at least 50MB free).'];
        }

        $tempDir = WK_ROOT . '/storage/cache/update_' . time();
        $zipPath = $tempDir . '.zip';

        try {
            // Create temp directory
            if (!mkdir($tempDir, 0755, true)) {
                return ['success' => false, 'message' => 'Cannot create temp directory. Check storage/cache/ permissions.'];
            }

            // Download ZIP
            $ctx = stream_context_create([
                'http' => ['timeout' => 60],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $zipData = @file_get_contents($downloadUrl, false, $ctx);
            if (!$zipData || strlen($zipData) < 100) {
                self::deleteDirectory($tempDir);
                return ['success' => false, 'message' => 'Failed to download update. Check your server can reach api.lohit.me.'];
            }

            // SECURITY: Verify SHA256 hash if provided
            if ($expectedHash) {
                $actualHash = hash('sha256', $zipData);
                if (!hash_equals($expectedHash, $actualHash)) {
                    self::deleteDirectory($tempDir);
                    return ['success' => false, 'message' => 'Update package integrity check failed. Download may be corrupted or tampered with.'];
                }
            }

            // Save ZIP
            if (!file_put_contents($zipPath, $zipData, LOCK_EX)) {
                self::deleteDirectory($tempDir);
                return ['success' => false, 'message' => 'Failed to save update file to disk.'];
            }

            // Verify it's a valid ZIP
            $zip = new \ZipArchive();
            if ($zip->open($zipPath) !== true) {
                @unlink($zipPath);
                self::deleteDirectory($tempDir);
                return ['success' => false, 'message' => 'Downloaded file is not a valid ZIP archive.'];
            }

            // SECURITY: Check for ZIP Slip (path traversal) before extracting
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if (str_contains($entryName, '..') || str_starts_with($entryName, '/') || str_starts_with($entryName, '\\')) {
                    $zip->close();
                    @unlink($zipPath);
                    self::deleteDirectory($tempDir);
                    return ['success' => false, 'message' => 'Update package contains suspicious file paths. Aborting for security.'];
                }
            }

            // Extract to temp directory
            $zip->extractTo($tempDir);
            $zip->close();

            // Find the whisker/ directory inside extracted files
            $sourceDir = $tempDir;
            if (is_dir($tempDir . '/whisker')) {
                $sourceDir = $tempDir . '/whisker';
            }

            // Protected files — never overwritten during update
            $protectedFiles = [
                'config/config.php',
                'config/database.php',
                'storage/.installed',
            ];

            // Copy files recursively, skipping protected files
            self::copyDirectory($sourceDir, WK_ROOT, $protectedFiles);

            // Cleanup
            @unlink($zipPath);
            self::deleteDirectory($tempDir);

            // Clear settings cache
            Database::clearSettingsCache();

            // Clear update check cache
            try {
                Database::query(
                    "DELETE FROM wk_settings WHERE setting_group='system_cache' AND setting_key='update_check'"
                );
            } catch (\Exception $e) {}

            return ['success' => true, 'message' => 'Update applied successfully! Please refresh the page.'];
        } catch (\Exception $e) {
            @unlink($zipPath);
            if (is_dir($tempDir)) self::deleteDirectory($tempDir);
            return ['success' => false, 'message' => 'Update failed unexpectedly. Your store is still running.'];
        }
    }

    /**
     * Copy directory recursively, skipping protected files.
     */
    private static function copyDirectory(string $src, string $dst, array $protected = [], string $relativePath = ''): void
    {
        $dir = opendir($src);
        if (!$dir) return;
        if (!is_dir($dst) && !mkdir($dst, 0755, true)) return;

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            $relPath = $relativePath ? $relativePath . '/' . $file : $file;

            // Skip protected files
            if (in_array($relPath, $protected)) continue;

            // Skip sample config files — don't overwrite real configs
            if ($file === 'config.sample.php' || $file === 'database.sample.php') {
                if (!file_exists($dstPath)) @copy($srcPath, $dstPath);
                continue;
            }

            if (is_dir($srcPath)) {
                self::copyDirectory($srcPath, $dstPath, $protected, $relPath);
            } else {
                @copy($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    /**
     * Delete directory recursively.
     */
    private static function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
