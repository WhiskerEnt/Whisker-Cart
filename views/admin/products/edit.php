<?php
$url = fn($p) => \Core\View::url($p);
$e = fn($v) => \Core\View::e($v);
$p = $product;
$images = \Core\Database::fetchAll("SELECT * FROM wk_product_images WHERE product_id=? AND (alt_text='' OR alt_text IS NULL) ORDER BY is_primary DESC, sort_order", [$p['id']]);
$variants = \App\Services\VariantService::getForProduct($p['id']);

// Weight display
$storedWeight = (float)($p['weight'] ?? 0);
$savedUnit = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='product_meta' AND setting_key=?", ['weight_unit_'.$p['id']]) ?: 'kg';
$displayWeight = match($savedUnit) { 'g'=>$storedWeight*1000, 'lb'=>$storedWeight*2.20462, 'oz'=>$storedWeight*35.274, 'ton'=>$storedWeight/1000, default=>$storedWeight };
$shipOverride = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='product_meta' AND setting_key=?", ['shipping_override_'.$p['id']]) ?: '';
$shipCharge = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='product_meta' AND setting_key=?", ['shipping_charge_'.$p['id']]) ?: '';
?>
<form method="POST" action="<?= $url('admin/products/update/'.$p['id']) ?>">
    <?= \Core\Session::csrfField() ?>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
        <div>
            <!-- Details -->
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>Product Details</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>Product Name</label><input type="text" name="name" class="wk-input" required value="<?= $e($p['name']) ?>"></div>
                    <div class="wk-form-group"><label>Description</label><textarea name="description" class="wk-textarea"><?= $e($p['description']??'') ?></textarea></div>
                    <div class="wk-form-group"><label>Short Description</label><input type="text" name="short_description" class="wk-input" value="<?= $e($p['short_description']??'') ?>"></div>
                </div>
            </div>

            <!-- Main Images -->
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>📸 Product Images</h2><span style="font-size:12px;color:var(--wk-text-muted)"><?= count($images) ?> image<?= count($images)!==1?'s':'' ?></span></div>
                <div class="wk-card-body">
                    <div id="imageGallery" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:10px;margin-bottom:14px">
                        <?php foreach ($images as $i => $img): ?>
                        <div style="position:relative;border-radius:8px;overflow:hidden;border:2px solid <?= $img['is_primary']?'var(--wk-purple)':'var(--wk-border)' ?>;aspect-ratio:1;background:var(--wk-bg)" id="img-<?= $img['id'] ?>">
                            <img src="<?= $url('storage/uploads/products/'.$img['image_path']) ?>" style="width:100%;height:100%;object-fit:cover">
                            <?php if ($img['is_primary']): ?><div style="position:absolute;bottom:0;left:0;right:0;background:var(--wk-purple);color:#fff;font-size:9px;font-weight:800;text-align:center;padding:2px">★ PRIMARY</div><?php endif; ?>
                            <div style="position:absolute;top:4px;right:4px;display:flex;gap:3px">
                                <?php if (!$img['is_primary']): ?><button type="button" onclick="setPrimary(<?= $img['id'] ?>)" style="background:var(--wk-purple);color:#fff;border:none;border-radius:4px;font-size:10px;padding:2px 6px;cursor:pointer">★</button><?php endif; ?>
                                <button type="button" onclick="deleteImage(<?= $img['id'] ?>)" style="background:var(--wk-red);color:#fff;border:none;border-radius:4px;font-size:10px;padding:2px 6px;cursor:pointer">✕</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="dropZone" style="border:2px dashed var(--wk-border);border-radius:8px;padding:20px;text-align:center;cursor:pointer;transition:all .2s;background:var(--wk-bg)" onclick="document.getElementById('fileInput').click()">
                        <div style="font-size:20px;margin-bottom:4px;opacity:.4">📸</div>
                        <div style="font-weight:800;font-size:13px">Drop images or click to upload</div>
                        <input type="file" id="fileInput" multiple accept="image/*" style="display:none" onchange="handleFiles(this.files)">
                    </div>
                    <div id="uploadProgress" style="margin-top:8px"></div>
                </div>
            </div>

            <!-- Pricing -->
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>Pricing & Inventory</h2></div>
                <div class="wk-card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
                        <div class="wk-form-group"><label>Price</label><input type="number" step="0.01" name="price" class="wk-input" required value="<?= $p['price'] ?>"></div>
                        <div class="wk-form-group"><label>Sale Price</label><input type="number" step="0.01" name="sale_price" class="wk-input" value="<?= $p['sale_price']??'' ?>"></div>
                        <div class="wk-form-group"><label>Stock <?= $variants['has_variants']?'<span style="font-weight:500;text-transform:none;color:var(--wk-green)">(auto from variants)</span>':'' ?></label><input type="number" <?= $variants['has_variants']?'':'name="stock_quantity"' ?> class="wk-input" value="<?= $p['stock_quantity'] ?>" <?= $variants['has_variants']?'disabled style="opacity:.6"':'' ?>></div>
                    </div>
                    <div class="wk-form-group"><label>SKU</label><input type="text" name="sku" class="wk-input" required value="<?= $e($p['sku']) ?>"></div>
                </div>
            </div>

            <!-- Shipping & Weight -->
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>🚚 Shipping & Weight</h2></div>
                <div class="wk-card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <div class="wk-form-group"><label>Weight</label><input type="number" step="0.001" name="weight_value" class="wk-input" value="<?= $displayWeight>0?round($displayWeight,3):'' ?>"></div>
                        <div class="wk-form-group"><label>Unit</label><select name="weight_unit" class="wk-select"><?php foreach(['kg'=>'Kilograms','g'=>'Grams','lb'=>'Pounds','oz'=>'Ounces','ton'=>'Metric Tons'] as $u=>$l): ?><option value="<?= $u ?>" <?= $savedUnit===$u?'selected':'' ?>><?= $l ?></option><?php endforeach; ?></select></div>
                    </div>
                    <div class="wk-form-group"><label>Shipping Override</label><select name="shipping_override" class="wk-select" onchange="document.getElementById('csBox').style.display=this.value==='custom'?'block':'none'"><option value="">Store default</option><option value="free" <?= $shipOverride==='free'?'selected':'' ?>>Free shipping</option><option value="custom" <?= $shipOverride==='custom'?'selected':'' ?>>Custom rate</option></select></div>
                    <div id="csBox" style="display:<?= $shipOverride==='custom'?'block':'none' ?>"><div class="wk-form-group"><label>Custom Charge</label><input type="number" step="0.01" name="shipping_charge" class="wk-input" value="<?= $e($shipCharge) ?>"></div></div>
                </div>
            </div>

            <!-- 2-Level Variants -->
            <div class="wk-card">
                <div class="wk-card-header"><h2>🎨 Variants</h2><span style="font-size:12px;color:var(--wk-text-muted)"><?= count($variants['combos']) ?> combo<?= count($variants['combos'])!==1?'s':'' ?></span></div>
                <div class="wk-card-body">
                    <p style="font-size:12px;color:var(--wk-text-muted);margin-bottom:14px"><strong>Primary</strong> (e.g. Color) gets its own image gallery. <strong>Secondary</strong> (e.g. Size) gets stock/price per combo. Max 2 groups.</p>

                    <div id="variantGroups">
                        <?php foreach ($variants['groups'] as $gi => $group): ?>
                        <div class="vg-row" style="background:var(--wk-bg);border-radius:8px;padding:12px;margin-bottom:8px">
                            <div style="display:grid;grid-template-columns:auto 1fr 2fr auto;gap:8px;align-items:end">
                                <div style="font-size:9px;font-weight:800;text-transform:uppercase;color:var(--wk-purple);padding-bottom:8px"><?= $gi===0?'1ST':'2ND' ?></div>
                                <div><label style="font-size:10px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted)">Name</label><input type="text" name="variant_group_name[]" class="wk-input" style="padding:7px 10px;font-size:13px" value="<?= $e($group['name']) ?>"></div>
                                <div><label style="font-size:10px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted)">Options (comma)</label><input type="text" name="variant_options[]" class="wk-input" style="padding:7px 10px;font-size:13px" value="<?= $e(implode(', ', array_column($group['options'], 'value'))) ?>"></div>
                                <button type="button" onclick="this.closest('.vg-row').remove()" style="background:none;border:none;color:var(--wk-red);cursor:pointer;font-size:16px;padding:6px">✕</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($variants['groups']) < 2): ?>
                    <button type="button" onclick="addVariantGroup()" class="wk-btn wk-btn-secondary wk-btn-sm" style="width:100%;justify-content:center;margin-bottom:10px">+ Add Variant Group</button>
                    <?php endif; ?>
                    <button type="button" onclick="saveVariants()" class="wk-btn wk-btn-primary wk-btn-sm" style="width:100%;justify-content:center">Save & Generate Combinations</button>

                    <!-- Primary Option Images -->
                    <?php if ($variants['primary'] && !empty($variants['primary']['options'])): ?>
                    <div style="margin-top:18px;border-top:1px solid var(--wk-border);padding-top:14px">
                        <div style="font-weight:800;font-size:13px;margin-bottom:10px">📸 <?= $e($variants['primary']['name']) ?> Images</div>
                        <?php foreach ($variants['primary']['options'] as $opt): ?>
                        <div style="background:var(--wk-bg);border-radius:8px;padding:12px;margin-bottom:8px">
                            <div style="font-weight:800;font-size:13px;margin-bottom:8px"><?= $e($opt['value']) ?> <span style="font-weight:500;color:var(--wk-text-muted)">(<?= count($opt['images']??[]) ?> images)</span></div>
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap" id="optImages-<?= $opt['id'] ?>">
                                <?php foreach ($opt['images'] ?? [] as $img): ?>
                                <div style="position:relative;width:60px;height:60px;border-radius:6px;overflow:hidden;border:2px solid var(--wk-border)" id="vimg-<?= $img['id'] ?>">
                                    <img src="<?= $url('storage/uploads/products/'.$img['image_path']) ?>" style="width:100%;height:100%;object-fit:cover">
                                    <button type="button" onclick="deleteOptionImage(<?= $img['id'] ?>)" style="position:absolute;top:1px;right:1px;background:rgba(239,68,68,.9);color:#fff;border:none;border-radius:3px;width:16px;height:16px;font-size:9px;cursor:pointer;display:flex;align-items:center;justify-content:center">✕</button>
                                </div>
                                <?php endforeach; ?>
                                <label style="width:60px;height:60px;border-radius:6px;border:2px dashed var(--wk-border);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;color:var(--wk-text-muted);flex-shrink:0;transition:border-color .2s" onmouseover="this.style.borderColor='var(--wk-purple)'" onmouseout="this.style.borderColor='var(--wk-border)'" title="Upload for <?= $e($opt['value']) ?>">+<input type="file" accept="image/*" multiple style="display:none" onchange="uploadOptionImages(<?= $opt['id'] ?>,this.files)"></label>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <!-- Combos -->
                    <?php if (!empty($variants['combos'])): ?>
                    <div style="margin-top:18px;border-top:1px solid var(--wk-border);padding-top:14px">
                        <div onclick="const b=document.getElementById('combosBody');b.style.display=b.style.display==='none'?'block':'none';this.querySelector('.ca').textContent=b.style.display==='none'?'▶':'▼'" style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;user-select:none;padding:6px 0">
                            <div style="font-weight:800;font-size:13px">Stock & Pricing <span style="color:var(--wk-text-muted);font-weight:600">(<?= count($variants['combos']) ?>)</span></div>
                            <span class="ca" style="font-size:11px;color:var(--wk-text-muted)">▼</span>
                        </div>
                        <div id="combosBody">
                        <?php foreach ($variants['combos'] as $combo): ?>
                        <div style="background:var(--wk-bg);border-radius:8px;padding:10px 12px;margin-bottom:6px">
                            <div style="font-weight:700;font-size:12px;margin-bottom:6px"><?= $e($combo['label']) ?></div>
                            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px">
                                <input type="text" class="wk-input" style="padding:5px 7px;font-size:11px" value="<?= $e($combo['sku']??'') ?>" placeholder="SKU" onchange="updateCombo(<?= $combo['id'] ?>,'sku',this.value)">
                                <input type="number" step="0.01" class="wk-input" style="padding:5px 7px;font-size:11px" value="<?= $combo['price_override']??'' ?>" placeholder="<?= $p['price'] ?> (base)" onchange="updateCombo(<?= $combo['id'] ?>,'price_override',this.value)">
                                <input type="number" class="wk-input" style="padding:5px 7px;font-size:11px" value="<?= $combo['stock_quantity'] ?>" placeholder="Stock" onchange="updateCombo(<?= $combo['id'] ?>,'stock_quantity',this.value)">
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div>
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>Organization</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>Category</label><select name="category_id" class="wk-select" id="categorySelect"><option value="">No category</option><?php foreach ($categories as $cat): ?><option value="<?= $cat['id'] ?>" <?= $cat['id']==$p['category_id']?'selected':'' ?>><?= $cat['parent_name']?htmlspecialchars($cat['parent_name']).' → ':'' ?><?= htmlspecialchars($cat['name']) ?></option><?php endforeach; ?></select>
                        <div style="margin-top:8px"><button type="button" class="wk-btn wk-btn-secondary wk-btn-sm" style="width:100%;justify-content:center" onclick="document.getElementById('qcBox').style.display=document.getElementById('qcBox').style.display==='none'?'block':'none'">+ Quick Add Category</button><div id="qcBox" style="display:none;margin-top:8px;padding:10px;background:var(--wk-bg);border-radius:8px"><input type="text" id="qcName" class="wk-input" placeholder="Category name" style="margin-bottom:6px"><button type="button" class="wk-btn wk-btn-primary wk-btn-sm" style="width:100%;justify-content:center" onclick="quickAddCategory()">Create</button></div></div>
                    </div>
                    <div class="wk-form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_active" value="1" <?= $p['is_active']?'checked':'' ?>> Active</label></div>
                    <div class="wk-form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_featured" value="1" <?= $p['is_featured']?'checked':'' ?>> Featured</label></div>
                </div>
            </div>
            <button type="submit" class="wk-btn wk-btn-primary" style="width:100%;justify-content:center">Update Product</button>
            <a href="<?= $url('admin/products') ?>" class="wk-btn wk-btn-secondary" style="width:100%;justify-content:center;margin-top:10px">Cancel</a>

            <?php
            $seoData = ['meta_title'=>$p['meta_title']??'','meta_description'=>$p['meta_description']??'','meta_keywords'=>$p['meta_keywords']??'','og_image'=>$p['og_image']??'','_product_id'=>$p['id']];
            $seoPreviewSlug = 'product/' . ($p['slug'] ?? 'example');
            include WK_ROOT . '/views/admin/partials/seo-fields.php';
            ?>
        </div>
    </div>
</form>

<script>
const productId = <?= $p['id'] ?>;
const baseUrl = '<?= $url('') ?>';
const csrfToken = '<?= \Core\Session::csrfToken() ?>';
const dropZone = document.getElementById('dropZone');
dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.borderColor='var(--wk-purple)'; });
dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor='var(--wk-border)'; });
dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.style.borderColor='var(--wk-border)'; handleFiles(e.dataTransfer.files); });

async function handleFiles(files) {
    for (const file of files) {
        if (!file.type.startsWith('image/')||file.size>5*1024*1024) continue;
        const form = new FormData(); form.append('image',file); form.append('product_id',productId);
        const res = await fetch(baseUrl+'admin/products/upload-image',{method:'POST',headers:{'X-CSRF-Token':csrfToken},body:form});
        const data = await res.json();
        if (data.success) { Whisker.toast('Uploaded!','success'); setTimeout(()=>location.reload(),500); }
        else Whisker.toast(data.message||data.error||'Failed','error');
    }
}
async function deleteImage(id) { await fetch(baseUrl+'admin/products/delete-image/'+id,{method:'POST',headers:{'X-CSRF-Token':csrfToken}}); document.getElementById('img-'+id)?.remove(); Whisker.toast('Deleted','success'); }
async function setPrimary(id) { await fetch(baseUrl+'admin/products/set-primary-image/'+id,{method:'POST',headers:{'X-CSRF-Token':csrfToken}}); location.reload(); }

async function quickAddCategory() {
    const name=document.getElementById('qcName').value.trim(); if(!name)return;
    const form=new FormData(); form.append('name',name);
    const res=await fetch(baseUrl+'admin/products/quick-category',{method:'POST',headers:{'X-CSRF-Token':csrfToken},body:form});
    const data=await res.json();
    if(data.success){const s=document.getElementById('categorySelect');const o=document.createElement('option');o.value=data.id;o.textContent=data.name;o.selected=true;s.appendChild(o);document.getElementById('qcName').value='';document.getElementById('qcBox').style.display='none';Whisker.toast('Created!','success');}
}

// Variants
function addVariantGroup() {
    const c=document.getElementById('variantGroups');const n=c.children.length;
    if(n>=2){Whisker.toast('Max 2 variant groups','warning');return;}
    const d=document.createElement('div');d.className='vg-row';d.style.cssText='background:var(--wk-bg);border-radius:8px;padding:12px;margin-bottom:8px';
    d.innerHTML=`<div style="display:grid;grid-template-columns:auto 1fr 2fr auto;gap:8px;align-items:end"><div style="font-size:9px;font-weight:800;text-transform:uppercase;color:var(--wk-purple);padding-bottom:8px">${n===0?'1ST':'2ND'}</div><div><label style="font-size:10px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted)">Name</label><input type="text" name="variant_group_name[]" class="wk-input" style="padding:7px 10px;font-size:13px" placeholder="${n===0?'e.g. Color':'e.g. Size'}"></div><div><label style="font-size:10px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted)">Options (comma)</label><input type="text" name="variant_options[]" class="wk-input" style="padding:7px 10px;font-size:13px" placeholder="${n===0?'Red, Blue, Green':'S, M, L, XL'}"></div><button type="button" onclick="this.closest('.vg-row').remove()" style="background:none;border:none;color:var(--wk-red);cursor:pointer;font-size:16px;padding:6px">✕</button></div>`;
    c.appendChild(d);
}
async function saveVariants() {
    const form=new FormData();
    document.querySelectorAll('input[name="variant_group_name[]"]').forEach((el,i)=>form.append('variant_group_name['+i+']',el.value));
    document.querySelectorAll('input[name="variant_options[]"]').forEach((el,i)=>form.append('variant_options['+i+']',el.value));
    const res=await fetch(baseUrl+'admin/products/variants/save/'+productId,{method:'POST',headers:{'X-CSRF-Token':csrfToken},body:form});
    const data=await res.json();
    if(data.success){Whisker.toast(data.count+' combos generated!','success');setTimeout(()=>location.reload(),600);}
    else Whisker.toast(data.error||'Failed','error');
}
async function updateCombo(id,field,val) {
    const form=new FormData();form.append(field,val);
    await fetch(baseUrl+'admin/products/variants/update-combo/'+id,{method:'POST',headers:{'X-CSRF-Token':csrfToken},body:form});
    Whisker.toast('Saved','success');
}

// Primary option images
async function uploadOptionImages(optionId, files) {
    for (const file of files) {
        if (!file.type.startsWith('image/')||file.size>5*1024*1024) continue;
        const form=new FormData(); form.append('image',file); form.append('product_id',productId); form.append('option_id',optionId);
        const res=await fetch(baseUrl+'admin/products/variants/upload-option-image',{method:'POST',headers:{'X-CSRF-Token':csrfToken},body:form});
        const data=await res.json();
        if (data.success) {
            const container=document.getElementById('optImages-'+optionId);
            const thumb=document.createElement('div');
            thumb.id='vimg-'+data.image_id;
            thumb.style.cssText='position:relative;width:60px;height:60px;border-radius:6px;overflow:hidden;border:2px solid var(--wk-purple)';
            thumb.innerHTML=`<img src="${data.url}" style="width:100%;height:100%;object-fit:cover"><button type="button" onclick="deleteOptionImage(${data.image_id})" style="position:absolute;top:1px;right:1px;background:rgba(239,68,68,.9);color:#fff;border:none;border-radius:3px;width:16px;height:16px;font-size:9px;cursor:pointer;display:flex;align-items:center;justify-content:center">✕</button>`;
            container.insertBefore(thumb,container.lastElementChild);
            Whisker.toast('Uploaded!','success');
        } else Whisker.toast(data.message||'Failed','error');
    }
}
async function deleteOptionImage(id) {
    await fetch(baseUrl+'admin/products/variants/delete-option-image/'+id,{method:'POST',headers:{'X-CSRF-Token':csrfToken}});
    document.getElementById('vimg-'+id)?.remove();
    Whisker.toast('Deleted','success');
}
</script>