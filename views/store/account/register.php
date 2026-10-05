<?php
$url = fn($p) => \Core\View::url($p);
$e   = fn($v) => \Core\View::e($v);
$F   = \App\Services\AccountFieldService::class;
// What was typed before a rejected submission, so nothing has to be retyped.
$old = $_SESSION['wk_register_old'] ?? [];
unset($_SESSION['wk_register_old']);
$was = fn($name) => $e((string)($old[$name] ?? ''));
$req = fn($key) => $F::isRequired($key) ? ' required' : '';
$opt = fn($key) => $F::isRequired($key) ? '' : ' <span class="wk-subtle">(optional)</span>';
?>
<section class="wk-section"><div class="wk-container" style="max-width:480px">
    <div class="wk-page-intro">
        <h1 style="font-size:26px;font-weight:900">Create Account</h1>
        <p style="color:var(--wk-muted);font-size:14px">Join us for faster checkout and order tracking</p>
    </div>
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:32px">
        <form method="POST" action="<?= $url('account/register') ?>" id="registerForm">
            <?= \Core\Session::csrfField() ?>
            <?php if ($F::isShown('first_name') || $F::isShown('last_name')): ?>
            <div class="wk-cols-2" style="gap:12px">
                <?php if ($F::isShown('first_name')): ?>
                <div><label class="wk-label">First Name<?= $opt('first_name') ?></label><input type="text" name="first_name"<?= $req('first_name') ?> value="<?= $was('first_name') ?>" placeholder="John" class="wk-field-input"></div>
                <?php endif; ?>
                <?php if ($F::isShown('last_name')): ?>
                <div><label class="wk-label">Last Name<?= $opt('last_name') ?></label><input type="text" name="last_name"<?= $req('last_name') ?> value="<?= $was('last_name') ?>" placeholder="Doe" class="wk-field-input"></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="wk-mt-14"><label class="wk-label">Email</label><input type="email" name="email" required value="<?= $was('email') ?>" placeholder="you@example.com" data-wk-validate="email" class="wk-field-input"></div>

            <?php if ($F::isShown('username')): ?>
            <div class="wk-mt-14">
                <label class="wk-label">Username<?= $opt('username') ?></label>
                <input type="text" name="username"<?= $req('username') ?> value="<?= $was('username') ?>" placeholder="johndoe" autocomplete="username" maxlength="40" class="wk-field-input">
                <?php if ($F::usernameLogin()): ?>
                <div class="wk-note wk-mt-8">You can sign in with this instead of your email address.</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($F::isShown('phone')): ?>
            <div class="wk-mt-14">
                <label class="wk-label">Phone<?= $opt('phone') ?></label>
                <?php
                $wkPhone = ['inputClass' => 'wk-field-input', 'required' => $F::isRequired('phone'), 'value' => (string)($old['phone'] ?? '')];
                require __DIR__ . '/../partials/phone-field.php';
                ?>
            </div>
            <?php endif; ?>

            <?php foreach ($F::custom(true) as $wkField): ?>
                <?php $wkFieldValue = (string)($old[$F::inputName($wkField['field_key'])] ?? ''); ?>
                <div class="wk-mt-14"><?php require __DIR__ . '/../partials/custom-field.php'; ?></div>
            <?php endforeach; ?>
            <div class="wk-mt-14">
                <label class="wk-label">Password</label>
                <input type="password" name="password" id="regPw" required minlength="8" placeholder="Min 8 chars, 1 number, 1 special" oninput="checkPw()" style="width:100%;padding:10px 14px;border:2px solid var(--wk-border);border-radius:8px;font-family:var(--font);font-size:14px;font-weight:600;outline:none">
                <div id="pwMeter" style="display:flex;align-items:center;gap:8px;margin-top:8px">
                    <div style="flex:1;display:flex;gap:3px">
                        <span class="wk-pw-seg" style="flex:1;height:4px;border-radius:99px;background:var(--wk-border);transition:background .2s"></span>
                        <span class="wk-pw-seg" style="flex:1;height:4px;border-radius:99px;background:var(--wk-border);transition:background .2s"></span>
                        <span class="wk-pw-seg" style="flex:1;height:4px;border-radius:99px;background:var(--wk-border);transition:background .2s"></span>
                        <span class="wk-pw-seg" style="flex:1;height:4px;border-radius:99px;background:var(--wk-border);transition:background .2s"></span>
                    </div>
                    <span id="pwStrength" style="font-size:11px;font-weight:800;color:var(--wk-muted);min-width:52px;text-align:right"></span>
                </div>
                <div id="pwRules" style="display:flex;gap:12px;margin-top:6px;font-size:11px;font-weight:700">
                    <span id="r-len" class="wk-muted">● 8+ chars</span>
                    <span id="r-num" class="wk-muted">● 1 number</span>
                    <span id="r-spc" class="wk-muted">● 1 special</span>
                </div>
                <div id="pwHint" style="font-size:11px;font-weight:600;color:var(--wk-muted);margin-top:5px;min-height:15px"></div>
            </div>
            <div class="wk-mt-14">
                <label class="wk-label">Confirm Password</label>
                <input type="password" name="password_confirm" id="regPw2" required oninput="checkPw()" placeholder="Type again" style="width:100%;padding:10px 14px;border:2px solid var(--wk-border);border-radius:8px;font-family:var(--font);font-size:14px;font-weight:600;outline:none">
                <div id="pwMatch" style="font-size:11px;font-weight:700;margin-top:4px;min-height:16px"></div>
            </div>
            <button type="submit" id="regSubmit" disabled class="wk-checkout-btn" style="margin-top:20px;opacity:.5">Create Account</button>
        </form>
    </div>
    <p style="text-align:center;margin-top:16px;font-size:14px;color:var(--wk-muted)">Already have an account? <a href="<?= $url('account/login') ?>" class="wk-link">Sign in</a></p>
</div></section>

<script>
// Four bands, so the meter reads as a verdict rather than a number.
const WK_PW_BANDS = [
    { label: 'Weak',   color: '#ef4444' },
    { label: 'Fair',   color: '#f59e0b' },
    { label: 'Good',   color: '#3b82f6' },
    { label: 'Strong', color: '#10b981' },
];

/**
 * Length is what actually makes a password hard to guess, so it carries the
 * most weight; variety helps but cannot rescue a short one. Predictable
 * shapes are marked down however well they otherwise score.
 */
function wkPwStrength(pw) {
    if (!pw) return { band: -1, hint: '' };

    // Length counts five times over, variety three: a long passphrase of
    // plain words is harder to guess than a short line of punctuation.
    let score = 0;
    [8, 12, 16, 20, 24].forEach(n => { if (pw.length >= n) score += 1; });

    const kinds = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^a-zA-Z0-9]/].filter(re => re.test(pw)).length;
    score += kinds - 1;

    let hint = '';
    // One character repeated, or a run straight off the keyboard.
    const repeated = /^(.)\1+$/.test(pw);
    const sequence = /012|123|234|345|456|567|678|789|abc|bcd|cde|def|qwe|wer|ert|asd|sdf/i.test(pw);
    const common   = /^(password|passw0rd|welcome|letmein|admin|iloveyou|monkey|dragon|football|qwerty|abc123)/i.test(pw);

    if (repeated || common) { score = 0; hint = 'That is one of the first passwords anyone would try.'; }
    else if (sequence)      { score -= 2; hint = 'Runs like “123” or “qwerty” are guessed early.'; }
    else if (pw.length < 12 && hint === '') hint = 'Longer beats more complicated — try a few words together.';

    const band = Math.max(0, Math.min(3, Math.floor(score / 2)));
    return { band: band, hint: hint };
}

function checkPw() {
    const pw = document.getElementById('regPw').value;
    const pw2 = document.getElementById('regPw2').value;
    const rules = { len: pw.length >= 8, num: /[0-9]/.test(pw), spc: /[^a-zA-Z0-9]/.test(pw) };

    ['len','num','spc'].forEach(k => {
        const el = document.getElementById('r-'+k);
        if (pw.length === 0) el.style.color = 'var(--wk-muted)';
        else el.style.color = rules[k] ? '#10b981' : '#ef4444';
    });

    const strength = wkPwStrength(pw);
    const segs = document.querySelectorAll('.wk-pw-seg');
    segs.forEach((seg, i) => {
        seg.style.background = (strength.band >= 0 && i <= strength.band)
            ? WK_PW_BANDS[strength.band].color
            : 'var(--wk-border)';
    });
    const label = document.getElementById('pwStrength');
    label.textContent = strength.band >= 0 ? WK_PW_BANDS[strength.band].label : '';
    label.style.color = strength.band >= 0 ? WK_PW_BANDS[strength.band].color : 'var(--wk-muted)';
    document.getElementById('pwHint').textContent = strength.hint;

    const match = document.getElementById('pwMatch');
    if (pw2.length === 0) match.innerHTML = '';
    else if (pw === pw2) match.innerHTML = '<span style="color:#10b981">✓ Passwords match</span>';
    else match.innerHTML = '<span style="color:#ef4444">✗ Passwords don\'t match</span>';

    const btn = document.getElementById('regSubmit');
    const allGood = rules.len && rules.num && rules.spc && pw === pw2 && pw2.length > 0;
    btn.disabled = !allGood;
    btn.style.opacity = allGood ? '1' : '.5';
}
</script>