<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); ?>
<a href="<?= $url('admin/pages') ?>" class="wk-mb-20 wk-btn wk-btn-secondary">← Back</a>
<form method="POST" action="<?= $url('admin/pages/store') ?>">
    <?= \Core\Session::csrfField() ?>
    <div style="max-width:700px">
        <div class="wk-mb-20 wk-card">
            <div class="wk-card-header"><h2>New Page</h2></div>
            <div class="wk-card-body">
                <div class="wk-form-group"><label>Page Title</label><input type="text" name="title" class="wk-input" required placeholder="e.g. About Us"></div>
                <div class="wk-form-group"><label>Content (HTML)</label><textarea name="content" class="wk-textarea" style="min-height:300px;font-family:var(--font-mono);font-size:12px" placeholder="<h2>About Us</h2><p>Your content here...</p>"></textarea></div>
            </div>
        </div>
        <button type="submit" class="wk-btn-block wk-btn wk-btn-primary">Create Page</button>
    </div>
</form>