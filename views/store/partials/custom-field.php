<?php
/**
 * One field a shop added for itself, on the signup or profile form.
 *
 * Set before requiring this:
 *   $wkField       array   a row from wk_customer_fields
 *   $wkFieldValue  string  what to show in it, '' for a blank one
 */
$wkFieldEsc  = fn($v) => \Core\View::e((string) $v);
$wkFieldName = \App\Services\AccountFieldService::inputName($wkField['field_key']);
$wkFieldReq  = !empty($wkField['is_required']);
$wkFieldVal  = (string) ($wkFieldValue ?? '');
?>
<?php if ($wkField['type'] === 'checkbox'): ?>
    <label class="wk-check">
        <input type="checkbox" name="<?= $wkFieldEsc($wkFieldName) ?>" value="1"<?= $wkFieldVal !== '' && $wkFieldVal !== '0' ? ' checked' : '' ?><?= $wkFieldReq ? ' required' : '' ?>>
        <span class="wk-bold"><?= $wkFieldEsc($wkField['label']) ?></span>
    </label>
<?php else: ?>
    <label class="wk-label">
        <?= $wkFieldEsc($wkField['label']) ?><?= $wkFieldReq ? '' : ' <span class="wk-subtle">(optional)</span>' ?>
    </label>
    <?php if ($wkField['type'] === 'textarea'): ?>
        <textarea name="<?= $wkFieldEsc($wkFieldName) ?>" rows="3"<?= $wkFieldReq ? ' required' : '' ?>
                  placeholder="<?= $wkFieldEsc($wkField['placeholder'] ?? '') ?>"
                  class="wk-field-input wk-field-textarea"><?= $wkFieldEsc($wkFieldVal) ?></textarea>
    <?php elseif ($wkField['type'] === 'select'): ?>
        <select name="<?= $wkFieldEsc($wkFieldName) ?>"<?= $wkFieldReq ? ' required' : '' ?> class="wk-field-input wk-field-select">
            <option value=""><?= $wkFieldReq ? 'Choose one' : 'No answer' ?></option>
            <?php foreach (\App\Services\AccountFieldService::choices($wkField) as $wkChoice): ?>
                <option value="<?= $wkFieldEsc($wkChoice) ?>"<?= $wkChoice === $wkFieldVal ? ' selected' : '' ?>><?= $wkFieldEsc($wkChoice) ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <input type="<?= $wkField['type'] === 'number' ? 'number' : ($wkField['type'] === 'date' ? 'date' : 'text') ?>"
               name="<?= $wkFieldEsc($wkFieldName) ?>" value="<?= $wkFieldEsc($wkFieldVal) ?>"<?= $wkFieldReq ? ' required' : '' ?>
               placeholder="<?= $wkFieldEsc($wkField['placeholder'] ?? '') ?>" maxlength="2000" class="wk-field-input">
    <?php endif; ?>
<?php endif; ?>
<?php if (!empty($wkField['help_text'])): ?>
    <div class="wk-note wk-mt-8"><?= $wkFieldEsc($wkField['help_text']) ?></div>
<?php endif; ?>
