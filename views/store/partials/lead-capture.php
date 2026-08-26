<?php
/**
 * Exit-intent prompt.
 *
 * Shown once, when someone with a basket looks like they are leaving. It asks
 * for one way to reach them so the basket can be sent back to them later.
 *
 * Deliberately quiet: one appearance per visitor per week, never over a form
 * they are filling in, and dismissed for good the moment they say no.
 */
use App\Services\LeadService;

if (!LeadService::shouldOffer()) return;

$e = fn($v) => \Core\View::e((string) $v);
$fields = LeadService::fields();
$coupon = LeadService::usableCoupon();
?>
<div class="wk-lead" id="wkLead" hidden>
    <div class="wk-lead-backdrop" data-lead-close></div>
    <div class="wk-lead-box" role="dialog" aria-modal="true" aria-labelledby="wkLeadTitle">
        <button type="button" class="wk-lead-x" data-lead-close aria-label="No thanks">&times;</button>

        <h2 class="wk-lead-title" id="wkLeadTitle"><?= $e(LeadService::title()) ?></h2>
        <?php if (LeadService::text() !== ''): ?>
            <p class="wk-lead-text" id="wkLeadText"><?= $e(LeadService::text()) ?></p>
        <?php endif; ?>

        <?php if ($coupon): ?>
            <div class="wk-lead-offer">
                <span class="wk-lead-offer-value"><?php
                    echo $coupon['type'] === 'percentage'
                        ? rtrim(rtrim(number_format((float) $coupon['value'], 2, '.', ''), '0'), '.') . '% off'
                        : \Core\View::price($coupon['value']) . ' off';
                ?></span>
                <span class="wk-lead-offer-note">on your order</span>
            </div>
        <?php endif; ?>

        <form class="wk-lead-form" id="wkLeadForm">
            <?= \Core\Session::csrfField() ?>
            <?php if ($fields === 'email' || $fields === 'both'): ?>
                <label class="wk-lead-field">
                    <span>Email</span>
                    <input type="email" name="email" maxlength="190" autocomplete="email"
                           placeholder="you@example.com" <?= $fields === 'email' ? 'required' : '' ?>>
                </label>
            <?php endif; ?>
            <?php if ($fields === 'phone' || $fields === 'both'): ?>
                <label class="wk-lead-field">
                    <span>Phone</span>
                    <input type="tel" name="phone" maxlength="40" autocomplete="tel"
                           placeholder="+91 98765 43210" <?= $fields === 'phone' ? 'required' : '' ?>>
                </label>
            <?php endif; ?>
            <?php if ($fields === 'both'): ?>
                <p class="wk-lead-hint">Either one is enough.</p>
            <?php endif; ?>

            <button type="submit" class="wk-lead-submit">
                <?= $coupon ? 'Send me the code' : 'Save my basket' ?>
            </button>
            <button type="button" class="wk-lead-no" data-lead-close>No thanks</button>
        </form>

        <p class="wk-lead-done" id="wkLeadDone" hidden></p>
    </div>
</div>
<script>
(function () {
    var box = document.getElementById('wkLead');
    if (!box) return;

    // Versioned, so a value written by an earlier build cannot keep the
    // prompt quiet for a week after the reason for it was fixed.
    var KEY = 'wk_lead_seen_v2';
    var QUIET_DAYS = 7;

    // ?lead=preview shows it straight away and does not count as being seen,
    // so a shopkeeper can look at their own wording without waiting a week.
    var preview = /[?&]lead=preview\b/.test(location.search);

    var TRIGGER = <?= json_encode(LeadService::trigger()) ?>;
    var DELAY   = <?= (int) LeadService::delaySeconds() ?>;
    var COPY    = {
        exit: {
            title: <?= json_encode(LeadService::title()) ?>,
            text:  <?= json_encode(LeadService::text()) ?>
        },
        time: {
            title: <?= json_encode(LeadService::timeTitle()) ?>,
            text:  <?= json_encode(LeadService::timeText()) ?>
        }
    };

    function seen() {
        if (preview) return false;
        try {
            var until = parseInt(localStorage.getItem(KEY) || '0', 10);
            return until > Date.now();
        } catch (e) { return false; }
    }
    function remember(days) {
        if (preview) return;
        try { localStorage.setItem(KEY, String(Date.now() + days * 86400000)); } catch (e) {}
    }

    var shown = false;
    function show(reason) {
        if (shown || seen()) return;
        // Never over a form somebody is part-way through.
        var active = document.activeElement;
        if (active && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) return;

        // Someone still browsing is asked a different question from someone
        // on their way out.
        var copy = COPY[reason === 'time' ? 'time' : 'exit'];
        var titleEl = document.getElementById('wkLeadTitle');
        var textEl = document.getElementById('wkLeadText');
        if (titleEl && copy.title) titleEl.textContent = copy.title;
        if (textEl && copy.text) textEl.textContent = copy.text;

        shown = true;
        box.hidden = false;
        setTimeout(function () { box.classList.add('is-open'); }, 20);
        remember(QUIET_DAYS);
    }

    function close() {
        box.classList.remove('is-open');
        setTimeout(function () { box.hidden = true; }, 240);
        remember(QUIET_DAYS);
    }

    box.querySelectorAll('[data-lead-close]').forEach(function (el) {
        el.addEventListener('click', close);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !box.hidden) close();
    });

    // Give the page a moment before watching, so a pointer that happens to
    // start near the top does not trigger it on arrival.
    var armed = false;
    setTimeout(function () { armed = true; }, 3000);

    if (preview) {
        armed = true;
        setTimeout(function () { show(/[?&]lead=preview=time/.test(location.search) ? 'time' : 'exit'); }, 400);
    }

    // ── Dwell ────────────────────────────────────────────────────────────
    // Counted across pages and only while the tab is actually being looked at,
    // so browsing four pages for a minute each counts, and a tab left open in
    // the background does not.
    if (TRIGGER === 'time' || TRIGGER === 'both') {
        var DWELL_KEY = 'wk_lead_dwell';
        var dwell = 0;
        try { dwell = parseInt(sessionStorage.getItem(DWELL_KEY) || '0', 10) || 0; } catch (e) {}

        setInterval(function () {
            if (document.visibilityState !== 'visible') return;
            dwell++;
            try { sessionStorage.setItem(DWELL_KEY, String(dwell)); } catch (e) {}
            if (dwell >= DELAY) show('time');
        }, 1000);
    }

    // Desktop: the pointer heading out through the top of the window, which
    // is where the tab bar, the address bar and the close button all live.
    // Both events are watched because browsers differ over which one fires
    // when the pointer leaves the window entirely.
    function maybeExit(e) {
        if (!armed || TRIGGER === 'time') return;
        var y = e.clientY;
        if (typeof y === 'number' && y <= 8 && !e.relatedTarget && !e.toElement) show('exit');
    }
    document.addEventListener('mouseout', maybeExit);
    document.documentElement.addEventListener('mouseleave', maybeExit);

    // Touch: no pointer to watch, so wait until they have read a fair amount
    // and then scrolled back up, which is what leaving tends to look like.
    var deepest = 0, settled = false;
    setTimeout(function () { settled = true; }, 25000);
    window.addEventListener('scroll', function () {
        var y = window.scrollY || 0;
        if (y > deepest) { deepest = y; return; }
        if (settled && deepest > 500 && y < deepest - 400) show('exit');
    }, { passive: true });

    var form = document.getElementById('wkLeadForm');
    var done = document.getElementById('wkLeadDone');
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = form.querySelector('.wk-lead-submit');
        var body = new FormData(form);
        btn.disabled = true;
        btn.textContent = 'Saving...';

        fetch(<?= json_encode(\Core\View::url('lead')) ?>, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (!d.success) {
                    btn.disabled = false;
                    btn.textContent = 'Try again';
                    done.hidden = false;
                    done.className = 'wk-lead-done is-bad';
                    done.textContent = d.message || 'Please check what you entered.';
                    return;
                }
                form.hidden = true;
                done.hidden = false;
                done.className = 'wk-lead-done is-ok';
                done.innerHTML = d.coupon
                    ? d.message + ' <strong class="wk-lead-code">' + d.coupon + '</strong>'
                    : d.message;
                remember(365);
                setTimeout(close, d.coupon ? 6000 : 2500);
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = 'Try again';
            });
    });
})();
</script>
