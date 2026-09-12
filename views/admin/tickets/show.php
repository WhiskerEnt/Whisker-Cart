<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); $t=$ticket;
$sm=['open'=>['dot','success','Open'],'in_progress'=>['dot','info','In Progress'],'waiting'=>['dot','warning','Waiting'],'resolved'=>['circle-check','success','Resolved'],'closed'=>['dot','danger','Closed']];
$s=$sm[$t['status']]??['clipboard-list','info',ucfirst($t['status'])];
?>
<a href="<?= $url('admin/tickets') ?>" style="color:var(--wk-purple);font-weight:700;font-size:13px;margin-bottom:16px;display:inline-block">← Back to Tickets</a>

<div style="display:flex;align-items:center;gap:12px;margin-bottom:24px">
    <h2 style="font-size:18px;font-weight:900;font-family:var(--font-mono);color:var(--wk-purple)"><?= $e($t['ticket_number']) ?></h2>
    <span class="wk-badge wk-badge-<?= $s[1] ?>"><?= $icon($s[0], 12) ?> <?= $s[2] ?></span>
    <span class="wk-badge"><?= ucfirst($t['priority']) ?></span>
</div>

<div style="display:grid;grid-template-columns:1.6fr 1fr;gap:20px">
    <div>
        <!-- Subject -->
        <div class="wk-mb-20 wk-card">
            <div class="wk-card-body"><h3 style="font-size:18px;font-weight:800;margin:0"><?= $e($t['subject']) ?></h3></div>
        </div>

        <!-- Conversation -->
        <div class="wk-mb-20 wk-card">
            <div class="wk-card-header"><h2>Conversation</h2><span class="wk-note"><?= count($replies) ?> message<?= count($replies)!==1?'s':'' ?></span></div>
            <div class="wk-card-body" style="padding:16px;max-height:500px;overflow-y:auto">
                <?php foreach ($replies as $r): $isAdmin = $r['sender_type']==='admin'; ?>
                <div style="display:flex;gap:12px;margin-bottom:20px;flex-direction:<?= $isAdmin?'row-reverse':'row' ?>">
                    <div style="width:36px;height:36px;border-radius:50%;background:<?= $isAdmin?'linear-gradient(135deg,var(--wk-purple),var(--wk-pink))':'var(--wk-bg)' ?>;display:flex;align-items:center;justify-content:center;color:<?= $isAdmin?'#fff':'var(--wk-text)' ?>;font-weight:800;font-size:13px;flex-shrink:0"><?= strtoupper(substr($r['sender_name'],0,1)) ?></div>
                    <div style="max-width:75%">
                        <div style="font-size:11px;color:var(--wk-text-muted);margin-bottom:4px;text-align:<?= $isAdmin?'right':'left' ?>">
                            <strong><?= $e($r['sender_name']) ?></strong> · <?= date('M j, g:i A', strtotime($r['created_at'])) ?>
                        </div>
                        <div style="padding:12px 16px;border-radius:<?= $isAdmin?'12px 4px 12px 12px':'4px 12px 12px 12px' ?>;background:<?= $isAdmin?'var(--wk-purple)':'var(--wk-bg)' ?>;color:<?= $isAdmin?'#fff':'var(--wk-text)' ?>;font-size:14px;line-height:1.7;white-space:pre-line"><?= $e($r['message']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Reply Form -->
        <?php if ($t['status'] !== 'closed'): ?>
        <div class="wk-card">
            <div class="wk-card-header"><h2>Reply</h2></div>
            <div class="wk-card-body">
                <form method="POST" action="<?= $url('admin/tickets/reply/'.$t['id']) ?>">
                    <?= \Core\Session::csrfField() ?>
                    <textarea name="message" class="wk-textarea" rows="4" required placeholder="Type your reply..."></textarea>
                    <button type="submit" class="wk-btn wk-btn-primary" style="margin-top:12px;width:100%;justify-content:center">Send Reply →</button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <!-- Customer Info -->
        <div class="wk-mb-20 wk-card">
            <div class="wk-card-header"><h2>Customer</h2></div>
            <div class="wk-card-body" style="font-size:14px">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                    <div style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800"><?= strtoupper(substr($t['name'],0,1)) ?></div>
                    <div><div class="wk-heavy"><?= $e($t['name']) ?></div><div class="wk-note"><?= $e($t['email']) ?></div></div>
                </div>
                <?php if ($t['phone']): ?><div style="margin-bottom:4px"><?= $icon('phone', 14) ?> <?= $e($t['phone']) ?></div><?php endif; ?>
                <div class="wk-note">Created: <?= date('M j, Y g:i A', strtotime($t['created_at'])) ?></div>
            </div>
        </div>

        <?php if ($order): ?>
        <div class="wk-mb-20 wk-card">
            <div class="wk-card-header"><h2>Related Order</h2></div>
            <div class="wk-card-body">
                <a href="<?= $url('admin/orders/'.$order['id']) ?>" style="font-family:var(--font-mono);color:var(--wk-purple);font-weight:700"><?= $e($order['order_number']) ?></a>
                <div style="font-size:13px;margin-top:4px">Status: <?= ucfirst($order['status']) ?> · Total: <?= $e($order['total']) ?></div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Status Update -->
        <div class="wk-mb-20 wk-card">
            <div class="wk-card-header"><h2>Update Status</h2></div>
            <div class="wk-card-body">
                <form method="POST" action="<?= $url('admin/tickets/status/'.$t['id']) ?>">
                    <?= \Core\Session::csrfField() ?>
                    <select name="status" class="wk-mb-12 wk-select">
                        <?php foreach (['open','in_progress','waiting','resolved','closed'] as $st): ?>
                        <option value="<?= $st ?>" <?= $t['status']===$st?'selected':'' ?>><?= $sm[$st][0] ?> <?= $sm[$st][2] ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="wk-btn-block wk-btn wk-btn-secondary">Update Status</button>
                </form>
            </div>
        </div>

        <!-- Priority -->
        <div class="wk-card">
            <div class="wk-card-header"><h2>Priority</h2></div>
            <div class="wk-card-body">
                <form method="POST" action="<?= $url('admin/tickets/status/'.$t['id']) ?>">
                    <?= \Core\Session::csrfField() ?>
                    <input type="hidden" name="status" value="<?= $e($t['status']) ?>">
                    <select name="priority" class="wk-select" onchange="this.form.submit()">
                        <?php foreach (['low','medium','high','urgent'] as $pr): ?>
                        <option value="<?= $pr ?>" <?= $t['priority']===$pr?'selected':'' ?>><?= ucfirst($pr) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>
    </div>
</div>
