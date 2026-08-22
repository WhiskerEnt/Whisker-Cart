<?php
namespace App\Services;

use Core\Database;
use Core\Session;

/**
 * WHISKER — Product reviews and ratings
 *
 * Who may write a review is the store owner's decision, set under
 * Settings → Reviews:
 *
 *   anyone     no purchase needed
 *   purchased  must have paid for the product
 *   received   must have paid for it and had it delivered
 *
 * The check runs on the way in, so a review that could not be earned is
 * never stored — an unapproved row still counts as a record of who said what
 * about a product, and the honest answer is to refuse it at the door.
 */
class ReviewService
{
    public const POLICY_ANYONE    = 'anyone';
    public const POLICY_PURCHASED = 'purchased';
    public const POLICY_RECEIVED  = 'received';

    /** Orders that count as paid, whatever happened afterwards. */
    private const PAID = ['captured', 'authorized', 'partially_refunded', 'refunded'];

    public static function enabled(): bool
    {
        return Database::setting('reviews', 'reviews_enabled', '1') === '1';
    }

    public static function policy(): string
    {
        $p = (string) Database::setting('reviews', 'review_policy', self::POLICY_PURCHASED);
        return in_array($p, [self::POLICY_ANYONE, self::POLICY_PURCHASED, self::POLICY_RECEIVED], true)
            ? $p
            : self::POLICY_PURCHASED;
    }

    public static function autoApprove(): bool
    {
        return Database::setting('reviews', 'auto_approve', '0') === '1';
    }

    public static function showOnCards(): bool
    {
        return Database::setting('reviews', 'show_on_cards', '1') === '1';
    }

    /**
     * The order that entitles this email to review this product, if any.
     *
     * Matches on the order's own email as well as the linked customer, so a
     * guest who checked out without an account still gets credit for it.
     */
    public static function qualifyingOrder(int $productId, string $email, ?int $customerId = null): ?array
    {
        $email = strtolower(trim($email));
        if ($email === '' && !$customerId) return null;

        $needsDelivery = self::policy() === self::POLICY_RECEIVED;
        $paid = "'" . implode("','", self::PAID) . "'";

        $owner = [];
        $params = [$productId];
        if ($email !== '')  { $owner[] = 'LOWER(o.customer_email) = ?'; $params[] = $email; }
        if ($customerId)    { $owner[] = 'o.customer_id = ?';           $params[] = $customerId; }
        if (!$owner) return null;

        $sql = "SELECT o.id, o.order_number, o.status, o.created_at
                  FROM wk_orders o
                  JOIN wk_order_items oi ON oi.order_id = o.id
                 WHERE oi.product_id = ?
                   AND o.payment_status IN ({$paid})
                   AND (" . implode(' OR ', $owner) . ")";

        if ($needsDelivery) {
            $sql .= " AND o.status = 'delivered'";
        }
        $sql .= " ORDER BY o.created_at DESC LIMIT 1";

        try {
            return Database::fetch($sql, $params) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * One review per person per product, whether they are identified by email
     * or by the account they are signed in to.
     */
    public static function hasReviewed(int $productId, string $email, ?int $customerId = null): bool
    {
        $email = strtolower(trim($email));
        if ($email === '' && !$customerId) return false;

        $sql = "SELECT 1 FROM wk_reviews WHERE product_id = ? AND (";
        $params = [$productId];
        $clauses = [];
        if ($email !== '') { $clauses[] = 'LOWER(author_email) = ?'; $params[] = $email; }
        if ($customerId)   { $clauses[] = 'customer_id = ?';         $params[] = $customerId; }
        $sql .= implode(' OR ', $clauses) . ') LIMIT 1';

        return (bool) Database::fetchValue($sql, $params);
    }

    /**
     * Whether this person may write a review right now.
     *
     * @return array{allowed:bool, reason:string, order:?array}
     */
    public static function eligibility(int $productId, string $email, ?int $customerId = null): array
    {
        if (!self::enabled()) {
            return ['allowed' => false, 'reason' => 'Reviews are turned off for this store.', 'order' => null];
        }

        $email = strtolower(trim($email));
        if (self::hasReviewed($productId, $email, $customerId)) {
            return ['allowed' => false, 'reason' => 'You have already reviewed this product.', 'order' => null];
        }

        if (self::policy() === self::POLICY_ANYONE) {
            return ['allowed' => true, 'reason' => '', 'order' => null];
        }

        // Only ask for an email when there is no signed-in account to go on.
        // Some older orders carry no email at all, and telling the customer to
        // "enter the email you ordered with" makes no sense on their own order
        // page, where there is no field to enter it in.
        if ($email === '' && !$customerId) {
            return ['allowed' => false, 'reason' => 'Enter the email address you ordered with.', 'order' => null];
        }

        $order = self::qualifyingOrder($productId, $email, $customerId);
        if ($order) {
            return ['allowed' => true, 'reason' => '', 'order' => $order];
        }

        return [
            'allowed' => false,
            'order'   => null,
            'reason'  => self::policy() === self::POLICY_RECEIVED
                ? 'Only customers who have received this product can review it. If you ordered it and it has arrived, use the email address on the order.'
                : 'Only customers who have bought this product can review it. Use the email address on your order.',
        ];
    }

    /**
     * Store a review.
     *
     * @return array{success:bool, message:string, status:?string}
     */
    public static function submit(int $productId, array $input, ?int $customerId = null): array
    {
        $name   = trim((string) ($input['name'] ?? ''));
        $email  = strtolower(trim((string) ($input['email'] ?? '')));
        $rating = (int) ($input['rating'] ?? 0);
        $title  = trim((string) ($input['title'] ?? ''));
        $body   = trim((string) ($input['body'] ?? ''));

        if ($rating < 1 || $rating > 5) {
            return ['success' => false, 'message' => 'Choose a rating from 1 to 5 stars.', 'status' => null];
        }
        if ($name === '' || mb_strlen($name) > 80) {
            return ['success' => false, 'message' => 'Enter your name.', 'status' => null];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Enter a valid email address.', 'status' => null];
        }
        if (mb_strlen($body) > 4000) {
            return ['success' => false, 'message' => 'Please keep your review under 4000 characters.', 'status' => null];
        }

        $check = self::eligibility($productId, $email, $customerId);
        if (!$check['allowed']) {
            return ['success' => false, 'message' => $check['reason'], 'status' => null];
        }

        $status = self::autoApprove() ? 'approved' : 'pending';

        Database::insert('wk_reviews', [
            'product_id'           => $productId,
            'customer_id'          => $customerId,
            'order_id'             => $check['order']['id'] ?? null,
            'author_name'          => mb_substr($name, 0, 80),
            'author_email'         => mb_substr($email, 0, 190),
            'rating'               => $rating,
            'title'                => $title !== '' ? mb_substr($title, 0, 140) : null,
            'body'                 => $body !== '' ? $body : null,
            'status'               => $status,
            'is_verified_purchase' => $check['order'] ? 1 : 0,
            'ip_address'           => $_SERVER['REMOTE_ADDR'] ?? null,
        ]);

        return [
            'success' => true,
            'status'  => $status,
            'message' => $status === 'approved'
                ? 'Thank you — your review is now on the page.'
                : 'Thank you. Your review has been sent for approval and will appear once it is checked.',
        ];
    }

    /** Approved reviews for a product, newest first. */
    public static function forProduct(int $productId, int $limit = 50): array
    {
        try {
            return Database::fetchAll(
                "SELECT * FROM wk_reviews
                  WHERE product_id = ? AND status = 'approved'
                  ORDER BY is_verified_purchase DESC, created_at DESC
                  LIMIT " . max(1, min(200, $limit)),
                [$productId]
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Rating summary for a product: count, average and the star breakdown.
     *
     * @return array{count:int, average:float, breakdown:array<int,int>}
     */
    public static function stats(int $productId): array
    {
        $empty = ['count' => 0, 'average' => 0.0, 'breakdown' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
        try {
            $rows = Database::fetchAll(
                "SELECT rating, COUNT(*) AS n FROM wk_reviews
                  WHERE product_id = ? AND status = 'approved' GROUP BY rating",
                [$productId]
            );
        } catch (\Exception $e) {
            return $empty;
        }

        $out = $empty;
        $sum = 0;
        foreach ($rows as $row) {
            $star = (int) $row['rating'];
            $n = (int) $row['n'];
            if ($star < 1 || $star > 5) continue;
            $out['breakdown'][$star] = $n;
            $out['count'] += $n;
            $sum += $star * $n;
        }
        $out['average'] = $out['count'] > 0 ? round($sum / $out['count'], 2) : 0.0;
        return $out;
    }

    /**
     * Summaries for many products at once, for listing pages.
     *
     * @param int[] $productIds
     * @return array<int,array{count:int, average:float}>
     */
    public static function statsForMany(array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$ids) return [];

        $in = implode(',', array_fill(0, count($ids), '?'));
        try {
            $rows = Database::fetchAll(
                "SELECT product_id, COUNT(*) AS n, AVG(rating) AS avg_rating
                   FROM wk_reviews
                  WHERE status = 'approved' AND product_id IN ({$in})
                  GROUP BY product_id",
                $ids
            );
        } catch (\Exception $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['product_id']] = [
                'count'   => (int) $row['n'],
                'average' => round((float) $row['avg_rating'], 2),
            ];
        }
        return $out;
    }

    /** @var array<int,array{count:int,average:float}>|null */
    private static ?array $cardStats = null;

    /**
     * Rating for a product card.
     *
     * Listing pages render up to a couple of dozen cards, and the views that
     * do so are reached from several controllers. Rather than thread the
     * numbers through each one, the whole set of approved ratings is loaded
     * once per request and answered from memory after that — reviews are few
     * next to products, so this is one small grouped query.
     */
    public static function cardStats(int $productId): ?array
    {
        if (!self::showOnCards() || !self::enabled()) return null;

        if (self::$cardStats === null) {
            self::$cardStats = [];
            try {
                $rows = Database::fetchAll(
                    "SELECT product_id, COUNT(*) AS n, AVG(rating) AS avg_rating
                       FROM wk_reviews WHERE status = 'approved' GROUP BY product_id"
                );
                foreach ($rows as $row) {
                    self::$cardStats[(int) $row['product_id']] = [
                        'count'   => (int) $row['n'],
                        'average' => round((float) $row['avg_rating'], 2),
                    ];
                }
            } catch (\Exception $e) {
                // Un-migrated store: no ratings to show.
            }
        }

        return self::$cardStats[$productId] ?? null;
    }

    /** Star markup, with the last star clipped so fractions render exactly. */
    public static function starMarkup(float $average, int $size = 12): string
    {
        $out = '<span class="wk-stars" style="font-size:' . $size . 'px" aria-hidden="true">';
        for ($i = 1; $i <= 5; $i++) {
            $pct = max(0, min(1, $average - ($i - 1))) * 100;
            $out .= '<span class="wk-star"><span class="wk-star-off">&#9733;</span>'
                  . '<span class="wk-star-on" style="width:' . round($pct, 1) . '%">&#9733;</span></span>';
        }
        return $out . '</span>';
    }

    /** Ready-made card rating line, or an empty string when there is nothing to show. */
    public static function cardRatingHtml(int $productId): string
    {
        $stats = self::cardStats($productId);
        if (!$stats || $stats['count'] < 1) return '';
        return '<div class="wk-card-rating">' . self::starMarkup((float) $stats['average'])
             . '<span>(' . $stats['count'] . ')</span></div>';
    }

    // ── Admin ────────────────────────────────────────────────────────────

    public static function pendingCount(): int
    {
        try {
            return (int) Database::fetchValue("SELECT COUNT(*) FROM wk_reviews WHERE status = 'pending'");
        } catch (\Exception $e) {
            return 0;
        }
    }

    public static function setStatus(int $reviewId, string $status): bool
    {
        if (!in_array($status, ['pending', 'approved', 'rejected'], true)) return false;
        return Database::update('wk_reviews', ['status' => $status], 'id = ?', [$reviewId]) > 0;
    }

    public static function reply(int $reviewId, string $reply): bool
    {
        $reply = trim($reply);
        return Database::update('wk_reviews', [
            'admin_reply'      => $reply !== '' ? mb_substr($reply, 0, 2000) : null,
            'admin_replied_at' => $reply !== '' ? date('Y-m-d H:i:s') : null,
        ], 'id = ?', [$reviewId]) >= 0;
    }

    public static function delete(int $reviewId): bool
    {
        return Database::delete('wk_reviews', 'id = ?', [$reviewId]) > 0;
    }

    /** Renders a star row as text, used where markup is inconvenient. */
    public static function starText(float $average): string
    {
        $full = (int) floor($average + 0.25);
        return str_repeat('★', max(0, min(5, $full))) . str_repeat('☆', max(0, 5 - $full));
    }
}
