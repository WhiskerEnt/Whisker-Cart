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
 *
 * It empties the order and product tables of whatever database
 * config/database.php names, so it runs from the command line only, never
 * over the web.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

define('WK_ROOT', dirname(__DIR__, 2));
require WK_ROOT . '/core/autoload.php';
define('WK_VERSION', (string) require WK_ROOT . '/app/version.php');

use App\Services\MigrationService;
use App\Services\AccountFieldService;
use App\Services\ShowcaseService;
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

// ── 3c. The homepage slider is never empty ───────────────────────────
// Builds a small catalogue and walks the fill order: chosen, best-sellers,
// recently added, on sale, a daily pick; photos first at every step; the
// automatic steps never offer something out of stock.
Database::query('SET FOREIGN_KEY_CHECKS = 0');
foreach (['wk_order_items', 'wk_orders', 'wk_product_images', 'wk_products'] as $t) Database::query("DELETE FROM {$t}");
Database::query('SET FOREIGN_KEY_CHECKS = 1');

$mk = function (string $slug, array $extra = []) {
    return Database::insert('wk_products', $extra + [
        'sku' => 'CI-' . $slug, 'name' => ucfirst($slug), 'slug' => $slug, 'price' => 100, 'stock_quantity' => 5,
        'is_active' => 1, 'is_featured' => 0, 'created_at' => date('Y-m-d H:i:s', strtotime('-200 days')),
    ]);
};
$photo = fn(int $id) => Database::insert('wk_product_images', ['product_id' => $id, 'image_path' => "p{$id}.jpg", 'is_primary' => 1]);
$sell = function (int $id, int $qty, string $status = 'paid') {
    $o = Database::insert('wk_orders', ['order_number' => substr('CI' . $id . $status . mt_rand(), 0, 30), 'status' => $status, 'total' => 100]);
    Database::insert('wk_order_items', ['order_id' => $o, 'product_id' => $id, 'product_name' => 'x', 'quantity' => $qty, 'unit_price' => 100, 'total_price' => 100 * $qty]);
};

ShowcaseService::reset();
check('showcase: a shop with no products has an empty slider', ShowcaseService::pick()['products'] === []);

$old1 = $mk('old-one');  $photo($old1);
$old2 = $mk('old-two');  $photo($old2);
ShowcaseService::reset();
$pick = ShowcaseService::pick();
check('showcase: a shop with products but no choices still gets a slider', count($pick['products']) === 2, json_encode(array_column($pick['products'], 'slug')));
check('showcase: and says it chose them itself', $pick['chosen'] === 0 && ShowcaseService::autoCount($pick) === 2);

$best  = $mk('best');  $photo($best);  $sell($best, 9);
$second = $mk('second'); $photo($second); $sell($second, 3);
$refunded = $mk('refunded'); $photo($refunded); $sell($refunded, 50, 'refunded');
$recentNoPhoto = $mk('recent-no-photo', ['created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))]);
$recent = $mk('recent', ['created_at' => date('Y-m-d H:i:s', strtotime('-10 days'))]); $photo($recent);
$sale = $mk('sale', ['sale_price' => 60]); $photo($sale);
$sold = $mk('sold-out', ['stock_quantity' => 0, 'created_at' => date('Y-m-d H:i:s', strtotime('-1 days'))]); $photo($sold);

ShowcaseService::reset();
$pick = ShowcaseService::pick();
$slugs = array_column($pick['products'], 'slug');
$sources = array_column($pick['products'], 'showcase_source');
check('showcase: fills six places', count($slugs) === ShowcaseService::SLOTS, json_encode($slugs));
check('showcase: best-sellers lead, most sold first', array_slice($slugs, 0, 2) === ['best', 'second'], json_encode($slugs));
check('showcase: a refunded order is not a sale', !in_array('refunded', array_slice($slugs, 0, 2), true));
check('showcase: then recently added, photos first', array_slice($slugs, 2, 2) === ['recent', 'recent-no-photo'], json_encode($slugs));
check('showcase: then on sale', ($slugs[4] ?? null) === 'sale', json_encode($slugs));
check('showcase: then the daily pick', ($sources[5] ?? null) === 'daily', json_encode($sources));
check('showcase: never offers something out of stock on its own', !in_array('sold-out', $slugs, true));

ShowcaseService::reset();
$again = array_column(ShowcaseService::pick()['products'], 'slug');
check('showcase: the daily pick holds still within a day', $again === $slugs);

Database::update('wk_products', ['is_featured' => 1], 'id IN (?, ?)', [$sold, $old2]);
ShowcaseService::reset();
$pick = ShowcaseService::pick();
$slugs = array_column($pick['products'], 'slug');
check('showcase: chosen products come first, even when sold out', array_slice($slugs, 0, 2) === ['old-two', 'sold-out'] || array_slice($slugs, 0, 2) === ['sold-out', 'old-two'], json_encode($slugs));
check('showcase: then the rest is filled', count($slugs) === ShowcaseService::SLOTS && $pick['chosen'] === 2 && ShowcaseService::autoCount($pick) === 4);
check('showcase: the summary says what happened', str_contains(ShowcaseService::describe($pick), 'the 2 products you chose, then'), ShowcaseService::describe($pick));

for ($i = 0; $i < 7; $i++) { $id = $mk("chosen-{$i}", ['is_featured' => 1]); $photo($id); }
ShowcaseService::reset();
$pick = ShowcaseService::pick();
check('showcase: a shop that chose plenty gets only its choices', ShowcaseService::autoCount($pick) === 0 && $pick['chosen'] === 9, json_encode(['chosen' => $pick['chosen'], 'auto' => $pick['auto']]));

Database::query('SET FOREIGN_KEY_CHECKS = 0');
foreach (['wk_order_items', 'wk_orders', 'wk_product_images', 'wk_products'] as $t) Database::query("DELETE FROM {$t}");
Database::query('SET FOREIGN_KEY_CHECKS = 1');
ShowcaseService::reset();

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
