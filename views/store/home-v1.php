<?php
$e = fn($v) => \Core\View::e($v);
$url = fn($p) => \Core\View::url($p);
$baseCurrency = \App\Services\CurrencyService::baseCurrency();
$displayCurrency = $_SESSION['wk_display_currency'] ?? $baseCurrency;
$baseSymbol = \App\Services\CurrencyService::baseSymbol();

// Show prices in the visitor's chosen currency only (no base-currency clutter).
$showPrice = fn($amount) => \App\Services\CurrencyService::displayPrice((float) $amount);
$price = $showPrice;

// Never empty: see ShowcaseService.
$carouselProducts = $showcase['products'] ?? [];
$gridProducts = $products;
?>

<!-- Hero -->
<section class="wk-hero">
    <div class="wk-container">
        <h1><?= $e($siteName) ?></h1>
        <p><?= $e($tagline) ?></p>
        <a href="#products" class="wk-hero-btn">Shop Now →</a>
    </div>
</section>

<?php require __DIR__ . '/partials/showcase-note.php'; ?>

<!-- Featured -->
<?php if (count($carouselProducts) > 0): ?>
<section class="wk-section wk-pb-0">
    <div class="wk-container">
        <h2 class="wk-section-title"><?= $icon('sparkles', 20) ?> Featured</h2>
        <p class="wk-section-sub">Our top picks for you</p>
        <?php $wkRowProducts = $carouselProducts; require __DIR__ . '/partials/product-row.php'; ?>
    </div>
</section>
<?php endif; ?>

<!-- All Products Grid -->
<section class="wk-section" id="products">
    <div class="wk-container">
        <h2 class="wk-section-title">All Products</h2>
        <p class="wk-section-sub"><?= count($gridProducts) ?> product<?= count($gridProducts) !== 1 ? 's' : '' ?> available</p>

        <?php if (empty($gridProducts)): ?>
            <div style="text-align:center;padding:60px 0;color:var(--wk-muted)">
                <div style="font-size:48px;margin-bottom:12px;opacity:.3"><?= $icon('package', 48) ?></div>
                <p class="wk-heavy wk-mb-4">No products yet</p>
                <p style="font-size:14px">Check back soon!</p>
            </div>
        <?php else: ?>
            <div class="wk-product-grid">
                <?php foreach ($gridProducts as $p):
                    $prc = $p['sale_price'] ?: $p['price'];
                    $hasSale = $p['sale_price'] && $p['sale_price'] < $p['price'];
                ?>
                <div class="wk-product-card">
                    <div class="wk-product-img" onclick="window.location='<?= $url('product/'.urlencode($p['slug'])) ?>'">
                        <?php if ($p['image']): ?>
                            <?= \Core\View::productImage($p['image'], $p['name']) ?>
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:48px;opacity:.15"><?= $icon('package', 48) ?></div>
                        <?php endif; ?>
                        <?php if ($p['stock_quantity'] <= 0): ?>
                            <span class="wk-product-badge" style="background:#dc2626">Sold Out</span>
                        <?php elseif ($hasSale): ?>
                            <span class="wk-product-badge">Sale</span>
                        <?php elseif ($p['is_featured']): ?>
                            <span class="wk-product-badge featured">Featured</span>
                        <?php endif; ?>
                    </div>
                    <div class="wk-product-info" onclick="window.location='<?= $url('product/'.urlencode($p['slug'])) ?>'" style="cursor:pointer">
                        <?php if ($p['category_name'] ?? null): ?>
                            <div class="wk-product-cat"><?= $e($p['category_name']) ?></div>
                        <?php endif; ?>
                        <div class="wk-product-name"><?= $e($p['name']) ?></div>
                        <?= \App\Services\ReviewService::cardRatingHtml((int) $p['id']) ?>
                        <div class="wk-product-price">
                            <span class="current"><?= $price($prc) ?></span>
                            <?php if ($hasSale): ?><span class="original"><?= $price($p['price']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($p['stock_quantity'] > 0): ?>
                        <?php if (($p['variant_count'] ?? 0) > 0): ?>
                            <a href="<?= $url('product/'.urlencode($p['slug'])) ?>" class="wk-block-link wk-add-btn"><?= $icon('palette') ?> View Options</a>
                        <?php else: ?>
                            <button class="wk-add-btn" data-add-to-cart="<?= $p['id'] ?>"><?= $icon('shopping-cart') ?> Add to Cart</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="wk-add-btn" disabled style="opacity:.5;cursor:not-allowed">Sold Out</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="text-align:center;margin-top:32px">
                <a href="<?= $url('shop') ?>" style="display:inline-block;padding:14px 36px;background:var(--wk-purple-ink);color:#fff;border-radius:12px;font-weight:800;text-decoration:none;font-size:15px;transition:all .2s">View All Products →</a>
            </div>
        <?php endif; ?>
    </div>
</section>
