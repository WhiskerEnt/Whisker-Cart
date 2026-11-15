<?php
namespace App\Services;

/**
 * Money arithmetic at cent precision.
 *
 * Amounts travel through the application as floats, because that is what
 * the database driver and the views hand around. Every calculation here is
 * done in whole cents and rounded back, so two amounts that read the same
 * compare equal, and a total is always the sum of its parts as shown.
 *
 * Two decimals is the precision of every money column. Currencies with no
 * minor unit are dealt with where it matters, at the gateway, by
 * CurrencyService::toMinorUnits().
 */
final class Money
{
    /** Whole cents. 0.29 is 29, where truncating 28.999 would give 28. */
    public static function cents(float $amount): int
    {
        return (int) round($amount * 100);
    }

    public static function round(float $amount): float
    {
        return round($amount, 2);
    }

    /** Each part is rounded first, so the result matches what the parts show. */
    public static function sum(float ...$parts): float
    {
        $cents = 0;
        foreach ($parts as $part) {
            $cents += self::cents($part);
        }
        return $cents / 100;
    }

    public static function subtract(float $amount, float $less): float
    {
        return (self::cents($amount) - self::cents($less)) / 100;
    }

    /** A unit price by a quantity, or a rate by a weight. */
    public static function times(float $amount, float $factor): float
    {
        return round($amount * $factor, 2);
    }

    /** A percentage of an amount: tax on a subtotal, a discount on an order. */
    public static function percent(float $amount, float $percent): float
    {
        return round($amount * $percent / 100, 2);
    }

    /** unit_price by quantity for one line. */
    public static function lineTotal(array $line): float
    {
        return self::times((float) ($line['unit_price'] ?? 0), (float) ($line['quantity'] ?? 0));
    }

    /** The lines with a line_total on each, so a view never multiplies. */
    public static function withLineTotals(array $lines): array
    {
        foreach ($lines as &$line) {
            $line['line_total'] = self::lineTotal($line);
        }
        unset($line);
        return $lines;
    }

    /** Every line total, added at cent precision. */
    public static function subtotal(array $lines): float
    {
        $cents = 0;
        foreach ($lines as $line) {
            $cents += self::cents(self::lineTotal($line));
        }
        return $cents / 100;
    }

    public static function equals(float $amount, float $other): bool
    {
        return self::cents($amount) === self::cents($other);
    }

    public static function atLeast(float $amount, float $floor): bool
    {
        return self::cents($amount) >= self::cents($floor);
    }
}
