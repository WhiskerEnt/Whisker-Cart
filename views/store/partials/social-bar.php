<?php
/**
 * Floating contact bar.
 *
 * Pinned to the side of the viewport and stays put while the page scrolls.
 * The wrapper does not take pointer events — only the links do — so the gaps
 * between icons never swallow a click meant for the page behind it.
 */
use App\Services\SocialService;

$links = SocialService::links();
if (!$links) return;

$e = fn($v) => \Core\View::e((string) $v);
$position = SocialService::position();
?>
<div class="wk-social wk-social-<?= $e($position) ?>" id="wkSocialBar" data-side="<?= $e($position) ?>">
    <nav class="wk-social-inner" aria-label="Contact us">
        <?php foreach ($links as $l): ?>
            <a class="wk-social-link"
               href="<?= \Core\View::safeUrl($l['url']) ?>"
               style="--wk-social-brand: <?= $e($l['color']) ?>"
               <?= str_starts_with($l['url'], 'http') ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <?= SocialService::icon($l['key']) ?>
                <span class="wk-social-label"><?= $e($l['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</div>
<script>
(function () {
    var bar = document.getElementById('wkSocialBar');
    if (!bar) return;

    // Tell the page a bar is present so the content keeps clear of it. Done
    // from here rather than in the layout so the class only appears when the
    // bar actually rendered — an enabled bar with no links renders nothing.
    document.body.classList.add('wk-has-social-' + bar.dataset.side);

    // Step aside for anything that takes over the screen — the cart drawer,
    // the chat panel — rather than floating on top of it.
    var overlays = ['.wk-cart-drawer.open', '.wk-cart-overlay.open', '#wkChatBox[style*="display: flex"]'];
    function sync() {
        var covered = overlays.some(function (sel) {
            try { return !!document.querySelector(sel); } catch (e) { return false; }
        });
        bar.classList.toggle('is-hidden', covered);
    }

    if (window.MutationObserver) {
        new MutationObserver(sync).observe(document.body, {
            subtree: true, attributes: true, attributeFilter: ['class', 'style']
        });
    }
    sync();
})();
</script>
