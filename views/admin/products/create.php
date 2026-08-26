<?php
$url=fn($p)=>\Core\View::url($p);
$old = \Core\Session::getOldInput();
$o = fn($k, $d='') => htmlspecialchars($old[$k] ?? $d);
?>
<form method="POST" action="<?= $url('admin/products/store') ?>" id="productForm">
    <?= \Core\Session::csrfField() ?>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">
        <div>
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>Product Details</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>Product Name</label><input type="text" name="name" class="wk-input" required placeholder="e.g. Classic Cotton T-Shirt" autofocus value="<?=$o('name')?>"></div>
                    <div class="wk-form-group"><label>Description</label><textarea name="description" class="wk-textarea" placeholder="Describe your product..."><?=$o('description')?></textarea></div>
                    <div class="wk-form-group">
                        <label>Frequently Asked Questions</label>
                        <p style="font-size:11px;color:var(--wk-text-muted);margin:0 0 10px;line-height:1.6">
                            Answer what customers keep asking, so they do not have to. These show on the product
                            page under the description. An empty row is dropped.
                        </p>
                        <div id="wkFaqRows">
                            <?php
                            $wkFaq = \App\Services\ProductFaqService::parse($o('faq'));
                            $wkFaq[] = ['q' => '', 'a' => ''];
                            foreach ($wkFaq as $wkItem): ?>
                                <div class="wk-faq-row">
                                    <input type="text" name="faq_q[]" class="wk-input" maxlength="200"
                                           placeholder="Does it shrink in the wash?"
                                           value="<?= \Core\View::e($wkItem['q']) ?>">
                                    <textarea name="faq_a[]" class="wk-input" rows="2" maxlength="2000"
                                              placeholder="Not if you wash it cold and hang it to dry."><?= \Core\View::e($wkItem['a']) ?></textarea>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="wk-btn wk-btn-secondary wk-btn-sm" id="wkFaqAdd">Add another question</button>
                    </div>
                    <script>
                    (function () {
                        var add = document.getElementById('wkFaqAdd');
                        var rows = document.getElementById('wkFaqRows');
                        if (!add || !rows) return;
                        add.addEventListener('click', function () {
                            var last = rows.lastElementChild;
                            var copy = last.cloneNode(true);
                            copy.querySelectorAll('input, textarea').forEach(function (f) { f.value = ''; });
                            rows.appendChild(copy);
                            copy.querySelector('input').focus();
                        });
                    })();
                    </script>

                    <div class="wk-form-group"><label>Short Description</label><input type="text" name="short_description" class="wk-input" placeholder="Brief summary for product cards" value="<?=$o('short_description')?>"></div>
                </div>
            </div>

            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>📸 Product Images</h2></div>
                <div class="wk-card-body">
                    <div id="imageGallery" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px;margin-bottom:16px"></div>
                    <div id="dropZone" style="border:2px dashed var(--wk-border);border-radius:var(--radius);padding:32px;text-align:center;cursor:pointer;transition:all .2s;background:var(--wk-bg)" onclick="document.getElementById('fileInput').click()">
                        <div style="font-size:32px;margin-bottom:8px;opacity:.4">📸</div>
                        <div style="font-weight:800;font-size:14px;margin-bottom:4px">Drop images here or click to upload</div>
                        <div style="font-size:12px;color:var(--wk-text-muted)">JPG, PNG, WebP, GIF · Max 5MB each · First image = primary</div>
                        <input type="file" id="fileInput" multiple accept="image/*" style="display:none" onchange="handleFiles(this.files)">
                    </div>
                    <div id="uploadProgress" style="margin-top:12px"></div>
                </div>
            </div>

            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>Pricing & Inventory</h2></div>
                <div class="wk-card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
                        <div class="wk-form-group"><label>Price</label><input type="number" step="0.01" name="price" class="wk-input" required placeholder="0.00" value="<?=$o('price')?>"></div>
                        <div class="wk-form-group"><label>Sale Price</label><input type="number" step="0.01" name="sale_price" class="wk-input" placeholder="Optional" value="<?=$o('sale_price')?>"></div>
                        <div class="wk-form-group"><label>Stock Quantity</label><input type="number" name="stock_quantity" class="wk-input" value="<?=$o('stock_quantity','0')?>"></div>
                    </div>
                    <div class="wk-form-group"><label>SKU</label><input type="text" name="sku" class="wk-input" required placeholder="PROD-001" value="<?=$o('sku')?>"></div>
                </div>
            </div>

            <div class="wk-card">
                <div class="wk-card-header"><h2>🚚 Shipping & Weight</h2></div>
                <div class="wk-card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <div class="wk-form-group">
                            <label>Weight</label>
                            <input type="number" step="0.001" name="weight_value" class="wk-input" placeholder="0.000">
                        </div>
                        <div class="wk-form-group">
                            <label>Unit</label>
                            <select name="weight_unit" class="wk-select">
                                <option value="kg">Kilograms (kg)</option>
                                <option value="g">Grams (g)</option>
                                <option value="lb">Pounds (lb)</option>
                                <option value="oz">Ounces (oz)</option>
                                <option value="ton">Metric Tons</option>
                            </select>
                        </div>
                    </div>
                    <div style="font-size:11px;color:var(--wk-text-muted);margin-top:-8px;margin-bottom:14px">Used for weight-based shipping calculation. Stored internally as kg.</div>
                    <div class="wk-form-group">
                        <label>Product Shipping Override</label>
                        <select name="shipping_override" class="wk-select" id="shipOverride" onchange="document.getElementById('customShipBox').style.display=this.value==='custom'?'block':'none'">
                            <option value="">Use store default</option>
                            <option value="free">Free shipping for this product</option>
                            <option value="custom">Custom flat rate for this product</option>
                        </select>
                    </div>
                    <div id="customShipBox" style="display:none">
                        <div class="wk-form-group">
                            <label>Custom Shipping Charge</label>
                            <input type="number" step="0.01" name="shipping_charge" class="wk-input" placeholder="0.00">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Variants (greyed out — need to save first) -->
            <div class="wk-card" style="opacity:.6;pointer-events:none">
                <div class="wk-card-header"><h2>🎨 Variants</h2></div>
                <div class="wk-card-body" style="text-align:center;padding:28px">
                    <div style="font-size:28px;margin-bottom:8px;opacity:.4">🎨</div>
                    <p style="font-weight:800;margin-bottom:4px">Save product first to add variants</p>
                    <p style="font-size:13px;color:var(--wk-text-muted)">After saving, you can add variants like Color, Size — each with its own images, price, and stock.</p>
                </div>
            </div>
        </div>
        <div>
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>Organization</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group">
                        <label>Category</label>
                        <select name="category_id" class="wk-select" id="categorySelect">
                            <option value="">No category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= $cat['parent_name'] ? htmlspecialchars($cat['parent_name']).' → ' : '' ?><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div style="margin-top:8px">
                            <button type="button" class="wk-btn wk-btn-secondary wk-btn-sm" style="width:100%;justify-content:center" onclick="document.getElementById('quickCatBox').style.display=document.getElementById('quickCatBox').style.display==='none'?'block':'none'">+ Quick Add Category</button>
                            <div id="quickCatBox" style="display:none;margin-top:8px;padding:12px;background:var(--wk-bg);border-radius:8px">
                                <input type="text" id="quickCatName" class="wk-input" placeholder="Category name" style="margin-bottom:8px">
                                <button type="button" class="wk-btn wk-btn-primary wk-btn-sm" style="width:100%;justify-content:center" onclick="quickAddCategory()">Create & Select</button>
                            </div>
                        </div>
                    </div>
                    <div class="wk-form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_active" value="1" checked> Active</label></div>
                    <div class="wk-form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_featured" value="1"> Featured</label></div>
                </div>
            </div>
            <button type="submit" class="wk-btn wk-btn-primary" style="width:100%;justify-content:center">Save & Add Variants →</button>
            <a href="<?= $url('admin/products') ?>" class="wk-btn wk-btn-secondary" style="width:100%;justify-content:center;margin-top:10px">Cancel</a>

            <?php
            $seoData = [];
            $seoPreviewSlug = 'product/new-product';
            include WK_ROOT . '/views/admin/partials/seo-fields.php';
            ?>
        </div>
    </div>
</form>

<script>
const baseUrl = '<?= $url('') ?>';
const csrfToken = '<?= \Core\Session::csrfToken() ?>';
const dropZone = document.getElementById('dropZone');
const gallery = document.getElementById('imageGallery');
let uploadedImages = [];

dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.style.borderColor='var(--wk-purple)'; dropZone.style.background='var(--wk-purple-soft)'; });
dropZone.addEventListener('dragleave', () => { dropZone.style.borderColor='var(--wk-border)'; dropZone.style.background='var(--wk-bg)'; });
dropZone.addEventListener('drop', (e) => { e.preventDefault(); dropZone.style.borderColor='var(--wk-border)'; dropZone.style.background='var(--wk-bg)'; handleFiles(e.dataTransfer.files); });

async function handleFiles(files) {
    for (const file of files) {
        if (!file.type.startsWith('image/')) continue;
        if (file.size > 5*1024*1024) { Whisker.toast(file.name+' is too large (max 5MB)','error'); continue; }

        const bar = document.createElement('div');
        bar.style.cssText='padding:8px 12px;background:var(--wk-purple-soft);border-radius:6px;font-size:12px;font-weight:700;color:var(--wk-purple);margin-bottom:8px';
        bar.textContent='Uploading '+file.name+'...';
        document.getElementById('uploadProgress').appendChild(bar);

        const form = new FormData();
        form.append('image', file);
        form.append('product_id', '0');
        form.append('wk_csrf', csrfToken);

        try {
            const res = await fetch(baseUrl+'admin/products/upload-image', {method:'POST', body:form});
            const data = await res.json();
            if (data.success) {
                bar.style.background='var(--wk-green-soft)'; bar.style.color='var(--wk-green)';
                bar.textContent='✓ '+file.name; setTimeout(()=>bar.remove(),2000);
                uploadedImages.push(data);
                renderGallery();
            } else {
                bar.style.background='var(--wk-red-soft)'; bar.style.color='var(--wk-red)';
                bar.textContent='✗ '+(data.message||'Failed');
            }
        } catch(e) { bar.style.background='var(--wk-red-soft)'; bar.style.color='var(--wk-red)'; bar.textContent='✗ Upload error'; }
    }
}

function renderGallery() {
    gallery.innerHTML = uploadedImages.map((img, i) => `
        <div style="position:relative;border-radius:10px;overflow:hidden;border:2px solid ${i===0?'var(--wk-purple)':'var(--wk-border)'};aspect-ratio:1;background:var(--wk-bg)">
            <img src="${img.url}" style="width:100%;height:100%;object-fit:cover">
            ${i===0 ? '<div style="position:absolute;bottom:0;left:0;right:0;background:var(--wk-purple);color:#fff;font-size:10px;font-weight:800;text-align:center;padding:3px;text-transform:uppercase">★ Primary</div>' : ''}
            <div style="position:absolute;top:6px;right:6px;display:flex;gap:4px">
                ${i!==0 ? '<button type="button" onclick="setPrimary('+i+')" style="background:var(--wk-purple);color:#fff;border:none;border-radius:5px;font-size:11px;padding:3px 8px;cursor:pointer;font-weight:700" title="Set as primary">★</button>' : ''}
                <button type="button" onclick="removeImage(${i})" style="background:var(--wk-red);color:#fff;border:none;border-radius:5px;font-size:11px;padding:3px 8px;cursor:pointer;font-weight:700" title="Remove">✕</button>
            </div>
        </div>
    `).join('');
}

function setPrimary(index) {
    const img = uploadedImages.splice(index, 1)[0];
    uploadedImages.unshift(img);
    renderGallery();
    Whisker.toast('Primary image changed', 'success');
}

function removeImage(index) {
    uploadedImages.splice(index, 1);
    renderGallery();
}

async function quickAddCategory() {
    const name = document.getElementById('quickCatName').value.trim();
    if (!name) return;
    const form = new FormData(); form.append('name', name); form.append('wk_csrf', csrfToken);
    try {
        const res = await fetch(baseUrl+'admin/products/quick-category', {method:'POST', body:form});
        const data = await res.json();
        if (data.success) {
            const sel = document.getElementById('categorySelect');
            const opt = document.createElement('option');
            opt.value=data.id; opt.textContent=data.name; opt.selected=true;
            sel.appendChild(opt);
            document.getElementById('quickCatName').value='';
            document.getElementById('quickCatBox').style.display='none';
            Whisker.toast('Category "'+data.name+'" created!','success');
        }
    } catch(e) { Whisker.toast('Failed to create category','error'); }
}
</script>