<?php
namespace App\Controllers\Store;

use Core\{Request, View, Database, Response, Session, Validator, RateLimiter};
use App\Services\EmailService;

class AccountController
{
    private const DUMMY_HASH = '$2y$12$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx';

    // ── Auth ──────────────────────────────────────

    public function showRegister(Request $request, array $params = []): void
    {
        if (Session::customerId()) { Response::redirect(View::url('account')); return; }
        View::render('store/account/register', ['pageTitle' => 'Create Account'], 'store/layouts/main');
    }

    public function register(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('account/register')); return;
        }

        // Rate limit: 3 registrations per IP per hour
        $ip = $request->ip();
        if (!RateLimiter::attempt('register', $ip, 3, 3600)) {
            Session::flash('error', 'Too many registration attempts. Please try again later.');
            Response::redirect(View::url('account/register')); return;
        }

        $v = new Validator($request->all(), [
            'first_name' => 'required|min:2', 'last_name' => 'required|min:1',
            'email' => 'required|email', 'password' => 'required|min:8',
        ]);
        if ($v->fails()) { Session::flash('error', $v->firstError()); Response::redirect(View::url('account/register')); return; }

        // Password complexity: require at least 1 number
        $pass = $request->input('password');
        if (!preg_match('/[0-9]/', $pass)) {
            Session::flash('error', 'Password must contain at least one number.');
            Response::redirect(View::url('account/register')); return;
        }

        if (Database::fetchValue("SELECT COUNT(*) FROM wk_customers WHERE email=?", [$request->clean('email')])) {
            Session::flash('error', 'An account with this email already exists.');
            Response::redirect(View::url('account/register')); return;
        }

        $id = Database::insert('wk_customers', [
            'first_name' => $request->clean('first_name'), 'last_name' => $request->clean('last_name'),
            'email' => $request->clean('email'), 'phone' => $request->clean('phone') ?? '',
            'password_hash' => password_hash($request->input('password'), PASSWORD_BCRYPT, ['cost' => 12]),
            'is_active' => 1,
        ]);
        Session::setCustomer($id);
        EmailService::sendWelcome($request->clean('email'), $request->clean('first_name'));
        Session::flash('success', 'Welcome! Your account has been created.');
        Response::redirect(View::url('account'));
    }

    public function showLogin(Request $request, array $params = []): void
    {
        if (Session::customerId()) { Response::redirect(View::url('account')); return; }
        View::render('store/account/login', ['pageTitle' => 'Sign In'], 'store/layouts/main');
    }

    public function login(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('account/login')); return;
        }

        // Rate limit: 5 attempts per IP per 15 minutes
        $ip = $request->ip();
        if (!RateLimiter::attempt('customer_login', $ip, 5, 900)) {
            $wait = ceil(RateLimiter::remainingSeconds('customer_login', $ip, 900) / 60);
            Session::flash('error', "Too many login attempts. Try again in {$wait} minutes.");
            Response::redirect(View::url('account/login')); return;
        }

        $email = $request->clean('email');
        $customer = Database::fetch("SELECT id, password_hash, is_active FROM wk_customers WHERE email=?", [$email]);

        // Timing attack prevention: always run password_verify
        $hash = $customer ? $customer['password_hash'] : self::DUMMY_HASH;
        $passwordValid = password_verify($request->input('password'), $hash);

        if (!$customer || !$customer['is_active'] || !$passwordValid) {
            Session::flash('error', 'Invalid email or password.');
            Response::redirect(View::url('account/login')); return;
        }

        RateLimiter::reset('customer_login', $ip);
        Session::setCustomer($customer['id']);
        Response::redirect(View::url('account'));
    }

    public function logout(Request $request, array $params = []): void
    {
        Session::destroy();
        // Restart session for flash message
        Session::start();
        Session::flash('success', 'You have been signed out.');
        Response::redirect(View::url(''));
    }

    // ── Dashboard ────────────────────────────────

    public function dashboard(Request $request, array $params = []): void
    {
        if (!Session::customerId()) { Response::redirect(View::url('account/login')); return; }
        $customer = Database::fetch("SELECT * FROM wk_customers WHERE id=?", [Session::customerId()]);
        $recentOrders = Database::fetchAll("SELECT * FROM wk_orders WHERE customer_id=? ORDER BY created_at DESC LIMIT 5", [Session::customerId()]);

        // Check if account was auto-created (password is a random hash they don't know)
        $needsPassword = !empty($customer['password_hash']) && str_starts_with($customer['password_hash'], '$2y$') && empty($_SESSION['wk_password_set']);
        // We can't truly detect random vs chosen password, so check a flag
        $hasSetPassword = (bool)Database::fetchValue(
            "SELECT setting_value FROM wk_settings WHERE setting_group='customer_flags' AND setting_key=?",
            ['password_set_' . Session::customerId()]
        );

        View::render('store/account/dashboard', [
            'pageTitle' => 'My Account', 'customer' => $customer,
            'recentOrders' => $recentOrders, 'needsPassword' => !$hasSetPassword,
        ], 'store/layouts/main');
    }

    // ── Profile ──────────────────────────────────

    public function profile(Request $request, array $params = []): void
    {
        if (!Session::customerId()) { Response::redirect(View::url('account/login')); return; }
        $customer = Database::fetch("SELECT * FROM wk_customers WHERE id=?", [Session::customerId()]);
        View::render('store/account/profile', ['pageTitle' => 'My Profile', 'customer' => $customer], 'store/layouts/main');
    }

    public function updateProfile(Request $request, array $params = []): void
    {
        if (!Session::customerId() || !Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('account/profile')); return;
        }
        Database::update('wk_customers', [
            'first_name' => $request->clean('first_name'),
            'last_name'  => $request->clean('last_name'),
            'phone'      => $request->clean('phone') ?? '',
        ], 'id=?', [Session::customerId()]);
        Session::flash('success', 'Profile updated!');
        Response::redirect(View::url('account/profile'));
    }

    // ── Set/Change Password ──────────────────────

    public function setPassword(Request $request, array $params = []): void
    {
        if (!Session::customerId() || !Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('account/profile')); return;
        }
        $newPass = $request->input('new_password');
        $confirm = $request->input('confirm_password');

        if (strlen($newPass) < 8) { Session::flash('error', 'Password must be at least 8 characters.'); Response::redirect(View::url('account/profile')); return; }
        if ($newPass !== $confirm) { Session::flash('error', 'Passwords do not match.'); Response::redirect(View::url('account/profile')); return; }

        Database::update('wk_customers', [
            'password_hash' => password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]),
        ], 'id=?', [Session::customerId()]);

        // Mark password as set
        Database::query(
            "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('customer_flags', ?, '1')
             ON DUPLICATE KEY UPDATE setting_value='1'",
            ['password_set_' . Session::customerId()]
        );

        Session::flash('success', 'Password updated!');
        Response::redirect(View::url('account/profile'));
    }

    // ── Addresses ────────────────────────────────

    public function addresses(Request $request, array $params = []): void
    {
        if (!Session::customerId()) { Response::redirect(View::url('account/login')); return; }
        $addresses = Database::fetchAll("SELECT * FROM wk_customer_addresses WHERE customer_id=? ORDER BY is_default DESC, id", [Session::customerId()]);
        View::render('store/account/addresses', ['pageTitle' => 'My Addresses', 'addresses' => $addresses], 'store/layouts/main');
    }

    public function storeAddress(Request $request, array $params = []): void
    {
        if (!Session::customerId() || !Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('account/addresses')); return;
        }
        $isDefault = $request->input('is_default') ? 1 : 0;
        if ($isDefault) {
            Database::update('wk_customer_addresses', ['is_default' => 0], 'customer_id=?', [Session::customerId()]);
        }
        Database::insert('wk_customer_addresses', [
            'customer_id'  => Session::customerId(),
            'label'        => $request->clean('label') ?: 'Home',
            'address_line1'=> $request->clean('address_line1'),
            'address_line2'=> $request->clean('address_line2') ?? '',
            'city'         => $request->clean('city'),
            'state'        => $request->clean('state'),
            'postal_code'  => $request->clean('postal_code'),
            'country'      => $request->clean('country') ?: 'IN',
            'is_default'   => $isDefault,
        ]);
        Session::flash('success', 'Address added!');
        Response::redirect(View::url('account/addresses'));
    }

    public function deleteAddress(Request $request, array $params = []): void
    {
        if (!Session::customerId()) { Response::redirect(View::url('account/login')); return; }
        Database::delete('wk_customer_addresses', 'id=? AND customer_id=?', [$params['id'], Session::customerId()]);
        Session::flash('success', 'Address deleted.');
        Response::redirect(View::url('account/addresses'));
    }

    // ── Orders ───────────────────────────────────

    public function orders(Request $request, array $params = []): void
    {
        if (!Session::customerId()) { Response::redirect(View::url('account/login')); return; }
        $orders = Database::fetchAll("SELECT * FROM wk_orders WHERE customer_id=? ORDER BY created_at DESC", [Session::customerId()]);
        View::render('store/account/orders', ['pageTitle' => 'My Orders', 'orders' => $orders], 'store/layouts/main');
    }

    public function orderDetail(Request $request, array $params = []): void
    {
        if (!Session::customerId()) { Response::redirect(View::url('account/login')); return; }
        $order = Database::fetch("SELECT * FROM wk_orders WHERE id=? AND customer_id=?", [$params['id'], Session::customerId()]);
        if (!$order) { Response::notFound(); return; }
        $items = Database::fetchAll(
            "SELECT oi.*, p.slug,
                    (SELECT image_path FROM wk_product_images WHERE product_id=oi.product_id AND is_primary=1 LIMIT 1) AS image
             FROM wk_order_items oi
             LEFT JOIN wk_products p ON p.id=oi.product_id
             WHERE oi.order_id=?",
            [$params['id']]
        );
        View::render('store/account/order-detail', ['pageTitle' => 'Order ' . $order['order_number'], 'order' => $order, 'items' => $items], 'store/layouts/main');
    }

    public function cancelOrder(Request $request, array $params = []): void
    {
        if (!Session::customerId() || !Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('account/orders')); return;
        }
        $order = Database::fetch("SELECT * FROM wk_orders WHERE id=? AND customer_id=?", [$params['id'], Session::customerId()]);
        if (!$order || !in_array($order['status'], ['pending', 'processing'])) {
            Session::flash('error', 'This order cannot be cancelled.');
            Response::redirect(View::url('account/orders')); return;
        }

        Database::update('wk_orders', ['status' => 'cancelled'], 'id=?', [$params['id']]);

        // Restore stock
        $items = Database::fetchAll("SELECT product_id, quantity, variant_combo_id FROM wk_order_items WHERE order_id=?", [$params['id']]);
        foreach ($items as $item) {
            Database::query("UPDATE wk_products SET stock_quantity = stock_quantity + ? WHERE id=?", [$item['quantity'], $item['product_id']]);
            if (!empty($item['variant_combo_id'])) {
                try { Database::query("UPDATE wk_variant_combos SET stock_quantity = stock_quantity + ? WHERE id=?", [$item['quantity'], $item['variant_combo_id']]); } catch (\Exception $e) {}
            }
        }

        // Update customer stats
        Database::query("UPDATE wk_customers SET total_orders = GREATEST(0, total_orders - 1), total_spent = GREATEST(0, total_spent - ?) WHERE id=?",
            [$order['total'], Session::customerId()]);

        // Send cancellation email to customer
        $currency = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';
        $storeName = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Store';
        $billing = json_decode($order['billing_address'] ?? '{}', true) ?: [];
        $vars = [
            '{{customer_name}}' => trim($billing['name'] ?? '') ?: $order['customer_email'],
            '{{order_number}}' => $order['order_number'],
            '{{order_status}}' => 'Cancelled',
            '{{status_emoji}}' => '❌',
            '{{order_total}}' => $currency . number_format($order['total'], 2),
            '{{order_date}}' => date('M j, Y', strtotime($order['created_at'])),
            '{{store_name}}' => $storeName,
            '{{store_url}}' => \Core\View::url(''),
        ];
        \App\Services\EmailService::sendFromTemplate('order-status-update', $order['customer_email'], $vars);

        // Notify admin
        $adminEmail = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='contact_email'")
            ?: \Core\Database::fetchValue("SELECT email FROM wk_admins WHERE role='superadmin' LIMIT 1");
        if ($adminEmail) {
            \App\Services\EmailService::send($adminEmail, "Order Cancelled: {$order['order_number']}",
                '<h2>Order Cancelled by Customer</h2><p><strong>'.$order['order_number'].'</strong> — '.$currency.number_format($order['total'],2).'</p><p>Customer: '.htmlspecialchars($order['customer_email']).'</p>');
        }

        Session::flash('success', 'Order cancelled. Stock has been restored.');
        Response::redirect(View::url('account/order/' . $params['id']));
    }

    // ── Forgot Password ──────────────────────────

    public function showForgotPassword(Request $request, array $params = []): void
    {
        View::render('store/account/forgot-password', ['pageTitle' => 'Forgot Password'], 'store/layouts/main');
    }

    public function forgotPassword(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('account/forgot-password')); return;
        }

        // Rate limit: 3 per email per hour + 10 per IP per hour
        $ip = $request->ip();
        $email = $request->clean('email');
        if (!RateLimiter::attempt('forgot_ip', $ip, 10, 3600) || !RateLimiter::attempt('forgot_email', $email ?? 'none', 3, 3600)) {
            Session::flash('error', 'Too many reset requests. Please try again later.');
            Response::redirect(View::url('account/forgot-password')); return;
        }

        $customer = Database::fetch("SELECT id, first_name FROM wk_customers WHERE email=?", [$email]);

        if ($customer) {
            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            // Delete existing tokens for this customer
            try { Database::query("DELETE FROM wk_password_resets WHERE user_type='customer' AND user_id=?", [$customer['id']]); } catch (\Exception $e) {}

            // Store hashed token
            try {
                Database::insert('wk_password_resets', [
                    'user_type'  => 'customer',
                    'user_id'    => $customer['id'],
                    'token_hash' => $tokenHash,
                    'ip_address' => $ip,
                    'expires_at' => $expiresAt,
                ]);
            } catch (\Exception $e) {
                // Fallback for old installs without the table
                Database::query(
                    "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('reset_tokens', ?, ?)
                     ON DUPLICATE KEY UPDATE setting_value=?",
                    ['token_' . $customer['id'], json_encode(['token' => $token, 'expires' => time() + 3600]), json_encode(['token' => $token, 'expires' => time() + 3600])]
                );
            }

            // Send reset email
            $resetUrl = View::url('account/reset-password?token=' . $token . '&id=' . $customer['id']);
            $body = '<div style="text-align:center;margin-bottom:24px">
                <div style="font-size:48px;margin-bottom:8px">🔑</div>
                <h1 style="font-size:24px;font-weight:900;margin:0 0 4px">Reset Your Password</h1>
                <p style="color:#6b7280;margin:0">Hi ' . htmlspecialchars($customer['first_name']) . ', click the link below to reset your password.</p>
            </div>
            <div style="text-align:center;margin-top:24px">
                <a href="' . $resetUrl . '" style="display:inline-block;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:14px 32px;border-radius:8px;text-decoration:none;font-weight:800">Reset Password →</a>
            </div>
            <p style="text-align:center;margin-top:16px;font-size:12px;color:#9ca3af">This link expires in 1 hour.</p>';
            EmailService::send($email, 'Reset Your Password', $body);
        }

        // Always show success (don't reveal if email exists)
        Session::flash('success', 'If that email exists, we\'ve sent a password reset link.');
        Response::redirect(View::url('account/forgot-password'));
    }

    public function showResetPassword(Request $request, array $params = []): void
    {
        $token = $request->query('token');
        $id = (int)$request->query('id');
        View::render('store/account/reset-password', ['pageTitle' => 'Reset Password', 'token' => $token, 'customerId' => $id], 'store/layouts/main');
    }

    public function resetPassword(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.'); Response::redirect(View::url('account/forgot-password')); return;
        }

        $token = $request->input('token');
        $id = (int)$request->input('customer_id');
        $newPass = $request->input('password');
        $confirm = $request->input('confirm_password');

        if (strlen($newPass) < 8) { Session::flash('error', 'Password must be at least 8 characters.'); Response::redirect(View::url('account/forgot-password')); return; }
        if ($newPass !== $confirm) { Session::flash('error', 'Passwords do not match.'); Response::redirect(View::url('account/forgot-password')); return; }

        // Verify token — try new table first, fallback to old
        $tokenHash = hash('sha256', $token);
        $validToken = false;

        try {
            $row = Database::fetch(
                "SELECT id FROM wk_password_resets WHERE user_type='customer' AND user_id=? AND token_hash=? AND expires_at > NOW() AND used_at IS NULL",
                [$id, $tokenHash]
            );
            if ($row) $validToken = true;
        } catch (\Exception $e) {}

        if (!$validToken) {
            // Fallback: old wk_settings storage
            $stored = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='reset_tokens' AND setting_key=?", ['token_' . $id]);
            if ($stored) {
                $data = json_decode($stored, true);
                if ($data && hash_equals($data['token'] ?? '', $token) && ($data['expires'] ?? 0) >= time()) {
                    $validToken = true;
                }
            }
        }

        if (!$validToken) {
            Session::flash('error', 'Invalid or expired link. Please request a new one.');
            Response::redirect(View::url('account/forgot-password')); return;
        }

        // Update password
        Database::update('wk_customers', ['password_hash' => password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12])], 'id=?', [$id]);

        // Delete tokens from both tables
        try { Database::query("DELETE FROM wk_password_resets WHERE user_type='customer' AND user_id=?", [$id]); } catch (\Exception $e) {}
        try { Database::query("DELETE FROM wk_settings WHERE setting_group='reset_tokens' AND setting_key=?", ['token_' . $id]); } catch (\Exception $e) {}

        // Cleanup expired tokens from new table
        try { Database::query("DELETE FROM wk_password_resets WHERE expires_at < NOW()"); } catch (\Exception $e) {}

        Database::query("INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('customer_flags', ?, '1') ON DUPLICATE KEY UPDATE setting_value='1'", ['password_set_' . $id]);

        Session::flash('success', 'Password reset! You can now sign in.');
        Response::redirect(View::url('account/login'));
    }
}
