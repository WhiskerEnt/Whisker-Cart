<?php
$e   = fn($v) => \Core\View::e((string) $v);
$url = fn($p) => \Core\View::url($p);
$s   = $settings;
$policy = $s['review_policy'] ?? 'purchased';
$stars = function (int $n) {
    return str_repeat('★', max(0, min(5, $n))) . str_repeat('☆', max(0, 5 - $n));
};
?>

<div class="wk-card" style="margin-bottom:20px;max-width:900px">
    <div class="wk-card-header"><h2>⭐ Review Settings</h2></div>
    <div class="wk-card-body">
        <form method="POST" action="<?= $url('admin/reviews/settings') ?>">
            <?= \Core\Session::csrfField() ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">
                <div class="wk-form-group" style="margin:0">
                    <label>Reviews</label>
                    <select name="reviews_enabled" class="wk-select">
                        <option value="1" <?= ($s['reviews_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>On — customers can rate and review</option>
                        <option value="0" <?= ($s['reviews_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Off — hide reviews everywhere</option>
                    </select>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>Who can review</label>
                    <select name="review_policy" class="wk-select">
                        <option value="anyone" <?= $policy === 'anyone' ? 'selected' : '' ?>>Anyone — no purchase needed</option>
                        <option value="purchased" <?= $policy === 'purchased' ? 'selected' : '' ?>>Customers who bought it</option>
                        <option value="received" <?= $policy === 'received' ? 'selected' : '' ?>>Customers who received it</option>
                    </select>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>New reviews</label>
                    <select name="auto_approve" class="wk-select">
                        <option value="0" <?= ($s['auto_approve'] ?? '0') !== '1' ? 'selected' : '' ?>>Wait for your approval</option>
                        <option value="1" <?= ($s['auto_approve'] ?? '0') === '1' ? 'selected' : '' ?>>Publish straight away</option>
                    </select>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>Stars on product cards</label>
                    <select name="show_on_cards" class="wk-select">
                        <option value="1" <?= ($s['show_on_cards'] ?? '1') === '1' ? 'selected' : '' ?>>Show</option>
                        <option value="0" <?= ($s['show_on_cards'] ?? '1') === '0' ? 'selected' : '' ?>>Hide</option>
                    </select>
                </div>
            </div>
            <p style="font-size:12px;color:var(--wk-text-muted);margin:14px 0 16px;line-height:1.6">
                <?php if ($policy === 'anyone'): ?>
                    Anyone can review, whether or not they have ordered. Keep approval on if you use this.
                <?php elseif ($policy === 'received'): ?>
                    A reviewer must have an order for the product marked <strong>delivered</strong>, matched on the email
                    address they ordered with. Their review is marked as a verified purchase.
                <?php else: ?>
                    A reviewer must have a paid order for the product, matched on the email address they ordered with.
                    Their review is marked as a verified purchase.
                <?php endif; ?>
            </p>
            <button type="submit" class="wk-btn wk-btn-primary">Save Review Settings</button>
        </form>
    </div>
</div>

<div style="display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap">
    <?php foreach (['pending' => 'Waiting', 'approved' => 'Published', 'rejected' => 'Rejected'] as $key => $label): ?>
        <a href="<?= $url('admin/reviews') ?>?status=<?= $key ?>"
           class="wk-btn <?= $filter === $key ? 'wk-btn-primary' : 'wk-btn-secondary' ?>">
            <?= $label ?> <span style="opacity:.7">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!$reviews): ?>
    <div class="wk-card"><div class="wk-card-body" style="text-align:center;padding:44px 20px">
        <div style="font-size:40px;margin-bottom:10px">⭐</div>
        <p style="font-weight:800;margin:0 0 4px">
            <?= $filter === 'pending' ? 'Nothing waiting for approval' : 'No ' . $e($filter) . ' reviews' ?>
        </p>
        <p style="font-size:13px;color:var(--wk-text-muted);margin:0">
            <?= $filter === 'pending' ? 'New reviews land here for you to check.' : '' ?>
        </p>
    </div></div>
<?php else: ?>
    <div style="display:grid;gap:14px;max-width:900px">
    <?php foreach ($reviews as $r): ?>
        <div class="wk-card">
            <div class="wk-card-body">
                <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;align-items:baseline">
                    <div style="min-width:0">
                        <div style="font-size:16px;color:var(--wk-yellow);letter-spacing:2px;line-height:1">
                            <?= $stars((int) $r['rating']) ?>
                            <span style="font-size:12px;color:var(--wk-text-muted);letter-spacing:0;margin-left:4px"><?= (int) $r['rating'] ?>/5</span>
                        </div>
                        <?php if ($r['title']): ?>
                            <div style="font-weight:800;font-size:15px;margin-top:6px"><?= $e($r['title']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div style="text-align:right;font-size:12px;color:var(--wk-text-muted)">
                        <?= $e(date('j M Y, H:i', strtotime($r['created_at']))) ?>
                    </div>
                </div>

                <div style="display:flex;gap:8px;flex-wrap:wrap;margin:10px 0">
                    <?php if ($r['is_verified_purchase']): ?>
                        <span class="wk-badge wk-badge-success">✓ Verified purchase</span>
                    <?php else: ?>
                        <span class="wk-badge">No purchase found</span>
                    <?php endif; ?>
                    <?php if ($r['order_number']): ?>
                        <span class="wk-badge wk-badge-purple"><?= $e($r['order_number']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($r['body']): ?>
                    <p style="font-size:14px;line-height:1.7;margin:0 0 12px;white-space:pre-line"><?= $e($r['body']) ?></p>
                <?php endif; ?>

                <div style="font-size:12px;color:var(--wk-text-muted);border-top:1px solid var(--wk-border);padding-top:11px;margin-bottom:12px;line-height:1.7">
                    <strong style="color:var(--wk-text)"><?= $e($r['author_name']) ?></strong> &lt;<?= $e($r['author_email']) ?>&gt;<br>
                    on <a href="<?= $url('product/' . $r['product_slug']) ?>" target="_blank" rel="noopener" style="color:var(--wk-purple);font-weight:700"><?= $e($r['product_name'] ?? 'deleted product') ?></a>
                    <?php if ($r['ip_address']): ?> · <code><?= $e($r['ip_address']) ?></code><?php endif; ?>
                </div>

                <?php if ($r['admin_reply']): ?>
                    <div style="background:var(--wk-bg);border-radius:var(--radius-sm);padding:11px 13px;margin-bottom:12px">
                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted);margin-bottom:4px">Your reply</div>
                        <div style="font-size:13px;line-height:1.6;white-space:pre-line"><?= $e($r['admin_reply']) ?></div>
                    </div>
                <?php endif; ?>

                <details style="margin-bottom:12px">
                    <summary style="font-size:12px;font-weight:800;color:var(--wk-purple);cursor:pointer">
                        <?= $r['admin_reply'] ? 'Edit your reply' : 'Reply publicly' ?>
                    </summary>
                    <form method="POST" action="<?= $url('admin/reviews/reply/' . $r['id']) ?>" style="margin-top:10px">
                        <?= \Core\Session::csrfField() ?>
                        <input type="hidden" name="from" value="<?= $e($filter) ?>">
                        <textarea name="reply" class="wk-input" rows="3" maxlength="2000" placeholder="Shown under the review on the product page."><?= $e($r['admin_reply'] ?? '') ?></textarea>
                        <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm" style="margin-top:8px">Save reply</button>
                    </form>
                </details>

                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <?php if ($r['status'] !== 'approved'): ?>
                        <form method="POST" action="<?= $url('admin/reviews/status/' . $r['id']) ?>">
                            <?= \Core\Session::csrfField() ?>
                            <input type="hidden" name="status" value="approved">
                            <input type="hidden" name="from" value="<?= $e($filter) ?>">
                            <button type="submit" class="wk-btn wk-btn-primary wk-btn-sm">Publish</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($r['status'] !== 'rejected'): ?>
                        <form method="POST" action="<?= $url('admin/reviews/status/' . $r['id']) ?>">
                            <?= \Core\Session::csrfField() ?>
                            <input type="hidden" name="status" value="rejected">
                            <input type="hidden" name="from" value="<?= $e($filter) ?>">
                            <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm">Reject</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($r['status'] === 'approved'): ?>
                        <form method="POST" action="<?= $url('admin/reviews/status/' . $r['id']) ?>">
                            <?= \Core\Session::csrfField() ?>
                            <input type="hidden" name="status" value="pending">
                            <input type="hidden" name="from" value="<?= $e($filter) ?>">
                            <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm">Unpublish</button>
                        </form>
                    <?php endif; ?>
                    <form method="POST" action="<?= $url('admin/reviews/delete/' . $r['id']) ?>"
                          onsubmit="return confirm('Delete this review for good?')" style="margin-left:auto">
                        <?= \Core\Session::csrfField() ?>
                        <input type="hidden" name="from" value="<?= $e($filter) ?>">
                        <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm" style="color:var(--wk-red)">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
