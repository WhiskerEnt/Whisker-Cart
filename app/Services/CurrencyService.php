<?php
namespace App\Services;

use Core\Database;

/**
 * WHISKER — Currency Service
 * Uses frankfurter.app (free, no API key, ECB rates)
 * Caches rates for 1 hour in database
 */
class CurrencyService
{
    private static array $rateCache = [];

    /**
     * Get all supported currencies with symbols
     */
    public static function currencies(): array
    {
        return [
            'INR' => ['name' => 'Indian Rupee',      'symbol' => '₹'],
            'USD' => ['name' => 'US Dollar',          'symbol' => '$'],
            'EUR' => ['name' => 'Euro',               'symbol' => '€'],
            'GBP' => ['name' => 'British Pound',      'symbol' => '£'],
            'AUD' => ['name' => 'Australian Dollar',  'symbol' => 'A$'],
            'CAD' => ['name' => 'Canadian Dollar',    'symbol' => 'C$'],
            'JPY' => ['name' => 'Japanese Yen',       'symbol' => '¥'],
            'SGD' => ['name' => 'Singapore Dollar',   'symbol' => 'S$'],
            'AED' => ['name' => 'UAE Dirham',         'symbol' => 'د.إ'],
            'BRL' => ['name' => 'Brazilian Real',     'symbol' => 'R$'],
            'CNY' => ['name' => 'Chinese Yuan',       'symbol' => '¥'],
            'MYR' => ['name' => 'Malaysian Ringgit',  'symbol' => 'RM'],
            'THB' => ['name' => 'Thai Baht',          'symbol' => '฿'],
            'IDR' => ['name' => 'Indonesian Rupiah',  'symbol' => 'Rp'],
            'PHP' => ['name' => 'Philippine Peso',    'symbol' => '₱'],
            'KRW' => ['name' => 'South Korean Won',   'symbol' => '₩'],
            'ZAR' => ['name' => 'South African Rand', 'symbol' => 'R'],
            'SEK' => ['name' => 'Swedish Krona',      'symbol' => 'kr'],
            'NZD' => ['name' => 'New Zealand Dollar', 'symbol' => 'NZ$'],
            'CHF' => ['name' => 'Swiss Franc',        'symbol' => 'CHF'],
            'PLN' => ['name' => 'Polish Złoty',       'symbol' => 'zł'],
            'NOK' => ['name' => 'Norwegian Krone',    'symbol' => 'kr'],
            'DKK' => ['name' => 'Danish Krone',       'symbol' => 'kr'],
            'CZK' => ['name' => 'Czech Koruna',       'symbol' => 'Kč'],
            'HUF' => ['name' => 'Hungarian Forint',   'symbol' => 'Ft'],
            'RON' => ['name' => 'Romanian Leu',       'symbol' => 'lei'],
            'MXN' => ['name' => 'Mexican Peso',       'symbol' => 'MX$'],
            'HKD' => ['name' => 'Hong Kong Dollar',   'symbol' => 'HK$'],
        ];
    }

    /**
     * Get the store's base currency
     */
    public static function baseCurrency(): string
    {
        return Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency'") ?: 'INR';
    }

    /**
     * Get the store's base currency symbol
     */
    public static function baseSymbol(): string
    {
        return Database::fetchValue("SELECT setting_value FROM wk_settings WHERE setting_group='general' AND setting_key='currency_symbol'") ?: '₹';
    }

    /**
     * Get symbol for a currency code
     */
    public static function symbol(string $code): string
    {
        $currencies = self::currencies();
        return $currencies[strtoupper($code)]['symbol'] ?? $code;
    }

    /**
     * Convert amount from one currency to another
     */
    public static function convert(float $amount, string $from, string $to): float
    {
        if (strtoupper($from) === strtoupper($to)) return $amount;

        $rate = self::getRate($from, $to);
        if ($rate === null) return $amount; // Fallback: return unconverted

        return round($amount * $rate, 2);
    }

    /**
     * Format price in a specific currency
     */
    public static function format(float $amount, string $currencyCode): string
    {
        $symbol = self::symbol($currencyCode);
        $code = strtoupper($currencyCode);

        // No decimals for JPY, KRW, IDR
        if (in_array($code, ['JPY', 'KRW', 'IDR'])) {
            return $symbol . number_format(round($amount), 0);
        }

        return $symbol . number_format($amount, 2);
    }

    /**
     * Get exchange rate (cached 1 hour)
     */
    public static function getRate(string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);
        $cacheKey = "rate_{$from}_{$to}";

        // Check memory cache
        if (isset(self::$rateCache[$cacheKey])) {
            return self::$rateCache[$cacheKey];
        }

        // Check DB cache
        try {
            // Ensure cache table exists
            self::ensureCacheTable();

            $cached = Database::fetch(
                "SELECT setting_value FROM wk_settings WHERE setting_group='currency_cache' AND setting_key=?",
                [$cacheKey]
            );

            if ($cached) {
                $data = json_decode($cached['setting_value'], true);
                if ($data && $data['expires'] > time()) {
                    self::$rateCache[$cacheKey] = (float)$data['rate'];
                    return (float)$data['rate'];
                }
            }
        } catch (\Exception $e) {
            // Cache table might not exist, that's fine
        }

        // Fetch from API
        $rate = self::fetchRate($from, $to);
        if ($rate !== null) {
            self::$rateCache[$cacheKey] = $rate;

            // Store in DB cache (6 hour TTL — exchange rates don't change frequently)
            try {
                Database::query(
                    "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('currency_cache', ?, ?)
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    [$cacheKey, json_encode(['rate' => $rate, 'expires' => time() + 21600])]
                );
            } catch (\Exception $e) {}
        }

        return $rate;
    }

    /**
     * Exchange-rate API (Frankfurter, free, no key, ECB rates).
     *
     * NOTE: the old host api.frankfurter.app now 301-redirects to
     * api.frankfurter.dev/v1. The redirect only worked because
     * file_get_contents follows redirects — on hosts with allow_url_fopen
     * OFF (common on shared hosting) the whole thing failed silently and the
     * storefront showed the base amount under the switched symbol (e.g. a
     * ₹2799 product read "€2799" instead of "€25"). Point straight at the
     * current endpoint and fetch via cURL so redirects and allow_url_fopen
     * are no longer load-bearing.
     */
    private const RATES_API = 'https://api.frankfurter.dev/v1/latest';

    /**
     * Fetch live rate from the exchange-rate API.
     */
    private static function fetchRate(string $from, string $to): ?float
    {
        $body = self::httpGet(self::RATES_API . "?base={$from}&symbols={$to}");
        if ($body === null) return null;

        $data = json_decode($body, true);
        if (!$data || empty($data['rates'][$to])) return null;

        return (float)$data['rates'][$to];
    }

    /**
     * HTTP GET a small JSON body. cURL first (works even when
     * allow_url_fopen is off, and follows redirects), then a stream-wrapper
     * fallback. Returns the body string, or null on any failure.
     */
    private static function httpGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch !== false) {
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS      => 3,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_TIMEOUT        => 8,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                ]);
                $body = curl_exec($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $err  = curl_errno($ch);
                curl_close($ch);
                if ($err === 0 && $code >= 200 && $code < 300 && is_string($body) && $body !== '') {
                    return $body;
                }
                // else fall through to the stream fallback
            }
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 8, 'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 3],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return (is_string($body) && $body !== '') ? $body : null;
    }

    /**
     * Get all rates from base currency (for currency switcher)
     */
    public static function getAllRates(?string $from = null): array
    {
        $from = $from ?: self::baseCurrency();
        $cacheKey = "rates_all_{$from}";

        // Check DB cache
        try {
            self::ensureCacheTable();
            $cached = Database::fetch(
                "SELECT setting_value FROM wk_settings WHERE setting_group='currency_cache' AND setting_key=?",
                [$cacheKey]
            );
            if ($cached) {
                $data = json_decode($cached['setting_value'], true);
                if ($data && ($data['expires'] ?? 0) > time()) {
                    return $data['rates'] ?? [];
                }
            }
        } catch (\Exception $e) {}

        // Fetch all rates
        $body = self::httpGet(self::RATES_API . "?base={$from}");
        if ($body === null) return [];

        $data = json_decode($body, true);
        $rates = $data['rates'] ?? [];

        // Add base currency with rate 1
        $rates[$from] = 1.0;

        // Cache
        try {
            Database::query(
                "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('currency_cache', ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$cacheKey, json_encode(['rates' => $rates, 'expires' => time() + 3600])]
            );
        } catch (\Exception $e) {}

        return $rates;
    }

    /**
     * Get countries list with currency codes
     */
    public static function countries(): array
    {
        return [
            'IN' => ['name' => 'India',             'currency' => 'INR'],
            'US' => ['name' => 'United States',     'currency' => 'USD'],
            'GB' => ['name' => 'United Kingdom',    'currency' => 'GBP'],
            'CA' => ['name' => 'Canada',            'currency' => 'CAD'],
            'AU' => ['name' => 'Australia',         'currency' => 'AUD'],
            'DE' => ['name' => 'Germany',           'currency' => 'EUR'],
            'FR' => ['name' => 'France',            'currency' => 'EUR'],
            'JP' => ['name' => 'Japan',             'currency' => 'JPY'],
            'SG' => ['name' => 'Singapore',         'currency' => 'SGD'],
            'AE' => ['name' => 'UAE',               'currency' => 'AED'],
            'BR' => ['name' => 'Brazil',            'currency' => 'BRL'],
            'NZ' => ['name' => 'New Zealand',       'currency' => 'NZD'],
            'ZA' => ['name' => 'South Africa',      'currency' => 'ZAR'],
            'MY' => ['name' => 'Malaysia',          'currency' => 'MYR'],
            'TH' => ['name' => 'Thailand',          'currency' => 'THB'],
            'ID' => ['name' => 'Indonesia',         'currency' => 'IDR'],
            'PH' => ['name' => 'Philippines',       'currency' => 'PHP'],
            'KR' => ['name' => 'South Korea',       'currency' => 'KRW'],
            'SE' => ['name' => 'Sweden',            'currency' => 'SEK'],
            'CH' => ['name' => 'Switzerland',       'currency' => 'CHF'],
            'IT' => ['name' => 'Italy',             'currency' => 'EUR'],
            'ES' => ['name' => 'Spain',             'currency' => 'EUR'],
            'NL' => ['name' => 'Netherlands',       'currency' => 'EUR'],
            'IE' => ['name' => 'Ireland',           'currency' => 'EUR'],
            'PT' => ['name' => 'Portugal',          'currency' => 'EUR'],
            'AT' => ['name' => 'Austria',           'currency' => 'EUR'],
            'BE' => ['name' => 'Belgium',           'currency' => 'EUR'],
            'NO' => ['name' => 'Norway',            'currency' => 'NOK'],
            'DK' => ['name' => 'Denmark',           'currency' => 'DKK'],
            'FI' => ['name' => 'Finland',           'currency' => 'EUR'],
            'PL' => ['name' => 'Poland',            'currency' => 'PLN'],
            'CZ' => ['name' => 'Czech Republic',    'currency' => 'CZK'],
            'RO' => ['name' => 'Romania',           'currency' => 'RON'],
            'HU' => ['name' => 'Hungary',           'currency' => 'HUF'],
            'GR' => ['name' => 'Greece',            'currency' => 'EUR'],
            'HR' => ['name' => 'Croatia',           'currency' => 'EUR'],
            'SK' => ['name' => 'Slovakia',          'currency' => 'EUR'],
            'SI' => ['name' => 'Slovenia',          'currency' => 'EUR'],
            'LT' => ['name' => 'Lithuania',         'currency' => 'EUR'],
            'LV' => ['name' => 'Latvia',            'currency' => 'EUR'],
            'EE' => ['name' => 'Estonia',           'currency' => 'EUR'],
            'BG' => ['name' => 'Bulgaria',          'currency' => 'EUR'],
            'CY' => ['name' => 'Cyprus',            'currency' => 'EUR'],
            'LU' => ['name' => 'Luxembourg',        'currency' => 'EUR'],
            'MT' => ['name' => 'Malta',             'currency' => 'EUR'],
            'HK' => ['name' => 'Hong Kong',         'currency' => 'HKD'],
            'CN' => ['name' => 'China',             'currency' => 'CNY'],
            'MX' => ['name' => 'Mexico',            'currency' => 'MXN'],
        ];
    }

    private static function ensureCacheTable(): void
    {
        // wk_settings table is already used for cache, no extra table needed
    }
}