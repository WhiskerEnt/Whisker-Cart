<?php
namespace App\Controllers\Admin;
use Core\{Request, View, Database, Response, Session, PluginManager};

class GatewayController
{
    public function index(Request $request, array $params = []): void
    {
        $gateways = Database::fetchAll("SELECT * FROM wk_payment_gateways ORDER BY sort_order");
        $plugins = PluginManager::gateways();
        View::render('admin/gateways/index', [
            'pageTitle'=>'Payment Gateways', 'gateways'=>$gateways, 'plugins'=>$plugins,
        ], 'admin/layouts/main');
    }

    public function toggle(Request $request, array $params = []): void
    {
        // Group middleware enforces CSRF, but this method has been historically
        // accessed via both form submit and AJAX — verify explicitly so the
        // method is safe on its own regardless of routing context (H2).
        if (!Session::verifyCsrf($request->input('wk_csrf') ?? $request->server('HTTP_X_CSRF_TOKEN'))) {
            if ($request->isAjax()) {
                Response::json(['success'=>false, 'error'=>'Session expired.'], 403);
                return;
            }
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/gateways'));
            return;
        }
        $code = $request->clean('gateway_code');
        $active = (int)$request->input('is_active');
        Database::update('wk_payment_gateways', ['is_active'=>$active], 'gateway_code=?', [$code]);

        if ($request->isAjax()) {
            Response::json(['success'=>true, 'message'=>ucfirst($code).' '.($active?'activated':'deactivated')]);
        }
        Session::flash('success', ucfirst($code).' '.($active?'activated':'deactivated'));
        Response::redirect(View::url('admin/gateways'));
    }

    /**
     * Check a gateway's saved credentials against the provider and report back.
     */
    public function test(Request $request, array $params = []): void
    {
        $code = preg_replace('/[^a-z0-9_]/', '', (string)($params['code'] ?? ''));
        $gw = Database::fetch("SELECT gateway_code FROM wk_payment_gateways WHERE gateway_code=?", [$code]);
        if (!$gw) { Response::json(['success' => false, 'message' => 'Unknown gateway.'], 404); return; }

        try {
            $gateway = \Core\PluginManager::loadGateway($code);
            if (!$gateway) { Response::json(['success' => false, 'message' => 'Gateway plugin could not be loaded.']); return; }
            $result = $gateway->testConnection();
        } catch (\Throwable $e) {
            Response::json(['success' => false, 'message' => 'Test failed to run.']);
            return;
        }

        Response::json([
            'success' => (bool)($result['success'] ?? false),
            'message' => (string)($result['message'] ?? ''),
        ]);
    }

    public function configure(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error','Session expired.');
            Response::redirect(View::url('admin/gateways'));
            return;
        }
        $code = $request->clean('gateway_code');
        $gw = Database::fetch("SELECT id,config FROM wk_payment_gateways WHERE gateway_code=?", [$code]);
        if (!$gw) { Response::redirect(View::url('admin/gateways')); return; }

        $config = json_decode($gw['config'], true) ?? [];
        foreach ($request->all() as $key => $value) {
            if (str_starts_with($key, 'cfg_')) {
                $name  = substr($key, 4);
                $value = trim($value);
                // Secret fields render empty, so an empty submission means
                // "unchanged" — keep the saved credential.
                if ($value === '' && ($config[$name] ?? '') !== '') continue;
                $config[$name] = $value;
            }
        }
        Database::update('wk_payment_gateways', [
            'is_active'   => $request->input('is_active') ? 1 : 0,
            'is_test_mode'=> $request->input('is_test_mode') ? 1 : 0,
            'config'      => json_encode($config),
        ], 'gateway_code=?', [$code]);

        Session::flash('success', ucfirst($code).' settings saved.');
        Response::redirect(View::url('admin/gateways'));
    }
}
