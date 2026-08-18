<?php
$e   = fn($v) => \Core\View::e((string) $v);
$url = fn($p) => \Core\View::url($p);
$CS  = \App\Services\CountryService::class;
$ZS  = \App\Services\ShippingZoneService::class;
$allCountries = $CS::all();

$methods = [
    'flat'       => 'Flat rate',
    'free'       => 'Free shipping',
    'free_above' => 'Free over a threshold',
    'per_item'   => 'Per item',
    'weight'     => 'By weight',
];

/** The rate fields, rendered the same way for a new zone and an edit. */
$rateFields = function (array $z, string $prefix) use ($methods, $e) {
    ob_start(); ?>
    <div class="wk-form-group">
        <label>How this zone is charged</label>
        <select name="method" class="wk-select" data-zone-method>
            <?php foreach ($methods as $key => $label): ?>
                <option value="<?= $key ?>" <?= ($z['method'] ?? 'flat') === $key ? 'selected' : '' ?>><?= $e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="wk-zone-rates">
        <label data-for="flat free_above"><span>Flat rate</span>
            <input type="number" step="0.01" min="0" name="flat_rate" class="wk-input" value="<?= $e(number_format((float) ($z['flat_rate'] ?? 0), 2, '.', '')) ?>"></label>
        <label data-for="free_above"><span>Free over</span>
            <input type="number" step="0.01" min="0" name="free_threshold" class="wk-input" value="<?= $e(number_format((float) ($z['free_threshold'] ?? 0), 2, '.', '')) ?>"></label>
        <label data-for="free_above"><span>Charge below that</span>
            <input type="number" step="0.01" min="0" name="flat_rate_below" class="wk-input" value="<?= $e(number_format((float) ($z['flat_rate_below'] ?? 0), 2, '.', '')) ?>"></label>
        <label data-for="per_item"><span>Per item</span>
            <input type="number" step="0.01" min="0" name="per_item" class="wk-input" value="<?= $e(number_format((float) ($z['per_item'] ?? 0), 2, '.', '')) ?>"></label>
        <label data-for="per_item"><span>Cap <em>(blank for none)</em></span>
            <input type="number" step="0.01" min="0" name="per_item_cap" class="wk-input" value="<?= ($z['per_item_cap'] ?? null) !== null && $z['per_item_cap'] !== '' ? $e(number_format((float) $z['per_item_cap'], 2, '.', '')) : '' ?>"></label>
        <label data-for="weight"><span>Base rate (first kg)</span>
            <input type="number" step="0.01" min="0" name="weight_base" class="wk-input" value="<?= $e(number_format((float) ($z['weight_base'] ?? 0), 2, '.', '')) ?>"></label>
        <label data-for="weight"><span>Each extra kg</span>
            <input type="number" step="0.01" min="0" name="weight_per_kg" class="wk-input" value="<?= $e(number_format((float) ($z['weight_per_kg'] ?? 0), 2, '.', '')) ?>"></label>
    </div>
    <?php return ob_get_clean();
};

/** Country picker, reused by every zone form on the page. */
$picker = function (array $selected, string $uid) use ($allCountries, $e, $CS) {
    ob_start(); ?>
    <div class="wk-form-group">
        <label>Countries in this zone <span id="cnt-<?= $e($uid) ?>" style="font-weight:500;color:var(--wk-text-muted)"></span></label>
        <div class="wk-ship-picker-bar">
            <input type="search" class="wk-input" placeholder="Search countries..." data-zone-search autocomplete="off" style="flex:1;min-width:170px">
            <button type="button" class="wk-btn wk-btn-secondary wk-btn-sm" data-zone-pick="eu">EU</button>
            <button type="button" class="wk-btn wk-btn-secondary wk-btn-sm" data-zone-pick="none">None</button>
        </div>
        <div class="wk-country-grid" data-zone-grid>
            <?php foreach ($allCountries as $code => $name):
                $on = in_array($code, $selected, true); ?>
                <label class="wk-country<?= $on ? ' is-on' : '' ?>" data-name="<?= $e(strtolower($name)) ?>" data-code="<?= $e(strtolower($code)) ?>">
                    <input type="checkbox" name="countries[]" value="<?= $e($code) ?>" <?= $on ? 'checked' : '' ?>>
                    <span><?= $e($name) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
    </div>
    <?php return ob_get_clean();
};
?>

<div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap">
    <a href="<?= $url('admin/shipping/settings') ?>" class="wk-btn wk-btn-secondary">📦 Shipping Rates</a>
    <a href="<?= $url('admin/shipping') ?>" class="wk-btn wk-btn-secondary">🚚 Manage Carriers</a>
</div>

<div class="wk-card" style="max-width:900px;margin-bottom:20px">
    <div class="wk-card-body">
        <p style="font-size:13px;color:var(--wk-text-muted);margin:0;line-height:1.7">
            A zone charges its own rate for the countries in it. Anywhere without a zone uses your
            <a href="<?= $url('admin/shipping/settings') ?>" style="color:var(--wk-purple);font-weight:700">store-wide shipping rates</a>,
            so you only need a zone where the price differs. If a country ends up in two zones, the one
            higher in this list wins.
        </p>
    </div>
</div>

<?php if ($zones): ?>
<div style="display:grid;gap:14px;max-width:900px;margin-bottom:20px">
    <?php foreach ($zones as $z):
        $codes = $ZS::codes($z);
        $clash = $ZS::overlaps($codes, (int) $z['id']);
    ?>
    <div class="wk-card">
        <div class="wk-card-body">
            <div style="display:flex;justify-content:space-between;gap:12px;align-items:baseline;flex-wrap:wrap;margin-bottom:10px">
                <h3 style="font-size:16px;font-weight:900;margin:0">
                    <?= $e($z['name']) ?>
                    <?php if (!$z['is_active']): ?><span class="wk-badge" style="margin-left:6px">Off</span><?php endif; ?>
                </h3>
                <span style="font-size:12px;color:var(--wk-text-muted);font-weight:700">
                    <?= count($codes) ?> countr<?= count($codes) === 1 ? 'y' : 'ies' ?> · <?= $e($methods[$z['method']] ?? $z['method']) ?>
                </span>
            </div>

            <p style="font-size:12px;color:var(--wk-text-muted);margin:0 0 12px;line-height:1.6">
                <?= $e(implode(', ', array_map(fn($c) => \App\Services\CountryService::name($c), array_slice($codes, 0, 12)))) ?><?php
                if (count($codes) > 12) echo ' and ' . (count($codes) - 12) . ' more'; ?>
            </p>

            <?php if ($clash): ?>
                <div style="background:#fef3c7;color:#92400e;border-radius:var(--radius-sm);padding:10px 13px;font-size:12px;margin-bottom:12px;line-height:1.6">
                    Also in another zone: <?= $e(implode(', ', array_map(
                        fn($code, $zoneName) => \App\Services\CountryService::name($code) . ' (' . $zoneName . ')',
                        array_keys($clash), $clash
                    ))) ?>. The zone higher in this list is the one that applies.
                </div>
            <?php endif; ?>

            <details>
                <summary style="font-size:13px;font-weight:800;color:var(--wk-purple);cursor:pointer">Edit zone</summary>
                <form method="POST" action="<?= $url('admin/shipping/zones/update/' . $z['id']) ?>" style="margin-top:14px" class="wk-zone-form">
                    <?= \Core\Session::csrfField() ?>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px">
                        <div class="wk-form-group" style="margin:0">
                            <label>Zone name</label>
                            <input type="text" name="name" class="wk-input" maxlength="80" required value="<?= $e($z['name']) ?>">
                        </div>
                        <div class="wk-form-group" style="margin:0">
                            <label>Order</label>
                            <input type="number" name="sort_order" class="wk-input" value="<?= (int) $z['sort_order'] ?>">
                        </div>
                        <div class="wk-form-group" style="margin:0">
                            <label>Status</label>
                            <select name="is_active" class="wk-select">
                                <option value="1" <?= $z['is_active'] ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= $z['is_active'] ? '' : 'selected' ?>>Off — use store-wide rates</option>
                            </select>
                        </div>
                    </div>
                    <?= $rateFields($z, 'z' . $z['id']) ?>
                    <?= $picker($codes, 'z' . $z['id']) ?>
                    <div style="display:flex;gap:8px;flex-wrap:wrap">
                        <button type="submit" class="wk-btn wk-btn-primary">Save zone</button>
                        <button type="submit" class="wk-btn wk-btn-secondary" style="color:var(--wk-red);margin-left:auto"
                                formaction="<?= $url('admin/shipping/zones/delete/' . $z['id']) ?>"
                                onclick="return confirm('Delete the zone <?= $e(addslashes($z['name'])) ?>? Its countries go back to your store-wide rates.')">Delete zone</button>
                    </div>
                </form>
            </details>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="wk-card" style="max-width:900px">
    <div class="wk-card-header"><h2>➕ New Zone</h2></div>
    <div class="wk-card-body">
        <form method="POST" action="<?= $url('admin/shipping/zones/store') ?>" class="wk-zone-form">
            <?= \Core\Session::csrfField() ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px">
                <div class="wk-form-group" style="margin:0">
                    <label>Zone name</label>
                    <input type="text" name="name" class="wk-input" maxlength="80" required placeholder="Europe">
                </div>
                <div class="wk-form-group" style="margin:0">
                    <label>Order</label>
                    <input type="number" name="sort_order" class="wk-input" value="<?= count($zones) ?>">
                </div>
            </div>
            <?= $rateFields([], 'new') ?>
            <?= $picker([], 'new') ?>
            <button type="submit" class="wk-btn wk-btn-primary">Create zone</button>
        </form>
    </div>
</div>

<script>
(function () {
    var EU = <?= json_encode(\App\Services\CountryService::EU) ?>;

    document.querySelectorAll('.wk-zone-form').forEach(function (form) {
        var grid   = form.querySelector('[data-zone-grid]');
        var search = form.querySelector('[data-zone-search]');
        var method = form.querySelector('[data-zone-method]');
        var rows   = grid ? [].slice.call(grid.querySelectorAll('.wk-country')) : [];
        var count  = form.querySelector('[id^=cnt-]');

        function refreshCount() {
            if (!count) return;
            var n = rows.filter(function (r) { return r.querySelector('input').checked; }).length;
            count.textContent = n ? '(' + n + ' selected)' : '(none selected yet)';
        }

        // Only the fields the chosen method actually uses.
        function syncFields() {
            if (!method) return;
            form.querySelectorAll('[data-for]').forEach(function (el) {
                var applies = el.getAttribute('data-for').split(' ').indexOf(method.value) > -1;
                el.style.display = applies ? '' : 'none';
            });
        }
        if (method) { method.addEventListener('change', syncFields); syncFields(); }

        if (grid) {
            grid.addEventListener('change', function (e) {
                if (e.target.type !== 'checkbox') return;
                e.target.closest('.wk-country').classList.toggle('is-on', e.target.checked);
                refreshCount();
            });
        }

        if (search) {
            search.addEventListener('input', function () {
                var q = search.value.trim().toLowerCase();
                rows.forEach(function (r) {
                    var hit = q === '' || r.dataset.name.indexOf(q) > -1 || r.dataset.code === q;
                    r.style.display = hit ? '' : 'none';
                });
            });
        }

        form.querySelectorAll('[data-zone-pick]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var what = btn.getAttribute('data-zone-pick');
                rows.forEach(function (r) {
                    if (r.style.display === 'none') return;
                    var box = r.querySelector('input');
                    box.checked = what === 'eu' ? EU.indexOf(box.value) > -1 : false;
                    r.classList.toggle('is-on', box.checked);
                });
                refreshCount();
            });
        });

        refreshCount();
    });
})();
</script>
