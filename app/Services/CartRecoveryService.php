<?php
namespace App\Services;

use Core\Database;
use Core\View;

/**
 * WHISKER — Abandoned cart recovery
 *
 * A cart with items that has sat untouched long enough is abandoned. If the
 * shop has the details to reach the shopper, it sends a short sequence of
 * reminders, each carrying a link that puts the basket back exactly as they
 * left it — on whatever device they open the mail on.
 *
 * Reminders are only worth sending if the shop can tell whether they work, so
 * a recovered cart is stamped with the order it turned into.
 *
 * Nothing here runs until the shopkeeper turns it on.
 */
class CartRecoveryService
{
    /** Never sweep more often than this, however busy the storefront is. */
    private const SWEEP_INTERVAL = 300;

    /** A recovery link is a bearer token, so it does not live forever. */
    public const TOKEN_TTL_DAYS = 30;

    public static function enabled(): bool
    {
        return Database::setting('cart_recovery', 'recovery_enabled', '0') === '1';
    }

    public static function abandonAfterMinutes(): int
    {
        return max(5, (int) Database::setting('cart_recovery', 'abandon_after_minutes', '60'));
    }

    /**
     * Minutes after abandonment at which each reminder goes out.
     *
     * @return int[] ascending, deduplicated
     */
    public static function schedule(): array
    {
        $raw = (string) Database::setting('cart_recovery', 'recovery_schedule', '60,1440,4320');
        $mins = array_filter(array_map('intval', explode(',', $raw)), fn($m) => $m > 0);
        $mins = array_values(array_unique($mins));
        sort($mins);
        return array_slice($mins, 0, 5);
    }

    public static function couponCode(): string
    {
        return trim((string) Database::setting('cart_recovery', 'recovery_coupon', ''));
    }

    // ── Abandonment ──────────────────────────────────────────────────────

    /**
     * Mark carts abandoned once they have been untouched long enough.
     *
     * The pruner only removes carts in a terminal state, so something has to
     * put them there; left to hand-marking alone the table grows without
     * limit.
     *
     * @return int how many carts changed
     */
    public static function markAbandoned(): int
    {
        $minutes = self::abandonAfterMinutes();
        try {
            return Database::query(
                // abandoned_at records when the shopper stopped, not when we
                // noticed. Stamping NOW() would push every reminder back by
                // however long the sweep took to get to it.
                "UPDATE wk_carts c
                    SET c.status = 'abandoned', c.abandoned_at = c.updated_at
                  WHERE c.status = 'active'
                    AND c.updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)
                    AND EXISTS (SELECT 1 FROM wk_cart_items ci WHERE ci.cart_id = c.id)",
                [$minutes]
            )->rowCount();
        } catch (\Exception $e) {
            return 0;
        }
    }

    // ── Recovery tokens ──────────────────────────────────────────────────

    public static function tokenFor(int $cartId): ?string
    {
        try {
            $existing = Database::fetchValue("SELECT recovery_token FROM wk_carts WHERE id=?", [$cartId]);
            if ($existing) return $existing;

            $token = bin2hex(random_bytes(20));
            Database::query("UPDATE wk_carts SET recovery_token=? WHERE id=?", [$token, $cartId]);
            return $token;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * The cart a recovery link points at, if the link is still good.
     *
     * A token that has aged out returns nothing rather than restoring a
     * basket of prices and stock levels from a month ago.
     */
    public static function cartForToken(string $token): ?array
    {
        $token = trim($token);
        if (!preg_match('/^[a-f0-9]{40}$/', $token)) return null;

        try {
            return Database::fetch(
                "SELECT * FROM wk_carts
                  WHERE recovery_token = ?
                    AND status IN ('active','abandoned')
                    AND updated_at > DATE_SUB(NOW(), INTERVAL ? DAY)",
                [$token, self::TOKEN_TTL_DAYS]
            ) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function recoveryUrl(int $cartId): string
    {
        $token = self::tokenFor($cartId);
        return $token ? View::url('cart/recover/' . $token) : View::url('cart');
    }

    // ── Suppression ──────────────────────────────────────────────────────

    public static function isSuppressed(string $email): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') return true;
        try {
            return (bool) Database::fetchValue(
                "SELECT 1 FROM wk_email_suppressions WHERE email=? LIMIT 1",
                [$email]
            );
        } catch (\Exception $e) {
            // With no suppression table there is no record of consent either,
            // so the safe reading is to say nothing.
            return true;
        }
    }

    public static function suppress(string $email, string $reason = 'unsubscribed'): bool
    {
        $email = strtolower(trim($email));
        if ($email === '') return false;
        try {
            Database::query(
                "INSERT INTO wk_email_suppressions (email, reason) VALUES (?,?)
                 ON DUPLICATE KEY UPDATE reason=VALUES(reason)",
                [$email, $reason]
            );
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function unsubscribeUrl(string $email): string
    {
        return View::url('cart/unsubscribe/' . rawurlencode(self::unsubscribeToken($email)) . '/' . rawurlencode($email));
    }

    /** @var string|null the signing secret, read once per request */
    private static ?string $salt = null;

    /**
     * The secret unsubscribe links are signed with.
     *
     * The installer generates a salt in config.php; that is the right thing to
     * sign with. A store without one gets a generated secret of its own rather
     * than falling back to anything public like the site address, which would
     * make the signature guessable.
     */
    private static function signingSalt(): string
    {
        if (self::$salt !== null) return self::$salt;

        $salt = '';
        if (defined('WK_ROOT') && is_file(WK_ROOT . '/config/config.php')) {
            $config = require WK_ROOT . '/config/config.php';
            $salt = is_array($config) ? (string) ($config['salt'] ?? '') : '';
        }

        if ($salt === '') {
            try {
                $salt = (string) Database::setting('system', 'unsubscribe_salt', '');
                if ($salt === '') {
                    $salt = bin2hex(random_bytes(32));
                    Database::query(
                        "INSERT INTO wk_settings (setting_group,setting_key,setting_value)
                         VALUES('system','unsubscribe_salt',?)
                         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                        [$salt]
                    );
                }
            } catch (\Throwable $e) {
                $salt = 'whisker-unsubscribe';
            }
        }

        return self::$salt = $salt;
    }

    /** Signed so an unsubscribe link cannot be used to enumerate addresses. */
    public static function unsubscribeToken(string $email): string
    {
        return substr(hash_hmac('sha256', strtolower(trim($email)), self::signingSalt()), 0, 32);
    }

    public static function verifyUnsubscribe(string $email, string $token): bool
    {
        return hash_equals(self::unsubscribeToken($email), trim($token));
    }

    // ── Conversion ───────────────────────────────────────────────────────

    /**
     * Record that a cart turned into an order. Called at checkout, so the
     * shop can see whether the reminders are earning their keep.
     */
    public static function markRecovered(string $sessionId, int $orderId): void
    {
        try {
            Database::query(
                "UPDATE wk_carts
                    SET recovered_at = NOW(), recovered_order_id = ?
                  WHERE session_id = ?
                    AND reminder_count > 0
                    AND recovered_at IS NULL",
                [$orderId, $sessionId]
            );
        } catch (\Exception $e) {
            // Columns arrive with a migration.
        }
    }

    /**
     * @return array{sent:int, recovered:int, value:float, rate:float}
     */
    public static function stats(): array
    {
        $empty = ['sent' => 0, 'recovered' => 0, 'value' => 0.0, 'rate' => 0.0];
        try {
            $row = Database::fetch(
                "SELECT
                    COUNT(CASE WHEN reminder_count > 0 THEN 1 END) AS sent,
                    COUNT(recovered_order_id) AS recovered
                   FROM wk_carts"
            );
            $value = (float) Database::fetchValue(
                "SELECT COALESCE(SUM(o.total), 0) FROM wk_orders o
                   JOIN wk_carts c ON c.recovered_order_id = o.id"
            );
        } catch (\Exception $e) {
            return $empty;
        }

        $sent = (int) ($row['sent'] ?? 0);
        $recovered = (int) ($row['recovered'] ?? 0);

        return [
            'sent'      => $sent,
            'recovered' => $recovered,
            'value'     => $value,
            'rate'      => $sent > 0 ? round($recovered / $sent * 100, 1) : 0.0,
        ];
    }

    // ── Sending ──────────────────────────────────────────────────────────

    /**
     * Carts due their next reminder.
     *
     * A cart is due when the time since it was abandoned has passed the
     * schedule entry for the number of reminders it has already had.
     */
    public static function due(int $limit = 25): array
    {
        $schedule = self::schedule();
        if (!$schedule) return [];

        $clauses = [];
        $params = [];
        foreach ($schedule as $i => $minutes) {
            $clauses[] = "(c.reminder_count = ? AND c.abandoned_at <= DATE_SUB(NOW(), INTERVAL ? MINUTE))";
            $params[] = $i;
            $params[] = $minutes;
        }

        try {
            return Database::fetchAll(
                "SELECT c.*, cu.first_name, cu.last_name, cu.email AS customer_email
                   FROM wk_carts c
                   LEFT JOIN wk_customers cu ON cu.id = c.customer_id
                  WHERE c.status = 'abandoned'
                    AND c.recovered_at IS NULL
                    AND c.abandoned_at IS NOT NULL
                    AND COALESCE(c.email, cu.email) IS NOT NULL
                    AND EXISTS (SELECT 1 FROM wk_cart_items ci WHERE ci.cart_id = c.id)
                    AND (" . implode(' OR ', $clauses) . ")
                  ORDER BY c.abandoned_at ASC
                  LIMIT " . max(1, min(100, $limit)),
                $params
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Send one cart its next reminder.
     *
     * @return array{sent:bool, reason:string}
     */
    public static function sendReminder(array $cart): array
    {
        $email = trim((string) ($cart['email'] ?: ($cart['customer_email'] ?? '')));
        if ($email === '') return ['sent' => false, 'reason' => 'no email address'];
        if (self::isSuppressed($email)) return ['sent' => false, 'reason' => 'unsubscribed'];

        $cartId = (int) $cart['id'];
        $items = self::items($cartId);
        if (!$items) return ['sent' => false, 'reason' => 'cart is empty'];

        $currency = Database::setting('general', 'currency_symbol') ?: '₹';
        $store    = Database::setting('general', 'site_name') ?: 'Our store';
        $name     = trim(($cart['first_name'] ?? '') . ' ' . ($cart['last_name'] ?? '')) ?: 'there';

        $total = 0.0;
        foreach ($items as $item) $total += $item['unit_price'] * $item['quantity'];

        $sent = EmailService::sendFromTemplate('abandoned-cart', $email, [
            '{{customer_name}}'   => $name,
            '{{customer_email}}'  => $email,
            '{{store_name}}'      => $store,
            '{{store_url}}'       => View::url(''),
            '{{cart_items_html}}' => self::itemsHtml($items, $currency),
            '{{cart_total}}'      => $currency . number_format($total, 2),
            '{{cart_url}}'        => self::recoveryUrl($cartId),
            '{{coupon_block}}'    => self::couponBlock(),
            '{{unsubscribe_url}}' => self::unsubscribeUrl($email),
            '{{currency_symbol}}' => $currency,
        ]);

        if ($sent) {
            try {
                Database::query(
                    "UPDATE wk_carts SET reminder_sent_at=NOW(), reminder_count=reminder_count+1 WHERE id=?",
                    [$cartId]
                );
            } catch (\Exception $e) {}
        }

        return ['sent' => $sent, 'reason' => $sent ? 'sent' : 'mail failed'];
    }

    /**
     * Send whatever is due, at most once every few minutes.
     *
     * Called from storefront page loads rather than a cron, because a shop on
     * shared hosting may have no cron at all — and the storefront is visited
     * far more often than the admin dashboard, which is where the low stock
     * alert already hangs off.
     *
     * @return array{swept:bool, abandoned:int, sent:int}
     */
    public static function sweep(bool $force = false): array
    {
        $idle = ['swept' => false, 'abandoned' => 0, 'sent' => 0];
        if (!self::enabled()) return $idle;

        if (!$force) {
            $last = (int) Database::setting('system_cache', 'last_cart_sweep', '0');
            if ($last && (time() - $last) < self::SWEEP_INTERVAL) return $idle;
        }

        try {
            Database::query(
                "INSERT INTO wk_settings (setting_group,setting_key,setting_value) VALUES('system_cache','last_cart_sweep',?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                [(string) time()]
            );
        } catch (\Exception $e) {
            return $idle;
        }

        $abandoned = self::markAbandoned();

        $sent = 0;
        foreach (self::due(10) as $cart) {
            if (self::sendReminder($cart)['sent']) $sent++;
        }

        return ['swept' => true, 'abandoned' => $abandoned, 'sent' => $sent];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public static function items(int $cartId): array
    {
        try {
            return Database::fetchAll(
                "SELECT ci.*, p.name, vc.label AS variant_label,
                        (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
                   FROM wk_cart_items ci
                   JOIN wk_products p ON p.id = ci.product_id
                   LEFT JOIN wk_variant_combos vc ON vc.id = ci.variant_combo_id
                  WHERE ci.cart_id = ?",
                [$cartId]
            );
        } catch (\Exception $e) {
            return Database::fetchAll(
                "SELECT ci.*, p.name FROM wk_cart_items ci
                   JOIN wk_products p ON p.id = ci.product_id WHERE ci.cart_id = ?",
                [$cartId]
            );
        }
    }

    public static function itemsHtml(array $items, string $currency): string
    {
        $html = '<table style="width:100%;font-size:14px;border-collapse:collapse">';
        foreach ($items as $item) {
            $line = $item['unit_price'] * $item['quantity'];
            $img = !empty($item['image'])
                ? '<img src="' . View::url('storage/uploads/products/' . $item['image']) . '" style="width:48px;height:48px;object-fit:cover;border-radius:6px" alt="">'
                : '<div style="width:48px;height:48px;background:#faf8f6;border-radius:6px"></div>';
            $variant = !empty($item['variant_label'])
                ? '<div style="font-size:12px;color:#8b5cf6;font-weight:700">' . View::e($item['variant_label']) . '</div>' : '';

            $html .= '<tr><td style="padding:12px 0;border-bottom:1px solid #e8e5df">'
                  . '<div style="display:flex;align-items:center;gap:10px">' . $img
                  . '<div><div style="font-weight:700">' . View::e($item['name']) . '</div>' . $variant
                  . '<div style="font-size:12px;color:#6b7280">Qty: ' . (int) $item['quantity'] . ' &times; '
                  . $currency . number_format((float) $item['unit_price'], 2) . '</div></div></div></td>'
                  . '<td style="text-align:right;font-family:monospace;font-weight:700;vertical-align:top;padding-top:16px">'
                  . $currency . number_format($line, 2) . '</td></tr>';
        }
        return $html . '</table>';
    }

    /** The coupon block, or nothing when the shop has not set one. */
    public static function couponBlock(): string
    {
        $code = self::couponCode();
        if ($code === '') return '';

        return '<div style="background:#ede9fe;border:2px dashed #8b5cf6;border-radius:10px;padding:18px;text-align:center;margin-top:22px">'
             . '<div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#8b5cf6">A little nudge</div>'
             . '<div style="font-size:24px;font-weight:900;font-family:monospace;margin:6px 0;color:#1e1b2e">' . View::e($code) . '</div>'
             . '<div style="font-size:13px;color:#6b7280">Use this code at checkout</div></div>';
    }
}
