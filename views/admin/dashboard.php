<?php
$e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); $price=fn($v)=>$currency.number_format((float)$v,2);
$statusMap = ['pending'=>['warning','⏳'],'processing'=>['info','🔄'],'paid'=>['success','✓'],'shipped'=>['purple','📦'],'delivered'=>['success','✅'],'cancelled'=>['danger','✗'],'refunded'=>['danger','↩']];
$customerName = function($o) {
    if (!empty($o['first_name'])) return trim($o['first_name'].' '.($o['last_name']??''));
    $b = json_decode($o['billing_address']??'{}', true);
    return trim($b['name']??'') ?: ($o['customer_email']??'Guest');
};

// Check if update was dismissed (time-based)
$dismissedData = \Core\Database::setting('system_cache', 'dismissed_update');
$showUpdate = !empty($updateAvailable);
if ($showUpdate && $dismissedData) {
    $dismissed = json_decode($dismissedData, true);
    if ($dismissed && ($dismissed['version'] ?? '') === ($updateAvailable['version'] ?? '') && ($dismissed['until'] ?? 0) > time()) {
        $showUpdate = false;
    }
}
?>

<?php if ($showUpdate): ?>
<!-- Update Banner -->
<div id="updateBanner" style="background:linear-gradient(135deg,#1e1b2e,#2d2640);border:2px solid <?= ($updateAvailable['severity'] ?? '') === 'critical' ? '#ef4444' : '#8b5cf6' ?>;border-radius:14px;padding:20px 24px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div style="display:flex;align-items:center;gap:14px">
        <div style="width:44px;height:44px;border-radius:12px;background:<?= ($updateAvailable['severity'] ?? '') === 'critical' ? '#ef4444' : '#8b5cf6' ?>;display:flex;align-items:center;justify-content:center;font-size:22px">🚀</div>
        <div>
            <div style="font-weight:800;font-size:15px;color:#fff">
                Whisker v<?= $e($updateAvailable['version'] ?? '') ?> is available!
                <?php if (($updateAvailable['severity'] ?? '') === 'critical'): ?>
                <span style="background:#ef4444;color:#fff;font-size:11px;padding:2px 8px;border-radius:6px;margin-left:6px">CRITICAL</span>
                <?php elseif (($updateAvailable['severity'] ?? '') === 'recommended'): ?>
                <span style="background:#f59e0b;color:#000;font-size:11px;padding:2px 8px;border-radius:6px;margin-left:6px">RECOMMENDED</span>
                <?php endif; ?>
            </div>
            <div style="font-size:13px;color:rgba(255,255,255,.6);margin-top:2px">
                You're on v<?= WK_VERSION ?>.
                <?php if (!empty($updateAvailable['changelog'])): ?>
                <?= $e(mb_substr($updateAvailable['changelog'], 0, 120)) ?><?= strlen($updateAvailable['changelog']) > 120 ? '...' : '' ?>
                <?php endif; ?>
                <?php if (!empty($updateAvailable['size'])): ?>
                (<?= $e($updateAvailable['size']) ?>)
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <?php if (!empty($updateAvailable['download_url'])): ?>
        <form method="POST" action="<?= $url('admin/update/apply') ?>" onsubmit="return confirm('This will backup your store and update to v<?= $e($updateAvailable['version']) ?>. Continue?')" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <?= \Core\Session::csrfField() ?>
            <input type="hidden" name="download_url" value="<?= $e($updateAvailable['download_url']) ?>">
            <input type="hidden" name="sha256" value="<?= $e($updateAvailable['sha256'] ?? '') ?>">
            <select name="db_backup" style="padding:8px 10px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;color:#fff;font-size:12px;font-weight:700">
                <option value="schema">DB: Schema only<?php if ($dbSize): ?> (~<?= round($dbSize['schema']/1024) ?>KB)<?php endif; ?></option>
                <option value="full">DB: Full dump<?php if ($dbSize): ?> (~<?= $dbSize['full'] > 1048576 ? round($dbSize['full']/1048576,1).'MB' : round($dbSize['full']/1024).'KB' ?>)<?php endif; ?></option>
                <option value="none">DB: No backup</option>
            </select>
            <button type="submit" style="padding:10px 20px;background:linear-gradient(135deg,#8b5cf6,#ec4899);color:#fff;border:none;border-radius:10px;font-weight:800;font-size:13px;cursor:pointer">Update Now →</button>
        </form>
        <?php endif; ?>
        <button onclick="dismissUpdate('<?= $e($updateAvailable['version'] ?? '') ?>', 24)" style="padding:10px 16px;background:rgba(255,255,255,.1);color:rgba(255,255,255,.6);border:none;border-radius:10px;font-weight:700;font-size:13px;cursor:pointer">Remind Tomorrow</button>
        <button onclick="dismissUpdate('<?= $e($updateAvailable['version'] ?? '') ?>', 168)" style="padding:10px 16px;background:rgba(255,255,255,.06);color:rgba(255,255,255,.4);border:none;border-radius:10px;font-weight:700;font-size:12px;cursor:pointer">Dismiss 7 days</button>
    </div>
</div>
<script>
function dismissUpdate(version, hours) {
    fetch('<?= $url('admin/update/dismiss') ?>', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded','X-CSRF-Token':'<?= \Core\Session::csrfToken() ?>'},
        body:'version='+encodeURIComponent(version)+'&hours='+hours
    }).then(()=>document.getElementById('updateBanner').style.display='none');
}
</script>
<?php endif; ?>

<?php if (!empty($backups)): ?>
<!-- Rollback Option -->
<div style="background:var(--wk-card,#1a1726);border:1px solid var(--wk-border,#2a2538);border-radius:12px;padding:14px 20px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:center;gap:10px">
        <span style="font-size:18px">🔄</span>
        <div>
            <div style="font-weight:700;font-size:13px">Rollback Available</div>
            <div style="font-size:12px;color:var(--wk-muted,#6b6580)"><?= count($backups) ?> backup<?= count($backups) > 1 ? 's' : '' ?> saved</div>
        </div>
    </div>
    <form method="POST" action="<?= $url('admin/update/rollback') ?>" onsubmit="return confirm('This will restore your store to the selected backup version. Your config, database, and uploads will NOT be affected. Continue?')" style="display:flex;align-items:center;gap:8px">
        <?= \Core\Session::csrfField() ?>
        <select name="backup_file" style="padding:8px 12px;background:var(--wk-bg,#12101e);border:1px solid var(--wk-border,#2a2538);border-radius:8px;font-size:12px;font-weight:700;color:var(--wk-text,#e2e0ea)">
            <?php foreach ($backups as $b): ?>
            <option value="<?= $e($b['filename']) ?>">v<?= $e($b['version']) ?> — <?= $e($b['date']) ?> (<?= $b['size'] > 1048576 ? round($b['size']/1048576,1).'MB' : round($b['size']/1024).'KB' ?><?= $b['has_db'] ? ' + DB' : '' ?>)</option>
            <?php endforeach; ?>
        </select>
        <button type="submit" style="padding:8px 16px;background:#7f1d1d;color:#fca5a5;border:none;border-radius:8px;font-weight:700;font-size:12px;cursor:pointer">Restore ↩</button>
    </form>
</div>
<?php endif; ?>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
    <div class="wk-card" style="padding:20px">
        <div style="display:flex;align-items:center;gap:14px">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#8b5cf6,#a78bfa);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">💰</div>
            <div>
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted)">Total Revenue</div>
                <div style="font-size:24px;font-weight:900;font-family:var(--font-mono)"><?= $price($stats['revenue']) ?></div>
                <div style="font-size:11px;color:var(--wk-green);font-weight:700">Today: <?= $price($stats['today_revenue']) ?></div>
            </div>
        </div>
    </div>
    <div class="wk-card" style="padding:20px">
        <div style="display:flex;align-items:center;gap:14px">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#ec4899,#f472b6);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">📦</div>
            <div>
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted)">Orders</div>
                <div style="font-size:24px;font-weight:900"><?= $stats['orders'] ?></div>
                <div style="font-size:11px;font-weight:700"><span style="color:var(--wk-yellow)"><?= $stats['pending'] ?> pending</span> · <span style="color:var(--wk-green)"><?= $stats['today_orders'] ?> today</span></div>
            </div>
        </div>
    </div>
    <div class="wk-card" style="padding:20px">
        <div style="display:flex;align-items:center;gap:14px">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#10b981,#34d399);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">👥</div>
            <div>
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted)">Customers</div>
                <div style="font-size:24px;font-weight:900"><?= $stats['customers'] ?></div>
                <div style="font-size:11px;color:var(--wk-text-muted);font-weight:700">Avg order: <?= $price($stats['avg_order']) ?></div>
            </div>
        </div>
    </div>
    <div class="wk-card" style="padding:20px">
        <div style="display:flex;align-items:center;gap:14px">
            <div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#f59e0b,#fbbf24);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff">🏪</div>
            <div>
                <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:var(--wk-text-muted)">Products</div>
                <div style="font-size:24px;font-weight:900"><?= $stats['products'] ?></div>
                <div style="font-size:11px;font-weight:700">
                    <?php if ($stats['out_of_stock']): ?><span style="color:var(--wk-red)"><?= $stats['out_of_stock'] ?> out of stock</span><?php endif; ?>
                    <?php if ($stats['low_stock']): ?> · <span style="color:var(--wk-yellow)"><?= $stats['low_stock'] ?> low</span><?php endif; ?>
                    <?php if (!$stats['out_of_stock'] && !$stats['low_stock']): ?><span style="color:var(--wk-green)">All stocked</span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
    <!-- Revenue Chart -->
    <div class="wk-card">
        <div class="wk-card-header">
            <h2>Revenue — Last 30 Days</h2>
            <span style="font-size:13px;font-weight:800;color:var(--wk-purple)"><?= $price($stats['month_revenue']) ?> this month</span>
        </div>
        <div class="wk-card-body" style="padding:12px 16px">
            <canvas id="revenueChart" height="220"></canvas>
        </div>
    </div>

    <!-- Order Status Donut -->
    <div class="wk-card">
        <div class="wk-card-header"><h2>Order Status</h2></div>
        <div class="wk-card-body" style="display:flex;flex-direction:column;align-items:center;padding:16px">
            <canvas id="statusChart" width="200" height="200"></canvas>
            <div style="margin-top:14px;width:100%">
                <?php foreach ($statusBreakdown as $sb):
                    $colors = ['pending'=>'#f59e0b','processing'=>'#3b82f6','paid'=>'#10b981','shipped'=>'#8b5cf6','delivered'=>'#10b981','cancelled'=>'#ef4444','refunded'=>'#6b7280'];
                ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:3px 0;font-size:12px">
                    <div style="display:flex;align-items:center;gap:6px"><span style="width:8px;height:8px;border-radius:50%;background:<?= $colors[$sb['status']]??'#6b7280' ?>"></span><span style="font-weight:700"><?= ucfirst($sb['status']) ?></span></div>
                    <span style="font-weight:800"><?= $sb['count'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Middle Row: Top Products + Stock Alerts -->
<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:20px;margin-bottom:24px">
    <!-- Top Selling Products -->
    <div class="wk-card">
        <div class="wk-card-header"><h2>🏆 Top Selling Products</h2></div>
        <?php if (empty($topProducts)): ?>
            <div style="text-align:center;padding:32px;color:var(--wk-text-muted)">No sales yet</div>
        <?php else: ?>
        <?php foreach ($topProducts as $i => $tp): ?>
        <div style="display:flex;align-items:center;gap:14px;padding:12px 20px;border-bottom:1px solid var(--wk-border)">
            <div style="font-size:16px;font-weight:900;color:<?= $i===0?'var(--wk-purple)':($i===1?'var(--wk-pink)':'var(--wk-text-muted)') ?>;width:24px">#<?= $i+1 ?></div>
            <div style="width:40px;height:40px;border-radius:8px;overflow:hidden;background:var(--wk-bg);flex-shrink:0">
                <?php if ($tp['image']): ?><img src="<?= $url('storage/uploads/products/'.$tp['image']) ?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:16px">📦</div><?php endif; ?>
            </div>
            <div style="flex:1">
                <div style="font-weight:800;font-size:13px"><?= $e($tp['name']) ?></div>
                <div style="font-size:11px;color:var(--wk-text-muted)"><?= $tp['sold'] ?> sold</div>
            </div>
            <div style="font-family:var(--font-mono);font-weight:800;font-size:14px;color:var(--wk-green)"><?= $price($tp['revenue']) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Recent Customers -->
    <div class="wk-card">
        <div class="wk-card-header"><h2>👥 Recent Customers</h2><a href="<?= $url('admin/customers') ?>" class="wk-btn wk-btn-secondary wk-btn-sm">View All →</a></div>
        <?php if (empty($recentCustomers)): ?>
            <div style="text-align:center;padding:32px;color:var(--wk-text-muted)">No customers yet</div>
        <?php else: ?>
        <?php foreach ($recentCustomers as $rc): ?>
        <div style="display:flex;align-items:center;gap:12px;padding:10px 20px;border-bottom:1px solid var(--wk-border)">
            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:13px"><?= strtoupper(substr($rc['first_name']??'?',0,1)) ?></div>
            <div style="flex:1">
                <div style="font-weight:700;font-size:13px"><?= $e($rc['first_name'].' '.$rc['last_name']) ?></div>
                <div style="font-size:11px;color:var(--wk-text-muted)"><?= $e($rc['email']) ?></div>
            </div>
            <div style="font-size:11px;color:var(--wk-text-muted)"><?= date('M j', strtotime($rc['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Orders -->
<div class="wk-card">
    <div class="wk-card-header"><h2>Recent Orders</h2><a href="<?= $url('admin/orders') ?>" class="wk-btn wk-btn-secondary wk-btn-sm">View All →</a></div>
    <?php if (empty($recentOrders)): ?>
        <div class="wk-empty"><div class="wk-empty-icon">🛍️</div><p style="font-weight:800">No orders yet</p></div>
    <?php else: ?>
    <table class="wk-table"><thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead><tbody>
    <?php foreach ($recentOrders as $order): $s=$statusMap[$order['status']]??['info','?']; ?>
    <tr style="cursor:pointer" onclick="window.location='<?= $url('admin/orders/'.$order['id']) ?>'">
        <td><span style="font-family:var(--font-mono);font-size:12px;font-weight:700;color:var(--wk-purple)"><?= $e($order['order_number']) ?></span></td>
        <td>
            <div style="font-weight:700"><?= $e($customerName($order)) ?></div>
            <?php if ($order['customer_email']): ?><div style="font-size:11px;color:var(--wk-text-muted)"><?= $e($order['customer_email']) ?></div><?php endif; ?>
        </td>
        <td style="font-family:var(--font-mono);font-weight:700"><?= $price($order['total']) ?></td>
        <td><span class="wk-badge wk-badge-<?= $s[0] ?>"><?= $s[1] ?> <?= ucfirst($order['status']) ?></span></td>
        <td style="color:var(--wk-text-muted);font-size:13px"><?= date('M j, g:i A', strtotime($order['created_at'])) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody></table>
    <?php endif; ?>
</div>

<!-- Charts JS (pure Canvas, no library) -->
<script>
const chartData = <?= json_encode($chartData) ?>;
const statusData = <?= json_encode($statusBreakdown) ?>;

// ── Revenue Bar/Line Chart ──
(function() {
    const canvas = document.getElementById('revenueChart');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = rect.height * dpr;
    ctx.scale(dpr, dpr);
    const W = rect.width, H = rect.height;
    const pad = {top:20, right:16, bottom:40, left:60};
    const plotW = W - pad.left - pad.right;
    const plotH = H - pad.top - pad.bottom;

    const maxRev = Math.max(...chartData.map(d => d.revenue), 1);
    const barW = Math.max(2, (plotW / chartData.length) - 3);

    // Grid lines
    ctx.strokeStyle = '#e8e5df';
    ctx.lineWidth = 0.5;
    for (let i = 0; i <= 4; i++) {
        const y = pad.top + plotH - (plotH * i / 4);
        ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(W - pad.right, y); ctx.stroke();
        ctx.fillStyle = '#9ca3af'; ctx.font = '10px sans-serif'; ctx.textAlign = 'right';
        ctx.fillText('<?= $currency ?>' + Math.round(maxRev * i / 4).toLocaleString(), pad.left - 6, y + 3);
    }

    // Bars
    const gradient = ctx.createLinearGradient(0, pad.top, 0, pad.top + plotH);
    gradient.addColorStop(0, '#8b5cf6');
    gradient.addColorStop(1, '#c4b5fd');

    chartData.forEach((d, i) => {
        const x = pad.left + (i * (plotW / chartData.length)) + 1;
        const barH = (d.revenue / maxRev) * plotH;
        const y = pad.top + plotH - barH;

        ctx.fillStyle = gradient;
        ctx.beginPath();
        const r = Math.min(3, barW/2);
        ctx.moveTo(x, y + r); ctx.arcTo(x, y, x + r, y, r); ctx.arcTo(x + barW, y, x + barW, y + r, r);
        ctx.lineTo(x + barW, pad.top + plotH); ctx.lineTo(x, pad.top + plotH);
        ctx.fill();

        // X labels (every 5th)
        if (i % 5 === 0 || i === chartData.length - 1) {
            ctx.fillStyle = '#9ca3af'; ctx.font = '9px sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(d.label, x + barW / 2, pad.top + plotH + 14);
        }
    });

    // Line overlay
    ctx.strokeStyle = '#ec4899';
    ctx.lineWidth = 2;
    ctx.beginPath();
    chartData.forEach((d, i) => {
        const x = pad.left + (i * (plotW / chartData.length)) + barW / 2;
        const y = pad.top + plotH - (d.revenue / maxRev) * plotH;
        if (i === 0) ctx.moveTo(x, y); else ctx.lineTo(x, y);
    });
    ctx.stroke();

    // Dots
    chartData.forEach((d, i) => {
        if (d.revenue > 0) {
            const x = pad.left + (i * (plotW / chartData.length)) + barW / 2;
            const y = pad.top + plotH - (d.revenue / maxRev) * plotH;
            ctx.fillStyle = '#ec4899'; ctx.beginPath(); ctx.arc(x, y, 3, 0, Math.PI * 2); ctx.fill();
        }
    });
})();

// ── Status Donut Chart ──
(function() {
    const canvas = document.getElementById('statusChart');
    if (!canvas || !statusData.length) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    canvas.width = 200 * dpr; canvas.height = 200 * dpr;
    ctx.scale(dpr, dpr);

    const colors = {pending:'#f59e0b',processing:'#3b82f6',paid:'#10b981',shipped:'#8b5cf6',delivered:'#10b981',cancelled:'#ef4444',refunded:'#6b7280'};
    const total = statusData.reduce((s, d) => s + parseInt(d.count), 0);
    let startAngle = -Math.PI / 2;
    const cx = 100, cy = 100, outerR = 80, innerR = 50;

    statusData.forEach(d => {
        const sliceAngle = (parseInt(d.count) / total) * Math.PI * 2;
        ctx.beginPath();
        ctx.arc(cx, cy, outerR, startAngle, startAngle + sliceAngle);
        ctx.arc(cx, cy, innerR, startAngle + sliceAngle, startAngle, true);
        ctx.closePath();
        ctx.fillStyle = colors[d.status] || '#6b7280';
        ctx.fill();
        startAngle += sliceAngle;
    });

    // Center text
    ctx.fillStyle = '#1e1b2e'; ctx.font = 'bold 28px sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
    ctx.fillText(total, cx, cy - 6);
    ctx.fillStyle = '#9ca3af'; ctx.font = '11px sans-serif';
    ctx.fillText('orders', cx, cy + 14);
})();
</script>