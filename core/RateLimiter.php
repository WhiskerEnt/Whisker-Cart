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
            // L12: 0700/0600 — principle of least privilege. The rate-limit
            // files are just counters but on shared hosting (cPanel), 0755
            // means other tenants on the same box can list and read the
            // directory. 0700 restricts to the web server's user.
            if (!is_dir(self::$dir)) @mkdir(self::$dir, 0700, true);
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

        // Finding 11: the read-modify-write here used to be non-atomic. Two
        // concurrent attempts could both read count=4, both write count=5,
        // and the rate limiter would effectively grant 2 attempts for the
        // cost of 1. Under brute-force pressure this measurably weakens
        // protection. Now we hold an exclusive flock() across the full
        // sequence: open ("c+" creates if missing), lock exclusive, read,
        // decide, write, unlock. Other concurrent attempts block on the
        // lock rather than racing.
        $fp = @fopen($file, 'c+');
        if (!$fp) {
            // If we can't even open the file (disk full, permissions),
            // fail CLOSED — refuse the attempt so a broken rate-limit
            // store doesn't quietly turn into open access.
            return false;
        }

        if (!@flock($fp, LOCK_EX)) {
            @fclose($fp);
            return false;
        }

        try {
            $raw  = stream_get_contents($fp);
            $data = ($raw && ($decoded = json_decode($raw, true))) ? $decoded : null;
            if (!is_array($data) || !isset($data['count'], $data['first_at'])) {
                $data = ['count' => 0, 'first_at' => time()];
            }

            // Reset if window expired.
            if ((time() - (int)$data['first_at']) >= $windowSeconds) {
                $data = ['count' => 0, 'first_at' => time()];
            }

            if ((int)$data['count'] >= $maxAttempts) {
                return false; // limited
            }

            $data['count'] = (int)$data['count'] + 1;

            // Write the new state atomically while still holding the lock.
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            return true;
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
            // L12: tighten file mode in case the default umask leaves it
            // group/world-readable on shared hosting.
            @chmod($file, 0600);
        }
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
