<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);
$sm=['open'=>['🟢','Open'],'in_progress'=>['🔵','In Progress'],'waiting'=>['🟡','Waiting'],'resolved'=>['✅','Resolved'],'closed'=>['⚫','Closed']];
?>
<section class="wk-section"><div class="wk-container" style="max-width:800px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
        <h1 style="font-size:24px;font-weight:900">My Tickets</h1>
        <a href="<?= $url('account/tickets/create') ?>" style="background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));color:#fff;padding:10px 20px;border-radius:8px;font-weight:800;text-decoration:none;font-size:14px">+ New Ticket</a>
    </div>
    <?php if (empty($tickets)): ?>
        <div style="text-align:center;padding:48px;color:var(--wk-muted)"><div style="font-size:48px;margin-bottom:8px">🎫</div><p style="font-weight:800">No tickets yet</p><p style="font-size:14px">Need help? Create a new support ticket.</p></div>
    <?php else: ?>
        <?php foreach ($tickets as $t): $s=$sm[$t['status']]??['📋','Open']; ?>
        <a href="<?= $url('account/tickets/'.$t['id']) ?>" style="display:block;background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:12px;padding:16px 20px;margin-bottom:12px;text-decoration:none;color:var(--wk-text);transition:border-color .2s" onmouseover="this.style.borderColor='var(--wk-purple)'" onmouseout="this.style.borderColor='var(--wk-border)'">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                <span style="font-family:var(--font-mono);font-size:12px;color:var(--wk-purple-ink);font-weight:700"><?= $e($t['ticket_number']) ?></span>
                <span style="font-size:12px;font-weight:700"><?= $s[0] ?> <?= $s[1] ?></span>
            </div>
            <div style="font-weight:800;font-size:15px;margin-bottom:4px"><?= $e($t['subject']) ?></div>
            <div style="font-size:12px;color:var(--wk-muted)"><?= $t['reply_count'] ?> replies · <?= date('M j, Y', strtotime($t['created_at'])) ?></div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
    <a href="<?= $url('account') ?>" style="color:var(--wk-purple-ink);font-weight:700;font-size:13px;margin-top:16px;display:inline-block">← Back to Account</a>
</div></section>
