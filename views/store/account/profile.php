<?php $url=fn($p)=>\Core\View::url($p); $e=fn($v)=>\Core\View::e($v); $c=$customer;
$countries = \App\Services\CurrencyService::countries();
$hasSetPassword = (bool)\Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='customer_flags' AND setting_key=?", ['password_set_'.$c['id']]);
?>
<section class="wk-section"><div class="wk-container" style="max-width:700px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
        <h1 style="font-size:24px;font-weight:900">My Profile</h1>
        <a href="<?= $url('account') ?>" style="font-size:13px;font-weight:700;color:var(--wk-purple-ink)">← Back to Account</a>
    </div>

    <?php if (!$hasSetPassword): ?>
    <div style="background:#fef3c7;border:2px solid #fbbf24;border-radius:var(--radius);padding:20px;margin-bottom:24px">
        <div style="font-weight:800;font-size:15px;margin-bottom:4px"><?= $icon('triangle-alert') ?> Set Your Password</div>
        <p style="font-size:13px;color:#92400e;margin-bottom:16px">Your account was created during checkout. Set a password so you can sign in next time.</p>
        <form method="POST" action="<?= $url('account/set-password') ?>" style="display:flex;gap:12px;flex-wrap:wrap">
            <?= \Core\Session::csrfField() ?>
            <input type="password" name="new_password" placeholder="New password (min 8 chars)" required minlength="8" class="wk-field-input" style="flex:1;min-width:180px">
            <input type="password" name="confirm_password" placeholder="Confirm password" required class="wk-field-input" style="flex:1;min-width:180px">
            <button type="submit" class="wk-checkout-btn" style="padding:10px 24px;white-space:nowrap">Set Password</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Edit Profile -->
    <div class="wk-panel">
        <h2 style="font-size:17px;font-weight:900;margin-bottom:20px">Personal Information</h2>
        <form method="POST" action="<?= $url('account/profile') ?>">
            <?= \Core\Session::csrfField() ?>
            <div class="wk-cols-2" style="gap:14px">
                <div><label class="wk-field-label">First Name</label><input type="text" name="first_name" value="<?= $e($c['first_name']) ?>" required class="wk-field-input"></div>
                <div><label class="wk-field-label">Last Name</label><input type="text" name="last_name" value="<?= $e($c['last_name']) ?>" required class="wk-field-input"></div>
            </div>
            <div class="wk-mt-14"><label class="wk-field-label">Email <span style="font-weight:500;text-transform:none">(cannot be changed)</span></label><input type="email" value="<?= $e($c['email']) ?>" disabled class="wk-field-input" style="background:var(--wk-bg);opacity:.7"></div>
            <div class="wk-mt-14">
                <label class="wk-field-label">Phone</label>
                <?php
                $wkPhone = ['value' => $c['phone'] ?? '', 'inputClass' => 'wk-field-input'];
                require __DIR__ . '/../partials/phone-field.php';
                ?>
            </div>
            <button type="submit" class="wk-mt-20 wk-checkout-btn">Save Changes</button>
        </form>
    </div>

    <?php if ($hasSetPassword): ?>
    <!-- Change Password -->
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:28px">
        <h2 style="font-size:17px;font-weight:900;margin-bottom:20px">Change Password</h2>
        <form method="POST" action="<?= $url('account/set-password') ?>">
            <?= \Core\Session::csrfField() ?>
            <div class="wk-mb-14"><label class="wk-field-label">Current Password</label><input type="password" name="current_password" required placeholder="Your existing password" autocomplete="current-password" class="wk-field-input"></div>
            <div class="wk-cols-2" style="gap:14px">
                <div><label class="wk-field-label">New Password</label><input type="password" name="new_password" required minlength="8" placeholder="Min 8 characters" autocomplete="new-password" class="wk-field-input"></div>
                <div><label class="wk-field-label">Confirm Password</label><input type="password" name="confirm_password" required placeholder="Type again" autocomplete="new-password" class="wk-field-input"></div>
            </div>
            <button type="submit" class="wk-checkout-btn" style="margin-top:16px">Update Password</button>
        </form>
    </div>
    <?php endif; ?>
</div></section>