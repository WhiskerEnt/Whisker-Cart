<?php
$e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);
$price=fn($v)=>$currency.number_format((float)$v,2);
?>

<!-- Stats -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px;flex-wrap:wrap">
    <h1 style="font-size:24px;font-weight:900;margin:0">Abandoned Carts</h1>
    <form method="POST" action="<?= $url('admin/abandoned-carts/prune') ?>" onsubmit="return confirm('Permanently delete carts that have been abandoned, converted, or merged for more than 90 days? This cannot be undone.')" style="margin:0">
        <?= \Core\Session::csrfField() ?>
        <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm" style="font-size:12px">🧹 Prune Old Carts (90d+)</button>
    </form>
</div>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px">
    <div class="wk-card" style="padding:20px;text-align:center">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted)">Abandoned Carts</div>
        <div style="font-size:32px;font-weight:900;color:var(--wk-red)"><?= $stats['total'] ?></div>
    </div>
    <div class="wk-card" style="padding:20px;text-align:center">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted)">Lost Revenue</div>
        <div style="font-size:32px;font-weight:900;font-family:var(--font-mono);color:var(--wk-yellow)"><?= $price($stats['value']) ?></div>
    </div>
    <div class="wk-card" style="padding:20px;text-align:center">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted)">Recoverable (has email)</div>
        <div style="font-size:32px;font-weight:900;color:var(--wk-green)"><?= $stats['with_email'] ?></div>
    </div>
</div>

<?php if (empty($carts)): ?>
    <div class="wk-card"><div class="wk-empty"><div class="wk-empty-icon">🎉</div><p style="font-weight:800">No abandoned carts!</p><p style="color:var(--wk-text-muted)">Carts older than 1 hour with items will appear here.</p></div></div>
<?php else: ?>
<div class="wk-card">
    <table class="wk-table">
        <thead><tr><th>Customer</th><th>Items</th><th>Value</th><th>Created</th><th>Reminder</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($carts as $c):
            $email = $c['cart_email'] ?? $c['customer_email'] ?? '';
            $name = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
            $hasEmail = !empty($email);
            $age = time() - strtotime($c['created_at']);
            $ageText = $age < 3600 ? round($age/60).'m' : ($age < 86400 ? round($age/3600).'h' : round($age/86400).'d');
        ?>
        <tr>
            <td>
                <?php if ($name): ?><div style="font-weight:700"><?= $e($name) ?></div><?php endif; ?>
                <?php if ($email): ?><div style="font-size:12px;color:var(--wk-text-muted)"><?= $e($email) ?></div>
                <?php else: ?><span style="font-size:12px;color:var(--wk-red);font-weight:700">No email</span><?php endif; ?>
            </td>
            <td><span style="font-weight:800"><?= $c['item_count'] ?></span> items</td>
            <td style="font-family:var(--font-mono);font-weight:700"><?= $price($c['cart_value']) ?></td>
            <td>
                <div style="font-size:13px"><?= date('M j, g:i A', strtotime($c['created_at'])) ?></div>
                <div style="font-size:11px;color:var(--wk-text-muted)"><?= $ageText ?> ago</div>
            </td>
            <td>
                <?php
                $rc = 0; $rAt = null;
                try { $rc = (int)($c['reminder_count'] ?? 0); $rAt = $c['reminder_sent_at'] ?? null; } catch (\Exception $e2) {}
                ?>
                <?php if ($rc > 0): ?>
                    <span style="font-size:12px;color:var(--wk-green);font-weight:700">Sent <?= $rc ?>x</span>
                    <?php if ($rAt): ?><div style="font-size:10px;color:var(--wk-text-muted)"><?= date('M j, g:i A', strtotime($rAt)) ?></div><?php endif; ?>
                <?php else: ?>
                    <span style="font-size:12px;color:var(--wk-text-muted)">Never</span>
                <?php endif; ?>
            </td>
            <td onclick="event.stopPropagation()">
                <div style="display:flex;gap:6px">
                    <a href="<?= $url('admin/abandoned-carts/'.$c['id']) ?>" class="wk-btn wk-btn-secondary wk-btn-sm">View</a>
                    <?php if ($hasEmail): ?>
                    <button type="button" class="wk-btn wk-btn-sm" style="background:var(--wk-purple);color:#fff;border:none;padding:4px 10px;border-radius:6px;font-weight:700;cursor:pointer;font-size:12px" onclick="sendReminder(<?= $c['id'] ?>,this)">📧 Remind</button>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
async function sendReminder(cartId, btn) {
    btn.disabled = true; btn.textContent = 'Sending...';
    const form = new FormData();
    const res = await fetch('<?= $url('admin/abandoned-carts/send-reminder/') ?>' + cartId, {method:'POST', body:form});
    const data = await res.json();
    if (data.success) {
        btn.textContent = '✓ Sent';
        btn.style.background = 'var(--wk-green)';
        setTimeout(() => location.reload(), 1500);
    } else {
        btn.textContent = data.message || 'Failed';
        btn.style.background = 'var(--wk-red)';
        setTimeout(() => { btn.textContent = '📧 Remind'; btn.style.background = 'var(--wk-purple)'; btn.disabled = false; }, 2000);
    }
}
</script>