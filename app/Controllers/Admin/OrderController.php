<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Response, Session};
use App\Services\{EmailService, RefundService, CurrencyService};

class OrderController
{
    public function index(Request $request, array $params = []): void
    {
        $orders = Database::fetchAll(
            "SELECT o.*, c.first_name, c.last_name FROM wk_orders o
             LEFT JOIN wk_customers c ON c.id=o.customer_id
             ORDER BY o.created_at DESC"
        );
        View::render('admin/orders/index', ['pageTitle' => 'Orders', 'orders' => $orders], 'admin/layouts/main');
    }

    public function show(Request $request, array $params = []): void
    {
        $order = Database::fetch("SELECT * FROM wk_orders WHERE id=?", [$params['id']]);
        if (!$order) { Response::notFound(); return; }

        $items = Database::fetchAll("SELECT * FROM wk_order_items WHERE order_id=?", [$params['id']]);
        $transactions = Database::fetchAll("SELECT * FROM wk_payment_transactions WHERE order_id=? ORDER BY created_at DESC", [$params['id']]);

        // Get shipping carriers (table may not exist yet)
        $carriers = [];
        try {
            $carriers = Database::fetchAll("SELECT * FROM wk_shipping_carriers WHERE is_active=1 ORDER BY name");
        } catch (\Exception $e) {
            // Table doesn't exist yet — that's fine, it gets created on first shipping update
            $carriers = [];
        }

        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

        // The refunds table arrives with a migration, so a store mid-update
        // still renders its orders.
        $refunds = [];
        $refundable = 0.0;
        $refundBlocked = false;
        try {
            $refunds       = RefundService::forOrder((int) $order['id']);
            $refundable    = RefundService::refundableAmount($order);
            $refundBlocked = RefundService::hasUnresolved((int) $order['id']);
        } catch (\Exception $e) {
            $refunds = [];
        }

        View::render('admin/orders/show', [
            'pageTitle'     => 'Order ' . $order['order_number'],
            'order'         => $order,
            'items'         => $items,
            'transactions'  => $transactions,
            'carriers'      => $carriers,
            'currency'      => $currency,
            'refunds'       => $refunds,
            'refundable'    => $refundable,
            'refundBlocked' => $refundBlocked,
        ], 'admin/layouts/main');
    }

    public function updateStatus(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/orders/' . $params['id']));
            return;
        }

        // Whitelist the status before writing: these literal values drive the
        // storefront status map, email labels, and cron matching. Keep in sync
        // with the wk_orders.status ENUM in sql/schema.sql ('payment_failed'
        // is written by CheckoutController when gateway init fails).
        $status = (string)$request->input('status');
        $allowedStatuses = [
            'pending', 'processing', 'paid', 'shipped',
            'delivered', 'cancelled', 'refunded', 'payment_failed',
        ];
        if (!in_array($status, $allowedStatuses, true)) {
            Session::flash('error', 'Invalid order status.');
            Response::redirect(View::url('admin/orders/' . $params['id']));
            return;
        }
        Database::update('wk_orders', ['status' => $status], 'id=?', [$params['id']]);

        // Send status update email to customer
        $order = Database::fetch("SELECT * FROM wk_orders WHERE id=?", [$params['id']]);
        if ($order && $order['customer_email']) {
            $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';
            $storeName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Store';
            $billing = json_decode($order['billing_address'] ?? '{}', true) ?: [];
            $statusLabels = ['pending'=>'Pending','processing'=>'Processing','paid'=>'Payment Confirmed','shipped'=>'Shipped','delivered'=>'Delivered','cancelled'=>'Cancelled','refunded'=>'Refunded'];
            $statusEmoji = ['pending'=>'⏳','processing'=>'🔄','paid'=>'✅','shipped'=>'📦','delivered'=>'🎉','cancelled'=>'❌','refunded'=>'↩️'];

            $vars = [
                '{{customer_name}}' => trim($billing['name'] ?? '') ?: $order['customer_email'],
                '{{order_number}}' => $order['order_number'],
                '{{order_status}}' => $statusLabels[$status] ?? ucfirst($status),
                '{{status_emoji}}' => $statusEmoji[$status] ?? '📋',
                '{{order_total}}' => $currency . number_format($order['total'], 2),
                '{{order_date}}' => date('M j, Y', strtotime($order['created_at'])),
                '{{store_name}}' => $storeName,
                '{{store_url}}' => View::url(''),
            ];
            EmailService::sendFromTemplate('order-status-update', $order['customer_email'], $vars);
        }

        Session::flash('success', 'Order status updated to ' . ucfirst($status));
        Response::redirect(View::url('admin/orders/' . $params['id']));
    }

    public function refund(Request $request, array $params = []): void
    {
        $back = View::url('admin/orders/' . (int) $params['id']);

        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired. Please try again.');
            Response::redirect($back);
            return;
        }

        $order = Database::fetch("SELECT * FROM wk_orders WHERE id=?", [$params['id']]);
        if (!$order) { Response::notFound(); return; }

        $amount = (float) str_replace(',', '', (string) $request->input('amount'));
        $reason = trim((string) $request->input('reason'));
        $manual = $request->input('manual') === '1';

        $result = RefundService::issue($order, $amount, $reason, Session::adminId(), $manual);

        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        Response::redirect($back);
    }

    public function updateShipping(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/orders/' . $params['id']));
            return;
        }

        $carrier        = $request->clean('shipping_carrier');
        $trackingNumber = $request->clean('tracking_number');
        $trackingUrl    = $request->clean('tracking_url');
        $newCarrier     = $request->clean('new_carrier');

        // Quick-add carrier
        if (!empty($newCarrier)) {
            // Check if shipping_carriers table exists, create if not
            try {
                Database::query("SELECT 1 FROM wk_shipping_carriers LIMIT 1");
            } catch (\Exception $e) {
                Database::exec("CREATE TABLE IF NOT EXISTS wk_shipping_carriers (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    tracking_url_template VARCHAR(500),
                    is_active TINYINT(1) DEFAULT 1
                ) ENGINE=InnoDB");
            }

            Database::insert('wk_shipping_carriers', [
                'name' => $newCarrier,
                'tracking_url_template' => $trackingUrl ?: '',
                'is_active' => 1,
            ]);
            $carrier = $newCarrier;
        }

        // Update order with shipping info
        $updateData = ['status' => 'shipped'];
        $notes = json_decode(Database::fetchValue("SELECT notes FROM wk_orders WHERE id=?", [$params['id']]) ?: '{}', true) ?: [];
        $notes['shipping_carrier']   = $carrier;
        $notes['tracking_number']    = $trackingNumber;
        $notes['tracking_url']       = $trackingUrl;
        $notes['shipped_at']         = date('Y-m-d H:i:s');

        Database::update('wk_orders', [
            'status' => 'shipped',
            'notes'  => json_encode($notes),
        ], 'id=?', [$params['id']]);

        // Send shipping email
        $order = Database::fetch("SELECT * FROM wk_orders WHERE id=?", [$params['id']]);
        if ($order && $order['customer_email']) {
            EmailService::sendShippingNotification($order, $carrier, $trackingNumber, $trackingUrl);
        }

        Session::flash('success', 'Shipping info updated and customer notified!');
        Response::redirect(View::url('admin/orders/' . $params['id']));
    }

    public function invoice(Request $request, array $params = []): void
    {
        $html = \App\Services\InvoiceService::generateHTML((int)$params['id']);
        if (!$html) { Response::notFound(); return; }
        echo $html;
        exit;
    }
}
