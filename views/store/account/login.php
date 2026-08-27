<?php $url=fn($p)=>\Core\View::url($p); ?>
<section class="wk-section"><div class="wk-container" style="max-width:440px">
    <div style="text-align:center;margin-bottom:32px">
        <h1 style="font-size:26px;font-weight:900">Sign In</h1>
        <p style="color:var(--wk-muted);font-size:14px">Welcome back</p>
    </div>
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:32px">
        <form method="POST" action="<?= $url('account/login') ?>">
            <?= \Core\Session::csrfField() ?>
            <div><label style="display:block;font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-muted);margin-bottom:4px">Email</label><input type="email" name="email" required placeholder="you@example.com" data-wk-validate="email" style="width:100%;padding:10px 14px;border:2px solid var(--wk-border);border-radius:8px;font-family:var(--font);font-size:14px;font-weight:600"></div>
            <div style="margin-top:14px">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                    <label style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-muted)">Password</label>
                    <a href="<?= $url('account/forgot-password') ?>" style="font-size:11px;font-weight:700;color:var(--wk-purple)">Forgot password?</a>
                </div>
                <input type="password" name="password" required style="width:100%;padding:10px 14px;border:2px solid var(--wk-border);border-radius:8px;font-family:var(--font);font-size:14px;font-weight:600">
            </div>
            <button type="submit" class="wk-checkout-btn" style="margin-top:20px">Sign In →</button>
        </form>
    </div>
    <p style="text-align:center;margin-top:16px;font-size:14px;color:var(--wk-muted)">Don't have an account? <a href="<?= $url('account/register') ?>" style="color:var(--wk-purple);font-weight:700">Create one</a></p>
</div></section>