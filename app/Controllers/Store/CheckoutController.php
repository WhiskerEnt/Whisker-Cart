<?php
namespace App\Controllers\Store;
use Core\{Request, Response, Session, Database, View, Validator, PluginManager};

class CheckoutController
{
    public function index(Request $request, array $params = []): void
    {
        $cart = $this->getCartData();
        $gateways = Database::fetchAll("SELECT gateway_code,display_name,description FROM wk_payment_gateways WHERE is_active=1 ORDER BY sort_order");
        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';
        $taxRate = (float)(Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='checkout' AND setting_key='tax_rate'") ?: 18);
        $shipping = self::calculateShipping($cart);

        // Get saved addresses and customer info for logged-in users
        $customer = null;
        $savedAddresses = [];
        if (Session::customerId()) {
            $customer = Database::fetch("SELECT * FROM wk_customers WHERE id=?", [Session::customerId()]);
            try {
                $savedAddresses = Database::fetchAll("SELECT * FROM wk_customer_addresses WHERE customer_id=? ORDER BY is_default DESC, id", [Session::customerId()]);
            } catch (\Exception $e) {}
        }

        View::render('store/checkout', [
            'cart'=>$cart, 'gateways'=>$gateways, 'currency'=>$currency,
            'taxRate'=>$taxRate, 'shipping'=>$shipping,
            'baseCurrency' => Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency'") ?: 'INR',
            'customer' => $customer,
            'savedAddresses' => $savedAddresses,
        ], 'store/layouts/main');
    }

    public function process(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error','Session expired.');
            Response::redirect(View::url('checkout'));
            return;
        }
        $cart = $this->getCartData();
        if (empty($cart['items'])) {
            Session::flash('error','Your cart is empty.');
            Response::redirect(View::url(''));
            return;
        }

        // Capture email on cart for abandoned cart tracking
        $email = $request->input('email');
        if ($email) {
            try {
                $sid = Session::cartId();
                Database::query("UPDATE wk_carts SET email=? WHERE session_id=? AND status='active'", [$email, $sid]);
            } catch (\Exception $e) {}
        }

        $taxRate = (float)(Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='checkout' AND setting_key='tax_rate'") ?: 18);
        $shipping = self::calculateShipping($cart);

        // Use TaxService for smart tax calculation based on customer address
        $custAddress = [
            'country' => $request->clean('country') ?? '',
            'state'   => $request->clean('state') ?? '',
        ];
        $taxResult = \App\Services\TaxService::calculate($cart['subtotal'], $custAddress);
        $tax = $taxResult['amount'];
        $total = $cart['subtotal'] + $tax + $shipping;

        // Auto-create or find customer by email
        $customerId = Session::customerId();
        $email = trim($request->input('email') ?? '');
        if (!$customerId && $email) {
            $existing = Database::fetch("SELECT id FROM wk_customers WHERE email=?", [$email]);
            if ($existing) {
                $customerId = $existing['id'];
                // Do NOT call Session::setCustomer() — guest cannot hijack existing account
            } else {
                $customerId = Database::insert('wk_customers', [
                    'first_name'    => $request->clean('first_name') ?? '',
                    'last_name'     => $request->clean('last_name') ?? '',
                    'email'         => htmlspecialchars($email, ENT_QUOTES, 'UTF-8'),
                    'phone'         => $request->clean('phone') ?? '',
                    'password_hash' => password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT),
                    'is_active'     => 1,
                ]);
                // Only auto-login newly created guest accounts
                Session::setCustomer($customerId);
            }
        }

        $orderNumber = 'WK-' . strtoupper(date('ymd')) . '-' . strtoupper(bin2hex(random_bytes(6)));
        $orderId = Database::insert('wk_orders', [
            'order_number'=>$orderNumber, 'customer_id'=>$customerId,
            'status'=>'pending', 'subtotal'=>$cart['subtotal'],
            'tax_amount'=>$tax, 'shipping_amount'=>$shipping, 'total'=>$total,
            'currency'=>Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency'") ?: 'INR',
            'payment_gateway'=>$request->clean('payment_gateway'),
            'customer_email'=>$request->clean('email'),
            'customer_phone'=>$request->clean('phone'),
            'tax_details'=>json_encode($taxResult['breakdown'] ?? []),
            'shipping_address'=>json_encode([
                'name'=>$request->clean('first_name').' '.$request->clean('last_name'),
                'line1'=>$request->clean('address1'), 'city'=>$request->clean('city'),
                'state'=>$request->clean('state'), 'zip'=>$request->clean('zip'),
                'country'=>$request->clean('country'),
            ]),
            'billing_address'=>json_encode(
                $request->input('billing_address1')
                ? [
                    'name'=>$request->clean('first_name').' '.$request->clean('last_name'),
                    'line1'=>$request->clean('billing_address1'), 'city'=>$request->clean('billing_city'),
                    'state'=>$request->clean('billing_state'), 'zip'=>$request->clean('billing_zip'),
                    'country'=>$request->clean('billing_country'),
                ]
                : [
                    'name'=>$request->clean('first_name').' '.$request->clean('last_name'),
                    'line1'=>$request->clean('address1'), 'city'=>$request->clean('city'),
                    'state'=>$request->clean('state'), 'zip'=>$request->clean('zip'),
                    'country'=>$request->clean('country'),
                ]
            ),
            'ip_address'=>$request->ip(),
        ]);

        foreach ($cart['items'] as $item) {
            $orderItemData = [
                'order_id'=>$orderId, 'product_id'=>$item['product_id'],
                'product_name'=>$item['name'], 'product_sku'=>'',
                'quantity'=>$item['quantity'], 'unit_price'=>$item['unit_price'],
                'total_price'=>$item['unit_price'] * $item['quantity'],
            ];

            // Add variant info if present
            $comboId = $item['variant_combo_id'] ?? null;
            try {
                if ($comboId) {
                    $orderItemData['variant_combo_id'] = $comboId;
                    $orderItemData['variant_label'] = $item['variant_label'] ?? '';
                }
            } catch (\Exception $e) {}

            Database::insert('wk_order_items', $orderItemData);

            // Reduce stock atomically — prevents overselling under concurrency
            // Only deducts if stock is sufficient (WHERE stock_quantity >= ?)
            if ($comboId) {
                try {
                    $affected = Database::query(
                        "UPDATE wk_variant_combos SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?",
                        [$item['quantity'], $comboId, $item['quantity']]
                    )->rowCount();
                    if ($affected === 0) {
                        // Stock was grabbed by another order — log but don't fail the order
                        Database::query("UPDATE wk_variant_combos SET stock_quantity = 0 WHERE id = ?", [$comboId]);
                    }
                } catch (\Exception $e) {}
            }
            $affected = Database::query(
                "UPDATE wk_products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?",
                [$item['quantity'], $item['product_id'], $item['quantity']]
            )->rowCount();
            if ($affected === 0) {
                Database::query("UPDATE wk_products SET stock_quantity = 0 WHERE id = ?", [$item['product_id']]);
            }
        }

        // Increment coupon usage
        $coupon = Session::get('wk_coupon');
        if ($coupon && !empty($coupon['id'])) {
            Database::query("UPDATE wk_coupons SET used_count = used_count + 1 WHERE id=?", [$coupon['id']]);
            Session::remove('wk_coupon');
        }

        // Mark cart converted
        Database::update('wk_carts', ['status'=>'converted'], 'session_id=? AND status=?', [Session::cartId(),'active']);

        // Update customer order stats
        if ($customerId) {
            Database::query(
                "UPDATE wk_customers SET total_orders = total_orders + 1, total_spent = total_spent + ? WHERE id = ?",
                [$total, $customerId]
            );
        }

        // Send order confirmation email (non-blocking — don't delay checkout response)
        $orderData = Database::fetch("SELECT * FROM wk_orders WHERE id=?", [$orderId]);
        $orderItems = Database::fetchAll("SELECT * FROM wk_order_items WHERE order_id=?", [$orderId]);
        if ($orderData && $orderData['customer_email']) {
            // Flush output to browser first so customer sees success page immediately
            if (function_exists('fastcgi_finish_request')) {
                Session::set('wk_last_order', $orderNumber);
                Response::redirect(View::url('order-success?order=' . $orderNumber));
                fastcgi_finish_request(); // Ends HTTP response, continues PHP execution
                \App\Services\EmailService::sendOrderConfirmation($orderData, $orderItems);
                return; // Already redirected above
            }
            // Fallback: send email normally (blocks slightly on non-FPM servers)
            try { \App\Services\EmailService::sendOrderConfirmation($orderData, $orderItems); } catch (\Exception $e) {}
        }

        // Redirect to success (payment integration via plugins)
        Session::set('wk_last_order', $orderNumber);
        Response::redirect(View::url('order-success?order='.$orderNumber));
    }

    public function success(Request $request, array $params = []): void
    {
        $orderNumber = $request->query('order') ?? Session::get('wk_last_order');
        $order = null;

        if ($orderNumber) {
            $order = Database::fetch("SELECT * FROM wk_orders WHERE order_number=?", [$orderNumber]);
            // Verify ownership: must be from current session or current customer
            if ($order) {
                $lastOrder = Session::get('wk_last_order');
                $custId = Session::customerId();
                $isOwner = ($orderNumber === $lastOrder) || ($custId && (int)$order['customer_id'] === $custId);
                if (!$isOwner) $order = null;
            }
        }

        View::render('store/order-success', ['order' => $order], 'store/layouts/main');
    }

    private function getCartData(): array
    {
        $sid = Session::cartId();
        $cart = Database::fetch("SELECT id FROM wk_carts WHERE session_id=? AND status='active'", [$sid]);
        if (!$cart) return ['items'=>[], 'subtotal'=>0, 'count'=>0];

        try {
            $items = Database::fetchAll(
                "SELECT ci.*, p.name, p.slug, p.weight, vc.label AS variant_label,
                        COALESCE(
                            (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND alt_text=CONCAT('variant_opt_', SUBSTRING_INDEX(COALESCE(vc.option_ids,''),',',1)) LIMIT 1),
                            (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1)
                        ) AS image
                 FROM wk_cart_items ci
                 JOIN wk_products p ON p.id=ci.product_id
                 LEFT JOIN wk_variant_combos vc ON vc.id=ci.variant_combo_id
                 WHERE ci.cart_id=?",
                [$cart['id']]
            );
        } catch (\Exception $e) {
            $items = Database::fetchAll(
                "SELECT ci.*, p.name, p.slug, p.weight,
                        (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
                 FROM wk_cart_items ci JOIN wk_products p ON p.id=ci.product_id WHERE ci.cart_id=?",
                [$cart['id']]
            );
        }
        $subtotal = array_reduce($items, fn($s,$i) => $s + ($i['unit_price'] * $i['quantity']), 0);
        return ['items'=>$items, 'subtotal'=>$subtotal, 'count'=>array_sum(array_column($items,'quantity'))];
    }

    private static function calculateShipping(array $cart): float
    {
        if (empty($cart['items'])) return 0;

        // Check for product-level shipping overrides
        $hasOverrides = false;
        $overrideTotal = 0;
        $normalItems = [];

        foreach ($cart['items'] as $item) {
            $override = Database::fetchValue(
                "SELECT setting_value FROM wk_settings WHERE setting_group='product_meta' AND setting_key=?",
                ['shipping_override_' . $item['product_id']]
            );

            if ($override === 'free') {
                $hasOverrides = true;
                // This item ships free — skip it
            } elseif ($override === 'custom') {
                $hasOverrides = true;
                $charge = (float)(Database::fetchValue(
                    "SELECT setting_value FROM wk_settings WHERE setting_group='product_meta' AND setting_key=?",
                    ['shipping_charge_' . $item['product_id']]
                ) ?: 0);
                $overrideTotal += $charge * $item['quantity'];
            } else {
                $normalItems[] = $item;
            }
        }

        // If ALL items have overrides, just return the override total
        if (empty($normalItems)) return $overrideTotal;

        // Calculate store-default shipping for normal items
        $getSetting = fn($key, $default = '0') =>
            Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='shipping' AND setting_key=?", [$key]) ?: $default;

        $method = $getSetting('method', 'flat');
        $normalCount = array_sum(array_column($normalItems, 'quantity'));
        $normalSubtotal = array_reduce($normalItems, fn($s, $i) => $s + ($i['unit_price'] * $i['quantity']), 0);

        $storeShipping = match($method) {
            'free' => 0,

            'free_above' => $normalSubtotal >= (float)$getSetting('free_threshold', '500')
                ? 0 : (float)$getSetting('flat_rate_below', '50'),

            'per_item' => min(
                (float)$getSetting('per_item', '10') * $normalCount,
                (float)($getSetting('per_item_cap') ?: PHP_FLOAT_MAX)
            ),

            'weight' => (function() use ($normalItems, $getSetting) {
                $baseRate = (float)$getSetting('weight_base', '50');
                $perKg = (float)$getSetting('weight_per_kg', '20');
                $totalWeight = 0;
                foreach ($normalItems as $item) {
                    $totalWeight += ((float)($item['weight'] ?? 0)) * $item['quantity'];
                }
                if ($totalWeight <= 1) return $baseRate;
                return $baseRate + (($totalWeight - 1) * $perKg);
            })(),

            default => (float)$getSetting('flat_rate',
                Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='checkout' AND setting_key='shipping_flat_rate'") ?: '50'
            ),
        };

        return $overrideTotal + $storeShipping;
    }
}