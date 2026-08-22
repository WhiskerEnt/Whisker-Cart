<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Full-viewport overlays are the one place a CSS mistake takes the whole page
 * down: an author `display` declaration beats the `hidden` attribute, so a
 * base `display: flex` on a `position: fixed; inset: 0` element leaves an
 * invisible sheet over everything, swallowing every click.
 *
 * Any overlay that hides itself with the hidden attribute must either not set
 * display in its base rule, or say what hidden means explicitly.
 */
class OverlayCssTest extends TestCase
{
    /** @return array<string,string> selector => declarations */
    private function rules(string $file): array
    {
        $css = (string) file_get_contents(WK_ROOT . '/assets/css/' . $file);
        // Comments sit between rules, so they land in the selector capture.
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('/([^{}]+)\{([^}]*)\}/', $css, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $r) {
            $sel = trim(preg_replace('/\s+/', ' ', $r[1]));
            if ($sel === '' || str_starts_with($sel, '@')) continue;
            $out[$sel] = $r[2];
        }
        return $out;
    }

    public function testNoFullScreenOverlayDefeatsTheHiddenAttribute(): void
    {
        $examined = 0;
        foreach (['admin.css', 'store.css'] as $file) {
            $rules = $this->rules($file);

            // An overlay is safe if it has an inert state — either it declares
            // what [hidden] means, or a modifier class turns off pointer events.
            $hasInertState = [];
            foreach ($rules as $sel => $body) {
                if (preg_match('/^(\.[\w-]+)(?:\[hidden\]|:not\(\[hidden\]\))/', $sel, $m)) {
                    $hasInertState[$m[1]] = true;
                }
                if (preg_match('/^(\.[\w-]+)\.[\w-]+$/', $sel, $m)
                    && preg_match('/pointer-events:\s*none/', $body)) {
                    $hasInertState[$m[1]] = true;
                }
            }

            foreach ($rules as $sel => $body) {
                // A base class rule only — not one already qualified by [hidden].
                if (!preg_match('/^(\.[\w-]+)$/', trim($sel), $m)) continue;
                $class = $m[1];

                $covers = preg_match('/position:\s*fixed/', $body) && preg_match('/inset:\s*0/', $body);
                if (!$covers) continue;
                $examined++;

                $setsDisplay = preg_match('/(?:^|;)\s*display:\s*(?:flex|block|grid|inline-flex)/', $body);
                if (!$setsDisplay) continue;

                // Setting display is fine unless the element is hidden via the
                // attribute, in which case the declaration would win over it.
                $this->assertArrayHasKey(
                    $class,
                    $hasInertState,
                    "{$file}: {$class} covers the viewport and sets display, but has no inert state. "
                    . 'An author display declaration beats the hidden attribute, so this can leave an '
                    . "invisible sheet swallowing every click. Add {$class}[hidden] { display: none } or "
                    . 'a modifier class that sets pointer-events: none.'
                );
            }
        }

        // Without this the test passes silently if the parser stops matching.
        $this->assertGreaterThanOrEqual(
            2,
            $examined,
            'expected to inspect the known full-viewport overlays — the CSS parser is no longer matching them'
        );
    }

    /** The refund modal specifically, since that is the one that broke. */
    public function testRefundModalIsInertWhenHidden(): void
    {
        $rules = $this->rules('admin.css');

        $this->assertArrayHasKey('.wk-modal[hidden]', $rules, 'the modal must say what hidden means');
        $this->assertMatchesRegularExpression(
            '/display:\s*none/',
            $rules['.wk-modal[hidden]'],
            'a hidden modal must not be laid out'
        );

        $this->assertArrayNotHasKey(
            '.wk-modal',
            array_filter($rules, fn($body, $sel) => $sel === '.wk-modal'
                && preg_match('/(?:^|;)\s*display:\s*(?:flex|block|grid)/', $body),
                ARRAY_FILTER_USE_BOTH),
            'the base .wk-modal rule must not set a display that overrides [hidden]'
        );
    }

    /** Overlays hidden by a class must stop taking clicks in that state. */
    public function testClassHiddenOverlaysDropPointerEvents(): void
    {
        foreach (['admin.css' => '.wk-loader.hidden', 'store.css' => '.wk-page-loader.done'] as $file => $sel) {
            $rules = $this->rules($file);
            $this->assertArrayHasKey($sel, $rules, "{$file}: expected {$sel}");
            $this->assertMatchesRegularExpression(
                '/pointer-events:\s*none/',
                $rules[$sel],
                "{$file}: {$sel} fades out but would still swallow clicks without pointer-events: none"
            );
        }
    }
}
