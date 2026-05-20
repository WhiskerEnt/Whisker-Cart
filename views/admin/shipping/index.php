<?php $e=fn($v)=>\Core\View::e($v); $url=fn($p)=>\Core\View::url($p); ?>

<!-- Add New Carrier -->
<div class="wk-card" style="margin-bottom:24px;max-width:600px">
    <div class="wk-card-header"><h2>+ Add Carrier</h2></div>
    <div class="wk-card-body">
        <form method="POST" action="<?= $url('admin/shipping/store') ?>">
            <?= \Core\Session::csrfField() ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                <div class="wk-form-group"><label>Carrier Name</label><input type="text" name="name" class="wk-input" required placeholder="e.g. BlueDart"></div>
                <div class="wk-form-group"><label>Code <span style="font-weight:400;text-transform:none">(optional)</span></label><input type="text" name="code" class="wk-input" placeholder="e.g. bluedart"></div>
            </div>
            <div class="wk-form-group">
                <label>Tracking URL Template <span style="font-weight:400;text-transform:none">(optional)</span></label>
                <input type="text" name="tracking_url_template" class="wk-input" placeholder="https://track.carrier.com/?awb={tracking_number}">
                <div style="font-size:11px;color:var(--wk-text-muted);margin-top:4px">Use <code style="background:var(--wk-bg);padding:1px 4px;border-radius:3px">{tracking_number}</code> as placeholder</div>
            </div>
            <button type="submit" class="wk-btn wk-btn-primary">Add Carrier</button>
        </form>
    </div>
</div>

<!-- Carrier List -->
<?php if (empty($carriers)): ?>
    <div class="wk-card"><div class="wk-empty"><div class="wk-empty-icon">🚚</div><p style="font-weight:800">No carriers yet</p><p style="font-size:13px">Add your first shipping carrier above</p></div></div>
<?php else: ?>
    <div class="wk-card">
        <div class="wk-card-header"><h2>Carriers</h2><span style="font-size:12px;color:var(--wk-text-muted);font-weight:600"><?= count($carriers) ?> total</span></div>
        <table class="wk-table">
            <thead><tr><th>Carrier</th><th>Code</th><th>Tracking URL</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($carriers as $c): ?>
                <tr>
                    <td style="font-weight:800"><?= $e($c['name']) ?></td>
                    <td><code style="font-family:var(--font-mono);font-size:12px;background:var(--wk-bg);padding:2px 6px;border-radius:4px"><?= $e($c['code'] ?? '—') ?></code></td>
                    <td style="font-size:12px;color:var(--wk-text-muted);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $e($c['tracking_url_template'] ?: '—') ?></td>
                    <td><span class="wk-badge <?= $c['is_active']?'wk-badge-success':'wk-badge-danger' ?>"><?= $c['is_active']?'Active':'Inactive' ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px">
                            <button type="button" class="wk-btn wk-btn-secondary wk-btn-sm wk-edit-carrier"
                                    data-id="<?= (int)$c['id'] ?>"
                                    data-name="<?= $e($c['name']) ?>"
                                    data-code="<?= $e($c['code'] ?? '') ?>"
                                    data-tracking-url="<?= $e($c['tracking_url_template'] ?? '') ?>"
                                    data-active="<?= $c['is_active'] ? '1' : '0' ?>">Edit</button>
                            <form method="POST" action="<?= $url('admin/shipping/delete/'.$c['id']) ?>" onsubmit="return confirm('Delete this carrier?')">
                                <?= \Core\Session::csrfField() ?>
                                <button type="submit" class="wk-btn wk-btn-danger wk-btn-sm">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Edit Modal (inline) -->
<div id="editModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);backdrop-filter:blur(4px);z-index:1000;align-items:center;justify-content:center">
    <div style="background:var(--wk-surface);border:1px solid var(--wk-border);border-radius:var(--radius);width:100%;max-width:480px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.1)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
            <h3 style="font-size:16px;font-weight:800">Edit Carrier</h3>
            <button onclick="closeEdit()" style="background:none;border:none;font-size:20px;cursor:pointer;color:var(--wk-text-muted)">×</button>
        </div>
        <form method="POST" id="editForm">
            <?= \Core\Session::csrfField() ?>
            <div class="wk-form-group"><label>Carrier Name</label><input type="text" name="name" id="editName" class="wk-input" required></div>
            <div class="wk-form-group"><label>Code</label><input type="text" name="code" id="editCode" class="wk-input"></div>
            <div class="wk-form-group"><label>Tracking URL Template</label><input type="text" name="tracking_url_template" id="editUrl" class="wk-input"></div>
            <div class="wk-form-group"><label style="display:flex;align-items:center;gap:8px;cursor:pointer"><input type="checkbox" name="is_active" value="1" id="editActive"> Active</label></div>
            <div style="display:flex;gap:12px">
                <button type="button" class="wk-btn wk-btn-secondary" style="flex:1;justify-content:center" onclick="closeEdit()">Cancel</button>
                <button type="submit" class="wk-btn wk-btn-primary" style="flex:1;justify-content:center">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
const WK_UPDATE_URL = <?= json_encode($url('admin/shipping/update/')) ?>;
function editCarrier(id, name, code, trackingUrl, active) {
    document.getElementById('editForm').action = WK_UPDATE_URL + id;
    document.getElementById('editName').value = name;
    document.getElementById('editCode').value = code;
    document.getElementById('editUrl').value = trackingUrl;
    document.getElementById('editActive').checked = !!active;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEdit() { document.getElementById('editModal').style.display = 'none'; }

// Bind edit buttons from data-* attributes. HTML attributes are HTML-context,
// not JS-context, so a malicious carrier name cannot break out the way an
// inline onclick="editCarrier('NAME',...)" would.
document.querySelectorAll('.wk-edit-carrier').forEach(function(btn) {
    btn.addEventListener('click', function() {
        editCarrier(
            parseInt(btn.dataset.id, 10),
            btn.dataset.name || '',
            btn.dataset.code || '',
            btn.dataset.trackingUrl || '',
            btn.dataset.active === '1'
        );
    });
});

document.getElementById('editModal').addEventListener('click', function(e) { if (e.target === this) closeEdit(); });
</script>
