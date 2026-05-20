<?php
$e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); $price=fn($v)=>\Core\View::price($v,$currency);
$o=$order;
$billing = json_decode($o['billing_address']??'{}', true) ?: [];
$shipping_addr = json_decode($o['shipping_address']??'{}', true) ?: [];
$notes = json_decode($o['notes']??'{}', true) ?: [];
$sm=['pending'=>['warning','⏳'],'processing'=>['info','🔄'],'paid'=>['success','✓'],'shipped'=>['purple','📦'],'delivered'=>['success','✅'],'cancelled'=>['danger','✗'],'refunded'=>['danger','↩']];
$s=$sm[$o['status']]??['info','?'];
?>

<a href="<?= $url('admin/orders') ?>" style="color:var(--wk-purple);font-weight:700;font-size:13px;text-decoration:none;margin-bottom:16px;display:inline-block">← Back to Orders</a>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
    <h2 style="font-size:20px;font-weight:900;font-family:var(--font-mono);color:var(--wk-purple)"><?= $e($o['order_number']) ?></h2>
    <span class="wk-badge wk-badge-<?= $s[0] ?>"><?= $s[1] ?> <?= ucfirst($o['status']) ?></span>
    <div style="margin-left:auto">
        <a href="<?= $url('admin/orders/invoice/'.$o['id']) ?>" target="_blank" class="wk-btn wk-btn-secondary wk-btn-sm">🧾 Invoice / Receipt</a>
    </div>
</div>

<div style="display:grid;grid-template-columns:1.6fr 1fr;gap:20px">
    <div>
        <!-- Items -->
        <div class="wk-card" style="margin-bottom:20px">
            <div class="wk-card-header"><h2>Items Ordered</h2></div>
            <table class="wk-table"><thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead><tbody>
            <?php foreach ($items as $i):
                $vLabel = $i['variant_label'] ?? '';
                if (empty($vLabel) && !empty($i['variant_combo_id'])) {
                    try { $vLabel = \Core\Database::fetchValue("SELECT label FROM wk_variant_combos WHERE id=?", [$i['variant_combo_id']]) ?: ''; } catch(\Exception $ex) {}
                }
            ?>
            <tr>
                <td>
                    <div style="font-weight:700"><?= $e($i['product_name']) ?></div>
                    <?php if ($vLabel): ?><div style="font-size:12px;color:var(--wk-purple);font-weight:700"><?= $e($vLabel) ?></div><?php endif; ?>
                    <?php if($i['product_sku']):?><code style="font-size:11px;color:var(--wk-text-muted)"><?= $e($i['product_sku']) ?></code><?php endif; ?>
                </td>
                <td style="font-weight:700"><?= $i['quantity'] ?></td>
                <td style="font-family:var(--font-mono)"><?= $price($i['unit_price']) ?></td>
                <td style="font-family:var(--font-mono);font-weight:700"><?= $price($i['total_price']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody></table>
        </div>

        <!-- Addresses -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
            <div class="wk-card">
                <div class="wk-card-header"><h2>📍 Billing Address</h2></div>
                <div class="wk-card-body" style="font-size:14px;line-height:1.8">
                    <strong><?= $e($billing['name']??'') ?></strong><br>
                    <?= $e($billing['line1']??'') ?><br>
                    <?= $e(($billing['city']??'').', '.($billing['state']??'').' '.($billing['zip']??'')) ?><br>
                    <?= $e($billing['country']??'') ?>
                </div>
            </div>
            <div class="wk-card">
                <div class="wk-card-header"><h2>🚚 Shipping Address</h2></div>
                <div class="wk-card-body" style="font-size:14px;line-height:1.8">
                    <strong><?= $e($shipping_addr['name']??'') ?></strong><br>
                    <?= $e($shipping_addr['line1']??'') ?><br>
                    <?= $e(($shipping_addr['city']??'').', '.($shipping_addr['state']??'').' '.($shipping_addr['zip']??'')) ?>
                </div>
            </div>
        </div>

        <!-- Shipping & Tracking -->
        <div class="wk-card">
            <div class="wk-card-header"><h2>📦 Shipping & Tracking</h2></div>
            <div class="wk-card-body">
                <?php if (!empty($notes['tracking_number'])): ?>
                    <div style="background:var(--wk-purple-soft);border-radius:8px;padding:16px;margin-bottom:16px">
                        <div style="font-size:12px;font-weight:800;text-transform:uppercase;color:var(--wk-purple);margin-bottom:8px">Current Tracking</div>
                        <div style="font-size:14px"><strong>Carrier:</strong> <?= $e($notes['shipping_carrier']??'') ?></div>
                        <div style="font-size:14px;font-family:var(--font-mono);font-weight:700;margin-top:4px"><?= $e($notes['tracking_number']) ?></div>
                        <?php if (!empty($notes['tracking_url'])): ?>
                            <a href="<?= \Core\View::safeUrl($notes['tracking_url']) ?>" target="_blank" rel="noopener noreferrer" style="color:var(--wk-purple);font-weight:700;font-size:13px;margin-top:8px;display:inline-block">Track Package ↗</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= $url('admin/orders/shipping/'.$o['id']) ?>">
                    <?= \Core\Session::csrfField() ?>
                    <div class="wk-form-group">
                        <label>Shipping Carrier</label>
                        <select name="shipping_carrier" class="wk-select" id="carrierSelect">
                            <option value="">Select carrier...</option>
                            <?php foreach ($carriers as $c): ?>
                                <option value="<?= $e($c['name']) ?>" <?= ($notes['shipping_carrier']??'')===$c['name']?'selected':'' ?>><?= $e($c['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="__new__">+ Add New Carrier</option>
                        </select>
                    </div>
                    <div id="newCarrierBox" style="display:none" class="wk-form-group">
                        <label>New Carrier Name</label>
                        <input type="text" name="new_carrier" class="wk-input" placeholder="e.g. BlueDart, Delhivery, FedEx">
                    </div>
                    <div class="wk-form-group">
                        <label>Tracking Number</label>
                        <input type="text" name="tracking_number" class="wk-input" value="<?= $e($notes['tracking_number']??'') ?>" placeholder="Enter tracking number">
                    </div>
                    <div class="wk-form-group">
                        <label>Tracking URL <span style="font-weight:400;text-transform:none">(optional)</span></label>
                        <input type="url" name="tracking_url" class="wk-input" value="<?= $e($notes['tracking_url']??'') ?>" placeholder="https://track.carrier.com/...">
                    </div>
                    <button type="submit" class="wk-btn wk-btn-primary" style="width:100%;justify-content:center">Update Shipping & Notify Customer 📧</button>
                </form>
            </div>
        </div>
    </div>

    <div>
        <!-- Summary -->
        <div class="wk-card" style="margin-bottom:20px">
            <div class="wk-card-header"><h2>Summary</h2></div>
            <div class="wk-card-body" style="font-size:14px">
                <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="color:var(--wk-text-muted)">Subtotal</span><span style="font-weight:700"><?= $price($o['subtotal']) ?></span></div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="color:var(--wk-text-muted)">Tax</span><span style="font-weight:700"><?= $price($o['tax_amount']) ?></span></div>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="color:var(--wk-text-muted)">Shipping</span><span style="font-weight:700"><?= $price($o['shipping_amount']) ?></span></div>
                <?php if ($o['discount_amount'] > 0): ?>
                <div style="display:flex;justify-content:space-between;margin-bottom:8px"><span style="color:var(--wk-green)">Discount</span><span style="font-weight:700;color:var(--wk-green)">-<?= $price($o['discount_amount']) ?></span></div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;padding-top:12px;border-top:2px solid var(--wk-border);font-size:20px"><span style="font-weight:900">Total</span><span style="font-weight:900;font-family:var(--font-mono)"><?= $price($o['total']) ?></span></div>
            </div>
        </div>

        <!-- Customer Info -->
        <div class="wk-card" style="margin-bottom:20px">
            <div class="wk-card-header"><h2>Customer</h2></div>
            <div class="wk-card-body" style="font-size:14px">
                <div style="margin-bottom:6px"><strong>Email:</strong> <?= $e($o['customer_email']??'') ?></div>
                <div style="margin-bottom:6px"><strong>Phone:</strong> <?= $e($o['customer_phone']??'—') ?></div>
                <div style="margin-bottom:6px"><strong>IP:</strong> <code style="font-size:12px"><?= $e($o['ip_address']??'') ?></code></div>
            </div>
        </div>

        <!-- Status Update -->
        <div class="wk-card">
            <div class="wk-card-header"><h2>Update Status</h2></div>
            <div class="wk-card-body">
                <form method="POST" action="<?= $url('admin/orders/status/'.$o['id']) ?>">
                    <?= \Core\Session::csrfField() ?>
                    <select name="status" class="wk-select" style="margin-bottom:12px">
                        <?php foreach (['pending','processing','paid','shipped','delivered','cancelled','refunded'] as $st): ?>
                            <option value="<?= $st ?>" <?= $o['status']===$st?'selected':'' ?>><?= ucfirst($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="wk-btn wk-btn-secondary" style="width:100%;justify-content:center">Update Status</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('carrierSelect').addEventListener('change', function() {
    document.getElementById('newCarrierBox').style.display = this.value === '__new__' ? 'block' : 'none';
});
</script>