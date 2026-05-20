<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Response, Session, Validator};

class ShippingController
{
    public function __construct()
    {
        // Ensure table exists
        try {
            Database::query("SELECT 1 FROM wk_shipping_carriers LIMIT 1");
        } catch (\Exception $e) {
            Database::connect()->exec("CREATE TABLE IF NOT EXISTS wk_shipping_carriers (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                code VARCHAR(50),
                tracking_url_template VARCHAR(500),
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB");
        }
    }

    public function index(Request $request, array $params = []): void
    {
        $carriers = Database::fetchAll("SELECT * FROM wk_shipping_carriers ORDER BY name");
        View::render('admin/shipping/index', [
            'pageTitle' => 'Shipping Carriers',
            'carriers'  => $carriers,
        ], 'admin/layouts/main');
    }

    public function store(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/shipping'));
            return;
        }

        // L16: structured validation. The carrier `code` is intentionally
        // optional but, if present, must look like a short identifier.
        $v = new Validator($request->all(), [
            'name' => 'required|min:2|max:100',
            'code' => 'max:50',
        ]);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            Response::redirect(View::url('admin/shipping'));
            return;
        }
        $name = trim($request->input('name') ?? '');
        // M26 follow-up: reject javascript: et al on the tracking-URL
        // template before it can land in any <a href> at display time.
        $trackingTemplate = trim($request->input('tracking_url_template') ?? '');
        if ($trackingTemplate !== '' && !View::isSafeUrl($trackingTemplate)) {
            Session::flash('error', 'Tracking URL template must be http(s) or a relative path.');
            Response::redirect(View::url('admin/shipping'));
            return;
        }

        Database::insert('wk_shipping_carriers', [
            'name'                 => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'code'                 => $request->clean('code') ?? '',
            'tracking_url_template'=> $trackingTemplate,
            'is_active'            => 1,
        ]);

        Session::flash('success', 'Carrier "' . htmlspecialchars($name) . '" added!');
        Response::redirect(View::url('admin/shipping'));
    }

    public function update(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/shipping'));
            return;
        }

        // L16: same validation as store(). Without this, admin can clear the
        // name field and trigger a 500 on the NOT NULL constraint.
        $v = new Validator($request->all(), [
            'name' => 'required|min:2|max:100',
            'code' => 'max:50',
        ]);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            Response::redirect(View::url('admin/shipping'));
            return;
        }
        $trackingTemplate = trim($request->input('tracking_url_template') ?? '');
        if ($trackingTemplate !== '' && !View::isSafeUrl($trackingTemplate)) {
            Session::flash('error', 'Tracking URL template must be http(s) or a relative path.');
            Response::redirect(View::url('admin/shipping'));
            return;
        }

        Database::update('wk_shipping_carriers', [
            'name'                 => $request->clean('name'),
            'code'                 => $request->clean('code') ?? '',
            'tracking_url_template'=> $trackingTemplate,
            'is_active'            => $request->input('is_active') ? 1 : 0,
        ], 'id=?', [$params['id']]);

        Session::flash('success', 'Carrier updated!');
        Response::redirect(View::url('admin/shipping'));
    }

    public function delete(Request $request, array $params = []): void
    {
        Database::delete('wk_shipping_carriers', 'id=?', [$params['id']]);
        Session::flash('success', 'Carrier deleted.');
        Response::redirect(View::url('admin/shipping'));
    }

    public function settings(Request $request, array $params = []): void
    {
        $settings = [];
        $rows = Database::fetchAll("SELECT setting_group, setting_key, setting_value FROM wk_settings");
        foreach ($rows as $row) {
            $settings[$row['setting_group']][$row['setting_key']] = $row['setting_value'];
        }
        View::render('admin/shipping/settings', [
            'pageTitle' => 'Shipping Settings',
            'settings'  => $settings,
        ], 'admin/layouts/main');
    }

    public function updateSettings(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/shipping/settings'));
            return;
        }

        $fields = ['method','flat_rate','flat_rate_below','free_threshold','per_item','per_item_cap','weight_base','weight_per_kg'];
        foreach ($fields as $key) {
            $val = $request->input('shipping_' . $key);
            if ($val !== null) {
                Database::query(
                    "INSERT INTO wk_settings (setting_group,setting_key,setting_value) VALUES('shipping',?,?)
                     ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                    [$key, trim($val)]
                );
            }
        }

        // Carrier-specific rates
        foreach ($request->all() as $key => $val) {
            if (str_starts_with($key, 'shipping_carrier_rate_')) {
                $carrierId = substr($key, strlen('shipping_carrier_rate_'));
                Database::query(
                    "INSERT INTO wk_settings (setting_group,setting_key,setting_value) VALUES('shipping',?,?)
                     ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                    ['carrier_rate_' . $carrierId, trim($val)]
                );
            }
        }

        // Sync flat rate to checkout for backward compat
        $flat = $request->input('shipping_flat_rate') ?? '0';
        Database::query(
            "INSERT INTO wk_settings (setting_group,setting_key,setting_value) VALUES('checkout','shipping_flat_rate',?)
             ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
            [$flat]
        );
        // M10/M14: invalidate request-scoped settings cache.
        Database::clearSettingsCache();

        Session::flash('success', 'Shipping settings saved!');
        Response::redirect(View::url('admin/shipping/settings'));
    }
}