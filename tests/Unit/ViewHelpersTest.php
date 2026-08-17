<?php
namespace Tests\Unit;

use Core\View;
use PHPUnit\Framework\TestCase;

class ViewHelpersTest extends TestCase
{
    /** @dataProvider safeUrlProvider */
    public function testIsSafeUrl(string $url, bool $allowDataImage, bool $expected): void
    {
        $this->assertSame($expected, View::isSafeUrl($url, $allowDataImage));
    }

    public static function safeUrlProvider(): array
    {
        return [
            'https absolute'            => ['https://example.com/logo.png', false, true],
            'http absolute'             => ['http://example.com/logo.png', false, true],
            'rooted relative'           => ['/storage/uploads/logo.png', false, true],
            'bare slug'                 => ['shop', false, true],
            'fragment'                  => ['#top', false, true],
            'mailto'                    => ['mailto:a@b.com', false, true],
            'tel'                       => ['tel:+3312345678', false, true],
            'javascript scheme'         => ['javascript:alert(1)', false, false],
            'mixed-case javascript'     => ['JaVaScRiPt:alert(1)', false, false],
            'vbscript scheme'           => ['vbscript:x', false, false],
            'file scheme'               => ['file:///etc/passwd', false, false],
            'protocol relative'         => ['//evil.com/x.png', false, false],
            'empty'                     => ['', false, false],
            'data image without flag'   => ['data:image/png;base64,AAAA', false, false],
            'data image with flag'      => ['data:image/png;base64,AAAA', true, true],
            'data html with flag'       => ['data:text/html;base64,AAAA', true, false],
        ];
    }

    public function testSafeUrlNeutralizesAndEscapes(): void
    {
        $this->assertSame('#', View::safeUrl('javascript:alert(1)'));
        // Attribute-context escaping of an otherwise-safe URL.
        $this->assertSame('/a?x=1&amp;y=2', View::safeUrl('/a?x=1&y=2'));
    }

    public function testPriceFormatsWithExplicitSymbol(): void
    {
        // Regression for the "EUR store shows ₹" class: every call site that
        // knows its symbol must format with exactly that symbol.
        $this->assertSame('€10.50', View::price(10.5, '€'));
        $this->assertSame('$1,234.50', View::price(1234.5, '$'));
        $this->assertSame('zł0.00', View::price(0.0, 'zł'));
    }

    public function testEscapeHelper(): void
    {
        $this->assertSame('&lt;a href=&quot;x&quot;&gt;', View::e('<a href="x">'));
    }
}
