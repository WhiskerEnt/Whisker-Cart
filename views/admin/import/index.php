<?php $url=fn($p)=>\Core\View::url($p); ?>

<!-- All-in-One Highlight -->
<div class="wk-card" style="margin-bottom:20px;border:2px solid var(--wk-purple)">
    <div class="wk-card-header" style="background:linear-gradient(135deg,var(--wk-purple-soft),var(--wk-pink-soft))">
        <h2>⚡ All-in-One Import <span style="font-size:11px;background:var(--wk-purple);color:#fff;padding:2px 8px;border-radius:10px;margin-left:8px;font-weight:800">RECOMMENDED</span></h2>
    </div>
    <div class="wk-card-body">
        <p style="font-size:14px;margin-bottom:14px">Import categories, products, and variants from a <strong>single CSV file</strong>. Each row has a <code>row_type</code> column that tells Whisker what it is.</p>
        <div style="display:flex;gap:10px;margin-bottom:16px;font-size:12px">
            <span style="background:var(--wk-purple-soft);padding:4px 10px;border-radius:6px;font-weight:700">row_type = category</span>
            <span style="font-size:16px">→</span>
            <span style="background:var(--wk-pink-soft);padding:4px 10px;border-radius:6px;font-weight:700">row_type = product</span>
            <span style="font-size:16px">→</span>
            <span style="background:var(--wk-green-soft);padding:4px 10px;border-radius:6px;font-weight:700">row_type = variant</span>
            <span style="font-size:16px">→</span>
            <span style="background:#fef3c7;padding:4px 10px;border-radius:6px;font-weight:700">row_type = combo</span>
        </div>
        <form method="POST" action="<?= $url('admin/import/process') ?>" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap">
            <?= \Core\Session::csrfField() ?>
            <input type="hidden" name="import_type" value="all">
            <div class="wk-form-group" style="flex:1;min-width:250px;margin-bottom:0">
                <label>Upload All-in-One CSV</label>
                <input type="file" name="csv_file" accept=".csv" required class="wk-input" style="padding:10px">
            </div>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;cursor:pointer;margin-bottom:2px">
                <input type="checkbox" name="skip_existing" value="1" checked style="width:16px;height:16px;accent-color:var(--wk-purple)"> Skip existing
            </label>
            <button type="submit" class="wk-btn wk-btn-primary" style="margin-bottom:2px">⚡ Import All</button>
        </form>
        <div style="margin-top:12px">
            <a href="<?= $url('admin/import/sample/all') ?>" class="wk-btn wk-btn-secondary wk-btn-sm">📥 Download Sample All-in-One CSV</a>
        </div>
    </div>
</div>

<!-- Individual Imports -->
<div class="wk-card" style="margin-bottom:20px">
    <div class="wk-card-header"><h2>📤 Import by Type</h2><span style="font-size:12px;color:var(--wk-muted)">Import categories, products, or variants separately</span></div>
    <div class="wk-card-body">
        <form method="POST" action="<?= $url('admin/import/process') ?>" enctype="multipart/form-data">
            <?= \Core\Session::csrfField() ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="wk-form-group">
                    <label>Import Type</label>
                    <select name="import_type" class="wk-select" id="importType" onchange="updateGuide()">
                        <option value="categories">📂 Categories</option>
                        <option value="products">📦 Products</option>
                        <option value="variants">🔀 Variants</option>
                    </select>
                </div>
                <div class="wk-form-group">
                    <label>CSV File</label>
                    <input type="file" name="csv_file" accept=".csv" required class="wk-input" style="padding:10px">
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px">
                <label style="display:flex;align-items:center;gap:6px;font-size:13px;font-weight:600;cursor:pointer">
                    <input type="checkbox" name="skip_existing" value="1" checked style="width:16px;height:16px;accent-color:var(--wk-purple)"> Skip existing records
                </label>
                <span style="font-size:11px;color:var(--wk-muted)">Uncheck to update existing products by SKU</span>
            </div>
            <button type="submit" class="wk-btn wk-btn-primary">📤 Import</button>
        </form>
        <div style="margin-top:14px;display:flex;gap:10px">
            <a href="<?= $url('admin/import/sample/categories') ?>" class="wk-btn wk-btn-secondary wk-btn-sm">📥 Categories CSV</a>
            <a href="<?= $url('admin/import/sample/products') ?>" class="wk-btn wk-btn-secondary wk-btn-sm">📥 Products CSV</a>
            <a href="<?= $url('admin/import/sample/variants') ?>" class="wk-btn wk-btn-secondary wk-btn-sm">📥 Variants CSV</a>
        </div>
    </div>
</div>

<!-- Instructions -->
<div class="wk-card" style="margin-bottom:20px">
    <div class="wk-card-header"><h2>📖 How It Works</h2></div>
    <div class="wk-card-body" style="font-size:13px;line-height:1.8">

        <div style="background:var(--wk-bg);border:1px solid var(--wk-border);border-radius:var(--radius-sm);padding:16px;margin-bottom:16px">
            <strong style="font-size:14px">⚡ All-in-One CSV Format</strong>
            <p style="margin:8px 0 4px;color:var(--wk-muted)">Single file with a <code>row_type</code> column. Put categories first, then products, then variants, then combo overrides.</p>
            <div style="overflow-x:auto;margin-top:10px">
                <table class="wk-table" style="font-size:11px;white-space:nowrap">
                    <thead><tr><th>row_type</th><th>name</th><th>parent</th><th>sku</th><th>category</th><th>price</th><th>stock</th><th>variant_group</th><th>options</th><th>combo_sku</th><th>combo_price</th><th>combo_stock</th></tr></thead>
                    <tbody>
                        <tr style="background:var(--wk-purple-soft)"><td><strong>category</strong></td><td>Clothing</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr style="background:var(--wk-purple-soft)"><td><strong>category</strong></td><td>T-Shirts</td><td>Clothing</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr style="background:var(--wk-pink-soft)"><td><strong>product</strong></td><td>White Tee</td><td></td><td>TSH-001</td><td>T-Shirts</td><td>999</td><td>50</td><td></td><td></td><td></td><td></td><td></td></tr>
                        <tr style="background:var(--wk-green-soft)"><td><strong>variant</strong></td><td></td><td></td><td>TSH-001</td><td></td><td></td><td></td><td>Size</td><td>S,M,L,XL</td><td></td><td></td><td></td></tr>
                        <tr style="background:var(--wk-green-soft)"><td><strong>variant</strong></td><td></td><td></td><td>TSH-001</td><td></td><td></td><td></td><td>Color</td><td>White,Black</td><td></td><td></td><td></td></tr>
                        <tr style="background:#fef3c7"><td><strong>combo</strong></td><td></td><td></td><td>TSH-001</td><td></td><td></td><td></td><td></td><td></td><td>TSH-S-WHT</td><td>799</td><td>10</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Individual format guides -->
        <div id="guide-categories">
            <strong>📂 Categories CSV:</strong> <code>name, parent, description, sort_order, is_active</code>
            <p style="color:var(--wk-muted);margin:4px 0">Leave <code>parent</code> empty for top-level. Child categories reference parent by exact name.</p>
        </div>
        <div id="guide-products" style="display:none">
            <strong>📦 Products CSV:</strong> <code>sku, name, category, price, sale_price, stock_quantity, description, short_description, weight, is_active, is_featured, faq, meta_title, meta_description, meta_keywords</code>
            <p style="color:var(--wk-muted);margin:4px 0"><code>sku</code> and <code>name</code> required. <code>category</code> matches by exact name. SEO fields auto-generate if empty.</p>
        </div>
        <div id="guide-variants" style="display:none">
            <strong>🔀 Variants CSV:</strong> <code>product_sku, variant_group, options, combo_sku, combo_price, combo_stock</code>
            <p style="color:var(--wk-muted);margin:4px 0">Group rows define dimensions (Size: S,M,L). Combos auto-generate. Combo rows override individual SKU/price/stock.</p>
        </div>

        <div style="margin-top:14px;padding:14px;background:var(--wk-purple-soft,#ede9fe);border-radius:var(--radius-sm)">
            <strong>❓ The <code>faq</code> column</strong>
            <p style="color:var(--wk-text-muted);margin:6px 0 8px;line-height:1.7">
                Questions you answer up front, shown on the product page under the description. One cell holds
                the lot: separate a question from its answer with <code>::</code> and one pair from the next
                with <code>||</code>.
            </p>
            <div style="background:var(--wk-surface);border:1px solid var(--wk-border);border-radius:6px;padding:10px 12px;font-family:var(--font-mono);font-size:11.5px;line-height:1.8;overflow-x:auto;white-space:nowrap">
                Does it shrink? :: Not if you wash cold. || Is it true to size? :: Yes, order your usual size.
            </div>
            <p style="color:var(--wk-text-muted);margin:8px 0 0;line-height:1.7;font-size:12px">
                Spaces around the separators are ignored. A pair missing either half is skipped rather than
                imported half-finished. Leave the cell empty for no FAQ. Up to 20 per product.
                If your answer needs a comma, wrap the whole cell in quotes, as with any CSV field.
            </p>
        </div>

        <div style="margin-top:14px;padding:12px;background:var(--wk-bg);border:1px solid var(--wk-border);border-radius:var(--radius-sm)">
            <strong>Tips:</strong>
            <span style="color:var(--wk-muted)">Save as UTF-8 CSV. First row = headers. If importing individually, do categories → products → variants in that order. Names are case-sensitive.</span>
        </div>
    </div>
</div>

<div style="text-align:center;padding:20px;background:linear-gradient(135deg,#8b5cf6,#ec4899);border-radius:12px;color:#fff;margin-bottom:20px">
    <h3 style="margin:0 0 6px;font-size:15px">Need Help Importing?</h3>
    <p style="margin:0 0 10px;opacity:.9;font-size:12px">Send me your spreadsheet and I'll import it for you.</p>
    <a href="mailto:mail@lohit.me" style="display:inline-block;background:#fff;color:#8b5cf6;padding:6px 20px;border-radius:8px;font-weight:800;text-decoration:none;font-size:12px">📧 mail@lohit.me</a>
</div>

<script>
function updateGuide() {
    const t = document.getElementById('importType').value;
    document.getElementById('guide-categories').style.display = t==='categories' ? '' : 'none';
    document.getElementById('guide-products').style.display = t==='products' ? '' : 'none';
    document.getElementById('guide-variants').style.display = t==='variants' ? '' : 'none';
}
</script>