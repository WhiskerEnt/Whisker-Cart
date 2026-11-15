<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); ?>
<?php if (empty($customers)): ?>
    <div class="wk-card"><div class="wk-empty"><div class="wk-empty-icon"><?= $icon('users', 48) ?></div><p class="wk-heavy">No customers yet</p></div></div>
<?php else: ?>
<div class="wk-card"><table class="wk-table"><thead><tr><th>Customer</th><th>Email</th><th>Orders</th><th>Total Spent</th><th>Joined</th></tr></thead><tbody>
<?php foreach ($customers as $c): ?>
<tr>
    <td><a href="<?= $url('admin/customers/'.$c['id']) ?>" style="font-weight:800;color:var(--wk-purple);text-decoration:none"><?= $e($c['first_name'].' '.$c['last_name']) ?></a></td>
    <td class="wk-muted"><?= $e($c['email']) ?></td>
    <td class="wk-bold"><?= $c['total_orders'] ?></td>
    <td class="wk-mono wk-bold"><?= \Core\View::price($c['total_spent']) ?></td>
    <td style="color:var(--wk-text-muted);font-size:13px"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>
