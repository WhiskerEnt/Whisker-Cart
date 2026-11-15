<?php
/**
 * The contact button, floating in a bottom corner of the screen.
 *
 * Two styles, chosen in the admin:
 *   dock  every channel as a tile in one glass bar, always in view
 *   menu  one "Contact us" button that opens the channels in a card
 * A phone always gets the menu: a bar of tiles is too wide for its screen and
 * would sit over the page. So does a shop with more than five channels, for
 * the same reason on any screen. The menu is in the markup either way.
 *
 * It stays in its corner as the page scrolls, folds down to its icon while
 * the shopper scrolls down, and steps out of the way of the cookie banner,
 * the cart and the chat window (see the rules in store.css).
 */
use App\Services\SocialService;

$links = SocialService::links();
if (!$links) return;

$e     = fn($v) => \Core\View::e((string) $v);
$side  = SocialService::position();
$style = (SocialService::collapsed() || count($links) > SocialService::DOCK_MAX) ? 'menu' : 'dock';
$open  = fn($url) => str_starts_with($url, 'http') ? ' target="_blank" rel="noopener noreferrer"' : '';
?>
<div class="wk-contact wk-contact-<?= $e($side) ?> wk-contact-style-<?= $style ?>" id="wkContact">
<?php if ($style === 'dock'): ?>
    <nav class="wk-contact-dock" aria-label="Contact us">
        <?php foreach ($links as $l): ?>
            <a class="wk-contact-tile wk-brand-<?= $e($l['key']) ?>" href="<?= \Core\View::safeUrl($l['url']) ?>"
               aria-label="<?= $e($l['label']) ?>"<?= $open($l['url']) ?>>
                <?= SocialService::icon($l['key']) ?>
                <span class="wk-contact-tip" aria-hidden="true"><?= $e($l['label']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
    <div class="wk-contact-card" id="wkContactMenu" hidden>
        <div class="wk-contact-card-title">Contact us</div>
        <nav aria-label="Contact us">
            <?php foreach ($links as $l): ?>
                <a class="wk-contact-row" href="<?= \Core\View::safeUrl($l['url']) ?>"<?= $open($l['url']) ?>>
                    <span class="wk-contact-tile wk-brand-<?= $e($l['key']) ?>"><?= SocialService::icon($l['key']) ?></span>
                    <span class="wk-contact-row-label"><?= $e($l['label']) ?></span>
                    <?= $icon('chevron-right', 16, 'wk-contact-row-go') ?>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
    <button type="button" class="wk-contact-button" id="wkContactButton" aria-expanded="false" aria-controls="wkContactMenu">
        <span class="wk-contact-button-icon">
            <?= $icon('message-circle', 20, 'wk-contact-i-open') ?>
            <?= $icon('x', 20, 'wk-contact-i-close') ?>
        </span>
        <span class="wk-contact-button-label">Contact us</span>
    </button>
</div>
<script>
(function () {
    var box = document.getElementById('wkContact');
    if (!box) return;

    // Folded down to its icon while the shopper scrolls down the page, back
    // to full size as soon as they scroll up: in view, but not in the way.
    // The position is only remembered once it has moved a few pixels, so a
    // slow scroll still counts as a direction.
    var lastY = window.scrollY;
    window.addEventListener('scroll', function () {
        var y = window.scrollY;
        if (y <= 160) box.classList.remove('is-compact');
        else if (y > lastY + 4) box.classList.add('is-compact');
        else if (y < lastY - 4) box.classList.remove('is-compact');
        if (Math.abs(y - lastY) > 4 || y <= 160) lastY = y;
    }, { passive: true });

    var button = document.getElementById('wkContactButton');
    var menu = document.getElementById('wkContactMenu');
    if (!button || !menu) return;

    var setOpen = function (state, fromKeyboard) {
        button.setAttribute('aria-expanded', state ? 'true' : 'false');
        if (state) {
            menu.hidden = false;
            requestAnimationFrame(function () { box.classList.add('is-open'); });
            if (fromKeyboard) {
                var first = menu.querySelector('a');
                if (first) first.focus();
            }
        } else {
            box.classList.remove('is-open');
            // Let the card finish closing before it leaves the page.
            setTimeout(function () { if (!box.classList.contains('is-open')) menu.hidden = true; }, 220);
        }
    };

    button.addEventListener('click', function (e) {
        setOpen(button.getAttribute('aria-expanded') !== 'true', e.detail === 0);
    });
    document.addEventListener('click', function (e) {
        if (!box.contains(e.target) && button.getAttribute('aria-expanded') === 'true') setOpen(false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
            setOpen(false);
            button.focus();
        }
    });
})();
</script>
