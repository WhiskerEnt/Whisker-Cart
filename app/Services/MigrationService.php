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
     * Migrations recorded as "executed" by pre-1.3.2 versions that may never
     * have actually run. The old splitSql() glued the `--` header comment to
     * the first real statement, the runner then skipped that "comment"
     * statement, and the file was still recorded as executed. On upgrade
     * these are forgotten once so they re-run under the fixed splitter —
     * safe now that benign duplicate errors are treated as already-applied.
     */
    private const REPAIR_RERUN = [
        '20260420_v110_abandoned_cart_columns.sql',
        '20260420_v120_tax_engine.sql',
        '20260511_v121_password_resets_table.sql',
        '20260520_v122_order_status_payment_failed.sql',
    ];

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
                        if (self::isBenignDuplicateError($e)) {
                            // The object this statement creates already exists
                            // (schema.sql seeded it on a fresh install, or the
                            // migration is being re-run by the repair path).
                            // That IS the desired end state — keep going.
                            $appliedThisRun++;
                            continue;
                        }
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
     * Handles semicolons inside strings, and strips `--` line comments.
     *
     * Comment handling matters: every migration file starts with a `--`
     * header block. The old splitter kept comment text inside the statement,
     * so (a) the runner's "skip statements starting with --" check silently
     * discarded the REAL statement glued to the header, and (b) apostrophes
     * inside comment prose ("don't", "'payment_failed'") flipped the
     * in-string state and broke splitting on later semicolons. Comments are
     * now consumed during the scan, outside of string literals only.
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

            // Consume `--` line comments (outside strings): skip to end of line.
            if (!$inString && $char === '-' && ($sql[$i+1] ?? '') === '-') {
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }
            // Consume `#` line comments (outside strings) — MySQL also allows these.
            if (!$inString && $char === '#') {
                while ($i < $len && $sql[$i] !== "\n") $i++;
                continue;
            }

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
     * True when the statement failed only because its object already exists —
     * duplicate table/column/index/row. Codes: 1050 table exists, 1060 dup
     * column, 1061 dup key name, 1062 dup entry, 1091 can't drop missing.
     * Message match is the fallback for exceptions that lost errorInfo.
     */
    private static function isBenignDuplicateError(\Exception $e): bool
    {
        if ($e instanceof \PDOException && isset($e->errorInfo[1])
            && in_array((int)$e->errorInfo[1], [1050, 1060, 1061, 1062, 1091], true)) {
            return true;
        }
        return (bool)preg_match(
            '/already exists|Duplicate column|Duplicate key name|Duplicate entry|check that (column|it) .*exists/i',
            $e->getMessage()
        );
    }

    /**
     * Drop legacy filenames from the executed/partial bookkeeping so the
     * fixed runner executes them for real (see REPAIR_RERUN).
     */
    private static function forgetRepairMigrations(): void
    {
        $executed = self::getExecutedMigrations();
        $filtered = array_values(array_diff($executed, self::REPAIR_RERUN));
        if (count($filtered) !== count($executed)) {
            try {
                Database::query(
                    "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system', 'executed_migrations', ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    [json_encode($filtered)]
                );
            } catch (\Exception $e) {}
        }
        foreach (self::REPAIR_RERUN as $file) {
            self::clearPartialProgress($file);
        }
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

        // Version changed — first repair pre-1.4 bookkeeping so silently
        // skipped legacy migrations get a real run (idempotent: on stores
        // where they did apply, every statement hits the benign-duplicate
        // path and is treated as applied).
        self::forgetRepairMigrations();

        // Run pending migrations
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
