<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); $price=fn($v)=>\Core\View::price($v); ?>

<div class="wk-row-between">
    <p class="wk-muted wk-semibold"><?= count($products) ?> product<?= count($products)!==1?'s':'' ?></p>
    <a href="<?= $url('admin/products/create') ?>" class="wk-btn wk-btn-primary">+ Add Product</a>
</div>

<?php if (!empty($products)): $wkShowcase = \App\Services\ShowcaseService::pick(); ?>
<div class="wk-showcase-summary wk-mb-20">
    <?= $icon('sparkles', 14) ?> <?= $e(\App\Services\ShowcaseService::describe($wkShowcase)) ?>
    <?php if (\App\Services\ShowcaseService::autoCount($wkShowcase) > 0): ?>
        To choose your own, open a product and tick <strong>Show in homepage slider</strong>.
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($products)): ?>
    <div class="wk-card"><div class="wk-empty"><div class="wk-empty-icon"><?= $icon('package', 48) ?></div><p style="font-weight:800;margin-bottom:4px">No products yet</p><p><a href="<?= $url('admin/products/create') ?>">Add your first product</a></p></div></div>
<?php else: ?>
    <div class="wk-card">
        <table class="wk-table">
            <thead><tr><th style="width:50px"></th><th>Product</th><th>SKU</th><th>Price</th><th>Stock</th><th>Sold</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($products as $p): ?>
                <tr style="cursor:pointer" onclick="window.location='<?= $url('admin/products/edit/'.$p['id']) ?>'">
                    <td>
                        <div style="width:44px;height:44px;border-radius:8px;background:var(--wk-bg);overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:18px">
                            <?php if ($p['image']): ?>
                                <img src="<?= $url('storage/uploads/products/'.$p['image']) ?>" class="wk-cover" alt="">
                            <?php else: ?><?= $icon('package', 18) ?><?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="wk-heavy"><?= $e($p['name']) ?></div>
                        <?php if ($p['category_name']): ?><div style="font-size:11px;color:var(--wk-purple);font-weight:700"><?= $e($p['category_name']) ?></div><?php endif; ?>
                    </td>
                    <td><code style="font-family:var(--font-mono);font-size:12px;background:var(--wk-bg);padding:2px 6px;border-radius:4px"><?= $e($p['sku']) ?></code></td>
                    <td class="wk-mono wk-bold">
                        <?= $price($p['sale_price'] ?? $p['price']) ?>
                        <?php if ($p['sale_price'] && $p['sale_price'] < $p['price']): ?>
                            <span style="text-decoration:line-through;color:var(--wk-text-muted);font-size:11px;margin-left:4px"><?= $price($p['price']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($p['stock_quantity'] <= 0): ?>
                            <span class="wk-badge wk-badge-danger">Out of stock</span>
                        <?php elseif ($p['stock_quantity'] <= ($p['low_stock_threshold'] ?? 5)): ?>
                            <span class="wk-badge wk-badge-warning"><?= $p['stock_quantity'] ?> left</span>
                        <?php else: ?>
                            <span class="wk-bold"><?= $p['stock_quantity'] ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="font-weight:800;color:<?= $p['total_sold'] > 0 ? 'var(--wk-green)' : 'var(--wk-text-muted)' ?>"><?= $p['total_sold'] ?></span>
                    </td>
                    <td><span class="wk-badge <?= $p['is_active'] ? 'wk-badge-success' : 'wk-badge-danger' ?>"><?= $p['is_active'] ? 'Active' : 'Draft' ?></span></td>
                    <td onclick="event.stopPropagation()">
                        <div style="display:flex;gap:6px">
                            <a href="<?= $url('admin/products/edit/'.$p['id']) ?>" class="wk-btn wk-btn-secondary wk-btn-sm" style="padding:4px 12px;border-radius:6px;font-size:12px">Edit</a>
                            <form method="POST" action="<?= $url('admin/products/delete/'.$p['id']) ?>" onsubmit="return confirm('Delete &quot;<?= $e($p['name']) ?>&quot;? This cannot be undone.')" style="display:inline">
                                <?= \Core\Session::csrfField() ?>
                                <button type="submit" class="wk-btn wk-btn-sm" style="background:none;border:2px solid var(--wk-red);color:var(--wk-red);padding:4px 12px;border-radius:6px;font-weight:700;cursor:pointer;font-size:12px">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>