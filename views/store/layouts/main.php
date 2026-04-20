<?php
$url = fn($p) => \Core\View::url($p);
$e = fn($v) => \Core\View::e($v);
$siteName = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker Store';
$logoUrl = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='logo_url'");

// Load parent categories with their children
$allCats = \Core\Database::fetchAll("SELECT id, name, slug, parent_id FROM wk_categories WHERE is_active=1 ORDER BY sort_order, name");
$parentCats = [];
$childMap = [];
foreach ($allCats as $cat) {
    if ($cat['parent_id']) {
        $childMap[$cat['parent_id']][] = $cat;
    } else {
        $parentCats[] = $cat;
    }
}

$isLoggedIn = \Core\Session::customerId() !== null;
$customer = $isLoggedIn ? \Core\Database::fetch("SELECT first_name FROM wk_customers WHERE id=?", [\Core\Session::customerId()]) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="wk-base" content="<?= \Core\View::url('') ?>">
    <?php if (!empty($seoMeta)): ?>
    <?= $seoMeta ?>
    <?php else: ?>
    <title><?= $e($pageTitle ?? $siteName) ?></title>
    <?php endif; ?>
    <?= $productSchema ?? '' ?>
    <link rel="icon" type="image/svg+xml" href="<?= \Core\View::asset('img/favicon.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= \Core\View::asset('css/store.css') ?>">
</head>
<?php $storeTheme = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='store_theme'") ?: 'purple'; ?>
<body data-theme="<?= htmlspecialchars($storeTheme) ?>">

<!-- Page Loader -->
<div class="wk-page-loader">
    <svg width="40" height="40" viewBox="0 0 56 56" fill="none">
        <circle cx="28" cy="28" r="26" fill="#faf8f6" stroke="url(#pl)" stroke-width="2"/>
        <path d="M16 10 L12 22 L22 18Z" fill="#8b5cf6"/><path d="M40 10 L44 22 L34 18Z" fill="#ec4899"/>
        <circle cx="21" cy="26" r="3" fill="#1e1b2e"/><circle cx="35" cy="26" r="3" fill="#1e1b2e"/>
        <ellipse cx="28" cy="31" rx="2" ry="1.5" fill="#f472b6"/>
        <defs><linearGradient id="pl" x1="0" y1="0" x2="56" y2="56"><stop offset="0%" stop-color="#8b5cf6"/><stop offset="100%" stop-color="#ec4899"/></linearGradient></defs>
    </svg>
    <div class="wk-loader-bar"></div>
</div>

<!-- Header -->
<header class="wk-header">
    <div class="wk-header-inner">
        <a href="<?= $url('') ?>" class="wk-logo">
            <?php if ($logoUrl): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= $e($siteName) ?>" style="max-height:32px;max-width:160px">
            <?php else: ?>
            <svg width="28" height="28" viewBox="0 0 56 56" fill="none">
                <circle cx="28" cy="28" r="26" fill="#faf8f6" stroke="url(#hl)" stroke-width="2"/>
                <path d="M16 10 L12 22 L22 18Z" fill="#8b5cf6"/><path d="M40 10 L44 22 L34 18Z" fill="#ec4899"/>
                <circle cx="21" cy="26" r="2.5" fill="#1e1b2e"/><circle cx="35" cy="26" r="2.5" fill="#1e1b2e"/>
                <ellipse cx="28" cy="31" rx="2" ry="1.5" fill="#f472b6"/>
                <defs><linearGradient id="hl" x1="0" y1="0" x2="56" y2="56"><stop offset="0%" stop-color="#8b5cf6"/><stop offset="100%" stop-color="#ec4899"/></linearGradient></defs>
            </svg>
            <span class="wk-logo-text"><?= $e($siteName) ?></span>
            <?php endif; ?>
        </a>

        <nav class="wk-header-nav">
            <a href="<?= $url('') ?>">Home</a>
            <a href="<?= $url('shop') ?>">Shop All</a>
            <?php foreach (array_slice($parentCats, 0, 6) as $cat):
                $children = $childMap[$cat['id']] ?? [];
            ?>
                <?php if (!empty($children)): ?>
                <div class="wk-nav-dropdown">
                    <a href="<?= $url('category/' . $cat['slug']) ?>" class="wk-nav-dropdown-trigger"><?= $e($cat['name']) ?> <span style="font-size:9px;opacity:.5">▼</span></a>
                    <div class="wk-nav-dropdown-menu">
                        <a href="<?= $url('category/' . $cat['slug']) ?>" style="font-weight:800;color:var(--wk-purple)">All <?= $e($cat['name']) ?></a>
                        <?php foreach ($children as $child): ?>
                        <a href="<?= $url('category/' . $child['slug']) ?>"><?= $e($child['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <a href="<?= $url('category/' . $cat['slug']) ?>"><?= $e($cat['name']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
            <a href="<?= $url('search') ?>">Search</a>
        </nav>

        <div style="display:flex;align-items:center;gap:12px">
            <?php
            $activeCurrencies = ['INR','USD','EUR','GBP','AUD','CAD','JPY','SGD','AED'];
            $currentCurrency = $_SESSION['wk_display_currency'] ?? (\App\Services\CurrencyService::baseCurrency());
            $currentSymbol = \App\Services\CurrencyService::symbol($currentCurrency);
            ?>
            <select onchange="window.location='<?= $url('') ?>?currency='+this.value" style="padding:6px 10px;border:2px solid var(--wk-border);border-radius:6px;font-family:var(--font);font-size:12px;font-weight:700;background:var(--wk-surface);cursor:pointer;color:var(--wk-text)">
                <?php foreach ($activeCurrencies as $cc): ?>
                    <option value="<?= $cc ?>" <?= $cc === $currentCurrency ? 'selected' : '' ?>><?= \App\Services\CurrencyService::symbol($cc) ?> <?= $cc ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($isLoggedIn): ?>
                <div style="position:relative" id="accountMenu">
                    <button onclick="document.getElementById('accountDrop').style.display=document.getElementById('accountDrop').style.display==='block'?'none':'block'" style="background:none;border:2px solid var(--wk-border);border-radius:8px;padding:6px 12px;cursor:pointer;font-family:var(--font);font-size:13px;font-weight:800;color:var(--wk-purple);display:flex;align-items:center;gap:6px">
                        👋 <?= $e($customer['first_name'] ?? 'Account') ?> ▾
                    </button>
                    <div id="accountDrop" style="display:none;position:absolute;right:0;top:calc(100% + 8px);background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:10px;box-shadow:0 12px 40px rgba(0,0,0,.1);width:200px;z-index:200;overflow:hidden">
                        <a href="<?= $url('account') ?>" style="display:block;padding:12px 16px;font-size:13px;font-weight:700;color:var(--wk-text);border-bottom:1px solid var(--wk-border)">📊 Dashboard</a>
                        <a href="<?= $url('account/orders') ?>" style="display:block;padding:12px 16px;font-size:13px;font-weight:700;color:var(--wk-text);border-bottom:1px solid var(--wk-border)">📦 My Orders</a>
                        <a href="<?= $url('account/profile') ?>" style="display:block;padding:12px 16px;font-size:13px;font-weight:700;color:var(--wk-text);border-bottom:1px solid var(--wk-border)">👤 Profile</a>
                        <a href="<?= $url('account/addresses') ?>" style="display:block;padding:12px 16px;font-size:13px;font-weight:700;color:var(--wk-text);border-bottom:1px solid var(--wk-border)">📍 Addresses</a>
                        <a href="<?= $url('account/logout') ?>" style="display:block;padding:12px 16px;font-size:13px;font-weight:700;color:#ef4444">↪ Sign Out</a>
                    </div>
                </div>
                <script>document.addEventListener('click',function(e){if(!document.getElementById('accountMenu').contains(e.target))document.getElementById('accountDrop').style.display='none'});</script>
            <?php else: ?>
                <a href="<?= $url('account/login') ?>" style="font-size:13px;font-weight:700;color:var(--wk-muted)">Sign In</a>
            <?php endif; ?>

            <button class="wk-cart-btn" data-cart-open>
                🛒 Cart <span class="wk-cart-count" style="display:none">0</span>
            </button>
        </div>
    </div>
</header>

<!-- Flash Messages -->
<?php foreach ($_flashes as $f): ?>
    <div style="max-width:var(--max-w);margin:12px auto;padding:0 24px">
        <div style="padding:12px 16px;border-radius:8px;font-size:14px;font-weight:700;background:<?= $f['type']==='error'?'#fee2e2':'#d1fae5' ?>;color:<?= $f['type']==='error'?'#ef4444':'#10b981' ?>">
            <?= $e($f['message']) ?>
        </div>
    </div>
<?php endforeach; ?>

<!-- Page Content -->
<?= $_content ?>

<!-- Cart Overlay + Drawer -->
<div class="wk-cart-overlay" data-cart-close></div>
<div class="wk-cart-drawer">
    <div class="wk-cart-drawer-header">
        <h2>Your Cart</h2>
        <button class="wk-cart-close" data-cart-close>✕ Close</button>
    </div>
    <div class="wk-cart-items">
        <div class="wk-cart-empty"><div class="wk-cart-empty-icon">🛒</div><p style="font-weight:800">Cart is empty</p></div>
    </div>
    <div class="wk-cart-footer">
        <div class="wk-cart-total">
            <span class="wk-cart-total-label">Subtotal</span>
            <span class="wk-cart-total-value">₹0.00</span>
        </div>
        <a href="<?= $url('checkout') ?>" class="wk-checkout-btn">Proceed to Checkout →</a>
    </div>
</div>

<!-- Footer -->
<footer class="wk-footer">
    <div class="wk-footer-inner" style="flex-direction:column;gap:24px;text-align:center">
        <div style="display:flex;justify-content:center;gap:32px;flex-wrap:wrap">
            <a href="<?= $url('') ?>" style="font-weight:700;color:rgba(255,255,255,.7)">Shop</a>
            <?php foreach (array_slice($parentCats, 0, 6) as $cat): ?>
                <a href="<?= $url('category/' . $cat['slug']) ?>" style="font-weight:700;color:rgba(255,255,255,.7)"><?= $e($cat['name']) ?></a>
            <?php endforeach; ?>
            <a href="<?= $url('contact') ?>" style="font-weight:700;color:rgba(255,255,255,.7)">Contact</a>
        </div>
        <div style="display:flex;justify-content:center;gap:24px;flex-wrap:wrap;font-size:12px">
            <a href="<?= $url('page/terms-and-conditions') ?>" style="color:rgba(255,255,255,.5)">Terms & Conditions</a>
            <a href="<?= $url('page/privacy-policy') ?>" style="color:rgba(255,255,255,.5)">Privacy Policy</a>
            <a href="<?= $url('page/refund-policy') ?>" style="color:rgba(255,255,255,.5)">Refund Policy</a>
            <a href="<?= $url('page/exchange-policy') ?>" style="color:rgba(255,255,255,.5)">Exchange Policy</a>
            <?php if ($isLoggedIn): ?>
                <a href="<?= $url('account') ?>" style="color:rgba(255,255,255,.5)">My Account</a>
            <?php else: ?>
                <a href="<?= $url('account/login') ?>" style="color:rgba(255,255,255,.5)">Sign In</a>
            <?php endif; ?>
        </div>
        <div class="wk-footer-brand">🐱 Powered by <a href="https://github.com" style="color:var(--wk-purple);margin-left:4px">Whisker</a></div>
        <div style="font-size:12px">&copy; <?= date('Y') ?> <?= $e($siteName) ?>. All rights reserved.</div>
    </div>
</footer>

<script src="<?= \Core\View::asset('js/store.js') ?>"></script>

<!-- Chatbot Widget -->
<?php
$chatbotName = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='chatbot_name'") ?: 'Whisker Bot';
$chatbotEnabled = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='chatbot_enabled'");
if ($chatbotEnabled !== '0'):
?>
<div id="wkChatbot">
    <button id="wkChatToggle" onclick="toggleChat()" style="position:fixed;bottom:24px;right:24px;width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));border:none;cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,.2);z-index:9999;display:flex;align-items:center;justify-content:center;font-size:24px;transition:transform .2s" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">💬</button>

    <div id="wkChatWindow" style="display:none;position:fixed;bottom:96px;right:24px;width:380px;max-height:520px;background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.15);z-index:9999;overflow:hidden;display:none;flex-direction:column">
        <div style="background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));color:#fff;padding:16px 20px;display:flex;align-items:center;justify-content:space-between">
            <div><div style="font-weight:800;font-size:15px"><?= $e($chatbotName) ?></div><div style="font-size:11px;opacity:.8">Online • Ask me anything</div></div>
            <button onclick="toggleChat()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:14px">✕</button>
        </div>
        <div id="wkChatMessages" style="flex:1;overflow-y:auto;padding:16px;max-height:340px;min-height:250px"></div>
        <div style="border-top:1px solid var(--wk-border);padding:12px;display:flex;gap:8px">
            <input type="text" id="wkChatInput" placeholder="Type a message..." onkeydown="if(event.key==='Enter')sendChat()" style="flex:1;padding:10px 14px;border:2px solid var(--wk-border);border-radius:8px;font-family:var(--font);font-size:13px;font-weight:600;outline:none">
            <button onclick="sendChat()" style="background:var(--wk-purple);color:#fff;border:none;border-radius:8px;padding:10px 16px;cursor:pointer;font-weight:800;font-size:13px">Send</button>
        </div>
    </div>
</div>

<script>
const wkBase = document.querySelector('meta[name="wk-base"]')?.content || '/';
let chatOpen = false;

function toggleChat() {
    chatOpen = !chatOpen;
    const w = document.getElementById('wkChatWindow');
    const b = document.getElementById('wkChatToggle');
    w.style.display = chatOpen ? 'flex' : 'none';
    b.innerHTML = chatOpen ? '✕' : '💬';
    if (chatOpen && document.getElementById('wkChatMessages').children.length === 0) sendChat('hello');
}

function addMessage(text, from, actions) {
    const c = document.getElementById('wkChatMessages');
    const d = document.createElement('div');
    d.style.cssText = 'margin-bottom:12px;display:flex;' + (from==='user'?'justify-content:flex-end':'');
    const bubble = document.createElement('div');
    bubble.style.cssText = from==='user'
        ? 'background:var(--wk-purple);color:#fff;padding:10px 14px;border-radius:12px 12px 4px 12px;max-width:80%;font-size:13px;font-weight:600;line-height:1.5'
        : 'background:var(--wk-bg);color:var(--wk-text);padding:10px 14px;border-radius:12px 12px 12px 4px;max-width:85%;font-size:13px;line-height:1.6';
    // Simple markdown: **bold**, [text](url), newlines
    let html = text.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
                   .replace(/\[(.*?)\]\((.*?)\)/g,'<a href="'+wkBase+'$2" style="color:var(--wk-purple);font-weight:700;text-decoration:underline">$1</a>')
                   .replace(/\n/g,'<br>');
    bubble.innerHTML = html;
    d.appendChild(bubble);
    c.appendChild(d);
    if (actions && actions.length) {
        const ad = document.createElement('div');
        ad.style.cssText = 'margin-bottom:12px;display:flex;flex-wrap:wrap;gap:6px';
        actions.forEach(a => {
            const btn = document.createElement('button');
            btn.textContent = a.label;
            btn.style.cssText = 'background:var(--wk-surface);border:2px solid var(--wk-purple);color:var(--wk-purple);padding:6px 12px;border-radius:20px;font-family:var(--font);font-size:11px;font-weight:700;cursor:pointer;transition:all .15s';
            btn.onmouseover = () => { btn.style.background='var(--wk-purple)'; btn.style.color='#fff'; };
            btn.onmouseout = () => { btn.style.background='var(--wk-surface)'; btn.style.color='var(--wk-purple)'; };
            btn.onclick = () => sendChat(a.value);
            ad.appendChild(btn);
        });
        c.appendChild(ad);
    }
    c.scrollTop = c.scrollHeight;
}

async function sendChat(override) {
    const input = document.getElementById('wkChatInput');
    const msg = override || input.value.trim();
    if (!msg) return;
    if (!override) { addMessage(msg, 'user'); input.value = ''; }
    // Show typing
    const typing = document.createElement('div');
    typing.style.cssText = 'margin-bottom:12px';
    typing.innerHTML = '<div style="background:var(--wk-bg);color:var(--wk-muted);padding:10px 14px;border-radius:12px;font-size:12px;display:inline-block">typing...</div>';
    document.getElementById('wkChatMessages').appendChild(typing);

    try {
        const form = new FormData();
        form.append('message', msg);
        const res = await fetch(wkBase + 'chatbot/message', { method:'POST', body:form });
        const data = await res.json();
        typing.remove();
        addMessage(data.reply, 'bot', data.actions);
    } catch(e) {
        typing.remove();
        addMessage("Sorry, I'm having trouble right now. Please try again later.", 'bot');
    }
}
</script>
<?php endif; ?>
</body>
</html>