<?php
namespace Tests\Unit;

use App\Services\CurrencyService;
use PHPUnit\Framework\TestCase;

/**
 * Money handling for refunds. These are the arithmetic mistakes that quietly
 * send the customer the wrong amount, so they are pinned down by value.
 */
class RefundMoneyTest extends TestCase
{
    /**
     * Binary floating point makes 0.29 * 100 come out as 28.999…, and casting
     * that to int truncates to 28 — a cent short on every affected amount.
     */
    public function testMinorUnitsAreRoundedNotTruncated(): void
    {
        $this->assertSame(29, CurrencyService::toMinorUnits(0.29, 'INR'));
        $this->assertSame(115, CurrencyService::toMinorUnits(1.15, 'USD'));
        $this->assertSame(887, CurrencyService::toMinorUnits(8.87, 'EUR'));
        $this->assertSame(104999, CurrencyService::toMinorUnits(1049.99, 'INR'));
    }

    /**
     * Yen has no hundredths. Multiplying by 100 would refund a hundred times
     * the intended amount.
     */
    public function testZeroDecimalCurrenciesAreNotMultiplied(): void
    {
        $this->assertSame(5000, CurrencyService::toMinorUnits(5000, 'JPY'));
        $this->assertSame(5000, CurrencyService::toMinorUnits(5000, 'KRW'));
        $this->assertSame(1200, CurrencyService::toMinorUnits(1200, 'VND'));
        $this->assertSame(0, CurrencyService::decimals('JPY'));
        $this->assertSame(2, CurrencyService::decimals('USD'));
    }

    public function testRoundTripThroughMinorUnitsKeepsTheAmount(): void
    {
        foreach ([['0.29','INR'], ['1.15','USD'], ['8.87','EUR'], ['1049.99','INR']] as [$amount, $code]) {
            $minor = CurrencyService::toMinorUnits((float) $amount, $code);
            $this->assertSame(
                (float) $amount,
                CurrencyService::fromMinorUnits($minor, $code),
                "{$amount} {$code} did not survive the round trip"
            );
        }
        $this->assertSame(5000.0, CurrencyService::fromMinorUnits(5000, 'JPY'));
    }

    public function testNoGatewayConvertsAmountsByHand(): void
    {
        // (int)($amount*100) inline in a gateway is the bug this replaces.
        foreach (glob(WK_ROOT . '/plugins/*/*.php') as $file) {
            $src = (string) file_get_contents($file);
            $this->assertSame(
                0,
                preg_match('/\(int\)\s*\(\s*\$amount\s*\*\s*100\s*\)/', $src),
                basename($file) . ' converts an amount by hand — use CurrencyService::toMinorUnits()'
            );
        }
    }
}
