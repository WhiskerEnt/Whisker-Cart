<?php
$admin = \Core\Database::fetch("SELECT username, email, role FROM wk_admins WHERE id = ?", [\Core\Session::adminId()]);
$siteName = \Core\Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='site_name'") ?: 'Whisker';
$pendingOrders = \Core\Database::fetchValue("SELECT COUNT(*) FROM wk_orders WHERE status='pending'") ?: 0;
$currentPath = (new \Core\Request())->path();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Admin') ?> — Whisker</title>
    <meta name="csrf-token" content="<?= \Core\Session::csrfToken() ?>">
    <script>
    // CSRF: Auto-inject token into all POST fetch calls (must run before any page scripts)
    (function(){
        const token = '<?= \Core\Session::csrfToken() ?>';
        const _fetch = window.fetch;
        window.fetch = function(url, opts = {}) {
            if (opts.method && opts.method.toUpperCase() === 'POST' && token) {
                if (opts.body instanceof FormData) {
                    if (!opts.body.has('wk_csrf')) opts.body.append('wk_csrf', token);
                } else if (opts.body instanceof URLSearchParams) {
                    if (!opts.body.has('wk_csrf')) opts.body.append('wk_csrf', token);
                }
                opts.headers = opts.headers || {};
                if (typeof opts.headers === 'object' && !(opts.headers instanceof Headers)) {
                    opts.headers['X-CSRF-Token'] = token;
                }
            }
            return _fetch.call(this, url, opts);
        };
    })();
    </script>
    <link rel="icon" type="image/svg+xml" href="<?= \Core\View::asset('img/favicon.svg') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= \Core\View::asset('css/admin.css') ?>">
</head>
<body>

<!-- Branded Loader -->
<div class="wk-loader" id="pageLoader">
    <div class="wk-loader-inner">
        <svg width="40" height="40" viewBox="0 0 56 56" fill="none" class="wk-loader-cat">
            <circle cx="28" cy="28" r="26" fill="#faf8f6" stroke="url(#ldr)" stroke-width="2"/>
            <path d="M16 10 L12 22 L22 18Z" fill="#8b5cf6"/>
            <path d="M40 10 L44 22 L34 18Z" fill="#ec4899"/>
            <circle cx="21" cy="26" r="3" fill="#1e1b2e"/>
            <circle cx="35" cy="26" r="3" fill="#1e1b2e"/>
            <ellipse cx="28" cy="31" rx="2" ry="1.5" fill="#f472b6"/>
            <defs><linearGradient id="ldr" x1="0" y1="0" x2="56" y2="56"><stop offset="0%" stop-color="#8b5cf6"/><stop offset="100%" stop-color="#ec4899"/></linearGradient></defs>
        </svg>
        <div class="wk-loader-dots">
            <span></span><span></span><span></span>
        </div>
    </div>
</div>

<div class="wk-admin">

    <!-- ═══ SIDEBAR ═══ -->
    <aside class="wk-sidebar" id="sidebar">
        <div class="wk-sidebar-brand">
            <svg width="30" height="30" viewBox="0 0 56 56" fill="none">
                <circle cx="28" cy="28" r="26" fill="#faf8f6" stroke="url(#sb)" stroke-width="2"/>
                <path d="M16 10 L12 22 L22 18Z" fill="#8b5cf6"/>
                <path d="M40 10 L44 22 L34 18Z" fill="#ec4899"/>
                <circle cx="21" cy="26" r="3" fill="#1e1b2e"/>
                <circle cx="35" cy="26" r="3" fill="#1e1b2e"/>
                <circle cx="22" cy="25" r="1" fill="#fff"/>
                <circle cx="36" cy="25" r="1" fill="#fff"/>
                <ellipse cx="28" cy="31" rx="2" ry="1.5" fill="#f472b6"/>
                <path d="M24 33 Q28 36 32 33" stroke="#1e1b2e" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                <defs><linearGradient id="sb" x1="0" y1="0" x2="56" y2="56"><stop offset="0%" stop-color="#8b5cf6"/><stop offset="100%" stop-color="#ec4899"/></linearGradient></defs>
            </svg>
            <div>
                <div class="wk-sidebar-name">Whisker</div>
                <div class="wk-sidebar-version">v<?= WK_VERSION ?> · Free</div>
            </div>
        </div>

        <nav class="wk-sidebar-nav">
            <div class="wk-nav-group">
                <div class="wk-nav-label">Main</div>
                <?php
                $abandonedCount = 0;
                try { $abandonedCount = (int)\Core\Database::fetchValue("SELECT COUNT(DISTINCT c.id) FROM wk_carts c JOIN wk_cart_items ci ON ci.cart_id=c.id WHERE c.status='active' AND c.created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)"); } catch(\Exception $e) {}
                $openTickets = 0;
                try { $openTickets = (int)\Core\Database::fetchValue("SELECT COUNT(*) FROM wk_tickets WHERE status IN ('open','in_progress')"); } catch(\Exception $e) {}
                $pendingReviews = \App\Services\ReviewService::pendingCount();
                $pendingQuestions = \App\Services\QuestionService::pendingCount();
                $navItems = [
                    ['/admin', '/admin/dashboard', 'Dashboard', '📊', null],
                    ['/admin/orders', null, 'Orders', '🛍️', $pendingOrders > 0 ? $pendingOrders : null],
                    ['/admin/tickets', null, 'Tickets', '🎫', $openTickets > 0 ? $openTickets : null],
                    ['/admin/abandoned-carts', null, 'Abandoned Carts', '🛒', $abandonedCount > 0 ? $abandonedCount : null],
                    ['/admin/products', null, 'Products', '📦', null],
                    ['/admin/customers', null, 'Customers', '👥', null],
                    ['/admin/reviews', null, 'Reviews', '⭐', $pendingReviews > 0 ? $pendingReviews : null],
                    ['/admin/questions', null, 'Questions', '💬', $pendingQuestions > 0 ? $pendingQuestions : null],
                ];
                foreach ($navItems as [$href, $alt, $label, $icon, $badge]):
                    $isActive = $currentPath === $href || $currentPath === $alt;
                ?>
                <a href="<?= \Core\View::url(ltrim($href, '/')) ?>" class="wk-nav-item <?= $isActive ? 'active' : '' ?>">
                    <span class="wk-nav-icon"><?= $icon ?></span>
                    <span class="wk-nav-text"><?= $label ?></span>
                    <?php if ($badge): ?>
                        <span class="wk-nav-badge"><?= $badge ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="wk-nav-group">
                <div class="wk-nav-label">Commerce</div>
                <?php
                $commerceItems = [
                    ['/admin/gateways', 'Payment Gateways', '💳'],
                    ['/admin/coupons', 'Coupons', '🏷️'],
                    ['/admin/categories', 'Categories', '📂'],
                    ['/admin/shipping', 'Shipping Carriers', '🚚'],
                    ['/admin/shipping/settings', 'Shipping Rates', '📦'],
                ];
                foreach ($commerceItems as [$href, $label, $icon]):
                    $isActive = $currentPath === $href;
                ?>
                <a href="<?= \Core\View::url(ltrim($href, '/')) ?>" class="wk-nav-item <?= $isActive ? 'active' : '' ?>">
                    <span class="wk-nav-icon"><?= $icon ?></span>
                    <span class="wk-nav-text"><?= $label ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <div class="wk-nav-group">
                <div class="wk-nav-label">System</div>
                <a href="<?= \Core\View::url('admin/settings') ?>" class="wk-nav-item <?= $currentPath === '/admin/settings' ? 'active' : '' ?>">
                    <span class="wk-nav-icon">⚙️</span>
                    <span class="wk-nav-text">Settings</span>
                </a>
                <a href="<?= \Core\View::url('admin/email-templates') ?>" class="wk-nav-item <?= str_starts_with($currentPath, '/admin/email-templates') ? 'active' : '' ?>">
                    <span class="wk-nav-icon">📧</span>
                    <span class="wk-nav-text">Email Templates</span>
                </a>
                <a href="<?= \Core\View::url('admin/pages') ?>" class="wk-nav-item <?= str_starts_with($currentPath, '/admin/pages') ? 'active' : '' ?>">
                    <span class="wk-nav-icon">📄</span>
                    <span class="wk-nav-text">Pages</span>
                </a>
                <a href="<?= \Core\View::url('admin/seo') ?>" class="wk-nav-item <?= str_starts_with($currentPath, '/admin/seo') ? 'active' : '' ?>">
                    <span class="wk-nav-icon">🔍</span>
                    <span class="wk-nav-text">SEO</span>
                </a>
                <a href="<?= \Core\View::url('admin/import') ?>" class="wk-nav-item <?= str_starts_with($currentPath, '/admin/import') ? 'active' : '' ?>">
                    <span class="wk-nav-icon">📤</span>
                    <span class="wk-nav-text">CSV Import</span>
                </a>
            </div>
        </nav>

        <div class="wk-sidebar-footer">
            <a href="<?= \Core\View::url('') ?>" target="_blank" class="wk-sidebar-store-link">
                ↗ View Store
            </a>
        </div>
    </aside>

    <!-- ═══ MAIN ═══ -->
    <main class="wk-main">

        <!-- Topbar -->
        <header class="wk-topbar">
            <div class="wk-topbar-left">
                <button class="wk-mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
                <h1 class="wk-topbar-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
            </div>
            <div class="wk-topbar-right">
                <div style="position:relative" id="adminMenu">
                    <button onclick="document.getElementById('adminDrop').style.display=document.getElementById('adminDrop').style.display==='block'?'none':'block'" style="background:none;border:2px solid var(--wk-border);border-radius:8px;padding:6px 12px;cursor:pointer;font-family:var(--font);font-size:13px;font-weight:700;color:var(--wk-text);display:flex;align-items:center;gap:8px">
                        <div class="wk-admin-avatar" style="width:28px;height:28px;font-size:12px;border:none;margin:0">
                            <?= strtoupper(substr($admin['username'] ?? 'A', 0, 1)) ?>
                        </div>
                        <?= htmlspecialchars($admin['username'] ?? 'Admin') ?> ▾
                    </button>
                    <div id="adminDrop" style="display:none;position:absolute;right:0;top:calc(100% + 8px);background:var(--wk-surface);border:1px solid var(--wk-border);border-radius:10px;box-shadow:0 12px 40px rgba(0,0,0,.08);width:200px;z-index:200;overflow:hidden">
                        <div style="padding:12px 16px;border-bottom:1px solid var(--wk-border)">
                            <div style="font-weight:800;font-size:13px"><?= htmlspecialchars($admin['username'] ?? 'Admin') ?></div>
                            <div style="font-size:11px;color:var(--wk-text-muted)"><?= htmlspecialchars($admin['email'] ?? '') ?></div>
                        </div>
                        <a href="<?= \Core\View::url('admin/settings') ?>" style="display:block;padding:10px 16px;font-size:13px;font-weight:700;color:var(--wk-text);text-decoration:none;border-bottom:1px solid var(--wk-border)">⚙️ Settings</a>
                        <form method="POST" action="<?= \Core\View::url('admin/logout') ?>" style="margin:0">
                            <?= \Core\Session::csrfField() ?>
                            <button type="submit" style="display:block;width:100%;padding:10px 16px;font-size:13px;font-weight:700;color:var(--wk-red);text-decoration:none;background:none;border:none;text-align:left;cursor:pointer;font-family:inherit">Sign Out</button>
                        </form>
                    </div>
                </div>
                <script>document.addEventListener('click',function(e){if(!document.getElementById('adminMenu').contains(e.target))document.getElementById('adminDrop').style.display='none'});</script>
            </div>
        </header>

        <!-- Flash Messages -->
        <?php foreach ($_flashes as $flash): ?>
            <div class="wk-flash wk-flash-<?= $flash['type'] ?>">
                <span class="wk-flash-icon"><?= $flash['type'] === 'success' ? '✓' : ($flash['type'] === 'error' ? '✗' : 'ℹ') ?></span>
                <?= htmlspecialchars($flash['message']) ?>
                <button class="wk-flash-close" onclick="this.parentElement.remove()">×</button>
            </div>
        <?php endforeach; ?>

        <!-- Page Content -->
        <div class="wk-content">
            <?= $_content ?>
        </div>
    </main>
</div>

<script src="<?= \Core\View::asset('js/admin.js') ?>"></script>
</body>
</html>