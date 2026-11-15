<?php $url=fn($p)=>\Core\View::url($p); ?>
<form method="POST" action="<?= $url('admin/coupons/store') ?>">
    <?= \Core\Session::csrfField() ?>
    <div class="wk-card" style="max-width:600px">
        <div class="wk-card-header"><h2>New Coupon</h2></div>
        <div class="wk-card-body">
            <div class="wk-form-group"><label>Coupon Code</label><input type="text" name="code" class="wk-input" required placeholder="SUMMER20" style="text-transform:uppercase"></div>
            <div class="wk-grid-2">
                <div class="wk-form-group"><label>Type</label><select name="type" class="wk-select"><option value="percentage">Percentage (%)</option><option value="fixed">Fixed Amount</option></select></div>
                <div class="wk-form-group"><label>Value</label><input type="number" step="0.01" name="value" class="wk-input" required placeholder="20"></div>
            </div>
            <div class="wk-grid-2">
                <div class="wk-form-group"><label>Min Order Amount</label><input type="number" step="0.01" name="min_order_amount" class="wk-input" placeholder="0"></div>
                <div class="wk-form-group"><label>Max Discount</label><input type="number" step="0.01" name="max_discount" class="wk-input" placeholder="No limit"></div>
            </div>
            <div class="wk-grid-2">
                <div class="wk-form-group"><label>Usage Limit</label><input type="number" name="usage_limit" class="wk-input" placeholder="Unlimited"></div>
                <div class="wk-form-group"><label>Expires At</label><input type="date" name="expires_at" class="wk-input"></div>
            </div>
            <div style="display:flex;gap:12px;margin-top:8px">
                <a href="<?= $url('admin/coupons') ?>" class="wk-btn-grow wk-btn wk-btn-secondary">Cancel</a>
                <button type="submit" class="wk-btn-grow wk-btn wk-btn-primary">Create Coupon</button>
            </div>
        </div>
    </div>
</form>
