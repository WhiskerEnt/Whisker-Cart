<?php
// Webhook route — closure form; see plugins/razorpay/routes.php for why.
$r->post("/callback", function (\Core\Request $request, array $params = []) {
    $gateway = \Core\PluginManager::loadGateway('nowpayments');
    if (!$gateway) { \Core\Response::json(['error' => 'gateway unavailable'], 503); return; }
    $gateway->webhook($request);
});
