<?php
$url=fn($p)=>\Core\View::url($p);
$s=$settings;
$v=fn($g,$k)=>htmlspecialchars($s[$g][$k]??'');
$shippingMethod = $s['shipping']['method'] ?? 'flat';
$carriers = [];
try { $carriers = \Core\Database::fetchAll("SELECT * FROM wk_shipping_carriers WHERE is_active=1 ORDER BY name"); } catch(\Exception $e) {}
?>
<div style="display:flex;gap:12px;margin-bottom:24px">
    <a href="<?= $url('admin/shipping') ?>" class="wk-btn wk-btn-secondary">🚚 Manage Carriers</a>
    <a href="<?= $url('admin/shipping/zones') ?>" class="wk-btn wk-btn-secondary">🌍 Shipping Zones</a>
</div>

<form method="POST" action="<?= $url('admin/shipping/settings/update') ?>">
    <?= \Core\Session::csrfField() ?>
<?php
// Where the store posts to. Most shops only ever need "my own country",
// so that is the first and default answer rather than a country checklist.
$CS          = \App\Services\CountryService::class;
$shipMode    = $s['shipping']['ship_mode'] ?? 'domestic';
$shipPicked  = array_filter(array_map('trim', explode(',', (string) ($s['shipping']['ship_countries'] ?? ''))));
$storeCode   = $CS::storeCountry();
$allCountries = $CS::all();
?>
<div class="wk-card" style="max-width:900px;margin-bottom:20px">
    <div class="wk-card-header">
        <h2>🌍 Shipping Destinations</h2>
        <span style="font-size:12px;color:var(--wk-text-muted);font-weight:600" id="destCount"></span>
    </div>
    <div class="wk-card-body">
        <p style="font-size:12px;color:var(--wk-text-muted);margin-bottom:16px;line-height:1.6">
            Checkout only offers the countries you post to, and refuses any other even if the form is edited.
            Billing addresses are not restricted — a customer can pay from anywhere.
        </p>

        <div class="wk-ship-modes">
            <?php
            $modes = [
                'domestic' => ['Domestic only', 'Ship within ' . $CS::name($storeCode) . ' only'],
                'selected' => ['Selected countries', 'Pick the countries you post to'],
                'all'      => ['Everywhere', 'Offer every country at checkout'],
            ];
            foreach ($modes as $key => [$label, $hint]): ?>
                <label class="wk-ship-mode<?= $shipMode === $key ? ' is-active' : '' ?>">
                    <input type="radio" name="shipping_ship_mode" value="<?= $key ?>" <?= $shipMode === $key ? 'checked' : '' ?>>
                    <span class="wk-ship-mode-label"><?= htmlspecialchars($label) ?></span>
                    <span class="wk-ship-mode-hint"><?= htmlspecialchars($hint) ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <?php if (!\App\Services\CountryService::exists((string) ($s['general']['store_country'] ?? ''))): ?>
            <div style="background:var(--wk-bg);border-radius:var(--radius-sm);padding:11px 13px;font-size:12px;color:var(--wk-text-muted);margin-top:14px;line-height:1.6">
                Your store country is not set, so domestic shipping assumes <strong><?= htmlspecialchars($CS::name($storeCode)) ?></strong>.
                Set it under <a href="<?= $url('admin/settings') ?>" style="color:var(--wk-purple);font-weight:700">Settings → Store Location</a>.
            </div>
        <?php endif; ?>

        <div id="countryPicker" style="margin-top:20px;<?= $shipMode === 'selected' ? '' : 'display:none' ?>">
            <div class="wk-ship-picker-bar">
                <input type="search" id="countrySearch" class="wk-input" placeholder="Search countries..." autocomplete="off" style="flex:1;min-width:180px">
                <button type="button" class="wk-btn wk-btn-secondary wk-btn-sm" data-pick="eu">EU</button>
                <button type="button" class="wk-btn wk-btn-secondary wk-btn-sm" data-pick="all">All</button>
                <button type="button" class="wk-btn wk-btn-secondary wk-btn-sm" data-pick="none">None</button>
            </div>
            <div class="wk-country-grid" id="countryGrid">
                <?php foreach ($allCountries as $code => $name):
                    $on = in_array($code, $shipPicked, true); ?>
                    <label class="wk-country<?= $on ? ' is-on' : '' ?>" data-name="<?= htmlspecialchars(strtolower($name)) ?>" data-code="<?= htmlspecialchars(strtolower($code)) ?>">
                        <input type="checkbox" name="ship_country[]" value="<?= htmlspecialchars($code) ?>" <?= $on ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($name) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p id="countryNoMatch" style="font-size:12px;color:var(--wk-text-muted);margin:12px 0 0;display:none">No country matches that search.</p>
        </div>
    </div>
</div>

<script>
(function () {
    var picker = document.getElementById('countryPicker');
    if (!picker) return;
    var grid    = document.getElementById('countryGrid');
    var search  = document.getElementById('countrySearch');
    var count   = document.getElementById('destCount');
    var noMatch = document.getElementById('countryNoMatch');
    var rows    = [].slice.call(grid.querySelectorAll('.wk-country'));
    var EU      = <?= json_encode(\App\Services\CountryService::EU) ?>;

    function refreshCount() {
        var mode = document.querySelector('[name=shipping_ship_mode]:checked');
        if (!mode) return;
        if (mode.value === 'all')      { count.textContent = 'Every country'; return; }
        if (mode.value === 'domestic') { count.textContent = <?= json_encode($CS::name($storeCode)) ?>; return; }
        var n = rows.filter(function (r) { return r.querySelector('input').checked; }).length;
        count.textContent = n + (n === 1 ? ' country' : ' countries');
    }

    document.querySelectorAll('[name=shipping_ship_mode]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            picker.style.display = radio.value === 'selected' ? '' : 'none';
            document.querySelectorAll('.wk-ship-mode').forEach(function (l) {
                l.classList.toggle('is-active', l.querySelector('input').checked);
            });
            refreshCount();
        });
    });

    grid.addEventListener('change', function (e) {
        if (e.target.type !== 'checkbox') return;
        e.target.closest('.wk-country').classList.toggle('is-on', e.target.checked);
        refreshCount();
    });

    search.addEventListener('input', function () {
        var q = search.value.trim().toLowerCase();
        var shown = 0;
        rows.forEach(function (r) {
            var hit = q === '' || r.dataset.name.indexOf(q) > -1 || r.dataset.code === q;
            r.style.display = hit ? '' : 'none';
            if (hit) shown++;
        });
        noMatch.style.display = shown === 0 ? '' : 'none';
    });

    document.querySelectorAll('[data-pick]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var what = btn.getAttribute('data-pick');
            rows.forEach(function (r) {
                var box = r.querySelector('input');
                // Only touch what the search is currently showing, so a filtered
                // "All" does what it looks like it does.
                if (r.style.display === 'none') return;
                box.checked = what === 'all' ? true
                            : what === 'none' ? false
                            : EU.indexOf(box.value) > -1;
                r.classList.toggle('is-on', box.checked);
            });
            refreshCount();
        });
    });

    refreshCount();
})();
</script>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px">
        <div>
            <div class="wk-card">
                <div class="wk-card-header"><h2>Shipping Method</h2></div>
                <div class="wk-card-body">
                    <div class="wk-form-group">
                        <label>Method</label>
                        <select name="shipping_method" class="wk-select" id="shippingMethod" onchange="toggleShipping()">
                            <option value="flat" <?= $shippingMethod==='flat'?'selected':'' ?>>Flat Rate</option>
                            <option value="free" <?= $shippingMethod==='free'?'selected':'' ?>>Free Shipping</option>
                            <option value="free_above" <?= $shippingMethod==='free_above'?'selected':'' ?>>Free Above Threshold</option>
                            <option value="per_item" <?= $shippingMethod==='per_item'?'selected':'' ?>>Per Item</option>
                            <option value="weight" <?= $shippingMethod==='weight'?'selected':'' ?>>Weight Based</option>
                        </select>
                    </div>

                    <!-- Flat Rate -->
                    <div class="sf" data-m="flat">
                        <div class="wk-form-group">
                            <label>Flat Rate Amount</label>
                            <input type="number" step="0.01" name="shipping_flat_rate" class="wk-input" value="<?= $v('shipping','flat_rate') ?: $v('checkout','shipping_flat_rate') ?>" placeholder="50.00">
                            <div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">Same shipping charge for every order</div>
                        </div>
                    </div>

                    <!-- Free Shipping -->
                    <div class="sf" data-m="free" style="display:none">
                        <div style="padding:16px;background:#d1fae5;border-radius:8px;font-size:13px;color:#10b981;font-weight:700;margin-top:8px">
                            ✓ All orders ship free — no charge at checkout.
                        </div>
                    </div>

                    <!-- Free Above Threshold -->
                    <div class="sf" data-m="free_above" style="display:none">
                        <div class="wk-form-group">
                            <label>Shipping Rate (below threshold)</label>
                            <input type="number" step="0.01" name="shipping_flat_rate_below" class="wk-input" value="<?= $v('shipping','flat_rate_below') ?>" placeholder="50.00">
                        </div>
                        <div class="wk-form-group">
                            <label>Free Shipping Threshold</label>
                            <input type="number" step="0.01" name="shipping_free_threshold" class="wk-input" value="<?= $v('shipping','free_threshold') ?>" placeholder="500.00">
                            <div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">Orders above this amount get free shipping</div>
                        </div>
                    </div>

                    <!-- Per Item -->
                    <div class="sf" data-m="per_item" style="display:none">
                        <div class="wk-form-group">
                            <label>Charge Per Item</label>
                            <input type="number" step="0.01" name="shipping_per_item" class="wk-input" value="<?= $v('shipping','per_item') ?>" placeholder="10.00">
                            <div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">Multiplied by total items in cart</div>
                        </div>
                        <div class="wk-form-group">
                            <label>Max Shipping Cap <span style="font-weight:500;text-transform:none">(optional)</span></label>
                            <input type="number" step="0.01" name="shipping_per_item_cap" class="wk-input" value="<?= $v('shipping','per_item_cap') ?>" placeholder="No cap">
                        </div>
                    </div>

                    <!-- Weight Based -->
                    <div class="sf" data-m="weight" style="display:none">
                        <div class="wk-form-group">
                            <label>Base Rate (first 1kg)</label>
                            <input type="number" step="0.01" name="shipping_weight_base" class="wk-input" value="<?= $v('shipping','weight_base') ?>" placeholder="50.00">
                        </div>
                        <div class="wk-form-group">
                            <label>Per Additional kg</label>
                            <input type="number" step="0.01" name="shipping_weight_per_kg" class="wk-input" value="<?= $v('shipping','weight_per_kg') ?>" placeholder="20.00">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div>
            <?php if (!empty($carriers)): ?>
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>📦 Carrier Rates</h2><span style="font-size:12px;color:var(--wk-text-muted);font-weight:600">Optional overrides</span></div>
                <div class="wk-card-body">
                    <p style="font-size:12px;color:var(--wk-text-muted);margin-bottom:14px">Set carrier-specific flat rates. Leave empty to use the default method.</p>
                    <?php foreach ($carriers as $c): ?>
                    <div class="wk-form-group">
                        <label><?= htmlspecialchars($c['name']) ?></label>
                        <input type="number" step="0.01" name="shipping_carrier_rate_<?= $c['id'] ?>" class="wk-input"
                               value="<?= $v('shipping','carrier_rate_'.$c['id']) ?>" placeholder="Use default">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-body" style="text-align:center;padding:28px;color:var(--wk-text-muted)">
                    <div style="font-size:28px;margin-bottom:8px;opacity:.3">🚚</div>
                    <p style="font-weight:700;margin-bottom:4px">No carriers configured</p>
                    <p style="font-size:13px">Add carriers in <a href="<?= $url('admin/shipping') ?>" style="color:var(--wk-purple);font-weight:700">Shipping Carriers</a> to set per-carrier rates.</p>
                </div>
            </div>
            <?php endif; ?>

            <div class="wk-card" style="margin-bottom:20px">
                <div class="wk-card-header"><h2>📦 Pickup Points (Lockers)</h2></div>
                <div class="wk-card-body">
                    <p style="font-size:12px;color:var(--wk-text-muted);margin-bottom:14px">Let customers collect orders from a locker / collection point (InPost, Mondial Relay, DHL Packstation…) instead of home delivery. Manage the locations in the list below.</p>
                    <div class="wk-form-group">
                        <label>Pickup Delivery</label>
                        <select name="shipping_pickup_enabled" class="wk-select">
                            <option value="1" <?= ($s['shipping']['pickup_enabled'] ?? '0')==='1'?'selected':'' ?>>Enabled</option>
                            <option value="0" <?= ($s['shipping']['pickup_enabled'] ?? '0')!=='1'?'selected':'' ?>>Disabled</option>
                        </select>
                        <div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">Shows a "Pickup point" option at checkout when at least one active location exists</div>
                    </div>
                    <div class="wk-form-group">
                        <label>Default Pickup Fee</label>
                        <input type="number" step="0.01" name="shipping_pickup_fee" class="wk-input" value="<?= $v('shipping','pickup_fee') ?>" placeholder="0.00">
                        <div style="font-size:11px;color:var(--wk-text-muted);margin-top:3px">Charged instead of the shipping method above. Empty or 0 = free. A location can override this with its own fee.</div>
                    </div>
                </div>
            </div>

            <button type="submit" class="wk-btn wk-btn-primary" style="width:100%;justify-content:center">Save Shipping Settings</button>
        </div>
    </div>
</form>

<!-- Pickup locations manager (separate forms — must not nest inside the settings form) -->
<div class="wk-card" style="margin-top:24px;max-width:900px">
    <div class="wk-card-header"><h2>📍 Pickup Locations</h2><span style="font-size:12px;color:var(--wk-text-muted);font-weight:600"><?= count($pickupLocations ?? []) ?> location<?= count($pickupLocations ?? [])!==1?'s':'' ?></span></div>
    <div class="wk-card-body">
        <?php $wkCountries = \App\Services\CurrencyService::countries(); ?>
        <?php if (!empty($pickupLocations)): ?>
        <div style="overflow-x:auto;margin-bottom:20px">
            <table class="wk-table" style="font-size:13px">
                <thead><tr><th>Name</th><th>Carrier</th><th>Address</th><th>Country</th><th>Fee</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($pickupLocations as $loc): ?>
                <tr>
                    <td style="font-weight:700"><?= htmlspecialchars($loc['name']) ?><?php if ($loc['opening_hours']): ?><div style="font-size:11px;color:var(--wk-text-muted);font-weight:500">🕐 <?= htmlspecialchars($loc['opening_hours']) ?></div><?php endif; ?></td>
                    <td><?= htmlspecialchars($loc['carrier'] ?: '—') ?></td>
                    <td style="font-size:12px"><?= htmlspecialchars($loc['address_line1']) ?>, <?= htmlspecialchars($loc['city']) ?> <?= htmlspecialchars($loc['zip']) ?></td>
                    <td><?= htmlspecialchars($loc['country']) ?></td>
                    <td style="font-family:var(--font-mono)"><?= $loc['fee'] !== null ? number_format((float)$loc['fee'], 2) : '<span style="color:var(--wk-text-muted)">default</span>' ?></td>
                    <td><span class="wk-badge <?= $loc['is_active'] ? 'wk-badge-success' : 'wk-badge-danger' ?>"><?= $loc['is_active'] ? 'Active' : 'Off' ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <form method="POST" action="<?= $url('admin/shipping/pickup/update/'.$loc['id']) ?>" style="display:inline">
                                <?= \Core\Session::csrfField() ?>
                                <input type="hidden" name="name" value="<?= htmlspecialchars($loc['name']) ?>">
                                <input type="hidden" name="carrier" value="<?= htmlspecialchars($loc['carrier']) ?>">
                                <input type="hidden" name="address_line1" value="<?= htmlspecialchars($loc['address_line1']) ?>">
                                <input type="hidden" name="city" value="<?= htmlspecialchars($loc['city']) ?>">
                                <input type="hidden" name="state" value="<?= htmlspecialchars($loc['state']) ?>">
                                <input type="hidden" name="zip" value="<?= htmlspecialchars($loc['zip']) ?>">
                                <input type="hidden" name="country" value="<?= htmlspecialchars($loc['country']) ?>">
                                <input type="hidden" name="opening_hours" value="<?= htmlspecialchars($loc['opening_hours']) ?>">
                                <input type="hidden" name="fee" value="<?= $loc['fee'] !== null ? htmlspecialchars((string)$loc['fee']) : '' ?>">
                                <input type="hidden" name="sort_order" value="<?= (int)$loc['sort_order'] ?>">
                                <?php if ($loc['is_active']): ?>
                                    <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm" style="padding:4px 12px;font-size:12px">Disable</button>
                                <?php else: ?>
                                    <input type="hidden" name="is_active" value="1">
                                    <button type="submit" class="wk-btn wk-btn-secondary wk-btn-sm" style="padding:4px 12px;font-size:12px">Enable</button>
                                <?php endif; ?>
                            </form>
                            <form method="POST" action="<?= $url('admin/shipping/pickup/delete/'.$loc['id']) ?>" onsubmit="return confirm('Delete pickup point &quot;<?= htmlspecialchars($loc['name']) ?>&quot;?')" style="display:inline">
                                <?= \Core\Session::csrfField() ?>
                                <button type="submit" class="wk-btn wk-btn-sm" style="background:none;border:2px solid var(--wk-red);color:var(--wk-red);padding:4px 12px;border-radius:6px;font-weight:700;cursor:pointer;font-size:12px">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:20px;color:var(--wk-text-muted);margin-bottom:20px">
            <div style="font-size:28px;margin-bottom:8px;opacity:.3">📍</div>
            <p style="font-weight:700">No pickup locations yet — add the first one below.</p>
        </div>
        <?php endif; ?>

        <form method="POST" action="<?= $url('admin/shipping/pickup/store') ?>">
            <?= \Core\Session::csrfField() ?>
            <div style="font-weight:800;font-size:13px;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px;color:var(--wk-text-muted)">Add Pickup Location</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="wk-form-group"><label>Name *</label><input type="text" name="name" class="wk-input" required placeholder="e.g. InPost Locker WAW-123"></div>
                <div class="wk-form-group"><label>Carrier / Network</label><input type="text" name="carrier" class="wk-input" placeholder="e.g. InPost, Mondial Relay, DHL"></div>
            </div>
            <div class="wk-form-group"><label>Address *</label><input type="text" name="address_line1" class="wk-input" required placeholder="12 Rue de la Paix"></div>
            <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px">
                <div class="wk-form-group"><label>City *</label><input type="text" name="city" class="wk-input" required></div>
                <div class="wk-form-group"><label>State</label><input type="text" name="state" class="wk-input"></div>
                <div class="wk-form-group"><label>ZIP</label><input type="text" name="zip" class="wk-input"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
                <div class="wk-form-group"><label>Country</label>
                    <select name="country" class="wk-select">
                        <?php foreach ($wkCountries as $code => $info): ?>
                        <option value="<?= $code ?>"><?= htmlspecialchars($info['name']) ?> (<?= $code ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="wk-form-group"><label>Opening Hours</label><input type="text" name="opening_hours" class="wk-input" placeholder="Mon–Sat 8:00–20:00"></div>
                <div class="wk-form-group"><label>Fee Override</label><input type="number" step="0.01" name="fee" class="wk-input" placeholder="Default fee"></div>
            </div>
            <button type="submit" class="wk-btn wk-btn-primary">+ Add Pickup Location</button>
        </form>
    </div>
</div>

<script>
function toggleShipping() {
    const m = document.getElementById('shippingMethod').value;
    document.querySelectorAll('.sf').forEach(f => f.style.display = f.dataset.m === m ? 'block' : 'none');
}
toggleShipping();
</script>