<?php
$e   = fn($v) => \Core\View::e((string) $v);
$url = fn($p) => \Core\View::url($p);
$s   = $settings;
?>

<div class="wk-card" style="margin-bottom:20px;max-width:900px">
    <div class="wk-card-header"><h2>💬 Question Settings</h2></div>
    <div class="wk-card-body">
        <form method="POST" action="<?= $url('admin/questions/settings') ?>">
            <?= \Core\Session::csrfField() ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px">
                <div class="wk-form-group" style="margin:0">
                    <label>Questions</label>
                    <select name="questions_enabled" class="wk-select">
                        <option value="1" <?= ($s['questions_enabled'] ?? '1') === '1' ? 'selected' : '' ?>>On — customers can ask about a product</option>
                        <option value="0" <?= ($s['questions_enabled'] ?? '1') === '0' ? 'selected' : '' ?>>Off — hide questions everywhere</option>
                    </select>
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>When you publish an answer</label>
                    <select name="notify_on_answer" class="wk-select">
                        <option value="1" <?= ($s['notify_on_answer'] ?? '1') === '1' ? 'selected' : '' ?>>Email the customer who asked</option>
                        <option value="0" <?= ($s['notify_on_answer'] ?? '1') === '0' ? 'selected' : '' ?>>Do not email anyone</option>
                    </select>
                </div>
            </div>
            <p style="font-size:12px;color:var(--wk-text-muted);margin:14px 0 16px;line-height:1.6">
                A question appears on the product page only once you have answered and published it, so shoppers
                never see an unanswered question sitting there.
            </p>
            <button type="submit" class="wk-btn wk-btn-primary">Save Question Settings</button>
        </form>
    </div>
</div>

<div style="display:flex;gap:6px;margin-bottom:18px;flex-wrap:wrap">
    <?php foreach (['pending' => 'Needs an answer', 'published' => 'On the site', 'rejected' => 'Rejected'] as $key => $label): ?>
        <a href="<?= $url('admin/questions') ?>?status=<?= $key ?>"
           class="wk-btn <?= $filter === $key ? 'wk-btn-primary' : 'wk-btn-secondary' ?>">
            <?= $label ?> <span style="opacity:.7">(<?= (int) ($counts[$key] ?? 0) ?>)</span>
        </a>
    <?php endforeach; ?>
</div>

<?php if (!$questions): ?>
    <div class="wk-card"><div class="wk-card-body" style="text-align:center;padding:44px 20px">
        <div style="font-size:40px;margin-bottom:10px">💬</div>
        <p style="font-weight:800;margin:0 0 4px">
            <?= $filter === 'pending' ? 'No questions waiting' : 'Nothing here' ?>
        </p>
        <p style="font-size:13px;color:var(--wk-text-muted);margin:0">
            <?= $filter === 'pending' ? 'Questions from customers land here for you to answer.' : '' ?>
        </p>
    </div></div>
<?php else: ?>
    <div style="display:grid;gap:14px;max-width:900px">
    <?php foreach ($questions as $q): ?>
        <div class="wk-card">
            <div class="wk-card-body">
                <div style="display:flex;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:12px">
                    <a href="<?= $url('product/' . $q['product_slug']) ?>" target="_blank" rel="noopener"
                       style="font-weight:800;font-size:14px;color:var(--wk-purple);text-decoration:none">
                        <?= $e($q['product_name'] ?? 'deleted product') ?>
                    </a>
                    <span style="font-size:12px;color:var(--wk-text-muted)"><?= $e(date('j M Y, H:i', strtotime($q['created_at']))) ?></span>
                </div>

                <div style="background:var(--wk-bg);border-radius:var(--radius-sm);padding:13px 15px;margin-bottom:14px">
                    <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:var(--wk-text-muted);margin-bottom:5px">Question</div>
                    <div style="font-size:14px;line-height:1.7;white-space:pre-line"><?= $e($q['question']) ?></div>
                </div>

                <div style="font-size:12px;color:var(--wk-text-muted);margin-bottom:14px;line-height:1.7">
                    <strong style="color:var(--wk-text)"><?= $e($q['author_name']) ?></strong> &lt;<?= $e($q['author_email']) ?>&gt;
                    <?php if ($q['ip_address']): ?> · <code><?= $e($q['ip_address']) ?></code><?php endif; ?>
                    <?php if ($q['answered_by_name']): ?><br>Answered by <?= $e($q['answered_by_name']) ?>
                        <?= $q['answered_at'] ? 'on ' . $e(date('j M Y, H:i', strtotime($q['answered_at']))) : '' ?>
                    <?php endif; ?>
                    <?php if ($q['notified_at']): ?> · customer emailed<?php endif; ?>
                </div>

                <form method="POST" action="<?= $url('admin/questions/publish/' . $q['id']) ?>">
                    <?= \Core\Session::csrfField() ?>
                    <input type="hidden" name="from" value="<?= $e($filter) ?>">
                    <div class="wk-form-group">
                        <label>Your answer <span style="font-weight:500;color:var(--wk-text-muted)">(shown publicly under the product)</span></label>
                        <textarea name="answer" class="wk-input" rows="4" maxlength="4000"
                                  placeholder="Answer the question as you would to a customer standing in front of you."><?= $e($q['answer'] ?? '') ?></textarea>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        <?php if ($q['status'] !== 'published'): ?>
                            <button type="submit" class="wk-btn wk-btn-primary wk-btn-sm">Publish answer</button>
                            <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm"
                                    formaction="<?= $url('admin/questions/draft/' . $q['id']) ?>">Save draft</button>
                        <?php else: ?>
                            <button type="submit" class="wk-btn wk-btn-primary wk-btn-sm">Update answer</button>
                            <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm"
                                    formaction="<?= $url('admin/questions/unpublish/' . $q['id']) ?>">Take off the site</button>
                        <?php endif; ?>
                        <?php if ($q['status'] !== 'rejected'): ?>
                            <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm"
                                    formaction="<?= $url('admin/questions/reject/' . $q['id']) ?>">Reject</button>
                        <?php endif; ?>
                        <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm" style="color:var(--wk-red);margin-left:auto"
                                formaction="<?= $url('admin/questions/delete/' . $q['id']) ?>"
                                onclick="return confirm('Delete this question for good?')">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
    </div>
<?php endif; ?>
