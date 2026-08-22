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
            <?php
            // Addresses store the ISO code; a customer service rep reading this
            // page wants "India", not "IN".
            $country = fn($code) => $code !== '' ? \App\Services\CountryService::name((string) $code) : '';
            ?>
            <div class="wk-card">
                <div class="wk-card-header"><h2>📍 Billing Address</h2></div>
                <div class="wk-card-body" style="font-size:14px;line-height:1.8">
                    <strong><?= $e($billing['name']??'') ?></strong><br>
                    <?= $e($billing['line1']??'') ?><br>
                    <?php if (!empty($billing['line2'])): ?><?= $e($billing['line2']) ?><br><?php endif; ?>
                    <?= $e(($billing['city']??'').', '.($billing['state']??'').' '.($billing['zip']??'')) ?><br>
                    <?= $e($country($billing['country'] ?? '')) ?>
                </div>
            </div>
            <?php $isPickup = !empty($shipping_addr['pickup']); ?>
            <div class="wk-card">
                <div class="wk-card-header"><h2><?= $isPickup ? '📦 Pickup Point' : '🚚 Shipping Address' ?></h2></div>
                <div class="wk-card-body" style="font-size:14px;line-height:1.8">
                    <?php if ($isPickup): ?><span class="wk-badge wk-badge-purple" style="margin-bottom:6px;display:inline-block">Locker / collection point</span><br><?php endif; ?>
                    <strong><?= $e($shipping_addr['name']??'') ?></strong><br>
                    <?= $e($shipping_addr['line1']??'') ?><br>
                    <?php if (!empty($shipping_addr['line2'])): ?><?= $e($shipping_addr['line2']) ?><br><?php endif; ?>
                    <?= $e(($shipping_addr['city']??'').', '.($shipping_addr['state']??'').' '.($shipping_addr['zip']??'')) ?><br>
                    <?= $e($country($shipping_addr['country'] ?? '')) ?>
                    <?php if ($isPickup && !empty($shipping_addr['opening_hours'])): ?>
                        <br><span style="color:var(--wk-text-muted);font-size:13px">🕐 <?= $e($shipping_addr['opening_hours']) ?></span>
                    <?php endif; ?>
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

        <!-- Refunds -->
        <?php
        $curCode  = $o['currency'] ?: 'INR';
        $money    = fn($v) => \App\Services\CurrencyService::format((float) $v, $curCode);
        $refunded = array_sum(array_map(
            fn($r) => in_array($r['status'], ['completed','pending','unknown'], true) ? (float) $r['amount'] : 0,
            $refunds ?? []
        ));
        $gwName        = $o['payment_gateway'] ?: '';
        $apiRefundable = in_array($gwName, ['stripe','razorpay'], true);
        // An unpaid order has nothing to send back, so the panel stays away
        // rather than offering a form that would be refused.
        $orderWasPaid  = in_array($o['payment_status'] ?? '', ['captured','authorized','partially_refunded','refunded'], true);
        ?>
        <?php if ($orderWasPaid || $refunds): ?>
        <div class="wk-card">
            <div class="wk-card-header"><h2>Refunds</h2></div>
            <div class="wk-card-body">
                <?php if ($refunds): ?>
                <div style="margin-bottom:16px">
                    <?php foreach ($refunds as $r):
                        $tone = ['completed'=>'var(--wk-green)','pending'=>'var(--wk-yellow)',
                                 'unknown'=>'var(--wk-red)','failed'=>'var(--wk-text-muted)'][$r['status']] ?? 'var(--wk-text-muted)';
                    ?>
                    <div style="padding:10px 0;border-bottom:1px solid var(--wk-border)">
                        <div style="display:flex;justify-content:space-between;gap:10px;align-items:baseline">
                            <code style="font-size:12px;font-weight:700"><?= $e($r['refund_ref']) ?></code>
                            <strong style="font-family:var(--font-mono)"><?= $e($money($r['amount'])) ?></strong>
                        </div>
                        <div style="display:flex;justify-content:space-between;gap:10px;margin-top:3px">
                            <span style="font-size:11px;font-weight:800;color:<?= $tone ?>;text-transform:uppercase">
                                <?= $e($r['status']) ?><?= $r['is_manual'] ? ' &middot; by hand' : '' ?>
                            </span>
                            <span style="font-size:11px;color:var(--wk-text-muted)"><?= $e(date('j M Y, H:i', strtotime($r['created_at']))) ?></span>
                        </div>
                        <?php if ($r['gateway_refund_id']): ?>
                            <div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">
                                Gateway ID <code><?= $e($r['gateway_refund_id']) ?></code>
                            </div>
                        <?php endif; ?>
                        <?php if ($r['reason']): ?>
                            <div style="font-size:12px;margin-top:4px"><?= $e($r['reason']) ?></div>
                        <?php endif; ?>
                        <?php if ($r['message'] && $r['status'] !== 'completed'): ?>
                            <div style="font-size:11px;color:<?= $tone ?>;margin-top:4px"><?= $e($r['message']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <div style="display:flex;justify-content:space-between;padding-top:10px;font-size:13px;font-weight:800">
                        <span>Refunded</span><span style="font-family:var(--font-mono)"><?= $e($money($refunded)) ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($refundBlocked)): ?>
                    <div style="background:#fee2e2;color:var(--wk-red);border-radius:var(--radius-sm);padding:12px 14px;font-size:12px;line-height:1.6">
                        <strong>An earlier refund was never confirmed.</strong>
                        The money may already have reached the customer. Check the gateway dashboard,
                        then resolve that refund before sending another.
                    </div>
                <?php elseif (($refundable ?? 0) <= 0): ?>
                    <p style="font-size:13px;color:var(--wk-text-muted);margin:0">
                        <?= $refunds ? 'This order is fully refunded.' : 'Nothing left to refund on this order.' ?>
                    </p>
                <?php else: ?>
                    <form method="POST" action="<?= $url('admin/orders/refund/'.$o['id']) ?>" id="refundForm">
                        <?= \Core\Session::csrfField() ?>
                        <div class="wk-form-group">
                            <label>Amount <span style="font-weight:500;color:var(--wk-text-muted)">(up to <?= $e($money($refundable)) ?>)</span></label>
                            <input type="number" name="amount" class="wk-input" step="0.01" min="0.01"
                                   max="<?= $e(number_format((float) $refundable, 2, '.', '')) ?>"
                                   value="<?= $e(number_format((float) $refundable, 2, '.', '')) ?>" required>
                        </div>
                        <div class="wk-form-group">
                            <label>Reason <span style="font-weight:500;color:var(--wk-text-muted)">(for your records)</span></label>
                            <input type="text" name="reason" class="wk-input" maxlength="255" placeholder="Returned damaged">
                        </div>
                        <?php if (!$apiRefundable): ?>
                            <input type="hidden" name="manual" value="1">
                            <div style="background:var(--wk-bg);border-radius:var(--radius-sm);padding:11px 13px;font-size:12px;color:var(--wk-text-muted);margin-bottom:12px;line-height:1.6">
                                <?php if ($gwName === 'nowpayments'): ?>
                                    Crypto payments cannot be reversed automatically. Send the refund from your wallet first, then record it here.
                                <?php elseif ($gwName === 'ccavenue'): ?>
                                    CCAvenue refunds are issued from the CCAvenue panel. Refund there first, then record it here.
                                <?php else: ?>
                                    No gateway payment is attached to this order, so this is recorded as a refund you made by hand.
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <label style="display:flex;align-items:flex-start;gap:8px;font-size:12px;margin-bottom:12px;cursor:pointer;line-height:1.5">
                                <input type="checkbox" name="manual" value="1" style="margin-top:2px">
                                <span>I already refunded this by hand &mdash; record it without contacting <?= $e(ucfirst($gwName)) ?>.</span>
                            </label>
                        <?php endif; ?>
                        <button type="submit" class="wk-btn wk-btn-primary" style="width:100%;justify-content:center">
                            Refund <?= $e($money($refundable)) ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="wk-modal" id="refundModal" hidden>
            <div class="wk-modal-backdrop" data-refund-cancel></div>
            <div class="wk-modal-box" role="dialog" aria-modal="true" aria-labelledby="refundModalTitle">
                <h3 class="wk-modal-title" id="refundModalTitle">Send this refund?</h3>
                <p class="wk-modal-text" id="refundModalText"></p>
                <dl class="wk-modal-facts">
                    <div><dt>Amount</dt><dd id="refundModalAmount"></dd></div>
                    <div><dt>Order</dt><dd><?= $e($o['order_number']) ?></dd></div>
                    <div><dt>Customer</dt><dd><?= $e($o['customer_email'] ?? '') ?></dd></div>
                </dl>
                <div class="wk-modal-actions">
                    <button type="button" class="wk-btn wk-btn-secondary" data-refund-cancel>Cancel</button>
                    <button type="button" class="wk-btn wk-btn-primary" id="refundModalConfirm">Send refund</button>
                </div>
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
(function () {
    var form = document.getElementById('refundForm');
    if (!form) return;

    var amount  = form.querySelector('[name=amount]');
    var manual  = form.querySelector('[name=manual][type=checkbox]');
    var button  = form.querySelector('button[type=submit]');
    var symbol  = button.textContent.trim().replace(/^Refund\s*/, '').replace(/[0-9.,\s]/g, '');
    var modal   = document.getElementById('refundModal');
    var box     = modal.querySelector('.wk-modal-box');
    var title   = document.getElementById('refundModalTitle');
    var text    = document.getElementById('refundModalText');
    var shown   = document.getElementById('refundModalAmount');
    var confirm = document.getElementById('refundModalConfirm');
    var lastFocus = null;
    var approved = false;

    function money(v) { return symbol + (isNaN(v) ? '0.00' : v.toFixed(2)); }

    amount.addEventListener('input', function () {
        button.textContent = 'Refund ' + money(parseFloat(amount.value || '0'));
    });

    function open() {
        var byHand = manual && manual.checked;
        var v = parseFloat(amount.value || '0');
        title.textContent = byHand ? 'Record this refund?' : 'Send this refund?';
        text.textContent  = byHand
            ? 'This only writes the refund down. No payment gateway is contacted.'
            : 'The money leaves your account straight away and cannot be undone from here.';
        shown.textContent = money(v);
        confirm.textContent = byHand ? 'Record refund' : 'Send refund';

        lastFocus = document.activeElement;
        modal.hidden = false;
        requestAnimationFrame(function () { modal.classList.add('is-open'); });
        confirm.focus();
        document.addEventListener('keydown', onKey);
    }

    function close() {
        modal.classList.remove('is-open');
        document.removeEventListener('keydown', onKey);
        setTimeout(function () { modal.hidden = true; }, 180);
        if (lastFocus) lastFocus.focus();
    }

    function onKey(e) {
        if (e.key === 'Escape') { close(); return; }
        if (e.key !== 'Tab') return;
        // Keep focus inside the dialog while it is open.
        var focusable = box.querySelectorAll('button');
        var first = focusable[0], last = focusable[focusable.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }

    // Money leaving the account deserves a deliberate second action.
    form.addEventListener('submit', function (e) {
        if (approved) return;
        e.preventDefault();
        open();
    });

    confirm.addEventListener('click', function () {
        approved = true;
        close();
        button.disabled = true;
        button.textContent = (manual && manual.checked) ? 'Recording...' : 'Refunding...';
        form.submit();
    });

    modal.querySelectorAll('[data-refund-cancel]').forEach(function (el) {
        el.addEventListener('click', close);
    });
})();

document.getElementById('carrierSelect').addEventListener('change', function() {
    document.getElementById('newCarrierBox').style.display = this.value === '__new__' ? 'block' : 'none';
});
</script>