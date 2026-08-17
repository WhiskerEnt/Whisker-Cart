<?php
namespace Tests\Unit;

use App\Services\CurrencyService;
use PHPUnit\Framework\TestCase;

class CurrencyServiceTest extends TestCase
{
    public function testSymbolLookupIsCaseInsensitiveAndFallsBackToCode(): void
    {
        $this->assertSame('€', CurrencyService::symbol('EUR'));
        $this->assertSame('€', CurrencyService::symbol('eur'));
        $this->assertSame('₹', CurrencyService::symbol('INR'));
        $this->assertSame('zł', CurrencyService::symbol('PLN'));
        // Unknown code falls back to the code itself, never to ₹.
        $this->assertSame('XXX', CurrencyService::symbol('XXX'));
    }

    public function testFormatUsesTwoDecimalsByDefault(): void
    {
        $this->assertSame('€1,234.50', CurrencyService::format(1234.5, 'EUR'));
        $this->assertSame('$0.00', CurrencyService::format(0.0, 'USD'));
    }

    public function testFormatOmitsDecimalsForZeroDecimalCurrencies(): void
    {
        $this->assertSame('¥1,000', CurrencyService::format(1000.4, 'JPY'));
        $this->assertSame('₩5', CurrencyService::format(5.0, 'KRW'));
        $this->assertSame('Rp10,000', CurrencyService::format(10000.0, 'IDR'));
    }

    public function testEveryCurrencyHasNameAndSymbol(): void
    {
        foreach (CurrencyService::currencies() as $code => $cur) {
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $code);
            $this->assertNotSame('', trim($cur['name'] ?? ''), "{$code} missing name");
            $this->assertNotSame('', trim($cur['symbol'] ?? ''), "{$code} missing symbol");
        }
    }

    public function testEveryCountryCurrencyIsAKnownCurrency(): void
    {
        // Regression: countries() used to reference NOK/DKK/HKD/MXN/PLN that
        // had no entry in currencies(), so their symbols rendered as raw codes
        // and the checkout country list drifted from the currency list.
        $currencies = CurrencyService::currencies();
        foreach (CurrencyService::countries() as $cc => $info) {
            $this->assertArrayHasKey(
                $info['currency'],
                $currencies,
                "Country {$cc} uses currency {$info['currency']} which is missing from currencies()"
            );
        }
    }

    public function testInstallerCurrencyListStaysInSyncWithCurrencyService(): void
    {
        // The install wizard duplicates the currency list because it runs
        // standalone (no autoloader). This guards the duplication: same codes,
        // same symbols, in both places.
        $src = file_get_contents(WK_ROOT . '/install/index.php');
        $this->assertNotFalse($src);
        $this->assertStringContainsString('function wk_install_currencies', $src, 'installer currency helper missing');

        preg_match_all("/'([A-Z]{3})'\s*=>\s*\['name'\s*=>\s*'[^']*',\s*'symbol'\s*=>\s*'([^']*)'\]/u", $src, $m, PREG_SET_ORDER);
        $installer = [];
        foreach ($m as $hit) {
            $installer[$hit[1]] = $hit[2];
        }
        $this->assertNotEmpty($installer, 'could not parse installer currency list');

        $service = array_map(static fn($c) => $c['symbol'], CurrencyService::currencies());
        ksort($installer);
        ksort($service);
        $this->assertSame($service, $installer, 'install/index.php currency list drifted from CurrencyService::currencies()');
    }
}
