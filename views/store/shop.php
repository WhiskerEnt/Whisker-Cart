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
         . ' <span style="font-size:11px;color:var(--wk-muted);font-weight:500">(' . $base . ')</span>';
};
$price = $showPrice;

// Build current URL params for sort/pagination links
$currentParams = [];
if (!empty($currentCategory)) $currentParams['category'] = $currentCategory['slug'];
if ($sort !== 'newest') $currentParams['sort'] = $sort;

$buildUrl = function($overrides = []) use ($url, $currentParams) {
    $params = array_merge($currentParams, $overrides);
    $qs = http_build_query($params);
    return $url('shop') . ($qs ? '?' . $qs : '');
};
?>

<!-- Shop Header -->
<section style="padding:32px 0 0;background:var(--wk-bg)">
    <div class="wk-container">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:24px">
            <div>
                <h1 style="font-size:28px;font-weight:900;margin:0"><?= $e($pageTitle) ?></h1>
                <p style="color:var(--wk-muted);font-size:14px;margin:4px 0 0"><?= $totalProducts ?> product<?= $totalProducts !== 1 ? 's' : '' ?></p>
            </div>

            <!-- Sort Dropdown -->
            <div style="position:relative">
                <select onchange="window.location=this.value" style="appearance:none;background:var(--wk-card);border:2px solid var(--wk-border);border-radius:10px;padding:10px 36px 10px 14px;font-size:13px;font-weight:700;color:var(--wk-text);cursor:pointer;min-width:180px">
                    <?php
                    $sortOptions = ['newest'=>'Newest First','oldest'=>'Oldest First','price_low'=>'Price: Low to High','price_high'=>'Price: High to Low','name_az'=>'Name: A-Z','name_za'=>'Name: Z-A'];
                    foreach ($sortOptions as $val => $label):
                    ?>
                    <option value="<?= $buildUrl(['sort' => $val, 'page' => 1]) ?>" <?= $sort === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <span style="position:absolute;right:12px;top:50%;transform:translateY(-50%);pointer-events:none;font-size:10px;color:var(--wk-muted)">▼</span>
            </div>
        </div>

        <!-- Category Filter Pills -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:24px;padding-bottom:24px;border-bottom:2px solid var(--wk-border)">
            <a href="<?= $url('shop') ?>" style="display:inline-block;padding:8px 18px;border-radius:99px;font-size:13px;font-weight:700;text-decoration:none;transition:all .2s;<?= empty($currentCategory) ? 'background:var(--wk-purple);color:#fff' : 'background:var(--wk-card);color:var(--wk-text);border:2px solid var(--wk-border)' ?>">All Products</a>
            <?php foreach ($categories as $cat): ?>
            <a href="<?= $buildUrl(['category' => $cat['slug'], 'page' => 1, 'sort' => $sort]) ?>"
               style="display:inline-block;padding:8px 18px;border-radius:99px;font-size:13px;font-weight:700;text-decoration:none;transition:all .2s;<?= (!empty($currentCategory) && $currentCategory['slug'] === $cat['slug']) ? 'background:var(--wk-purple);color:#fff' : 'background:var(--wk-card);color:var(--wk-text);border:2px solid var(--wk-border)' ?>">
                <?= $e($cat['name']) ?>
                <?php if (($cat['product_count'] ?? 0) > 0): ?>
                <span style="font-size:11px;opacity:.6">(<?= $cat['product_count'] ?>)</span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Product Grid -->
<section class="wk-section" style="padding-top:0">
    <div class="wk-container">
        <?php if (empty($products)): ?>
            <div style="text-align:center;padding:60px 0;color:var(--wk-muted)">
                <div style="font-size:48px;margin-bottom:12px;opacity:.3">📦</div>
                <p style="font-weight:800;margin-bottom:4px">No products found</p>
                <p style="font-size:14px">Try a different category or <a href="<?= $url('shop') ?>" style="color:var(--wk-purple);font-weight:700">browse all products</a></p>
            </div>
        <?php else: ?>
            <div class="wk-product-grid">
                <?php foreach ($products as $p):
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

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:40px;flex-wrap:wrap">
                <?php if ($page > 1): ?>
                <a href="<?= $buildUrl(['page' => $page - 1]) ?>" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;background:var(--wk-card);border:2px solid var(--wk-border);text-decoration:none;color:var(--wk-text);font-weight:700;font-size:14px">←</a>
                <?php endif; ?>

                <?php
                // Show page numbers with ellipsis
                $range = 2;
                $start = max(1, $page - $range);
                $end = min($totalPages, $page + $range);

                if ($start > 1):
                ?>
                <a href="<?= $buildUrl(['page' => 1]) ?>" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;background:var(--wk-card);border:2px solid var(--wk-border);text-decoration:none;color:var(--wk-text);font-weight:700;font-size:14px">1</a>
                <?php if ($start > 2): ?><span style="color:var(--wk-muted);padding:0 4px">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                <a href="<?= $buildUrl(['page' => $i]) ?>" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;text-decoration:none;font-weight:700;font-size:14px;<?= $i === $page ? 'background:var(--wk-purple);color:#fff;border:2px solid var(--wk-purple)' : 'background:var(--wk-card);color:var(--wk-text);border:2px solid var(--wk-border)' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><span style="color:var(--wk-muted);padding:0 4px">...</span><?php endif; ?>
                <a href="<?= $buildUrl(['page' => $totalPages]) ?>" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;background:var(--wk-card);border:2px solid var(--wk-border);text-decoration:none;color:var(--wk-text);font-weight:700;font-size:14px"><?= $totalPages ?></a>
                <?php endif; ?>

                <?php if ($page < $totalPages): ?>
                <a href="<?= $buildUrl(['page' => $page + 1]) ?>" style="display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:10px;background:var(--wk-card);border:2px solid var(--wk-border);text-decoration:none;color:var(--wk-text);font-weight:700;font-size:14px">→</a>
                <?php endif; ?>
            </nav>
            <p style="text-align:center;margin-top:12px;font-size:13px;color:var(--wk-muted)">
                Page <?= $page ?> of <?= $totalPages ?>
            </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
