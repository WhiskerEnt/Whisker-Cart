<?php
$e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);
$payData = \Core\Session::get('wk_payment_data');
$needsPayment = $order && $order['payment_status'] !== 'captured' && $payData && ($payData['gateway'] ?? '') === 'razorpay';
$paymentFailed = $order && ($order['status'] === 'payment_failed' || $order['payment_status'] === 'failed');
if ($payData) \Core\Session::remove('wk_payment_data');

// Calculate time remaining for retry (15 minutes from order creation)
$retryExpires = 0;
$retryMinutes = 0;
if ($order) {
    $retryExpires = strtotime($order['created_at']) + 900; // 15 minutes
    $retryMinutes = max(0, (int)ceil(($retryExpires - time()) / 60));
}
$retryExpired = $retryMinutes <= 0 && ($paymentFailed || $needsPayment);
?>
<section class="wk-section" style="text-align:center;padding:80px 0">
    <div class="wk-container" style="max-width:500px">

        <?php if ($retryExpired): ?>
            <!-- Retry window expired -->
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:36px;color:#fff">✗</div>
            <h1 style="font-size:28px;font-weight:900;margin-bottom:8px">Order Expired</h1>
            <p style="color:var(--wk-muted);margin-bottom:16px;font-size:16px">Payment was not completed within 15 minutes. Your stock has been released.</p>
            <p style="font-family:var(--font-mono);font-weight:700;font-size:14px;background:#fef2f2;color:#ef4444;display:inline-block;padding:6px 16px;border-radius:20px;margin-bottom:24px">
                <?= $e($order['order_number']) ?>
            </p>

        <?php elseif ($paymentFailed): ?>
            <!-- Payment failed — show retry -->
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#ef4444);display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:36px;color:#fff">⚠️</div>
            <h1 style="font-size:28px;font-weight:900;margin-bottom:8px">Payment Failed</h1>
            <p style="color:var(--wk-muted);margin-bottom:8px;font-size:16px">Your order is reserved. Retry payment within <strong id="retryTimer"><?= $retryMinutes ?></strong> minutes.</p>
            <p style="font-family:var(--font-mono);font-weight:700;font-size:14px;background:var(--wk-purple-soft);color:var(--wk-purple);display:inline-block;padding:6px 16px;border-radius:20px;margin-bottom:24px">
                <?= $e($order['order_number']) ?>
            </p>
            <div style="margin-top:12px">
                <a href="<?= $url('checkout?retry=' . urlencode($order['order_number'])) ?>" style="display:inline-block;padding:16px 40px;background:linear-gradient(135deg,var(--wk-purple),#ec4899);color:#fff;border-radius:12px;font-weight:800;font-size:16px;text-decoration:none">Retry Payment →</a>
            </div>
            <div style="margin-top:12px;background:#fef3c7;border:1px solid #f59e0b;border-radius:10px;padding:12px;font-size:13px;color:#92400e">
                Stock is reserved for 15 minutes. After that, your order will be released and items may become unavailable.
            </div>

        <?php elseif ($needsPayment): ?>
            <!-- Razorpay modal payment -->
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#f97316);display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:36px;color:#fff">💳</div>
            <h1 style="font-size:28px;font-weight:900;margin-bottom:8px">Complete Payment</h1>
            <p style="color:var(--wk-muted);margin-bottom:8px;font-size:16px">Your order is created. Complete payment within <strong id="retryTimer"><?= $retryMinutes ?></strong> minutes.</p>
            <p style="font-family:var(--font-mono);font-weight:700;font-size:14px;background:var(--wk-purple-soft);color:var(--wk-purple);display:inline-block;padding:6px 16px;border-radius:20px;margin-bottom:24px">
                <?= $e($order['order_number']) ?>
            </p>
            <div style="margin-top:12px">
                <button id="payNowBtn" onclick="openRazorpay()" style="padding:16px 40px;background:linear-gradient(135deg,var(--wk-purple),#ec4899);color:#fff;border:none;border-radius:12px;font-weight:800;font-size:16px;cursor:pointer">Pay Now →</button>
            </div>

        <?php else: ?>
            <!-- Order success -->
            <div style="width:80px;height:80px;border-radius:50%;background:linear-gradient(135deg,var(--wk-green),#34d399);display:flex;align-items:center;justify-content:center;margin:0 auto 24px;font-size:36px;color:#fff;animation:successPop .5s cubic-bezier(.34,1.56,.64,1)">🎉</div>
            <h1 style="font-size:28px;font-weight:900;margin-bottom:8px">Order Placed!</h1>
            <?php if ($order): ?>
                <p style="color:var(--wk-muted);margin-bottom:8px;font-size:16px">Thank you for your purchase.</p>
                <p style="font-family:var(--font-mono);font-weight:700;font-size:14px;background:var(--wk-purple-soft);color:var(--wk-purple);display:inline-block;padding:6px 16px;border-radius:20px;margin-bottom:24px">
                    <?= $e($order['order_number']) ?>
                </p>
            <?php else: ?>
                <p style="color:var(--wk-muted);margin-bottom:24px">Thank you for your order!</p>
            <?php endif; ?>
        <?php endif; ?>

        <div style="display:flex;gap:12px;justify-content:center;margin-top:16px">
            <a href="<?= $url('') ?>" style="padding:12px 24px;border:2px solid var(--wk-border);border-radius:8px;font-weight:800;font-size:14px;transition:all .2s;text-decoration:none;color:var(--wk-text)">Continue Shopping</a>
        </div>
    </div>
</section>
<style>@keyframes successPop{from{transform:scale(0)}to{transform:scale(1)}}</style>

<?php if ($needsPayment && !$retryExpired): ?>
<!-- Razorpay Checkout -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
function openRazorpay() {
    const options = {
        key: '<?= $e($payData['key_id']) ?>',
        amount: <?= (int)$payData['amount'] ?>,
        currency: 'INR',
        name: document.title,
        order_id: '<?= $e($payData['gateway_order_id']) ?>',
        prefill: {
            email: '<?= $e($payData['email'] ?? '') ?>',
            contact: '<?= $e($payData['phone'] ?? '') ?>',
            name: '<?= $e($payData['name'] ?? '') ?>'
        },
        handler: function(response) {
            fetch('<?= $url('checkout/verify-payment') ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    razorpay_payment_id: response.razorpay_payment_id,
                    razorpay_order_id: response.razorpay_order_id,
                    razorpay_signature: response.razorpay_signature,
                    order_id: <?= (int)$payData['order_id'] ?>
                })
            }).then(r => r.json()).then(data => {
                if (data.success) location.reload();
                else alert('Payment verification failed. Please contact support.');
            });
        },
        modal: { ondismiss: function() { document.getElementById('payNowBtn').textContent = 'Retry Payment →'; } }
    };
    new Razorpay(options).open();
}
document.addEventListener('DOMContentLoaded', () => setTimeout(openRazorpay, 500));
</script>
<?php endif; ?>

<?php if (($needsPayment || $paymentFailed) && !$retryExpired): ?>
<!-- Countdown timer -->
<script>
(function() {
    const expires = <?= $retryExpires ?>;
    const el = document.getElementById('retryTimer');
    if (!el) return;
    setInterval(() => {
        const left = Math.max(0, expires - Math.floor(Date.now() / 1000));
        const m = Math.floor(left / 60), s = left % 60;
        el.textContent = m + ':' + String(s).padStart(2, '0');
        if (left <= 0) location.reload();
    }, 1000);
})();
</script>
<?php endif; ?>

<?php if (!$needsPayment && !$paymentFailed && !$retryExpired): ?>
<script>document.addEventListener('DOMContentLoaded', () => { if (typeof WhiskerStore !== 'undefined') WhiskerStore.confetti(); });</script>
<?php endif; ?>
