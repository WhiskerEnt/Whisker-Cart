<?php
$seoData = $seoData ?? [];
$mt = htmlspecialchars($seoData['meta_title'] ?? '');
$md = htmlspecialchars($seoData['meta_description'] ?? '');
$mk = htmlspecialchars($seoData['meta_keywords'] ?? '');
$oi = htmlspecialchars($seoData['og_image'] ?? '');
$slug = $seoPreviewSlug ?? 'product/example';
$collapsed = empty($mt) && empty($md);

$_seoSiteName = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='seo' AND setting_key='site_meta_title'")
    ?: (\Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store');
$_seoSep = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='seo' AND setting_key='title_separator'") ?: ' — ';
$_seoFormat = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='seo' AND setting_key='title_format'") ?: '{page} {sep} {site}';
$_seoBaseUrl = defined('WK_BASE_URL') ? rtrim(WK_BASE_URL, '/') : '';
$_faviconUrl = $_seoBaseUrl . '/assets/img/favicon.svg';

// Get primary image if editing existing product
$_seoPrimaryImage = '';
if (!empty($seoData['_product_id'])) {
    $imgPath = \Core\Database::fetchValue("SELECT image_path FROM wk_product_images WHERE product_id=? AND is_primary=1 LIMIT 1", [$seoData['_product_id']]);
    if ($imgPath) $_seoPrimaryImage = $_seoBaseUrl . '/storage/uploads/products/' . $imgPath;
}
?>
<div class="wk-mb-20 wk-card">
    <div class="wk-card-header" style="cursor:pointer" onclick="document.getElementById('seoBody').style.display=document.getElementById('seoBody').style.display==='none'?'block':'none'">
        <h2><?= $icon('search') ?> SEO Settings</h2>
        <span class="wk-fine"><?=$collapsed?'▸ Click to expand':'▾ Expanded'?></span>
    </div>
    <div class="wk-card-body" id="seoBody" style="display:<?=$collapsed?'none':'block'?>">

        <!-- Google Preview -->
        <div style="background:#fff;border:1px solid #dfe1e5;border-radius:12px;padding:20px;margin-bottom:18px;font-family:Arial,sans-serif">
            <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:#70757a;margin-bottom:12px">Google Search Preview</div>
            <div style="display:flex;gap:16px">
                <div style="flex:1;min-width:0">
                    <!-- Site name + breadcrumb (how Google actually shows it) -->
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                        <img src="<?= htmlspecialchars($_faviconUrl) ?>" style="width:28px;height:28px;border-radius:50%;border:1px solid #eee" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" alt="">
                        <div style="display:none;width:28px;height:28px;border-radius:50%;background:#f1f3f4;align-items:center;justify-content:center;font-size:14px;flex-shrink:0"><?= $icon('globe', 14) ?></div>
                        <div style="min-width:0">
                            <div style="font-size:14px;color:#202124;line-height:1.3" id="seoPreviewSiteName"><?= htmlspecialchars($_seoSiteName) ?></div>
                            <div style="font-size:12px;color:#4d5156" id="seoPreviewBreadcrumb"><?= htmlspecialchars($_seoBaseUrl) ?> › <?= htmlspecialchars(str_replace('/', ' › ', $slug)) ?></div>
                        </div>
                    </div>
                    <!-- Title -->
                    <div id="seoPreviewTitle" style="font-size:20px;color:#1a0dab;line-height:1.3;margin-bottom:6px;cursor:pointer;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical"><?= $mt ?: htmlspecialchars(str_replace(['{page}','{sep}','{site}'], ['Product Name', $_seoSep, $_seoSiteName], $_seoFormat)) ?></div>
                    <!-- Description -->
                    <div id="seoPreviewDesc" style="font-size:14px;color:#4d5156;line-height:1.58;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden"><?= $md ?: 'Type a product name and description — this preview updates in real time.' ?></div>
                </div>
                <!-- Product image thumbnail -->
                <div id="seoPreviewImgWrap" style="flex-shrink:0;width:92px;height:92px;border-radius:8px;overflow:hidden;background:#f8f9fa;border:1px solid #eee;display:<?= $_seoPrimaryImage ? 'block' : 'none' ?>;align-self:center">
                    <img id="seoPreviewImg" src="<?= htmlspecialchars($_seoPrimaryImage) ?>" class="wk-cover" alt="">
                </div>
            </div>
        </div>

        <div class="wk-form-group">
            <label>Meta Title <span style="font-weight:500;text-transform:none;letter-spacing:0;font-size:11px;color:var(--wk-muted)">(leave empty = auto from product name)</span></label>
            <input type="text" name="meta_title" class="wk-input" id="seoMetaTitle" value="<?=$mt?>" placeholder="Auto-generates from product name" maxlength="70">
            <div class="wk-form-hint"><span id="seoTitleCt" class="wk-bold"><?=mb_strlen($seoData['meta_title']??'')?></span>/60 · <?php if(empty($seoData['meta_title'])):?><span class="wk-text-green">✓ Auto</span><?php else:?><span style="color:var(--wk-purple)">Custom</span><?php endif;?></div>
        </div>

        <div class="wk-form-group">
            <label>Meta Description <span style="font-weight:500;text-transform:none;letter-spacing:0;font-size:11px;color:var(--wk-muted)">(leave empty = auto from description)</span></label>
            <textarea name="meta_description" class="wk-textarea" id="seoMetaDesc" rows="3" maxlength="160" placeholder="Auto-generates from product description..."><?=$md?></textarea>
            <div class="wk-form-hint"><span id="seoDescCt" class="wk-bold"><?=mb_strlen($seoData['meta_description']??'')?></span>/155 · <?php if(empty($seoData['meta_description'])):?><span class="wk-text-green">✓ Auto</span><?php else:?><span style="color:var(--wk-purple)">Custom</span><?php endif;?></div>
        </div>

        <div class="wk-form-group"><label>Meta Keywords</label><input type="text" name="meta_keywords" class="wk-input" value="<?=$mk?>" placeholder="Auto-generates from content"></div>
        <div class="wk-form-group"><label>Social Share Image (OG)</label><input type="text" name="og_image" class="wk-input" value="<?=$oi?>" placeholder="Leave empty = primary product image"></div>
    </div>
</div>

<script>
(function(){
    const siteName = <?= json_encode($_seoSiteName) ?>;
    const sep = <?= json_encode($_seoSep) ?>;
    const fmt = <?= json_encode($_seoFormat) ?>;

    const seoTitle = document.getElementById('seoMetaTitle');
    const seoDesc = document.getElementById('seoMetaDesc');
    const previewTitle = document.getElementById('seoPreviewTitle');
    const previewDesc = document.getElementById('seoPreviewDesc');
    const previewImg = document.getElementById('seoPreviewImg');
    const previewImgWrap = document.getElementById('seoPreviewImgWrap');
    const titleCt = document.getElementById('seoTitleCt');
    const descCt = document.getElementById('seoDescCt');

    const prodName = document.querySelector('input[name="name"]');
    const prodDesc = document.querySelector('textarea[name="description"]');
    const prodShort = document.querySelector('input[name="short_description"]');

    function buildTitle(page) {
        if (!page) return siteName;
        return fmt.replace('{page}', page).replace('{sep}', sep).replace('{site}', siteName);
    }

    function truncate(text, max) {
        text = text.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
        if (text.length <= max) return text;
        var t = text.substring(0, max);
        var last = t.lastIndexOf(' ');
        if (last > max * 0.7) t = t.substring(0, last);
        return t.replace(/[.,;:!?\s]+$/, '') + '...';
    }

    function update() {
        var title = (seoTitle && seoTitle.value.trim()) || (prodName && prodName.value.trim()) || '';
        if (previewTitle) previewTitle.textContent = title ? buildTitle(title) : siteName;
        if (titleCt) titleCt.textContent = seoTitle ? seoTitle.value.length : 0;

        var desc = '';
        if (seoDesc && seoDesc.value.trim()) desc = seoDesc.value.trim();
        else if (prodShort && prodShort.value.trim()) desc = truncate(prodShort.value.trim(), 155);
        else if (prodDesc && prodDesc.value.trim()) desc = truncate(prodDesc.value.trim(), 155);
        if (previewDesc) {
            previewDesc.textContent = desc || 'Type a product name and description — this preview updates in real time.';
            previewDesc.style.color = desc ? '#4d5156' : '#9aa0a6';
        }
        if (descCt) descCt.textContent = seoDesc ? seoDesc.value.length : 0;
    }

    function updateImage() {
        var primaryImg = document.querySelector('#imageGallery img');
        if (primaryImg && previewImg && previewImgWrap) {
            previewImg.src = primaryImg.src;
            previewImgWrap.style.display = 'block';
        }
    }

    [seoTitle, seoDesc, prodName, prodDesc, prodShort].forEach(function(el) {
        if (el) { el.addEventListener('input', update); el.addEventListener('change', update); }
    });

    update();
    updateImage();

    var gallery = document.getElementById('imageGallery');
    if (gallery) {
        new MutationObserver(updateImage).observe(gallery, { childList: true, subtree: true });
    }
})();
</script>