<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);
$sm=['open'=>['dot','success'],'in_progress'=>['dot','info'],'waiting'=>['dot','warning'],'resolved'=>['circle-check','success'],'closed'=>['dot','danger']];
$pl=['low'=>'','medium'=>'','high'=>'wk-badge-warning','urgent'=>'wk-badge-danger'];
?>
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
    <a href="<?= $url('admin/tickets') ?>" class="wk-btn wk-btn-sm <?= !$currentStatus?'wk-btn-primary':'wk-btn-secondary' ?>">All (<?= array_sum($counts) ?>)</a>
    <?php foreach ($counts as $s=>$c): ?>
    <a href="<?= $url('admin/tickets?status='.$s) ?>" class="wk-btn wk-btn-sm <?= $currentStatus===$s?'wk-btn-primary':'wk-btn-secondary' ?>"><?= $sm[$s][0] ?? '' ?> <?= ucfirst(str_replace('_',' ',$s)) ?> (<?= $c ?>)</a>
    <?php endforeach; ?>
</div>

<?php if (empty($tickets)): ?>
<div class="wk-card"><div class="wk-empty"><div class="wk-empty-icon"><?= $icon('ticket', 48) ?></div><p class="wk-heavy">No tickets<?= $currentStatus?' with this status':'' ?></p></div></div>
<?php else: ?>
<div class="wk-card">
<table class="wk-table"><thead><tr><th>Ticket</th><th>Customer</th><th>Subject</th><th>Status</th><th>Priority</th><th>Replies</th><th>Last Activity</th></tr></thead><tbody>
<?php foreach ($tickets as $t): $s=$sm[$t['status']]??['clipboard-list','info']; ?>
<tr style="cursor:pointer" onclick="window.location='<?= $url('admin/tickets/'.$t['id']) ?>'">
    <td><span style="font-family:var(--font-mono);font-size:12px;font-weight:700;color:var(--wk-purple)"><?= $e($t['ticket_number']) ?></span></td>
    <td><div style="font-weight:700;font-size:13px"><?= $e($t['name']) ?></div><div class="wk-fine"><?= $e($t['email']) ?></div></td>
    <td style="font-weight:600;font-size:13px;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $e($t['subject']) ?></td>
    <td><span class="wk-badge wk-badge-<?= $s[1] ?>"><?= $icon($s[0], 12) ?> <?= ucfirst(str_replace('_',' ',$t['status'])) ?></span></td>
    <td><span class="wk-badge <?= $pl[$t['priority']]??'' ?>"><?= ucfirst($t['priority']) ?></span></td>
    <td style="font-weight:700;text-align:center"><?= $t['reply_count'] ?></td>
    <td class="wk-note"><?= date('M j, g:i A', strtotime($t['last_reply_at'] ?? $t['created_at'])) ?></td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>
<?php endif; ?>
