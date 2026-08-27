<?php
/**
 * The phone box, everywhere it appears: a country picker beside the number.
 *
 * Set $wkPhone before requiring this, then unset nothing — each include reads
 * its own copy:
 *   value       string  the stored number, split back into country and rest
 *   required    bool    default false
 *   inputStyle  string  inline style for the number box, to match the form
 *   selectStyle string  inline style for the picker; falls back to inputStyle
 *   name        string  field name, default 'phone'
 */
$wkPhone       = ($wkPhone ?? []) + ['value' => '', 'required' => false, 'inputStyle' => '', 'selectStyle' => '', 'name' => 'phone'];
$wkPhoneParts  = \App\Services\CountryService::splitPhone($wkPhone['value']);
$wkPhoneChosen = $wkPhoneParts['code'] !== '' ? $wkPhoneParts['code'] : \App\Services\CountryService::storeCountry();
$wkPhoneEsc    = fn($v) => \Core\View::e($v);
$wkPhoneSelSty = $wkPhone['selectStyle'] !== '' ? $wkPhone['selectStyle'] : $wkPhone['inputStyle'];
?>
<div class="wk-phone-field">
    <select name="<?= $wkPhoneEsc($wkPhone['name']) ?>_code" aria-label="Country calling code"
            data-wk-phone-code
            style="<?= $wkPhoneEsc($wkPhoneSelSty) ?>;padding-left:8px;padding-right:8px;font-size:13px">
        <?php foreach (\App\Services\CountryService::dialCodes() as $wkCc => $wkDial): ?>
            <option value="<?= $wkPhoneEsc($wkCc) ?>"<?= $wkCc === $wkPhoneChosen ? ' selected' : '' ?>><?= $wkPhoneEsc(\App\Services\CountryService::name($wkCc)) ?> (+<?= $wkPhoneEsc($wkDial) ?>)</option>
        <?php endforeach; ?>
    </select>
    <input type="tel" name="<?= $wkPhoneEsc($wkPhone['name']) ?>"
           value="<?= $wkPhoneEsc($wkPhoneParts['number']) ?>"
           placeholder="98765 43210" autocomplete="tel" maxlength="24"
           data-wk-validate="phone"<?= $wkPhone['required'] ? ' required' : '' ?>
           style="<?= $wkPhoneEsc($wkPhone['inputStyle']) ?>">
</div>
<p class="wk-field-note" data-wk-note style="display:none"></p>
