<?php $url=fn($p)=>\Core\View::url($p); ?>
<section class="wk-section"><div class="wk-container" style="max-width:440px">
    <div class="wk-page-intro">
        <h1 style="font-size:26px;font-weight:900">Set New Password</h1>
    </div>
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:32px">
        <form method="POST" action="<?= $url('account/reset-password') ?>">
            <?= \Core\Session::csrfField() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token??'') ?>">
            <input type="hidden" name="customer_id" value="<?= (int)($customerId??0) ?>">
            <div><label class="wk-label">New Password</label><input type="password" name="password" required minlength="8" placeholder="Min 8 characters" style="width:100%;padding:10px 14px;border:2px solid var(--wk-border);border-radius:8px;font-family:var(--font);font-size:14px;font-weight:600"></div>
            <div class="wk-mt-14"><label class="wk-label">Confirm Password</label><input type="password" name="confirm_password" required placeholder="Type again" style="width:100%;padding:10px 14px;border:2px solid var(--wk-border);border-radius:8px;font-family:var(--font);font-size:14px;font-weight:600"></div>
            <button type="submit" class="wk-mt-20 wk-checkout-btn">Reset Password</button>
        </form>
    </div>
</div></section>