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
     *
     * Finding 16: when two admins clicked "Apply Update" near-simultaneously,
     * both processes saw the same "not-yet-executed" list and both tried to
     * run the same SQL — typically producing "duplicate column" or
     * double-inserted seed rows. Now wrapped in a MySQL named lock so only
     * one runner can be active at a time, server-wide.
     *
     * @return array ['ran' => int, 'errors' => [...], 'busy' => bool]
     */
    public static function runPending(): array
    {
        $dir = WK_ROOT . self::MIGRATIONS_DIR;
        if (!is_dir($dir)) return ['ran' => 0, 'errors' => []];

        // MySQL named lock — instance-wide, survives the request, but
        // auto-released if the connection drops. 0s timeout = "fail fast"
        // rather than block the second admin's PHP-FPM worker.
        $lockKey = 'whisker_migrations';
        try {
            $got = Database::fetchValue("SELECT GET_LOCK(?, 0)", [$lockKey]);
        } catch (\Exception $e) {
            $got = null;
        }
        if ((int)$got !== 1) {
            // Another runner has the lock. Tell the caller; the dashboard
            // can show "migrations already running, refresh in a moment".
            return ['ran' => 0, 'errors' => [], 'busy' => true];
        }

        try {
            // Get list of already-run migrations
            $executed = self::getExecutedMigrations();
            $partialProgress = self::getPartialProgress();

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

                // M17: per-statement progress. The old behavior was "record the
                // migration only on full success", which meant a failure on
                // statement 3-of-5 caused statements 1-2 to re-run on retry —
                // typically triggering "duplicate column" failures even though
                // the data migration was actually in progress. Now we remember
                // how far we got, so retries skip the already-succeeded prefix.
                $startIdx = (int)($partialProgress[$filename] ?? 0);
                $statements = self::splitSql($sql);
                $cleaned = [];
                foreach ($statements as $s) {
                    $s = trim($s);
                    if ($s === '' || str_starts_with($s, '--')) continue;
                    $cleaned[] = $s;
                }

                $appliedThisRun = 0;
                $failedStmt = null;
                for ($i = $startIdx; $i < count($cleaned); $i++) {
                    try {
                        Database::exec($cleaned[$i]);
                        $appliedThisRun++;
                    } catch (\Exception $e) {
                        $failedStmt = $cleaned[$i];
                        $errors[] = $filename . " (stmt " . ($i + 1) . "): " . $e->getMessage();
                        // Record how far we got so the next retry resumes here.
                        self::recordPartialProgress($filename, $i);
                        break;
                    }
                }

                if ($failedStmt === null) {
                    // Whole migration succeeded — mark file as executed and
                    // drop partial-progress bookkeeping for it.
                    self::recordMigration($filename);
                    self::clearPartialProgress($filename);
                    if ($appliedThisRun > 0 || $startIdx > 0) $ran++;
                }
            }

            return ['ran' => $ran, 'errors' => $errors];
        } finally {
            // Always release the MySQL lock, even on uncaught exception.
            try { Database::query("SELECT RELEASE_LOCK(?)", [$lockKey]); } catch (\Exception $e) {}
        }
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

    // ── Partial-progress bookkeeping (M17) ──
    // Stores how many statements within an in-progress migration have
    // succeeded, keyed by filename. Cleared when the migration finishes.

    private static function getPartialProgress(): array
    {
        try {
            $data = Database::fetchValue(
                "SELECT setting_value FROM wk_settings WHERE setting_group='system' AND setting_key='migration_progress'"
            );
            if ($data) return json_decode($data, true) ?: [];
        } catch (\Exception $e) {}
        return [];
    }

    private static function recordPartialProgress(string $filename, int $nextStmtIndex): void
    {
        $progress = self::getPartialProgress();
        // Store the index of the NEXT statement to try, i.e. the index of
        // the one that just failed. On retry the loop resumes from here.
        $progress[$filename] = $nextStmtIndex;
        try {
            Database::query(
                "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system', 'migration_progress', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [json_encode($progress)]
            );
        } catch (\Exception $e) {}
    }

    private static function clearPartialProgress(string $filename): void
    {
        $progress = self::getPartialProgress();
        if (!isset($progress[$filename])) return;
        unset($progress[$filename]);
        try {
            Database::query(
                "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system', 'migration_progress', ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [json_encode($progress)]
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
