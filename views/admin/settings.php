<?php $url=fn($p)=>\Core\View::url($p); $s=$settings; $v=fn($g,$k)=>htmlspecialchars($s[$g][$k]??''); ?>

<!-- Tab Navigation -->
<div style="display:flex;gap:6px;margin-bottom:24px;border-bottom:2px solid var(--wk-border);padding-bottom:0;flex-wrap:wrap">
    <?php $tabs = ['store'=>'🏪 Store','appearance'=>'🎨 Appearance','checkout'=>'🛒 Checkout & Tax','email'=>'📧 Email','system'=>'⚙️ System'];
    foreach ($tabs as $key => $label): ?>
    <button onclick="switchTab('<?= $key ?>')" id="tab-btn-<?= $key ?>" style="padding:10px 20px;border:none;background:transparent;font-weight:700;font-size:13px;color:var(--wk-text-muted);cursor:pointer;border-bottom:3px solid transparent;margin-bottom:-2px;transition:all .2s;font-family:inherit"><?= $label ?></button>
    <?php endforeach; ?>
</div>

<form method="POST" action="<?= $url('admin/settings/update') ?>" id="settingsForm">
    <?= \Core\Session::csrfField() ?>

    <!-- TAB: Store -->
    <div class="settings-tab" id="tab-store">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div class="wk-card">
                <div class="wk-card-header"><h2>Store Details</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>Store Name</label><input type="text" name="general_site_name" class="wk-input" value="<?= $v('general','site_name') ?>"></div>
                    <div class="wk-form-group"><label>Tagline</label><input type="text" name="general_site_tagline" class="wk-input" value="<?= $v('general','site_tagline') ?>"></div>
                    <div class="wk-form-group"><label>Store URL</label><input type="url" name="general_site_url" class="wk-input" value="<?= $v('general','site_url') ?>"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <div class="wk-form-group"><label>Currency Code</label><input type="text" name="general_currency" class="wk-input" value="<?= $v('general','currency') ?>" placeholder="INR"></div>
                        <div class="wk-form-group"><label>Currency Symbol</label><input type="text" name="general_currency_symbol" class="wk-input" value="<?= $v('general','currency_symbol') ?>" placeholder="₹"></div>
                    </div>
                    <div class="wk-form-group"><label>Timezone</label><input type="text" name="general_timezone" class="wk-input" value="<?= $v('general','timezone') ?>"></div>
                    <div class="wk-form-group"><label>Contact Form Email</label><input type="email" name="general_contact_email" class="wk-input" value="<?= $v('general','contact_email') ?>" placeholder="support@yourstore.com"></div>
                </div>
            </div>
            <div class="wk-card">
                <div class="wk-card-header"><h2>Business Info (Invoices)</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>Store Phone</label><input type="text" name="general_store_phone" class="wk-input" value="<?= $v('general','store_phone') ?>" placeholder="+91 98765 43210"></div>
                    <div class="wk-form-group"><label>Store Address</label><textarea name="general_store_address" class="wk-input" rows="3" placeholder="123 Main Street&#10;City, State 560001&#10;Country"><?= $v('general','store_address') ?></textarea></div>
                    <div class="wk-form-group"><label>GSTIN / VAT / Tax ID</label><input type="text" name="general_store_tax_id" class="wk-input" value="<?= $v('general','store_tax_id') ?>" placeholder="e.g. 29ABCDE1234F1Z5"><div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">Shown on invoices</div></div>
                    <div class="wk-form-group"><label>Store Logo URL</label><input type="text" name="general_store_logo" class="wk-input" value="<?= $v('general','store_logo') ?>" placeholder="https://yourstore.com/logo.png"><div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">Shown on invoices</div></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <div class="wk-form-group"><label>Chatbot Name</label><input type="text" name="general_chatbot_name" class="wk-input" value="<?= $v('general','chatbot_name') ?>" placeholder="Whisker Bot"></div>
                        <div class="wk-form-group"><label>Chatbot</label><select name="general_chatbot_enabled" class="wk-select"><option value="1" <?= ($s['general']['chatbot_enabled']??'1')==='1'?'selected':'' ?>>Enabled</option><option value="0" <?= ($s['general']['chatbot_enabled']??'1')==='0'?'selected':'' ?>>Disabled</option></select></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: Appearance -->
    <div class="settings-tab" id="tab-appearance" style="display:none">
        <div class="wk-card" style="margin-bottom:20px">
            <div class="wk-card-header"><h2>🎨 Store Theme</h2></div>
            <div class="wk-card-body">
                <?php $currentTheme = $v('general','store_theme') ?: 'purple';
                $themes = [
                    'purple'  => ['Whisker Purple', '#8b5cf6', '#ec4899', '#faf8f6', 'Purple & pink gradient, light cream background'],
                    'ocean'   => ['Ocean Blue', '#0ea5e9', '#06b6d4', '#f0f9ff', 'Sky blue with teal accents'],
                    'forest'  => ['Forest Green', '#16a34a', '#ca8a04', '#f7faf5', 'Green with amber accents'],
                    'sunset'  => ['Sunset Orange', '#f97316', '#e11d48', '#fffbf5', 'Orange with coral accents'],
                    'midnight'=> ['Midnight Dark', '#a78bfa', '#f472b6', '#0f0e17', 'Dark mode, vibrant accents'],
                ]; ?>
                <div style="display:grid;grid-template-columns:1fr;gap:10px">
                    <?php foreach ($themes as $key => [$name, $c1, $c2, $bg, $desc]): ?>
                    <label style="display:flex;align-items:center;gap:14px;padding:14px;border:2px solid <?= $currentTheme===$key?'var(--wk-purple)':'var(--wk-border)' ?>;border-radius:10px;cursor:pointer;transition:all .2s;background:<?= $currentTheme===$key?'var(--wk-purple-soft)':'transparent' ?>" onclick="this.parentElement.querySelectorAll('label').forEach(l=>{l.style.borderColor='var(--wk-border)';l.style.background='transparent'});this.style.borderColor='var(--wk-purple)';this.style.background='var(--wk-purple-soft)'">
                        <input type="radio" name="general_store_theme" value="<?= $key ?>" <?= $currentTheme===$key?'checked':'' ?> style="accent-color:var(--wk-purple)">
                        <div style="display:flex;gap:4px;flex-shrink:0">
                            <div style="width:24px;height:24px;border-radius:6px;background:<?= $c1 ?>;border:2px solid rgba(0,0,0,.1)"></div>
                            <div style="width:24px;height:24px;border-radius:6px;background:<?= $c2 ?>;border:2px solid rgba(0,0,0,.1)"></div>
                            <div style="width:24px;height:24px;border-radius:6px;background:<?= $bg ?>;border:2px solid rgba(0,0,0,.15)"></div>
                        </div>
                        <div style="flex:1">
                            <div style="font-weight:800;font-size:14px"><?= $name ?></div>
                            <div style="font-size:11px;color:var(--wk-text-muted)"><?= $desc ?></div>
                        </div>
                        <?php if ($currentTheme===$key): ?><span class="wk-badge wk-badge-success">Active</span><?php endif; ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="wk-card">
            <div class="wk-card-header"><h2>🏠 Homepage Layout</h2></div>
            <div class="wk-card-body">
                <?php $currentLayout = $v('general','homepage_style') ?: 'v2'; ?>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <label style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:20px;border:2px solid <?= $currentLayout==='v1'?'var(--wk-purple)':'var(--wk-border)' ?>;border-radius:12px;cursor:pointer;transition:all .2s;background:<?= $currentLayout==='v1'?'var(--wk-purple-soft)':'transparent' ?>" onclick="this.parentElement.querySelectorAll('label').forEach(l=>{l.style.borderColor='var(--wk-border)';l.style.background='transparent'});this.style.borderColor='var(--wk-purple)';this.style.background='var(--wk-purple-soft)'">
                        <input type="radio" name="general_homepage_style" value="v1" <?= $currentLayout==='v1'?'checked':'' ?> style="accent-color:var(--wk-purple)">
                        <div style="font-size:32px">🏪</div>
                        <div style="text-align:center">
                            <div style="font-weight:800;font-size:14px">Classic</div>
                            <div style="font-size:11px;color:var(--wk-text-muted)">Simple hero, featured carousel, product grid</div>
                        </div>
                    </label>
                    <label style="display:flex;flex-direction:column;align-items:center;gap:10px;padding:20px;border:2px solid <?= $currentLayout==='v2'?'var(--wk-purple)':'var(--wk-border)' ?>;border-radius:12px;cursor:pointer;transition:all .2s;background:<?= $currentLayout==='v2'?'var(--wk-purple-soft)':'transparent' ?>" onclick="this.parentElement.querySelectorAll('label').forEach(l=>{l.style.borderColor='var(--wk-border)';l.style.background='transparent'});this.style.borderColor='var(--wk-purple)';this.style.background='var(--wk-purple-soft)'">
                        <input type="radio" name="general_homepage_style" value="v2" <?= $currentLayout==='v2'?'checked':'' ?> style="accent-color:var(--wk-purple)">
                        <div style="font-size:32px">✨</div>
                        <div style="text-align:center">
                            <div style="font-weight:800;font-size:14px">Modern</div>
                            <div style="font-size:11px;color:var(--wk-text-muted)">Hero banner, category grid, sale section, carousel</div>
                        </div>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: Checkout & Tax -->
    <div class="settings-tab" id="tab-checkout" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div class="wk-card">
                <div class="wk-card-header"><h2>🛒 Checkout</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>Minimum Order Amount</label><input type="number" step="0.01" name="checkout_min_order" class="wk-input" value="<?= $v('checkout','min_order') ?>"></div>
                    <div class="wk-form-group"><label>Guest Checkout</label>
                        <select name="checkout_guest_checkout" class="wk-input">
                            <option value="1" <?= $v('checkout','guest_checkout')!=='0'?'selected':'' ?>>Enabled</option>
                            <option value="0" <?= $v('checkout','guest_checkout')==='0'?'selected':'' ?>>Disabled</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="wk-card">
                <div class="wk-card-header"><h2>🌍 Store Location</h2></div>
                <div class="wk-card-body">
                    <?php $currentCountry = $v('general','store_country') ?: 'IN';
                    $countries = ['IN'=>'India','US'=>'United States','GB'=>'United Kingdom','DE'=>'Germany','FR'=>'France','IT'=>'Italy','ES'=>'Spain','NL'=>'Netherlands','BE'=>'Belgium','AT'=>'Austria','PL'=>'Poland','PT'=>'Portugal','SE'=>'Sweden','DK'=>'Denmark','FI'=>'Finland','IE'=>'Ireland','GR'=>'Greece','CZ'=>'Czech Republic','RO'=>'Romania','HU'=>'Hungary','BG'=>'Bulgaria','HR'=>'Croatia','SK'=>'Slovakia','SI'=>'Slovenia','LT'=>'Lithuania','LV'=>'Latvia','EE'=>'Estonia','CY'=>'Cyprus','LU'=>'Luxembourg','MT'=>'Malta','AU'=>'Australia','CA'=>'Canada','JP'=>'Japan','SG'=>'Singapore','AE'=>'UAE','SA'=>'Saudi Arabia','BR'=>'Brazil','MX'=>'Mexico','ZA'=>'South Africa','NG'=>'Nigeria','KE'=>'Kenya']; ?>
                    <div class="wk-form-group"><label>Store Country</label>
                        <select name="general_store_country" class="wk-input">
                            <?php foreach ($countries as $code => $name): ?>
                            <option value="<?= $code ?>" <?= $currentCountry===$code?'selected':'' ?>><?= $name ?> (<?= $code ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="wk-form-group"><label>Store State / Province</label><input type="text" name="general_store_state" class="wk-input" value="<?= $v('general','store_state') ?>" placeholder="e.g. KA, CA, TX"><div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">For GST split and US sales tax nexus</div></div>
                    <div class="wk-form-group"><label>Fallback Tax Rate (%)</label><input type="number" step="0.01" name="checkout_tax_rate" class="wk-input" value="<?= $v('checkout','tax_rate') ?>" style="max-width:200px"><div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">Used when no country-specific rate applies</div></div>
                </div>
            </div>
        </div>

        <div class="wk-card" style="margin-top:20px">
            <div class="wk-card-header"><h2>Built-in Tax Rules</h2></div>
            <div class="wk-card-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">
                    <div style="padding:12px;background:var(--wk-bg);border-radius:8px"><div style="font-weight:700;color:var(--wk-purple)">🇮🇳 India (GST)</div><div style="color:var(--wk-text-muted);font-size:12px;margin-top:4px">18% / 12% / 5% · Auto CGST+SGST or IGST</div></div>
                    <div style="padding:12px;background:var(--wk-bg);border-radius:8px"><div style="font-weight:700;color:var(--wk-purple)">🇪🇺 EU (VAT)</div><div style="color:var(--wk-text-muted);font-size:12px;margin-top:4px">27 countries · Standard & reduced rates</div></div>
                    <div style="padding:12px;background:var(--wk-bg);border-radius:8px"><div style="font-weight:700;color:var(--wk-purple)">🇬🇧 UK (VAT)</div><div style="color:var(--wk-text-muted);font-size:12px;margin-top:4px">20% standard · 5% reduced</div></div>
                    <div style="padding:12px;background:var(--wk-bg);border-radius:8px"><div style="font-weight:700;color:var(--wk-purple)">🇺🇸 US (Sales Tax)</div><div style="color:var(--wk-text-muted);font-size:12px;margin-top:4px">50 states · Nexus-based</div></div>
                </div>
                <?php
                try { $customRates = \Core\Database::fetchAll("SELECT * FROM wk_tax_rates WHERE is_active=1 ORDER BY priority DESC, country, state"); } catch (\Exception $e) { $customRates = []; }
                if (!empty($customRates)): ?>
                <div style="margin-top:16px;font-weight:700;font-size:13px">Custom Rates</div>
                <table class="wk-table" style="font-size:13px;margin-top:8px">
                    <thead><tr><th>Country</th><th>State</th><th>Class</th><th>Rate</th><th>Label</th></tr></thead>
                    <tbody><?php foreach ($customRates as $rate): ?>
                    <tr><td><?= $rate['country'] ?: 'All' ?></td><td><?= $rate['state'] ?: 'All' ?></td><td><?= $rate['tax_class'] ?></td><td><?= $rate['rate'] ?>%</td><td><?= htmlspecialchars($rate['label']) ?></td></tr>
                    <?php endforeach; ?></tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- TAB: Email -->
    <div class="settings-tab" id="tab-email" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div class="wk-card">
                <div class="wk-card-header"><h2>📧 Email Settings</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group"><label>From Email</label><input type="email" name="email_from_email" class="wk-input" value="<?= $v('email','from_email') ?>"></div>
                    <div class="wk-form-group"><label>From Name</label><input type="text" name="email_from_name" class="wk-input" value="<?= $v('email','from_name') ?>"></div>
                </div>
            </div>
            <div class="wk-card">
                <div class="wk-card-header"><h2>📮 SMTP <span style="font-weight:500;font-size:12px;color:var(--wk-text-muted)">(optional)</span></h2></div>
                <div class="wk-card-body">
                    <p style="font-size:12px;color:var(--wk-text-muted);margin-bottom:14px">Leave empty to use PHP's built-in mail(). Fill in for reliable SMTP delivery.</p>
                    <div class="wk-form-group"><label>SMTP Host</label><input type="text" name="email_smtp_host" id="smtpHost" class="wk-input" value="<?= $v('email','smtp_host') ?>" placeholder="smtp.gmail.com"></div>
                    <div class="wk-form-group"><label>SMTP Port</label><input type="number" name="email_smtp_port" id="smtpPort" class="wk-input" value="<?= $v('email','smtp_port') ?>" placeholder="587"></div>
                    <div class="wk-form-group"><label>SMTP Username</label><input type="text" name="email_smtp_user" id="smtpUser" class="wk-input" value="<?= $v('email','smtp_user') ?>"></div>
                    <div class="wk-form-group"><label>SMTP Password</label><input type="password" name="email_smtp_pass" id="smtpPass" class="wk-input" value="<?= $v('email','smtp_pass') ?>"></div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button type="button" onclick="testSmtpConnection()" id="testConnBtn" class="wk-btn wk-btn-secondary" style="justify-content:center">🔌 Test Connection</button>
                        <button type="button" onclick="sendTestEmail()" id="testEmailBtn" class="wk-btn wk-btn-secondary" style="justify-content:center">📧 Send Test Email</button>
                    </div>
                    <div id="smtpTestResult" style="margin-top:10px;font-size:13px"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: System -->
    <div class="settings-tab" id="tab-system" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
            <div class="wk-card">
                <div class="wk-card-header"><h2>🔄 System Update</h2></div>
                <div class="wk-card-body">
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                        <div>
                            <div style="font-weight:700;font-size:14px">Current Version: v<?= WK_VERSION ?></div>
                            <div style="font-size:12px;color:var(--wk-text-muted);margin-top:2px">Check if a newer version is available</div>
                        </div>
                        <button type="button" id="checkUpdateBtn" onclick="checkForUpdate()" class="wk-btn wk-btn-secondary wk-btn-sm">🔍 Check for Updates</button>
                    </div>
                    <div id="updateResult" style="margin-top:12px"></div>
                </div>
            </div>
            <div class="wk-card">
                <div class="wk-card-header"><h2>🔐 Change Password</h2></div>
                <div class="wk-card-body">
                    <div style="font-size:12px;color:var(--wk-text-muted);margin-bottom:14px">Change your admin password. You'll stay signed in.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Save Button (sticky) -->
    <div style="position:sticky;bottom:0;background:var(--wk-bg);padding:16px 0;border-top:2px solid var(--wk-border);margin-top:24px;z-index:10">
        <button type="submit" class="wk-btn wk-btn-primary" style="justify-content:center;min-width:200px">💾 Save Settings</button>
    </div>
</form>

<!-- Change Password (separate form, inside System tab) -->
<form method="POST" action="<?= $url('admin/settings/change-password') ?>" id="changePasswordForm" style="display:none">
    <?= \Core\Session::csrfField() ?>
    <div class="wk-card" style="max-width:440px">
        <div class="wk-card-body">
            <div class="wk-form-group"><label>Current Password</label><input type="password" name="current_password" class="wk-input" required></div>
            <div class="wk-form-group"><label>New Password</label><input type="password" name="new_password" class="wk-input" required minlength="8"></div>
            <div class="wk-form-group"><label>Confirm New Password</label><input type="password" name="confirm_password" class="wk-input" required minlength="8"></div>
            <button type="submit" class="wk-btn wk-btn-primary" style="justify-content:center" onclick="return confirm('Change your admin password?')">Change Password</button>
        </div>
    </div>
</form>

<script>
// Tab switching
function switchTab(tab) {
    document.querySelectorAll('.settings-tab').forEach(t => t.style.display = 'none');
    document.querySelectorAll('[id^="tab-btn-"]').forEach(b => { b.style.borderBottomColor = 'transparent'; b.style.color = 'var(--wk-text-muted)'; });
    const el = document.getElementById('tab-' + tab);
    if (el) el.style.display = 'block';
    const btn = document.getElementById('tab-btn-' + tab);
    if (btn) { btn.style.borderBottomColor = 'var(--wk-purple)'; btn.style.color = 'var(--wk-text)'; }
    // Show change password form only on system tab
    const cpf = document.getElementById('changePasswordForm');
    if (cpf) cpf.style.display = tab === 'system' ? 'block' : 'none';
    localStorage.setItem('wk_settings_tab', tab);
}
// Restore last tab
const savedTab = localStorage.getItem('wk_settings_tab') || 'store';
switchTab(savedTab);

// Update check
async function checkForUpdate() {
    const btn = document.getElementById('checkUpdateBtn');
    const result = document.getElementById('updateResult');
    btn.disabled = true; btn.textContent = 'Checking...';
    result.innerHTML = '<span style="color:var(--wk-text-muted)">Contacting update server...</span>';
    try {
        const res = await fetch('<?= $url('admin/update/check') ?>', {method:'POST', headers:{'X-CSRF-Token':'<?= \Core\Session::csrfToken() ?>'}});
        const data = await res.json();
        if (data.available) {
            result.innerHTML = '<div style="background:var(--wk-purple-soft);border:2px solid var(--wk-purple);border-radius:10px;padding:14px;margin-top:8px">'
                + '<div style="font-weight:800;font-size:14px">Whisker v' + data.version + ' is available!</div>'
                + (data.changelog ? '<div style="font-size:13px;color:var(--wk-text-muted);margin-top:6px">' + data.changelog.substring(0,200) + '</div>' : '')
                + '<div style="margin-top:10px"><a href="<?= $url('admin') ?>" class="wk-btn wk-btn-primary wk-btn-sm">Go to Dashboard to Update →</a></div></div>';
        } else {
            result.innerHTML = '<span style="color:var(--wk-green);font-weight:700">✅ You are on the latest version (v<?= WK_VERSION ?>)</span>';
        }
    } catch(e) { result.innerHTML = '<span style="color:var(--wk-red)">❌ Could not reach update server.</span>'; }
    btn.disabled = false; btn.textContent = '🔍 Check for Updates';
}

// SMTP tests
async function testSmtpConnection() {
    const btn = document.getElementById('testConnBtn');
    const result = document.getElementById('smtpTestResult');
    const host = document.getElementById('smtpHost').value.trim();
    if (!host) { result.innerHTML = '<span style="color:var(--wk-red)">Enter SMTP host first</span>'; return; }
    btn.disabled = true; btn.textContent = 'Testing...'; result.innerHTML = '';
    const form = new FormData();
    form.append('action', 'test_connection');
    form.append('host', host); form.append('port', document.getElementById('smtpPort').value || '587');
    form.append('user', document.getElementById('smtpUser').value); form.append('pass', document.getElementById('smtpPass').value);
    try {
        const res = await fetch('<?= $url('admin/settings/test-smtp') ?>', {method:'POST', body:form});
        const data = await res.json();
        result.innerHTML = data.success ? '<span style="color:var(--wk-green)">✅ ' + data.message + '</span>' : '<span style="color:var(--wk-red)">❌ ' + data.message + '</span>';
    } catch(e) { result.innerHTML = '<span style="color:var(--wk-red)">❌ Network error</span>'; }
    btn.disabled = false; btn.textContent = '🔌 Test Connection';
}
async function sendTestEmail() {
    const btn = document.getElementById('testEmailBtn');
    const result = document.getElementById('smtpTestResult');
    const email = prompt('Send test email to:', document.querySelector('input[name="email_from_email"]')?.value || '');
    if (!email) return;
    btn.disabled = true; btn.textContent = 'Sending...'; result.innerHTML = '';
    const form = new FormData();
    form.append('action', 'test_email'); form.append('to', email);
    form.append('host', document.getElementById('smtpHost').value);
    form.append('port', document.getElementById('smtpPort').value || '587');
    form.append('user', document.getElementById('smtpUser').value);
    form.append('pass', document.getElementById('smtpPass').value);
    try {
        const res = await fetch('<?= $url('admin/settings/test-smtp') ?>', {method:'POST', body:form});
        const data = await res.json();
        result.innerHTML = data.success ? '<span style="color:var(--wk-green)">✅ ' + data.message + '</span>' : '<span style="color:var(--wk-red)">❌ ' + data.message + '</span>';
    } catch(e) { result.innerHTML = '<span style="color:var(--wk-red)">❌ Network error</span>'; }
    btn.disabled = false; btn.textContent = '📧 Send Test Email';
}
</script>
