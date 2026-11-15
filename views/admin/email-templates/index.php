<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);
$system = ['order-confirmation','shipping-notification','welcome','password-reset','abandoned-cart'];
$icons = ['order-confirmation'=>'party-popper','shipping-notification'=>'package','welcome'=>'cat','password-reset'=>'key-round','abandoned-cart'=>'shopping-cart'];
?>
<div class="wk-row-between">
    <p class="wk-muted wk-semibold"><?= count($templates) ?> template<?= count($templates)!==1?'s':'' ?></p>
    <a href="<?= $url('admin/email-templates/create') ?>" class="wk-btn wk-btn-primary">+ New Template</a>
</div>
<?php if (empty($templates)): ?>
    <div class="wk-card"><div class="wk-empty"><div class="wk-empty-icon"><?= $icon('mail', 48) ?></div><p class="wk-heavy">No templates yet</p><p>Refresh this page — defaults will be created.</p></div></div>
<?php else: ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
    <?php foreach ($templates as $t): $isSystem = in_array($t['slug'], $system); ?>
    <div class="wk-card" style="overflow:hidden;transition:border-color .2s" onmouseover="this.style.borderColor='var(--wk-purple)'" onmouseout="this.style.borderColor='var(--wk-border)'">
        <div style="padding:20px">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
                <div style="width:40px;height:40px;border-radius:10px;background:var(--wk-purple-soft);display:flex;align-items:center;justify-content:center;font-size:18px"><?= $icon($icons[$t['slug']] ?? 'mail', 18) ?></div>
                <div class="wk-grow">
                    <div style="font-weight:800;font-size:15px"><?= $e($t['name']) ?></div>
                    <div style="font-size:11px;color:var(--wk-text-muted);font-family:var(--font-mono)"><?= $e($t['slug']) ?></div>
                </div>
                <span class="wk-badge <?= $t['is_active']?'wk-badge-success':'wk-badge-danger' ?>"><?= $t['is_active']?'Active':'Off' ?></span>
            </div>
            <div style="font-size:13px;color:var(--wk-text-muted);margin-bottom:4px"><strong>Subject:</strong> <?= $e($t['subject']) ?></div>
            <div style="font-size:11px;color:var(--wk-text-muted);margin-bottom:14px">Updated: <?= date('M j, g:i A', strtotime($t['updated_at'])) ?></div>
            <div style="display:flex;gap:8px">
                <a href="<?= $url('admin/email-templates/edit/'.$t['id']) ?>" class="wk-btn wk-btn-secondary wk-btn-sm"><?= $icon('pencil', 14) ?> Edit</a>
                <a href="<?= $url('admin/email-templates/preview/'.$t['id']) ?>" target="_blank" class="wk-btn wk-btn-secondary wk-btn-sm"><?= $icon('eye', 14) ?> Preview</a>
                <?php if (!$isSystem): ?>
                <form method="POST" action="<?= $url('admin/email-templates/delete/'.$t['id']) ?>" onsubmit="return confirm('Delete?')">
                    <?= \Core\Session::csrfField() ?><button type="submit" class="wk-btn wk-btn-sm" style="background:none;border:2px solid var(--wk-red);color:var(--wk-red);padding:4px 10px;border-radius:6px;font-weight:700;cursor:pointer;font-size:12px">Delete</button>
                </form>
                <?php else: ?><span style="font-size:11px;color:var(--wk-purple);padding:6px;font-weight:700;background:var(--wk-purple-soft);border-radius:4px">System</span><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>