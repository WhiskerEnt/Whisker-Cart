<?php $url=fn($p)=>\Core\View::url($p); $e=fn($v)=>\Core\View::e($v); $c=$category; ?>
<form method="POST" action="<?= $url('admin/categories/update/'.$c['id']) ?>">
    <?= \Core\Session::csrfField() ?>
    <div class="wk-card" style="max-width:600px">
        <div class="wk-card-header"><h2>Edit Category</h2></div>
        <div class="wk-card-body">
            <div class="wk-form-group"><label>Category Name</label><input type="text" name="name" class="wk-input" required value="<?= $e($c['name']) ?>"></div>
            <div class="wk-form-group">
                <label>Parent Category</label>
                <select name="parent_id" class="wk-select">
                    <option value="">None (top level)</option>
                    <?php foreach ($parents as $p): ?><option value="<?= $p['id'] ?>" <?= $p['id']==$c['parent_id']?'selected':'' ?>><?= htmlspecialchars($p['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="wk-form-group"><label>Description</label><textarea name="description" class="wk-textarea"><?= $e($c['description']??'') ?></textarea></div>
            <div class="wk-grid-2">
                <div class="wk-form-group"><label>Sort Order</label><input type="number" name="sort_order" class="wk-input" value="<?= $c['sort_order'] ?>"></div>
                <div class="wk-form-group" style="display:flex;align-items:flex-end">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding-bottom:10px"><input type="checkbox" name="is_active" value="1" <?= $c['is_active']?'checked':'' ?>> Active</label>
                </div>
            </div>
            <div style="display:flex;gap:12px;margin-top:8px">
                <a href="<?= $url('admin/categories') ?>" class="wk-btn-grow wk-btn wk-btn-secondary">Cancel</a>
                <button type="submit" class="wk-btn-grow wk-btn wk-btn-primary">Update Category</button>
            </div>

            <!-- SEO -->
            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--wk-border)">
                <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:var(--wk-text-muted);margin-bottom:10px;cursor:pointer" onclick="document.getElementById('catSeo').style.display=document.getElementById('catSeo').style.display==='none'?'block':'none'"><?= $icon('search') ?> SEO Settings ▸</div>
                <div id="catSeo" style="display:<?=empty($c['meta_title']??'')?'none':'block'?>">
                    <div class="wk-form-group"><label>Meta Title</label><input type="text" name="meta_title" class="wk-input" value="<?=$e($c['meta_title']??'')?>" placeholder="Auto-generated if empty" maxlength="70"></div>
                    <div class="wk-form-group"><label>Meta Description</label><textarea name="meta_description" class="wk-textarea" rows="2" maxlength="160" placeholder="Auto-generated if empty"><?=$e($c['meta_description']??'')?></textarea></div>
                    <div class="wk-form-group"><label>Meta Keywords</label><input type="text" name="meta_keywords" class="wk-input" value="<?=$e($c['meta_keywords']??'')?>" placeholder="Auto-generated if empty"></div>
                </div>
            </div>
        </div>
    </div>
</form>
