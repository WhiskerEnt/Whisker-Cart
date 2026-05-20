<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Response, Session};

class AbandonedCartController
{
    /**
     * List abandoned carts (active carts with items, older than 1 hour, not converted)
     */
    public function index(Request $request, array $params = []): void
    {
        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

        // Carts that: have items, are still 'active', created more than 1 hour ago
        $carts = Database::fetchAll(
            "SELECT c.*, 
                    c.email AS cart_email,
                    cu.first_name, cu.last_name, cu.email AS customer_email,
                    COUNT(ci.id) AS item_count,
                    SUM(ci.unit_price * ci.quantity) AS cart_value
             FROM wk_carts c
             LEFT JOIN wk_customers cu ON cu.id = c.customer_id
             JOIN wk_cart_items ci ON ci.cart_id = c.id
             WHERE c.status = 'active'
               AND c.created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
             GROUP BY c.id
             ORDER BY c.created_at DESC
             LIMIT 50"
        );

        $stats = [
            'total' => count($carts),
            'value' => array_sum(array_column($carts, 'cart_value')),
            'with_email' => count(array_filter($carts, fn($c) => !empty($c['cart_email'] ?? $c['customer_email']))),
        ];

        View::render('admin/abandoned-carts/index', [
            'pageTitle' => 'Abandoned Carts',
            'carts' => $carts,
            'stats' => $stats,
            'currency' => $currency,
        ], 'admin/layouts/main');
    }

    /**
     * View cart details
     */
    public function show(Request $request, array $params = []): void
    {
        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';
        $cartId = (int)$params['id'];

        $cart = Database::fetch(
            "SELECT c.*, cu.first_name, cu.last_name, cu.email AS customer_email, cu.phone
             FROM wk_carts c LEFT JOIN wk_customers cu ON cu.id=c.customer_id WHERE c.id=?", [$cartId]
        );
        if (!$cart) { Response::notFound(); return; }

        $items = Database::fetchAll(
            "SELECT ci.*, p.name, p.slug, p.price AS product_price,
                    vc.label AS variant_label,
                    (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
             FROM wk_cart_items ci
             JOIN wk_products p ON p.id=ci.product_id
             LEFT JOIN wk_variant_combos vc ON vc.id=ci.variant_combo_id
             WHERE ci.cart_id=?", [$cartId]
        );

        $total = array_reduce($items, fn($s, $i) => $s + ($i['unit_price'] * $i['quantity']), 0);

        View::render('admin/abandoned-carts/show', [
            'pageTitle' => 'Abandoned Cart #' . $cartId,
            'cart' => $cart, 'items' => $items, 'total' => $total, 'currency' => $currency,
        ], 'admin/layouts/main');
    }

    /**
     * Send abandoned cart reminder email
     */
    public function sendReminder(Request $request, array $params = []): void
    {
        // CSRF — group middleware enforces but be explicit (consistent with
        // other destructive/side-effect admin endpoints).
        if (!Session::verifyCsrf($request->input('wk_csrf') ?? $request->server('HTTP_X_CSRF_TOKEN'))) {
            Response::json(['success' => false, 'message' => 'Session expired.'], 403);
            return;
        }

        $cartId = (int)$params['id'];
        $cart = Database::fetch(
            "SELECT c.*, cu.first_name, cu.last_name, cu.email AS customer_email
             FROM wk_carts c LEFT JOIN wk_customers cu ON cu.id=c.customer_id WHERE c.id=?", [$cartId]
        );
        if (!$cart) { Response::json(['success' => false, 'message' => 'Cart not found']); return; }

        // M25: 6-hour cooldown per cart. Without this an admin (or attacker
        // riding a compromised admin session) can spam reminders to a
        // customer's email — and the UI's btn.disabled isn't binding. The
        // wk_carts.reminder_sent_at column already exists and gets bumped
        // on a successful send below; we just need to enforce the window.
        if (!empty($cart['reminder_sent_at'])) {
            $lastSent = strtotime($cart['reminder_sent_at']);
            $sixHours = 6 * 3600;
            if ($lastSent !== false && (time() - $lastSent) < $sixHours) {
                $minsLeft = (int)ceil(($sixHours - (time() - $lastSent)) / 60);
                Response::json([
                    'success' => false,
                    'message' => "A reminder was sent recently for this cart. Try again in {$minsLeft} minute" . ($minsLeft === 1 ? '' : 's') . '.',
                ]);
                return;
            }
        }

        $email = $cart['email'] ?? $cart['customer_email'] ?? null;
        if (!$email) { Response::json(['success' => false, 'message' => 'No email address for this cart']); return; }

        $name = trim(($cart['first_name'] ?? '') . ' ' . ($cart['last_name'] ?? '')) ?: 'Customer';
        $currency = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';
        $storeName = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store';

        // Build cart items HTML
        $items = Database::fetchAll(
            "SELECT ci.*, p.name, vc.label AS variant_label,
                    (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
             FROM wk_cart_items ci JOIN wk_products p ON p.id=ci.product_id
             LEFT JOIN wk_variant_combos vc ON vc.id=ci.variant_combo_id WHERE ci.cart_id=?", [$cartId]
        );

        $itemsHtml = '<table style="width:100%;font-size:14px;border-collapse:collapse">';
        $total = 0;
        foreach ($items as $item) {
            $lineTotal = $item['unit_price'] * $item['quantity'];
            $total += $lineTotal;
            $imgTag = '';
            if ($item['image']) {
                $imgTag = '<img src="' . View::url('storage/uploads/products/' . $item['image']) . '" style="width:48px;height:48px;object-fit:cover;border-radius:6px" alt="">';
            } else {
                $imgTag = '<div style="width:48px;height:48px;background:#faf8f6;border-radius:6px;display:flex;align-items:center;justify-content:center">📦</div>';
            }
            $variant = !empty($item['variant_label']) ? '<div style="font-size:12px;color:#8b5cf6;font-weight:700">' . htmlspecialchars($item['variant_label']) . '</div>' : '';
            $itemsHtml .= '<tr><td style="padding:12px 0;border-bottom:1px solid #e8e5df"><div style="display:flex;align-items:center;gap:10px">' . $imgTag . '<div><div style="font-weight:700">' . htmlspecialchars($item['name']) . '</div>' . $variant . '<div style="font-size:12px;color:#6b7280">Qty: ' . $item['quantity'] . ' × ' . $currency . number_format($item['unit_price'], 2) . '</div></div></div></td><td style="text-align:right;font-family:monospace;font-weight:700;vertical-align:top;padding-top:16px">' . $currency . number_format($lineTotal, 2) . '</td></tr>';
        }
        $itemsHtml .= '</table>';

        $vars = [
            '{{customer_name}}' => $name, '{{customer_email}}' => $email,
            '{{store_name}}' => $storeName, '{{store_url}}' => View::url(''),
            '{{cart_items_html}}' => $itemsHtml,
            '{{cart_total}}' => $currency . number_format($total, 2),
            '{{cart_url}}' => View::url(''),
            '{{currency_symbol}}' => $currency,
        ];

        $sent = \App\Services\EmailService::sendFromTemplate('abandoned-cart', $email, $vars);

        if ($sent) {
            try {
                Database::query("UPDATE wk_carts SET reminder_sent_at=NOW(), reminder_count=reminder_count+1 WHERE id=?", [$cartId]);
            } catch (\Exception $e) {}
        }

        Response::json(['success' => $sent, 'message' => $sent ? 'Reminder sent to ' . $email : 'Failed to send. Check SMTP settings.']);
    }

    /**
     * Mark cart as abandoned (manual)
     */
    public function markAbandoned(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/abandoned-carts'));
            return;
        }
        Database::query("UPDATE wk_carts SET status='abandoned' WHERE id=?", [$params['id']]);
        Session::flash('success', 'Cart marked as abandoned.');
        Response::redirect(View::url('admin/abandoned-carts'));
    }

    /**
     * M1: Prune old carts to keep the table bounded.
     *
     * Deletes carts in terminal states (abandoned, converted, merged) whose
     * last activity is older than 90 days. Active carts are NEVER pruned —
     * a customer might still come back to a real cart.
     *
     * Triggered manually by the admin from the abandoned-carts page. We
     * deliberately do not run this on every request: cart pruning is a
     * housekeeping operation that should be observable, not a silent
     * background sweep that could surprise an operator.
     */
    public function prune(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/abandoned-carts'));
            return;
        }

        try {
            // wk_cart_items has FK ON DELETE CASCADE on wk_carts.id, so the
            // item rows go with the cart row automatically.
            $stmt = Database::query(
                "DELETE FROM wk_carts
                  WHERE status IN ('abandoned','converted','merged')
                    AND updated_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
            $deleted = $stmt->rowCount();
            Session::flash('success', "Pruned {$deleted} old cart" . ($deleted === 1 ? '' : 's') . " (>90 days, terminal state).");
        } catch (\Exception $e) {
            Session::flash('error', 'Cart pruning failed: ' . $e->getMessage());
        }
        Response::redirect(View::url('admin/abandoned-carts'));
    }
}