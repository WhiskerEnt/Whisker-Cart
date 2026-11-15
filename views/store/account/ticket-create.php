<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);
$c = $customer;
?>
<section class="wk-section"><div class="wk-container" style="max-width:700px">
    <a href="<?= $url('account/tickets') ?>" style="color:var(--wk-purple-ink);font-weight:700;font-size:13px;margin-bottom:16px;display:inline-block">← My Tickets</a>
    <h1 style="font-size:24px;font-weight:900;margin-bottom:24px">New Support Ticket</h1>
    <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:32px">
        <form method="POST" action="<?= $url('account/tickets/store') ?>">
            <?= \Core\Session::csrfField() ?>
            <div class="wk-cols-2" style="gap:14px;margin-bottom:14px">
                <div><label class="wk-eyebrow">Name *</label><input type="text" name="name" required value="<?= $e(($c['first_name']??'').' '.($c['last_name']??'')) ?>" class="wk-field-input-lg"></div>
                <div><label class="wk-eyebrow">Email *</label><input type="email" name="email" required value="<?= $e($c['email']??'') ?>" data-wk-validate="email" class="wk-field-input-lg"></div>
            </div>
            <div class="wk-cols-2" style="gap:14px;margin-bottom:14px">
                <div>
                    <label class="wk-eyebrow">Phone</label>
                    <?php
                    $wkPhone = ['value' => $c['phone'] ?? '', 'inputClass' => 'wk-field-input-lg'];
                    require __DIR__ . '/../partials/phone-field.php';
                    ?>
                </div>
                <div><label class="wk-eyebrow">Order # (optional)</label><input type="text" name="order_id" placeholder="WK-..." class="wk-field-input-lg"></div>
            </div>
            <div class="wk-mb-14"><label class="wk-eyebrow">Subject *</label><input type="text" name="subject" required placeholder="What do you need help with?" class="wk-field-input-lg"></div>
            <div class="wk-mb-20"><label class="wk-eyebrow">Message *</label><textarea name="message" required rows="5" placeholder="Describe your issue in detail..." class="wk-field-input-lg" style="resize:vertical"></textarea></div>
            <button type="submit" style="width:100%;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));color:#fff;border:none;padding:16px;border-radius:10px;font-family:var(--font);font-size:16px;font-weight:800;cursor:pointer">Submit Ticket →</button>
        </form>
    </div>
</div></section>
