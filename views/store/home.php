<?php
$e = fn($v) => \Core\View::e($v);
$url = fn($p) => \Core\View::url($p);
$baseCurrency = \App\Services\CurrencyService::baseCurrency();
$displayCurrency = $_SESSION['wk_display_currency'] ?? $baseCurrency;
$baseSymbol = \App\Services\CurrencyService::baseSymbol();

// Smart price display — shows converted if different currency selected
$showPrice = function($amount) use ($baseSymbol, $baseCurrency, $displayCurrency) {
    $base = $baseSymbol . number_format($amount, 2);
    if ($displayCurrency === $baseCurrency) return $base;
    $converted = \App\Services\CurrencyService::convert($amount, $baseCurrency, $displayCurrency);
    return \App\Services\CurrencyService::format($converted, $displayCurrency)
         . ' <span style="font-size:11px;color:var(--wk-muted);font-weight:500">(' . $base . ')</span>';
};

$price = $showPrice; // alias

// Split products: featured for carousel, rest for grid
$carouselProducts = array_filter($products, fn($p) => $p['is_featured']);
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

<!-- Featured Carousel -->
<?php if (count($carouselProducts) > 0): ?>
<section class="wk-section" style="padding-bottom:0">
    <div class="wk-container">
        <h2 class="wk-section-title">✨ Featured</h2>
        <p class="wk-section-sub">Our top picks for you</p>

        <div class="wk-carousel">
            <div class="wk-carousel-track">
                <?php foreach ($carouselProducts as $p):
                    $prc = $p['sale_price'] ?: $p['price'];
                    $hasSale = $p['sale_price'] && $p['sale_price'] < $p['price'];
                ?>
                <div class="wk-carousel-slide">
                    <div class="wk-carousel-slide-inner">
                        <div class="wk-carousel-img" onclick="window.location='<?= $url('product/'.$p['slug']) ?>'">
                            <?php if ($p['image']): ?>
                                <img src="<?= $url('storage/uploads/products/'.$p['image']) ?>" alt="<?= $e($p['name']) ?>">
                            <?php else: ?>
                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:64px;opacity:.15;background:var(--wk-bg)">📦</div>
                            <?php endif; ?>
                            <?php if ($hasSale): ?>
                                <span class="wk-product-badge">Sale</span>
                            <?php endif; ?>
                        </div>
                        <div class="wk-carousel-info">
                            <?php if ($p['category_name'] ?? null): ?>
                                <div class="wk-product-cat"><?= $e($p['category_name']) ?></div>
                            <?php endif; ?>
                            <h3 class="wk-carousel-name"><?= $e($p['name']) ?></h3>
                            <?php if ($p['short_description']): ?>
                                <p class="wk-carousel-desc"><?= $e($p['short_description']) ?></p>
                            <?php endif; ?>
                            <div class="wk-product-price" style="margin-bottom:16px">
                                <span class="current" style="font-size:24px"><?= $price($prc) ?></span>
                                <?php if ($hasSale): ?><span class="original"><?= $price($p['price']) ?></span><?php endif; ?>
                            </div>
                            <?php if ($p['stock_quantity'] > 0): ?>
                                <?php if (($p['variant_count'] ?? 0) > 0): ?>
                                    <a href="<?= $url('product/'.$p['slug']) ?>" class="wk-carousel-add-btn" style="text-decoration:none;text-align:center;display:block">🎨 View Options →</a>
                                <?php else: ?>
                                    <button class="wk-carousel-add-btn" data-add-to-cart="<?= $p['id'] ?>">🛒 Quick Add to Cart</button>
                                <?php endif; ?>
                            <?php else: ?>
                                <button class="wk-carousel-add-btn" disabled style="opacity:.5;cursor:not-allowed;background:#6b7280">Sold Out</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Controls -->
            <button class="wk-carousel-prev">‹</button>
            <button class="wk-carousel-next">›</button>
            <div class="wk-carousel-dots"></div>
        </div>
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
                <div style="font-size:48px;margin-bottom:12px;opacity:.3">📦</div>
                <p style="font-weight:800;margin-bottom:4px">No products yet</p>
                <p style="font-size:14px">Check back soon!</p>
            </div>
        <?php else: ?>
            <div class="wk-product-grid">
                <?php foreach ($gridProducts as $p):
                    $prc = $p['sale_price'] ?: $p['price'];
                    $hasSale = $p['sale_price'] && $p['sale_price'] < $p['price'];
                ?>
                <div class="wk-product-card">
                    <div class="wk-product-img" onclick="window.location='<?= $url('product/'.$p['slug']) ?>'">
                        <?php if ($p['image']): ?>
                            <img src="<?= $url('storage/uploads/products/'.$p['image']) ?>" alt="<?= $e($p['name']) ?>">
                        <?php else: ?>
                            <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:48px;opacity:.15">📦</div>
                        <?php endif; ?>
                        <?php if ($p['stock_quantity'] <= 0): ?>
                            <span class="wk-product-badge" style="background:#ef4444">Sold Out</span>
                        <?php elseif ($hasSale): ?>
                            <span class="wk-product-badge">Sale</span>
                        <?php elseif ($p['is_featured']): ?>
                            <span class="wk-product-badge featured">Featured</span>
                        <?php endif; ?>
                    </div>
                    <div class="wk-product-info">
                        <?php if ($p['category_name'] ?? null): ?>
                            <div class="wk-product-cat"><?= $e($p['category_name']) ?></div>
                        <?php endif; ?>
                        <div class="wk-product-name"><?= $e($p['name']) ?></div>
                        <div class="wk-product-price">
                            <span class="current"><?= $price($prc) ?></span>
                            <?php if ($hasSale): ?><span class="original"><?= $price($p['price']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($p['stock_quantity'] > 0): ?>
                        <?php if (($p['variant_count'] ?? 0) > 0): ?>
                            <a href="<?= $url('product/'.$p['slug']) ?>" class="wk-add-btn" style="text-decoration:none;text-align:center;display:block">🎨 View Options</a>
                        <?php else: ?>
                            <button class="wk-add-btn" data-add-to-cart="<?= $p['id'] ?>">🛒 Add to Cart</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="wk-add-btn" disabled style="opacity:.5;cursor:not-allowed">Sold Out</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- View All Link -->
            <div style="text-align:center;margin-top:32px">
                <a href="<?= $url('shop') ?>" style="display:inline-block;padding:14px 36px;background:var(--wk-purple);color:#fff;border-radius:12px;font-weight:800;text-decoration:none;font-size:15px;transition:all .2s">View All Products →</a>
            </div>
        <?php endif; ?>
    </div>
</section>