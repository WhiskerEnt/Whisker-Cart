<?php
$e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p);

// State, titles and the retry window are decided by the controller so the
// browser tab title matches the page.
$state         = $state ?? 'ok';
$title         = $statusTitle ?? 'Thank you';
$blurb         = $statusBlurb ?? '';
$needsPayment  = $needsPayment ?? false;
$paymentFailed = $paymentFailed ?? false;
$retryExpired  = $retryExpired ?? false;
$retryMinutes  = $retryMinutes ?? 0;
$retryExpires  = $retryExpires ?? 0;
$payData       = $payData ?? null;

// Amounts are shown in the currency the order was actually charged in, not the
// visitor's browsing currency — this is a receipt, not a price list.
$cur   = $order['currency'] ?? \App\Services\CurrencyService::baseCurrency();
$money = fn($v) => \App\Services\CurrencyService::format((float)$v, $cur);

$ship  = $order ? (json_decode($order['shipping_address'] ?? '{}', true) ?: []) : [];
$items = $items ?? [];
?>
<section class="wk-section" style="padding:56px 0">
    <div class="wk-container" style="max-width:560px">

        <div style="text-align:center;margin-bottom:32px">
            <?php if ($state === 'ok'): ?>
                <svg class="wk-status wk-status-ok" viewBox="0 0 52 52" role="img" aria-label="Order confirmed">
                    <circle class="wk-status-ring" cx="26" cy="26" r="24"/>
                    <path class="wk-status-mark" d="M15 27l7.5 7.5L37 20"/>
                </svg>
            <?php elseif ($state === 'pending'): ?>
                <svg class="wk-status wk-status-wait" viewBox="0 0 52 52" role="img" aria-label="Awaiting payment">
                    <circle class="wk-status-ring" cx="26" cy="26" r="24"/>
                    <path class="wk-status-mark" d="M26 14v13l8.5 5"/>
                </svg>
            <?php else: ?>
                <svg class="wk-status wk-status-bad" viewBox="0 0 52 52" role="img" aria-label="<?= $e($title) ?>">
                    <circle class="wk-status-ring" cx="26" cy="26" r="24"/>
                    <path class="wk-status-mark" d="M18 18l16 16"/>
                    <path class="wk-status-mark wk-status-mark-2" d="M34 18L18 34"/>
                </svg>
            <?php endif; ?>

            <h1 style="font-size:27px;font-weight:900;margin:20px 0 8px"><?= $e($title) ?></h1>
            <p style="color:var(--wk-muted);font-size:15px;margin-bottom:14px"><?= $e($blurb) ?></p>

            <?php if ($order): ?>
            <span style="font-family:var(--font-mono);font-weight:700;font-size:13px;background:var(--wk-purple-soft);color:var(--wk-purple);display:inline-block;padding:6px 16px;border-radius:20px">
                <?= $e($order['order_number']) ?>
            </span>
            <?php endif; ?>
        </div>

        <?php if ($state === 'pending' || $state === 'failed'): ?>
        <div style="background:#fef3c7;border:1px solid #f59e0b;border-radius:var(--radius-sm);padding:12px 14px;font-size:13px;color:#92400e;margin-bottom:20px;text-align:center">
            Your items are held for <strong id="retryTimer"><?= $retryMinutes ?></strong> more minutes. After that the order is released.
        </div>
        <div style="text-align:center;margin-bottom:28px">
            <?php if ($state === 'pending'): ?>
                <button id="payNowBtn" onclick="openRazorpay()" style="padding:15px 38px;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));color:#fff;border:none;border-radius:12px;font-weight:800;font-size:15px;cursor:pointer">Pay now →</button>
            <?php else: ?>
                <a href="<?= $url('checkout?retry=' . urlencode($order['order_number'])) ?>" style="display:inline-block;padding:15px 38px;background:linear-gradient(135deg,var(--wk-purple),var(--wk-pink));color:#fff;border-radius:12px;font-weight:800;font-size:15px;text-decoration:none">Retry payment →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($order && $items): ?>
        <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);overflow:hidden">
            <div style="padding:16px 20px;border-bottom:1px solid var(--wk-border);display:flex;justify-content:space-between;align-items:center">
                <span style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-muted)">Order summary</span>
                <span style="font-size:12px;color:var(--wk-muted)"><?= $e(date('j M Y', strtotime($order['created_at']))) ?></span>
            </div>

            <div style="padding:8px 20px">
                <?php foreach ($items as $it): ?>
                <div style="display:flex;align-items:center;gap:12px;padding:10px 0;font-size:14px">
                    <?php if (!empty($it['image'])): ?>
                        <img src="<?= $e($url('storage/uploads/products/' . $it['image'])) ?>" alt=""
                             style="width:48px;height:48px;border-radius:var(--radius-sm);object-fit:cover;background:var(--wk-bg);flex-shrink:0">
                    <?php else: ?>
                        <div style="width:48px;height:48px;border-radius:var(--radius-sm);background:var(--wk-bg);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0">📦</div>
                    <?php endif; ?>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;line-height:1.35"><?= $e($it['product_name']) ?></div>
                        <div style="font-size:12px;color:var(--wk-muted)">
                            <?php if (!empty($it['variant_label'])): ?><?= $e($it['variant_label']) ?> · <?php endif; ?>
                            Qty <?= (int)$it['quantity'] ?> × <?= $money($it['unit_price']) ?>
                        </div>
                    </div>
                    <div style="font-family:var(--font-mono);font-weight:700;white-space:nowrap"><?= $money($it['total_price']) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="padding:14px 20px;border-top:1px solid var(--wk-border);font-size:14px">
                <?php
                $rows = [
                    ['Subtotal', $order['subtotal'] ?? 0, false],
                    ['Shipping', $order['shipping_amount'] ?? 0, false],
                    ['Tax',      $order['tax_amount'] ?? 0, false],
                ];
                foreach ($rows as [$label, $amount, $neg]):
                    if ((float)$amount <= 0 && $label !== 'Subtotal') continue; ?>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;color:var(--wk-muted)">
                        <span><?= $label ?></span><span style="font-family:var(--font-mono)"><?= $money($amount) ?></span>
                    </div>
                <?php endforeach; ?>
                <?php if ((float)($order['discount_amount'] ?? 0) > 0): ?>
                    <div style="display:flex;justify-content:space-between;padding:4px 0;color:var(--wk-green)">
                        <span>Discount</span><span style="font-family:var(--font-mono)">−<?= $money($order['discount_amount']) ?></span>
                    </div>
                <?php endif; ?>
                <div style="display:flex;justify-content:space-between;padding:10px 0 0;margin-top:8px;border-top:1px solid var(--wk-border);font-weight:900;font-size:16px">
                    <span>Total</span><span style="font-family:var(--font-mono)"><?= $money($order['total']) ?></span>
                </div>
            </div>

            <?php
            // Full postal address, skipping any part the order does not carry.
            $addrLines = array_values(array_filter([
                $ship['line1'] ?? '',
                $ship['line2'] ?? '',
                trim(($ship['city'] ?? '') . ' ' . ($ship['zip'] ?? '')),
                $ship['state'] ?? '',
                $ship['country'] ?? '',
            ], fn($l) => trim((string)$l) !== ''));
            ?>
            <?php if ($addrLines || !empty($order['customer_email']) || !empty($order['customer_phone'])): ?>
            <div style="padding:16px 20px;border-top:1px solid var(--wk-border);display:grid;grid-template-columns:repeat(auto-fit,minmax(13rem,1fr));gap:16px;font-size:13px;line-height:1.7">
                <?php if ($addrLines): ?>
                <div>
                    <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">
                        <?= !empty($ship['pickup']) ? 'Collect from' : 'Delivering to' ?>
                    </div>
                    <div style="color:var(--wk-muted)">
                        <?php if (!empty($ship['name'])): ?><strong style="color:var(--wk-text)"><?= $e($ship['name']) ?></strong><br><?php endif; ?>
                        <?php foreach ($addrLines as $line): ?><?= $e($line) ?><br><?php endforeach; ?>
                        <?php if (!empty($ship['opening_hours'])): ?>🕐 <?= $e($ship['opening_hours']) ?><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($order['customer_email']) || !empty($order['customer_phone'])): ?>
                <div>
                    <div style="font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">Contact</div>
                    <div style="color:var(--wk-muted);word-break:break-word">
                        <?php if (!empty($order['customer_email'])): ?><?= $e($order['customer_email']) ?><br><?php endif; ?>
                        <?php if (!empty($order['customer_phone'])): ?><?= $e($order['customer_phone']) ?><?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <p style="text-align:center;font-size:13px;color:var(--wk-muted);margin-top:18px">
            Track this order any time at <a href="<?= $url('track') ?>" style="color:var(--wk-purple);font-weight:700">order tracking</a>.
        </p>
        <?php endif; ?>

        <div style="display:flex;gap:12px;justify-content:center;margin-top:24px">
            <a href="<?= $url('') ?>" style="padding:12px 24px;border:2px solid var(--wk-border);border-radius:8px;font-weight:800;font-size:14px;text-decoration:none;color:var(--wk-text)">Continue shopping</a>
        </div>
    </div>
</section>

<?php /* Matches the branch that renders #payNowBtn. */ ?>
<?php if ($needsPayment && !$paymentFailed && !$retryExpired): ?>
<!-- Razorpay Checkout -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
function openRazorpay() {
    const options = {
        key: '<?= $e($payData['key_id']) ?>',
        amount: <?= (int)$payData['amount'] ?>,
        currency: '<?= $e($payData['currency'] ?? 'INR') ?>',
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
        modal: { ondismiss: function() { const b = document.getElementById('payNowBtn'); if (b) b.textContent = 'Retry Payment →'; } }
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
