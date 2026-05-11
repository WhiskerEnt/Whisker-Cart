<?php
namespace App\Services;

use Core\Database;

/**
 * WHISKER — Tax Engine
 *
 * Supports:
 * - GST (India) — CGST + SGST for intra-state, IGST for inter-state
 * - VAT (EU/UK) — country-specific rates with standard/reduced classes
 * - Sales Tax (US) — state-level rates
 * - Custom rates per country/state
 * - Per-product tax class (standard, reduced, zero, exempt)
 * - Tax-inclusive and tax-exclusive pricing
 *
 * Tax rates stored in wk_tax_rates table.
 * Admin configures rates via Settings → Tax.
 */
class TaxService
{
    private static ?array $ratesCache = null;

    /**
     * Calculate tax for an order.
     *
     * @param float  $subtotal     Cart subtotal
     * @param array  $address      Billing/shipping address ['country' => 'IN', 'state' => 'KA', ...]
     * @param string $taxClass     Product tax class (standard, reduced, zero, exempt)
     * @param string $storeCountry Store's country code
     * @param string $storeState   Store's state code
     * @return array ['amount' => float, 'rate' => float, 'label' => string, 'breakdown' => [...]]
     */
    public static function calculate(float $subtotal, array $address, string $taxClass = 'standard', ?string $storeCountry = null, ?string $storeState = null): array
    {
        if ($taxClass === 'exempt' || $taxClass === 'zero') {
            return ['amount' => 0, 'rate' => 0, 'label' => 'Tax Exempt', 'breakdown' => []];
        }

        // Get store location
        $storeCountry = $storeCountry ?? Database::setting('general', 'store_country') ?? 'IN';
        $storeState = $storeState ?? Database::setting('general', 'store_state') ?? '';

        $custCountry = strtoupper($address['country'] ?? $storeCountry);
        $custState = strtoupper($address['state'] ?? '');

        // Try custom rates first
        $customRate = self::getCustomRate($custCountry, $custState, $taxClass);
        if ($customRate !== null) {
            $amount = round($subtotal * ($customRate['rate'] / 100), 2);
            return [
                'amount' => $amount,
                'rate' => $customRate['rate'],
                'label' => $customRate['label'] ?? 'Tax',
                'breakdown' => [['label' => $customRate['label'] ?? 'Tax', 'rate' => $customRate['rate'], 'amount' => $amount]],
            ];
        }

        // India GST
        if ($custCountry === 'IN' && $storeCountry === 'IN') {
            return self::calculateGST($subtotal, $custState, $storeState, $taxClass);
        }

        // EU VAT
        if (in_array($custCountry, self::EU_COUNTRIES)) {
            return self::calculateVAT($subtotal, $custCountry, $taxClass);
        }

        // UK VAT
        if ($custCountry === 'GB') {
            return self::calculateUKVAT($subtotal, $taxClass);
        }

        // US Sales Tax
        if ($custCountry === 'US') {
            return self::calculateUSSalesTax($subtotal, $custState, $storeState, $taxClass);
        }

        // Fallback: use global tax rate from settings
        $fallbackRate = (float)(Database::setting('checkout', 'tax_rate') ?? 0);
        $amount = round($subtotal * ($fallbackRate / 100), 2);
        return [
            'amount' => $amount,
            'rate' => $fallbackRate,
            'label' => 'Tax',
            'breakdown' => $fallbackRate > 0 ? [['label' => 'Tax', 'rate' => $fallbackRate, 'amount' => $amount]] : [],
        ];
    }

    /**
     * India GST — CGST + SGST (same state) or IGST (different state)
     */
    private static function calculateGST(float $subtotal, string $custState, string $storeState, string $taxClass): array
    {
        $rates = ['standard' => 18, 'reduced' => 12, 'low' => 5];
        $gstRate = $rates[$taxClass] ?? 18;

        $amount = round($subtotal * ($gstRate / 100), 2);

        if ($custState && $storeState && strtoupper($custState) === strtoupper($storeState)) {
            // Intra-state: split into CGST + SGST
            $half = round($amount / 2, 2);
            $halfRate = $gstRate / 2;
            return [
                'amount' => $amount,
                'rate' => $gstRate,
                'label' => 'GST (' . $gstRate . '%)',
                'breakdown' => [
                    ['label' => 'CGST', 'rate' => $halfRate, 'amount' => $half],
                    ['label' => 'SGST', 'rate' => $halfRate, 'amount' => $amount - $half],
                ],
            ];
        }

        // Inter-state: IGST
        return [
            'amount' => $amount,
            'rate' => $gstRate,
            'label' => 'IGST (' . $gstRate . '%)',
            'breakdown' => [
                ['label' => 'IGST', 'rate' => $gstRate, 'amount' => $amount],
            ],
        ];
    }

    /**
     * EU VAT — per-country standard/reduced rates
     */
    private static function calculateVAT(float $subtotal, string $country, string $taxClass): array
    {
        $vatRates = [
            'standard' => [
                'DE'=>19,'FR'=>20,'IT'=>22,'ES'=>21,'NL'=>21,'BE'=>21,'AT'=>20,'PL'=>23,
                'PT'=>23,'SE'=>25,'DK'=>25,'FI'=>24,'IE'=>23,'GR'=>24,'CZ'=>21,'RO'=>19,
                'HU'=>27,'BG'=>20,'HR'=>25,'SK'=>20,'SI'=>22,'LT'=>21,'LV'=>21,'EE'=>22,
                'CY'=>19,'LU'=>17,'MT'=>18,
            ],
            'reduced' => [
                'DE'=>7,'FR'=>5.5,'IT'=>10,'ES'=>10,'NL'=>9,'BE'=>6,'AT'=>10,'PL'=>8,
                'PT'=>13,'SE'=>12,'DK'=>25,'FI'=>14,'IE'=>13.5,'GR'=>13,'CZ'=>12,'RO'=>9,
                'HU'=>18,'BG'=>9,'HR'=>13,'SK'=>10,'SI'=>9.5,'LT'=>9,'LV'=>12,'EE'=>9,
                'CY'=>5,'LU'=>8,'MT'=>5,
            ],
        ];

        $rateMap = $vatRates[$taxClass] ?? $vatRates['standard'];
        $rate = $rateMap[$country] ?? 20; // Default 20% if country not in list

        $amount = round($subtotal * ($rate / 100), 2);
        return [
            'amount' => $amount,
            'rate' => $rate,
            'label' => 'VAT (' . $rate . '%)',
            'breakdown' => [['label' => 'VAT', 'rate' => $rate, 'amount' => $amount]],
        ];
    }

    /**
     * UK VAT — 20% standard, 5% reduced
     */
    private static function calculateUKVAT(float $subtotal, string $taxClass): array
    {
        $rate = match ($taxClass) {
            'reduced' => 5,
            'zero' => 0,
            default => 20,
        };
        $amount = round($subtotal * ($rate / 100), 2);
        return [
            'amount' => $amount,
            'rate' => $rate,
            'label' => 'VAT (' . $rate . '%)',
            'breakdown' => [['label' => 'VAT', 'rate' => $rate, 'amount' => $amount]],
        ];
    }

    /**
     * US Sales Tax — state-level. Only charged if nexus state matches.
     */
    private static function calculateUSSalesTax(float $subtotal, string $custState, string $storeState, string $taxClass): array
    {
        // Only charge sales tax if customer is in the same state as store (nexus)
        if ($storeState && $custState && strtoupper($custState) !== strtoupper($storeState)) {
            return ['amount' => 0, 'rate' => 0, 'label' => 'No Tax (out of state)', 'breakdown' => []];
        }

        $stateRates = [
            'AL'=>4,'AK'=>0,'AZ'=>5.6,'AR'=>6.5,'CA'=>7.25,'CO'=>2.9,'CT'=>6.35,'DE'=>0,
            'FL'=>6,'GA'=>4,'HI'=>4,'ID'=>6,'IL'=>6.25,'IN'=>7,'IA'=>6,'KS'=>6.5,
            'KY'=>6,'LA'=>4.45,'ME'=>5.5,'MD'=>6,'MA'=>6.25,'MI'=>6,'MN'=>6.875,'MS'=>7,
            'MO'=>4.225,'MT'=>0,'NE'=>5.5,'NV'=>6.85,'NH'=>0,'NJ'=>6.625,'NM'=>5.125,
            'NY'=>4,'NC'=>4.75,'ND'=>5,'OH'=>5.75,'OK'=>4.5,'OR'=>0,'PA'=>6,'RI'=>7,
            'SC'=>6,'SD'=>4.2,'TN'=>7,'TX'=>6.25,'UT'=>6.1,'VT'=>6,'VA'=>5.3,'WA'=>6.5,
            'WV'=>6,'WI'=>5,'WY'=>4,
        ];

        $rate = $stateRates[strtoupper($custState)] ?? 0;
        $amount = round($subtotal * ($rate / 100), 2);

        return [
            'amount' => $amount,
            'rate' => $rate,
            'label' => $rate > 0 ? 'Sales Tax (' . $rate . '%)' : 'No Sales Tax',
            'breakdown' => $rate > 0 ? [['label' => 'State Sales Tax', 'rate' => $rate, 'amount' => $amount]] : [],
        ];
    }

    /**
     * Get custom tax rate from wk_tax_rates table.
     */
    private static function getCustomRate(string $country, string $state, string $taxClass): ?array
    {
        if (self::$ratesCache === null) {
            self::$ratesCache = [];
            try {
                $rows = Database::fetchAll("SELECT * FROM wk_tax_rates WHERE is_active=1 ORDER BY priority DESC");
                foreach ($rows as $row) {
                    self::$ratesCache[] = $row;
                }
            } catch (\Exception $e) {
                // Table might not exist yet (upgrading from older version)
                return null;
            }
        }

        foreach (self::$ratesCache as $rate) {
            $matchCountry = !$rate['country'] || strtoupper($rate['country']) === $country;
            $matchState = !$rate['state'] || strtoupper($rate['state']) === $state;
            $matchClass = !$rate['tax_class'] || $rate['tax_class'] === $taxClass;
            if ($matchCountry && $matchState && $matchClass) {
                return ['rate' => (float)$rate['rate'], 'label' => $rate['label'] ?? 'Tax'];
            }
        }

        return null;
    }

    /**
     * Get formatted tax label for display.
     */
    public static function formatBreakdown(array $taxResult): string
    {
        if (empty($taxResult['breakdown'])) return '';
        $parts = [];
        foreach ($taxResult['breakdown'] as $b) {
            $parts[] = $b['label'] . ' ' . $b['rate'] . '%';
        }
        return implode(' + ', $parts);
    }

    /** EU member state country codes */
    private const EU_COUNTRIES = [
        'AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE',
        'IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE',
    ];
}
