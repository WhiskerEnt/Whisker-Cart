<?php
/**
 * The cart, as a page.
 *
 * Quantity and removal go through the same endpoints the drawer uses, then
 * reload: the subtotal, the delivery threshold and the suggestions below all
 * depend on what is in the basket, and re-deriving them in the browser would
 * be a second implementation of sums the server already does.
 */
$e     = fn($v) => \Core\View::e($v);
$url   = fn($p) => \Core\View::url($p);
$price = fn($v) => \App\Services\CurrencyService::displayPrice((float) $v);

$count = array_sum(array_column($items, 'quantity'));
?>
<section class="wk-section">
    <div class="wk-container" style="max-width:1000px">

        <h1 style="font-size:26px;font-weight:900;margin-bottom:4px">Your Cart</h1>
        <p style="color:var(--wk-muted);font-size:14px;margin-bottom:24px">
            <?= $count ? $count . ' ' . ($count === 1 ? 'item' : 'items') : 'Nothing here yet' ?>
        </p>

        <?php if (!$items): ?>
            <div style="text-align:center;padding:56px 20px;background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius)">
                <div style="font-size:44px;opacity:.35;margin-bottom:10px">🛒</div>
                <p style="font-weight:800;margin-bottom:6px">Your cart is empty</p>
                <p style="font-size:14px;color:var(--wk-muted);margin-bottom:20px">Once you add something it will show up here.</p>
                <a href="<?= $url('shop') ?>" class="wk-checkout-btn" style="display:inline-block;width:auto;padding:14px 32px;text-decoration:none">Browse products</a>
            </div>
        <?php else: ?>

        <div class="wk-cart-page">
            <div>
                <?php if ($freeShipping): ?>
                    <?php $pct = min(100, (int) round(($subtotal / $freeShipping['threshold']) * 100)); ?>
                    <div class="wk-ship-progress">
                        <p>
                            <?php if ($freeShipping['qualified']): ?>
                                ✓ Your order qualifies for <strong>free delivery</strong>.
                            <?php else: ?>
                                <strong><?= $e($price($freeShipping['remaining'])) ?></strong> away from free delivery.
                            <?php endif; ?>
                        </p>
                        <div class="wk-ship-bar" role="progressbar"
                             aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100"
                             aria-label="Progress towards free delivery">
                            <span style="width:<?= $pct ?>%"></span>
                        </div>
                    </div>
                <?php endif; ?>

                <ul class="wk-cart-lines">
                    <?php foreach ($items as $item): ?>
                        <li class="wk-cart-line" data-line="<?= (int) $item['id'] ?>">
                            <a href="<?= $url('product/' . urlencode($item['slug'])) ?>" class="wk-cart-line-img">
                                <?php if ($item['image']): ?>
                                    <?= \Core\View::productImage($item['image'], $item['name']) ?>
                                <?php else: ?>
                                    <span aria-hidden="true">📦</span>
                                <?php endif; ?>
                            </a>

                            <div class="wk-cart-line-info">
                                <a href="<?= $url('product/' . urlencode($item['slug'])) ?>" class="wk-cart-line-name"><?= $e($item['name']) ?></a>
                                <?php if (!empty($item['variant_label'])): ?>
                                    <div class="wk-cart-line-variant"><?= $e($item['variant_label']) ?></div>
                                <?php endif; ?>
                                <div class="wk-cart-line-unit"><?= $e($price($item['unit_price'])) ?> each</div>

                                <div class="wk-cart-line-controls">
                                    <div class="wk-qty-ctrl">
                                        <button type="button" class="wk-qty-btn" aria-label="Reduce quantity"
                                                onclick="wkCartQty(<?= (int) $item['id'] ?>, <?= (int) $item['quantity'] - 1 ?>)">−</button>
                                        <input class="wk-qty-val" value="<?= (int) $item['quantity'] ?>" readonly
                                               aria-label="Quantity of <?= $e($item['name']) ?>">
                                        <button type="button" class="wk-qty-btn" aria-label="Increase quantity"
                                                onclick="wkCartQty(<?= (int) $item['id'] ?>, <?= (int) $item['quantity'] + 1 ?>)">+</button>
                                    </div>
                                    <button type="button" class="wk-cart-line-remove"
                                            onclick="wkCartRemove(<?= (int) $item['id'] ?>)">Remove</button>
                                </div>
                            </div>

                            <div class="wk-cart-line-total"><?= $e($price($item['unit_price'] * $item['quantity'])) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <aside class="wk-cart-summary">
                <h2>Summary</h2>
                <div class="wk-cart-summary-row">
                    <span>Subtotal</span>
                    <strong><?= $e($price($subtotal)) ?></strong>
                </div>
                <p class="wk-cart-summary-note">Delivery and tax are worked out at checkout, once you have given an address.</p>
                <a href="<?= $url('checkout') ?>" class="wk-checkout-btn" style="display:block;text-align:center;text-decoration:none">Checkout</a>
                <a href="<?= $url('shop') ?>" class="wk-cart-keep-shopping">Keep shopping</a>
            </aside>
        </div>

        <?php endif; ?>

        <?php if (!empty($alsoLike)): ?>
            <section style="margin-top:48px">
                <h2 class="wk-section-title" style="font-size:20px;margin-bottom:16px">You might also like</h2>
                <div class="wk-product-grid">
                    <?php foreach ($alsoLike as $p): ?>
                        <?php $prc = $p['sale_price'] ?: $p['price']; ?>
                        <div class="wk-product-card">
                            <a href="<?= $url('product/' . urlencode($p['slug'])) ?>" class="wk-product-img">
                                <?php if ($p['image']): ?>
                                    <?= \Core\View::productImage($p['image'], $p['name']) ?>
                                <?php endif; ?>
                            </a>
                            <div class="wk-product-info">
                                <a href="<?= $url('product/' . urlencode($p['slug'])) ?>" class="wk-product-name"><?= $e($p['name']) ?></a>
                                <div class="wk-product-price"><?= $e($price($prc)) ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    </div>
</section>

<script>
// The cart page reloads after a change rather than patching itself: the
// summary, the delivery threshold and the suggestions are all server-derived.
function wkCartPost(path, fields) {
    const form = new FormData();
    Object.entries(fields).forEach(([k, v]) => form.append(k, v));
    return WhiskerStore.cartFetch(path, form).then((data) => {
        if (data === null) return;          // reloading over a stale token
        window.location.reload();
    });
}
function wkCartQty(itemId, quantity) {
    if (quantity < 1) return wkCartRemove(itemId);
    wkCartPost('cart/update', { item_id: itemId, quantity: quantity });
}
function wkCartRemove(itemId) {
    wkCartPost('cart/remove', { item_id: itemId });
}
</script>
