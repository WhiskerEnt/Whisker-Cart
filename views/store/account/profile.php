<?php $url=fn($p)=>\Core\View::url($p); $e=fn($v)=>\Core\View::e($v); $c=$customer;
$countries = \App\Services\CurrencyService::countries();
$hasSetPassword = (bool)\Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='customer_flags' AND setting_key=?", ['password_set_'.$c['id']]);
$is = 'width:100%;padding:10px 14px;border:2px solid var(--wk-border);border-radius:8px;font-family:var(--font);font-size:14px;font-weight:600;outline:none';
$ls = 'display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-muted);margin-bottom:4px';
?>
<section class="wk-section"><div class="wk-container" style="max-width:700px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
        <h1 style="font-size:24px;font-weight:900">My Profile</h1>
        <a href="<?= $url('account') ?>" style="font-size:13px;font-weight:700;color:var(--wk-purple)">← Back to Account</a>
    </div>

    <?php if (!$hasSetPassword): ?>
    <div style="background:#fef3c7;border:2px solid #fbbf24;border-radius:var(--radius);padding:20px;margin-bottom:24px">
        <div style="font-weight:800;font-size:15px;margin-bottom:4px">⚠ Set Your Password</div>
        <p style="font-size:13px;color:#92400e;margin-bottom:16px">Your account was created during checkout. Set a password so you can sign in next time.</p>
        <form method="POST" action="<?= $url('account/set-password') ?>" style="display:flex;gap:12px;flex-wrap:wrap">
            <?= \Core\Session::csrfField() ?>
            <input type="password" name="new_password" placeholder="New password (min 8 chars)" required minlength="8" style="<?= $is ?>;flex:1;min-width:180px">
            <input type="password" name="confirm_password" placeholder="Confirm password" required style="<?= $is ?>;flex:1;min-width:180px">
            <button type="submit" class="wk-checkout-btn" style="padding:10px 24px;white-space:nowrap">Set Password</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Edit Profile -->
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:28px;margin-bottom:20px">
        <h2 style="font-size:17px;font-weight:900;margin-bottom:20px">Personal Information</h2>
        <form method="POST" action="<?= $url('account/profile') ?>">
            <?= \Core\Session::csrfField() ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div><label style="<?= $ls ?>">First Name</label><input type="text" name="first_name" value="<?= $e($c['first_name']) ?>" required style="<?= $is ?>"></div>
                <div><label style="<?= $ls ?>">Last Name</label><input type="text" name="last_name" value="<?= $e($c['last_name']) ?>" required style="<?= $is ?>"></div>
            </div>
            <div style="margin-top:14px"><label style="<?= $ls ?>">Email <span style="font-weight:500;text-transform:none">(cannot be changed)</span></label><input type="email" value="<?= $e($c['email']) ?>" disabled style="<?= $is ?>;background:var(--wk-bg);opacity:.7"></div>
            <div style="margin-top:14px"><label style="<?= $ls ?>">Phone</label><input type="tel" name="phone" value="<?= $e($c['phone']??'') ?>" style="<?= $is ?>"></div>
            <button type="submit" class="wk-checkout-btn" style="margin-top:20px">Save Changes</button>
        </form>
    </div>

    <?php if ($hasSetPassword): ?>
    <!-- Change Password -->
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:28px">
        <h2 style="font-size:17px;font-weight:900;margin-bottom:20px">Change Password</h2>
        <form method="POST" action="<?= $url('account/set-password') ?>">
            <?= \Core\Session::csrfField() ?>
            <div style="margin-bottom:14px"><label style="<?= $ls ?>">Current Password</label><input type="password" name="current_password" required placeholder="Your existing password" autocomplete="current-password" style="<?= $is ?>"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div><label style="<?= $ls ?>">New Password</label><input type="password" name="new_password" required minlength="8" placeholder="Min 8 characters" autocomplete="new-password" style="<?= $is ?>"></div>
                <div><label style="<?= $ls ?>">Confirm Password</label><input type="password" name="confirm_password" required placeholder="Type again" autocomplete="new-password" style="<?= $is ?>"></div>
            </div>
            <button type="submit" class="wk-checkout-btn" style="margin-top:16px">Update Password</button>
        </form>
    </div>
    <?php endif; ?>
</div></section>