<?php
$url=fn($p)=>\Core\View::url($p);
$s=$settings;
$v=fn($k,$d='')=>htmlspecialchars($s[$k]??$d);
$baseUrl = defined('WK_BASE_URL') ? rtrim(WK_BASE_URL, '/') : 'https://yourstore.com';
$currentSiteName = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store';
?>

<div class="wk-mb-20 wk-stats">
    <div class="wk-stat-card"><div class="wk-stat-label">Products with Custom SEO</div><div class="wk-stat-value"><?= $customMetaProducts ?>/<?= $totalProducts ?></div><div class="wk-stat-sub"><?= $totalProducts>0?round(($customMetaProducts/$totalProducts)*100):0 ?>% coverage</div></div>
    <div class="wk-stat-card"><div class="wk-stat-label">Categories with Custom SEO</div><div class="wk-stat-value"><?= $customMetaCategories ?>/<?= $totalCategories ?></div><div class="wk-stat-sub"><?= $totalCategories>0?round(($customMetaCategories/$totalCategories)*100):0 ?>% coverage</div></div>
    <div class="wk-stat-card"><div class="wk-stat-label">Sitemap</div><div class="wk-stat-value" style="font-size:14px"><?= $sitemapExists?'<span class="wk-text-green">✓ Active</span>':'<span style="color:var(--wk-yellow)">Not generated</span>' ?></div><?php if($sitemapDate):?><div class="wk-stat-sub"><?=$sitemapDate?></div><?php endif;?></div>
    <div class="wk-stat-card"><div class="wk-stat-label">robots.txt</div><div class="wk-stat-value" style="font-size:14px"><?= $robotsExists?'<span class="wk-text-green">✓ Active</span>':'<span style="color:var(--wk-yellow)">Not generated</span>' ?></div></div>
</div>

<form method="POST" action="<?= $url('admin/seo/update') ?>">
    <?= \Core\Session::csrfField() ?>

    <div class="wk-mb-20 wk-card">
        <div class="wk-card-header"><h2><?= $icon('search') ?> Site-Wide Meta Tags</h2></div>
        <div class="wk-card-body">
            <div class="wk-form-group"><label>Site Meta Title</label><input type="text" name="site_meta_title" class="wk-input" id="seoSiteTitle" value="<?=$v('site_meta_title')?>" placeholder="<?= htmlspecialchars($currentSiteName) ?>" maxlength="70"><div class="wk-form-hint">Homepage title in search results. Leave empty to use store name.</div></div>
            <div class="wk-form-group"><label>Site Meta Description</label><textarea name="site_meta_description" class="wk-textarea" rows="3" maxlength="160" id="seoSiteDesc" placeholder="A compelling description of your store..."><?=$v('site_meta_description')?></textarea><div class="wk-form-hint"><span id="descCount"><?=mb_strlen($s['site_meta_description']??'')?></span>/160 characters</div></div>
            <div class="wk-form-group"><label>Site Meta Keywords</label><input type="text" name="site_meta_keywords" class="wk-input" value="<?=$v('site_meta_keywords')?>" placeholder="ecommerce, online store, products"></div>
            <div class="wk-form-group"><label>Default Social Share Image</label><input type="text" name="og_image" class="wk-input" value="<?=$v('og_image')?>" placeholder="uploads/og-image.jpg or full URL"><div class="wk-form-hint">Recommended: 1200×630px. Shown when shared on social media.</div></div>
        </div>
    </div>

    <div class="wk-mb-20 wk-card">
        <div class="wk-card-header"><h2><?= $icon('notebook-pen') ?> Title Format</h2></div>
        <div class="wk-card-body">
            <div class="wk-grid-2">
                <div class="wk-form-group"><label>Title Format</label><select name="title_format" class="wk-select" id="seoFormat">
                    <option value="{page} {sep} {site}" <?=($s['title_format']??'')=='{page} {sep} {site}'?'selected':''?>>Page — Site Name</option>
                    <option value="{site} {sep} {page}" <?=($s['title_format']??'')=='{site} {sep} {page}'?'selected':''?>>Site Name — Page</option>
                    <option value="{page}" <?=($s['title_format']??'')=='{page}'?'selected':''?>>Page Title Only</option>
                </select></div>
                <div class="wk-form-group"><label>Separator</label><select name="title_separator" class="wk-select" id="seoSep">
                    <?php foreach([' — '=>'— (em dash)',' | '=>'| (pipe)',' - '=>'- (hyphen)',' · '=>'· (dot)',' » '=>'» (chevron)'] as $val=>$lbl):?>
                    <option value="<?=$val?>" <?=($s['title_separator']??'')===$val?'selected':''?>><?=$lbl?></option>
                    <?php endforeach;?>
                </select></div>
            </div>

            <!-- Proper Google Preview -->
            <div style="background:#fff;border:1px solid #dadce0;border-radius:10px;padding:16px 18px;margin-top:12px">
                <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#70757a;margin-bottom:8px"><?= $icon('eye', 12) ?> Google Search Preview — Homepage</div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                    <div style="width:26px;height:26px;border-radius:50%;background:#f1f3f4;display:flex;align-items:center;justify-content:center;font-size:12px"><?= $icon('globe', 12) ?></div>
                    <div>
                        <div style="font-size:12px;color:#202124"><?= htmlspecialchars($baseUrl) ?></div>
                        <div style="font-size:12px;color:#4d5156"><?= htmlspecialchars($baseUrl) ?></div>
                    </div>
                </div>
                <div id="prevTitle" style="font-size:20px;color:#1a0dab;font-weight:400;line-height:1.3;margin-bottom:4px"><?= $v('site_meta_title', $currentSiteName) ?></div>
                <div id="prevDesc" style="font-size:14px;color:#4d5156;line-height:1.58"><?= $v('site_meta_description', 'Add a meta description to see how your store appears in search results.') ?></div>
            </div>

            <!-- Product page example preview -->
            <div style="background:#fff;border:1px solid #dadce0;border-radius:10px;padding:16px 18px;margin-top:10px">
                <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:1px;color:#70757a;margin-bottom:8px"><?= $icon('eye', 12) ?> Google Search Preview — Product Page Example</div>
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                    <div style="width:26px;height:26px;border-radius:50%;background:#f1f3f4;display:flex;align-items:center;justify-content:center;font-size:12px"><?= $icon('globe', 12) ?></div>
                    <div style="font-size:12px;color:#4d5156"><?= htmlspecialchars($baseUrl) ?>/product/example-product</div>
                </div>
                <div id="prevProductTitle" style="font-size:20px;color:#1a0dab;font-weight:400;line-height:1.3;margin-bottom:4px">Example Product<?= htmlspecialchars(($s['title_separator'] ?? ' — ') . ($s['site_meta_title'] ?? $currentSiteName)) ?></div>
                <div style="font-size:14px;color:#4d5156;line-height:1.58">This is how a product page will look in Google. The title format and separator you choose above will be applied to all pages.</div>
            </div>
        </div>
    </div>

    <div class="wk-mb-20 wk-card">
        <div class="wk-card-header"><h2><?= $icon('globe') ?> Social & Verification</h2></div>
        <div class="wk-card-body">
            <div class="wk-form-group"><label>Twitter / X Handle</label><input type="text" name="twitter_handle" class="wk-input" value="<?=$v('twitter_handle')?>" placeholder="@yourstore"></div>
            <div class="wk-grid-2">
                <div class="wk-form-group"><label>Google Verification</label><input type="text" name="google_verification" class="wk-input" value="<?=$v('google_verification')?>" placeholder="Code from Google Search Console"></div>
                <div class="wk-form-group"><label>Bing Verification</label><input type="text" name="bing_verification" class="wk-input" value="<?=$v('bing_verification')?>" placeholder="Code from Bing Webmaster"></div>
            </div>
        </div>
    </div>

    <div class="wk-mb-20 wk-card">
        <div class="wk-card-header"><h2><?= $icon('bot') ?> Crawling & Indexing</h2></div>
        <div class="wk-card-body" style="display:flex;flex-direction:column;gap:16px">
            <?php
            $toggles = [
                ['robots_index','Allow Search Engine Indexing','Let search engines index your store pages'],
                ['robots_follow','Allow Link Following','Let search engines follow links on your pages'],
                ['auto_generate_meta','Auto-Generate Meta Tags','Auto-create meta titles & descriptions from product/category content'],
                ['schema_org_enabled','Schema.org Structured Data','Add JSON-LD product schema for rich snippets in search results'],
                ['sitemap_enabled','Sitemap Generation','Enable sitemap.xml generation'],
            ];
            foreach ($toggles as [$name,$title,$hint]):
            ?>
            <label style="display:flex;align-items:center;justify-content:space-between;cursor:pointer">
                <div><strong><?=$title?></strong><div class="wk-form-hint" style="margin-top:2px"><?=$hint?></div></div>
                <input type="checkbox" name="<?=$name?>" value="1" <?=($s[$name]??'1')==='1'?'checked':''?> style="width:20px;height:20px;accent-color:var(--wk-purple)">
            </label>
            <?php endforeach;?>
        </div>
    </div>

    <button type="submit" class="wk-mb-20 wk-btn wk-btn-primary"><?= $icon('save') ?> Save SEO Settings</button>
</form>

<div class="wk-mb-20 wk-card">
    <div class="wk-card-header"><h2><?= $icon('map') ?> Sitemap & robots.txt</h2></div>
    <div class="wk-card-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div style="background:var(--wk-bg);border:1px solid var(--wk-border);border-radius:8px;padding:16px">
                <h3 style="font-size:14px;margin-bottom:6px">sitemap.xml</h3>
                <p style="font-size:12px;color:var(--wk-text-muted);margin-bottom:12px">All active products, categories, and pages for search engines.</p>
                <?php if($sitemapDate):?><p style="font-size:11px;color:var(--wk-green);margin-bottom:8px">✓ Last: <?=$sitemapDate?></p><?php endif;?>
                <form method="POST" action="<?=$url('admin/seo/generate-sitemap')?>"><?=\Core\Session::csrfField()?><button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm"><?= $sitemapExists ? $icon('refresh-cw', 14) . ' Regenerate' : $icon('map', 14) . ' Generate' ?> Sitemap</button></form>
            </div>
            <div style="background:var(--wk-bg);border:1px solid var(--wk-border);border-radius:8px;padding:16px">
                <h3 style="font-size:14px;margin-bottom:6px">robots.txt</h3>
                <p style="font-size:12px;color:var(--wk-text-muted);margin-bottom:12px">Blocks admin, API, checkout. Links to sitemap.</p>
                <?php if($robotsExists):?><p style="font-size:11px;color:var(--wk-green);margin-bottom:8px">✓ File exists</p><?php endif;?>
                <form method="POST" action="<?=$url('admin/seo/generate-robots')?>"><?=\Core\Session::csrfField()?><button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm"><?= $robotsExists ? $icon('refresh-cw', 14) . ' Regenerate' : $icon('bot', 14) . ' Generate' ?> robots.txt</button></form>
            </div>
        </div>
    </div>
</div>

<div style="text-align:center;padding:24px;background:linear-gradient(135deg,#8b5cf6,#ec4899);border-radius:12px;color:#fff;margin-bottom:20px">
    <h3 style="margin:0 0 6px">Need Custom SEO Features?</h3>
    <p style="margin:0 0 12px;opacity:.9;font-size:13px">Advanced analytics, Google Merchant Center, structured data for reviews, and more.</p>
    <a href="mailto:mail@lohit.me" style="display:inline-block;background:#fff;color:#8b5cf6;padding:8px 24px;border-radius:8px;font-weight:800;text-decoration:none;font-size:13px"><?= $icon('mail', 14) ?> mail@lohit.me</a>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('seoSiteTitle');
    const descInput = document.getElementById('seoSiteDesc');
    const fmtSelect = document.getElementById('seoFormat');
    const sepSelect = document.getElementById('seoSep');
    const prevTitle = document.getElementById('prevTitle');
    const prevDesc = document.getElementById('prevDesc');
    const prevProduct = document.getElementById('prevProductTitle');
    const descCount = document.getElementById('descCount');
    const fallbackName = <?= json_encode($currentSiteName) ?>;

    function update() {
        const siteName = (titleInput && titleInput.value.trim()) || fallbackName;
        const desc = descInput ? descInput.value.trim() : '';
        const fmt = fmtSelect ? fmtSelect.value : '{page} {sep} {site}';
        const sep = sepSelect ? sepSelect.value : ' — ';

        // Homepage preview
        if (prevTitle) prevTitle.textContent = siteName;
        if (prevDesc) {
            prevDesc.textContent = desc || 'Add a meta description to see how your store appears in search results.';
            prevDesc.style.color = desc ? '#4d5156' : '#9aa0a6';
        }
        if (descCount) descCount.textContent = desc.length;

        // Product page example
        if (prevProduct) {
            const productTitle = fmt.replace('{page}', 'Example Product').replace('{sep}', sep).replace('{site}', siteName);
            prevProduct.textContent = productTitle;
        }
    }

    [titleInput, descInput, fmtSelect, sepSelect].forEach(function(el) {
        if (el) {
            el.addEventListener('input', update);
            el.addEventListener('change', update);
        }
    });

    update();
});
</script>