<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);
$c = $customer;
$is='width:100%;padding:12px 16px;border:2px solid var(--wk-border);border-radius:10px;font-family:var(--font);font-size:14px;font-weight:600;outline:none;background:var(--wk-surface)';
?>
<section class="wk-section"><div class="wk-container" style="max-width:700px">
    <a href="<?= $url('account/tickets') ?>" style="color:var(--wk-purple-ink);font-weight:700;font-size:13px;margin-bottom:16px;display:inline-block">← My Tickets</a>
    <h1 style="font-size:24px;font-weight:900;margin-bottom:24px">New Support Ticket</h1>
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:32px">
        <form method="POST" action="<?= $url('account/tickets/store') ?>">
            <?= \Core\Session::csrfField() ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                <div><label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;color:var(--wk-muted);margin-bottom:6px">Name *</label><input type="text" name="name" required value="<?= $e(($c['first_name']??'').' '.($c['last_name']??'')) ?>" style="<?= $is ?>"></div>
                <div><label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;color:var(--wk-muted);margin-bottom:6px">Email *</label><input type="email" name="email" required value="<?= $e($c['email']??'') ?>" data-wk-validate="email" style="<?= $is ?>"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
                <div>
                    <label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;color:var(--wk-muted);margin-bottom:6px">Phone</label>
                    <?php
                    $wkPhone = ['value' => $c['phone'] ?? '', 'inputStyle' => $is];
                    require __DIR__ . '/../partials/phone-field.php';
                    ?>
                </div>
                <div><label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;color:var(--wk-muted);margin-bottom:6px">Order # (optional)</label><input type="text" name="order_id" placeholder="WK-..." style="<?= $is ?>"></div>
            </div>
            <div style="margin-bottom:14px"><label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;color:var(--wk-muted);margin-bottom:6px">Subject *</label><input type="text" name="subject" required placeholder="What do you need help with?" style="<?= $is ?>"></div>
            <div style="margin-bottom:20px"><label style="display:block;font-size:12px;font-weight:800;text-transform:uppercase;color:var(--wk-muted);margin-bottom:6px">Message *</label><textarea name="message" required rows="5" placeholder="Describe your issue in detail..." style="<?= $is ?>;resize:vertical"></textarea></div>
            <button type="submit" style="width:100%;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));color:#fff;border:none;padding:16px;border-radius:10px;font-family:var(--font);font-size:16px;font-weight:800;cursor:pointer">Submit Ticket →</button>
        </form>
    </div>
</div></section>
