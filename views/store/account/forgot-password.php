<?php $url=fn($p)=>\Core\View::url($p); ?>
<section class="wk-section"><div class="wk-container" style="max-width:440px">
    <div style="text-align:center;margin-bottom:32px">
        <div style="font-size:48px;margin-bottom:8px">🔑</div>
        <h1 style="font-size:26px;font-weight:900">Forgot Password</h1>
        <p style="color:var(--wk-muted);font-size:14px">Enter your email and we'll send a reset link</p>
    </div>
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:32px">
        <form method="POST" action="<?= $url('account/forgot-password') ?>">
            <?= \Core\Session::csrfField() ?>
            <div><label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-muted);margin-bottom:4px">Email Address</label><input type="email" name="email" required placeholder="you@example.com" style="width:100%;padding:10px 14px;border:2px solid var(--wk-border);border-radius:8px;font-family:var(--font);font-size:14px;font-weight:600"></div>
            <button type="submit" class="wk-checkout-btn" style="margin-top:20px">Send Reset Link</button>
        </form>
    </div>
    <p style="text-align:center;margin-top:16px;font-size:14px;color:var(--wk-muted)">Remember your password? <a href="<?= $url('account/login') ?>" style="color:var(--wk-purple-ink);font-weight:700">Sign in</a></p>
</div></section>