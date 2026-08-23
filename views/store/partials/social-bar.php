<?php
/**
 * Floating contact bar.
 *
 * Pinned to the side of the viewport and stays put while the page scrolls.
 * The wrapper does not take pointer events — only the links do — so the gaps
 * between icons never swallow a click meant for the page behind it.
 *
 * Two modes, chosen in the admin: every channel on show, or a single button
 * that opens them. Collapsed suits a shop with a long list, or a busy layout
 * where a column of circles is one thing too many.
 */
use App\Services\SocialService;

$links = SocialService::links();
if (!$links) return;

$e = fn($v) => \Core\View::e((string) $v);
$position  = SocialService::position();
$collapsed = SocialService::collapsed();
?>
<div class="wk-social wk-social-<?= $e($position) ?><?= $collapsed ? ' wk-social-collapsible' : '' ?>"
     id="wkSocialBar" data-side="<?= $e($position) ?>" data-collapsed="<?= $collapsed ? '1' : '0' ?>">

    <?php if ($collapsed): ?>
        <button type="button" class="wk-social-toggle" id="wkSocialToggle"
                aria-expanded="false" aria-controls="wkSocialLinks" aria-label="Contact us">
            <svg class="wk-social-toggle-open" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M12 8a2 2 0 1 0 0-4 2 2 0 0 0 0 4m0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4m0 6a2 2 0 1 0 0-4 2 2 0 0 0 0 4"/>
            </svg>
            <svg class="wk-social-toggle-close" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M19 6.41 17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
            </svg>
        </button>
    <?php endif; ?>

    <nav class="wk-social-inner" id="wkSocialLinks" aria-label="Contact us" <?= $collapsed ? 'hidden' : '' ?>>
        <?php foreach ($links as $i => $l): ?>
            <a class="wk-social-link"
               href="<?= \Core\View::safeUrl($l['url']) ?>"
               style="--wk-social-brand: <?= $e($l['color']) ?>; --wk-social-i: <?= (int) $i ?>"
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
    if (bar.dataset.collapsed === '1') document.body.classList.add('wk-has-social-collapsed');

    var toggle = document.getElementById('wkSocialToggle');
    var panel  = document.getElementById('wkSocialLinks');

    if (toggle && panel) {
        var setOpen = function (state) {
            toggle.setAttribute('aria-expanded', state ? 'true' : 'false');
            bar.classList.toggle('is-open', state);
            if (state) {
                panel.hidden = false;
            } else {
                // Let the icons travel back before they leave the layout.
                setTimeout(function () {
                    if (!bar.classList.contains('is-open')) panel.hidden = true;
                }, 240);
            }
        };

        toggle.addEventListener('click', function () {
            setOpen(toggle.getAttribute('aria-expanded') !== 'true');
        });

        // Opening it should not mean living with it.
        document.addEventListener('click', function (e) {
            if (!bar.contains(e.target)) setOpen(false);
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') setOpen(false);
        });
    }

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
