<?php
namespace Tests\Unit;

use App\Services\Money;
use PHPUnit\Framework\TestCase;

/**
 * Prices come out of the database as decimal strings and are cast to
 * floats. Binary floating point cannot hold most of them exactly, so any
 * arithmetic done directly on them drifts by fractions of a cent. Money
 * exists so that drift never reaches a comparison, a stored total or a
 * gateway.
 */
class MoneyTest extends TestCase
{
    public function testCentsAreRoundedNotTruncated(): void
    {
        $this->assertSame(29, Money::cents(0.29));
        $this->assertSame(2999, Money::cents((float) '29.99'));
        $this->assertSame(113, Money::cents(1.13));
        $this->assertSame(104999, Money::cents(1049.99));
        $this->assertSame(0, Money::cents(0.0));
    }

    public function testSumsAreExactAtTheCent(): void
    {
        $this->assertSame(0.3, Money::sum(0.1, 0.2));
        $this->assertSame(0.8, Money::sum(0.1, 0.7));
        $this->assertSame(60.67, Money::sum(59.97, 0.70));
        $this->assertSame(0.0, Money::sum());
    }

    public function testSubtractionComesBackToZero(): void
    {
        $left = 100.0;
        foreach ([33.33, 33.33, 33.34] as $part) {
            $left = Money::subtract($left, $part);
        }
        $this->assertSame(0.0, $left);
    }

    public function testQuantitiesAndRatesRoundToTheCent(): void
    {
        $this->assertSame(59.97, Money::times(19.99, 3));
        $this->assertSame(0.3, Money::times(0.1, 3));
        $this->assertSame(30.3, Money::times(10.10, 3));
        $this->assertSame(50.0, Money::times(33.33, 1.5));
    }

    public function testPercentagesRoundToTheCent(): void
    {
        $this->assertSame(3.6, Money::percent(19.99, 18));
        $this->assertSame(12.5, Money::percent(100, 12.5));
        $this->assertSame(16.67, Money::percent(33.33, 50));
        $this->assertSame(0.0, Money::percent(19.99, 0));
    }

    public function testSubtotalWorksOnRowsAsTheDatabaseReturnsThem(): void
    {
        $lines = [
            ['unit_price' => '19.99', 'quantity' => '3'],
            ['unit_price' => '0.10', 'quantity' => 7],
            ['unit_price' => '0.70', 'quantity' => 1],
        ];

        $this->assertSame(61.37, Money::subtotal($lines));
        $this->assertSame(0.0, Money::subtotal([]));

        $withTotals = Money::withLineTotals($lines);
        $this->assertSame([59.97, 0.7, 0.7], array_column($withTotals, 'line_total'));
        $this->assertSame('19.99', $withTotals[0]['unit_price'], 'the rows are otherwise untouched');
    }

    public function testComparisonsIgnoreFloatingPointNoise(): void
    {
        $this->assertTrue(Money::equals(0.1 + 0.2, 0.3));
        $this->assertTrue(Money::atLeast(0.1 + 0.7, 0.8), 'a basket of 0.10 and 0.70 reaches a 0.80 threshold');
        $this->assertFalse(Money::atLeast(0.79, 0.8));
        $this->assertTrue(Money::atLeast(0.8, 0.8));
        $this->assertFalse(Money::equals(0.29, 0.28));
    }

    /**
     * What the invoice shows must add up: the printed parts, summed by
     * hand, equal the printed total.
     */
    public function testATotalIsAlwaysTheSumOfThePartsAsShown(): void
    {
        mt_srand(1404);
        for ($i = 0; $i < 500; $i++) {
            $subtotal = mt_rand(1, 999999) / 100;
            $tax      = Money::percent($subtotal, mt_rand(0, 2800) / 100);
            $shipping = mt_rand(0, 99999) / 100;
            $discount = Money::percent($subtotal, mt_rand(0, 5000) / 100);

            $total = Money::subtract(Money::sum($subtotal, $tax, $shipping), $discount);

            $shown = fn (float $amount): int => (int) str_replace('.', '', number_format($amount, 2, '.', ''));
            $this->assertSame(
                $shown($subtotal) + $shown($tax) + $shown($shipping) - $shown($discount),
                $shown($total),
                "subtotal $subtotal, tax $tax, shipping $shipping, discount $discount"
            );
        }
    }

    /**
     * Money is the only place that rounds to two decimals or compares
     * with an epsilon. Ratings round too; they are not money.
     */
    public function testArithmeticOnMoneyGoesThroughMoney(): void
    {
        $files = array_merge(
            glob(WK_ROOT . '/app/Controllers/*/*.php'),
            glob(WK_ROOT . '/app/Services/*.php'),
            glob(WK_ROOT . '/views/*/*.php'),
            glob(WK_ROOT . '/views/*/*/*.php')
        );
        $notMoney = ['Money.php', 'ReviewService.php'];

        foreach ($files as $file) {
            if (in_array(basename($file), $notMoney, true)) continue;
            $src = (string) file_get_contents($file);
            $rel = substr($file, strlen(WK_ROOT) + 1);

            $this->assertDoesNotMatchRegularExpression('/\bround\([^;]*,\s*2\)/', $src, "$rel rounds to two decimals by hand");
            $this->assertDoesNotMatchRegularExpression('/[+-]\s*0\.0+1\b/', $src, "$rel compares money with an epsilon");
            $this->assertDoesNotMatchRegularExpression('/price\'\]\s*\*|\*\s*(\((int|float)\))?\s*\$\w+\[\'(unit_|sale_)?price\'\]/', $src, "$rel multiplies a price itself");
        }
    }
}
