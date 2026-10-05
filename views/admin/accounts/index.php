<?php
$e   = fn($v) => \Core\View::e((string) $v);
$url = fn($p) => \Core\View::url($p);
$F   = \App\Services\AccountFieldService::class;

$stateLabels = [
    $F::OFF      => 'Not asked',
    $F::OPTIONAL => 'Optional',
    $F::REQUIRED => 'Required',
];
$typeLabels = [
    'text'     => 'Short text',
    'textarea' => 'Long text',
    'number'   => 'Number',
    'select'   => 'Dropdown',
    'checkbox' => 'Tick box',
    'date'     => 'Date',
];

/** The inputs that describe a field, the same for adding one and editing one. */
$fieldInputs = function (array $f, string $idPrefix) use ($e, $typeLabels) {
    $type = $f['type'] ?? 'text';
    ob_start(); ?>
    <div class="wk-grid-2">
        <div class="wk-form-group">
            <label for="<?= $idPrefix ?>label">Label</label>
            <input id="<?= $idPrefix ?>label" type="text" name="label" class="wk-input" maxlength="80" required value="<?= $e($f['label'] ?? '') ?>" placeholder="Company name">
        </div>
        <div class="wk-form-group">
            <label for="<?= $idPrefix ?>type">Type</label>
            <select id="<?= $idPrefix ?>type" name="type" class="wk-select" data-wk-field-type>
                <?php foreach ($typeLabels as $value => $name): ?>
                    <option value="<?= $value ?>"<?= $type === $value ? ' selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="wk-form-group" data-wk-field-options<?= $type === 'select' ? '' : ' hidden' ?>>
        <label for="<?= $idPrefix ?>options">Choices</label>
        <textarea id="<?= $idPrefix ?>options" name="options" class="wk-input" rows="4" placeholder="One per line"><?= $e($f['options'] ?? '') ?></textarea>
        <div class="wk-hint">One choice per line. Customers pick one.</div>
    </div>
    <div class="wk-grid-2">
        <div class="wk-form-group">
            <label for="<?= $idPrefix ?>placeholder">Placeholder <span class="wk-subtle">(optional)</span></label>
            <input id="<?= $idPrefix ?>placeholder" type="text" name="placeholder" class="wk-input" maxlength="120" value="<?= $e($f['placeholder'] ?? '') ?>" placeholder="Shown faintly inside an empty box">
        </div>
        <div class="wk-form-group">
            <label for="<?= $idPrefix ?>help">Help text <span class="wk-subtle">(optional)</span></label>
            <input id="<?= $idPrefix ?>help" type="text" name="help_text" class="wk-input" maxlength="255" value="<?= $e($f['help_text'] ?? '') ?>" placeholder="Shown under the box">
        </div>
    </div>
    <div class="wk-acct-checks">
        <label class="wk-check"><input type="checkbox" name="is_required" value="1"<?= !empty($f['is_required']) ? ' checked' : '' ?>> Required</label>
        <label class="wk-check"><input type="checkbox" name="show_on_register" value="1"<?= !isset($f['show_on_register']) || $f['show_on_register'] ? ' checked' : '' ?>> Ask on the signup form</label>
    </div>
    <?php return ob_get_clean();
};
?>

<div class="wk-acct-actions">
    <a href="<?= $url('admin/customers') ?>" class="wk-btn wk-btn-secondary"><?= $icon('users') ?> Customers</a>
    <a href="<?= $url('account/register') ?>" target="_blank" rel="noopener" class="wk-btn wk-btn-secondary"><?= $icon('eye') ?> See the signup form</a>
</div>

<form method="POST" action="<?= $url('admin/accounts/settings') ?>">
    <?= \Core\Session::csrfField() ?>

    <div class="wk-card wk-mb-20 wk-acct-card">
        <div class="wk-card-header"><h2><?= $icon('lock') ?> Sign-in</h2></div>
        <div class="wk-card-body">
            <div class="wk-form-group wk-m-0">
                <label for="login_with_username">Customers sign in with</label>
                <select id="login_with_username" name="login_with_username" class="wk-select wk-acct-narrow">
                    <option value="0"<?= !$usernameLogin ? ' selected' : '' ?>>Their email address</option>
                    <option value="1"<?= $usernameLogin ? ' selected' : '' ?>>Their email address or a username</option>
                </select>
                <div class="wk-hint">With usernames on, the signup form asks for one, and either works at the sign-in screen. Email stays the address for orders and password resets.</div>
            </div>
        </div>
    </div>

    <div class="wk-card wk-mb-20 wk-acct-card">
        <div class="wk-card-header"><h2><?= $icon('user-plus') ?> Signup form</h2></div>
        <div class="wk-card-body wk-acct-flush">
            <table class="wk-table">
                <thead><tr><th>Field</th><th class="wk-acct-state-col">Asked for</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="wk-bold">Email</td>
                        <td><span class="wk-muted">Always required</span></td>
                    </tr>
                    <tr>
                        <td class="wk-bold">Password</td>
                        <td><span class="wk-muted">Always required</span></td>
                    </tr>
                    <?php foreach ($states as $key => $state): ?>
                    <tr>
                        <td>
                            <label for="field_<?= $key ?>" class="wk-bold"><?= $e($F::label($key)) ?></label>
                            <?php if ($key === 'phone'): ?><div class="wk-fine">Checked for the right number of digits for the chosen country.</div><?php endif; ?>
                            <?php if ($key === 'username'): ?><div class="wk-fine">Letters, numbers, dots, dashes and underscores. Never the same as another customer's.</div><?php endif; ?>
                        </td>
                        <td>
                            <select id="field_<?= $key ?>" name="field_<?= $key ?>" class="wk-select">
                                <?php foreach ($stateLabels as $value => $name): ?>
                                    <option value="<?= $value ?>"<?= $state === $value ? ' selected' : '' ?>><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="wk-card-body wk-acct-foot">
            <p class="wk-note wk-m-0">Switching a field off hides it from the signup and profile forms. Anything customers already gave is kept, and shows again if you switch it back on.</p>
        </div>
    </div>

    <button type="submit" class="wk-btn wk-btn-primary wk-mb-20"><?= $icon('save') ?> Save signup and sign-in</button>
</form>

<div class="wk-card wk-mb-20 wk-acct-card">
    <div class="wk-card-header">
        <h2><?= $icon('notebook-pen') ?> Your own fields</h2>
        <span class="wk-note wk-semibold"><?= count($fields) ?> field<?= count($fields) === 1 ? '' : 's' ?></span>
    </div>
    <div class="wk-card-body">
        <?php if (!$fields): ?>
            <p class="wk-note wk-m-0">None yet. Add a question below, such as a company name, a VAT number, or how somebody heard about the shop.</p>
        <?php else: ?>
            <div class="wk-acct-list">
            <?php foreach ($fields as $f): $answers = (int) ($answerCounts[$f['id']] ?? 0); ?>
                <div class="wk-acct-field<?= $f['is_active'] ? '' : ' is-off' ?>">
                    <div class="wk-acct-field-head">
                        <div>
                            <div class="wk-heavy"><?= $e($f['label']) ?></div>
                            <div class="wk-fine wk-mt-8">
                                <?= $e($typeLabels[$f['type']] ?? $f['type']) ?>
                                · <?= $f['is_required'] ? 'Required' : 'Optional' ?>
                                · <?= $f['show_on_register'] ? 'On signup and profile' : 'Profile only' ?>
                                <?php if (!$f['is_active']): ?> · <strong>Switched off</strong><?php endif; ?>
                                · <?= $answers ?> answer<?= $answers === 1 ? '' : 's' ?>
                            </div>
                        </div>
                        <form method="POST" action="<?= $url('admin/accounts/fields/' . (int) $f['id'] . '/delete') ?>"
                              onsubmit="return confirm(<?= $e(json_encode('Delete "' . $f['label'] . '"?' . ($answers ? ' The ' . $answers . ' answer' . ($answers === 1 ? '' : 's') . ' customers gave will be deleted too.' : ''))) ?>)">
                            <?= \Core\Session::csrfField() ?>
                            <button type="submit" class="wk-btn wk-btn-sm wk-btn-danger"><?= $icon('trash', 14) ?> Delete</button>
                        </form>
                    </div>
                    <details class="wk-mt-12">
                        <summary class="wk-acct-edit">Edit</summary>
                        <form method="POST" action="<?= $url('admin/accounts/fields/' . (int) $f['id']) ?>" class="wk-mt-12">
                            <?= \Core\Session::csrfField() ?>
                            <?= $fieldInputs($f, 'f' . (int) $f['id'] . '_') ?>
                            <div class="wk-grid-2">
                                <div class="wk-form-group">
                                    <label for="f<?= (int) $f['id'] ?>_active">Status</label>
                                    <select id="f<?= (int) $f['id'] ?>_active" name="is_active" class="wk-select">
                                        <option value="1"<?= $f['is_active'] ? ' selected' : '' ?>>On</option>
                                        <option value="0"<?= !$f['is_active'] ? ' selected' : '' ?>>Off, keep the answers</option>
                                    </select>
                                </div>
                                <div class="wk-form-group">
                                    <label for="f<?= (int) $f['id'] ?>_order">Position</label>
                                    <input id="f<?= (int) $f['id'] ?>_order" type="number" name="sort_order" class="wk-input" value="<?= (int) $f['sort_order'] ?>">
                                    <div class="wk-hint">Lower numbers come first.</div>
                                </div>
                            </div>
                            <button type="submit" class="wk-btn wk-btn-primary wk-btn-sm"><?= $icon('save', 14) ?> Save field</button>
                        </form>
                    </details>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="wk-card wk-acct-card">
    <div class="wk-card-header"><h2><?= $icon('plus') ?> Add a field</h2></div>
    <div class="wk-card-body">
        <form method="POST" action="<?= $url('admin/accounts/fields') ?>">
            <?= \Core\Session::csrfField() ?>
            <?= $fieldInputs([], 'new_') ?>
            <button type="submit" class="wk-btn wk-btn-primary"><?= $icon('plus') ?> Add field</button>
        </form>
    </div>
</div>

<script>
// The choices box only means something for a dropdown.
document.querySelectorAll('[data-wk-field-type]').forEach(function (select) {
    var form = select.closest('form');
    var box = form && form.querySelector('[data-wk-field-options]');
    if (!box) return;
    select.addEventListener('change', function () { box.hidden = select.value !== 'select'; });
});
</script>
