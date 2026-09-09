<?php $url=fn($p)=>\Core\View::url($p); $e=fn($v)=>\Core\View::e($v); ?>
<section class="wk-section"><div class="wk-container" style="max-width:800px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
        <h1 style="font-size:24px;font-weight:900">My Orders</h1>
        <a href="<?= $url('account') ?>" style="font-size:13px;font-weight:700;color:var(--wk-purple-ink)">← Back to Account</a>
    </div>
    <?php if (empty($orders)): ?>
        <div style="text-align:center;padding:60px;color:var(--wk-muted);background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius)">
            <div style="font-size:48px;margin-bottom:12px;opacity:.3">📦</div>
            <p style="font-weight:800;margin-bottom:4px">No orders yet</p>
            <p style="font-size:13px"><a href="<?= $url('') ?>" style="color:var(--wk-purple-ink)">Start shopping →</a></p>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $o):
            // Fetch first few items for thumbnails
            $orderItems = \Core\Database::fetchAll(
                "SELECT oi.product_name,
                        (SELECT image_path FROM wk_product_images WHERE product_id=oi.product_id AND is_primary=1 LIMIT 1) AS image
                 FROM wk_order_items oi WHERE oi.order_id=? LIMIT 3",
                [$o['id']]
            );
            $totalItems = \Core\Database::fetchValue("SELECT COUNT(*) FROM wk_order_items WHERE order_id=?", [$o['id']]);
            $statusColors = ['pending'=>['#ede9fe','#8b5cf6'],'processing'=>['#dbeafe','#3b82f6'],'paid'=>['#d1fae5','#10b981'],
                'shipped'=>['#dbeafe','#3b82f6'],'delivered'=>['#d1fae5','#10b981'],'cancelled'=>['#fee2e2','#ef4444'],'refunded'=>['#fee2e2','#ef4444']];
            $sc = $statusColors[$o['status']] ?? ['#ede9fe','#8b5cf6'];
        ?>
        <a href="<?= $url('account/order/'.$o['id']) ?>" style="display:block;background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:20px;margin-bottom:12px;text-decoration:none;transition:border-color .2s">
            <!-- Top row: Order # + Status + Total -->
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <div>
                    <span style="font-family:var(--font-mono);font-size:14px;font-weight:700;color:var(--wk-purple-ink)"><?= $e($o['order_number']) ?></span>
                    <span style="font-size:12px;color:var(--wk-muted);margin-left:8px"><?= date('M j, Y', strtotime($o['created_at'])) ?></span>
                </div>
                <div style="display:flex;align-items:center;gap:10px">
                    <span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:800;text-transform:uppercase;background:<?= $sc[0] ?>;color:<?= $sc[1] ?>"><?= ucfirst($o['status']) ?></span>
                    <span style="font-weight:900;font-family:var(--font-mono);color:var(--wk-text)"><?= \Core\View::price($o['total']) ?></span>
                </div>
            </div>
            <!-- Item thumbnails -->
            <div style="display:flex;align-items:center;gap:8px">
                <?php foreach ($orderItems as $oi): ?>
                <div style="width:44px;height:44px;border-radius:8px;overflow:hidden;background:var(--wk-bg);flex-shrink:0;display:flex;align-items:center;justify-content:center;border:1px solid var(--wk-border)">
                    <?php if (!empty($oi['image'])): ?>
                        <img src="<?= $url('storage/uploads/products/'.$oi['image']) ?>" style="width:100%;height:100%;object-fit:cover" alt="">
                    <?php else: ?>
                        <span style="font-size:16px;opacity:.3">📦</span>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if ($totalItems > 3): ?>
                    <div style="width:44px;height:44px;border-radius:8px;background:var(--wk-bg);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:var(--wk-muted);border:1px solid var(--wk-border)">+<?= $totalItems - 3 ?></div>
                <?php endif; ?>
                <div style="flex:1;margin-left:8px;font-size:13px;color:var(--wk-muted)">
                    <?= $totalItems ?> item<?= $totalItems!==1?'s':'' ?>
                </div>
                <span style="font-size:14px;color:var(--wk-purple-ink)">→</span>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div></section>