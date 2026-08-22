<?php
/**
 * Questions and answers under a product.
 *
 * Only answered, published questions reach this view — the service filters
 * them, so there is no chance of an unanswered question appearing here.
 */
$e   = fn($v) => \Core\View::e((string) $v);
$url = fn($p) => \Core\View::url($p);
?>
<section class="wk-qa" id="questions">
    <h2 class="wk-qa-heading">
        Questions &amp; Answers
        <?php if ($questions): ?><span class="wk-qa-count"><?= count($questions) ?></span><?php endif; ?>
    </h2>

    <?php if ($questions): ?>
    <ol class="wk-qa-list">
        <?php foreach ($questions as $q): ?>
        <li class="wk-qa-item">
            <div class="wk-qa-q">
                <span class="wk-qa-marker" aria-hidden="true">Q</span>
                <div>
                    <p class="wk-qa-text"><?= $e($q['question']) ?></p>
                    <p class="wk-qa-meta">
                        <?= $e($q['author_name']) ?>
                        <time datetime="<?= $e(date('c', strtotime($q['created_at']))) ?>"><?= $e(date('j M Y', strtotime($q['created_at']))) ?></time>
                    </p>
                </div>
            </div>
            <div class="wk-qa-a">
                <span class="wk-qa-marker wk-qa-marker-a" aria-hidden="true">A</span>
                <div>
                    <p class="wk-qa-text"><?= $e($q['answer']) ?></p>
                    <p class="wk-qa-meta">From the store<?php if ($q['answered_at']): ?>
                        <time datetime="<?= $e(date('c', strtotime($q['answered_at']))) ?>"><?= $e(date('j M Y', strtotime($q['answered_at']))) ?></time>
                    <?php endif; ?></p>
                </div>
            </div>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php else: ?>
        <p class="wk-qa-empty">No questions about this one yet. Ask us anything and we will answer here.</p>
    <?php endif; ?>

    <details class="wk-qa-form-wrap" <?= $questions ? '' : 'open' ?>>
        <summary class="wk-qa-form-toggle">Ask a question</summary>
        <p class="wk-qa-note">
            We answer by email and publish the answer here, so the next shopper with the same question finds it.
            Your email address is never shown.
        </p>
        <form method="POST" action="<?= $url('question') ?>" class="wk-qa-form">
            <?= \Core\Session::csrfField() ?>
            <input type="hidden" name="product_slug" value="<?= $e($product['slug']) ?>">

            <label class="wk-qa-field">
                <span>Your question</span>
                <textarea name="question" rows="3" maxlength="1000" required
                          placeholder="Is this true to size? How long does delivery take?"></textarea>
            </label>

            <div class="wk-qa-fields">
                <label>
                    <span>Your name</span>
                    <input type="text" name="name" maxlength="80" required autocomplete="name">
                </label>
                <label>
                    <span>Your email</span>
                    <input type="email" name="email" id="wkQaEmail" maxlength="190" required
                           autocomplete="email" aria-describedby="wkQaEmailMsg">
                    <small id="wkQaEmailMsg">We reply here. Never shown publicly.</small>
                </label>
            </div>

            <button type="submit" class="wk-review-submit">Send question</button>
        </form>
    </details>
</section>
<script>
(function () {
    var form = document.querySelector('.wk-qa-form');
    if (!form) return;
    var email = document.getElementById('wkQaEmail');
    var msg   = document.getElementById('wkQaEmailMsg');
    var hint  = msg.textContent;
    var touched = false;

    function set(state, text) {
        email.classList.remove('is-ok', 'is-bad');
        msg.classList.remove('is-ok', 'is-bad');
        if (state) { email.classList.add(state); msg.classList.add(state); }
        msg.textContent = text;
    }

    function check(loud) {
        var v = email.value.trim();
        if (v === '') { set('', hint); return false; }
        var ok = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
        if (ok) set('is-ok', 'Looks good.');
        else if (loud) set('is-bad', v.indexOf('@') === -1
            ? 'An email address needs an @ — for example you@example.com'
            : 'That does not look complete yet — for example you@example.com');
        else set('', hint);
        return ok;
    }

    email.addEventListener('blur', function () { touched = true; check(true); });
    email.addEventListener('input', function () { check(touched); });
    form.addEventListener('submit', function (e) {
        touched = true;
        if (!check(true)) { e.preventDefault(); email.focus(); }
    });
})();
</script>
