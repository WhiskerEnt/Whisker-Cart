<?php
$e = fn($v) => \Core\View::e($v);
$url = fn($p) => \Core\View::url($p);
$price = fn($v) => \Core\View::price($v, $currency);
// Server is the authority on every line in the summary. The controller
// computed these in CheckoutController::computeTotals(); we just render them.
// Tax may be unknown at first paint (address not filled yet) — in that case
// $totals['tax_known'] is false and we show a placeholder instead of a number.
$countries = \App\Services\CurrencyService::countries();
$displayCurrency = $_SESSION['wk_display_currency'] ?? $baseCurrency ?? 'INR';
$isSameCurrency = ($displayCurrency === ($baseCurrency ?? 'INR'));

$showPrice = function($amount) use ($price, $displayCurrency, $baseCurrency, $isSameCurrency) {
    $base = $price($amount);
    if ($isSameCurrency) return $base;
    $converted = \App\Services\CurrencyService::convert($amount, $baseCurrency, $displayCurrency);
    $formatted = \App\Services\CurrencyService::format($converted, $displayCurrency);
    return $base . ' <span style="font-size:12px;color:var(--wk-muted);font-weight:500">≈ ' . $formatted . '</span>';
};

$is = 'width:100%;padding:10px 14px;border:2px solid var(--wk-border);border-radius:8px;font-family:var(--font);font-size:14px;font-weight:600;outline:none;transition:border-color .2s;';
$ls = 'display:block;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-muted);margin-bottom:4px';

$cust = $customer ?? null;
$addrs = $savedAddresses ?? [];
$defAddr = !empty($addrs) ? $addrs[0] : [];
?>

<section class="wk-section">
    <div class="wk-container" style="max-width:960px">
        <h1 style="font-size:24px;font-weight:900;margin-bottom:24px">Checkout</h1>

        <?php if (empty($cart['items'])): ?>
            <div style="text-align:center;padding:60px;color:var(--wk-muted)">
                <div style="font-size:48px;margin-bottom:12px;opacity:.4">🛒</div>
                <p style="font-weight:800;margin-bottom:8px">Your cart is empty</p>
                <a href="<?= $url('') ?>" style="color:var(--wk-purple);font-weight:700">Continue shopping →</a>
            </div>
        <?php else: ?>

        <form method="POST" action="<?= $url('checkout/process') ?>" style="display:grid;grid-template-columns:1.3fr 1fr;gap:28px">
            <?= \Core\Session::csrfField() ?>

            <div>
                <!-- Contact -->
                <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:28px;margin-bottom:20px">
                    <h2 style="font-size:17px;font-weight:900;margin-bottom:20px">Contact Information</h2>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <div><label style="<?= $ls ?>">First Name</label><input type="text" name="first_name" required value="<?= $e($cust['first_name']??'') ?>" placeholder="John" style="<?= $is ?>"></div>
                        <div><label style="<?= $ls ?>">Last Name</label><input type="text" name="last_name" required value="<?= $e($cust['last_name']??'') ?>" placeholder="Doe" style="<?= $is ?>"></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
                        <div><label style="<?= $ls ?>">Email</label><input type="email" name="email" required value="<?= $e($cust['email']??'') ?>" placeholder="john@example.com" style="<?= $is ?>"></div>
                        <div><label style="<?= $ls ?>">Phone</label><input type="tel" name="phone" value="<?= $e($cust['phone']??'') ?>" placeholder="+91 98765 43210" style="<?= $is ?>"></div>
                    </div>
                </div>

                <?php if (!empty($pickupEnabled)): ?>
                <!-- Delivery Method -->
                <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:28px;margin-bottom:20px">
                    <h2 style="font-size:17px;font-weight:900;margin-bottom:16px">Delivery Method</h2>
                    <label style="display:flex;align-items:center;gap:12px;padding:14px;border:2px solid var(--wk-purple);border-radius:8px;cursor:pointer;margin-bottom:10px;background:rgba(139,92,246,.03)" id="wkDelivShipLabel">
                        <input type="radio" name="delivery_method" value="shipping" checked onchange="wkDeliveryToggle()" style="accent-color:var(--wk-purple)">
                        <div><div style="font-weight:800;font-size:14px">🏠 Home Delivery</div><div style="font-size:12px;color:var(--wk-muted)">Delivered to your address</div></div>
                    </label>
                    <label style="display:flex;align-items:center;gap:12px;padding:14px;border:2px solid var(--wk-border);border-radius:8px;cursor:pointer" id="wkDelivPickupLabel">
                        <input type="radio" name="delivery_method" value="pickup" onchange="wkDeliveryToggle()" style="accent-color:var(--wk-purple)">
                        <div><div style="font-weight:800;font-size:14px">📦 Pickup Point / Locker</div><div style="font-size:12px;color:var(--wk-muted)">Collect from a nearby pickup location</div></div>
                    </label>
                    <div id="wkPickupBox" style="display:none;margin-top:16px">
                        <label style="<?= $ls ?>">Pickup Location</label>
                        <select name="pickup_location_id" id="pickup_location" onchange="wkPickupHoursHint();wkRecalcTotals()" style="<?= $is ?> cursor:pointer">
                            <?php foreach ($pickupLocations as $loc): ?>
                            <option value="<?= (int)$loc['id'] ?>" data-hours="<?= $e($loc['opening_hours'] ?? '') ?>">
                                <?= $e($loc['name']) ?><?= $loc['carrier'] ? ' · ' . $e($loc['carrier']) : '' ?> — <?= $e($loc['address_line1']) ?>, <?= $e($loc['city']) ?> <?= $e($loc['zip']) ?> (<?= $e($loc['country']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div style="font-size:12px;color:var(--wk-muted);margin-top:6px" id="wkPickupHours"></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Shipping Address -->
                <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:28px;margin-bottom:20px" id="wkShipCard">
                    <h2 style="font-size:17px;font-weight:900;margin-bottom:20px">Shipping Address</h2>

                    <?php if (!empty($addrs)): ?>
                    <div style="margin-bottom:16px">
                        <label style="<?= $ls ?>">Use Saved Address</label>
                        <select id="savedAddrShip" onchange="fillAddress('ship')" style="<?= $is ?> cursor:pointer">
                            <option value="">Enter new address</option>
                            <?php foreach ($addrs as $addr): ?>
                            <option value="<?= $addr['id'] ?>"
                                data-line1="<?= $e($addr['address_line1']) ?>"
                                data-line2="<?= $e($addr['address_line2']??'') ?>"
                                data-city="<?= $e($addr['city']) ?>"
                                data-state="<?= $e($addr['state']) ?>"
                                data-zip="<?= $e($addr['postal_code']) ?>"
                                data-country="<?= $e($addr['country']) ?>"
                                <?= $addr['is_default']?'selected':'' ?>>
                                <?= $e($addr['label']) ?> — <?= $e($addr['address_line1']) ?>, <?= $e($addr['city']) ?> <?= $addr['is_default']?'(Default)':'' ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div><label style="<?= $ls ?>">Address</label><input type="text" name="address1" id="ship_addr" required value="<?= $e($defAddr['address_line1']??'') ?>" placeholder="123 Main Street" style="<?= $is ?>"></div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
                        <div><label style="<?= $ls ?>">City</label><input type="text" name="city" id="ship_city" required value="<?= $e($defAddr['city']??'') ?>" style="<?= $is ?>"></div>
                        <div><label style="<?= $ls ?>">State</label><input type="text" name="state" id="ship_state" required value="<?= $e($defAddr['state']??'') ?>" style="<?= $is ?>"></div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
                        <div><label style="<?= $ls ?>">Country</label><select name="country" id="ship_country" style="<?= $is ?> cursor:pointer">
                            <?php foreach ($countries as $code => $info): ?><option value="<?= $code ?>" <?= $code===($defAddr['country']??'IN')?'selected':'' ?>><?= $e($info['name']) ?></option><?php endforeach; ?>
                        </select></div>
                        <div><label style="<?= $ls ?>">ZIP / Postal Code</label><input type="text" name="zip" id="ship_zip" required value="<?= $e($defAddr['postal_code']??'') ?>" style="<?= $is ?>"></div>
                    </div>
                </div>

                <!-- Billing Address -->
                <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:28px;margin-bottom:20px">
                    <h2 style="font-size:17px;font-weight:900;margin-bottom:12px">Billing Address</h2>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;font-size:14px;margin-bottom:16px" id="wkSameRow">
                        <input type="checkbox" id="sameAsShipping" checked onchange="toggleBilling()"> <span id="wkSameLabel">Same as shipping address</span>
                    </label>
                    <div id="billingFields" style="display:none">
                        <?php if (!empty($addrs)): ?>
                        <div style="margin-bottom:16px">
                            <label style="<?= $ls ?>">Use Saved Address</label>
                            <select id="savedAddrBill" onchange="fillAddress('bill')" style="<?= $is ?> cursor:pointer">
                                <option value="">Enter new address</option>
                                <?php foreach ($addrs as $addr): ?>
                                <option value="<?= $addr['id'] ?>"
                                    data-line1="<?= $e($addr['address_line1']) ?>"
                                    data-city="<?= $e($addr['city']) ?>"
                                    data-state="<?= $e($addr['state']) ?>"
                                    data-zip="<?= $e($addr['postal_code']) ?>"
                                    data-country="<?= $e($addr['country']) ?>">
                                    <?= $e($addr['label']) ?> — <?= $e($addr['address_line1']) ?>, <?= $e($addr['city']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>
                        <div><label style="<?= $ls ?>">Address</label><input type="text" name="billing_address1" id="bill_addr" placeholder="123 Main Street" style="<?= $is ?>"></div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
                            <div><label style="<?= $ls ?>">City</label><input type="text" name="billing_city" id="bill_city" style="<?= $is ?>"></div>
                            <div><label style="<?= $ls ?>">State</label><input type="text" name="billing_state" id="bill_state" style="<?= $is ?>"></div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:14px">
                            <div><label style="<?= $ls ?>">Country</label><select name="billing_country" id="bill_country" style="<?= $is ?> cursor:pointer">
                                <?php foreach ($countries as $code => $info): ?><option value="<?= $code ?>" <?= $code==='IN'?'selected':'' ?>><?= $e($info['name']) ?></option><?php endforeach; ?>
                            </select></div>
                            <div><label style="<?= $ls ?>">ZIP</label><input type="text" name="billing_zip" id="bill_zip" style="<?= $is ?>"></div>
                        </div>
                    </div>
                </div>

                <!-- Payment -->
                <?php if (!empty($gateways)): ?>
                <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:28px">
                    <h2 style="font-size:17px;font-weight:900;margin-bottom:16px">Payment Method</h2>
                    <?php foreach ($gateways as $i => $gw): ?>
                    <label style="display:flex;align-items:center;gap:12px;padding:14px;border:2px solid var(--wk-border);border-radius:8px;cursor:pointer;margin-bottom:10px;transition:all .2s" onclick="this.parentElement.querySelectorAll('label').forEach(l=>{l.style.borderColor='var(--wk-border)';l.style.background='transparent'});this.style.borderColor='var(--wk-purple)';this.style.background='rgba(139,92,246,.03)'">
                        <input type="radio" name="payment_gateway" value="<?= $gw['gateway_code'] ?>" <?= $i===0?'checked':'' ?> style="accent-color:var(--wk-purple)">
                        <div><div style="font-weight:800;font-size:14px"><?= $e($gw['display_name']) ?></div><div style="font-size:12px;color:var(--wk-muted)"><?= $e($gw['description']) ?></div></div>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Order Summary -->
            <div>
                <div style="background:var(--wk-surface);border:2px solid var(--wk-border);border-radius:var(--radius);padding:28px;position:sticky;top:84px">
                    <h2 style="font-size:17px;font-weight:900;margin-bottom:16px">Order Summary</h2>

                    <?php foreach ($cart['items'] as $item): ?>
                    <div style="display:flex;gap:12px;padding:12px 0;border-bottom:1px solid var(--wk-border);font-size:14px">
                        <div style="width:52px;height:52px;border-radius:8px;overflow:hidden;background:var(--wk-bg);flex-shrink:0;border:1px solid var(--wk-border)">
                            <?php if (!empty($item['image'])): ?>
                                <img src="<?= $url('storage/uploads/products/'.$item['image']) ?>" style="width:100%;height:100%;object-fit:cover">
                            <?php else: ?>
                                <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:20px;opacity:.3">📦</div>
                            <?php endif; ?>
                        </div>
                        <div style="flex:1">
                            <div style="font-weight:700"><?= $e($item['name']) ?></div>
                            <?php if (!empty($item['variant_label'])): ?>
                                <div style="font-size:12px;color:var(--wk-purple);font-weight:700"><?= $e($item['variant_label']) ?></div>
                            <?php endif; ?>
                            <div style="font-size:12px;color:var(--wk-muted)">Qty: <?= $item['quantity'] ?></div>
                        </div>
                        <div style="font-family:var(--font-mono);font-weight:700;text-align:right;white-space:nowrap"><?= $showPrice($item['unit_price'] * $item['quantity']) ?></div>
                    </div>
                    <?php endforeach; ?>

                    <div style="margin-top:16px;font-size:14px" id="wk-summary">
                        <div style="display:flex;justify-content:space-between;padding:6px 0">
                            <span style="color:var(--wk-muted)">Subtotal</span>
                            <span style="font-weight:700" id="wk-sum-subtotal"><?= $showPrice($totals['subtotal']) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0" id="wk-sum-discount-row" <?= $totals['discount'] > 0 ? '' : 'hidden' ?>>
                            <span style="color:var(--wk-muted)">Discount<?= !empty($totals['discount_code']) ? ' <span style="color:var(--wk-purple);font-weight:700">(' . $e($totals['discount_code']) . ')</span>' : '' ?></span>
                            <span style="font-weight:700;color:#16a34a" id="wk-sum-discount">− <?= $showPrice($totals['discount']) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0">
                            <span style="color:var(--wk-muted)" id="wk-sum-tax-label"><?= $totals['tax_known'] ? $e($totals['tax_label'] ?: 'Tax') : 'Tax' ?></span>
                            <span style="font-weight:700" id="wk-sum-tax"><?= $totals['tax_known'] ? $showPrice($totals['tax']) : '<span style="color:var(--wk-muted);font-weight:500;font-size:13px">Calculated at next step</span>' ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:6px 0">
                            <span style="color:var(--wk-muted)" id="wk-sum-shipping-label">Shipping</span>
                            <span style="font-weight:700" id="wk-sum-shipping"><?= $showPrice($totals['shipping']) ?></span>
                        </div>
                        <div style="display:flex;justify-content:space-between;padding:12px 0 0;margin-top:8px;border-top:2px solid var(--wk-border);font-size:20px">
                            <span style="font-weight:900">Total</span>
                            <span style="font-weight:900;font-family:var(--font-mono)" id="wk-sum-total"><?= $showPrice($totals['total']) ?></span>
                        </div>
                    </div>

                    <button type="submit" class="wk-checkout-btn" style="margin-top:20px" id="wk-pay-btn">Pay <?= $price($totals['total']) ?> →</button>
                </div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>

<script>
const WK_CHECKOUT_URL = <?= json_encode($url('checkout/calculate')) ?>;
const WK_PAY_PREFIX   = <?= json_encode('Pay ') ?>;

function fillAddress(prefix) {
    const sel = document.getElementById(prefix === 'ship' ? 'savedAddrShip' : 'savedAddrBill');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    const f = (id, key) => { const el = document.getElementById(id); if(el) el.value = opt.value ? (opt.dataset[key]||'') : ''; };
    f(prefix+'_addr', 'line1');
    f(prefix+'_city', 'city');
    f(prefix+'_state', 'state');
    f(prefix+'_zip', 'zip');
    const cs = document.getElementById(prefix+'_country');
    if (cs && opt.dataset.country) cs.value = opt.dataset.country;
    if (prefix === 'ship') wkRecalcTotals();
}

function toggleBilling() {
    document.getElementById('billingFields').style.display = document.getElementById('sameAsShipping').checked ? 'none' : 'block';
}

// ── Delivery method: home shipping vs pickup point ─────────────────
function wkDeliveryMethod() {
    const sel = document.querySelector('input[name="delivery_method"]:checked');
    return sel ? sel.value : 'shipping';
}

function wkDeliveryToggle() {
    const isPickup = wkDeliveryMethod() === 'pickup';
    const shipCard = document.getElementById('wkShipCard');
    const pickupBox = document.getElementById('wkPickupBox');
    const shipLabel = document.getElementById('wkDelivShipLabel');
    const pickLabel = document.getElementById('wkDelivPickupLabel');

    if (shipCard) shipCard.style.display = isPickup ? 'none' : 'block';
    if (pickupBox) pickupBox.style.display = isPickup ? 'block' : 'none';
    // Address inputs must not block submit while hidden.
    ['ship_addr', 'ship_city', 'ship_state', 'ship_zip'].forEach(function(id) {
        const el = document.getElementById(id);
        if (el) el.required = !isPickup;
    });
    // "Same as shipping" makes no sense for a locker — the billing section
    // becomes a plain optional billing-address form while pickup is active.
    const sameRow = document.getElementById('wkSameRow');
    const sameCheck = document.getElementById('sameAsShipping');
    if (sameRow && sameCheck) {
        if (isPickup) {
            sameRow.style.display = 'none';
            sameCheck.checked = false;
        } else {
            sameRow.style.display = 'flex';
            sameCheck.checked = true;
        }
        toggleBilling();
    }
    // Highlight the selected option + relabel the summary row.
    if (shipLabel && pickLabel) {
        shipLabel.style.borderColor = isPickup ? 'var(--wk-border)' : 'var(--wk-purple)';
        shipLabel.style.background  = isPickup ? 'transparent' : 'rgba(139,92,246,.03)';
        pickLabel.style.borderColor = isPickup ? 'var(--wk-purple)' : 'var(--wk-border)';
        pickLabel.style.background  = isPickup ? 'rgba(139,92,246,.03)' : 'transparent';
    }
    const sumLabel = document.getElementById('wk-sum-shipping-label');
    if (sumLabel) sumLabel.textContent = isPickup ? 'Pickup' : 'Shipping';
    wkPickupHoursHint();
    wkRecalcTotals();
}

function wkPickupHoursHint() {
    const sel = document.getElementById('pickup_location');
    const hint = document.getElementById('wkPickupHours');
    if (!sel || !hint) return;
    const opt = sel.options[sel.selectedIndex];
    const hours = opt ? (opt.dataset.hours || '') : '';
    hint.textContent = hours ? '🕐 ' + hours : '';
}

// ── Server-authoritative totals recompute ──────────────────────────
// Whenever the shipping address changes, ask the server for fresh totals.
// The server is the single source of truth — this just keeps the display
// in sync so the customer never sees a different number than they pay.
let wkRecalcTimer = null;
let wkRecalcInflight = null;
function wkRecalcTotals() {
    if (wkRecalcTimer) clearTimeout(wkRecalcTimer);
    wkRecalcTimer = setTimeout(wkDoRecalc, 200);
}

async function wkDoRecalc() {
    const country = (document.getElementById('ship_country')||{}).value || '';
    const state   = (document.getElementById('ship_state')||{}).value   || '';
    const method  = wkDeliveryMethod();
    const pickupId = (document.getElementById('pickup_location')||{}).value || '';
    // For home delivery we need at least a country/state to compute against;
    // for pickup the server derives everything from the pickup location.
    if (method !== 'pickup' && !country && !state) return;

    // Coalesce concurrent calls — only the latest result wins.
    const myCall = wkRecalcInflight = Symbol('recalc');
    try {
        const res = await fetch(WK_CHECKOUT_URL, {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify({country: country, state: state, delivery_method: method, pickup_location_id: pickupId}),
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        if (wkRecalcInflight !== myCall) return; // a newer call superseded us

        const f = data.formatted || {};
        const setText = (id, txt) => { const el = document.getElementById(id); if (el && txt != null) el.textContent = txt; };
        const setHtml = (id, html) => { const el = document.getElementById(id); if (el && html != null) el.innerHTML = html; };
        setText('wk-sum-tax-label', data.tax_known ? (data.tax_label || 'Tax') : 'Tax');
        const taxEl = document.getElementById('wk-sum-tax');
        if (taxEl) {
            if (data.tax_known) {
                taxEl.innerHTML = f.tax || '';
            } else {
                taxEl.innerHTML = '<span style="color:var(--wk-muted);font-weight:500;font-size:13px">Calculated at next step</span>';
            }
        }
        setHtml('wk-sum-subtotal', f.subtotal);
        setHtml('wk-sum-shipping', f.shipping);
        setHtml('wk-sum-total',    f.total);

        // Discount row — show or hide based on result. We also refresh the
        // label so the coupon code stays in sync if the server dropped a
        // since-expired coupon.
        const discRow = document.getElementById('wk-sum-discount-row');
        if (discRow) {
            if ((data.discount || 0) > 0) {
                discRow.removeAttribute('hidden');
                setHtml('wk-sum-discount', '\u2212 ' + (f.discount || ''));
            } else {
                discRow.setAttribute('hidden', '');
            }
        }

        // Pay button uses plain total (no conversion suffix).
        const payBtn = document.getElementById('wk-pay-btn');
        if (payBtn && f.total_plain) payBtn.textContent = WK_PAY_PREFIX + f.total_plain + ' \u2192';
    } catch (_) {
        // Network/server error — leave the display untouched. The customer
        // can still submit; process() recomputes server-side and will reject
        // mismatched/invalid totals.
    }
}

// Recompute on address change. We listen to both 'change' (for the country
// <select>) and 'blur' (for free-text fields) to cover every input type.
['ship_country','ship_state','ship_zip'].forEach(function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('change', wkRecalcTotals);
    el.addEventListener('blur',   wkRecalcTotals);
});

// Auto-fill default on load
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('savedAddrShip');
    if (sel && sel.value) {
        fillAddress('ship');
    } else {
        // No saved address but the country <select> has a default value —
        // kick off an initial calc so the tax line is filled if possible.
        wkRecalcTotals();
    }
});
</script>