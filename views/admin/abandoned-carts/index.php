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
<?php
$r  = $recovery ?? ['sent'=>0,'recovered'=>0,'value'=>0.0,'rate'=>0.0];
$cr = $settings['cart_recovery'] ?? [];
$ld = $settings['leads'] ?? [];
$on = fn($v, $d = '0') => ($v ?? $d) === '1';
?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:20px">
    <div class="wk-card"><div class="wk-card-body">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--wk-text-muted)">Reminders sent</div>
        <div style="font-size:26px;font-weight:900;font-family:var(--font-mono)"><?= (int) $r['sent'] ?></div>
    </div></div>
    <div class="wk-card"><div class="wk-card-body">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--wk-text-muted)">Baskets recovered</div>
        <div style="font-size:26px;font-weight:900;font-family:var(--font-mono);color:var(--wk-green)"><?= (int) $r['recovered'] ?></div>
    </div></div>
    <div class="wk-card"><div class="wk-card-body">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--wk-text-muted)">Recovery rate</div>
        <div style="font-size:26px;font-weight:900;font-family:var(--font-mono)"><?= number_format((float) $r['rate'], 1) ?>%</div>
    </div></div>
    <div class="wk-card"><div class="wk-card-body">
        <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--wk-text-muted)">Revenue recovered</div>
        <div style="font-size:26px;font-weight:900;font-family:var(--font-mono)"><?= htmlspecialchars($currency) ?><?= number_format((float) $r['value'], 2) ?></div>
    </div></div>
</div>

<details class="wk-card" style="margin-bottom:20px;max-width:900px" <?= $on($cr['recovery_enabled'] ?? null) ? '' : 'open' ?>>
    <summary style="padding:16px 22px;cursor:pointer;font-weight:800;font-size:14px">
        ⚙ Recovery &amp; Lead Capture Settings
        <span style="font-weight:600;color:var(--wk-text-muted);font-size:12px;margin-left:6px">
            reminders <?= $on($cr['recovery_enabled'] ?? null) ? 'on' : 'off' ?> ·
            capture <?= $on($ld['lead_capture_enabled'] ?? null) ? 'on' : 'off' ?> ·
            <?= (int) ($leadCount ?? 0) ?> lead<?= (int) ($leadCount ?? 0) === 1 ? '' : 's' ?>
        </span>
    </summary>
    <div class="wk-card-body" style="border-top:1px solid var(--wk-border)">
        <form method="POST" action="<?= $url('admin/abandoned-carts/settings') ?>">
            <?= \Core\Session::csrfField() ?>

            <div style="font-weight:800;font-size:13px;margin-bottom:10px">Reminder emails</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:20px">
                <div class="wk-form-group" style="margin:0">
                    <label>Send reminders</label>
                    <select name="recovery_enabled" class="wk-select">
                        <option value="0" <?= $on($cr['recovery_enabled'] ?? null) ? '' : 'selected' ?>>Off — send them by hand</option>
                        <option value="1" <?= $on($cr['recovery_enabled'] ?? null) ? 'selected' : '' ?>>On — send them automatically</option>
                    </select>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>Basket counts as abandoned after</label>
                    <input type="number" name="abandon_after_minutes" class="wk-input" min="5" step="5"
                           value="<?= (int) ($cr['abandon_after_minutes'] ?? 60) ?>">
                    <div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">Minutes of no activity.</div>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>Send them at</label>
                    <input type="text" name="recovery_schedule" class="wk-input"
                           value="<?= htmlspecialchars($cr['recovery_schedule'] ?? '60,1440,4320') ?>">
                    <div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">
                        Minutes after abandonment, comma separated. 60,1440,4320 is an hour, a day, then three days.
                    </div>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>Coupon to include <span style="font-weight:500;color:var(--wk-text-muted)">(optional)</span></label>
                    <input type="text" name="recovery_coupon" class="wk-input" maxlength="50"
                           value="<?= htmlspecialchars($cr['recovery_coupon'] ?? '') ?>" placeholder="COMEBACK10">
                    <div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">An existing coupon code. Left blank, no discount is offered.</div>
                </div>
            </div>

            <div style="font-weight:800;font-size:13px;margin-bottom:4px;border-top:1px solid var(--wk-border);padding-top:18px">Lead capture</div>
            <p style="font-size:12px;color:var(--wk-text-muted);margin:0 0 14px;line-height:1.6">
                Asks a visitor who looks like they are leaving for a way to reach them, so a basket without an
                email address is not a dead end. Shown once, then not again for a week.
            </p>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
                <div class="wk-form-group" style="margin:0">
                    <label>Ask before they leave</label>
                    <select name="lead_capture_enabled" class="wk-select">
                        <option value="0" <?= $on($ld['lead_capture_enabled'] ?? null) ? '' : 'selected' ?>>Off</option>
                        <option value="1" <?= $on($ld['lead_capture_enabled'] ?? null) ? 'selected' : '' ?>>On</option>
                    </select>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>What to ask for</label>
                    <select name="lead_capture_fields" class="wk-select">
                        <?php foreach (['email'=>'Email address','phone'=>'Phone number','both'=>'Either one'] as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= ($ld['lead_capture_fields'] ?? 'email') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>When to ask</label>
                    <select name="lead_capture_trigger" class="wk-select">
                        <?php foreach ([
                            'both' => 'Both — on the way out, and after a while',
                            'exit' => 'Only when they look like leaving',
                            'time' => 'Only after they have been browsing a while',
                        ] as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= ($ld['lead_capture_trigger'] ?? 'both') === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>After browsing for</label>
                    <?php $wkDelay = (string) ($ld['lead_capture_delay'] ?? '90'); ?>
                    <select name="lead_capture_delay" class="wk-select">
                        <?php foreach ([
                            '30' => '30 seconds', '60' => '1 minute', '90' => '90 seconds',
                            '120' => '2 minutes', '180' => '3 minutes', '300' => '5 minutes',
                            '600' => '10 minutes',
                        ] as $secs => $lbl): ?>
                            <option value="<?= $secs ?>" <?= $wkDelay === (string) $secs ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">
                        Counted across pages, and only while the tab is actually being looked at.
                    </div>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>Who to ask</label>
                    <select name="lead_capture_when" class="wk-select">
                        <option value="cart" <?= ($ld['lead_capture_when'] ?? 'cart') !== 'always' ? 'selected' : '' ?>>Only with something in the basket</option>
                        <option value="always" <?= ($ld['lead_capture_when'] ?? 'cart') === 'always' ? 'selected' : '' ?>>Every visitor</option>
                    </select>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>Coupon to offer <span style="font-weight:500;color:var(--wk-text-muted)">(optional)</span></label>
                    <input type="text" name="lead_capture_coupon" class="wk-input" maxlength="50"
                           value="<?= htmlspecialchars($ld['lead_capture_coupon'] ?? '') ?>" placeholder="STAY5">
                    <div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">Checked before it is shown — an expired code is never promised.</div>
                </div>
            </div>
            <div class="wk-form-group" style="margin-top:16px">
                <label>Heading</label>
                <input type="text" name="lead_capture_title" class="wk-input" maxlength="120"
                       value="<?= htmlspecialchars($ld['lead_capture_title'] ?? 'Before you go') ?>">
            </div>
            <div class="wk-form-group">
                <label>Message</label>
                <textarea name="lead_capture_text" class="wk-input" rows="2" maxlength="400"><?= htmlspecialchars($ld['lead_capture_text'] ?? '') ?></textarea>
            </div>

            <div style="border-top:1px solid var(--wk-border);padding-top:16px;margin-top:4px">
                <div style="font-weight:800;font-size:13px;margin-bottom:4px">Wording after a while</div>
                <p style="font-size:12px;color:var(--wk-text-muted);margin:0 0 12px;line-height:1.6">
                    Someone still browsing is in a different frame of mind from someone closing the tab —
                    &ldquo;still looking?&rdquo; rather than &ldquo;before you go&rdquo;. Leave these blank to use
                    the same wording for both.
                </p>
                <div class="wk-form-group">
                    <label>Heading</label>
                    <input type="text" name="lead_capture_title_time" class="wk-input" maxlength="120"
                           value="<?= htmlspecialchars($ld['lead_capture_title_time'] ?? '') ?>"
                           placeholder="Still looking?">
                </div>
                <div class="wk-form-group">
                    <label>Message</label>
                    <textarea name="lead_capture_text_time" class="wk-input" rows="2" maxlength="400"
                              placeholder="Take 5% off if you order today — leave us your email and we will send the code."><?= htmlspecialchars($ld['lead_capture_text_time'] ?? '') ?></textarea>
                </div>
            </div>

            <button type="submit" class="wk-btn wk-btn-primary">Save Settings</button>
        </form>
    </div>
</details>

<div style="display:flex;gap:8px;margin-bottom:18px;flex-wrap:wrap">
    <form method="POST" action="<?= $url('admin/abandoned-carts/sweep') ?>">
        <?= \Core\Session::csrfField() ?>
        <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm">Run the sweep now</button>
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

<?php if (($totalCarts ?? 0) > ($perPage ?? 25)):
    $pages = (int) ceil($totalCarts / $perPage); ?>
<div style="display:flex;gap:6px;justify-content:center;margin:22px 0;flex-wrap:wrap">
    <?php for ($i = 1; $i <= min($pages, 20); $i++): ?>
        <a href="<?= $url('admin/abandoned-carts') ?>?page=<?= $i ?>"
           class="wk-btn <?= ($page ?? 1) === $i ? 'wk-btn-primary' : 'wk-btn-secondary' ?> wk-btn-sm"><?= $i ?></a>
    <?php endfor; ?>
</div>
<p style="text-align:center;font-size:12px;color:var(--wk-text-muted);margin:0 0 20px">
    <?= (int) $totalCarts ?> baskets waiting
</p>
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