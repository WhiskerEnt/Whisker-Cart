<?php
namespace App\Services;

use Core\Database;

/**
 * WHISKER — Migration Service
 *
 * Runs pending database migrations after code updates.
 * Each migration file is named: YYYYMMDD_description.sql
 * Once a migration runs, its filename is recorded in wk_settings
 * so it never runs again.
 *
 * Migrations live in /sql/migrations/ directory.
 * They run automatically when the admin dashboard loads and detects
 * a version change.
 */
class MigrationService
{
    private const MIGRATIONS_DIR = '/sql/migrations';

    /**
     * Run all pending migrations.
     * Called automatically when version changes.
     * @return array ['ran' => int, 'errors' => [...]]
     */
    public static function runPending(): array
    {
        $dir = WK_ROOT . self::MIGRATIONS_DIR;
        if (!is_dir($dir)) return ['ran' => 0, 'errors' => []];

        // Get list of already-run migrations
        $executed = self::getExecutedMigrations();

        // Get all migration files sorted by name (date order)
        $files = glob($dir . '/*.sql');
        if (!$files) return ['ran' => 0, 'errors' => []];
        sort($files);

        $ran = 0;
        $errors = [];

        foreach ($files as $file) {
            $filename = basename($file);

            // Skip if already executed
            if (in_array($filename, $executed)) continue;

            // Read and execute
            $sql = file_get_contents($file);
            if (!$sql || !trim($sql)) continue;

            try {
                // Split by semicolons, execute each statement
                $statements = self::splitSql($sql);
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '' || str_starts_with($stmt, '--')) continue;
                    Database::exec($stmt);
                }

                // Record as executed
                self::recordMigration($filename);
                $ran++;
            } catch (\Exception $e) {
                $errors[] = $filename . ': ' . $e->getMessage();
            }
        }

        return ['ran' => $ran, 'errors' => $errors];
    }

    /**
     * Get list of already-executed migration filenames.
     */
    private static function getExecutedMigrations(): array
    {
        try {
            $data = Database::fetchValue(
                "SELECT setting_value FROM wk_settings WHERE setting_group='system' AND setting_key='executed_migrations'"
            );
            if ($data) {
                return json_decode($data, true) ?: [];
            }
        } catch (\Exception $e) {}
        return [];
    }

    /**
     * Record a migration as executed.
     */
    private static function recordMigration(string $filename): void
    {
        $executed = self::getExecutedMigrations();
        $executed[] = $filename;
        $json = json_encode(array_unique($executed));

        try {
            Database::query(
                "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system', 'executed_migrations', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$json]
            );
        } catch (\Exception $e) {}
    }

    /**
     * Split SQL string into individual statements.
     * Handles semicolons inside strings.
     */
    private static function splitSql(string $sql): array
    {
        $statements = [];
        $current = '';
        $inString = false;
        $stringChar = '';
        $len = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $char = $sql[$i];

            // Handle string literals
            if (!$inString && ($char === "'" || $char === '"')) {
                $inString = true;
                $stringChar = $char;
                $current .= $char;
                continue;
            }
            if ($inString && $char === $stringChar && ($i === 0 || $sql[$i-1] !== '\\')) {
                $inString = false;
                $current .= $char;
                continue;
            }

            // Split on semicolons outside strings
            if (!$inString && $char === ';') {
                $stmt = trim($current);
                if ($stmt !== '') $statements[] = $stmt;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        // Last statement (might not end with semicolon)
        $stmt = trim($current);
        if ($stmt !== '') $statements[] = $stmt;

        return $statements;
    }

    /**
     * Check if version changed since last check. If so, run migrations.
     */
    public static function checkAndRun(): array
    {
        $lastVersion = Database::setting('system', 'last_version');
        $currentVersion = defined('WK_VERSION') ? WK_VERSION : '0.0.0';

        if ($lastVersion === $currentVersion) {
            return ['ran' => 0, 'errors' => []]; // Same version, no migrations needed
        }

        // Version changed — run pending migrations
        $result = self::runPending();

        // Update stored version
        try {
            Database::query(
                "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system', 'last_version', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$currentVersion]
            );
        } catch (\Exception $e) {}

        return $result;
    }
}
