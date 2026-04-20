<?php
namespace Core;

use PDO;
use PDOStatement;
use PDOException;

/**
 * WHISKER — Database (PDO Singleton)
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $cfg = require WK_ROOT . '/config/database.php';
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']);
            try {
                self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                if (defined('WK_DEBUG') && WK_DEBUG) {
                    die('DB Error: ' . $e->getMessage());
                }
                die('Database connection failed. Check your configuration.');
            }
        }
        return self::$pdo;
    }

    /**
     * Test a database connection with given credentials.
     * Used by the installer before config is written.
     */
    public static function testConnection(string $host, int $port, string $name, string $user, string $pass): array
    {
        try {
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $name);
            new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connect()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row ?: null;
    }

    public static function fetchValue(string $sql, array $params = [])
    {
        return self::query($sql, $params)->fetchColumn();
    }

    public static function insert(string $table, array $data): int
    {
        $cols   = implode(', ', array_keys($data));
        $places = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO {$table} ({$cols}) VALUES ({$places})", array_values($data));
        return (int)self::connect()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = implode(', ', array_map(fn($k) => "{$k} = ?", array_keys($data)));
        return self::query(
            "UPDATE {$table} SET {$sets} WHERE {$where}",
            array_merge(array_values($data), $whereParams)
        )->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::query("DELETE FROM {$table} WHERE {$where}", $params)->rowCount();
    }

    /** Run raw SQL (used by installer to execute schema) */
    public static function exec(string $sql): void
    {
        self::connect()->exec($sql);
    }

    // ── Settings Cache ──────────────────────────
    // Loads all settings once per request, avoids 10+ DB queries per page

    private static ?array $settingsCache = null;

    /**
     * Get a setting value with in-memory caching.
     * First call loads ALL settings into memory. Subsequent calls are instant.
     */
    public static function setting(string $group, string $key, ?string $default = null): ?string
    {
        if (self::$settingsCache === null) {
            self::$settingsCache = [];
            try {
                $rows = self::fetchAll("SELECT setting_group, setting_key, setting_value FROM wk_settings");
                foreach ($rows as $row) {
                    self::$settingsCache[$row['setting_group'] . '.' . $row['setting_key']] = $row['setting_value'];
                }
            } catch (\Exception $e) {}
        }
        return self::$settingsCache[$group . '.' . $key] ?? $default;
    }

    /**
     * Clear settings cache (call after updating settings).
     */
    public static function clearSettingsCache(): void
    {
        self::$settingsCache = null;
    }
}
