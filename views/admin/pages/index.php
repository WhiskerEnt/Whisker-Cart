<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <p style="color:var(--wk-text-muted);font-weight:600"><?= count($pages) ?> page<?= count($pages)!==1?'s':'' ?></p>
    <a href="<?= $url('admin/pages/create') ?>" class="wk-btn wk-btn-primary">+ New Page</a>
</div>

<?php
// What a shop is expected to have, and what this one has done about it. The
// gap was invisible before: a shop could trade for months with no refund
// policy and hear about it first from a chargeback.
$missing = array_values(array_filter($recommended, fn($r) => $r['state'] === 'missing'));
$drafts  = array_values(array_filter($recommended, fn($r) => $r['state'] === 'draft'));
?>
<div class="wk-card" style="margin-bottom:24px">
    <div class="wk-card-header" style="display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap">
        <h2>Pages a shop is expected to have</h2>
        <span style="font-size:13px;font-weight:700;color:var(--wk-text-muted)">
            <?= (int) $summary['published'] ?> published<?php
            if ($summary['draft']):   ?> · <?= (int) $summary['draft'] ?> draft<?php endif;
            if ($summary['missing']): ?> · <?= (int) $summary['missing'] ?> missing<?php endif; ?>
        </span>
    </div>
    <div class="wk-card-body">
        <?php if (!$missing && !$drafts): ?>
            <p style="margin:0;font-weight:700;color:var(--wk-green)">✓ All of them are published. Nothing to do here.</p>
        <?php else: ?>
        <ul class="wk-recommended">
            <?php foreach ($recommended as $r): ?>
                <?php if ($r['state'] === 'published') continue; ?>
                <li class="wk-recommended-item">
                    <div>
                        <div class="wk-recommended-title">
                            <?= $e($r['title']) ?>
                            <span class="wk-badge <?= $r['state'] === 'draft' ? 'wk-badge-warning' : 'wk-badge-danger' ?>">
                                <?= $r['state'] === 'draft' ? 'Draft — not visible' : 'Missing' ?>
                            </span>
                        </div>
                        <p class="wk-recommended-why"><?= $e($r['why']) ?></p>
                        <?php if ($r['used_by']): ?>
                            <p class="wk-recommended-used">↳ <?= $e($r['used_by']) ?></p>
                        <?php endif; ?>
                    </div>
                    <div style="flex-shrink:0">
                        <?php if ($r['state'] === 'draft'): ?>
                            <a href="<?= $url('admin/pages/edit/' . (int) $r['id']) ?>" class="wk-btn wk-btn-secondary wk-btn-sm">Finish it</a>
                        <?php else: ?>
                            <form method="POST" action="<?= $url('admin/pages/add-recommended') ?>" style="margin:0">
                                <?= \Core\Session::csrfField() ?>
                                <input type="hidden" name="slug" value="<?= $e($r['slug']) ?>">
                                <button type="submit" class="wk-btn wk-btn-primary wk-btn-sm">Start this page</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="wk-recommended-note">
            Each one starts as a draft with the decisions left blank for you to fill in.
            Nothing is published until you say so, and none of it is legal advice.
        </p>
        <?php endif; ?>
    </div>
</div>
<div class="wk-card">
<table class="wk-table"><thead><tr><th>Page</th><th>URL</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($pages as $p): ?>
<tr>
    <td style="font-weight:800"><?= $e($p['title']) ?></td>
    <td><a href="<?= $url('page/'.$p['slug']) ?>" target="_blank" style="font-family:var(--font-mono);font-size:12px;color:var(--wk-purple)">/page/<?= $e($p['slug']) ?> ↗</a></td>
    <td><span class="wk-badge <?= $p['is_active']?'wk-badge-success':'wk-badge-danger' ?>"><?= $p['is_active']?'Active':'Hidden' ?></span></td>
    <td style="font-size:13px;color:var(--wk-text-muted)"><?= date('M j, Y', strtotime($p['updated_at'])) ?></td>
    <td>
        <div style="display:flex;gap:6px">
            <a href="<?= $url('admin/pages/edit/'.$p['id']) ?>" class="wk-btn wk-btn-secondary wk-btn-sm">Edit</a>
            <form method="POST" action="<?= $url('admin/pages/delete/'.$p['id']) ?>" onsubmit="return confirm('Delete this page?')">
                <?= \Core\Session::csrfField() ?>
                <button type="submit" class="wk-btn wk-btn-sm" style="background:none;border:2px solid var(--wk-red);color:var(--wk-red);padding:4px 10px;border-radius:6px;font-weight:700;cursor:pointer;font-size:12px">Delete</button>
            </form>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table>
</div>