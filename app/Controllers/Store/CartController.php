<?php
namespace App\Controllers\Store;
use Core\{Request, Response, Session, Database};

class CartController
{
    /**
     * The cart as a page.
     *
     * The drawer is quicker for a glance, but it cannot be linked to, shared,
     * or returned to with the back button, and a recovery email has to send
     * somebody somewhere. This is that somewhere.
     */
    public function show(Request $request, array $params = []): void
    {
        $cart  = $this->getCart();
        $items = $this->getItems($cart['id']);
        $subtotal = array_reduce($items, fn($s, $i) => $s + ($i['unit_price'] * $i['quantity']), 0);

        \Core\View::render('store/cart', [
            'pageTitle'    => 'Your Cart',
            'items'        => $items,
            'subtotal'     => (float) $subtotal,
            'freeShipping' => self::freeShippingProgress((float) $subtotal),
            'alsoLike'     => self::alsoLike($items),
        ], 'store/layouts/main');
    }

    /** The same cart, as data, for the drawer and the counter. */
    public function data(Request $request, array $params = []): void
    {
        $cart = $this->getCart();
        $items = $this->getItems($cart['id']);
        $subtotal = array_reduce($items, fn($s,$i) => $s + ($i['unit_price'] * $i['quantity']), 0);

        // Pre-format prices server-side, honoring the storefront currency
        // switcher the same way product/shop pages do. The drawer JS used to
        // render amounts with a hardcoded ₹ regardless of the configured
        // currency — formatting here keeps symbols out of the JS entirely.
        $baseCurrency    = \App\Services\CurrencyService::baseCurrency();
        $displayCurrency = Session::get('wk_display_currency') ?: $baseCurrency;
        $fmt = function (float $amount) use ($baseCurrency, $displayCurrency): string {
            if ($displayCurrency !== $baseCurrency) {
                $converted = \App\Services\CurrencyService::convert($amount, $baseCurrency, $displayCurrency);
                return \App\Services\CurrencyService::format($converted, $displayCurrency);
            }
            return \App\Services\CurrencyService::baseSymbol() . number_format($amount, 2);
        };
        foreach ($items as &$item) {
            $item['line_total_formatted'] = $fmt((float)$item['unit_price'] * (int)$item['quantity']);
        }
        unset($item);

        Response::json([
            'success'=>true, 'items'=>$items,
            'count'=>array_sum(array_column($items,'quantity')),
            'subtotal'=>$subtotal,
            'subtotal_formatted'=>$fmt((float)$subtotal),
        ]);
    }

    public function add(Request $request, array $params = []): void
    {
        $result = self::addLine(
            $this->getCart()['id'],
            (int) $request->input('product_id'),
            max(1, (int) ($request->input('quantity') ?? 1)),
            (int) ($request->input('variant_combo_id') ?? 0)
        );

        Response::json(
            ['success' => $result['ok'], 'message' => $result['message']],
            $result['ok'] ? 200 : $result['status']
        );
    }

    /**
     * Put something in a cart, at today's price.
     *
     * Shared by the add button and by reordering, so stock and pricing are
     * decided in one place: a second implementation would eventually disagree
     * with this one about what an item costs.
     *
     * @return array{ok:bool,message:string,status:int}
     */
    public static function addLine(int $cartId, int $productId, int $quantity, int $comboId = 0): array
    {
        $fail = fn(string $why, int $status = 400): array
            => ['ok' => false, 'message' => $why, 'status' => $status];

        $product = Database::fetch(
            "SELECT id, name, price, sale_price, stock_quantity FROM wk_products WHERE id=? AND is_active=1",
            [$productId]
        );
        if (!$product) return $fail('Product not found', 404);

        $unitPrice      = $product['sale_price'] ?: $product['price'];
        $stockAvailable = $product['stock_quantity'];

        if ($comboId) {
            $combo = Database::fetch(
                "SELECT * FROM wk_variant_combos WHERE id=? AND product_id=? AND is_active=1",
                [$comboId, $productId]
            );
            if (!$combo) return $fail('Variant not available');
            if ($combo['price_override']) $unitPrice = (float) $combo['price_override'];
            $stockAvailable = $combo['stock_quantity'];
        }

        if ($stockAvailable <= 0) return $fail('Out of stock');

        $existing = Database::fetch(
            "SELECT id,quantity FROM wk_cart_items WHERE cart_id=? AND product_id=? AND COALESCE(variant_combo_id,0)=?",
            [$cartId, $productId, $comboId]
        );

        $wanted = ($existing['quantity'] ?? 0) + $quantity;
        if ($wanted > $stockAvailable) return $fail('Only ' . $stockAvailable . ' available');

        if ($existing) {
            Database::update('wk_cart_items', ['quantity'=>$wanted, 'unit_price'=>$unitPrice], 'id=?', [$existing['id']]);
        } else {
            Database::insert('wk_cart_items', [
                'cart_id'          => $cartId,
                'product_id'       => $productId,
                'quantity'         => $quantity,
                'unit_price'       => $unitPrice,
                'variant_combo_id' => $comboId ?: null,
            ]);
        }

        return ['ok' => true, 'message' => 'Added to cart', 'status' => 200];
    }

    /** The cart this session should be writing to, for callers outside it. */
    public static function currentCartId(): int
    {
        return (int) (new self())->getCart()['id'];
    }

    public function update(Request $request, array $params = []): void
    {
        $itemId = (int)$request->input('item_id');
        $quantity = max(0, (int)$request->input('quantity'));
        $cart = $this->getCart();

        if ($quantity === 0) {
            Database::delete('wk_cart_items', 'id=? AND cart_id=?', [$itemId, $cart['id']]);
        } else {
            // Check stock availability before updating
            $item = Database::fetch("SELECT product_id, variant_combo_id FROM wk_cart_items WHERE id=? AND cart_id=?", [$itemId, $cart['id']]);
            if ($item) {
                $comboId = $item['variant_combo_id'] ?? 0;
                if ($comboId) {
                    $stock = (int)Database::fetchValue("SELECT stock_quantity FROM wk_variant_combos WHERE id=?", [$comboId]);
                } else {
                    $stock = (int)Database::fetchValue("SELECT stock_quantity FROM wk_products WHERE id=?", [$item['product_id']]);
                }
                if ($quantity > $stock) {
                    Response::json(['success' => false, 'message' => "Only {$stock} available"], 400);
                    return;
                }
            }
            Database::update('wk_cart_items', ['quantity' => $quantity], 'id=? AND cart_id=?', [$itemId, $cart['id']]);
        }
        Response::json(['success' => true]);
    }

    public function remove(Request $request, array $params = []): void
    {
        $itemId = (int)$request->input('item_id');
        $cart = $this->getCart();
        Database::delete('wk_cart_items', 'id=? AND cart_id=?', [$itemId, $cart['id']]);
        Response::json(['success'=>true]);
    }

    public function applyCoupon(Request $request, array $params = []): void
    {
        // Rate limit: 10 coupon attempts per session per hour
        if (!\Core\RateLimiter::attempt('coupon', \Core\Session::cartId(), 10, 3600)) {
            Response::json(['success' => false, 'message' => 'Too many attempts. Try again later.'], 429);
            return;
        }

        $code = strtoupper(trim($request->input('coupon_code') ?? ''));
        if ($code === '') {
            Response::json(['success' => false, 'message' => 'Enter a coupon code.'], 400);
            return;
        }

        $coupon = Database::fetch(
            "SELECT * FROM wk_coupons
               WHERE code = ?
                 AND is_active = 1
                 AND (starts_at IS NULL OR starts_at <= NOW())
                 AND (expires_at IS NULL OR expires_at > NOW())
                 AND (usage_limit IS NULL OR used_count < usage_limit)",
            [$code]
        );
        if (!$coupon) {
            Response::json(['success' => false, 'message' => 'Invalid or expired coupon'], 400);
            return;
        }

        // Enforce minimum order amount at apply time. The final authoritative
        // check happens in CheckoutController::process — this is a friendly
        // upfront rejection so the customer sees the rule.
        $minOrder = (float)($coupon['min_order_amount'] ?? 0);
        if ($minOrder > 0) {
            $cart = $this->getCart();
            $items = $this->getItems($cart['id']);
            $subtotal = array_reduce(
                $items,
                fn($sum, $i) => $sum + ((float)$i['unit_price'] * (int)$i['quantity']),
                0.0
            );
            if ($subtotal + 0.0001 < $minOrder) {
                $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '';
                Response::json([
                    'success' => false,
                    'message' => sprintf('This coupon requires a minimum order of %s%s.', $currency, number_format($minOrder, 2)),
                ], 400);
                return;
            }
        }

        Session::set('wk_coupon', $coupon);
        Response::json([
            'success' => true,
            'coupon' => [
                'code'  => $coupon['code'],
                'type'  => $coupon['type'],
                'value' => $coupon['value'],
            ],
        ]);
    }

    public function clear(Request $request, array $params = []): void
    {
        $cart = $this->getCart();
        Database::delete('wk_cart_items', 'cart_id=?', [$cart['id']]);
        Response::json(['success'=>true]);
    }

    private function getCart(): array
    {
        $sid = Session::cartId();
        $custId = Session::customerId();

        $cart = Database::fetch("SELECT id FROM wk_carts WHERE session_id=? AND status='active'", [$sid]);

        // Signed in, the customer's own cart is the one that counts: theirs
        // follows them between devices and outlives the session cookie, where a
        // cart keyed only to the browser would not.
        if ($custId) {
            $theirs = self::customerCart($custId, $sid);
            if ($theirs) {
                $cart = $cart
                    ? self::mergeCarts((int) $theirs['id'], (int) $cart['id'], $sid)
                    : self::adoptCart((int) $theirs['id'], $sid);
            }
        }

        if (!$cart) {
            $id = Database::insert('wk_carts', [
                'session_id'=>$sid, 'customer_id'=>$custId,
                'status'=>'active', 'expires_at'=>date('Y-m-d H:i:s', strtotime('+7 days')),
            ]);
            $cart = ['id'=>$id];
        } else {
            // Link to customer if logged in but cart wasn't linked
            $custId = Session::customerId();
            if ($custId) {
                try {
                    Database::query("UPDATE wk_carts SET customer_id=? WHERE id=? AND customer_id IS NULL", [$custId, $cart['id']]);
                    $email = Database::fetchValue("SELECT email FROM wk_customers WHERE id=?", [$custId]);
                    if ($email) Database::query("UPDATE wk_carts SET email=? WHERE id=? AND email IS NULL", [$email, $cart['id']]);
                } catch (\Exception $e) {}
            }
        }
        return $cart;
    }

    private function getItems(int $cartId): array
    {
        try {
            return Database::fetchAll(
                "SELECT ci.id, ci.product_id, ci.quantity, ci.unit_price, p.name, p.slug,
                        ci.variant_combo_id,
                        vc.label AS variant_label,
                        COALESCE(
                            (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND alt_text=CONCAT('variant_opt_', SUBSTRING_INDEX(vc.option_ids,',',1)) LIMIT 1),
                            (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1)
                        ) AS image
                 FROM wk_cart_items ci
                 JOIN wk_products p ON p.id=ci.product_id
                 LEFT JOIN wk_variant_combos vc ON vc.id=ci.variant_combo_id
                 WHERE ci.cart_id=?", [$cartId]
            );
        } catch (\Exception $e) {
            // Fallback if variant columns don't exist
            return Database::fetchAll(
                "SELECT ci.id, ci.product_id, ci.quantity, ci.unit_price, p.name, p.slug,
                        (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
                 FROM wk_cart_items ci JOIN wk_products p ON p.id=ci.product_id WHERE ci.cart_id=?", [$cartId]
            );
        }
    }

    /**
     * How far off free delivery this basket is.
     *
     * The threshold belongs to a shipping zone, and no destination has been
     * chosen yet on the cart page, so the shop's own country is used — which
     * is where most orders go. Once an address is entered at checkout the
     * real zone applies.
     *
     * @return array{threshold:float,remaining:float,qualified:bool}|null
     *         null when the shop has no threshold, so nothing is promised
     */
    private static function freeShippingProgress(float $subtotal): ?array
    {
        try {
            $zone = \App\Services\ShippingZoneService::forCountry(
                \App\Services\CountryService::storeCountry()
            );
        } catch (\Throwable $e) {
            return null;
        }

        $threshold = (float) ($zone['free_threshold'] ?? 0);
        if ($threshold <= 0) return null;

        return [
            'threshold' => $threshold,
            'remaining' => max(0, $threshold - $subtotal),
            'qualified' => $subtotal >= $threshold,
        ];
    }

    /**
     * A few things to add before checking out, from the same categories as
     * what is already in the basket. Nothing already in it, and nothing out
     * of stock — offering either wastes the space.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function alsoLike(array $items, int $limit = 4): array
    {
        if (!$items) return [];

        $productIds = array_values(array_unique(array_column($items, 'product_id')));
        if (!$productIds) return [];

        $in = implode(',', array_fill(0, count($productIds), '?'));
        try {
            return Database::fetchAll(
                "SELECT p.id, p.name, p.slug, p.price, p.sale_price,
                        (SELECT image_path FROM wk_product_images
                          WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS image
                   FROM wk_products p
                  WHERE p.is_active = 1
                    AND p.stock_quantity > 0
                    AND p.id NOT IN ({$in})
                    AND p.category_id IN (
                        SELECT category_id FROM wk_products WHERE id IN ({$in}) AND category_id IS NOT NULL
                    )
                  ORDER BY p.is_featured DESC, RAND()
                  LIMIT {$limit}",
                array_merge($productIds, $productIds)
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * This customer's own active cart, from any session but this one.
     *
     * The newest is the one they were last using; older ones are left where
     * they are rather than being stitched together, which would resurrect
     * things they had moved on from.
     */
    private static function customerCart(int $customerId, string $exceptSession): ?array
    {
        try {
            $row = Database::fetch(
                "SELECT id FROM wk_carts
                  WHERE customer_id = ? AND status = 'active' AND session_id <> ?
                  ORDER BY updated_at DESC, id DESC LIMIT 1",
                [$customerId, $exceptSession]
            );
            return $row ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /** Bring an existing cart into this session, rather than copying it. */
    private static function adoptCart(int $cartId, string $sid): array
    {
        try {
            Database::query("UPDATE wk_carts SET session_id = ? WHERE id = ?", [$sid, $cartId]);
        } catch (\Exception $e) {}
        return ['id' => $cartId];
    }

    /**
     * Two live carts for one person: what they just put in this browser, and
     * what was already waiting from before.
     *
     * Everything ends up in the cart this session is already using. A line for
     * something already there raises its quantity rather than appearing twice,
     * which is what somebody adding the same thing on two devices means.
     */
    private static function mergeCarts(int $fromId, int $intoId, string $sid): array
    {
        if ($fromId === $intoId) return ['id' => $intoId];

        try {
            $incoming = Database::fetchAll(
                "SELECT product_id, variant_combo_id, quantity, unit_price
                   FROM wk_cart_items WHERE cart_id = ?", [$fromId]
            );

            foreach ($incoming as $line) {
                $existing = Database::fetch(
                    "SELECT id, quantity FROM wk_cart_items
                      WHERE cart_id = ? AND product_id = ?
                        AND (variant_combo_id <=> ?) LIMIT 1",
                    [$intoId, $line['product_id'], $line['variant_combo_id']]
                );

                if ($existing) {
                    Database::query(
                        "UPDATE wk_cart_items SET quantity = quantity + ? WHERE id = ?",
                        [(int) $line['quantity'], $existing['id']]
                    );
                } else {
                    Database::insert('wk_cart_items', [
                        'cart_id'          => $intoId,
                        'product_id'       => $line['product_id'],
                        'variant_combo_id' => $line['variant_combo_id'],
                        'quantity'         => $line['quantity'],
                        'unit_price'       => $line['unit_price'],
                    ]);
                }
            }

            // The cart it came from is spent, not abandoned — nobody walked
            // away from it, so the recovery emails must leave it alone.
            Database::query("UPDATE wk_carts SET status = 'merged' WHERE id = ?", [$fromId]);
        } catch (\Exception $e) {
            // A merge that cannot complete must not cost anybody the basket
            // they are looking at.
        }

        return ['id' => $intoId];
    }
}
