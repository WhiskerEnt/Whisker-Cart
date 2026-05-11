<?php
$e = fn($v) => \Core\View::e($v);
$url = fn($p) => \Core\View::url($p);
$baseCurrency = \App\Services\CurrencyService::baseCurrency();
$displayCurrency = $_SESSION['wk_display_currency'] ?? $baseCurrency;
$baseSymbol = \App\Services\CurrencyService::baseSymbol();

$showPrice = function($amount) use ($baseSymbol, $baseCurrency, $displayCurrency) {
    $base = $baseSymbol . number_format($amount, 2);
    if ($displayCurrency === $baseCurrency) return $base;
    $converted = \App\Services\CurrencyService::convert($amount, $baseCurrency, $displayCurrency);
    return \App\Services\CurrencyService::format($converted, $displayCurrency)
         . ' <span style="font-size:11px;color:rgba(255,255,255,.6);font-weight:500">(' . $base . ')</span>';
};
$price = $showPrice;

$showPriceNormal = function($amount) use ($baseSymbol, $baseCurrency, $displayCurrency) {
    $base = $baseSymbol . number_format($amount, 2);
    if ($displayCurrency === $baseCurrency) return $base;
    $converted = \App\Services\CurrencyService::convert($amount, $baseCurrency, $displayCurrency);
    return \App\Services\CurrencyService::format($converted, $displayCurrency)
         . ' <span style="font-size:11px;color:var(--wk-muted);font-weight:500">(' . $base . ')</span>';
};
$priceNormal = $showPriceNormal;

$carouselProducts = array_values(array_filter($products, fn($p) => $p['is_featured']));
$gridProducts = $products;
?>

<!-- ═══ HERO CAROUSEL ═══ -->
<?php if (count($carouselProducts) > 0): ?>
<section class="wk-hero-carousel" id="featured">
    <div class="wk-hero-track wk-carousel-track">
        <?php foreach ($carouselProducts as $i => $p):
            $prc = $p['sale_price'] ?: $p['price'];
            $hasSale = $p['sale_price'] && $p['sale_price'] < $p['price'];
        ?>
        <div class="wk-hero-slide wk-carousel-slide">
            <!-- Background image -->
            <?php if ($p['image']): ?>
            <div class="wk-hero-slide-bg">
                <img src="<?= $url('storage/uploads/products/'.$p['image']) ?>" alt="">
            </div>
            <?php endif; ?>
            <!-- Gradient overlay -->
            <div class="wk-hero-slide-overlay"></div>
            <!-- Content -->
            <div class="wk-container wk-hero-slide-content">
                <div class="wk-hero-slide-text">
                    <?php if ($hasSale): ?><span class="wk-hero-badge">🔥 Sale</span><?php endif; ?>
                    <?php if ($p['category_name'] ?? null): ?><div class="wk-hero-cat"><?= $e($p['category_name']) ?></div><?php endif; ?>
                    <h2 class="wk-hero-name"><?= $e($p['name']) ?></h2>
                    <?php if ($p['short_description']): ?><p class="wk-hero-desc"><?= $e($p['short_description']) ?></p><?php endif; ?>
                    <div class="wk-hero-price">
                        <span class="wk-hero-price-current"><?= $price($prc) ?></span>
                        <?php if ($hasSale): ?><span class="wk-hero-price-original"><?= $price($p['price']) ?></span><?php endif; ?>
                    </div>
                    <div class="wk-hero-actions">
                        <a href="<?= $url('product/'.urlencode($p['slug'])) ?>" class="wk-hero-btn-primary">View Product →</a>
                        <?php if ($p['stock_quantity'] > 0 && ($p['variant_count'] ?? 0) == 0): ?>
                        <button class="wk-hero-btn-cart" data-add-to-cart="<?= $p['id'] ?>">🛒 Add to Cart</button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="wk-hero-slide-img" onclick="window.location='<?= $url('product/'.urlencode($p['slug'])) ?>'">
                    <?php if ($p['image']): ?>
                    <img src="<?= $url('storage/uploads/products/'.$p['image']) ?>" alt="<?= $e($p['name']) ?>">
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <!-- Nav arrows -->
    <button class="wk-hero-prev wk-carousel-prev">‹</button>
    <button class="wk-hero-next wk-carousel-next">›</button>
    <!-- Dots -->
    <div class="wk-hero-dots wk-carousel-dots"></div>
    <!-- Slide counter -->
    <div class="wk-hero-counter"><span class="wk-hero-counter-current">1</span> / <?= count($carouselProducts) ?></div>
</section>
<?php else: ?>
<!-- Fallback hero if no featured products -->
<section style="background:linear-gradient(135deg, var(--wk-purple), var(--wk-pink));padding:80px 0;position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;opacity:.06;background:url('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%22100%22 height=%22100%22><circle cx=%2250%22 cy=%2250%22 r=%2240%22 fill=%22white%22/></svg>') repeat;background-size:120px"></div>
    <div class="wk-container" style="position:relative;text-align:center">
        <h1 style="font-size:clamp(32px,5vw,52px);font-weight:900;color:#fff;margin-bottom:12px;line-height:1.1"><?= $e($heroTitle ?? $siteName) ?></h1>
        <p style="font-size:clamp(16px,2vw,20px);color:rgba(255,255,255,.85);max-width:600px;margin:0 auto 28px;font-weight:500"><?= $e($heroSubtitle ?? $tagline) ?></p>
        <a href="<?= $url('shop') ?>" style="display:inline-block;padding:16px 40px;background:#fff;color:var(--wk-purple);border-radius:14px;font-weight:800;font-size:16px;text-decoration:none;box-shadow:0 4px 20px rgba(0,0,0,.15)"><?= $e($heroCta ?? 'Shop Now') ?> →</a>
    </div>
</section>
<?php endif; ?>

<!-- ═══ FEATURED CATEGORIES ═══ -->
<?php if (!empty($categories)): ?>
<section class="wk-section" style="padding-bottom:0">
    <div class="wk-container">
        <h2 class="wk-section-title">Shop by Category</h2>
        <p class="wk-section-sub">Browse our collections</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;margin-top:24px">
            <?php foreach ($categories as $cat): ?>
            <a href="<?= $url('category/'.urlencode($cat['slug'])) ?>" style="display:block;text-decoration:none;background:var(--wk-card);border:2px solid var(--wk-border);border-radius:16px;overflow:hidden;transition:all .2s">
                <div style="height:140px;overflow:hidden;background:var(--wk-bg)">
                    <?php if ($cat['cover_image'] ?? null): ?>
                    <img src="<?= $url('storage/uploads/products/'.$cat['cover_image']) ?>" alt="<?= $e($cat['name']) ?>" style="width:100%;height:100%;object-fit:cover;transition:transform .3s" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:40px;opacity:.15">📦</div>
                    <?php endif; ?>
                </div>
                <div style="padding:14px 16px">
                    <div style="font-weight:800;font-size:15px;color:var(--wk-text)"><?= $e($cat['name']) ?></div>
                    <div style="font-size:12px;color:var(--wk-muted);margin-top:2px"><?= (int)($cat['product_count'] ?? 0) ?> product<?= ($cat['product_count'] ?? 0) != 1 ? 's' : '' ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══ ON SALE ═══ -->
<?php if (!empty($saleProducts)): ?>
<section class="wk-section" style="padding-bottom:0">
    <div class="wk-container">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <div>
                <h2 class="wk-section-title" style="margin-bottom:0">🔥 On Sale</h2>
                <p class="wk-section-sub" style="margin-top:4px">Limited time offers</p>
            </div>
            <a href="<?= $url('shop?sort=price_low') ?>" style="font-size:14px;font-weight:700;color:var(--wk-purple);text-decoration:none">View All →</a>
        </div>
        <div style="display:flex;gap:16px;overflow-x:auto;scroll-snap-type:x mandatory;padding-bottom:12px;-webkit-overflow-scrolling:touch">
            <?php foreach ($saleProducts as $p):
                $prc = $p['sale_price'];
                $discPct = (int)$p['discount_pct'];
            ?>
            <div style="flex:0 0 220px;scroll-snap-align:start;background:var(--wk-card);border:2px solid var(--wk-border);border-radius:16px;overflow:hidden;transition:all .2s">
                <div onclick="window.location='<?= $url('product/'.urlencode($p['slug'])) ?>'" style="height:180px;overflow:hidden;cursor:pointer;position:relative;background:var(--wk-bg)">
                    <?php if ($p['image']): ?>
                    <img src="<?= $url('storage/uploads/products/'.$p['image']) ?>" alt="<?= $e($p['name']) ?>" style="width:100%;height:100%;object-fit:cover">
                    <?php else: ?>
                    <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:40px;opacity:.15">📦</div>
                    <?php endif; ?>
                    <span style="position:absolute;top:10px;left:10px;background:#ef4444;color:#fff;font-size:12px;font-weight:800;padding:4px 10px;border-radius:8px"><?= $discPct ?>% OFF</span>
                </div>
                <div onclick="window.location='<?= $url('product/'.urlencode($p['slug'])) ?>'" style="padding:14px 16px;cursor:pointer">
                    <div style="font-weight:800;font-size:14px;color:var(--wk-text);margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= $e($p['name']) ?></div>
                    <div class="wk-product-price">
                        <span class="current"><?= $priceNormal($prc) ?></span>
                        <span class="original"><?= $priceNormal($p['price']) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══ ALL PRODUCTS ═══ -->
<section class="wk-section" id="products">
    <div class="wk-container">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
            <div>
                <h2 class="wk-section-title" style="margin-bottom:0">New Arrivals</h2>
                <p class="wk-section-sub" style="margin-top:4px"><?= count($gridProducts) ?> product<?= count($gridProducts) !== 1 ? 's' : '' ?></p>
            </div>
            <a href="<?= $url('shop') ?>" style="font-size:14px;font-weight:700;color:var(--wk-purple);text-decoration:none">View All →</a>
        </div>
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
                    <div class="wk-product-img" onclick="window.location='<?= $url('product/'.urlencode($p['slug'])) ?>'">
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
                    <div class="wk-product-info" onclick="window.location='<?= $url('product/'.urlencode($p['slug'])) ?>'" style="cursor:pointer">
                        <?php if ($p['category_name'] ?? null): ?><div class="wk-product-cat"><?= $e($p['category_name']) ?></div><?php endif; ?>
                        <div class="wk-product-name"><?= $e($p['name']) ?></div>
                        <div class="wk-product-price">
                            <span class="current"><?= $priceNormal($prc) ?></span>
                            <?php if ($hasSale): ?><span class="original"><?= $priceNormal($p['price']) ?></span><?php endif; ?>
                        </div>
                    </div>
                    <?php if ($p['stock_quantity'] > 0): ?>
                        <?php if (($p['variant_count'] ?? 0) > 0): ?>
                            <a href="<?= $url('product/'.urlencode($p['slug'])) ?>" class="wk-add-btn" style="text-decoration:none;text-align:center;display:block">🎨 View Options</a>
                        <?php else: ?>
                            <button class="wk-add-btn" data-add-to-cart="<?= $p['id'] ?>">🛒 Add to Cart</button>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="wk-add-btn" disabled style="opacity:.5;cursor:not-allowed">Sold Out</button>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align:center;margin-top:40px">
                <a href="<?= $url('shop') ?>" style="display:inline-block;padding:16px 40px;background:var(--wk-purple);color:#fff;border-radius:14px;font-weight:800;text-decoration:none;font-size:15px;transition:all .2s">Browse All Products →</a>
            </div>
        <?php endif; ?>
    </div>
</section>
