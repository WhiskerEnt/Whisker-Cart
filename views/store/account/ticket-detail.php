<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); $t=$ticket;
$sm=['open'=>'🟢 Open','in_progress'=>'🔵 In Progress','waiting'=>'🟡 Waiting','resolved'=>'✅ Resolved','closed'=>'⚫ Closed'];
?>
<section class="wk-section"><div class="wk-container" style="max-width:800px">
    <a href="<?= $url('account/tickets') ?>" style="color:var(--wk-purple-ink);font-weight:700;font-size:13px;margin-bottom:16px;display:inline-block">← My Tickets</a>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px">
        <span style="font-family:var(--font-mono);font-size:14px;color:var(--wk-purple-ink);font-weight:800"><?= $e($t['ticket_number']) ?></span>
        <span style="font-size:13px;font-weight:700"><?= $sm[$t['status']]??$t['status'] ?></span>
    </div>
    <h1 style="font-size:22px;font-weight:900;margin-bottom:24px"><?= $e($t['subject']) ?></h1>

    <!-- Messages -->
    <div style="margin-bottom:24px">
        <?php foreach ($replies as $r): $isAdmin = $r['sender_type']==='admin'; ?>
        <div style="display:flex;gap:12px;margin-bottom:16px;flex-direction:<?= $isAdmin?'row':'row-reverse' ?>">
            <div style="width:36px;height:36px;border-radius:50%;background:<?= $isAdmin?'linear-gradient(135deg,var(--wk-purple),var(--wk-pink))':'var(--wk-bg)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $isAdmin?'#fff':'var(--wk-text)' ?>;font-weight:800;font-size:13px;flex-shrink:0;border:<?= $isAdmin?'none':'2px solid var(--wk-border)' ?>"><?= strtoupper(substr($r['sender_name'],0,1)) ?></div>
            <div style="max-width:75%">
                <div style="font-size:11px;color:var(--wk-text-muted);margin-bottom:4px;text-align:<?= $isAdmin?'left':'right' ?>">
                    <strong><?= $e($r['sender_name']) ?></strong><?= $isAdmin?' (Support)':'' ?> · <?= date('M j, g:i A', strtotime($r['created_at'])) ?>
                </div>
                <div style="padding:12px 16px;border-radius:<?= $isAdmin?'4px 12px 12px 12px':'12px 4px 12px 12px' ?>;background:<?= $isAdmin?'var(--wk-purple-soft)':'var(--wk-surface)' ?>;border:<?= $isAdmin?'none':'2px solid var(--wk-border)' ?>;font-size:14px;line-height:1.7;white-space:pre-line"><?= $e($r['message']) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Reply -->
    <?php if ($t['status'] !== 'closed'): ?>
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:12px;padding:20px">
        <form method="POST" action="<?= $url('account/tickets/reply/'.$t['id']) ?>">
            <?= \Core\Session::csrfField() ?>
            <textarea name="message" rows="3" required placeholder="Type your reply..." style="width:100%;padding:12px;border:2px solid var(--wk-border);border-radius:8px;font-family:var(--font);font-size:14px;outline:none;resize:vertical"></textarea>
            <button type="submit" style="margin-top:10px;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));color:#fff;border:none;padding:12px 24px;border-radius:8px;font-weight:800;cursor:pointer;font-size:14px;font-family:var(--font)">Send Reply →</button>
        </form>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:20px;color:var(--wk-muted);font-weight:700;background:var(--wk-bg);border-radius:12px">This ticket is closed.</div>
    <?php endif; ?>
</div></section>
