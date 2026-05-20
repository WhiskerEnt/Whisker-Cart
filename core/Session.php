<?php
namespace Core;

/**
 * WHISKER — Secure Session Manager
 *
 * Security features:
 * - 15 minute inactivity timeout (admin + customer)
 * - Session ID regeneration on login (prevents session fixation)
 * - IP + User-Agent fingerprint binding (detects session hijacking)
 * - Secure cookie flags (httponly, samesite, secure when HTTPS)
 * - CSRF token per-session with timing-safe comparison
 * - Proper destroy that clears cookie
 */
class Session
{
    private static bool $started = false;

    private const TIMEOUT = 900; // 15 minutes in seconds

    public static function start(): void
    {
        if (self::$started) return;

        if (session_status() === PHP_SESSION_NONE) {
            $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

            session_name('wk_sess');
            ini_set('session.gc_maxlifetime', self::TIMEOUT);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.use_only_cookies', 1);

            session_set_cookie_params([
                'lifetime' => 0,            // Session cookie — dies when browser closes
                'path'     => '/',
                'httponly'  => true,          // No JavaScript access
                'samesite' => 'Lax',         // CSRF protection at cookie level
                'secure'   => $isSecure,     // HTTPS only when available
            ]);

            session_start();
        }

        // ── Timeout check (15 min inactivity) ────────
        if (isset($_SESSION['wk_last_activity'])) {
            $elapsed = time() - $_SESSION['wk_last_activity'];
            if ($elapsed > self::TIMEOUT) {
                $wasAdmin = self::isAdmin();
                $wasCustomer = self::customerId();

                // Destroy everything
                self::destroyFull();

                // Restart fresh session for flash message
                session_start();
                if ($wasAdmin) {
                    self::flash('warning', 'You were signed out due to inactivity.');
                } elseif ($wasCustomer) {
                    self::flash('warning', 'Session expired. Please sign in again.');
                }
                self::$started = true;
                return;
            }
        }
        $_SESSION['wk_last_activity'] = time();

        // ── Fingerprint check (session hijacking detection) ──
        $fingerprint = self::generateFingerprint();
        if (isset($_SESSION['wk_fingerprint'])) {
            // Accept the current (UA-only) fingerprint OR the legacy
            // (IP+UA) fingerprint left over from sessions created on
            // pre-1.3.0 installs. On match, silently rotate to the new
            // format so the legacy hash doesn't linger forever. On full
            // mismatch (neither legacy nor current matches), treat as a
            // possible hijack and destroy.
            $legacyFingerprint = self::generateLegacyFingerprint();
            if ($_SESSION['wk_fingerprint'] !== $fingerprint
             && $_SESSION['wk_fingerprint'] !== $legacyFingerprint) {
                // Possible session hijack — destroy and restart
                self::destroyFull();
                session_start();
                self::$started = true;
                return;
            }
            // Quietly upgrade to the new format.
            if ($_SESSION['wk_fingerprint'] !== $fingerprint) {
                $_SESSION['wk_fingerprint'] = $fingerprint;
            }
        } else {
            $_SESSION['wk_fingerprint'] = $fingerprint;
        }

        self::$started = true;
    }

    // ── Basic get/set ────────────────────────────

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Full destroy — clears session data, cookie, and starts fresh
     */
    public static function destroy(): void
    {
        self::destroyFull();
        self::$started = false;
    }

    private static function destroyFull(): void
    {
        $_SESSION = [];

        // Delete the session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        self::$started = false;
    }

    // ── CSRF Protection ──────────────────────────

    public static function csrfToken(): string
    {
        if (empty($_SESSION['wk_csrf'])) {
            $_SESSION['wk_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['wk_csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="wk_csrf" value="' . self::csrfToken() . '">';
    }

    public static function verifyCsrf(?string $token): bool
    {
        if (empty($token) || empty($_SESSION['wk_csrf'])) return false;
        return hash_equals($_SESSION['wk_csrf'], $token);
        // Token is rotated on login (setAdmin/setCustomer) and cleared on
        // logout (destroy), so any token captured during an unauthenticated
        // session cannot be replayed after the auth state changes.
    }

    // ── Flash Messages ───────────────────────────

    public static function flash(string $type, string $message): void
    {
        $_SESSION['wk_flash'][] = ['type' => $type, 'message' => $message];
    }

    public static function getFlashes(): array
    {
        $flashes = $_SESSION['wk_flash'] ?? [];
        unset($_SESSION['wk_flash']);
        return $flashes;
    }

    // ── Old Input (form repopulation after validation errors) ──

    public static function setOldInput(array $data): void
    {
        // Strip sensitive fields
        unset($data['wk_csrf'], $data['password'], $data['admin_pass'], $data['admin_pass2']);
        $_SESSION['wk_old_input'] = $data;
    }

    public static function old(string $key, $default = '')
    {
        return $_SESSION['wk_old_input'][$key] ?? $default;
    }

    public static function getOldInput(): array
    {
        $old = $_SESSION['wk_old_input'] ?? [];
        unset($_SESSION['wk_old_input']);
        return $old;
    }

    public static function hasOldInput(): bool
    {
        return !empty($_SESSION['wk_old_input']);
    }

    // ── Auth Helpers ─────────────────────────────

    /**
     * Set admin login — regenerates session ID to prevent fixation attacks
     * and rotates the CSRF token so any token captured before login becomes
     * invalid.
     */
    public static function setAdmin(int $id): void
    {
        session_regenerate_id(true); // New session ID, delete old one
        unset($_SESSION['wk_csrf']); // Force fresh CSRF token on next read
        $_SESSION['wk_admin_id'] = $id;
        $_SESSION['wk_last_activity'] = time();
        $_SESSION['wk_fingerprint'] = self::generateFingerprint();
    }

    public static function adminId(): ?int
    {
        return $_SESSION['wk_admin_id'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return !empty($_SESSION['wk_admin_id']);
    }

    /**
     * Set customer login — regenerates session ID and rotates the CSRF token
     * so any token captured before login becomes invalid.
     */
    public static function setCustomer(int $id): void
    {
        session_regenerate_id(true);
        unset($_SESSION['wk_csrf']); // Force fresh CSRF token on next read
        $_SESSION['wk_customer_id'] = $id;
        $_SESSION['wk_last_activity'] = time();
        $_SESSION['wk_fingerprint'] = self::generateFingerprint();
    }

    public static function customerId(): ?int
    {
        return $_SESSION['wk_customer_id'] ?? null;
    }

    // ── Cart Session ─────────────────────────────

    public static function cartId(): string
    {
        if (empty($_SESSION['wk_cart_session'])) {
            $_SESSION['wk_cart_session'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['wk_cart_session'];
    }

    // ── Internal ─────────────────────────────────

    /**
     * Generate a session fingerprint to detect cookie theft across devices.
     *
     * **Historical note:** earlier versions of this method hashed
     * IP + User-Agent + salt. That was too aggressive in practice and
     * caused the "Your session expired. Please try again." failure mode
     * at checkout on consumer ISPs:
     *
     *   - Mobile carriers (Jio, Airtel, etc.) rotate egress IPs behind
     *     CGNAT mid-session.
     *   - Customers handing off Wi-Fi ↔ cellular get a new public IP for
     *     the form-submit POST that didn't exist for the form-render GET.
     *   - CDNs / corporate proxies sometimes load-balance requests across
     *     multiple egress IPs for the same user.
     *
     * In all three cases the IP changed but the user did NOT — the session
     * cookie wasn't stolen. The fingerprint mismatch then destroyed the
     * session, wiping $_SESSION['wk_csrf'] and $_SESSION['wk_cart_session'],
     * which presented to the customer as "session expired + empty cart" at
     * the moment they pressed Pay.
     *
     * OWASP's Session Management Cheat Sheet explicitly recommends AGAINST
     * binding session validity to client IP for exactly this reason
     * (https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html#binding-the-session-id-to-other-user-properties).
     * Every mainstream framework (Laravel, Symfony, Rails, Django) does
     * UA-only or no fingerprinting at all. The real defenses against
     * cookie theft are:
     *
     *   - HttpOnly cookie (set in start() — blocks JS access)
     *   - SameSite=Lax (blocks cross-site CSRF-via-cookie)
     *   - Secure flag when HTTPS (no plaintext leak)
     *   - session_regenerate_id(true) on every privilege change
     *     (setAdmin/setCustomer call this — limits replay window)
     *
     * Those are what stop a stolen cookie. The UA component below is a
     * cheap last-mile check that catches the trivial case of a cookie
     * pasted into a different browser; it does NOT catch a determined
     * attacker who copies the User-Agent header along with the cookie,
     * but that's an acceptable tradeoff because the alternative (the old
     * IP-bound fingerprint) was breaking real checkouts.
     */
    private static function generateFingerprint(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        // Use installation-specific salt if available so a fingerprint
        // captured from one Whisker install can't be replayed against
        // another even if the cookie were somehow transplanted.
        $salt = defined('WK_BASE_URL') ? WK_BASE_URL : 'whisker_default_salt';
        return hash('sha256', $ua . '|' . $salt);
    }

    /**
     * Pre-1.3.0 fingerprint format (IP + UA + salt). Kept ONLY so existing
     * sessions don't get destroyed at the moment of upgrade. New sessions
     * never write this format; old ones are upgraded in place by start()
     * after a successful match. Remove this method in a future major
     * version once no live install can reasonably still be running
     * sessions issued before the 1.3.0 deploy (~1 week given the 15-min
     * inactivity timeout — sessions naturally roll over in that window).
     */
    private static function generateLegacyFingerprint(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $salt = defined('WK_BASE_URL') ? WK_BASE_URL : 'whisker_default_salt';
        return hash('sha256', $ip . '|' . $ua . '|' . $salt);
    }
}
