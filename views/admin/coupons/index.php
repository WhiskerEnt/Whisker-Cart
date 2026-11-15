<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); ?>
<div class="wk-row-between">
    <p class="wk-muted wk-semibold"><?= count($coupons) ?> coupon<?= count($coupons)!==1?'s':'' ?></p>
    <a href="<?= $url('admin/coupons/create') ?>" class="wk-btn wk-btn-primary">+ Create Coupon</a>
</div>
<?php if (empty($coupons)): ?>
    <div class="wk-card"><div class="wk-empty"><div class="wk-empty-icon"><?= $icon('tag', 48) ?></div><p class="wk-heavy">No coupons yet</p><p><a href="<?= $url('admin/coupons/create') ?>">Create your first coupon</a></p></div></div>
<?php else: ?>
<div class="wk-card"><table class="wk-table"><thead><tr><th>Code</th><th>Discount</th><th>Used</th><th>Expires</th><th>Status</th><th></th></tr></thead><tbody>
<?php foreach ($coupons as $c): ?>
<tr>
    <td><code style="font-family:var(--font-mono);font-weight:700;font-size:13px;background:var(--wk-purple-soft);color:var(--wk-purple);padding:3px 8px;border-radius:4px"><?= $e($c['code']) ?></code></td>
    <td class="wk-bold"><?= $c['type']==='percentage'? $c['value'].'%' : \Core\View::price($c['value']) ?> <?= $c['type']==='percentage'?'off':'off' ?></td>
    <td><?= $c['used_count'] ?><?= $c['usage_limit']?' / '.$c['usage_limit']:'' ?></td>
    <td style="font-size:13px;color:var(--wk-text-muted)"><?= $c['expires_at']?date('M j, Y',strtotime($c['expires_at'])):'Never' ?></td>
    <td><span class="wk-badge <?= $c['is_active']?'wk-badge-success':'wk-badge-danger' ?>"><?= $c['is_active']?'Active':'Inactive' ?></span></td>
    <td><form method="POST" action="<?= $url('admin/coupons/delete/'.$c['id']) ?>" onsubmit="return confirm('Delete this coupon?')"><?= \Core\Session::csrfField() ?><button type="submit" class="wk-btn wk-btn-danger wk-btn-sm">Delete</button></form></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
