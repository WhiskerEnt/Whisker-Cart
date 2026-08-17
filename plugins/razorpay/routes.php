<?php
// Webhook route. Registered as a closure: the gateway class is not on the
// autoloader's path and its constructor needs the gateway code, so the
// router's `new $class()` dispatch path can never instantiate it — the old
// [\RazorpayGateway::class, "webhook"] form fataled on every webhook.
$r->post("/callback", function (\Core\Request $request, array $params = []) {
    $gateway = \Core\PluginManager::loadGateway('razorpay');
    if (!$gateway) { \Core\Response::json(['error' => 'gateway unavailable'], 503); return; }
    $gateway->webhook($request);
});
