<?php
namespace Tests\Unit;

use Core\Icons;
use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Icons are inline SVG that inherit the text colour and stay out of the
 * accessibility tree unless given a name.
 */
class IconTest extends TestCase
{
    public function testAnIconIsSelfContainedInlineSvg(): void
    {
        $svg = View::icon('shopping-cart');

        $this->assertStringStartsWith('<svg class="wk-icon" width="16" height="16" viewBox="0 0 24 24"', $svg);
        $this->assertStringContainsString('stroke="currentColor"', $svg);
        $this->assertStringContainsString('aria-hidden="true"', $svg);
        $this->assertStringContainsString('focusable="false"', $svg);
        $this->assertStringContainsString(Icons::SET['shopping-cart'], $svg);
        $this->assertStringEndsWith('</svg>', $svg);
        $this->assertStringNotContainsString('<use', $svg, 'no sprite to depend on');
    }

    public function testSizeAndClassAreApplied(): void
    {
        $svg = View::icon('package', 24, 'wk-nav-icon');

        $this->assertStringContainsString('class="wk-icon wk-nav-icon"', $svg);
        $this->assertStringContainsString('width="24" height="24"', $svg);
    }

    public function testALabelledIconIsAnImageWithAName(): void
    {
        $svg = View::icon('x', 20, '', 'Remove "this"');

        $this->assertStringContainsString('role="img" aria-label="Remove &quot;this&quot;"', $svg);
        $this->assertStringNotContainsString('aria-hidden', $svg);
    }

    public function testAnUnknownNameIsAMistakeNotABlank(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        View::icon('no-such-icon');
    }

    public function testEveryIconIsWellFormedMarkupWithNoStyleOrScript(): void
    {
        foreach (Icons::SET as $name => $inner) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $name);
            $doc = new \DOMDocument();
            $this->assertTrue(
                @$doc->loadXML('<svg xmlns="http://www.w3.org/2000/svg">' . $inner . '</svg>'),
                "$name is not well-formed"
            );
            $this->assertDoesNotMatchRegularExpression('/<(script|style|foreignObject)|on[a-z]+=|href=/i', $inner, "$name carries more than drawing instructions");
        }
    }

    public function testTheSetCarriesItsLicence(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/core/Icons.php');

        $this->assertStringContainsString('ISC License', $src);
        $this->assertStringContainsString('Lucide Icons and Contributors', $src);
        $this->assertStringContainsString('Cole Bemis', $src, 'the Feather-derived icons carry their own notice');
    }
}
