<?php
namespace Tests\Unit;

use App\Services\CountryService;
use PHPUnit\Framework\TestCase;

/**
 * The sign-up form is the first thing a shopper fills in, so what it promises
 * and what the server accepts have to be the same thing.
 */
class SignupFormTest extends TestCase
{
    private function view(): string
    {
        return (string) file_get_contents(WK_ROOT . '/views/store/account/register.php');
    }

    private function controller(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/AccountController.php');
    }

    private function layout(): string
    {
        return (string) file_get_contents(WK_ROOT . '/views/store/layouts/main.php');
    }

    // ── Calling codes ────────────────────────────────────────────────────

    /** Every country in the list can be picked, or the list is a trap. */
    public function testEveryCountryHasACallingCode(): void
    {
        $countries = CountryService::all();
        $dial      = CountryService::dialCodes();

        $this->assertSame(count($countries), count($dial), 'a country in the list has no calling code');
        $this->assertSame([], array_diff_key($countries, $dial));
        $this->assertSame([], array_diff_key($dial, $countries), 'a calling code for a country nobody can pick');

        foreach ($dial as $code => $value) {
            $this->assertMatchesRegularExpression('/^[1-9][0-9]{0,3}$/', $value, "{$code} has a malformed calling code");
        }
    }

    /** Countries that share a code must each keep it. */
    public function testSharedCallingCodesAreNotDeduplicated(): void
    {
        $this->assertSame('1',  CountryService::dialCode('US'));
        $this->assertSame('1',  CountryService::dialCode('CA'));
        $this->assertSame('7',  CountryService::dialCode('RU'));
        $this->assertSame('7',  CountryService::dialCode('KZ'));
        $this->assertSame('44', CountryService::dialCode('GB'));
        $this->assertSame('91', CountryService::dialCode('IN'));
    }

    public function testAnUnknownCountryHasNoCode(): void
    {
        $this->assertSame('', CountryService::dialCode('ZZ'));
        $this->assertSame('', CountryService::dialCode(''));
    }

    // ── Joining the number ───────────────────────────────────────────────

    public function testTheNumberIsPrefixedWithTheChosenCode(): void
    {
        $this->assertSame('+91 98765 43210', CountryService::joinPhone('IN', '98765 43210'));
    }

    /** A shopper who typed their own prefix meant it. */
    public function testATypedPrefixWins(): void
    {
        $this->assertSame('+44 7700 900123', CountryService::joinPhone('IN', '+44 7700 900123'));
    }

    /** The trunk zero is for dialling inside the country, not in front of +44. */
    public function testTheTrunkZeroIsDropped(): void
    {
        $this->assertSame('+44 7700 900123', CountryService::joinPhone('GB', '07700 900123'));
    }

    /** The field is optional, so an empty one stores nothing. */
    public function testAnEmptyNumberStaysEmpty(): void
    {
        $this->assertSame('', CountryService::joinPhone('IN', ''));
        $this->assertSame('', CountryService::joinPhone('IN', '   '));
        $this->assertSame('', CountryService::joinPhone('IN', null));
    }

    /** An unknown country must not swallow the number the shopper typed. */
    public function testAnUnknownCountryKeepsTheNumber(): void
    {
        $this->assertSame('555 0100', CountryService::joinPhone('ZZ', '555 0100'));
    }

    // ── The form and the server agree ────────────────────────────────────

    /** The picker is only useful if the server reads it. */
    public function testTheChosenCodeReachesTheStoredNumber(): void
    {
        $this->assertStringContainsString('phone-field.php', $this->view(),
            'the sign-up form no longer offers a country picker');

        $partial = (string) file_get_contents(WK_ROOT . '/views/store/partials/phone-field.php');
        $this->assertStringContainsString('_code"', $partial);

        $this->assertStringContainsString('joinPhone(', $this->controller(),
            'the form sends a country code the server never joins on');
    }

    /**
     * The rules under the password box are a promise. A shopper with
     * JavaScript off must not be able to register around them.
     */
    public function testEveryRuleTheFormShowsIsAlsoEnforcedServerSide(): void
    {
        $view = $this->view();
        $register = substr($this->controller(), strpos($this->controller(), 'public function register('));
        $register = substr($register, 0, strpos($register, 'public function showLogin('));

        $this->assertStringContainsString('8+ chars', $view);
        $this->assertStringContainsString("'password' => 'required|min:8'", $register, '8 characters is not checked server-side');

        $this->assertStringContainsString('1 number', $view);
        $this->assertStringContainsString("preg_match('/[0-9]/", $register, 'the number rule is not checked server-side');

        $this->assertStringContainsString('1 special', $view);
        $this->assertStringContainsString("preg_match('/[^a-zA-Z0-9]/", $register, 'the special-character rule is not checked server-side');

        $this->assertStringContainsString("password_confirm", $register, 'the confirmation is not checked server-side');
    }

    /** A strength reading nobody updates is decoration. */
    public function testTheStrengthMeterIsDrivenByTheTypedPassword(): void
    {
        $view = $this->view();
        $this->assertStringContainsString('wkPwStrength', $view);
        $this->assertStringContainsString("id=\"pwStrength\"", $view);
        $this->assertStringContainsString('oninput="checkPw()"', $view);
        $this->assertStringContainsString('wkPwStrength(pw)', $view, 'the meter never reads the password');

        // Length has to count for more than one threshold, or a passphrase
        // scores the same as eight characters of punctuation.
        $this->assertStringContainsString('[8, 12, 16, 20, 24]', $view);
    }

    // ── The account menu ─────────────────────────────────────────────────

    /** Signed in or not, the account menu is in the same place. */
    public function testTheAccountMenuExistsInBothStates(): void
    {
        $layout = $this->layout();

        $menu = substr($layout, strpos($layout, 'id="accountMenu"'));
        $menu = substr($menu, 0, strpos($menu, 'wk-cart-btn'));

        $this->assertStringContainsString('$isLoggedIn', $menu, 'the menu does not vary with sign-in state');

        // Signed out: the two ways in, and the guest route to an order.
        $this->assertStringContainsString("\$url('account/login')", $menu);
        $this->assertStringContainsString("\$url('account/register')", $menu);

        // Signed in: everything that was there before stays there.
        foreach (['account', 'account/orders', 'account/profile', 'account/addresses', 'account/logout'] as $path) {
            $this->assertStringContainsString("\$url('{$path}')", $menu, "the signed-in menu lost {$path}");
        }
    }

    /** A menu that opens must also close, and say which it is. */
    public function testTheMenuToggleIsWiredToTheMenuItOpens(): void
    {
        $layout = $this->layout();

        $this->assertStringContainsString("id=\"accountToggle\"", $layout);
        $this->assertStringContainsString("id=\"accountDrop\"", $layout);
        $this->assertStringContainsString('wkToggleAccount()', $layout);
        $this->assertStringContainsString("aria-expanded", $layout, 'the toggle never reports its state');
        $this->assertStringContainsString("getElementById('accountMenu').contains(e.target)", $layout,
            'clicking away does not close the menu');
    }

    /** Signing out changes state, so it cannot be a plain link. */
    public function testSigningOutIsAPostedForm(): void
    {
        $layout = $this->layout();
        $form = substr($layout, strpos($layout, 'method="POST" action="<?= $url(\'account/logout\')'));
        $form = substr($form, 0, strpos($form, '</form>'));

        $this->assertStringContainsString('csrfField()', $form, 'signing out is not protected against forgery');
        $this->assertStringContainsString('type="submit"', $form);
    }

    /**
     * A view that prints through $e() has to define it. Views get no helpers
     * by default, so calling one it never defined is a fatal error that takes
     * the whole page — layout included — down with it.
     */
    public function testEveryViewDefinesTheEscaperItUses(): void
    {
        $views = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(WK_ROOT . '/views', \FilesystemIterator::SKIP_DOTS)
        );

        $checked = 0;
        foreach ($views as $file) {
            if ($file->getExtension() !== 'php') continue;
            $src = (string) file_get_contents($file->getPathname());
            if (!preg_match('/(?<![\w$>])\$e\(/', $src)) continue;

            $checked++;
            $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(WK_ROOT) + 1));
            $this->assertMatchesRegularExpression(
                '/\$e\s*=\s*(fn|function)/',
                $src,
                "{$rel} prints through \$e() but never defines it"
            );
        }

        $this->assertGreaterThan(10, $checked, 'the sweep found almost no views, so it is not proving anything');
    }
}
