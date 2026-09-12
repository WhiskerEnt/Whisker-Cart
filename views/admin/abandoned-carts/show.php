<?php
$e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);
$price=fn($v)=>$currency.number_format((float)$v,2);
$c=$cart;
$email = $c['email'] ?? $c['customer_email'] ?? '';
$name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: 'Guest';
?>

<a href="<?= $url('admin/abandoned-carts') ?>" style="color:var(--wk-purple);font-weight:700;font-size:13px;margin-bottom:16px;display:inline-block">← Back to Abandoned Carts</a>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
    <h2 style="font-size:20px;font-weight:900"><?= $icon('shopping-cart', 20) ?> Cart #<?= $c['id'] ?></h2>
    <span class="wk-badge wk-badge-warning">Abandoned</span>
    <span style="font-size:13px;color:var(--wk-text-muted)"><?= date('M j, Y g:i A', strtotime($c['created_at'])) ?></span>
</div>

<div style="display:grid;grid-template-columns:1.6fr 1fr;gap:20px">
    <div>
        <!-- Cart Items -->
        <div class="wk-mb-20 wk-card">
            <div class="wk-card-header"><h2>Cart Items</h2><span class="wk-note"><?= count($items) ?> item<?= count($items)!==1?'s':'' ?></span></div>
            <?php foreach ($items as $item): ?>
            <div style="display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid var(--wk-border)">
                <div style="width:56px;height:56px;border-radius:8px;overflow:hidden;background:var(--wk-bg);flex-shrink:0;border:1px solid var(--wk-border)">
                    <?php if ($item['image']): ?><img src="<?= $url('storage/uploads/products/'.$item['image']) ?>" class="wk-cover"><?php else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:20px"><?= $icon('package', 20) ?></div><?php endif; ?>
                </div>
                <div class="wk-grow">
                    <div style="font-weight:800;font-size:14px"><?= $e($item['name']) ?></div>
                    <?php if (!empty($item['variant_label'])): ?><div style="font-size:12px;color:var(--wk-purple);font-weight:700"><?= $e($item['variant_label']) ?></div><?php endif; ?>
                    <div class="wk-note">Qty: <?= $item['quantity'] ?> × <?= $price($item['unit_price']) ?></div>
                </div>
                <div style="font-family:var(--font-mono);font-weight:800;font-size:15px"><?= $price($item['line_total']) ?></div>
            </div>
            <?php endforeach; ?>
            <div style="display:flex;justify-content:space-between;padding:16px 20px;font-size:18px">
                <span style="font-weight:900">Cart Total</span>
                <span style="font-weight:900;font-family:var(--font-mono)"><?= $price($total) ?></span>
            </div>
        </div>
    </div>

    <div>
        <!-- Customer Info -->
        <div class="wk-mb-20 wk-card">
            <div class="wk-card-header"><h2>Customer</h2></div>
            <div class="wk-card-body" style="font-size:14px">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                    <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800"><?= strtoupper(substr($name,0,1)) ?></div>
                    <div>
                        <div class="wk-heavy"><?= $e($name) ?></div>
                        <?php if ($email): ?><div class="wk-note"><?= $e($email) ?></div><?php endif; ?>
                    </div>
                </div>
                <?php if ($c['phone'] ?? null): ?><div style="margin-bottom:4px"><strong>Phone:</strong> <?= $e($c['phone']) ?></div><?php endif; ?>
                <div><strong>Session:</strong> <code style="font-size:11px"><?= $e(substr($c['session_id'],0,20)) ?>...</code></div>
            </div>
        </div>

        <!-- Send Reminder -->
        <?php if ($email): ?>
        <div class="wk-card" style="margin-bottom:20px;border:2px solid var(--wk-purple)">
            <div class="wk-card-header" style="background:var(--wk-purple-soft)"><h2><?= $icon('mail') ?> Send Reminder</h2></div>
            <div class="wk-card-body">
                <p style="font-size:13px;color:var(--wk-text-muted);margin-bottom:12px">Send an abandoned cart reminder email to <strong><?= $e($email) ?></strong> with their cart items.</p>
                <?php
                $rc = 0; $rAt = null;
                try { $rc = (int)($c['reminder_count'] ?? 0); $rAt = $c['reminder_sent_at'] ?? null; } catch (\Exception $e2) {}
                if ($rc > 0): ?>
                <div style="background:var(--wk-bg);border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:12px">
                    <strong>Last sent:</strong> <?= $rAt ? date('M j, g:i A', strtotime($rAt)) : 'Unknown' ?> · <strong><?= $rc ?></strong> reminder<?= $rc!==1?'s':'' ?> sent
                </div>
                <?php endif; ?>
                <button type="button" id="remindBtn" onclick="sendReminder()" class="wk-btn-block wk-btn wk-btn-primary">Send Abandoned Cart Email →</button>
                <div id="remindResult" style="margin-top:8px;font-size:12px;font-weight:700;min-height:18px"></div>
            </div>
        </div>
        <?php else: ?>
        <div class="wk-mb-20 wk-card">
            <div class="wk-card-body" style="text-align:center;padding:24px;color:var(--wk-text-muted)">
                <div style="font-size:24px;margin-bottom:8px"><?= $icon('mail', 24) ?></div>
                <p class="wk-bold">No email available</p>
                <p style="font-size:12px">This customer didn't log in or provide an email at checkout.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <form method="POST" action="<?= $url('admin/abandoned-carts/mark-abandoned/'.$c['id']) ?>" onsubmit="return confirm('Mark this cart as permanently abandoned? It will stop showing in the list.')">
            <?= \Core\Session::csrfField() ?>
            <button type="submit" class="wk-btn-block wk-btn wk-btn-secondary">Archive Cart</button>
        </form>
    </div>
</div>

<script>
async function sendReminder() {
    const btn = document.getElementById('remindBtn');
    const result = document.getElementById('remindResult');
    btn.disabled = true; btn.textContent = 'Sending...';
    const form = new FormData();
    const res = await fetch('<?= $url('admin/abandoned-carts/send-reminder/'.$c['id']) ?>', {method:'POST', body:form});
    const data = await res.json();
    result.innerHTML = data.success
        ? '<span class="wk-text-green">✓ ' + data.message + '</span>'
        : '<span class="wk-text-red">✗ ' + data.message + '</span>';
    btn.disabled = false; btn.textContent = 'Send Abandoned Cart Email →';
}
</script>