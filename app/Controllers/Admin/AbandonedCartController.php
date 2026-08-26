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
        $currency = Database::setting('general', 'currency_symbol') ?: '₹';

        $page = max(1, (int) ($request->query('page') ?? 1));
        $perPage = 25;
        $offset = ($page - 1) * $perPage;

        // Carts worth chasing: still open, holding something, and left alone
        // long enough that the shopper has moved on.
        $minutes = \App\Services\CartRecoveryService::abandonAfterMinutes();

        $carts = Database::fetchAll(
            "SELECT c.*, c.email AS cart_email,
                    cu.first_name, cu.last_name, cu.email AS customer_email,
                    COUNT(ci.id) AS item_count,
                    SUM(ci.unit_price * ci.quantity) AS cart_value
               FROM wk_carts c
               LEFT JOIN wk_customers cu ON cu.id = c.customer_id
               JOIN wk_cart_items ci ON ci.cart_id = c.id
              WHERE c.recovered_at IS NULL
                AND (c.status = 'abandoned'
                     OR (c.status = 'active' AND c.updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)))
              GROUP BY c.id
              ORDER BY c.updated_at DESC
              LIMIT {$perPage} OFFSET {$offset}",
            [$minutes]
        );

        $totalCarts = (int) (Database::fetchValue(
            "SELECT COUNT(*) FROM (
                SELECT c.id FROM wk_carts c
                  JOIN wk_cart_items ci ON ci.cart_id = c.id
                 WHERE c.recovered_at IS NULL
                   AND (c.status = 'abandoned'
                        OR (c.status = 'active' AND c.updated_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)))
                 GROUP BY c.id
             ) t",
            [$minutes]
        ) ?: 0);

        $stats = [
            'total'      => $totalCarts,
            'value'      => array_sum(array_column($carts, 'cart_value')),
            'with_email' => count(array_filter($carts, fn($c) => !empty($c['cart_email'] ?? $c['customer_email']))),
        ];

        $settings = [];
        foreach (Database::fetchAll(
            "SELECT setting_group, setting_key, setting_value FROM wk_settings
              WHERE setting_group IN ('cart_recovery','leads')"
        ) as $row) {
            $settings[$row['setting_group']][$row['setting_key']] = $row['setting_value'];
        }

        View::render('admin/abandoned-carts/index', [
            'pageTitle'  => 'Abandoned Carts',
            'carts'      => $carts,
            'stats'      => $stats,
            'recovery'   => \App\Services\CartRecoveryService::stats(),
            'settings'   => $settings,
            'currency'   => $currency,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalCarts' => $totalCarts,
            'leadCount'  => (int) (Database::fetchValue("SELECT COUNT(*) FROM wk_leads") ?: 0),
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
        if (!Session::verifyCsrf($request->input('wk_csrf') ?? $request->server('HTTP_X_CSRF_TOKEN'))) {
            Response::json(['success' => false, 'message' => 'Session expired.'], 403);
            return;
        }

        $cartId = (int) $params['id'];
        $cart = Database::fetch(
            "SELECT c.*, cu.first_name, cu.last_name, cu.email AS customer_email
               FROM wk_carts c LEFT JOIN wk_customers cu ON cu.id=c.customer_id WHERE c.id=?",
            [$cartId]
        );
        if (!$cart) { Response::json(['success' => false, 'message' => 'Cart not found']); return; }

        // Six hours between reminders for the same cart, whatever the button
        // says — the disabled state in the UI is advisory only.
        if (!empty($cart['reminder_sent_at'])) {
            $lastSent = strtotime($cart['reminder_sent_at']);
            $sixHours = 6 * 3600;
            if ($lastSent !== false && (time() - $lastSent) < $sixHours) {
                $minsLeft = (int) ceil(($sixHours - (time() - $lastSent)) / 60);
                Response::json([
                    'success' => false,
                    'message' => "A reminder was sent recently for this cart. Try again in {$minsLeft} minute" . ($minsLeft === 1 ? '' : 's') . '.',
                ]);
                return;
            }
        }

        // One implementation for the email, whether it was sent by hand or by
        // the sweep: same template, same recovery link, same unsubscribe.
        $result = \App\Services\CartRecoveryService::sendReminder($cart);

        $email = $cart['email'] ?: ($cart['customer_email'] ?? '');
        Response::json([
            'success' => $result['sent'],
            'message' => $result['sent']
                ? 'Reminder sent to ' . $email
                : 'Not sent: ' . $result['reason'] . '.',
        ]);
    }

    /**
     * Run the sweep now, rather than waiting for storefront traffic.
     */
    public function runSweep(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/abandoned-carts'));
            return;
        }

        $r = \App\Services\CartRecoveryService::sweep(true);
        Session::flash('success', sprintf(
            'Sweep finished. %d cart%s marked abandoned, %d reminder%s sent.',
            $r['abandoned'], $r['abandoned'] === 1 ? '' : 's',
            $r['sent'], $r['sent'] === 1 ? '' : 's'
        ));
        Response::redirect(View::url('admin/abandoned-carts'));
    }

    /**
     * Save the recovery and lead-capture settings.
     */
    public function updateSettings(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/abandoned-carts'));
            return;
        }

        $onOff = fn($v) => $v === '1' ? '1' : '0';

        $values = [
            'cart_recovery' => [
                'recovery_enabled'      => $onOff($request->input('recovery_enabled')),
                'abandon_after_minutes' => (string) max(5, (int) $request->input('abandon_after_minutes')),
                'recovery_schedule'     => $this->cleanSchedule((string) $request->input('recovery_schedule')),
                'recovery_coupon'       => trim((string) $request->input('recovery_coupon')),
            ],
            'leads' => [
                'lead_capture_enabled' => $onOff($request->input('lead_capture_enabled')),
                'lead_capture_fields'  => in_array($request->input('lead_capture_fields'), ['email','phone','both'], true)
                    ? (string) $request->input('lead_capture_fields') : 'email',
                'lead_capture_when'    => $request->input('lead_capture_when') === 'always' ? 'always' : 'cart',
                'lead_capture_trigger' => in_array($request->input('lead_capture_trigger'), ['exit','time','both'], true)
                    ? (string) $request->input('lead_capture_trigger') : 'both',
                'lead_capture_delay'   => (string) max(10, min(1800, (int) $request->input('lead_capture_delay'))),
                'lead_capture_title_time' => mb_substr(trim((string) $request->input('lead_capture_title_time')), 0, 120),
                'lead_capture_text_time'  => mb_substr(trim((string) $request->input('lead_capture_text_time')), 0, 400),
                'lead_capture_title'   => mb_substr(trim((string) $request->input('lead_capture_title')), 0, 120),
                'lead_capture_text'    => mb_substr(trim((string) $request->input('lead_capture_text')), 0, 400),
                'lead_capture_coupon'  => trim((string) $request->input('lead_capture_coupon')),
            ],
        ];

        foreach ($values as $group => $pairs) {
            foreach ($pairs as $key => $value) {
                Database::query(
                    "INSERT INTO wk_settings (setting_group,setting_key,setting_value) VALUES(?,?,?)
                     ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                    [$group, $key, $value]
                );
            }
        }
        Database::clearSettingsCache();

        Session::flash('success', 'Recovery settings saved.');
        Response::redirect(View::url('admin/abandoned-carts'));
    }

    /** Keep only sensible, ascending minute values. */
    private function cleanSchedule(string $raw): string
    {
        $mins = array_filter(array_map('intval', explode(',', $raw)), fn($m) => $m > 0 && $m <= 43200);
        $mins = array_values(array_unique($mins));
        sort($mins);
        $mins = array_slice($mins, 0, 5);
        return $mins ? implode(',', $mins) : '60,1440,4320';
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
     * Prune old carts to keep the table bounded.
     *
     * Deletes carts in terminal states (abandoned, converted, merged) whose
     * last activity is older than 90 days. Active carts are never pruned —
     * a customer might still come back to a real cart. Triggered manually
     * from the abandoned-carts page so pruning stays observable rather than
     * running as a silent background sweep.
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