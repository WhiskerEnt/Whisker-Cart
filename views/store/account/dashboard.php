<?php $url=fn($p)=>\Core\View::url($p); $e=fn($v)=>\Core\View::e($v); $c=$customer; ?>
<section class="wk-section"><div class="wk-container" style="max-width:800px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28px">
        <div>
            <h1 style="font-size:24px;font-weight:900">Hi, <?= $e($c['first_name']) ?>! 👋</h1>
            <p style="color:var(--wk-muted);font-size:14px"><?= $e($c['email']) ?></p>
        </div>
        <form method="POST" action="<?= $url('account/logout') ?>" style="margin:0">
            <?= \Core\Session::csrfField() ?>
            <button type="submit" style="font-size:13px;font-weight:700;color:var(--wk-muted);background:none;border:none;cursor:pointer;font-family:inherit;padding:0">Sign Out</button>
        </form>
    </div>

    <?php if (!empty($needsPassword) && $needsPassword): ?>
    <div style="background:#fef3c7;border:2px solid #fbbf24;border-radius:var(--radius);padding:20px;margin-bottom:24px">
        <div style="font-weight:800;font-size:15px;margin-bottom:4px">⚠ Set Your Password</div>
        <p style="font-size:13px;color:#92400e;margin-bottom:12px">Your account was created during checkout. Set a password to access your account anytime.</p>
        <a href="<?= $url('account/profile') ?>" style="display:inline-block;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));color:#fff;padding:10px 20px;border-radius:8px;font-weight:800;font-size:13px;text-decoration:none">Set Password →</a>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:28px">
        <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:24px">
            <div style="font-size:12px;font-weight:800;text-transform:uppercase;color:var(--wk-muted);margin-bottom:6px">Total Orders</div>
            <div style="font-size:28px;font-weight:900;font-family:var(--font-mono)"><?= $c['total_orders'] ?></div>
        </div>
        <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:24px">
            <div style="font-size:12px;font-weight:800;text-transform:uppercase;color:var(--wk-muted);margin-bottom:6px">Total Spent</div>
            <div style="font-size:28px;font-weight:900;font-family:var(--font-mono)"><?= \Core\View::price($c['total_spent']) ?></div>
        </div>
    </div>

    <!-- Quick Links -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;margin-bottom:28px">
        <a href="<?= $url('account/profile') ?>" style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:20px;text-align:center;text-decoration:none;transition:border-color .2s">
            <div style="font-size:24px;margin-bottom:8px">👤</div>
            <div style="font-weight:800;font-size:14px;color:var(--wk-text)">My Profile</div>
            <div style="font-size:12px;color:var(--wk-muted)">Edit info & password</div>
        </a>
        <a href="<?= $url('account/addresses') ?>" style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:20px;text-align:center;text-decoration:none;transition:border-color .2s">
            <div style="font-size:24px;margin-bottom:8px">📍</div>
            <div style="font-weight:800;font-size:14px;color:var(--wk-text)">Addresses</div>
            <div style="font-size:12px;color:var(--wk-muted)">Manage saved addresses</div>
        </a>
        <a href="<?= $url('account/orders') ?>" style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:20px;text-align:center;text-decoration:none;transition:border-color .2s">
            <div style="font-size:24px;margin-bottom:8px">📦</div>
            <div style="font-weight:800;font-size:14px;color:var(--wk-text)">Orders</div>
            <div style="font-size:12px;color:var(--wk-muted)">View & track orders</div>
        </a>
    </div>

    <!-- Recent Orders -->
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius)">
        <div style="padding:18px 22px;border-bottom:1px solid var(--wk-border);display:flex;align-items:center;justify-content:space-between">
            <h2 style="font-size:16px;font-weight:800">Recent Orders</h2>
            <a href="<?= $url('account/orders') ?>" style="font-size:13px;font-weight:700;color:var(--wk-purple)">View All →</a>
        </div>
        <?php if (empty($recentOrders)): ?>
            <div style="text-align:center;padding:40px;color:var(--wk-muted)"><p style="font-weight:700">No orders yet</p><p style="font-size:13px"><a href="<?= $url('') ?>" style="color:var(--wk-purple)">Start shopping →</a></p></div>
        <?php else: ?>
            <?php foreach ($recentOrders as $o): ?>
            <a href="<?= $url('account/order/'.$o['id']) ?>" style="display:flex;align-items:center;justify-content:space-between;padding:14px 22px;border-bottom:1px solid var(--wk-border);text-decoration:none;transition:background .1s">
                <div>
                    <div style="font-family:var(--font-mono);font-size:13px;font-weight:700;color:var(--wk-purple)"><?= $e($o['order_number']) ?></div>
                    <div style="font-size:12px;color:var(--wk-muted)"><?= date('M j, Y', strtotime($o['created_at'])) ?></div>
                </div>
                <div style="text-align:right">
                    <div style="font-weight:800;font-family:var(--font-mono);color:var(--wk-text)"><?= \Core\View::price($o['total']) ?></div>
                    <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:<?= $o['status']==='cancelled'?'var(--wk-red)':'var(--wk-purple)' ?>"><?= $o['status'] ?></div>
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div></section>