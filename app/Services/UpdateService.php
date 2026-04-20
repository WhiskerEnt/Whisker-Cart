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
    public static function applyUpdate(string $downloadUrl, ?string $expectedHash = null, string $dbBackupMode = 'schema'): array
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

        // ── Create backup before updating ──
        $backupResult = self::createBackup($dbBackupMode);
        if (!$backupResult['success']) {
            return ['success' => false, 'message' => 'Backup failed: ' . $backupResult['message'] . ' Update aborted.'];
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

            return ['success' => true, 'message' => 'Update applied successfully! A backup of v' . WK_VERSION . ' was saved. Refresh the page.'];
        } catch (\Exception $e) {
            @unlink($zipPath);
            if (is_dir($tempDir)) self::deleteDirectory($tempDir);
            return ['success' => false, 'message' => 'Update failed unexpectedly. Your store is still running. A backup was saved.'];
        }
    }

    // ── Backup & Rollback ───────────────────────────

    private const BACKUP_DIR = '/storage/cache/backups';
    private const MAX_BACKUPS = 3;

    /**
     * Directories to skip when creating backup (uploads don't change during updates).
     */
    private static array $backupSkipDirs = [
        'storage/uploads',
        'storage/cache',
        'storage/logs',
    ];

    /**
     * Create a backup ZIP of the current installation.
     * @param string $dbMode 'none' | 'schema' | 'full'
     */
    public static function createBackup(string $dbMode = 'schema'): array
    {
        $backupDir = WK_ROOT . self::BACKUP_DIR;
        if (!is_dir($backupDir) && !@mkdir($backupDir, 0755, true)) {
            return ['success' => false, 'message' => 'Cannot create backup directory.'];
        }

        $version = defined('WK_VERSION') ? WK_VERSION : 'unknown';
        $filename = 'backup_v' . $version . '_' . date('Ymd_His') . '.zip';
        $backupPath = $backupDir . '/' . $filename;

        $zip = new \ZipArchive();
        if ($zip->open($backupPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'message' => 'Cannot create backup ZIP file.'];
        }

        // Add all files except uploads, cache, logs
        self::addDirectoryToZip($zip, WK_ROOT, '', self::$backupSkipDirs);

        // Add database backup if requested
        if ($dbMode === 'schema' || $dbMode === 'full') {
            $sqlDump = self::dumpDatabase($dbMode === 'full');
            if ($sqlDump) {
                $zip->addFromString('_db_backup.sql', $sqlDump);
            }
        }

        $zip->close();

        // Verify backup was created and has content
        if (!file_exists($backupPath) || filesize($backupPath) < 100) {
            @unlink($backupPath);
            return ['success' => false, 'message' => 'Backup file is empty or corrupted.'];
        }

        // Cleanup old backups — keep only the latest MAX_BACKUPS
        self::cleanupOldBackups($backupDir);

        // Store backup info in settings
        $backupSize = filesize($backupPath);
        try {
            $backupInfo = json_encode([
                'filename' => $filename,
                'version'  => $version,
                'db_mode'  => $dbMode,
                'created'  => date('Y-m-d H:i:s'),
                'size'     => $backupSize,
            ]);
            Database::query(
                "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system_cache', 'last_backup', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$backupInfo]
            );
        } catch (\Exception $e) {}

        return ['success' => true, 'message' => 'Backup created.', 'filename' => $filename, 'size' => $backupSize];
    }

    /**
     * Estimate database size for display to user.
     * Returns ['schema' => bytes, 'full' => bytes, 'tables' => [...]]
     */
    public static function estimateDbSize(): array
    {
        $tables = [];
        $schemaSize = 0;
        $fullSize = 0;

        try {
            $rows = Database::fetchAll("SHOW TABLE STATUS LIKE 'wk_%'");
            foreach ($rows as $row) {
                $dataLen = (int)($row['Data_length'] ?? 0);
                $indexLen = (int)($row['Index_length'] ?? 0);
                $rowCount = (int)($row['Rows'] ?? 0);
                $total = $dataLen + $indexLen;
                $tables[] = [
                    'name'  => $row['Name'],
                    'rows'  => $rowCount,
                    'size'  => $total,
                ];
                $fullSize += $total;
                $schemaSize += 2048; // ~2KB per table for CREATE TABLE statement
            }
        } catch (\Exception $e) {}

        return [
            'schema' => $schemaSize,
            'full'   => $fullSize,
            'tables' => $tables,
            'table_count' => count($tables),
        ];
    }

    /**
     * Dump database to SQL string.
     * @param bool $includeData If false, only dumps CREATE TABLE statements (schema only).
     */
    private static function dumpDatabase(bool $includeData = false): ?string
    {
        try {
            $sql = "-- Whisker Database Backup\n";
            $sql .= "-- Version: " . (defined('WK_VERSION') ? WK_VERSION : 'unknown') . "\n";
            $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Mode: " . ($includeData ? 'Full dump' : 'Schema only') . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

            $tables = Database::fetchAll("SHOW TABLES LIKE 'wk_%'");
            foreach ($tables as $row) {
                $tableName = array_values($row)[0];

                // Get CREATE TABLE statement
                $create = Database::fetch("SHOW CREATE TABLE `{$tableName}`");
                $createSql = $create['Create Table'] ?? '';
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $createSql . ";\n\n";

                // Dump data if full mode
                if ($includeData) {
                    $rows = Database::fetchAll("SELECT * FROM `{$tableName}`");
                    if (!empty($rows)) {
                        $columns = array_keys($rows[0]);
                        $colList = '`' . implode('`, `', $columns) . '`';

                        // Batch inserts (100 rows per INSERT for performance)
                        $chunks = array_chunk($rows, 100);
                        foreach ($chunks as $chunk) {
                            $values = [];
                            foreach ($chunk as $dataRow) {
                                $vals = [];
                                foreach ($dataRow as $val) {
                                    if ($val === null) {
                                        $vals[] = 'NULL';
                                    } else {
                                        $vals[] = "'" . addslashes((string)$val) . "'";
                                    }
                                }
                                $values[] = '(' . implode(', ', $vals) . ')';
                            }
                            $sql .= "INSERT INTO `{$tableName}` ({$colList}) VALUES\n" . implode(",\n", $values) . ";\n";
                        }
                        $sql .= "\n";
                    }
                }
            }

            $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            return $sql;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Rollback to a backup ZIP.
     */
    public static function rollback(string $filename): array
    {
        // Sanitize filename
        $filename = basename($filename);
        if (!preg_match('/^backup_v[\d.]+_\d{8}_\d{6}\.zip$/', $filename)) {
            return ['success' => false, 'message' => 'Invalid backup filename.'];
        }

        $backupPath = WK_ROOT . self::BACKUP_DIR . '/' . $filename;
        if (!file_exists($backupPath)) {
            return ['success' => false, 'message' => 'Backup file not found.'];
        }

        // Verify it's a real ZIP
        $zip = new \ZipArchive();
        if ($zip->open($backupPath) !== true) {
            return ['success' => false, 'message' => 'Backup file is corrupted.'];
        }

        // ZIP Slip check
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (str_contains($entry, '..') || str_starts_with($entry, '/') || str_starts_with($entry, '\\')) {
                $zip->close();
                return ['success' => false, 'message' => 'Backup contains suspicious paths. Aborting.'];
            }
        }

        // Extract to temp, then copy (same safe flow as update)
        $tempDir = WK_ROOT . '/storage/cache/rollback_' . time();
        if (!@mkdir($tempDir, 0755, true)) {
            $zip->close();
            return ['success' => false, 'message' => 'Cannot create temp directory for rollback.'];
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $protectedFiles = [
            'config/config.php',
            'config/database.php',
            'storage/.installed',
        ];

        self::copyDirectory($tempDir, WK_ROOT, $protectedFiles);
        self::deleteDirectory($tempDir);

        // Clear caches
        Database::clearSettingsCache();
        try {
            Database::query("DELETE FROM wk_settings WHERE setting_group='system_cache' AND setting_key='update_check'");
        } catch (\Exception $e) {}

        return ['success' => true, 'message' => 'Rollback complete. Your store has been restored. Refresh the page.'];
    }

    /**
     * Get list of available backups.
     */
    public static function getBackups(): array
    {
        $backupDir = WK_ROOT . self::BACKUP_DIR;
        if (!is_dir($backupDir)) return [];

        $backups = [];
        foreach (glob($backupDir . '/backup_v*.zip') as $file) {
            $name = basename($file);
            preg_match('/^backup_v([\d.]+)_(\d{8})_(\d{6})\.zip$/', $name, $m);

            // Check if ZIP contains _db_backup.sql
            $hasDb = false;
            try {
                $zip = new \ZipArchive();
                if ($zip->open($file) === true) {
                    $hasDb = ($zip->locateName('_db_backup.sql') !== false);
                    $zip->close();
                }
            } catch (\Exception $e) {}

            $backups[] = [
                'filename' => $name,
                'version'  => $m[1] ?? 'unknown',
                'date'     => isset($m[2], $m[3]) ? substr($m[2],0,4).'-'.substr($m[2],4,2).'-'.substr($m[2],6,2).' '.substr($m[3],0,2).':'.substr($m[3],2,2).':'.substr($m[3],4,2) : '',
                'size'     => filesize($file),
                'has_db'   => $hasDb,
            ];
        }

        usort($backups, fn($a, $b) => strcmp($b['filename'], $a['filename']));
        return $backups;
    }

    /**
     * Add directory contents to ZIP, skipping specified directories.
     */
    private static function addDirectoryToZip(\ZipArchive $zip, string $baseDir, string $relativePath, array $skipDirs): void
    {
        $fullPath = $relativePath ? $baseDir . '/' . $relativePath : $baseDir;
        $dir = @opendir($fullPath);
        if (!$dir) return;

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $srcPath = $fullPath . '/' . $file;
            $relPath = $relativePath ? $relativePath . '/' . $file : $file;

            // Skip specified directories
            $skip = false;
            foreach ($skipDirs as $skipDir) {
                if ($relPath === $skipDir || str_starts_with($relPath, $skipDir . '/')) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            // Skip hidden files and the backup directory itself
            if (str_starts_with($file, '.') && $file !== '.htaccess') continue;

            if (is_dir($srcPath)) {
                $zip->addEmptyDir($relPath);
                self::addDirectoryToZip($zip, $baseDir, $relPath, $skipDirs);
            } else {
                $zip->addFile($srcPath, $relPath);
            }
        }
        closedir($dir);
    }

    /**
     * Keep only the latest N backups.
     */
    private static function cleanupOldBackups(string $backupDir): void
    {
        $files = glob($backupDir . '/backup_v*.zip');
        if (count($files) <= self::MAX_BACKUPS) return;

        // Sort oldest first
        usort($files, fn($a, $b) => filemtime($a) - filemtime($b));

        // Delete oldest ones
        $toDelete = count($files) - self::MAX_BACKUPS;
        for ($i = 0; $i < $toDelete; $i++) {
            @unlink($files[$i]);
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
