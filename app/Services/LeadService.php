<?php
namespace App\Services;

use Core\Database;
use Core\Session;

/**
 * WHISKER — Capturing a way to reach a visitor who is leaving
 *
 * A shopper heading for the exit with a basket is the one worth asking. The
 * prompt takes an email address or a phone number, optionally offers a
 * discount code, and — the point of the exercise — attaches what it gets to
 * their cart, so the recovery emails have somewhere to go.
 *
 * Off until the shopkeeper turns it on.
 */
class LeadService
{
    public static function enabled(): bool
    {
        return Database::setting('leads', 'lead_capture_enabled', '0') === '1';
    }

    /** 'email', 'phone' or 'both' */
    public static function fields(): string
    {
        $f = (string) Database::setting('leads', 'lead_capture_fields', 'email');
        return in_array($f, ['email', 'phone', 'both'], true) ? $f : 'email';
    }

    /** 'cart' — only when they have something in the basket — or 'always'. */
    public static function when(): string
    {
        return Database::setting('leads', 'lead_capture_when', 'cart') === 'always' ? 'always' : 'cart';
    }

    public static function title(): string
    {
        return (string) Database::setting('leads', 'lead_capture_title', 'Before you go');
    }

    public static function text(): string
    {
        return (string) Database::setting('leads', 'lead_capture_text', '');
    }

    public static function couponCode(): string
    {
        return trim((string) Database::setting('leads', 'lead_capture_coupon', ''));
    }

    /**
     * The coupon to show, if the shop set one and it is still worth having.
     *
     * An expired or used-up code is worse than no code, so it is checked
     * rather than promised blindly.
     */
    public static function usableCoupon(): ?array
    {
        $code = self::couponCode();
        if ($code === '') return null;

        try {
            $coupon = Database::fetch(
                "SELECT code, type, value, min_order_amount, starts_at, expires_at,
                        usage_limit, used_count, is_active
                   FROM wk_coupons WHERE code = ?",
                [$code]
            );
        } catch (\Exception $e) {
            return null;
        }
        if (!$coupon || !$coupon['is_active']) return null;

        if (!empty($coupon['starts_at']) && strtotime($coupon['starts_at']) > time()) return null;
        if (!empty($coupon['expires_at']) && strtotime($coupon['expires_at']) < time()) return null;
        if (!empty($coupon['usage_limit']) && (int) $coupon['used_count'] >= (int) $coupon['usage_limit']) return null;

        return $coupon;
    }

    /**
     * Record a lead and, where we can, attach it to the cart it came from so
     * the recovery emails can reach them.
     *
     * @return array{success:bool, message:string, coupon:?string}
     */
    public static function capture(string $email, string $phone, ?int $cartId): array
    {
        $email = strtolower(trim($email));
        $phone = trim($phone);

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'That email address does not look right.', 'coupon' => null];
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);
        if ($phone !== '' && strlen($digits) < 6) {
            return ['success' => false, 'message' => 'That phone number does not look right.', 'coupon' => null];
        }

        if ($email === '' && $phone === '') {
            return ['success' => false, 'message' => 'Leave us one way to reach you.', 'coupon' => null];
        }

        $coupon = self::usableCoupon();
        $code = $coupon['code'] ?? null;

        try {
            Database::query(
                "INSERT INTO wk_leads (email, phone, source, cart_id, coupon_code, ip_address)
                 VALUES (?,?,?,?,?,?)
                 ON DUPLICATE KEY UPDATE
                    phone = COALESCE(VALUES(phone), phone),
                    cart_id = COALESCE(VALUES(cart_id), cart_id),
                    coupon_code = COALESCE(VALUES(coupon_code), coupon_code)",
                [
                    $email !== '' ? $email : null,
                    $phone !== '' ? $phone : null,
                    'exit_intent',
                    $cartId,
                    $code,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]
            );
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Something went wrong. Please try again.', 'coupon' => null];
        }

        // The whole point: give the recovery emails an address to use.
        if ($cartId) {
            try {
                Database::query(
                    "UPDATE wk_carts SET email = COALESCE(email, ?), phone = COALESCE(phone, ?) WHERE id = ?",
                    [$email !== '' ? $email : null, $phone !== '' ? $phone : null, $cartId]
                );
            } catch (\Exception $e) {
                // The phone column arrives with a migration.
                try {
                    Database::query("UPDATE wk_carts SET email = COALESCE(email, ?) WHERE id = ?",
                        [$email !== '' ? $email : null, $cartId]);
                } catch (\Exception $e2) {}
            }
        }

        return [
            'success' => true,
            'coupon'  => $code,
            'message' => $code
                ? 'Thank you. Here is your code — it is waiting at checkout.'
                : 'Thank you. We have saved your basket.',
        ];
    }

    public static function currentCartId(): ?int
    {
        try {
            $id = Database::fetchValue(
                "SELECT id FROM wk_carts WHERE session_id=? AND status='active'",
                [Session::cartId()]
            );
            return $id ? (int) $id : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function cartHasItems(): bool
    {
        $cartId = self::currentCartId();
        if (!$cartId) return false;
        try {
            return (bool) Database::fetchValue("SELECT 1 FROM wk_cart_items WHERE cart_id=? LIMIT 1", [$cartId]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /** Whether the prompt should be rendered on this page at all. */
    public static function shouldOffer(): bool
    {
        if (!self::enabled()) return false;
        return self::when() === 'always' || self::cartHasItems();
    }
}
