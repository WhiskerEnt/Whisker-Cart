<?php
$e = fn($v) => \Core\View::e($v);
$url = fn($p) => \Core\View::url($p);

$icons = ['razorpay'=>'⚡','ccavenue'=>'🏦','stripe'=>'💳','nowpayments'=>'₿'];

$fields = [
    'razorpay' => [
        ['key_id','Key ID','text'], ['key_secret','Key Secret','password'],
        ['webhook_secret','Webhook Secret','password'],
        ['test_key_id','Test Key ID','text'], ['test_key_secret','Test Key Secret','password'],
    ],
    'ccavenue' => [
        ['merchant_id','Merchant ID','text'], ['access_code','Access Code','text'],
        ['working_key','Working Key','password'],
        ['test_merchant_id','Test Merchant ID','text'], ['test_access_code','Test Access Code','text'],
        ['test_working_key','Test Working Key','password'],
    ],
    'stripe' => [
        ['publishable_key','Publishable Key','text'], ['secret_key','Secret Key','password'],
        ['webhook_secret','Webhook Secret','password'],
        ['test_publishable_key','Test Publishable Key','text'], ['test_secret_key','Test Secret Key','password'],
    ],
    'nowpayments' => [
        ['api_key','API Key','password'], ['ipn_secret','IPN Secret','password'],
        ['test_api_key','Sandbox API Key','password'],
    ],
];
?>

<p style="color:var(--wk-text-muted);margin-bottom:24px;font-weight:600">
    Configure and activate payment gateways. Use test mode while developing, switch to live when ready.
</p>

<div class="wk-gateway-grid">
    <?php foreach ($gateways as $gw):
        $code   = $gw['gateway_code'];
        $config = json_decode($gw['config'], true) ?? [];
        $icon   = $icons[$code] ?? '💰';
        $currencies = json_decode($gw['supported_currencies'] ?? '[]', true);
    ?>
    <div class="wk-gateway-card <?= $gw['is_active'] ? 'active' : '' ?>" style="position:relative">
        <?php if ($gw['is_active']): ?>
            <div style="position:absolute;top:0;left:16px;right:16px;height:3px;background:var(--wk-green);border-radius:0 0 3px 3px"></div>
        <?php endif; ?>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
            <h3 style="font-size:17px;font-weight:900"><?= $e($gw['display_name']) ?></h3>
            <div style="width:44px;height:44px;border-radius:10px;background:var(--wk-bg);display:flex;align-items:center;justify-content:center;font-size:20px"><?= $icon ?></div>
        </div>

        <p style="font-size:13px;color:var(--wk-text-muted);margin-bottom:14px;line-height:1.5"><?= $e($gw['description']) ?></p>

        <?php if ($currencies): ?>
        <div style="display:flex;gap:5px;flex-wrap:wrap;margin-bottom:14px">
            <?php foreach ($currencies as $cur): ?>
                <span style="font-size:10px;font-family:var(--font-mono);font-weight:700;padding:2px 6px;background:var(--wk-bg);border-radius:4px;color:var(--wk-text-muted)"><?= $cur ?></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Toggle + Mode -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding-top:14px;border-top:1px solid var(--wk-border)">
            <form method="POST" action="<?= $url('admin/gateways/toggle') ?>" style="display:flex;align-items:center;gap:8px">
                <?= \Core\Session::csrfField() ?>
                <input type="hidden" name="gateway_code" value="<?= $code ?>">
                <input type="hidden" name="is_active" value="<?= $gw['is_active'] ? 0 : 1 ?>">
                <label class="wk-toggle">
                    <input type="checkbox" <?= $gw['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()">
                    <span class="wk-toggle-slider"></span>
                </label>
                <span style="font-size:12px;font-weight:700;color:var(--wk-text-muted)"><?= $gw['is_active'] ? 'Active' : 'Inactive' ?></span>
            </form>
            <span class="wk-badge <?= $gw['is_test_mode'] ? 'wk-badge-warning' : 'wk-badge-success' ?>">
                <?= $gw['is_test_mode'] ? '🧪 Test' : '🟢 Live' ?>
            </span>
        </div>

        <!-- Configure -->
        <details style="margin-top:16px;padding-top:16px;border-top:1px solid var(--wk-border)">
            <summary style="font-size:13px;font-weight:800;color:var(--wk-purple);cursor:pointer;list-style:none;display:flex;align-items:center;gap:6px">
                ⚙ Configure
            </summary>
            <form method="POST" action="<?= $url('admin/gateways/configure') ?>" style="margin-top:16px">
                <?= \Core\Session::csrfField() ?>
                <input type="hidden" name="gateway_code" value="<?= $code ?>">

                <div style="display:flex;gap:16px;margin-bottom:16px">
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;cursor:pointer">
                        <input type="checkbox" name="is_active" value="1" <?= $gw['is_active']?'checked':'' ?>> Enabled
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;font-weight:700;cursor:pointer">
                        <input type="checkbox" name="is_test_mode" value="1" <?= $gw['is_test_mode']?'checked':'' ?>> Test Mode
                    </label>
                </div>

                <?php foreach ($fields[$code] ?? [] as [$key, $label, $type]):
                    // Stored secrets are never rendered back into the page.
                    // Secret fields show empty; submitting empty keeps the
                    // saved value (see GatewayController::configure).
                    $isSecret = ($type === 'password');
                    $hasStored = ($config[$key] ?? '') !== '';
                ?>
                <div class="wk-form-group">
                    <label><?= $label ?></label>
                    <input type="<?= $type ?>" name="cfg_<?= $key ?>" class="wk-input"
                           value="<?= $isSecret ? '' : $e($config[$key] ?? '') ?>"
                           placeholder="<?= $isSecret && $hasStored ? '•••••••• (saved — leave blank to keep)' : 'Enter ' . strtolower($label) ?>" autocomplete="off">
                </div>
                <?php endforeach; ?>

                <div style="background:var(--wk-blue-soft);border-radius:var(--radius-sm);padding:10px 14px;font-size:11px;color:var(--wk-blue);margin-bottom:16px">
                    <strong>Webhook URL:</strong><br>
                    <code style="font-family:var(--font-mono);font-size:11px"><?= $url('webhook/'.$code) ?></code>
                </div>

                <button type="submit" class="wk-btn wk-btn-primary" style="width:100%;justify-content:center">Save <?= $e($gw['display_name']) ?> Settings</button>
            </form>
        </details>
    </div>
    <?php endforeach; ?>
</div>
