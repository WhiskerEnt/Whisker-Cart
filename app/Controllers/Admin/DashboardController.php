<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Response, Session};

class DashboardController
{
    public function index(Request $request, array $params = []): void
    {
        $currencySymbol = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';

        // Check for updates (cached, hits API once per day)
        $updateAvailable = null;
        try {
            $updateAvailable = \App\Services\UpdateService::check();
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