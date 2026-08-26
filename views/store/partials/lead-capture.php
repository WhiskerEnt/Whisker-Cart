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
            <p class="wk-lead-text"><?= $e(LeadService::text()) ?></p>
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

    var KEY = 'wk_lead_seen';
    var QUIET_DAYS = 7;

    function seen() {
        try {
            var until = parseInt(localStorage.getItem(KEY) || '0', 10);
            return until > Date.now();
        } catch (e) { return false; }
    }
    function remember(days) {
        try { localStorage.setItem(KEY, String(Date.now() + days * 86400000)); } catch (e) {}
    }

    var shown = false;
    function show() {
        if (shown || seen()) return;
        // Never over a form somebody is part-way through.
        var active = document.activeElement;
        if (active && /^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)) return;

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

    // Desktop: the pointer leaving through the top of the window.
    document.addEventListener('mouseout', function (e) {
        if (e.clientY <= 0 && !e.relatedTarget) show();
    });

    // Touch: no pointer to watch, so wait until they have read a fair amount
    // and then scrolled back up, which is what leaving tends to look like.
    var deepest = 0, settled = false;
    setTimeout(function () { settled = true; }, 25000);
    window.addEventListener('scroll', function () {
        var y = window.scrollY || 0;
        if (y > deepest) { deepest = y; return; }
        if (settled && deepest > 500 && y < deepest - 400) show();
    }, { passive: true });

    // Leaving the tab for something else counts too.
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') remember(0.02);
    });

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
