<?php
/**
 * WHISKER — CI integration checks.
 *
 * Run by .github/workflows/ci.yml against a live MySQL/MariaDB service with
 * sql/schema.sql already loaded and config/{config,database}.php written.
 * Plain PHP on purpose: no framework, prints PASS/FAIL lines, exit code 1 on
 * any failure.
 *
 * What this proves (all shipped-bug regressions):
 *  - schema.sql loads on both MySQL and MariaDB
 *  - migrations actually execute (pre-1.3.2 they were recorded but skipped)
 *  - migrations are idempotent over an already-complete schema (benign
 *    duplicate tolerance), which is exactly what upgraded stores hit
 */

define('WK_ROOT', dirname(__DIR__, 2));
require WK_ROOT . '/core/autoload.php';
define('WK_VERSION', (string) require WK_ROOT . '/app/version.php');

use App\Services\MigrationService;
use App\Services\AccountFieldService;
use Core\Database;

$failures = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures;
    if ($ok) {
        echo "PASS  {$label}\n";
    } else {
        $failures++;
        echo "FAIL  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

function tableExists(string $table): bool
{
    return (bool) Database::fetchValue(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
        [$table]
    );
}

function columnExists(string $table, string $column): bool
{
    return (bool) Database::fetchValue(
        "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
        [$table, $column]
    );
}

// ── 1. schema.sql sanity ─────────────────────────────────────────────
check('schema: wk_products exists', tableExists('wk_products'));
check('schema: wk_orders exists', tableExists('wk_orders'));
check('schema: wk_settings exists', tableExists('wk_settings'));
check('schema: wk_pickup_locations exists', tableExists('wk_pickup_locations'));

// ── 2. Simulate an upgraded store and run migrations ─────────────────
Database::query(
    "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system','last_version','1.0.0')
     ON DUPLICATE KEY UPDATE setting_value = '1.0.0'"
);
Database::clearSettingsCache();

$result = MigrationService::checkAndRun();
check('migrations: first run reports no errors', empty($result['errors']), json_encode($result['errors'] ?? []));
check('migrations: runner not blocked by named lock', empty($result['busy']));

// ── 3. Post-migration schema state ───────────────────────────────────
check('wk_carts.email exists (v1.1.0)', columnExists('wk_carts', 'email'));
check('wk_carts.reminder_sent_at exists (v1.1.0)', columnExists('wk_carts', 'reminder_sent_at'));
check('wk_tax_rates exists (v1.2.0)', tableExists('wk_tax_rates'));
check('wk_orders.tax_details exists (v1.2.0)', columnExists('wk_orders', 'tax_details'));
check('wk_password_resets exists (v1.2.1)', tableExists('wk_password_resets'));
check('wk_orders.delivery_method exists (v1.3.2)', columnExists('wk_orders', 'delivery_method'));
check('wk_orders.pickup_location_id exists (v1.3.2)', columnExists('wk_orders', 'pickup_location_id'));

$enum = (string) Database::fetchValue(
    "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'wk_orders' AND COLUMN_NAME = 'status'"
);
check('wk_orders.status ENUM includes payment_failed (v1.2.2)', str_contains($enum, 'payment_failed'), $enum);

check(
    'shipping.pickup_enabled setting present',
    (int) Database::fetchValue("SELECT COUNT(*) FROM wk_settings WHERE setting_group='shipping' AND setting_key='pickup_enabled'") > 0
);

// ── 3b. Signup fields: the database round trips ──────────────────────
// What the unit tests cannot prove: the unique index really refuses a
// second username (in any letter case), answers survive a save and come
// back, and deleting a field takes its answers with it.

check('accounts: wk_customers.username exists', columnExists('wk_customers', 'username'));
check('accounts: wk_customer_fields exists', tableExists('wk_customer_fields'));
check('accounts: wk_customer_field_values exists', tableExists('wk_customer_field_values'));

Database::clearSettingsCache();
check('accounts: an upgraded shop keeps its old form (names required)', AccountFieldService::isRequired('first_name') && AccountFieldService::isRequired('last_name'));
check('accounts: phone stays optional by default', AccountFieldService::state('phone') === 'optional');
check('accounts: usernames are off by default', !AccountFieldService::isShown('username') && !AccountFieldService::usernameLogin());

Database::query("DELETE FROM wk_customers WHERE email IN ('ci-a@example.com','ci-b@example.com')");
$a = Database::insert('wk_customers', ['email' => 'ci-a@example.com', 'username' => 'ci_shopper', 'password_hash' => '*', 'first_name' => 'A', 'last_name' => 'A']);
$clash = null;
try {
    Database::insert('wk_customers', ['email' => 'ci-b@example.com', 'username' => 'CI_Shopper', 'password_hash' => '*', 'first_name' => 'B', 'last_name' => 'B']);
} catch (\Throwable $e) {
    $clash = $e->getMessage();
}
check('accounts: a username cannot be taken twice, whatever its case', $clash !== null, 'second insert succeeded');

$noName = 0;
try {
    Database::insert('wk_customers', ['email' => 'ci-b@example.com', 'password_hash' => '*', 'first_name' => 'B', 'last_name' => 'B']);
    Database::query("UPDATE wk_customers SET username = NULL WHERE email = 'ci-b@example.com'");
    $noName = (int) Database::fetchValue("SELECT COUNT(*) FROM wk_customers WHERE username IS NULL AND email IN ('ci-b@example.com')");
} catch (\Throwable $e) {}
check('accounts: any number of customers may have no username', $noName === 1);

Database::query("DELETE FROM wk_customer_fields WHERE field_key IN ('ci_company','ci_trade')");
$company = Database::insert('wk_customer_fields', ['field_key' => 'ci_company', 'label' => 'Company', 'type' => 'text', 'is_required' => 1, 'show_on_register' => 1, 'sort_order' => 1]);
$trade   = Database::insert('wk_customer_fields', ['field_key' => 'ci_trade', 'label' => 'Trade', 'type' => 'select', 'options' => "Retail\nWholesale", 'show_on_register' => 0, 'sort_order' => 2]);

$onSignup = array_column(AccountFieldService::custom(true), 'field_key');
$onProfile = array_column(AccountFieldService::custom(), 'field_key');
check('accounts: a profile-only field is not on the signup form', in_array('ci_company', $onSignup, true) && !in_array('ci_trade', $onSignup, true));
check('accounts: but it is on the profile', in_array('ci_trade', $onProfile, true));

$fields = AccountFieldService::custom();
AccountFieldService::saveCustom($a, $fields, ['cf_ci_company' => 'Acme <Ltd>', 'cf_ci_trade' => 'Wholesale']);
$back = AccountFieldService::valuesFor($a);
check('accounts: answers come back as given', ($back['ci_company'] ?? null) === 'Acme <Ltd>' && ($back['ci_trade'] ?? null) === 'Wholesale', json_encode($back));

AccountFieldService::saveCustom($a, $fields, ['cf_ci_company' => 'Acme Group', 'cf_ci_trade' => 'Retail']);
$rows = (int) Database::fetchValue('SELECT COUNT(*) FROM wk_customer_field_values WHERE customer_id = ?', [$a]);
check('accounts: saving again replaces rather than adds', $rows === 2 && AccountFieldService::valuesFor($a)['ci_company'] === 'Acme Group', "rows={$rows}");

Database::delete('wk_customer_fields', 'id = ?', [$trade]);
$left = (int) Database::fetchValue('SELECT COUNT(*) FROM wk_customer_field_values WHERE field_id = ?', [$trade]);
check('accounts: deleting a field deletes its answers', $left === 0);

Database::query("DELETE FROM wk_customers WHERE email IN ('ci-a@example.com','ci-b@example.com')");
$orphans = (int) Database::fetchValue('SELECT COUNT(*) FROM wk_customer_field_values WHERE customer_id = ?', [$a]);
check('accounts: deleting a customer deletes their answers', $orphans === 0);
Database::delete('wk_customer_fields', 'id = ?', [$company]);

// ── 4. Idempotency: a full re-run over the completed schema ──────────
// This is the upgraded-store path: everything already exists, every
// statement must resolve through the benign-duplicate tolerance.
Database::query(
    "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system','last_version','1.0.0')
     ON DUPLICATE KEY UPDATE setting_value = '1.0.0'"
);
Database::query(
    "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('system','executed_migrations','[]')
     ON DUPLICATE KEY UPDATE setting_value = '[]'"
);
Database::clearSettingsCache();

$again = MigrationService::checkAndRun();
check('migrations: full re-run has no errors (duplicates tolerated)', empty($again['errors']), json_encode($again['errors'] ?? []));

$storedVersion = Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='system' AND setting_key='last_version'");
check('migrations: last_version updated to ' . WK_VERSION, $storedVersion === WK_VERSION, "got {$storedVersion}");

echo $failures === 0 ? "\nALL CHECKS PASSED\n" : "\n{$failures} CHECK(S) FAILED\n";
exit($failures === 0 ? 0 : 1);
