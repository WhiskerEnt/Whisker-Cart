<?php
$e = fn($v) => \Core\View::e($v);
$url = fn($p) => \Core\View::url($p);
$p = $product;
$prc = $p['sale_price'] ?: $p['price'];

$baseCurrency = \App\Services\CurrencyService::baseCurrency();
$displayCurrency = $_SESSION['wk_display_currency'] ?? $baseCurrency;
$baseSymbol = \App\Services\CurrencyService::baseSymbol();

// Show prices in the visitor's chosen currency only (no base-currency clutter).
$showPrice = fn($amount) => \App\Services\CurrencyService::displayPrice((float) $amount);

$hasVariants = !empty($variants['combos']);

// Build variant JS data
$variantData = [];
foreach ($variants['combos'] ?? [] as $combo) {
    // Variant images are stored against the option, tagged
    // 'variant_opt_<optionId>' by the admin uploader.
    $firstOptionId = (int) strtok((string)($combo['option_ids'] ?? ''), ',');
    $comboImages = $firstOptionId > 0
        ? \Core\Database::fetchAll(
            "SELECT image_path FROM wk_product_images WHERE product_id=? AND alt_text=? ORDER BY sort_order",
            [$p['id'], 'variant_opt_' . $firstOptionId]
          )
        : [];
    $comboPrice = $combo['price_override'] ?? $p['price'];
    $variantData[] = [
        'id' => $combo['id'],
        'label' => $combo['label'],
        'option_ids' => $combo['option_ids'],
        'price' => $comboPrice,
        // Pre-formatted in the visitor's display currency.
        'price_formatted' => \App\Services\CurrencyService::displayPrice((float) $comboPrice),
        'stock' => $combo['stock_quantity'],
        'sku' => $combo['sku'] ?? $p['sku'],
        'images' => array_map(fn($img) => $url('storage/uploads/products/' . $img['image_path']), $comboImages),
    ];
}
?>
<section class="wk-section">
    <div class="wk-container">
        <a href="<?= $url('') ?>" style="color:var(--wk-purple);font-weight:700;font-size:13px;margin-bottom:20px;display:inline-block">← Back to Shop</a>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:40px;align-items:start">

            <!-- Images -->
            <div>
                <div id="mainImage" style="background:var(--wk-bg);border-radius:var(--radius);overflow:hidden;aspect-ratio:1;display:flex;align-items:center;justify-content:center;margin-bottom:12px;border:2px solid var(--wk-border)">
                    <?php if (!empty($images)): ?>
                        <img src="<?= $url('storage/uploads/products/'.$images[0]['image_path']) ?>" alt="<?= $e($p['name']) ?>" style="width:100%;height:100%;object-fit:cover" id="mainImg">
                    <?php else: ?>
                        <span style="font-size:80px;opacity:.15">📦</span>
                    <?php endif; ?>
                </div>
                <div id="thumbGallery" style="display:flex;gap:8px;flex-wrap:wrap">
                    <?php foreach ($images as $i => $img): ?>
                    <div onclick="setMainImage('<?= $url('storage/uploads/products/'.$img['image_path']) ?>',this)"
                         class="wk-thumb"
                         style="width:64px;height:64px;border-radius:8px;overflow:hidden;cursor:pointer;border:2px solid <?= $i===0?'var(--wk-purple)':'var(--wk-border)' ?>;transition:border-color .2s">
                        <img src="<?= $url('storage/uploads/products/'.$img['image_path']) ?>" style="width:100%;height:100%;object-fit:cover" alt="">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Product Info -->
            <div>
                <?php if ($p['category_name']): ?>
                    <div class="wk-product-cat" style="margin-bottom:8px"><?= $e($p['category_name']) ?></div>
                <?php endif; ?>

                <h1 style="font-size:28px;font-weight:900;margin-bottom:12px;line-height:1.2"><?= $e($p['name']) ?></h1>

                <?php if (!empty($reviewsOn) && ($reviewStats['count'] ?? 0) > 0):
                    $rAvg = (float) $reviewStats['average'];
                    $rCount = (int) $reviewStats['count']; ?>
                    <a href="#reviews" class="wk-rating-line">
                        <span class="wk-stars" style="font-size:15px" aria-hidden="true"><?php
                        for ($i = 1; $i <= 5; $i++) {
                            $pct = max(0, min(1, $rAvg - ($i - 1))) * 100;
                            echo '<span class="wk-star"><span class="wk-star-off">&#9733;</span>'
                               . '<span class="wk-star-on" style="width:' . round($pct, 1) . '%">&#9733;</span></span>';
                        } ?></span>
                        <span class="wk-rating-line-text"><?= number_format($rAvg, 1) ?> &middot; <?= $rCount ?> review<?= $rCount === 1 ? '' : 's' ?></span>
                    </a>
                <?php endif; ?>

                <div id="priceDisplay" class="wk-product-price" style="margin-bottom:20px">
                    <span class="current" style="font-size:28px"><?= $showPrice($prc) ?></span>
                    <?php if ($p['sale_price'] && $p['sale_price'] < $p['price']): ?>
                        <br><span class="original" style="font-size:16px"><?= $showPrice($p['price']) ?></span>
                        <span style="background:#d1fae5;color:#10b981;font-size:12px;font-weight:800;padding:2px 8px;border-radius:10px;margin-left:6px">
                            <?= round((1 - $p['sale_price'] / $p['price']) * 100) ?>% OFF
                        </span>
                    <?php endif; ?>
                </div>

                <?php if ($p['short_description']): ?>
                    <p style="color:var(--wk-muted);margin-bottom:20px;line-height:1.7;font-size:15px"><?= $e($p['short_description']) ?></p>
                <?php endif; ?>

                <!-- Variant Selectors -->
                <?php if ($hasVariants): ?>
                <div id="variantSelector" style="margin-bottom:20px">
                    <?php foreach ($variants['groups'] as $group): ?>
                    <div style="margin-bottom:14px">
                        <label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-muted);margin-bottom:8px"><?= $e($group['name']) ?></label>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <?php foreach ($group['options'] as $opt): ?>
                            <button type="button"
                                class="variant-opt" data-group="<?= $group['id'] ?>" data-option="<?= $opt['id'] ?>"
                                onclick="selectVariantOption(this)"
                                style="padding:8px 18px;border:2px solid var(--wk-border);border-radius:8px;background:var(--wk-surface);font-family:var(--font);font-size:13px;font-weight:700;cursor:pointer;transition:all .2s;color:var(--wk-text)">
                                <?php if ($opt['color_hex']): ?>
                                    <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:<?= $e($opt['color_hex']) ?>;border:1px solid rgba(0,0,0,.1);vertical-align:middle;margin-right:4px"></span>
                                <?php endif; ?>
                                <?= $e($opt['value']) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div id="variantMessage" style="font-size:13px;font-weight:700;margin-bottom:8px;min-height:20px"></div>
                </div>
                <?php endif; ?>

                <!-- Stock Status -->
                <div id="stockDisplay" style="display:flex;align-items:center;gap:8px;margin-bottom:20px;font-size:14px;font-weight:700">
                    <?php $totalStock = $hasVariants ? array_sum(array_column($variants['combos'], 'stock_quantity')) : $p['stock_quantity']; ?>
                    <?php if ($totalStock > 0): ?>
                        <span style="width:8px;height:8px;border-radius:50%;background:#10b981"></span>
                        <span style="color:#10b981">In Stock</span>
                    <?php else: ?>
                        <span style="width:8px;height:8px;border-radius:50%;background:#ef4444"></span>
                        <span style="color:#ef4444">Out of Stock</span>
                    <?php endif; ?>
                    <!-- Filled in with the selected variant's availability. -->
                    <span id="stockCount" style="color:var(--wk-muted);font-weight:500"></span>
                </div>

                <!-- Quantity + Add to Cart -->
                <?php if ($totalStock > 0): ?>
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px">
                    <div class="wk-qty-ctrl" style="border-width:2px">
                        <button type="button" class="wk-qty-btn" style="width:40px;height:36px;font-size:18px" onclick="let i=document.getElementById('product-qty');i.value=Math.max(1,parseInt(i.value)-1)">−</button>
                        <input type="number" id="product-qty" class="wk-qty-val" value="1" min="1" max="<?= $totalStock ?>" style="width:48px;height:36px;font-size:15px">
                        <button type="button" class="wk-qty-btn" style="width:40px;height:36px;font-size:18px" onclick="let i=document.getElementById('product-qty');i.value=Math.min(999,parseInt(i.value)+1)">+</button>
                    </div>
                    <button id="addToCartBtn" class="wk-add-btn" data-add-to-cart="<?= $p['id'] ?>" <?= $hasVariants ? 'disabled style="flex:1;border-radius:var(--radius-sm);font-size:15px;padding:16px;opacity:.5;cursor:not-allowed"' : 'style="flex:1;border-radius:var(--radius-sm);font-size:15px;padding:16px"' ?>>
                        <?= $hasVariants ? 'Select options above' : '🛒 Add to Cart' ?>
                    </button>
                </div>
                <?php else: ?>
                <button class="wk-add-btn" disabled style="width:100%;border-radius:var(--radius-sm);font-size:15px;padding:16px;opacity:.5;cursor:not-allowed;margin-bottom:24px">Out of Stock</button>
                <?php endif; ?>

                <div style="font-size:12px;color:var(--wk-muted);margin-bottom:24px">
                    SKU: <span style="font-family:var(--font-mono);font-weight:600" id="skuDisplay"><?= $e($p['sku']) ?></span>
                </div>

                <?php if ($p['description']): ?>
                <div style="border-top:1px solid var(--wk-border);padding-top:24px">
                    <h3 style="font-size:16px;font-weight:800;margin-bottom:12px">Description</h3>
                    <div style="color:var(--wk-muted);line-height:1.8;font-size:14px"><?= nl2br($e($p['description'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($related)): ?>
        <div style="margin-top:60px">
            <h2 style="font-size:22px;font-weight:900;margin-bottom:8px">You might also like</h2>
            <p style="color:var(--wk-muted);font-size:14px;margin-bottom:24px">More from this category</p>
            <div class="wk-product-grid">
                <?php foreach ($related as $rp): $rprc = $rp['sale_price'] ?: $rp['price']; ?>
                <div class="wk-product-card" onclick="window.location='<?= $url('product/'.$rp['slug']) ?>'">
                    <div class="wk-product-img">
                        <?php if ($rp['image']): ?><img src="<?= $url('storage/uploads/products/'.$rp['image']) ?>" alt="<?= $e($rp['name']) ?>"><?php else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:48px;opacity:.15">📦</div><?php endif; ?>
                    </div>
                    <div class="wk-product-info">
                        <div class="wk-product-name"><?= $e($rp['name']) ?></div>
                        <?= \App\Services\ReviewService::cardRatingHtml((int) $rp['id']) ?>
                        <div class="wk-product-price"><span class="current"><?= $showPrice($rprc) ?></span></div>
                    </div>
                    <button class="wk-add-btn" data-add-to-cart="<?= $rp['id'] ?>">🛒 Add to Cart</button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($reviewsOn)) require __DIR__ . '/partials/reviews.php'; ?>

        <?php if (!empty($questionsOn)) require __DIR__ . '/partials/questions.php'; ?>
    </div>
</section>

<script>
// Image gallery — used by every product, so it stays outside the
// variants-only block below.
function setMainImage(src, thumbEl) {
    const img = document.getElementById('mainImg');
    if (img) img.src = src;
    if (thumbEl) {
        document.querySelectorAll('.wk-thumb').forEach(t => t.style.borderColor = 'var(--wk-border)');
        thumbEl.style.borderColor = 'var(--wk-purple)';
    }
}
</script>

<?php if ($hasVariants): ?>
<script>
const variantCombos = <?= json_encode($variantData) ?>;
const basePrice = <?= $prc ?>;
const baseCurrencySymbol = '<?= $baseSymbol ?>';
const selectedOptions = {};
const groups = <?= json_encode(array_map(fn($g) => ['id'=>$g['id'],'name'=>$g['name']], $variants['groups'])) ?>;

function selectVariantOption(btn) {
    const groupId = btn.dataset.group;
    const optionId = btn.dataset.option;

    // Toggle selection within group
    document.querySelectorAll(`.variant-opt[data-group="${groupId}"]`).forEach(b => {
        b.style.borderColor = 'var(--wk-border)';
        b.style.background = 'var(--wk-surface)';
        b.style.color = 'var(--wk-text)';
    });
    btn.style.borderColor = 'var(--wk-purple)';
    btn.style.background = 'var(--wk-purple)';
    btn.style.color = '#fff';

    selectedOptions[groupId] = optionId;

    // Check if all groups are selected
    if (Object.keys(selectedOptions).length === groups.length) {
        findMatchingCombo();
    }
}

function findMatchingCombo() {
    const selectedIds = Object.values(selectedOptions).sort().join(',');

    const match = variantCombos.find(c => {
        const comboIds = c.option_ids.split(',').sort().join(',');
        return comboIds === selectedIds;
    });

    const msg = document.getElementById('variantMessage');
    const addBtn = document.getElementById('addToCartBtn');

    if (match) {
        // Use the server-formatted price so it stays in the visitor's currency.
        const priceEl = document.querySelector('#priceDisplay .current');
        if (priceEl) {
            priceEl.textContent = match.price_formatted
                || (baseCurrencySymbol + parseFloat(match.price).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
        }

        // Update stock
        const stockEl = document.getElementById('stockCount');
        if (stockEl) stockEl.textContent = '(' + match.stock + ' available)';

        // Update SKU
        const skuEl = document.getElementById('skuDisplay');
        if (skuEl && match.sku) skuEl.textContent = match.sku;

        // Update images if variant has its own
        if (match.images && match.images.length > 0) {
            const mainImg = document.getElementById('mainImg');
            if (mainImg) mainImg.src = match.images[0];

            // Update thumbnail gallery
            const gallery = document.getElementById('thumbGallery');
            gallery.innerHTML = match.images.map((src, i) =>
                `<div onclick="setMainImage('${src}',this)" class="wk-thumb" style="width:64px;height:64px;border-radius:8px;overflow:hidden;cursor:pointer;border:2px solid ${i===0?'var(--wk-purple)':'var(--wk-border)'};transition:border-color .2s">
                    <img src="${src}" style="width:100%;height:100%;object-fit:cover" alt="">
                </div>`
            ).join('');
        }

        // Labels are merchant-supplied, so set them via textContent.
        const inStock = match.stock > 0;
        if (addBtn) {
            addBtn.disabled = !inStock;
            addBtn.style.opacity = inStock ? '1' : '.5';
            addBtn.style.cursor = inStock ? 'pointer' : 'not-allowed';
            addBtn.textContent = (inStock ? '🛒 Add to Cart — ' : 'Out of Stock — ') + match.label;
        }
        msg.textContent = '';
        const tag = document.createElement('span');
        tag.style.color = inStock ? '#10b981' : '#ef4444';
        tag.textContent = (inStock ? '✓ ' : '✗ ') + match.label + (inStock ? ' — In Stock' : ' — Out of Stock');
        msg.appendChild(tag);

        // Store selected combo ID for cart
        if (addBtn) addBtn.dataset.variantCombo = match.id;
    } else {
        msg.innerHTML = '<span style="color:#f59e0b">This combination is not available</span>';
        if (addBtn) { addBtn.disabled = true; addBtn.style.opacity = '.5'; addBtn.style.cursor = 'not-allowed'; addBtn.innerHTML = 'Select options above'; delete addBtn.dataset.variantCombo; }
    }
}
</script>
<?php endif; ?>