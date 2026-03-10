<?php
namespace Core;

/**
 * WHISKER — Simple Rate Limiter
 *
 * File-based rate limiting keyed by IP + action.
 * Uses storage/cache/ for persistence across sessions.
 */
class RateLimiter
{
    private static string $dir = '';

    private static function dir(): string
    {
        if (!self::$dir) {
            self::$dir = WK_ROOT . '/storage/cache/ratelimit';
            if (!is_dir(self::$dir)) @mkdir(self::$dir, 0755, true);
        }
        return self::$dir;
    }

    /**
     * Check if action is rate limited.
     *
     * @param string $action  Action name (e.g. 'customer_login', 'forgot_password')
     * @param string $key     Identifier (IP address, email, etc.)
     * @param int    $maxAttempts  Max attempts allowed in window
     * @param int    $windowSeconds  Time window in seconds
     * @return bool  True if allowed, false if rate limited
     */
    public static function attempt(string $action, string $key, int $maxAttempts = 5, int $windowSeconds = 900): bool
    {
        $file = self::dir() . '/' . md5($action . ':' . $key) . '.json';

        $data = ['count' => 0, 'first_at' => time()];
        if (file_exists($file)) {
            $raw = @file_get_contents($file);
            $data = $raw ? (json_decode($raw, true) ?: $data) : $data;
        }

        // Reset if window expired
        if ((time() - $data['first_at']) >= $windowSeconds) {
            $data = ['count' => 0, 'first_at' => time()];
        }

        // Check limit
        if ($data['count'] >= $maxAttempts) {
            return false;
        }

        // Increment
        $data['count']++;
        @file_put_contents($file, json_encode($data), LOCK_EX);

        return true;
    }

    /**
     * Get remaining wait time in seconds. Returns 0 if not limited.
     */
    public static function remainingSeconds(string $action, string $key, int $windowSeconds = 900): int
    {
        $file = self::dir() . '/' . md5($action . ':' . $key) . '.json';
        if (!file_exists($file)) return 0;

        $raw = @file_get_contents($file);
        $data = $raw ? json_decode($raw, true) : null;
        if (!$data) return 0;

        $elapsed = time() - $data['first_at'];
        if ($elapsed >= $windowSeconds) return 0;

        return $windowSeconds - $elapsed;
    }

    /**
     * Reset rate limit for a specific action + key (e.g. after successful login).
     */
    public static function reset(string $action, string $key): void
    {
        $file = self::dir() . '/' . md5($action . ':' . $key) . '.json';
        if (file_exists($file)) @unlink($file);
    }

    /**
     * Cleanup expired rate limit files (call periodically).
     */
    public static function cleanup(int $maxAge = 3600): void
    {
        $dir = self::dir();
        foreach (glob($dir . '/*.json') as $file) {
            if (filemtime($file) < (time() - $maxAge)) {
                @unlink($file);
            }
        }
    }
}
