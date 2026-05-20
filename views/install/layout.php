<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install Whisker — Step <?= $step ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        :root { --purple:#8b5cf6; --pink:#ec4899; --green:#10b981; --yellow:#f59e0b; --red:#ef4444;
            --bg:#faf8f6; --surface:#fff; --text:#1e1b2e; --muted:#6b7280; --border:#e5e2dc; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Nunito',sans-serif; background:var(--bg); color:var(--text); min-height:100vh;
            display:flex; flex-direction:column; align-items:center; justify-content:center; padding:24px;
            position:relative; overflow-x:hidden; }
        body::before { content:''; position:fixed; width:500px; height:500px; border-radius:50%; background:var(--purple);
            filter:blur(80px); opacity:.12; top:-150px; right:-100px; z-index:0; pointer-events:none; }
        body::after { content:''; position:fixed; width:400px; height:400px; border-radius:50%; background:var(--pink);
            filter:blur(80px); opacity:.12; bottom:-100px; left:-80px; z-index:0; pointer-events:none; }

        .installer { background:var(--surface); border:1px solid var(--border); border-radius:14px; width:100%; max-width:600px;
            box-shadow:0 20px 60px rgba(0,0,0,.06); position:relative; z-index:1;
            animation:cardPop .5s cubic-bezier(.34,1.56,.64,1); }
        @keyframes cardPop { from{opacity:0;transform:translateY(20px) scale(.97)} to{opacity:1;transform:translateY(0) scale(1)} }

        .installer-header { padding:32px 36px 24px; text-align:center; border-bottom:1px solid var(--border); }
        .installer-header h1 { font-size:22px; font-weight:900; }
        .installer-header h1 span { background:linear-gradient(135deg,var(--purple),var(--pink));
            -webkit-background-clip:text; -webkit-text-fill-color:transparent; }

        .steps { display:flex; justify-content:center; gap:6px; padding:18px 20px; border-bottom:1px solid var(--border); flex-wrap:wrap; }
        .step-circle { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center;
            font-size:12px; font-weight:800; border:2px solid var(--border); color:var(--muted); transition:all .3s; }
        .step-circle.active { background:var(--purple); border-color:var(--purple); color:#fff; box-shadow:0 3px 12px rgba(139,92,246,.3); }
        .step-circle.done { background:var(--green); border-color:var(--green); color:#fff; }
        .step-line { width:16px; height:2px; background:var(--border); align-self:center; }
        .step-line.done { background:var(--green); }

        .installer-body { padding:28px 36px 32px; }
        .step-title { font-size:18px; font-weight:800; margin-bottom:6px; }
        .step-desc { font-size:14px; color:var(--muted); margin-bottom:24px; line-height:1.5; }

        .field { margin-bottom:16px; }
        .field label { display:block; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.8px;
            color:var(--muted); margin-bottom:5px; }
        .field input, .field select { width:100%; padding:11px 14px; border:2px solid var(--border); border-radius:8px;
            font-family:'Nunito',sans-serif; font-size:14px; font-weight:600; color:var(--text); background:var(--bg);
            outline:none; transition:border-color .2s, box-shadow .2s; }
        .field input:focus, .field select:focus { border-color:var(--purple); box-shadow:0 0 0 4px rgba(139,92,246,.1); }
        .field-hint { font-size:11px; color:var(--muted); margin-top:3px; }
        .field-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }

        .btn { display:inline-flex; align-items:center; justify-content:center; gap:8px; padding:12px 24px; border-radius:8px;
            font-family:'Nunito',sans-serif; font-size:14px; font-weight:800; border:none; cursor:pointer; transition:all .2s; }
        .btn-primary { background:linear-gradient(135deg,var(--purple),var(--pink)); color:#fff;
            box-shadow:0 4px 15px rgba(139,92,246,.25); }
        .btn-primary:hover { transform:translateY(-1px); box-shadow:0 6px 25px rgba(139,92,246,.35); }
        .btn-secondary { background:var(--bg); color:var(--text); border:2px solid var(--border); }
        .btn-secondary:hover { border-color:var(--purple); color:var(--purple); }
        .btn-block { width:100%; }
        .btn-row { display:flex; gap:12px; margin-top:8px; }
        .btn-row .btn { flex:1; }

        .btn-test { background:var(--bg); color:var(--purple); border:2px solid var(--purple); font-size:13px; padding:9px 16px; }
        .btn-test:hover { background:rgba(139,92,246,.08); }

        .alert { padding:12px 16px; border-radius:8px; font-size:13px; font-weight:600; margin-bottom:16px; }
        .alert-error { background:#fef2f2; border:1px solid #fecaca; color:var(--red); animation:shake .4s ease; }
        .alert-success { background:#f0fdf4; border:1px solid #bbf7d0; color:var(--green); }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-4px)} 75%{transform:translateX(4px)} }

        .req-list { list-style:none; margin-bottom:16px; }
        .req-item { display:flex; align-items:center; gap:10px; padding:9px 0; border-bottom:1px solid var(--border); font-size:14px; font-weight:600; }
        .req-icon { width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; flex-shrink:0; }
        .req-pass .req-icon { background:#d1fae5; color:var(--green); }
        .req-fail .req-icon { background:#fee2e2; color:var(--red); }
        .req-fail { color:var(--red); }

        /* Password validation indicators */
        .pw-rules { display:flex; flex-direction:column; gap:4px; margin-top:6px; }
        .pw-rule { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:700; color:var(--muted); transition:color .2s; }
        .pw-rule.pass { color:var(--green); }
        .pw-rule.fail { color:var(--red); }
        .pw-dot { width:8px; height:8px; border-radius:50%; background:var(--border); transition:background .2s; flex-shrink:0; }
        .pw-rule.pass .pw-dot { background:var(--green); }
        .pw-rule.fail .pw-dot { background:var(--red); }

        /* Gateway selector */
        .gw-option { display:flex; align-items:center; gap:12px; padding:14px 16px; border:2px solid var(--border);
            border-radius:8px; cursor:pointer; transition:all .2s; margin-bottom:10px; }
        .gw-option:hover { border-color:var(--purple); }
        .gw-option.selected { border-color:var(--purple); background:rgba(139,92,246,.05); }
        .gw-option input[type="radio"] { accent-color:var(--purple); }
        .gw-fields { display:none; margin-top:12px; padding:16px; background:var(--bg); border-radius:8px; }
        .gw-fields.visible { display:block; animation:cardPop .3s ease; }

        .success-wrap { text-align:center; padding:20px 0; }
        .success-icon { width:80px; height:80px; border-radius:50%;
            background:linear-gradient(135deg,var(--green),#34d399); display:flex; align-items:center; justify-content:center;
            margin:0 auto 20px; font-size:36px; color:#fff; animation:popIn .5s cubic-bezier(.34,1.56,.64,1); }
        @keyframes popIn { from{transform:scale(0)} to{transform:scale(1)} }

        .installer-footer { text-align:center; padding:14px; font-size:12px; color:var(--muted); }

        @media(max-width:600px) { .installer-body{padding:20px 24px;} .field-row{grid-template-columns:1fr;} }
    </style>
</head>
<body>

<div class="installer">
    <div class="installer-header">
        <svg width="48" height="48" viewBox="0 0 56 56" fill="none" style="margin-bottom:12px">
            <circle cx="28" cy="28" r="26" fill="url(#cg)" stroke="url(#cs)" stroke-width="2"/>
            <path d="M16 10 L12 22 L22 18Z" fill="#8b5cf6"/><path d="M40 10 L44 22 L34 18Z" fill="#ec4899"/>
            <path d="M16.5 13 L14 20 L20 17.5Z" fill="#a78bfa"/><path d="M39.5 13 L42 20 L36 17.5Z" fill="#f472b6"/>
            <circle cx="21" cy="26" r="3.5" fill="#1e1b2e"/><circle cx="35" cy="26" r="3.5" fill="#1e1b2e"/>
            <circle cx="22" cy="25" r="1.2" fill="#fff"/><circle cx="36" cy="25" r="1.2" fill="#fff"/>
            <ellipse cx="28" cy="31" rx="2" ry="1.5" fill="#f472b6"/>
            <path d="M24 33 Q28 37 32 33" stroke="#1e1b2e" stroke-width="1.5" fill="none" stroke-linecap="round"/>
            <line x1="6" y1="26" x2="17" y2="28" stroke="#8b5cf6" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="6" y1="30" x2="17" y2="30" stroke="#a78bfa" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="6" y1="34" x2="17" y2="32" stroke="#c4b5fd" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="50" y1="26" x2="39" y2="28" stroke="#ec4899" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="50" y1="30" x2="39" y2="30" stroke="#f472b6" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="50" y1="34" x2="39" y2="32" stroke="#f9a8d4" stroke-width="1.5" stroke-linecap="round"/>
            <defs>
                <linearGradient id="cg" x1="0" y1="0" x2="56" y2="56"><stop offset="0%" stop-color="#faf8f6"/><stop offset="100%" stop-color="#f3f0eb"/></linearGradient>
                <linearGradient id="cs" x1="0" y1="0" x2="56" y2="56"><stop offset="0%" stop-color="#8b5cf6"/><stop offset="100%" stop-color="#ec4899"/></linearGradient>
            </defs>
        </svg>
        <h1><span>Whisker</span> Installer</h1>
    </div>

    <!-- Step Indicator -->
    <div class="steps">
        <?php for ($i = 1; $i <= 6; $i++): ?>
            <?php if ($i > 1): ?><div class="step-line <?= $i <= $step ? 'done' : '' ?>"></div><?php endif; ?>
            <div class="step-circle <?= $i < $step ? 'done' : ($i === $step ? 'active' : '') ?>">
                <?= $i < $step ? '✓' : $i ?>
            </div>
        <?php endfor; ?>
    </div>

    <div class="installer-body">
        <?php if ($error): ?><div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- ═══ STEP 1: Requirements ═══ -->
        <?php if ($step === 1): ?>
            <div class="step-title">System Requirements</div>
            <div class="step-desc">Let's make sure your server is ready for Whisker.</div>
            <ul class="req-list">
                <?php foreach ($requirements as [$label, $passed]): ?>
                    <li class="req-item <?= $passed ? 'req-pass' : 'req-fail' ?>">
                        <span class="req-icon"><?= $passed ? '✓' : '✗' ?></span><?= htmlspecialchars($label) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php if ($allPassed): ?>
                <?php
                $htaccessExists = file_exists(WK_ROOT . '/.htaccess') && str_contains(file_get_contents(WK_ROOT . '/.htaccess'), 'RewriteRule ^(.*)$ index.php');
                ?>
                <?php if (!$htaccessExists): ?>
                    <div class="alert alert-success" style="margin-bottom:12px">📝 The .htaccess file will be auto-generated in the next step to enable clean URLs.</div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['wk_htaccess_created'])): ?>
                    <div class="alert alert-success" style="margin-bottom:12px">✓ .htaccess was created automatically!</div>
                    <?php unset($_SESSION['wk_htaccess_created']); ?>
                <?php endif; ?>
                <form method="POST" action="?step=1"><input type="hidden" name="_install_csrf" value="<?= htmlspecialchars($csrfToken) ?>"><button type="submit" class="btn btn-primary btn-block">Everything looks great — Continue →</button></form>
            <?php else: ?>
                <div class="alert alert-error">Some requirements aren't met. Fix them and refresh.</div>
            <?php endif; ?>

        <!-- ═══ STEP 2: Database ═══ -->
        <?php elseif ($step === 2): ?>
            <div class="step-title">Database Connection</div>
            <div class="step-desc">Enter your MySQL credentials. We'll create the tables automatically.</div>
            <div id="dbTestResult"></div>
            <form method="POST" action="?step=2" id="dbForm">
                <input type="hidden" name="_install_csrf" id="install_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="field-row">
                    <div class="field"><label>Host</label><input type="text" name="db_host" id="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"></div>
                    <div class="field"><label>Port</label><input type="number" name="db_port" id="db_port" value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>"></div>
                </div>
                <div class="field"><label>Database Name</label><input type="text" name="db_name" id="db_name" value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>" required placeholder="whisker_cart">
                    <div class="field-hint">We'll create it if it doesn't exist.</div></div>
                <div class="field"><label>Username</label><input type="text" name="db_user" id="db_user" value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>" required placeholder="root"></div>
                <div class="field"><label>Password</label><input type="password" name="db_pass" id="db_pass"></div>
                <div style="display:flex;gap:12px;margin-top:8px">
                    <button type="button" class="btn btn-test" onclick="testDB()">🔌 Test Connection</button>
                    <div style="flex:1"></div>
                    <a href="?step=1" class="btn btn-secondary">← Back</a>
                    <button type="submit" class="btn btn-primary">Connect & Setup →</button>
                </div>
            </form>

        <!-- ═══ STEP 3: Store ═══ -->
        <?php elseif ($step === 3): ?>
            <div class="step-title">Your Store</div>
            <div class="step-desc">Give your store a name and tell us where it's installed.</div>
            <form method="POST" action="?step=3">
                <input type="hidden" name="_install_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="field"><label>Store Name</label><input type="text" name="store_name" required placeholder="My Awesome Store" autofocus value="<?= htmlspecialchars($_POST['store_name'] ?? '') ?>"></div>
                <div class="field"><label>Tagline <span style="font-weight:500;text-transform:none;letter-spacing:0">(optional)</span></label><input type="text" name="store_tagline" placeholder="Shop the things you love" value="<?= htmlspecialchars($_POST['store_tagline'] ?? '') ?>"></div>
                <div class="field">
                    <label>Store URL</label>
                    <input type="url" name="store_url" required placeholder="https://yourdomain.com" value="<?= htmlspecialchars($_POST['store_url'] ?? (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'))) ?>">
                    <div style="font-size:11px;color:var(--muted);margin-top:4px">
                        No trailing slash. Examples: <code>https://mystore.com</code> or <code>https://example.com/shop</code>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field"><label>Currency</label><select name="currency">
                        <?php foreach ($currencies as $code => $label): ?><option value="<?= $code ?>" <?= $code===($_POST['currency']??'INR')?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
                    </select></div>
                    <div class="field"><label>Timezone</label><select name="timezone">
                        <option value="Asia/Kolkata">Asia/Kolkata (IST)</option><option value="America/New_York">America/New York</option>
                        <option value="America/Los_Angeles">America/Los Angeles</option><option value="Europe/London">Europe/London</option>
                        <option value="Europe/Berlin">Europe/Berlin</option><option value="Asia/Tokyo">Asia/Tokyo</option>
                        <option value="Asia/Singapore">Asia/Singapore</option><option value="Australia/Sydney">Australia/Sydney</option>
                        <option value="UTC">UTC</option>
                    </select></div>
                </div>
                <div class="btn-row"><a href="?step=2" class="btn btn-secondary">← Back</a><button type="submit" class="btn btn-primary">Continue →</button></div>
            </form>

        <!-- ═══ STEP 4: Admin Account ═══ -->
        <?php elseif ($step === 4): ?>
            <div class="step-title">Create Admin Account</div>
            <div class="step-desc">This will be your login to manage everything.</div>
            <form method="POST" action="?step=4" id="adminForm">
                <input type="hidden" name="_install_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                <div class="field"><label>Username</label><input type="text" name="admin_user" required placeholder="admin" autofocus></div>
                <div class="field"><label>Email</label><input type="email" name="admin_email" required placeholder="you@example.com"></div>
                <div class="field">
                    <label>Password</label>
                    <input type="password" name="admin_pass" id="pw1" required placeholder="Min 8 chars, 1 number, 1 special" oninput="validatePw()">
                    <div class="pw-rules" id="pwRules">
                        <div class="pw-rule" id="rule-len"><span class="pw-dot"></span> At least 8 characters</div>
                        <div class="pw-rule" id="rule-num"><span class="pw-dot"></span> At least 1 number</div>
                        <div class="pw-rule" id="rule-spc"><span class="pw-dot"></span> At least 1 special character</div>
                    </div>
                </div>
                <div class="field">
                    <label>Confirm Password</label>
                    <input type="password" name="admin_pass2" id="pw2" required placeholder="Type it again" oninput="validatePw()">
                    <div id="pwMatch" style="font-size:12px;font-weight:700;margin-top:4px;min-height:18px"></div>
                </div>
                <div class="btn-row">
                    <a href="?step=3" class="btn btn-secondary">← Back</a>
                    <button type="submit" class="btn btn-primary" id="adminSubmit" disabled>Continue →</button>
                </div>
            </form>

        <!-- ═══ STEP 5: Gateway Quick Setup ═══ -->
        <?php elseif ($step === 5): ?>
            <div class="step-title">Payment Gateway</div>
            <div class="step-desc">Set up a payment gateway now, or skip and do it later from admin.</div>
            <form method="POST" action="?step=5">
                <input type="hidden" name="_install_csrf" value="<?= htmlspecialchars($csrfToken) ?>">
                <label class="gw-option" onclick="selectGw(this, '')" id="gw-skip">
                    <input type="radio" name="gateway" value="" checked>
                    <div><div style="font-weight:800">⏭ Skip for now</div><div style="font-size:12px;color:var(--muted)">Set up later in Admin → Payment Gateways</div></div>
                </label>
                <label class="gw-option" onclick="selectGw(this, 'razorpay')">
                    <input type="radio" name="gateway" value="razorpay">
                    <div><div style="font-weight:800">⚡ Razorpay</div><div style="font-size:12px;color:var(--muted)">UPI, Cards, Netbanking & Wallets</div></div>
                </label>
                <div class="gw-fields" id="fields-razorpay">
                    <div class="field"><label>Test Key ID</label><input type="text" name="gw_test_key_id" placeholder="rzp_test_..."></div>
                    <div class="field"><label>Test Key Secret</label><input type="password" name="gw_test_key_secret" placeholder="Enter secret"></div>
                </div>

                <label class="gw-option" onclick="selectGw(this, 'stripe')">
                    <input type="radio" name="gateway" value="stripe">
                    <div><div style="font-weight:800">💳 Stripe</div><div style="font-size:12px;color:var(--muted)">International cards — 195+ countries</div></div>
                </label>
                <div class="gw-fields" id="fields-stripe">
                    <div class="field"><label>Test Publishable Key</label><input type="text" name="gw_test_publishable_key" placeholder="pk_test_..."></div>
                    <div class="field"><label>Test Secret Key</label><input type="password" name="gw_test_secret_key" placeholder="sk_test_..."></div>
                </div>

                <label class="gw-option" onclick="selectGw(this, 'ccavenue')">
                    <input type="radio" name="gateway" value="ccavenue">
                    <div><div style="font-weight:800">🏦 CCAvenue</div><div style="font-size:12px;color:var(--muted)">India's largest payment gateway</div></div>
                </label>
                <div class="gw-fields" id="fields-ccavenue">
                    <div class="field"><label>Test Merchant ID</label><input type="text" name="gw_test_merchant_id"></div>
                    <div class="field"><label>Test Access Code</label><input type="text" name="gw_test_access_code"></div>
                    <div class="field"><label>Test Working Key</label><input type="password" name="gw_test_working_key"></div>
                </div>

                <label class="gw-option" onclick="selectGw(this, 'nowpayments')">
                    <input type="radio" name="gateway" value="nowpayments">
                    <div><div style="font-weight:800">₿ NOWPayments</div><div style="font-size:12px;color:var(--muted)">Bitcoin, Ethereum & 300+ crypto</div></div>
                </label>
                <div class="gw-fields" id="fields-nowpayments">
                    <div class="field"><label>Sandbox API Key</label><input type="password" name="gw_test_api_key"></div>
                </div>

                <div class="btn-row" style="margin-top:16px">
                    <a href="?step=4" class="btn btn-secondary">← Back</a>
                    <button type="submit" class="btn btn-primary">Install Whisker 🚀</button>
                </div>
            </form>

        <!-- ═══ STEP 6: Complete ═══ -->
        <?php elseif ($step === 6): ?>
            <div class="success-wrap">
                <div class="success-icon">🐱</div>
                <h2 style="font-size:24px;font-weight:900;margin-bottom:8px">Whisker is installed!</h2>
                <p style="color:var(--muted);margin-bottom:24px;line-height:1.6">Your store is ready. Add products, configure payments, and start selling!</p>
                <div style="display:flex;gap:12px;justify-content:center;margin-bottom:24px">
                    <a href="/" class="btn btn-secondary">View Store</a>
                    <a href="/admin" class="btn btn-primary">Go to Admin Panel →</a>
                </div>
                <div style="background:linear-gradient(135deg,#8b5cf6,#ec4899);border-radius:12px;padding:20px;color:#fff;text-align:center;margin-top:16px">
                    <h3 style="margin:0 0 6px;font-size:16px">Need Custom Features?</h3>
                    <p style="margin:0 0 12px;opacity:.9;font-size:13px;line-height:1.5">Payment integrations, custom themes, APIs, multi-vendor, analytics — delivered as a ready-to-deploy package.</p>
                    <a href="mailto:mail@lohit.me" style="display:inline-block;background:#fff;color:#8b5cf6;padding:8px 20px;border-radius:8px;font-weight:800;text-decoration:none;font-size:13px">📧 mail@lohit.me — Get a Quote</a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="installer-footer">Whisker v1.0.0 · Free Edition · <a href="mailto:mail@lohit.me" style="color:var(--purple);text-decoration:none">mail@lohit.me</a></div>
</div>

<script>
// ── Test DB Connection ───────────────────────
async function testDB() {
    const result = document.getElementById('dbTestResult');
    result.innerHTML = '<div class="alert" style="background:#ede9fe;border:1px solid #c4b5fd;color:var(--purple)">🔌 Testing connection...</div>';

    const form = new FormData();
    form.append('_ajax_action', 'test_db');
    form.append('_install_csrf', document.getElementById('install_csrf').value);
    form.append('db_host', document.getElementById('db_host').value);
    form.append('db_port', document.getElementById('db_port').value);
    form.append('db_name', document.getElementById('db_name').value);
    form.append('db_user', document.getElementById('db_user').value);
    form.append('db_pass', document.getElementById('db_pass').value);

    try {
        const res = await fetch('', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            result.innerHTML = '<div class="alert alert-success">✓ ' + data.message + '</div>';
        } else {
            result.innerHTML = '<div class="alert alert-error">✗ ' + data.message + '</div>';
        }
    } catch (e) {
        result.innerHTML = '<div class="alert alert-error">✗ Request failed. Check your server.</div>';
    }
}

// ── Live Password Validation ─────────────────
function validatePw() {
    const pw = document.getElementById('pw1')?.value || '';
    const pw2 = document.getElementById('pw2')?.value || '';
    const submit = document.getElementById('adminSubmit');

    const rules = {
        len: pw.length >= 8,
        num: /[0-9]/.test(pw),
        spc: /[^a-zA-Z0-9]/.test(pw),
    };

    for (const [key, passed] of Object.entries(rules)) {
        const el = document.getElementById('rule-' + key);
        if (el) {
            el.className = 'pw-rule ' + (pw.length === 0 ? '' : (passed ? 'pass' : 'fail'));
        }
    }

    // Match indicator
    const matchEl = document.getElementById('pwMatch');
    if (matchEl) {
        if (pw2.length === 0) {
            matchEl.innerHTML = '';
        } else if (pw === pw2) {
            matchEl.innerHTML = '<span style="color:var(--green)">✓ Passwords match</span>';
        } else {
            matchEl.innerHTML = '<span style="color:var(--red)">✗ Passwords don\'t match</span>';
        }
    }

    // Enable/disable submit
    const allPass = rules.len && rules.num && rules.spc && pw === pw2 && pw2.length > 0;
    if (submit) submit.disabled = !allPass;
}

// ── Gateway Selector ─────────────────────────
function selectGw(el, code) {
    document.querySelectorAll('.gw-option').forEach(o => o.classList.remove('selected'));
    document.querySelectorAll('.gw-fields').forEach(f => f.classList.remove('visible'));
    el.classList.add('selected');
    if (code) {
        const fields = document.getElementById('fields-' + code);
        if (fields) fields.classList.add('visible');
    }
}
// Init skip as selected
document.getElementById('gw-skip')?.classList.add('selected');
</script>

<!-- Confetti on step 6 -->
<?php if ($step === 6): ?>
<script>
(function(){
    const cols=['#8b5cf6','#ec4899','#10b981','#f59e0b','#60a5fa'];
    const c=document.createElement('canvas'); c.style.cssText='position:fixed;inset:0;z-index:0;pointer-events:none';
    document.body.prepend(c); const ctx=c.getContext('2d'); c.width=innerWidth; c.height=innerHeight;
    const p=[];for(let i=0;i<120;i++)p.push({x:Math.random()*c.width,y:-Math.random()*c.height,w:4+Math.random()*6,h:3+Math.random()*4,
        color:cols[Math.floor(Math.random()*cols.length)],vy:2+Math.random()*3,vx:(Math.random()-.5)*2,
        rot:Math.random()*360,vr:(Math.random()-.5)*8,op:1});
    let f=0;(function draw(){ctx.clearRect(0,0,c.width,c.height);let alive=false;
        p.forEach(q=>{if(q.op<=0)return;alive=true;q.x+=q.vx;q.y+=q.vy;q.rot+=q.vr;q.vy+=.04;
        if(q.y>c.height+20)q.op-=.02;ctx.save();ctx.translate(q.x,q.y);ctx.rotate(q.rot*Math.PI/180);
        ctx.globalAlpha=Math.max(0,q.op);ctx.fillStyle=q.color;ctx.fillRect(-q.w/2,-q.h/2,q.w,q.h);ctx.restore()});
        if(alive&&f<300){f++;requestAnimationFrame(draw)}else c.remove()})();
})();
</script>
<?php endif; ?>

</body>
</html>