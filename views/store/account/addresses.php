<?php $url=fn($p)=>\Core\View::url($p); $e=fn($v)=>\Core\View::e($v);
$countries = \App\Services\CurrencyService::countries();
?>
<section class="wk-section"><div class="wk-container" style="max-width:700px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
        <h1 style="font-size:24px;font-weight:900">My Addresses</h1>
        <a href="<?= $url('account') ?>" style="font-size:13px;font-weight:700;color:var(--wk-purple-ink)">← Back to Account</a>
    </div>

    <!-- Existing Addresses -->
    <?php if (!empty($addresses)): ?>
    <div class="wk-cols-2" style="gap:16px;margin-bottom:24px">
        <?php foreach ($addresses as $addr): ?>
        <div style="background:var(--wk-surface);border:2px solid <?= $addr['is_default']?'var(--wk-purple)':'var(--wk-border)' ?>;border-radius:var(--radius);padding:20px;position:relative">
            <?php if ($addr['is_default']): ?><span style="position:absolute;top:10px;right:10px;font-size:10px;font-weight:800;background:var(--wk-purple-soft);color:var(--wk-purple-ink);padding:2px 8px;border-radius:10px">DEFAULT</span><?php endif; ?>
            <div class="wk-heavy wk-mb-4"><?= $e($addr['label']) ?></div>
            <div style="font-size:14px;color:var(--wk-muted);line-height:1.6">
                <?= $e($addr['address_line1']) ?><br>
                <?php if ($addr['address_line2']): ?><?= $e($addr['address_line2']) ?><br><?php endif; ?>
                <?= $e($addr['city']) ?>, <?= $e($addr['state']) ?> <?= $e($addr['postal_code']) ?><br>
                <?= $e(\App\Services\CountryService::name((string) ($addr['country'] ?? ''))) ?>
            </div>
            <form method="POST" action="<?= $url('account/addresses/delete/'.$addr['id']) ?>" style="margin-top:12px" onsubmit="return confirm('Delete this address?')">
                <?= \Core\Session::csrfField() ?>
                <button type="submit" style="background:none;border:none;color:var(--wk-red);font-size:13px;font-weight:700;cursor:pointer">Delete</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Add Address -->
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:28px">
        <h2 style="font-size:17px;font-weight:900;margin-bottom:20px">Add New Address</h2>
        <form method="POST" action="<?= $url('account/addresses/store') ?>">
            <?= \Core\Session::csrfField() ?>
            <div class="wk-cols-2" style="gap:14px">
                <div><label class="wk-field-label">Label</label><select name="label" class="wk-field-input" style="cursor:pointer"><option>Home</option><option>Work</option><option>Other</option></select></div>
                <div><label class="wk-field-label">Country</label><select name="country" class="wk-field-input" style="cursor:pointer">
                    <?php foreach ($countries as $code => $info): ?><option value="<?= $code ?>" <?= $code==='IN'?'selected':'' ?>><?= $e($info['name']) ?></option><?php endforeach; ?>
                </select></div>
            </div>
            <div class="wk-mt-14"><label class="wk-field-label">Address Line 1</label><input type="text" name="address_line1" required placeholder="Street address" class="wk-field-input"></div>
            <div class="wk-mt-14"><label class="wk-field-label">Address Line 2 <span style="font-weight:500;text-transform:none">(optional)</span></label><input type="text" name="address_line2" placeholder="Apartment, suite, etc." class="wk-field-input"></div>
            <div class="wk-cols-3" style="gap:14px;margin-top:14px">
                <div><label class="wk-field-label">City</label><input type="text" name="city" required class="wk-field-input"></div>
                <div><label class="wk-field-label">State</label><input type="text" name="state" required class="wk-field-input"></div>
                <div><label class="wk-field-label">Postal Code</label><input type="text" name="postal_code" required class="wk-field-input"></div>
            </div>
            <div class="wk-mt-14"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:14px"><input type="checkbox" name="is_default" value="1"> Set as default address</label></div>
            <button type="submit" class="wk-mt-20 wk-checkout-btn">Save Address</button>
        </form>
    </div>
</div></section>