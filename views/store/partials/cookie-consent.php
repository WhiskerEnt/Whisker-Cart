<?php
/**
 * Cookie consent banner.
 *
 * Shown only when the store owner enables it in Settings → Privacy, and
 * only until the shopper makes a choice. Accept and Reject carry the same
 * visual weight, which consent rules require, and the shopper can open
 * Customise to grant categories individually.
 *
 * The choice is recorded in the wk_cookie_consent cookie for six months as
 * JSON: which categories were granted, when, and against which version of
 * the store's cookie policy. Raising the version in the admin re-asks
 * everyone. Anything that needs to check consent uses:
 *
 *   WhiskerConsent.get()                    -> {analytics, marketing, ts, v} | null
 *   WhiskerConsent.allows('analytics')      -> bool
 *   WhiskerConsent.onAllow('analytics', fn) -> runs fn now if already granted,
 *                                              otherwise when it is granted
 *   WhiskerConsent.reopen()                 -> shows the banner again
 */

use Core\Database;
use Core\View;

if (Database::setting('privacy', 'cookie_consent', '0') !== '1') return;

$e = fn($v) => View::e($v);

$title   = Database::setting('privacy', 'cookie_title', 'We use cookies');
$text    = Database::setting('privacy', 'cookie_text', '');
$policy  = trim((string) Database::setting('privacy', 'cookie_policy_url', ''));
$version = (int) Database::setting('privacy', 'cookie_version', '1');

// A store that runs no analytics or ad scripts should not ask about them.
$categories = [];
if (Database::setting('privacy', 'cookie_analytics', '1') === '1') {
    $categories['analytics'] = ['Analytics', 'Which pages and products people look at, so we can improve the store.'];
}
if (Database::setting('privacy', 'cookie_marketing', '0') === '1') {
    $categories['marketing'] = ['Marketing', 'Relevant ads on other sites, and measuring how well they work.'];
}
?>
<div id="wkCookieBanner" class="wk-cookie" role="dialog" aria-labelledby="wkCookieTitle" aria-describedby="wkCookieText" hidden>
    <div class="wk-cookie-inner">
        <div class="wk-cookie-main">
            <div class="wk-cookie-copy">
                <p class="wk-cookie-title" id="wkCookieTitle"><?= $e($title) ?></p>
                <p class="wk-cookie-text" id="wkCookieText">
                    <?= $e($text) ?>
                    <?php if ($policy !== ''): ?>
                        <a href="<?= $e($policy) ?>" class="wk-cookie-link">Read our policy</a>
                    <?php endif; ?>
                </p>
            </div>
            <div class="wk-cookie-actions">
                <button type="button" class="wk-cookie-btn" data-consent="reject">Reject optional</button>
                <?php if ($categories): ?>
                    <button type="button" class="wk-cookie-btn" id="wkCookieCustomise" aria-expanded="false" aria-controls="wkCookiePrefs">Customise</button>
                <?php endif; ?>
                <button type="button" class="wk-cookie-btn wk-cookie-btn-accept" data-consent="accept">Accept all</button>
            </div>
        </div>

        <?php if ($categories): ?>
        <div class="wk-cookie-prefs" id="wkCookiePrefs" hidden>
            <div class="wk-cookie-cats">
            <div class="wk-cookie-cat">
                <div class="wk-cookie-cat-head">
                    <span class="wk-cookie-cat-name">Strictly necessary</span>
                    <span class="wk-cookie-always">Always on</span>
                </div>
                <p class="wk-cookie-cat-desc">Cart, sign-in and checkout. Cannot be switched off.</p>
            </div>
            <?php foreach ($categories as $key => [$label, $desc]): ?>
            <div class="wk-cookie-cat">
                <div class="wk-cookie-cat-head">
                    <label class="wk-cookie-cat-name" for="wkCookieCat-<?= $e($key) ?>"><?= $e($label) ?></label>
                    <label class="wk-cookie-switch">
                        <input type="checkbox" id="wkCookieCat-<?= $e($key) ?>" data-category="<?= $e($key) ?>">
                        <span class="wk-cookie-slider"></span>
                    </label>
                </div>
                <p class="wk-cookie-cat-desc"><?= $e($desc) ?></p>
            </div>
            <?php endforeach; ?>
            </div>
            <div class="wk-cookie-prefs-foot">
                <button type="button" class="wk-cookie-btn wk-cookie-btn-accept" data-consent="save">Save my choices</button>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
(function () {
    var NAME     = 'wk_cookie_consent';
    var VERSION  = <?= $version ?>;
    var OPTIONAL = <?= json_encode(array_keys($categories)) ?>;
    var banner   = document.getElementById('wkCookieBanner');
    var prefs    = document.getElementById('wkCookiePrefs');
    var waiting  = [];

    function read() {
        var m = document.cookie.match(/(?:^|;\s*)wk_cookie_consent=([^;]*)/);
        if (!m) return null;
        try {
            var v = JSON.parse(decodeURIComponent(m[1]));
            // A policy change re-asks rather than assuming the old answer still holds.
            return (v && v.v === VERSION) ? v : null;
        } catch (e) { return null; }
    }

    function write(granted) {
        var record = {v: VERSION, ts: Math.floor(Date.now() / 1000)};
        OPTIONAL.forEach(function (c) { record[c] = granted.indexOf(c) > -1 ? 1 : 0; });
        var expires = new Date();
        expires.setMonth(expires.getMonth() + 6);
        document.cookie = NAME + '=' + encodeURIComponent(JSON.stringify(record))
            + ';expires=' + expires.toUTCString()
            + ';path=/;SameSite=Lax'
            + (location.protocol === 'https:' ? ';Secure' : '');
        return record;
    }

    function allows(category) {
        var v = read();
        return !!(v && v[category] === 1);
    }

    function show() {
        banner.hidden = false;
        // setTimeout rather than requestAnimationFrame: rAF is paused in
        // background tabs, which would leave the banner faded out but
        // still laid out over the bottom of the page.
        setTimeout(function () { banner.classList.add('is-open'); }, 20);
    }

    function hide() {
        banner.classList.remove('is-open');
        setTimeout(function () { banner.hidden = true; }, 260);
    }

    function checkedCategories() {
        if (!prefs) return [];
        return [].filter.call(prefs.querySelectorAll('[data-category]'), function (i) { return i.checked; })
                 .map(function (i) { return i.getAttribute('data-category'); });
    }

    function choose(mode) {
        var granted = mode === 'accept' ? OPTIONAL.slice()
                    : mode === 'reject' ? []
                    : checkedCategories();
        write(granted);
        hide();
        waiting = waiting.filter(function (w) {
            if (granted.indexOf(w.category) === -1) return true;
            w.fn();
            return false;
        });
    }

    window.WhiskerConsent = {
        get: read,
        allows: allows,
        onAllow: function (category, fn) {
            allows(category) ? fn() : waiting.push({category: category, fn: fn});
        },
        reopen: function () {
            var current = read();
            if (prefs && current) {
                prefs.querySelectorAll('[data-category]').forEach(function (i) {
                    i.checked = current[i.getAttribute('data-category')] === 1;
                });
            }
            show();
        }
    };

    banner.querySelectorAll('[data-consent]').forEach(function (btn) {
        btn.addEventListener('click', function () { choose(btn.getAttribute('data-consent')); });
    });

    var toggle = document.getElementById('wkCookieCustomise');
    if (toggle && prefs) {
        toggle.addEventListener('click', function () {
            var open = prefs.hidden;
            prefs.hidden = !open;
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    if (!read()) show();
})();
</script>
