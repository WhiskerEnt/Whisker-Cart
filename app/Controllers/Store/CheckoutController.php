<?php
namespace App\Controllers\Store;
use Core\{Request, Response, Session, Database, View, Validator, PluginManager};

class CheckoutController
{
    public function index(Request $request, array $params = []): void
    {
        // Release stock from expired unpaid orders (15 min window)
        try {
            $expiredOrders = Database::fetchAll(
                "SELECT id FROM wk_orders WHERE status IN ('pending','payment_failed') AND payment_status != 'captured' AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE) LIMIT 10"
            );
            foreach ($expiredOrders as $expired) {
                // Fall back to the always-present columns so the sweep still
                // runs on a database awaiting the v1.3.3 migration.
                try {
                    $items = Database::fetchAll("SELECT product_id, quantity, variant_combo_id FROM wk_order_items WHERE order_id=?", [$expired['id']]);
                } catch (\Exception $e) {
                    $items = Database::fetchAll("SELECT product_id, quantity FROM wk_order_items WHERE order_id=?", [$expired['id']]);
                }
                foreach ($items as $item) {
                    Database::query("UPDATE wk_products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$item['quantity'], $item['product_id']]);
                    if ($item['variant_combo_id'] ?? null) {
                        try { Database::query("UPDATE wk_variant_combos SET stock_quantity = stock_quantity + ? WHERE id = ?", [$item['quantity'], $item['variant_combo_id']]); } catch (\Exception $e) {}
                    }
                }
                Database::update('wk_orders', ['status' => 'cancelled', 'payment_status' => 'expired'], 'id=?', [$expired['id']]);
            }
        } catch (\Exception $e) {}

        $cart = $this->getCartData();
        $gateways = Database::fetchAll("SELECT gateway_code,display_name,description FROM wk_payment_gateways WHERE is_active=1 ORDER BY sort_order");
        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

        // Pickup / locker delivery availability for the delivery-method chooser
        $pickupLocations = self::activePickupLocations();
        $pickupEnabled = !empty($pickupLocations)
            && (Database::setting('shipping', 'pickup_enabled') === '1');

        // Tax depends on the customer's address — at this point we don't know
        // it yet. Compute the rest of the totals with tax=0 and tax_known=false;
        // the view shows "Calculated at checkout" until the customer fills in
        // their country/state and the JS recomputes via /checkout/calculate.
        $totals = self::computeTotals($cart, null, Session::get('wk_coupon'));

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
            'pageTitle'       => 'Checkout',
            'cart'            => $cart,
            'gateways'        => $gateways,
            'currency'        => $currency,
            'totals'          => $totals,
            'baseCurrency'    => Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency'") ?: 'INR',
            'customer'        => $customer,
            'savedAddresses'  => $savedAddresses,
            'pickupEnabled'   => $pickupEnabled,
            'pickupLocations' => $pickupLocations,
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

        // Enforce the store's minimum order amount (Settings → Checkout).
        $minOrder = (float)(Database::setting('checkout', 'min_order') ?: 0);
        if ($minOrder > 0 && (float)$cart['subtotal'] < $minOrder) {
            Session::flash('error', 'Minimum order amount is ' . View::price($minOrder) . '. Add a few more items to check out.');
            Response::redirect(View::url('checkout'));
            return;
        }

        // Enforce the admin's guest-checkout toggle.
        if (!Session::customerId() && Database::setting('checkout', 'guest_checkout') === '0') {
            Session::flash('error', 'Please sign in or create an account to check out.');
            Response::redirect(View::url('account/login'));
            return;
        }

        // Capture email on cart for abandoned-cart tracking. Validate and
        // lowercase once here; the same form is stored in every table, with
        // escaping applied at output.
        $emailRaw = trim((string)$request->input('email'));
        $email = ($emailRaw !== '' && filter_var($emailRaw, FILTER_VALIDATE_EMAIL))
            ? strtolower($emailRaw)
            : '';
        if ($email !== '') {
            try {
                $sid = Session::cartId();
                Database::query("UPDATE wk_carts SET email=? WHERE session_id=? AND status='active'", [$email, $sid]);
            } catch (\Exception $e) {}
        }

        // Delivery method: home shipping (default) or pickup point / locker.
        $deliveryMethod = $request->input('delivery_method') === 'pickup' ? 'pickup' : 'shipping';
        $pickupLocation = null;
        if ($deliveryMethod === 'pickup') {
            $pickupLocation = self::pickupLocationIfAllowed((int)$request->input('pickup_location_id'));
            if (!$pickupLocation) {
                Session::flash('error', 'Please choose a valid pickup point.');
                Response::redirect(View::url('checkout'));
                return;
            }
        }

        $custAddress = [
            'country' => $request->clean('country') ?? '',
            'state'   => $request->clean('state') ?? '',
        ];
        // Authoritative totals computed server-side. The session coupon is
        // re-validated against the DB inside computeTotals(), so a stale
        // session cannot grant a discount on an expired coupon. For pickup
        // delivery, computeTotals uses the pickup point as the destination
        // (fee + destination-based tax).
        $totals = self::computeTotals($cart, $custAddress, Session::get('wk_coupon'), $pickupLocation);
        $tax        = $totals['tax'];
        $shipping   = $totals['shipping'];
        $discount   = $totals['discount'];
        $total      = $totals['total'];

        // A shopper can edit the form, so the posted country is checked against
        // the same list the dropdown was built from.
        $shipCountry = strtoupper(trim((string) $request->input('country')));
        if (!\App\Services\CountryService::canShipTo($shipCountry)) {
            Session::flash('error', 'We do not ship to that country yet. Please choose another delivery address.');
            Response::redirect(View::url('checkout'));
            return;
        }

        // Auto-create or find customer by email.
        // A session can outlive the customer record it points at (deleted in the
        // admin, restored database). Drop the stale id rather than letting the
        // order fail on a foreign key.
        $customerId = Session::customerId();
        if ($customerId && !Database::fetchValue("SELECT id FROM wk_customers WHERE id=?", [$customerId])) {
            $customerId = null;
        }
        if (!$customerId && $email !== '') {
            $existing = Database::fetch("SELECT id FROM wk_customers WHERE email=?", [$email]);
            if ($existing) {
                $customerId = $existing['id'];
                // Do NOT call Session::setCustomer() — guest cannot hijack existing account
            } else {
                // Guest checkout stores the sentinel '*' (which
                // password_verify always rejects) instead of a usable hash;
                // the customer sets a real password via the forgot-password
                // flow. Keeps the NOT NULL column satisfied.
                $customerId = Database::insert('wk_customers', [
                    'first_name'    => $request->clean('first_name') ?? '',
                    'last_name'     => $request->clean('last_name') ?? '',
                    'email'         => $email, // validated lowercase; escape at output, not at storage
                    'phone'         => $request->clean('phone') ?? '',
                    'password_hash' => '*',
                    'is_active'     => 1,
                ]);
                // Only auto-login newly created guest accounts
                Session::setCustomer($customerId);
            }
        }

        $orderNumber = 'WK-' . strtoupper(date('ymd')) . '-' . strtoupper(bin2hex(random_bytes(6)));

        // ── Atomic order-creation + stock reservation ──
        // One transaction covers the order/items insert and the stock
        // deduction: if two customers race for the last unit, the loser's
        // conditional UPDATE affects 0 rows, we throw, and the whole order
        // rolls back cleanly.
        //
        // Gateway init is deliberately kept OUTSIDE the transaction so we
        // never hold InnoDB row locks across a network call (which can take
        // seconds and would block every other checkout).
        $orderId = null;
        $stockError = null;
        try {
            Database::transaction(function () use (
                $cart, $orderNumber, $customerId, $tax, $shipping, $discount, $total,
                $totals, $request, $email, $deliveryMethod, $pickupLocation, &$orderId
            ) {
                $customerName = $request->clean('first_name').' '.$request->clean('last_name');

                // For pickup delivery the "shipping address" is a snapshot of
                // the chosen pickup point — order history must survive the
                // location being edited or deleted later.
                $shippingAddress = $deliveryMethod === 'pickup' && $pickupLocation
                    ? [
                        'name'    => $customerName,
                        'line1'   => $pickupLocation['name'] . ($pickupLocation['carrier'] ? ' (' . $pickupLocation['carrier'] . ')' : ''),
                        'line2'   => $pickupLocation['address_line1'],
                        'city'    => $pickupLocation['city'],
                        'state'   => $pickupLocation['state'],
                        'zip'     => $pickupLocation['zip'],
                        'country' => $pickupLocation['country'],
                        'pickup'  => true,
                        'pickup_location_id' => (int)$pickupLocation['id'],
                        'opening_hours' => $pickupLocation['opening_hours'],
                    ]
                    : [
                        'name'=>$customerName,
                        'line1'=>$request->clean('address1'), 'line2'=>$request->clean('address2') ?? '',
                        'city'=>$request->clean('city'),
                        'state'=>$request->clean('state'), 'zip'=>$request->clean('zip'),
                        'country'=>$request->clean('country'),
                    ];

                $orderId = Database::insert('wk_orders', [
                    'order_number'=>$orderNumber, 'customer_id'=>$customerId,
                    'status'=>'pending', 'subtotal'=>$cart['subtotal'],
                    'tax_amount'=>$tax, 'shipping_amount'=>$shipping,
                    'discount_amount'=>$discount, 'total'=>$total,
                    'currency'=>Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency'") ?: 'INR',
                    'payment_gateway'=>$request->clean('payment_gateway'),
                    'customer_email'=>$email, // validated lowercase form
                    'customer_phone'=>$request->clean('phone'),
                    'tax_details'=>json_encode($totals['tax_breakdown'] ?? []),
                    'shipping_address'=>json_encode($shippingAddress),
                    'billing_address'=>json_encode(
                        $request->input('billing_address1')
                        ? [
                            'name'=>$customerName,
                            'line1'=>$request->clean('billing_address1'), 'line2'=>$request->clean('billing_address2') ?? '',
                            'city'=>$request->clean('billing_city'),
                            'state'=>$request->clean('billing_state'), 'zip'=>$request->clean('billing_zip'),
                            'country'=>$request->clean('billing_country'),
                        ]
                        : $shippingAddress
                    ),
                    'ip_address'=>$request->ip(),
                ]);

                // Delivery columns are added by the v1.3.2 migration; keep the
                // order insert working on not-yet-migrated databases.
                try {
                    Database::update('wk_orders', [
                        'delivery_method'    => $deliveryMethod,
                        'pickup_location_id' => $pickupLocation['id'] ?? null,
                    ], 'id=?', [$orderId]);
                } catch (\Exception $e) {}

                foreach ($cart['items'] as $item) {
                    $orderItemData = [
                        'order_id'=>$orderId, 'product_id'=>$item['product_id'],
                        'product_name'=>$item['name'], 'product_sku'=>'',
                        'quantity'=>$item['quantity'], 'unit_price'=>$item['unit_price'],
                        'total_price'=>$item['unit_price'] * $item['quantity'],
                    ];
                    $comboId = $item['variant_combo_id'] ?? null;
                    if ($comboId) {
                        $orderItemData['variant_combo_id'] = $comboId;
                        $orderItemData['variant_label'] = $item['variant_label'] ?? '';
                    }
                    // The variant columns arrive with the v1.3.3 migration. If a
                    // store is mid-update (files copied, migration pending),
                    // fall back to variant_info so the sale still completes.
                    try {
                        Database::insert('wk_order_items', $orderItemData);
                    } catch (\PDOException $e) {
                        if ($comboId && self::isUnknownColumnError($e)) {
                            unset($orderItemData['variant_combo_id'], $orderItemData['variant_label']);
                            $orderItemData['variant_info'] = $item['variant_label'] ?? '';
                            Database::insert('wk_order_items', $orderItemData);
                        } else {
                            throw $e;
                        }
                    }

                    // Atomic stock deduction. The conditional WHERE guarantees
                    // that only one of two concurrent orders can reserve the
                    // last units; the loser gets rowCount() === 0 and we
                    // abort by throwing, which rolls back the whole order.
                    if ($comboId) {
                        $affected = Database::query(
                            "UPDATE wk_variant_combos SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?",
                            [$item['quantity'], $comboId, $item['quantity']]
                        )->rowCount();
                        if ($affected === 0) {
                            throw new \RuntimeException('Out of stock: ' . ($item['name'] ?? 'item') . ' (variant)');
                        }
                    }
                    $affected = Database::query(
                        "UPDATE wk_products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?",
                        [$item['quantity'], $item['product_id'], $item['quantity']]
                    )->rowCount();
                    if ($affected === 0) {
                        throw new \RuntimeException('Out of stock: ' . ($item['name'] ?? 'item'));
                    }
                }
            });
        } catch (\PDOException $e) {
            // PDOException extends RuntimeException, so it must be caught first —
            // otherwise its message (table names, constraint names) is shown to
            // the shopper as if it were a friendly stock notice.
            error_log('Whisker checkout: ' . $e->getMessage());
            $stockError = 'Could not place order. Please try again.';
        } catch (\RuntimeException $e) {
            // Raised deliberately below for out-of-stock lines; safe to show.
            $stockError = $e->getMessage();
        } catch (\Throwable $e) {
            error_log('Whisker checkout: ' . $e->getMessage());
            $stockError = 'Could not place order. Please try again.';
        }

        if ($stockError) {
            Session::flash('error', $stockError);
            Response::redirect(View::url('checkout'));
            return;
        }

        $orderData = Database::fetch("SELECT * FROM wk_orders WHERE id=?", [$orderId]);
        $orderItems = Database::fetchAll("SELECT * FROM wk_order_items WHERE order_id=?", [$orderId]);

        // ── Initiate Payment Gateway ──
        // Order and stock are already reserved. If gateway init fails, the
        // order is marked payment_failed and the 15-minute expiry job in
        // index() will restore the stock.
        $gatewayCode = $request->clean('payment_gateway');
        $payResult = null;

        if ($gatewayCode) {
            try {
                $gateway = PluginManager::loadGateway($gatewayCode);
                if ($gateway) {
                    $payResult = $gateway->createOrder([
                        'id'           => $orderId,
                        'order_number' => $orderNumber,
                        'total'        => $total,
                        'currency'     => Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency'") ?: 'INR',
                    ]);

                    // Verify gateway returned something useful
                    $hasRedirect = !empty($payResult['session_url']) || !empty($payResult['form_url']) || !empty($payResult['invoice_url']) || !empty($payResult['gateway_order_id']);
                    if (!$hasRedirect || !empty($payResult['error'])) {
                        throw new \Exception('Gateway returned no payment URL');
                    }
                    // Note: each gateway plugin already calls logTransaction()
                    // during createOrder(), writing the gateway_order_id to
                    // wk_payment_transactions. That table is the source of
                    // truth verifyPayment() consults to confirm a client-
                    // submitted gateway_order_id genuinely belongs to the
                    // order (no extra column on wk_orders needed).
                } else {
                    throw new \Exception('Gateway not found: ' . $gatewayCode);
                }
            } catch (\Exception $e) {
                // Gateway init failed — mark order as payment_failed, let customer retry
                Database::update('wk_orders', [
                    'status' => 'payment_failed',
                    'payment_status' => 'failed',
                ], 'id=?', [$orderId]);
                Session::set('wk_last_order', $orderNumber);
                Session::flash('error', 'Payment could not be initiated. You have 15 minutes to retry before your order is released.');
                Response::redirect(View::url('order-success?order=' . $orderNumber));
                return;
            }
        }

        // Increment coupon usage atomically: the conditional WHERE cannot
        // push used_count past usage_limit under concurrent checkouts. If 0
        // rows are affected the limit was reached by a concurrent order —
        // record a system warning so ops can review.
        $coupon = Session::get('wk_coupon');
        if ($discount > 0 && $coupon && !empty($coupon['id'])) {
            $bumped = Database::query(
                "UPDATE wk_coupons SET used_count = used_count + 1
                  WHERE id = ?
                    AND (usage_limit IS NULL OR used_count < usage_limit)",
                [(int)$coupon['id']]
            )->rowCount();
            if ($bumped === 0) {
                // Race detected: another concurrent checkout hit the limit
                // first. The order has already been created with the
                // discount applied. Log for ops; do NOT fail the order.
                try {
                    Database::query(
                        "INSERT INTO wk_settings (setting_group, setting_key, setting_value)
                         VALUES ('system', ?, ?)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                        [
                            'coupon_race_' . $orderNumber,
                            json_encode([
                                'coupon_id' => (int)$coupon['id'],
                                'order_id'  => $orderId,
                                'when'      => date('c'),
                            ]),
                        ]
                    );
                } catch (\Exception $e) {}
            }
        }
        // Clear the session coupon regardless — the order has been placed,
        // a stale coupon must not carry to the next order.
        if ($coupon) {
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

        $orderData = Database::fetch("SELECT * FROM wk_orders WHERE id=?", [$orderId]);
        $orderItems = Database::fetchAll("SELECT * FROM wk_order_items WHERE order_id=?", [$orderId]);

        // ── Redirect to payment ──
        if ($payResult) {
            // Stripe: redirect to Stripe Checkout
            if (!empty($payResult['session_url'])) {
                Response::redirect($payResult['session_url']);
                return;
            }

            // CCAvenue: redirect with encrypted form
            if (!empty($payResult['form_url'])) {
                echo '<html><body><form id="ccav" method="POST" action="' . htmlspecialchars($payResult['form_url']) . '">';
                echo '<input type="hidden" name="encRequest" value="' . htmlspecialchars($payResult['encrypted_data']) . '">';
                echo '<input type="hidden" name="access_code" value="' . htmlspecialchars($payResult['access_code']) . '">';
                echo '</form><script>document.getElementById("ccav").submit();</script></body></html>';
                return;
            }

            // NOWPayments: redirect to crypto invoice
            if (!empty($payResult['invoice_url'])) {
                Response::redirect($payResult['invoice_url']);
                return;
            }

            // Razorpay: store data for modal on success page
            if (!empty($payResult['gateway_order_id'])) {
                Session::set('wk_payment_data', [
                    'gateway'          => $gatewayCode,
                    'gateway_order_id' => $payResult['gateway_order_id'],
                    'key_id'           => $payResult['key_id'] ?? '',
                    'amount'           => $payResult['amount'] ?? 0,
                    'currency'         => $payResult['currency']
                        ?? (Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency'") ?: 'INR'),
                    'order_number'     => $orderNumber,
                    'order_id'         => $orderId,
                    'email'            => $request->clean('email') ?? '',
                    'phone'            => $request->clean('phone') ?? '',
                    'name'             => trim($request->clean('first_name') . ' ' . $request->clean('last_name')),
                ]);
            }
        }

        // Send the confirmation on shutdown, so the shopper is redirected
        // immediately and a slow mail server never holds up the page.
        // Registering it (rather than calling it after the redirect) is what
        // makes it run at all: Response::redirect() ends the request, so any
        // statement written after it is unreachable.
        if ($orderData && $orderData['customer_email']) {
            register_shutdown_function(static function () use ($orderData, $orderItems) {
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                }
                try {
                    \App\Services\EmailService::sendOrderConfirmation($orderData, $orderItems);
                } catch (\Throwable $e) {
                    error_log('Whisker: order confirmation email failed — ' . $e->getMessage());
                }
            });
        }

        Session::set('wk_last_order', $orderNumber);
        Response::redirect(View::url('order-success?order='.$orderNumber));
    }

    /**
     * AJAX: Recompute checkout totals based on submitted address.
     *
     * Called by the checkout view's JS whenever country/state/zip changes.
     * Pure computation — no side effects, no DB writes. The browser's display
     * is updated from this response; the actual order is created by process()
     * which calls the same computeTotals() helper, so the two cannot diverge.
     */
    public function calculate(Request $request, array $params = []): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) $input = [];

        $cart = $this->getCartData();
        if (empty($cart['items'])) {
            Response::json([
                'subtotal'      => 0,
                'tax'           => 0,
                'tax_label'     => '',
                'tax_known'     => false,
                'shipping'      => 0,
                'discount'      => 0,
                'discount_code' => '',
                'total'         => 0,
            ]);
            return;
        }

        $country = isset($input['country']) ? trim((string)$input['country']) : '';
        $state   = isset($input['state'])   ? trim((string)$input['state'])   : '';
        $address = null;
        if ($country !== '' || $state !== '') {
            $address = ['country' => $country, 'state' => $state];
        }

        // Pickup delivery preview: fee + destination tax come from the
        // chosen pickup point, exactly as process() will compute them.
        $pickupLocation = null;
        if (($input['delivery_method'] ?? '') === 'pickup') {
            $pickupLocation = self::pickupLocationIfAllowed((int)($input['pickup_location_id'] ?? 0));
        }

        $totals = self::computeTotals($cart, $address, Session::get('wk_coupon'), $pickupLocation);

        // Strip internal-only field before sending to the client.
        unset($totals['tax_breakdown']);

        // Pre-format display strings on the server so the JS can update the
        // DOM byte-for-byte the way it would have rendered on a fresh page
        // load — same currency symbol, same number_format, same optional
        // display-currency suffix as the view's $showPrice helper.
        $symbol         = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';
        $baseCurrency   = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency'") ?: 'INR';
        $displayCurr    = (string)(Session::get('wk_display_currency') ?? $baseCurrency);
        $sameCurrency   = ($displayCurr === $baseCurrency);

        $fmt = static function (float $n) use ($symbol, $sameCurrency, $baseCurrency, $displayCurr): string {
            $base = $symbol . number_format($n, 2);
            if ($sameCurrency) return $base;
            $converted = \App\Services\CurrencyService::convert($n, $baseCurrency, $displayCurr);
            $tail = \App\Services\CurrencyService::format($converted, $displayCurr);
            return $base . ' <span style="font-size:12px;color:var(--wk-muted);font-weight:500">≈ ' . htmlspecialchars($tail, ENT_QUOTES, 'UTF-8') . '</span>';
        };

        $totals['formatted'] = [
            'subtotal' => $fmt($totals['subtotal']),
            'tax'      => $fmt($totals['tax']),
            'shipping' => $fmt($totals['shipping']),
            'discount' => $fmt($totals['discount']),
            'total'    => $fmt($totals['total']),
            // Plain (no conversion suffix) total for the pay button.
            'total_plain' => $symbol . number_format($totals['total'], 2),
        ];

        Response::json($totals);
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

        // The confirmation page shows a full summary, so the items come with it.
        $items = [];
        if ($order) {
            try {
                $items = Database::fetchAll(
                    "SELECT oi.product_name, oi.quantity, oi.unit_price, oi.total_price,
                            oi.variant_label, p.slug,
                            (SELECT image_path FROM wk_product_images
                              WHERE product_id = oi.product_id AND is_primary = 1 LIMIT 1) AS image
                       FROM wk_order_items oi
                       LEFT JOIN wk_products p ON p.id = oi.product_id
                      WHERE oi.order_id = ?",
                    [$order['id']]
                );
            } catch (\Exception $e) {
                $items = Database::fetchAll(
                    "SELECT product_name, quantity, unit_price, total_price
                       FROM wk_order_items WHERE order_id = ?",
                    [$order['id']]
                );
            }
        }

        // Work out which state the page is showing here, so the browser tab
        // title matches what the shopper sees.
        $payData = Session::get('wk_payment_data');
        if ($payData && $order && ($payData['order_number'] ?? null) !== ($order['order_number'] ?? null)) {
            $payData = null;
        }
        if (Session::get('wk_payment_data')) Session::remove('wk_payment_data');

        $needsPayment  = $order && $order['payment_status'] !== 'captured' && $payData && ($payData['gateway'] ?? '') === 'razorpay';
        $paymentFailed = $order && ($order['status'] === 'payment_failed' || $order['payment_status'] === 'failed');
        $retryMinutes  = $order ? max(0, (int)ceil(((strtotime($order['created_at']) + 900) - time()) / 60)) : 0;
        $retryExpired  = $retryMinutes <= 0 && ($paymentFailed || $needsPayment);

        if     ($retryExpired)  { $state = 'expired'; $title = 'Order expired';    $blurb = 'Payment was not completed in time, so the items have been released back into stock.'; }
        elseif ($paymentFailed) { $state = 'failed';  $title = 'Payment failed';   $blurb = 'Your order is still reserved. You can try paying again below.'; }
        elseif ($needsPayment)  { $state = 'pending'; $title = 'Complete payment'; $blurb = 'Your order is created and waiting for payment.'; }
        elseif ($order)         { $state = 'ok';      $title = 'Order confirmed';  $blurb = 'Thank you — we have your order and a confirmation email is on its way.'; }
        else                    { $state = 'ok';      $title = 'Thank you';        $blurb = 'Thank you for your order.'; }

        View::render('store/order-success', [
            'order'         => $order,
            'items'         => $items,
            'payData'       => $payData,
            'state'         => $state,
            'statusTitle'   => $title,
            'statusBlurb'   => $blurb,
            'needsPayment'  => $needsPayment,
            'paymentFailed' => $paymentFailed,
            'retryExpired'  => $retryExpired,
            'retryMinutes'  => $retryMinutes,
            'retryExpires'  => $order ? strtotime($order['created_at']) + 900 : 0,
            // Tab space is tight and truncates; the status is what identifies
            // the tab, and the store name is appended by the layout.
            'pageTitle'     => $title,
        ], 'store/layouts/main');
    }

    /**
     * AJAX: Verify Razorpay payment after client-side checkout
     */
    public function verifyPayment(Request $request, array $params = []): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $orderId = (int)($input['order_id'] ?? 0);

        if (!$orderId) {
            Response::json(['success' => false, 'message' => 'Missing order ID']);
            return;
        }

        // Get the gateway for this order
        $order = Database::fetch("SELECT payment_gateway, total, currency FROM wk_orders WHERE id=?", [$orderId]);
        if (!$order || !$order['payment_gateway']) {
            Response::json(['success' => false, 'message' => 'Order not found']);
            return;
        }

        // ── Ownership check ──
        // The client passes a razorpay_order_id (or equivalent) in the verify
        // payload; it must match the gateway_order_id recorded when the order
        // was created. session_id is Stripe's key for this value; the others
        // cover Razorpay and the generic case.
        $clientGatewayOrderId = (string)(
            $input['razorpay_order_id']
            ?? $input['gateway_order_id']
            ?? $input['session_id']
            ?? ''
        );
        if (!self::gatewayOrderIdMatches($orderId, (string)$order['payment_gateway'], $clientGatewayOrderId)) {
            Response::json(['success' => false, 'message' => 'Order/payment mismatch']);
            return;
        }

        try {
            $gateway = PluginManager::loadGateway($order['payment_gateway']);
            if (!$gateway) {
                Response::json(['success' => false, 'message' => 'Gateway not found']);
                return;
            }

            $result = $gateway->verifyPayment($input);
            if ($result['success']) {
                // Pass paidAmount through so BaseGateway::markOrderPaid can run
                // its amount-mismatch guard. If the gateway didn't surface an
                // amount in the verify result we fall back to the order total
                // — which is fine for signature-verified flows (the signature
                // itself proves Razorpay processed this exact order).
                $paidAmount = isset($result['amount'])
                    ? (float)$result['amount']
                    : (float)$order['total'];
                $gateway->markOrderPaid($orderId, $result['payment_id'] ?? '', $paidAmount);
                Response::json(['success' => true]);
            } else {
                Response::json(['success' => false, 'message' => 'Verification failed']);
            }
        } catch (\Exception $e) {
            Response::json(['success' => false, 'message' => 'Payment verification error']);
        }
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

    /**
     * Compute order totals authoritatively.
     *
     * Single source of truth used by index(), process(), and calculate(). The
     * server is always the authority — any client-side preview must round-trip
     * through this method before the order is created.
     *
     * @param array       $cart    Cart array from getCartData()
     * @param array|null  $address Customer address ['country' => 'IN', 'state' => 'KA', ...]
     *                             When null, tax is reported as 0 with no label
     *                             (front-end should show "Calculated at checkout").
     * @param array|null  $coupon  Session coupon row (or null). Re-validated here:
     *                             a coupon that has since expired or hit its usage
     *                             cap is ignored, so a stale session cannot
     *                             grant a discount.
     * @param array|null  $pickupLocation Active wk_pickup_locations row when the
     *                             customer chose locker/collection-point delivery.
     *                             Overrides the shipping fee (location fee, else
     *                             shipping.pickup_fee setting) and the tax
     *                             destination (the pickup point's country/state —
     *                             VAT is destination-based, and the goods are
     *                             delivered to the locker).
     * @return array {
     *     subtotal:   float,
     *     tax:        float,
     *     tax_label:  string,
     *     tax_known:  bool,       // false until address is supplied
     *     tax_breakdown: array,
     *     shipping:   float,
     *     discount:   float,
     *     discount_code: string,  // empty if no valid coupon
     *     total:      float
     * }
     */
    private static function computeTotals(array $cart, ?array $address, ?array $coupon, ?array $pickupLocation = null): array
    {
        $subtotal = (float)($cart['subtotal'] ?? 0);
        if ($pickupLocation !== null) {
            $shipping = ($pickupLocation['fee'] !== null && $pickupLocation['fee'] !== '')
                ? (float)$pickupLocation['fee']
                : (float)(Database::setting('shipping', 'pickup_fee') ?: 0);
            $address = [
                'country' => (string)$pickupLocation['country'],
                'state'   => (string)$pickupLocation['state'],
            ];
        } else {
            $shipping = self::calculateShipping($cart);
        }

        // Tax — only computed once we know the customer's country/state.
        $tax = 0.0;
        $taxLabel = '';
        $taxBreakdown = [];
        $taxKnown = false;
        if ($address !== null
            && (!empty($address['country']) || !empty($address['state']))) {
            $taxResult = \App\Services\TaxService::calculate($subtotal, $address);
            $tax = (float)($taxResult['amount'] ?? 0);
            $taxLabel = (string)($taxResult['label'] ?? 'Tax');
            $taxBreakdown = (array)($taxResult['breakdown'] ?? []);
            $taxKnown = true;
        }

        // Coupon — re-validate against DB so a session payload cannot grant a
        // discount on an expired/disabled/exhausted coupon.
        $discount = 0.0;
        $discountCode = '';
        if (is_array($coupon) && !empty($coupon['id'])) {
            $fresh = Database::fetch(
                "SELECT id, code, type, value, min_order_amount, max_discount,
                        usage_limit, used_count, starts_at, expires_at, is_active
                   FROM wk_coupons
                  WHERE id = ?
                    AND is_active = 1
                    AND (starts_at  IS NULL OR starts_at  <= NOW())
                    AND (expires_at IS NULL OR expires_at >  NOW())
                    AND (usage_limit IS NULL OR used_count < usage_limit)",
                [(int)$coupon['id']]
            );
            if ($fresh) {
                $minOrder = (float)($fresh['min_order_amount'] ?? 0);
                if ($subtotal + 0.0001 >= $minOrder) {
                    if ($fresh['type'] === 'percentage') {
                        $discount = round($subtotal * ((float)$fresh['value'] / 100), 2);
                    } else { // 'fixed'
                        $discount = round((float)$fresh['value'], 2);
                    }
                    // Cap by max_discount if set, and never exceed subtotal.
                    if (!empty($fresh['max_discount'])) {
                        $discount = min($discount, (float)$fresh['max_discount']);
                    }
                    $discount = min($discount, $subtotal);
                    $discount = max($discount, 0.0);
                    $discountCode = (string)$fresh['code'];
                }
            }
        }

        $total = $subtotal + $tax + $shipping - $discount;
        if ($total < 0) $total = 0.0;

        return [
            'subtotal'      => round($subtotal, 2),
            'tax'           => round($tax, 2),
            'tax_label'     => $taxLabel,
            'tax_known'     => $taxKnown,
            'tax_breakdown' => $taxBreakdown,
            'shipping'      => round($shipping, 2),
            'discount'      => round($discount, 2),
            'discount_code' => $discountCode,
            'total'         => round($total, 2),
        ];
    }

    /**
     * True when a PDOException is MySQL's "Unknown column" (1054) — used to
     * degrade gracefully on a database that hasn't run the latest migration.
     */
    private static function isUnknownColumnError(\PDOException $e): bool
    {
        if (isset($e->errorInfo[1]) && (int)$e->errorInfo[1] === 1054) return true;
        return stripos($e->getMessage(), 'Unknown column') !== false;
    }

    /**
     * All active pickup locations, empty when the table doesn't exist yet
     * (pre-v1.3.2 database).
     */
    private static function activePickupLocations(): array
    {
        try {
            return Database::fetchAll("SELECT * FROM wk_pickup_locations WHERE is_active=1 ORDER BY sort_order, name");
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * The requested pickup location, but only if pickup delivery is enabled
     * and the location exists and is active — the client's location id is
     * never trusted beyond this lookup.
     */
    private static function pickupLocationIfAllowed(int $id): ?array
    {
        if ($id <= 0) return null;
        if (Database::setting('shipping', 'pickup_enabled') !== '1') return null;
        try {
            return Database::fetch("SELECT * FROM wk_pickup_locations WHERE id=? AND is_active=1", [$id]);
        } catch (\Exception $e) {
            return null;
        }
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

    /**
     * Ownership check for gateway verify-callbacks.
     *
     * Returns true iff:
     *   - The client supplied no gateway_order_id (legacy clients / gateways
     *     that don't surface one — the gateway's own HMAC is then the sole
     *     defense, which is correct for Stripe-style flows). We accept and
     *     let the gateway's verifyPayment() do its signature check.
     *   - The client supplied one AND it equals the value we recorded when
     *     the order was created. Compared with hash_equals to avoid any
     *     timing-side-channel during enumeration.
     *
     * Returns false iff a value was supplied that does NOT match the
     * stored gateway_order_id — the verify endpoint must reject.
     *
     * Why this method exists (vs. inline): isolating the SQL+compare into
     * a pure function lets the test suite seed wk_payment_transactions and
     * exercise the contract directly without going through php://input.
     */
    public static function gatewayOrderIdMatches(int $orderId, string $gatewayCode, string $clientGatewayOrderId): bool
    {
        if ($clientGatewayOrderId === '') return true; // nothing to compare
        $expected = Database::fetchValue(
            "SELECT gateway_order_id FROM wk_payment_transactions
             WHERE order_id=? AND gateway_code=? AND gateway_order_id IS NOT NULL
             ORDER BY id DESC LIMIT 1",
            [$orderId, $gatewayCode]
        );
        if ($expected === null || $expected === false) {
            // No stored value (legacy row, or fetchValue miss). Pass through
            // to the gateway HMAC check — rejecting legacy rows outright
            // would break in-flight orders at deploy time.
            return true;
        }
        return hash_equals((string)$expected, $clientGatewayOrderId);
    }
}