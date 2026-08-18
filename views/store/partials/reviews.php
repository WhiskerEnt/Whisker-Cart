<?php
/**
 * Reviews block on a product page.
 *
 * Expects $product, $reviewStats, $reviews and $reviewPolicy from the
 * controller. The form is always offered; whether this person may actually
 * post is decided server-side once they submit, because we do not know who
 * they are until they give us an email address.
 */
$e   = fn($v) => \Core\View::e((string) $v);
$url = fn($p) => \Core\View::url($p);

$avg   = (float) ($reviewStats['average'] ?? 0);
$count = (int) ($reviewStats['count'] ?? 0);
$breakdown = $reviewStats['breakdown'] ?? [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

/** Star row. $filled may be fractional; the last star is clipped to show it. */
$starRow = function (float $filled, int $size = 16) {
    $out = '<span class="wk-stars" style="font-size:' . $size . 'px" aria-hidden="true">';
    for ($i = 1; $i <= 5; $i++) {
        $pct = max(0, min(1, $filled - ($i - 1))) * 100;
        $out .= '<span class="wk-star"><span class="wk-star-off">★</span>'
              . '<span class="wk-star-on" style="width:' . round($pct, 1) . '%">★</span></span>';
    }
    return $out . '</span>';
};
?>
<section class="wk-reviews" id="reviews">
    <h2 class="wk-reviews-heading">Ratings &amp; Reviews</h2>

    <?php if ($count > 0): ?>
    <div class="wk-reviews-summary">
        <div class="wk-reviews-score">
            <div class="wk-reviews-avg"><?= number_format($avg, 1) ?></div>
            <?= $starRow($avg, 18) ?>
            <div class="wk-reviews-count"><?= $count ?> review<?= $count === 1 ? '' : 's' ?></div>
        </div>
        <div class="wk-reviews-bars">
            <?php for ($star = 5; $star >= 1; $star--):
                $n = (int) ($breakdown[$star] ?? 0);
                $pct = $count > 0 ? round($n / $count * 100) : 0; ?>
                <div class="wk-reviews-bar">
                    <span class="wk-reviews-bar-label"><?= $star ?>★</span>
                    <span class="wk-reviews-bar-track"><span class="wk-reviews-bar-fill" style="width:<?= $pct ?>%"></span></span>
                    <span class="wk-reviews-bar-n"><?= $n ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </div>
    <?php else: ?>
        <p class="wk-reviews-empty">No reviews yet. If you have bought this, yours would be the first.</p>
    <?php endif; ?>

    <?php if ($reviews): ?>
    <ol class="wk-review-list">
        <?php foreach ($reviews as $r): ?>
        <li class="wk-review">
            <div class="wk-review-head">
                <?= $starRow((float) $r['rating'], 14) ?>
                <?php if ($r['title']): ?><strong class="wk-review-title"><?= $e($r['title']) ?></strong><?php endif; ?>
            </div>
            <div class="wk-review-meta">
                <span class="wk-review-author"><?= $e($r['author_name']) ?></span>
                <?php if ($r['is_verified_purchase']): ?>
                    <span class="wk-review-verified">✓ Verified purchase</span>
                <?php endif; ?>
                <time datetime="<?= $e(date('c', strtotime($r['created_at']))) ?>"><?= $e(date('j M Y', strtotime($r['created_at']))) ?></time>
            </div>
            <?php if ($r['body']): ?>
                <p class="wk-review-body"><?= $e($r['body']) ?></p>
            <?php endif; ?>
            <?php if ($r['admin_reply']): ?>
                <div class="wk-review-reply">
                    <span class="wk-review-reply-who">Reply from the store</span>
                    <p><?= $e($r['admin_reply']) ?></p>
                </div>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ol>
    <?php endif; ?>

    <details class="wk-review-form-wrap" <?= $count === 0 ? 'open' : '' ?>>
        <summary class="wk-review-form-toggle">Write a review</summary>

        <?php if ($reviewPolicy !== 'anyone'): ?>
            <p class="wk-review-note">
                <?= $reviewPolicy === 'received'
                    ? 'Reviews are for customers who have received this product. Use the email address on your order.'
                    : 'Reviews are for customers who have bought this product. Use the email address on your order.' ?>
            </p>
        <?php endif; ?>

        <form method="POST" action="<?= $url('review') ?>" class="wk-review-form">
            <?= \Core\Session::csrfField() ?>
            <input type="hidden" name="product_slug" value="<?= $e($product['slug']) ?>">

            <fieldset class="wk-rating-input">
                <legend>Your rating</legend>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" name="rating" id="wkRate<?= $i ?>" value="<?= $i ?>" required>
                    <label for="wkRate<?= $i ?>" title="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>">
                        <span aria-hidden="true">★</span>
                        <span class="wk-sr"><?= $i ?> star<?= $i === 1 ? '' : 's' ?></span>
                    </label>
                <?php endfor; ?>
            </fieldset>

            <div class="wk-review-fields">
                <label>
                    <span>Your name</span>
                    <input type="text" name="name" maxlength="80" required autocomplete="name">
                </label>
                <label>
                    <span>Your email</span>
                    <input type="email" name="email" id="wkReviewEmail" maxlength="190" required
                           autocomplete="email" aria-describedby="wkReviewEmailMsg">
                    <small id="wkReviewEmailMsg">Not shown publicly.</small>
                </label>
            </div>

            <label class="wk-review-field">
                <span>Headline <em>(optional)</em></span>
                <input type="text" name="title" maxlength="140" placeholder="Sums up your experience">
            </label>

            <label class="wk-review-field">
                <span>Your review <em>(optional)</em></span>
                <textarea name="body" rows="4" maxlength="4000" placeholder="How was it? What should other shoppers know?"></textarea>
            </label>

            <button type="submit" class="wk-review-submit">Submit review</button>
        </form>
    </details>
</section>
<script>
(function () {
    var form = document.querySelector('.wk-review-form');
    if (!form) return;
    var email = document.getElementById('wkReviewEmail');
    var msg   = document.getElementById('wkReviewEmailMsg');
    var hint  = msg.textContent;
    var touched = false;

    function check(showWhileTyping) {
        var v = email.value.trim();
        if (v === '') {
            set('', hint);
            return false;
        }
        // Deliberately loose: something@something.tld. The point is to catch
        // "test" before the page reloads, not to police valid addresses.
        var ok = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
        if (ok) {
            set('is-ok', 'Looks good.');
        } else if (showWhileTyping) {
            set('is-bad', v.indexOf('@') === -1
                ? 'An email address needs an @ — for example you@example.com'
                : 'That does not look complete yet — for example you@example.com');
        } else {
            set('', hint);
        }
        return ok;
    }

    function set(state, text) {
        email.classList.remove('is-ok', 'is-bad');
        msg.classList.remove('is-ok', 'is-bad');
        if (state) { email.classList.add(state); msg.classList.add(state); }
        msg.textContent = text;
    }

    // Quiet until they leave the field, then live from that point on.
    email.addEventListener('blur', function () { touched = true; check(true); });
    email.addEventListener('input', function () { check(touched); });

    form.addEventListener('submit', function (e) {
        touched = true;
        if (!check(true)) {
            e.preventDefault();
            email.focus();
        }
    });
})();
</script>
