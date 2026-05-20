<?php $e=fn($v)=>\Core\View::e($v); $c=$customer; ?>
<a href="<?= \Core\View::url('admin/customers') ?>" style="color:var(--wk-purple);font-weight:700;font-size:13px;text-decoration:none;margin-bottom:16px;display:inline-block">← Back to Customers</a>
<div class="wk-card">
    <div class="wk-card-header"><h2><?= $e($c['first_name'].' '.$c['last_name']) ?></h2></div>
    <div class="wk-card-body">
        <p><strong>Email:</strong> <?= $e($c['email']) ?></p>
        <p><strong>Phone:</strong> <?= $e($c['phone']??'—') ?></p>
        <p><strong>Orders:</strong> <?= $c['total_orders'] ?></p>
        <p><strong>Total Spent:</strong> <?= \Core\View::price($c['total_spent']) ?></p>
        <p><strong>Joined:</strong> <?= date('M j, Y', strtotime($c['created_at'])) ?></p>
    </div>
</div>

<?php if ((int)($c['is_active'] ?? 1) === 1): ?>
<div class="wk-card" style="margin-top:24px;border-color:#fecaca">
    <div class="wk-card-header" style="background:#fef2f2"><h2 style="color:#991b1b">GDPR — Right to Erasure</h2></div>
    <div class="wk-card-body">
        <p style="font-size:13px;color:var(--wk-text-muted);margin-bottom:16px">
            Pseudonymize this customer to honor a GDPR deletion request.
            Personal data (name, email, phone, addresses, cart) is scrubbed.
            Order records are retained with anonymized contact fields for
            tax/financial compliance — they're typically required to be
            kept for several years even after a deletion request.
        </p>
        <form method="POST" action="<?= \Core\View::url('admin/customers/'.$c['id'].'/pseudonymize') ?>"
              onsubmit="return confirm('Pseudonymize this customer? Their personal data will be wiped and they will no longer be able to log in. Order records will be retained with anonymized contact details. This cannot be undone.')"
              style="margin:0">
            <?= \Core\Session::csrfField() ?>
            <button type="submit" class="wk-btn" style="background:#dc2626;color:#fff;font-size:13px;padding:8px 16px;border-radius:6px;border:none;cursor:pointer;font-weight:700">Pseudonymize Customer</button>
        </form>
    </div>
</div>
<?php else: ?>
<div class="wk-card" style="margin-top:24px;border-color:#e5e7eb">
    <div class="wk-card-body" style="font-size:13px;color:var(--wk-text-muted)">
        This customer has been pseudonymized — order records are retained for compliance, but personal data has been scrubbed.
    </div>
</div>
<?php endif; ?>
