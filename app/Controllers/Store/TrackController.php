<?php
namespace App\Controllers\Store;

use Core\{Request, View, Database, Response, RateLimiter};

/**
 * Public order tracking for shoppers who checked out as a guest.
 *
 * A lookup needs the order number AND the email it was placed with. The email
 * alone never lists anything, and a wrong pair is answered exactly like an
 * order that does not exist, so the form cannot be used to discover which
 * order numbers or addresses are real.
 */
class TrackController
{
    /** Same wording for every unsuccessful lookup. */
    private const NOT_FOUND = 'We could not find an order with that number and email address. Please check both and try again.';

    private const MAX_ATTEMPTS = 8;
    private const WINDOW       = 900; // 15 minutes

    public function show(Request $request, array $params = []): void
    {
        $this->render();
    }

    public function lookup(Request $request, array $params = []): void
    {
        $number = strtoupper(trim((string)$request->input('order_number')));
        $email  = strtolower(trim((string)$request->input('email')));

        if ($number === '' || $email === '') {
            $this->render('Enter both your order number and the email address you used.', $number, $email);
            return;
        }

        if (!RateLimiter::attempt('order_track', $request->ip(), self::MAX_ATTEMPTS, self::WINDOW)) {
            $wait = max(1, (int)ceil(RateLimiter::remainingSeconds('order_track', $request->ip(), self::WINDOW) / 60));
            $this->render("Too many attempts. Please try again in {$wait} minutes.", $number, $email);
            return;
        }

        $order = self::findOrder($number, $email);
        if (!$order) {
            $this->render(self::NOT_FOUND, $number, $email);
            return;
        }

        RateLimiter::reset('order_track', $request->ip());

        $items = Database::fetchAll(
            "SELECT product_name, quantity, unit_price, total_price, variant_label
               FROM wk_order_items WHERE order_id = ?",
            [$order['id']]
        );

        $this->render(null, $number, $email, $order, $items);
    }

    /**
     * Resolve an order from a number + email pair.
     *
     * Both must belong to the same order. Kept separate so the pairing rule can
     * be tested directly.
     */
    public static function findOrder(string $number, string $email): ?array
    {
        $number = strtoupper(trim($number));
        $email  = strtolower(trim($email));
        if ($number === '' || $email === '') return null;

        try {
            return Database::fetch(
                "SELECT id, order_number, status, payment_status, total, currency,
                        created_at, notes, shipping_address, delivery_method, customer_email
                   FROM wk_orders
                  WHERE order_number = ? AND LOWER(customer_email) = ?",
                [$number, $email]
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    private function render(
        ?string $error = null,
        string $number = '',
        string $email = '',
        ?array $order = null,
        array $items = []
    ): void {
        View::render('store/track', [
            'pageTitle'   => 'Track Your Order',
            'trackError'  => $error,
            'trackNumber' => $number,
            'trackEmail'  => $email,
            'order'       => $order,
            'items'       => $items,
            'seoMeta'     => '',
        ], 'store/layouts/main');
    }
}
