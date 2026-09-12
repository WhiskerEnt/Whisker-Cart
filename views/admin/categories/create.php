<?php $url=fn($p)=>\Core\View::url($p); ?>
<form method="POST" action="<?= $url('admin/categories/store') ?>">
    <?= \Core\Session::csrfField() ?>
    <div class="wk-card" style="max-width:600px">
        <div class="wk-card-header"><h2>New Category</h2></div>
        <div class="wk-card-body">
            <div class="wk-form-group"><label>Category Name</label><input type="text" name="name" class="wk-input" required placeholder="e.g. Electronics"></div>
            <div class="wk-form-group">
                <label>Parent Category</label>
                <select name="parent_id" class="wk-select">
                    <option value="">None (top level)</option>
                    <?php foreach ($parents as $p): ?><option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="wk-form-group"><label>Description</label><textarea name="description" class="wk-textarea" placeholder="Optional description..."></textarea></div>
            <div class="wk-grid-2">
                <div class="wk-form-group"><label>Sort Order</label><input type="number" name="sort_order" class="wk-input" value="0"></div>
                <div class="wk-form-group" style="display:flex;align-items:flex-end">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding-bottom:10px"><input type="checkbox" name="is_active" value="1" checked> Active</label>
                </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:8px">
                <a href="<?= $url('admin/categories') ?>" class="wk-btn-grow wk-btn wk-btn-secondary">Cancel</a>
                <button type="submit" class="wk-btn-grow wk-btn wk-btn-primary">Create Category</button>
            </div>
        </div>
    </div>
</form>
