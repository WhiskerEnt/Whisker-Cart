<?php
namespace Tests\Unit;

use App\Services\CountryService;
use PHPUnit\Framework\TestCase;

/**
 * An email that cannot receive a receipt and a phone number that cannot be
 * rung are both worse than a blank field: the order goes through and nobody
 * finds out until someone tries to make contact.
 */
class ContactValidationTest extends TestCase
{
    // ── Pulling a stored number apart ────────────────────────────────────

    /** What was saved has to come back out as the same number. */
    public function testAStoredNumberSurvivesTheRoundTrip(): void
    {
        foreach (['+91 98765 43210', '+1 555 0100', '+44 7700 900123', '+7 495 1234567'] as $stored) {
            $parts = CountryService::splitPhone($stored);
            $this->assertNotSame('', $parts['code'], "{$stored} lost its country");
            $this->assertSame($stored, CountryService::joinPhone($parts['code'], $parts['number']),
                "{$stored} did not survive being split and rejoined");
        }
    }

    /** +1 is a prefix of +1264, and the longer code owns the number. */
    public function testTheLongestMatchingCodeWins(): void
    {
        $this->assertSame('AI', CountryService::splitPhone('+1264 555 0100')['code']);
        $this->assertSame('US', CountryService::splitPhone('+1 555 0100')['code']);
        $this->assertSame('CW', CountryService::splitPhone('+599 9 5551234')['code']);
    }

    /** A code several countries share must resolve the same way every time. */
    public function testASharedCodeResolvesConsistently(): void
    {
        foreach ([['+1 555 0100', 'US'], ['+7 495 1234567', 'RU'], ['+44 20 7946 0000', 'GB']] as [$stored, $expected]) {
            $this->assertSame($expected, CountryService::splitPhone($stored)['code']);
            // Twice, because a guess that varies would move the picker about.
            $this->assertSame($expected, CountryService::splitPhone($stored)['code']);
        }
    }

    /** A number with no prefix is shown exactly as it was stored. */
    public function testANumberWithoutAPrefixIsLeftAlone(): void
    {
        $this->assertSame(['code' => '', 'number' => '9876543210'], CountryService::splitPhone('9876543210'));
        $this->assertSame(['code' => '', 'number' => ''], CountryService::splitPhone(''));
        $this->assertSame(['code' => '', 'number' => ''], CountryService::splitPhone(null));
    }

    /** An unrecognised prefix is not silently reassigned to some country. */
    public function testAnUnknownPrefixKeepsTheNumberWhole(): void
    {
        $this->assertSame('', CountryService::splitPhone('+999 12345')['code']);
        $this->assertSame('+999 12345', CountryService::splitPhone('+999 12345')['number']);
    }

    // ── What counts as a phone number ────────────────────────────────────

    public function testHowPeopleWriteNumbersIsAccepted(): void
    {
        foreach (['98765 43210', '(98765) 43210', '98765-43210', '98765.43210', '98765/43210'] as $written) {
            $this->assertNull(CountryService::phoneError('IN', $written), "{$written} should be accepted");
        }
    }

    public function testGarbageIsRejected(): void
    {
        $this->assertNotNull(CountryService::phoneError('IN', 'test'));
        $this->assertNotNull(CountryService::phoneError('IN', 'abcdefgh'));
        $this->assertNotNull(CountryService::phoneError('IN', '98765 ext 4'));
        $this->assertNotNull(CountryService::phoneError('IN', '++919876'));
    }

    public function testLengthIsBounded(): void
    {
        $this->assertNotNull(CountryService::phoneError('IN', '12'), 'too short is not caught');
        $this->assertNotNull(CountryService::phoneError('IN', '1234567890123456'), 'past 15 digits is not caught');
        $this->assertNull(CountryService::phoneError('IN', '98765 43210'));
    }

    /** What gets typed to get past a required field. */
    public function testOneDigitRepeatedIsNotANumber(): void
    {
        $this->assertNotNull(CountryService::phoneError('IN', '5555555555'));
        $this->assertNotNull(CountryService::phoneError('IN', '0000 0000 00'));
    }

    public function testTheFieldIsOptionalUnlessItIsNot(): void
    {
        $this->assertNull(CountryService::phoneError('IN', ''));
        $this->assertNull(CountryService::phoneError('IN', '   '));
        $this->assertNotNull(CountryService::phoneError('IN', '', true));
    }

    /** A prefix the shopper typed still has to be a real country. */
    public function testATypedPrefixIsChecked(): void
    {
        $this->assertNull(CountryService::phoneError('IN', '+44 7700 900123'));
        $this->assertNotNull(CountryService::phoneError('IN', '+999 12345678'));
    }

    // ── The same rules on both sides ─────────────────────────────────────

    /** The browser and the server must not disagree about what is valid. */
    public function testTheClientRulesMatchTheServerRules(): void
    {
        $js = (string) file_get_contents(WK_ROOT . '/assets/js/store.js');
        $block = substr($js, strpos($js, 'phone(value, dial)'));
        $block = substr($block, 0, strpos($block, 'dialFor(input)'));

        $this->assertStringContainsString('national.length < 4', $block, 'the browser uses a different minimum');
        $this->assertStringContainsString('total > 15', $block, 'the browser uses a different maximum');
        $this->assertStringContainsString('/^(\d)\1+$/', $block, 'the browser does not catch one repeated digit');
        $this->assertStringContainsString("replace(/[\\s().\\-\\/]/g, '')", $block,
            'the browser strips different punctuation from the server');
    }

    // ── Every form that asks ─────────────────────────────────────────────

    /** One phone field, so a fix reaches every form at once. */
    public function testEveryPhoneFieldComesFromTheSharedPartial(): void
    {
        $expected = [
            'views/store/account/register.php',
            'views/store/account/profile.php',
            'views/store/account/ticket-create.php',
            'views/store/checkout.php',
            'views/store/partials/lead-capture.php',
        ];
        foreach ($expected as $view) {
            $src = (string) file_get_contents(WK_ROOT . '/' . $view);
            $this->assertStringContainsString('phone-field.php', $src, "{$view} still has its own phone box");
            $this->assertDoesNotMatchRegularExpression(
                '/<input[^>]*type="tel"[^>]*name="phone"/',
                $src,
                "{$view} hand-rolls a phone input instead of using the partial"
            );
        }
    }

    /** A picker the server ignores would just be decoration. */
    public function testEveryControllerThatStoresAPhoneJoinsAndChecksIt(): void
    {
        $controllers = [
            'Store/AccountController.php'  => 2,   // register, and the profile edit
            'Store/CheckoutController.php' => 1,
            'Store/TicketController.php'   => 1,
            'Store/LeadController.php'     => 1,
        ];
        foreach ($controllers as $file => $joins) {
            $src = (string) file_get_contents(WK_ROOT . '/app/Controllers/' . $file);
            $this->assertGreaterThanOrEqual($joins, substr_count($src, 'joinPhone('),
                "{$file} stores a phone without joining the chosen country code");
            $this->assertStringContainsString('phoneError(', $src,
                "{$file} accepts a phone number without checking it");
        }
    }

    /** Live checking on every box where an address is typed. */
    public function testEveryEmailFieldIsCheckedAsItIsTyped(): void
    {
        $views = [
            'views/store/account/register.php',
            'views/store/account/login.php',
            'views/store/account/ticket-create.php',
            'views/store/checkout.php',
            'views/store/track.php',
            'views/store/partials/lead-capture.php',
        ];
        foreach ($views as $view) {
            $src = (string) file_get_contents(WK_ROOT . '/' . $view);
            // A PHP tag inside the attributes carries its own '>', so it has to
            // be matched whole rather than treated as the end of the element.
            preg_match_all('/<input(?:<\?.*?\?>|[^>])*>/s', $src, $all);
            $m = [array_values(array_filter($all[0], fn($tag) => str_contains($tag, 'type="email"')))];
            $this->assertNotEmpty($m[0], "{$view} was expected to ask for an email");
            foreach ($m[0] as $input) {
                if (str_contains($input, 'disabled')) continue;   // shown, not asked for
                $this->assertStringContainsString('data-wk-validate="email"', $input,
                    "{$view} has an email box with no live checking");
            }
        }
    }

    /** Checkout cannot take an order it has no way to confirm. */
    public function testCheckoutRefusesAnUnusableEmail(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CheckoutController.php');
        $block = substr($src, strpos($src, '$emailRaw = trim('));
        $block = substr($block, 0, 1400);

        $this->assertStringContainsString('FILTER_VALIDATE_EMAIL', $block);
        $this->assertMatchesRegularExpression(
            '/if \(\$email === \'\'\) \{\s*Session::flash\(\'error\'/',
            $block,
            'a mistyped address is blanked and the order placed anyway'
        );
    }
}
