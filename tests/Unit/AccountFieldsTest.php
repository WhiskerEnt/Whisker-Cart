<?php
namespace Tests\Unit;

use App\Services\AccountFieldService as F;
use PHPUnit\Framework\TestCase;

/**
 * The signup form a shop configures. The rules that need no database are
 * pinned here; the database round trips are in tests/ci/integration.php.
 */
class AccountFieldsTest extends TestCase
{
    private function source(string $rel): string
    {
        return (string) file_get_contents(WK_ROOT . '/' . $rel);
    }

    private function method(string $rel, string $name): string
    {
        $src = $this->source($rel);
        $from = strpos($src, 'function ' . $name . '(');
        $this->assertNotFalse($from, "$name() not found in $rel");
        $next = strpos($src, "\n    public function ", $from + 1);
        $nextPrivate = strpos($src, "\n    private static function ", $from + 1);
        $end = min(array_filter([$next, $nextPrivate, strlen($src)]));
        return substr($src, $from, $end - $from);
    }

    public function testAUsernameCannotBeMistakenForAnEmail(): void
    {
        foreach (['johndoe', 'john.doe', 'j_d-2', 'ABC'] as $ok) {
            $this->assertNull(F::usernameError($ok), "$ok should be accepted");
        }
        foreach (['jo', 'john@doe', 'john doe', '.john', '-x1', str_repeat('a', 41), '<b>', "a\nb"] as $bad) {
            $this->assertNotNull(F::usernameError($bad), "$bad should be refused");
        }
    }

    public function testKeysAreMadeFromLabels(): void
    {
        $this->assertSame('company_name', F::keyFrom('Company name'));
        $this->assertSame('vat_number', F::keyFrom('  VAT number! '));
        $this->assertSame('how_did_you_hear_about_us', F::keyFrom('How did you hear about us?'));
        $this->assertSame('field', F::keyFrom('???'));
        $this->assertLessThanOrEqual(40, strlen(F::keyFrom(str_repeat('word ', 30))));
    }

    public function testDropdownChoicesAreOnePerLine(): void
    {
        $this->assertSame(['Retail', 'Wholesale', 'Other'], F::choices(['options' => "Retail\r\n  Wholesale \n\nOther\n"]));
        $this->assertSame([], F::choices(['options' => null]));
    }

    public function testCustomAnswersAreChecked(): void
    {
        $fields = [
            ['id' => 1, 'field_key' => 'company', 'label' => 'Company', 'type' => 'text', 'is_required' => 1, 'options' => null],
            ['id' => 2, 'field_key' => 'staff', 'label' => 'Staff', 'type' => 'number', 'is_required' => 0, 'options' => null],
            ['id' => 3, 'field_key' => 'trade', 'label' => 'Trade', 'type' => 'select', 'is_required' => 0, 'options' => "Retail\nWholesale"],
            ['id' => 4, 'field_key' => 'born', 'label' => 'Birthday', 'type' => 'date', 'is_required' => 0, 'options' => null],
            ['id' => 5, 'field_key' => 'terms', 'label' => 'Trade terms', 'type' => 'checkbox', 'is_required' => 1, 'options' => null],
        ];
        $good = ['cf_company' => 'Acme', 'cf_staff' => '12', 'cf_trade' => 'Retail', 'cf_born' => '1990-04-01', 'cf_terms' => '1'];

        $this->assertNull(F::validateCustom($fields, $good));
        $this->assertSame('Company is required.', F::validateCustom($fields, ['cf_company' => '  '] + $good));
        $this->assertSame('Staff must be a number.', F::validateCustom($fields, ['cf_staff' => 'twelve'] + $good));
        $this->assertSame('Choose one of the options for Trade.', F::validateCustom($fields, ['cf_trade' => 'Smuggling'] + $good));
        $this->assertSame('Birthday must be a date.', F::validateCustom($fields, ['cf_born' => 'April'] + $good));
        $this->assertSame('Trade terms is required.', F::validateCustom($fields, array_diff_key($good, ['cf_terms' => 1])));
        $this->assertNull(F::validateCustom($fields, ['cf_staff' => '', 'cf_trade' => '', 'cf_born' => ''] + $good), 'optional fields may be left empty');
    }

    public function testEmailAndPasswordAreNotTheShopsToSwitchOff(): void
    {
        $this->assertNotContains('email', F::builtInKeys());
        $this->assertNotContains('password', F::builtInKeys());

        $register = $this->method('app/Controllers/Store/AccountController.php', 'register');
        $this->assertStringContainsString("\$rules = ['email' => 'required|email', 'password' => 'required|min:8'];", $register);
    }

    public function testRegistrationAsksTheRegistryNotAFixedList(): void
    {
        $register = $this->method('app/Controllers/Store/AccountController.php', 'register');
        foreach (["isRequired('first_name')", "isRequired('last_name')", "isShown('phone')", "isShown('username')", 'validateCustom(', 'saveCustom('] as $call) {
            $this->assertStringContainsString($call, $register);
        }
        $this->assertStringNotContainsString("'first_name' => 'required|min:2', 'last_name' => 'required|min:1',", $register, 'the old fixed rules are gone');

        $view = $this->source('views/store/account/register.php');
        foreach (["isShown('first_name')", "isShown('last_name')", "isShown('username')", "isShown('phone')", 'custom(true)'] as $call) {
            $this->assertStringContainsString($call, $view);
        }
    }

    public function testARejectedSignupKeepsWhatWasTypedButNeverThePassword(): void
    {
        $reject = $this->method('app/Controllers/Store/AccountController.php', 'rejectRegistration');
        $this->assertStringContainsString("!str_contains(\$name, 'password')", $reject);
        $this->assertStringContainsString("\$name !== 'wk_csrf'", $reject);

        $register = $this->method('app/Controllers/Store/AccountController.php', 'register');
        $this->assertSame(0, substr_count($register, "Response::redirect(View::url('account/register'))"), 'every rejection goes through rejectRegistration');
    }

    /**
     * Three mistyped submissions used to spend the whole hourly allowance,
     * locking a real customer out. Only created accounts count against it.
     */
    public function testMistakesDoNotUseUpTheSignupAllowance(): void
    {
        $register = $this->method('app/Controllers/Store/AccountController.php', 'register');
        $strict = strpos($register, "RateLimiter::attempt('register', \$ip, 3, 3600)");
        $insert = strpos($register, "Database::insert('wk_customers'");
        $this->assertNotFalse($strict);
        $this->assertGreaterThan(strpos($register, 'validateCustom('), $strict, 'the strict limit comes after every check');
        $this->assertLessThan($insert, $strict, 'and before the account is created');
        $this->assertStringContainsString("RateLimiter::attempt('register_submit', \$ip, 20, 3600)", $register);
    }

    public function testSignInTellsAUsernameFromAnEmailByTheAtSign(): void
    {
        $login = $this->method('app/Controllers/Store/AccountController.php', 'login');
        $this->assertStringContainsString("str_contains(\$emailRaw, '@') || !AccountFields::usernameLogin()", $login);
        $this->assertStringContainsString('WHERE username=?', $login);
        $this->assertStringContainsString('WHERE email=?', $login);
        $this->assertStringContainsString('self::DUMMY_HASH', $login, 'an unknown name still costs a password_verify');
    }

    public function testTheProfileOnlyWritesFieldsTheShopStillAsksFor(): void
    {
        $update = $this->method('app/Controllers/Store/AccountController.php', 'updateProfile');
        $this->assertStringContainsString("if (!AccountFields::isShown(\$key)) continue;", $update);
        $this->assertStringContainsString("if (AccountFields::isShown('phone'))", $update);
        $this->assertStringContainsString("if (AccountFields::isShown('username'))", $update);
        $this->assertStringContainsString('AND id<>?', $update, 'a customer keeping their own username is not a clash');
    }

    public function testErasureRemovesTheNewPersonalData(): void
    {
        $erase = $this->method('app/Controllers/Admin/CustomerController.php', 'pseudonymize');
        $this->assertStringContainsString('DELETE FROM wk_customer_field_values WHERE customer_id=?', $erase);
        $this->assertStringContainsString("\$scrub['username'] = null;", $erase);
    }

    public function testUsernameLoginNeedsTheUsernameField(): void
    {
        $save = $this->method('app/Controllers/Admin/AccountSettingsController.php', 'updateSettings');
        $this->assertStringContainsString("if (\$usernameLogin && \$states['username'] === AccountFields::OFF)", $save);

        $service = $this->source('app/Services/AccountFieldService.php');
        $this->assertStringContainsString("&& self::isShown('username');", $service, 'usernameLogin() is false while nobody can have a username');
    }

    public function testAnswersAreEscapedOnTheWayOut(): void
    {
        $partial = $this->source('views/store/partials/custom-field.php');
        $this->assertDoesNotMatchRegularExpression('/<\?=\s*\$wkField\[[^\]]+\]\s*\?>/', $partial, 'every field property is printed through the escaper');
        $this->assertDoesNotMatchRegularExpression('/<\?=\s*\$wkFieldVal\s*\?>/', $partial);

        $admin = $this->source('views/admin/customers/show.php');
        $this->assertStringContainsString('nl2br($e($wkAnswer))', $admin);
    }

    public function testTheMigrationAndTheSchemaAgree(): void
    {
        $schema = $this->source('sql/schema.sql');
        $this->assertStringContainsString('username VARCHAR(50) DEFAULT NULL', $schema);
        $this->assertStringContainsString('UNIQUE KEY uniq_customer_username (username)', $schema);
        foreach (['field_first_name', 'field_last_name', 'field_phone', 'field_username', 'login_with_username'] as $key) {
            $this->assertStringContainsString("('accounts', '{$key}',", $schema, "$key is seeded for a fresh install");
        }

        $migration = $this->source('sql/migrations/20261005_v142_account_fields.sql');
        $this->assertStringContainsString("ON DUPLICATE KEY UPDATE setting_value = setting_value", $migration, 'an upgrade never overwrites a choice already made');
    }
}
