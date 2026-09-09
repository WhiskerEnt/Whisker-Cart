<?php $e = fn($v) => \Core\View::e((string) $v); $url = fn($p) => \Core\View::url($p); ?>
<section class="wk-section">
    <div class="wk-container" style="max-width:560px;text-align:center;padding-top:40px;padding-bottom:60px">
        <?php if ($ok): ?>
            <div style="font-size:52px;margin-bottom:12px">✅</div>
            <h1 style="font-size:26px;font-weight:900;margin-bottom:10px">You are off the list</h1>
            <p style="color:var(--wk-muted);font-size:15px;line-height:1.7;margin-bottom:8px">
                We will not email <strong><?= $e($email) ?></strong> about a basket left behind again.
            </p>
            <p style="color:var(--wk-muted);font-size:13px;line-height:1.7">
                Order confirmations and delivery updates still come through — those are about orders you placed.
            </p>
        <?php else: ?>
            <div style="font-size:52px;margin-bottom:12px">🔗</div>
            <h1 style="font-size:26px;font-weight:900;margin-bottom:10px">That link did not work</h1>
            <p style="color:var(--wk-muted);font-size:15px;line-height:1.7">
                It may have been cut short by your email app. Get in touch and we will take you off the list.
            </p>
        <?php endif; ?>
        <a href="<?= $url('') ?>" style="display:inline-block;margin-top:22px;color:var(--wk-purple-ink);font-weight:800;text-decoration:none">
            Back to the shop
        </a>
    </div>
</section>
