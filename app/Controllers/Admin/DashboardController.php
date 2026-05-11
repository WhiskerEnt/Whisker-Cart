<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Response, Session};

class DashboardController
{
    public function index(Request $request, array $params = []): void
    {
        $currencySymbol = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

        // Run pending database migrations if version changed
        $migrationResult = null;
        try {
            $migrationResult = \App\Services\MigrationService::checkAndRun();
        } catch (\Exception $e) {}

        // Cleanup expired rate limiter files (lightweight, runs on every dashboard load)
        try { \Core\RateLimiter::cleanup(7200); } catch (\Exception $e) {}

        // Release stock from expired unpaid orders (15 min window)
        try {
            $expiredOrders = Database::fetchAll(
                "SELECT id FROM wk_orders WHERE status IN ('pending','payment_failed') AND payment_status != 'captured' AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
            );
            foreach ($expiredOrders as $expired) {
                // Restore stock
                $items = Database::fetchAll("SELECT product_id, quantity, variant_combo_id FROM wk_order_items WHERE order_id=?", [$expired['id']]);
                foreach ($items as $item) {
                    Database::query("UPDATE wk_products SET stock_quantity = stock_quantity + ? WHERE id = ?", [$item['quantity'], $item['product_id']]);
                    if ($item['variant_combo_id'] ?? null) {
                        try { Database::query("UPDATE wk_variant_combos SET stock_quantity = stock_quantity + ? WHERE id = ?", [$item['quantity'], $item['variant_combo_id']]); } catch (\Exception $e) {}
                    }
                }
                // Mark as expired
                Database::update('wk_orders', ['status' => 'cancelled', 'payment_status' => 'expired'], 'id=?', [$expired['id']]);
            }
        } catch (\Exception $e) {}

        // Check for updates (cached, hits API once per day)
        $updateAvailable = null;
        try {
            $updateAvailable = \App\Services\UpdateService::check();
        } catch (\Exception $e) {}

        // Low stock email alert (once per day)
        try {
            $lastAlert = Database::setting('system_cache', 'last_stock_alert');
            if (!$lastAlert || (time() - (int)$lastAlert) > 86400) {
                $lowStockItems = Database::fetchAll(
                    "SELECT name, sku, stock_quantity FROM wk_products WHERE is_active=1 AND stock_quantity > 0 AND stock_quantity <= 5 ORDER BY stock_quantity ASC LIMIT 20"
                );
                if (!empty($lowStockItems)) {
                    $adminEmail = Database::fetchValue("SELECT email FROM wk_admins WHERE role='superadmin' LIMIT 1");
                    if ($adminEmail) {
                        $rows = '';
                        foreach ($lowStockItems as $item) {
                            $rows .= '<tr><td style="padding:8px 12px;border-bottom:1px solid #eee">' . htmlspecialchars($item['name']) . '</td>'
                                    . '<td style="padding:8px 12px;border-bottom:1px solid #eee">' . htmlspecialchars($item['sku'] ?? '-') . '</td>'
                                    . '<td style="padding:8px 12px;border-bottom:1px solid #eee;font-weight:700;color:#ef4444">' . $item['stock_quantity'] . '</td></tr>';
                        }
                        \App\Services\EmailService::send($adminEmail, 'Low Stock Alert — ' . count($lowStockItems) . ' products', '
                            <h2>Low Stock Alert</h2>
                            <p>The following products have 5 or fewer items in stock:</p>
                            <table style="width:100%;border-collapse:collapse;font-size:14px">
                                <thead><tr style="background:#f9fafb"><th style="padding:8px 12px;text-align:left">Product</th><th style="padding:8px 12px;text-align:left">SKU</th><th style="padding:8px 12px;text-align:left">Stock</th></tr></thead>
                                <tbody>' . $rows . '</tbody>
                            </table>
                            <p style="margin-top:16px;color:#6b7280;font-size:13px">This alert is sent once per day. Restock soon to avoid lost sales.</p>
                        ');
                    }
                }
                Database::query(
                    "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system_cache', 'last_stock_alert', ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    [(string)time()]
                );
            }
        } catch (\Exception $e) {}

        // ── Core Stats ──
        $stats = [
            'revenue'        => (float)(Database::fetchValue("SELECT COALESCE(SUM(total),0) FROM wk_orders WHERE status NOT IN ('cancelled','refunded')") ?: 0),
            'orders'         => (int)(Database::fetchValue("SELECT COUNT(*) FROM wk_orders") ?: 0),
            'products'       => (int)(Database::fetchValue("SELECT COUNT(*) FROM wk_products WHERE is_active=1") ?: 0),
            'customers'      => (int)(Database::fetchValue("SELECT COUNT(*) FROM wk_customers") ?: 0),
            'pending'        => (int)(Database::fetchValue("SELECT COUNT(*) FROM wk_orders WHERE status='pending'") ?: 0),
            'processing'     => (int)(Database::fetchValue("SELECT COUNT(*) FROM wk_orders WHERE status='processing'") ?: 0),
            'avg_order'      => (float)(Database::fetchValue("SELECT COALESCE(AVG(total),0) FROM wk_orders WHERE status NOT IN ('cancelled','refunded')") ?: 0),
            'today_revenue'  => (float)(Database::fetchValue("SELECT COALESCE(SUM(total),0) FROM wk_orders WHERE DATE(created_at)=CURDATE() AND status NOT IN ('cancelled','refunded')") ?: 0),
            'today_orders'   => (int)(Database::fetchValue("SELECT COUNT(*) FROM wk_orders WHERE DATE(created_at)=CURDATE()") ?: 0),
            'month_revenue'  => (float)(Database::fetchValue("SELECT COALESCE(SUM(total),0) FROM wk_orders WHERE MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW()) AND status NOT IN ('cancelled','refunded')") ?: 0),
            'low_stock'      => (int)(Database::fetchValue("SELECT COUNT(*) FROM wk_products WHERE is_active=1 AND stock_quantity > 0 AND stock_quantity <= 5") ?: 0),
            'out_of_stock'   => (int)(Database::fetchValue("SELECT COUNT(*) FROM wk_products WHERE is_active=1 AND stock_quantity <= 0") ?: 0),
        ];

        // ── Revenue last 30 days (for chart) ──
        $revenueChart = Database::fetchAll(
            "SELECT DATE(created_at) AS date, COALESCE(SUM(total),0) AS revenue, COUNT(*) AS orders
             FROM wk_orders
             WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               AND status NOT IN ('cancelled','refunded')
             GROUP BY DATE(created_at)
             ORDER BY date"
        );

        // Fill missing days
        $chartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $found = false;
            foreach ($revenueChart as $row) {
                if ($row['date'] === $date) {
                    $chartData[] = ['date' => $date, 'label' => date('M j', strtotime($date)), 'revenue' => (float)$row['revenue'], 'orders' => (int)$row['orders']];
                    $found = true; break;
                }
            }
            if (!$found) $chartData[] = ['date' => $date, 'label' => date('M j', strtotime($date)), 'revenue' => 0, 'orders' => 0];
        }

        // ── Order status breakdown ──
        $statusBreakdown = Database::fetchAll(
            "SELECT status, COUNT(*) AS count FROM wk_orders GROUP BY status ORDER BY count DESC"
        );

        // ── Top selling products ──
        $topProducts = Database::fetchAll(
            "SELECT p.name, p.slug, SUM(oi.quantity) AS sold, SUM(oi.total_price) AS revenue,
                    (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
             FROM wk_order_items oi
             JOIN wk_products p ON p.id=oi.product_id
             JOIN wk_orders o ON o.id=oi.order_id
             WHERE o.status NOT IN ('cancelled','refunded')
             GROUP BY p.id
             ORDER BY sold DESC
             LIMIT 5"
        );

        // ── Recent orders ──
        $recentOrders = Database::fetchAll(
            "SELECT o.*, c.first_name, c.last_name
             FROM wk_orders o LEFT JOIN wk_customers c ON c.id=o.customer_id
             ORDER BY o.created_at DESC LIMIT 8"
        );

        // ── Recent customers ──
        $recentCustomers = Database::fetchAll(
            "SELECT * FROM wk_customers ORDER BY created_at DESC LIMIT 5"
        );

        // Get available backups for rollback
        $backups = \App\Services\UpdateService::getBackups();

        // Show migration results if any ran
        if ($migrationResult && $migrationResult['ran'] > 0) {
            $msg = $migrationResult['ran'] . ' database migration' . ($migrationResult['ran'] > 1 ? 's' : '') . ' applied automatically.';
            if (!empty($migrationResult['errors'])) {
                $msg .= ' (' . count($migrationResult['errors']) . ' error' . (count($migrationResult['errors']) > 1 ? 's' : '') . ')';
            }
            Session::flash('success', $msg);
        }

        // Estimate DB size for backup options
        $dbSize = null;
        if ($updateAvailable) {
            try { $dbSize = \App\Services\UpdateService::estimateDbSize(); } catch (\Exception $e) {}
        }

        View::render('admin/dashboard', [
            'pageTitle'        => 'Dashboard',
            'stats'            => $stats,
            'chartData'        => $chartData,
            'statusBreakdown'  => $statusBreakdown,
            'topProducts'      => $topProducts,
            'recentOrders'     => $recentOrders,
            'recentCustomers'  => $recentCustomers,
            'currency'         => $currencySymbol,
            'updateAvailable'  => $updateAvailable,
            'backups'          => $backups,
            'dbSize'           => $dbSize,
        ], 'admin/layouts/main');
    }

    /**
     * Apply update — downloads and extracts new version
     */
    public function applyUpdate(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin'));
            return;
        }

        $downloadUrl = $request->input('download_url');
        $sha256 = $request->input('sha256');
        $dbBackupMode = $request->input('db_backup') ?? 'schema';
        if (!in_array($dbBackupMode, ['none', 'schema', 'full'])) $dbBackupMode = 'schema';

        if (!$downloadUrl || !filter_var($downloadUrl, FILTER_VALIDATE_URL)) {
            Session::flash('error', 'Invalid update URL.');
            Response::redirect(View::url('admin'));
            return;
        }

        $result = \App\Services\UpdateService::applyUpdate($downloadUrl, $sha256 ?: null, $dbBackupMode);

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        Response::redirect(View::url('admin'));
    }

    /**
     * AJAX: Manual update check from settings page
     */
    public function checkUpdate(Request $request, array $params = []): void
    {
        // Clear cached check to force a fresh API call
        try {
            Database::query("DELETE FROM wk_settings WHERE setting_group='system_cache' AND setting_key='update_check'");
            Database::clearSettingsCache();
        } catch (\Exception $e) {}

        $update = \App\Services\UpdateService::check();
        if ($update) {
            Response::json([
                'available' => true,
                'version'   => $update['version'] ?? '',
                'changelog' => $update['changelog'] ?? '',
                'severity'  => $update['severity'] ?? 'normal',
            ]);
        } else {
            Response::json(['available' => false]);
        }
    }

    /**
     * Rollback to a previous backup
     */
    public function rollback(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin'));
            return;
        }

        $filename = $request->input('backup_file');
        if (!$filename) {
            Session::flash('error', 'No backup file specified.');
            Response::redirect(View::url('admin'));
            return;
        }

        $result = \App\Services\UpdateService::rollback($filename);

        if ($result['success']) {
            Session::flash('success', $result['message']);
        } else {
            Session::flash('error', $result['message']);
        }

        Response::redirect(View::url('admin'));
    }

    /**
     * Dismiss update notification (hides for specified hours)
     */
    public function dismissUpdate(Request $request, array $params = []): void
    {
        $version = $request->input('version') ?? '';
        $hours = max(1, min(168, (int)($request->input('hours') ?? 24))); // 1h min, 7d max
        if ($version) {
            try {
                $data = json_encode([
                    'version' => $version,
                    'until'   => time() + ($hours * 3600),
                ]);
                Database::query(
                    "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system_cache', 'dismissed_update', ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    [$data]
                );
            } catch (\Exception $e) {}
        }
        Response::json(['success' => true]);
    }
}