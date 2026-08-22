<?php
$e   = fn($v) => \Core\View::e((string)$v);
$url = fn($p) => \Core\View::url($p);
$money = fn($v) => \App\Services\CurrencyService::displayPrice((float)$v);

$statusLabels = [
    'pending'        => ['Order placed',   '#f59e0b'],
    'processing'     => ['Being prepared', '#8b5cf6'],
    'paid'           => ['Payment received', '#10b981'],
    'shipped'        => ['On its way',     '#8b5cf6'],
    'delivered'      => ['Delivered',      '#10b981'],
    'cancelled'      => ['Cancelled',      '#ef4444'],
    'refunded'       => ['Refunded',       '#ef4444'],
    'payment_failed' => ['Payment failed', '#ef4444'],
];
$notes = $order ? (json_decode($order['notes'] ?? '{}', true) ?: []) : [];
$ship  = $order ? (json_decode($order['shipping_address'] ?? '{}', true) ?: []) : [];
?>

<section class="wk-section">
    <div class="wk-container" style="max-width:640px">

        <h1 style="font-size:26px;font-weight:900;margin-bottom:6px">Track your order</h1>
        <p style="color:var(--wk-muted);font-size:14px;margin-bottom:24px">
            Enter your order number and the email address you used at checkout.
        </p>

        <?php if (!empty($trackError)): ?>
            <div style="background:var(--wk-surface);border:2px solid var(--wk-red);border-radius:var(--radius);padding:14px 16px;margin-bottom:20px;font-size:14px;font-weight:600;color:var(--wk-red)">
                <?= $e($trackError) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="<?= $url('track') ?>"
              style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:24px;margin-bottom:28px">
            <?= \Core\Session::csrfField() ?>
            <div class="wk-form-group" style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-muted);margin-bottom:6px">Order number</label>
                <input type="text" name="order_number" required value="<?= $e($trackNumber ?? '') ?>"
                       placeholder="WK-260818-A1B2C3"
                       style="width:100%;padding:11px 14px;border:2px solid var(--wk-border);border-radius:var(--radius-sm);font-family:var(--font-mono);font-size:14px;background:var(--wk-bg);color:var(--wk-text)">
            </div>
            <div class="wk-form-group" style="margin-bottom:18px">
                <label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-muted);margin-bottom:6px">Email address</label>
                <input type="email" name="email" required value="<?= $e($trackEmail ?? '') ?>"
                       placeholder="you@example.com"
                       style="width:100%;padding:11px 14px;border:2px solid var(--wk-border);border-radius:var(--radius-sm);font-family:var(--font);font-size:14px;background:var(--wk-bg);color:var(--wk-text)">
            </div>
            <button type="submit" class="wk-btn wk-btn-primary" style="width:100%;justify-content:center">Find my order</button>
        </form>

        <?php if ($order): ?>
            <?php [$label, $colour] = $statusLabels[$order['status']] ?? ['Processing', '#8b5cf6']; ?>
            <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);overflow:hidden">

                <div style="padding:22px 24px;border-bottom:1px solid var(--wk-border)">
                    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
                        <div>
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-muted)">Order</div>
                            <div style="font-family:var(--font-mono);font-size:16px;font-weight:800"><?= $e($order['order_number']) ?></div>
                        </div>
                        <span style="background:<?= $colour ?>;color:#fff;font-size:12px;font-weight:800;padding:6px 14px;border-radius:999px"><?= $e($label) ?></span>
                    </div>
                    <div style="font-size:13px;color:var(--wk-muted);margin-top:10px">
                        Placed <?= $e(date('j M Y', strtotime($order['created_at']))) ?>
                    </div>
                </div>

                <?php if (!empty($notes['tracking_number'])): ?>
                <div style="padding:18px 24px;border-bottom:1px solid var(--wk-border);background:var(--wk-purple-soft)">
                    <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-purple);margin-bottom:4px">Shipment</div>
                    <?php if (!empty($notes['shipping_carrier'])): ?>
                        <div style="font-size:14px;font-weight:700"><?= $e($notes['shipping_carrier']) ?></div>
                    <?php endif; ?>
                    <div style="font-family:var(--font-mono);font-size:14px;font-weight:700"><?= $e($notes['tracking_number']) ?></div>
                    <?php if (!empty($notes['tracking_url'])): ?>
                        <a href="<?= \Core\View::safeUrl($notes['tracking_url']) ?>" target="_blank" rel="noopener noreferrer"
                           style="display:inline-block;margin-top:8px;font-size:13px;font-weight:800;color:var(--wk-purple)">Track with carrier ↗</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div style="padding:18px 24px;border-bottom:1px solid var(--wk-border)">
                    <?php foreach ($items as $it): ?>
                    <div style="display:flex;justify-content:space-between;gap:12px;padding:7px 0;font-size:14px">
                        <div>
                            <?= $e($it['product_name']) ?>
                            <?php if (!empty($it['variant_label'])): ?>
                                <span style="color:var(--wk-muted);font-size:12px">· <?= $e($it['variant_label']) ?></span>
                            <?php endif; ?>
                            <span style="color:var(--wk-muted)">× <?= (int)$it['quantity'] ?></span>
                        </div>
                        <div style="font-family:var(--font-mono);font-weight:700;white-space:nowrap"><?= $money($it['total_price']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="padding:18px 24px;display:flex;justify-content:space-between;font-size:15px;font-weight:900">
                    <span>Total</span>
                    <span style="font-family:var(--font-mono)"><?= $money($order['total']) ?></span>
                </div>

                <?php if (!empty($ship['line1'])): ?>
                <div style="padding:18px 24px;border-top:1px solid var(--wk-border);font-size:13px;color:var(--wk-muted);line-height:1.7">
                    <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">
                        <?= !empty($ship['pickup']) ? 'Collection point' : 'Delivering to' ?>
                    </div>
                    <?= $e($ship['name'] ?? '') ?><br>
                    <?= $e($ship['line1']) ?><?php if (!empty($ship['line2'])): ?><br><?= $e($ship['line2']) ?><?php endif; ?><br>
                    <?= $e(trim(($ship['city'] ?? '') . ' ' . ($ship['zip'] ?? ''))) ?>
                </div>
                <?php endif; ?>
            </div>

            <p style="text-align:center;font-size:13px;color:var(--wk-muted);margin-top:20px">
                Questions about this order? <a href="<?= $url('contact') ?>" style="color:var(--wk-purple);font-weight:700">Contact us</a>
            </p>
        <?php endif; ?>
    </div>
</section>
