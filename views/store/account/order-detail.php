<?php $url=fn($p)=>\Core\View::url($p); $e=fn($v)=>\Core\View::e($v); $price=fn($v)=>\Core\View::price($v); $o=$order;
$notes=json_decode($o['notes']??'{}',true)?:[];
$billing=json_decode($o['billing_address']??'{}',true)?:[];
$shipping_addr=json_decode($o['shipping_address']??'{}',true)?:[];
$canCancel = in_array($o['status'], ['pending', 'processing']);
$countries = \App\Services\CurrencyService::countries();
?>
<section class="wk-section"><div class="wk-container" style="max-width:700px">
    <a href="<?= $url('account/orders') ?>" style="color:var(--wk-purple);font-weight:700;font-size:13px;margin-bottom:16px;display:inline-block">← My Orders</a>

    <!-- Order Header — always visible -->
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:24px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;align-items:center">
            <div>
                <div style="font-family:var(--font-mono);font-size:18px;font-weight:900"><?= $e($o['order_number']) ?></div>
                <div style="font-size:13px;color:var(--wk-muted);margin-top:2px"><?= date('M j, Y g:i A', strtotime($o['created_at'])) ?></div>
            </div>
            <div style="text-align:right">
                <span style="display:inline-block;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:800;text-transform:uppercase;
                    background:<?= $o['status']==='cancelled'?'#fee2e2':($o['status']==='delivered'?'#d1fae5':'#ede9fe') ?>;
                    color:<?= $o['status']==='cancelled'?'#ef4444':($o['status']==='delivered'?'#10b981':'#8b5cf6') ?>">
                    <?= ucfirst($o['status']) ?>
                </span>
                <div style="font-family:var(--font-mono);font-size:18px;font-weight:900;margin-top:6px"><?= $price($o['total']) ?></div>
            </div>
        </div>
    </div>

    <!-- Items — always visible -->
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);overflow:hidden;margin-bottom:16px">
        <div style="padding:16px 22px;border-bottom:1px solid var(--wk-border);font-weight:800;font-size:14px">Items (<?= count($items) ?>)</div>
        <?php foreach ($items as $i): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:14px 22px;border-bottom:1px solid var(--wk-border)">
            <a href="<?= $i['slug'] ? $url('product/'.$i['slug']) : '#' ?>" style="flex-shrink:0">
                <div style="width:56px;height:56px;border-radius:8px;overflow:hidden;background:var(--wk-bg);display:flex;align-items:center;justify-content:center">
                    <?php if (!empty($i['image'])): ?>
                        <img src="<?= $url('storage/uploads/products/'.$i['image']) ?>" style="width:100%;height:100%;object-fit:cover" alt="">
                    <?php else: ?>
                        <span style="font-size:20px;opacity:.3">📦</span>
                    <?php endif; ?>
                </div>
            </a>
            <div style="flex:1">
                <a href="<?= $i['slug'] ? $url('product/'.$i['slug']) : '#' ?>" style="font-weight:700;font-size:14px;color:var(--wk-text);text-decoration:none"><?= $e($i['product_name']) ?></a>
                <div style="font-size:12px;color:var(--wk-muted)">Qty: <?= $i['quantity'] ?> × <?= $price($i['unit_price']) ?></div>
            </div>
            <div style="font-family:var(--font-mono);font-weight:700;white-space:nowrap"><?= $price($i['total_price']) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Collapsible Sections -->
    <style>
        .wk-collapse { background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);margin-bottom:12px;overflow:hidden; }
        .wk-collapse-header { padding:16px 22px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;font-weight:800;font-size:14px;transition:background .15s;user-select:none; }
        .wk-collapse-header:hover { background:var(--wk-bg); }
        .wk-collapse-arrow { transition:transform .2s;font-size:12px;color:var(--wk-muted); }
        .wk-collapse.open .wk-collapse-arrow { transform:rotate(180deg); }
        .wk-collapse-body { display:none;padding:0 22px 20px;font-size:14px;line-height:1.7; }
        .wk-collapse.open .wk-collapse-body { display:block;animation:collapseIn .2s ease; }
        @keyframes collapseIn { from{opacity:0;transform:translateY(-8px)} to{opacity:1;transform:translateY(0)} }
    </style>

    <!-- Order Summary -->
    <div class="wk-collapse open">
        <div class="wk-collapse-header" onclick="this.parentElement.classList.toggle('open')">
            💰 Order Summary <span class="wk-collapse-arrow">▼</span>
        </div>
        <div class="wk-collapse-body">
            <div style="display:flex;justify-content:space-between;padding:4px 0"><span style="color:var(--wk-muted)">Subtotal</span><span style="font-weight:700"><?= $price($o['subtotal']) ?></span></div>
            <div style="display:flex;justify-content:space-between;padding:4px 0"><span style="color:var(--wk-muted)">Tax</span><span style="font-weight:700"><?= $price($o['tax_amount']) ?></span></div>
            <div style="display:flex;justify-content:space-between;padding:4px 0"><span style="color:var(--wk-muted)">Shipping</span><span style="font-weight:700"><?= $price($o['shipping_amount']) ?></span></div>
            <?php if ($o['discount_amount'] > 0): ?>
            <div style="display:flex;justify-content:space-between;padding:4px 0"><span style="color:#10b981">Discount</span><span style="font-weight:700;color:#10b981">-<?= $price($o['discount_amount']) ?></span></div>
            <?php endif; ?>
            <div style="display:flex;justify-content:space-between;padding:12px 0 0;border-top:2px solid var(--wk-border);margin-top:8px;font-size:18px"><span style="font-weight:900">Total</span><span style="font-weight:900;font-family:var(--font-mono)"><?= $price($o['total']) ?></span></div>
        </div>
    </div>

    <!-- Billing Address -->
    <div class="wk-collapse">
        <div class="wk-collapse-header" onclick="this.parentElement.classList.toggle('open')">
            🧾 Billing Address <span class="wk-collapse-arrow">▼</span>
        </div>
        <div class="wk-collapse-body">
            <?php if (!empty($billing)): ?>
                <strong><?= $e($billing['name']??'') ?></strong><br>
                <?= $e($billing['line1']??'') ?><br>
                <?= $e(($billing['city']??'').', '.($billing['state']??'').' '.($billing['zip']??'')) ?><br>
                <?= $e($countries[$billing['country']??'']['name'] ?? ($billing['country']??'')) ?>
            <?php else: ?>
                <span style="color:var(--wk-muted)">No billing address on file</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Shipping Address / Pickup Point -->
    <?php $isPickup = !empty($shipping_addr['pickup']); ?>
    <div class="wk-collapse">
        <div class="wk-collapse-header" onclick="this.parentElement.classList.toggle('open')">
            <?= $isPickup ? '📦 Pickup Point' : '📍 Shipping Address' ?> <span class="wk-collapse-arrow">▼</span>
        </div>
        <div class="wk-collapse-body">
            <?php if (!empty($shipping_addr)): ?>
                <strong><?= $e($shipping_addr['name']??'') ?></strong><br>
                <?= $e($shipping_addr['line1']??'') ?><br>
                <?php if (!empty($shipping_addr['line2'])): ?><?= $e($shipping_addr['line2']) ?><br><?php endif; ?>
                <?= $e(($shipping_addr['city']??'').', '.($shipping_addr['state']??'').' '.($shipping_addr['zip']??'')) ?>
                <?php if ($isPickup && !empty($shipping_addr['opening_hours'])): ?>
                    <br><span style="color:var(--wk-muted);font-size:13px">🕐 <?= $e($shipping_addr['opening_hours']) ?></span>
                <?php endif; ?>
            <?php else: ?>
                <span style="color:var(--wk-muted)">Same as billing address</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Method -->
    <div class="wk-collapse">
        <div class="wk-collapse-header" onclick="this.parentElement.classList.toggle('open')">
            💳 Payment Method <span class="wk-collapse-arrow">▼</span>
        </div>
        <div class="wk-collapse-body">
            <div style="display:flex;justify-content:space-between;padding:4px 0">
                <span style="color:var(--wk-muted)">Gateway</span>
                <span style="font-weight:700"><?= $e(ucfirst($o['payment_gateway']??'Not selected')) ?></span>
            </div>
            <div style="display:flex;justify-content:space-between;padding:4px 0">
                <span style="color:var(--wk-muted)">Payment Status</span>
                <span style="font-weight:700;color:<?= $o['payment_status']==='captured'?'#10b981':'#f59e0b' ?>"><?= ucfirst($o['payment_status']??'Pending') ?></span>
            </div>
            <?php if (!empty($o['payment_id'])): ?>
            <div style="display:flex;justify-content:space-between;padding:4px 0">
                <span style="color:var(--wk-muted)">Transaction ID</span>
                <span style="font-family:var(--font-mono);font-size:12px;font-weight:700"><?= $e($o['payment_id']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tracking & Status Updates -->
    <div class="wk-collapse <?= !empty($notes['tracking_number']) ? 'open' : '' ?>">
        <div class="wk-collapse-header" onclick="this.parentElement.classList.toggle('open')">
            📦 Tracking & Updates
            <?php if (!empty($notes['tracking_number'])): ?>
                <span style="font-size:11px;font-weight:700;background:#d1fae5;color:#10b981;padding:2px 8px;border-radius:10px;margin-left:8px">SHIPPED</span>
            <?php endif; ?>
            <span class="wk-collapse-arrow">▼</span>
        </div>
        <div class="wk-collapse-body">
            <?php if (!empty($notes['tracking_number'])): ?>
                <div style="background:var(--wk-bg);border-radius:8px;padding:16px;margin-bottom:12px">
                    <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-purple);margin-bottom:8px">Shipment Info</div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0">
                        <span style="color:var(--wk-muted)">Carrier</span>
                        <span style="font-weight:700"><?= $e($notes['shipping_carrier']??'') ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:4px 0">
                        <span style="color:var(--wk-muted)">Tracking Number</span>
                        <span style="font-family:var(--font-mono);font-weight:700;color:var(--wk-purple)"><?= $e($notes['tracking_number']) ?></span>
                    </div>
                    <?php if (!empty($notes['shipped_at'])): ?>
                    <div style="display:flex;justify-content:space-between;padding:4px 0">
                        <span style="color:var(--wk-muted)">Shipped On</span>
                        <span style="font-weight:600"><?= date('M j, Y g:i A', strtotime($notes['shipped_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($notes['tracking_url'])): ?>
                    <a href="<?= \Core\View::safeUrl($notes['tracking_url']) ?>" target="_blank" rel="noopener noreferrer" style="display:inline-flex;align-items:center;gap:6px;margin-top:12px;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;padding:10px 20px;border-radius:8px;font-weight:800;font-size:13px;text-decoration:none">Track Package ↗</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Timeline -->
            <div style="padding-left:20px;border-left:2px solid var(--wk-border);margin-left:8px">
                <?php
                $timeline = [];
                $timeline[] = ['time' => $o['created_at'], 'label' => 'Order Placed', 'icon' => '🛒', 'color' => '#8b5cf6'];

                if ($o['payment_status'] === 'captured') {
                    $timeline[] = ['time' => $o['updated_at'], 'label' => 'Payment Confirmed', 'icon' => '✅', 'color' => '#10b981'];
                }
                if (!empty($notes['shipped_at'])) {
                    $timeline[] = ['time' => $notes['shipped_at'], 'label' => 'Shipped — ' . ($notes['shipping_carrier']??'') . ' #' . ($notes['tracking_number']??''), 'icon' => '📦', 'color' => '#3b82f6'];
                }
                if ($o['status'] === 'delivered') {
                    $timeline[] = ['time' => $o['updated_at'], 'label' => 'Delivered', 'icon' => '🎉', 'color' => '#10b981'];
                }
                if ($o['status'] === 'cancelled') {
                    $timeline[] = ['time' => $o['updated_at'], 'label' => 'Order Cancelled', 'icon' => '❌', 'color' => '#ef4444'];
                }

                foreach (array_reverse($timeline) as $event):
                ?>
                <div style="position:relative;padding:0 0 20px 20px">
                    <div style="position:absolute;left:-11px;top:2px;width:20px;height:20px;border-radius:50%;background:<?= $event['color'] ?>;display:flex;align-items:center;justify-content:center;font-size:10px"><?= $event['icon'] ?></div>
                    <div style="font-weight:700;font-size:13px"><?= $e($event['label']) ?></div>
                    <div style="font-size:11px;color:var(--wk-muted)"><?= date('M j, Y g:i A', strtotime($event['time'])) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php if (empty($notes['tracking_number']) && !in_array($o['status'], ['cancelled', 'delivered'])): ?>
                <div style="text-align:center;padding:12px;color:var(--wk-muted);font-size:13px">
                    Tracking info will appear here once your order ships.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canCancel): ?>
    <form method="POST" action="<?= $url('account/order/cancel/'.$o['id']) ?>" onsubmit="return confirm('Are you sure you want to cancel this order? This cannot be undone.')" style="margin-top:8px">
        <?= \Core\Session::csrfField() ?>
        <button type="submit" style="width:100%;padding:14px;background:none;border:2px solid #ef4444;border-radius:8px;color:#ef4444;font-family:var(--font);font-size:14px;font-weight:800;cursor:pointer;transition:all .2s">
            Cancel Order
        </button>
    </form>
    <?php endif; ?>

</div></section>