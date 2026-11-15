<?php
/**
 * A row of product cards that scrolls sideways: the homepage's featured and
 * on-sale rows.
 *
 * Set before requiring this:
 *   $wkRowProducts  array  product rows with image, category_name and variant_count
 */
$wkRowEsc   = fn($v) => \Core\View::e((string) $v);
$wkRowUrl   = fn($p) => \Core\View::url($p);
$wkRowPrice = fn($amount) => \App\Services\CurrencyService::displayPrice((float) $amount);
?>
<div class="wk-row-scroll">
    <?php foreach ($wkRowProducts as $wkP):
        $wkOnSale = $wkP['sale_price'] && $wkP['sale_price'] < $wkP['price'];
        $wkOff = $wkOnSale && $wkP['price'] > 0 ? (int) round((1 - $wkP['sale_price'] / $wkP['price']) * 100) : 0;
        $wkLink = $wkRowUrl('product/' . urlencode($wkP['slug']));
    ?>
    <a href="<?= $wkRowEsc($wkLink) ?>" class="wk-row-card">
        <span class="wk-row-card-img">
            <?php if ($wkP['image']): ?>
                <?= \Core\View::productImage($wkP['image'], $wkP['name'], ['sizes' => '220px']) ?>
            <?php else: ?>
                <span class="wk-row-card-empty"><?= $icon('package', 40) ?></span>
            <?php endif; ?>
            <?php if ($wkOff > 0): ?><span class="wk-row-badge"><?= $wkOff ?>% OFF</span><?php endif; ?>
            <?php if ($wkP['stock_quantity'] <= 0): ?><span class="wk-row-badge wk-row-badge-out">Sold Out</span><?php endif; ?>
        </span>
        <span class="wk-row-card-body">
            <span class="wk-row-card-name"><?= $wkRowEsc($wkP['name']) ?></span>
            <span class="wk-product-price">
                <span class="current"><?= $wkRowPrice($wkOnSale ? $wkP['sale_price'] : $wkP['price']) ?></span>
                <?php if ($wkOnSale): ?><span class="original"><?= $wkRowPrice($wkP['price']) ?></span><?php endif; ?>
            </span>
        </span>
    </a>
    <?php endforeach; ?>
</div>
