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
        // Pickup locations (lockers / collection points) — same on-demand
        // bootstrap pattern, so the admin UI works even before the v1.3.2
        // migration has run.
        try {
            Database::query("SELECT 1 FROM wk_pickup_locations LIMIT 1");
        } catch (\Exception $e) {
            Database::connect()->exec("CREATE TABLE IF NOT EXISTS wk_pickup_locations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                carrier VARCHAR(100) DEFAULT '',
                address_line1 VARCHAR(255) NOT NULL,
                city VARCHAR(100) NOT NULL,
                state VARCHAR(100) DEFAULT '',
                zip VARCHAR(20) DEFAULT '',
                country CHAR(2) NOT NULL DEFAULT 'IN',
                opening_hours VARCHAR(255) DEFAULT '',
                fee DECIMAL(12,2) DEFAULT NULL,
                is_active TINYINT(1) DEFAULT 1,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_active (is_active)
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

        // The carrier `code` is intentionally optional but, if present,
        // must look like a short identifier.
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
        // The tracking-URL template is rendered into <a href> at display
        // time, so only http(s) or relative URLs are accepted.
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

        // Same validation as store(); name is NOT NULL in the schema.
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
        $pickupLocations = [];
        try {
            $pickupLocations = Database::fetchAll("SELECT * FROM wk_pickup_locations ORDER BY sort_order, name");
        } catch (\Exception $e) {}
        View::render('admin/shipping/settings', [
            'pageTitle'       => 'Shipping Settings',
            'settings'        => $settings,
            'pickupLocations' => $pickupLocations,
        ], 'admin/layouts/main');
    }

    public function updateSettings(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/shipping/settings'));
            return;
        }

        $fields = ['method','flat_rate','flat_rate_below','free_threshold','per_item','per_item_cap','weight_base','weight_per_kg','pickup_enabled','pickup_fee','ship_mode'];
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

        // Chosen destinations. Stored as one comma-separated list of ISO codes,
        // validated here so nothing outside the known set is written. Only
        // rewritten when the form actually carried the picker, so saving from
        // another mode does not wipe a list the shopkeeper built earlier.
        if ($request->input('shipping_ship_mode') === \App\Services\CountryService::MODE_SELECTED) {
            $picked = (array) ($request->all()['ship_country'] ?? []);
            $codes = [];
            foreach ($picked as $code) {
                $code = strtoupper(trim((string) $code));
                if (\App\Services\CountryService::exists($code)) $codes[$code] = true;
            }
            Database::query(
                "INSERT INTO wk_settings (setting_group,setting_key,setting_value) VALUES('shipping','ship_countries',?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                [implode(',', array_keys($codes))]
            );
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
        // Invalidate the request-scoped settings cache.
        Database::clearSettingsCache();

        Session::flash('success', 'Shipping settings saved!');
        Response::redirect(View::url('admin/shipping/settings'));
    }

    // ── Pickup points (lockers / collection points) ──────────────────

    public function pickupStore(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/shipping/settings'));
            return;
        }

        $v = new Validator($request->all(), [
            'name'          => 'required|min:2|max:150',
            'address_line1' => 'required|min:3|max:255',
            'city'          => 'required|min:1|max:100',
        ]);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            Response::redirect(View::url('admin/shipping/settings'));
            return;
        }

        $fee = trim((string)($request->input('fee') ?? ''));
        Database::insert('wk_pickup_locations', [
            'name'          => $request->clean('name'),
            'carrier'       => $request->clean('carrier') ?? '',
            'address_line1' => $request->clean('address_line1'),
            'city'          => $request->clean('city'),
            'state'         => $request->clean('state') ?? '',
            'zip'           => $request->clean('zip') ?? '',
            'country'       => strtoupper(substr(trim((string)($request->input('country') ?? 'IN')), 0, 2)),
            'opening_hours' => $request->clean('opening_hours') ?? '',
            'fee'           => $fee === '' ? null : round((float)$fee, 2),
            'is_active'     => 1,
            'sort_order'    => (int)($request->input('sort_order') ?? 0),
        ]);

        Session::flash('success', 'Pickup point added!');
        Response::redirect(View::url('admin/shipping/settings'));
    }

    public function pickupUpdate(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/shipping/settings'));
            return;
        }

        $v = new Validator($request->all(), [
            'name'          => 'required|min:2|max:150',
            'address_line1' => 'required|min:3|max:255',
            'city'          => 'required|min:1|max:100',
        ]);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            Response::redirect(View::url('admin/shipping/settings'));
            return;
        }

        $fee = trim((string)($request->input('fee') ?? ''));
        Database::update('wk_pickup_locations', [
            'name'          => $request->clean('name'),
            'carrier'       => $request->clean('carrier') ?? '',
            'address_line1' => $request->clean('address_line1'),
            'city'          => $request->clean('city'),
            'state'         => $request->clean('state') ?? '',
            'zip'           => $request->clean('zip') ?? '',
            'country'       => strtoupper(substr(trim((string)($request->input('country') ?? 'IN')), 0, 2)),
            'opening_hours' => $request->clean('opening_hours') ?? '',
            'fee'           => $fee === '' ? null : round((float)$fee, 2),
            'is_active'     => $request->input('is_active') ? 1 : 0,
            'sort_order'    => (int)($request->input('sort_order') ?? 0),
        ], 'id=?', [$params['id']]);

        Session::flash('success', 'Pickup point updated!');
        Response::redirect(View::url('admin/shipping/settings'));
    }

    public function pickupDelete(Request $request, array $params = []): void
    {
        Database::delete('wk_pickup_locations', 'id=?', [$params['id']]);
        Session::flash('success', 'Pickup point deleted.');
        Response::redirect(View::url('admin/shipping/settings'));
    }
}