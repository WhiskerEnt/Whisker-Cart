<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * An author `display` declaration beats the `hidden` attribute. On a
 * full-viewport overlay that leaves an invisible sheet over the page
 * swallowing every click; on a panel of links it leaves them invisible but
 * still clickable. Both have happened, so both are guarded here.
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

    private function setsDisplay(string $body): bool
    {
        return (bool) preg_match('/(?:^|;)\s*display:\s*(?:flex|block|grid|inline-flex|inline-block)/', $body);
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

                if (!$this->setsDisplay($body)) continue;

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

        $base = $rules['.wk-modal'] ?? '';
        $this->assertFalse(
            $this->setsDisplay($base),
            'the base .wk-modal rule must not set a display that overrides [hidden]'
        );
    }

    /**
     * Anything a view hides with the hidden attribute must have a matching
     * [hidden] rule if its own styling sets display. Otherwise it stays laid
     * out while "hidden" — invisible, and for links, still taking clicks.
     */
    public function testElementsHiddenByAttributeAreActuallyHidden(): void
    {
        $rules = array_merge($this->rules('store.css'), $this->rules('admin.css'));

        // Scanned line by line rather than by tag, because the attribute is
        // usually emitted by a PHP expression, and the closing tag of that
        // expression ends any tag-shaped match early.
        $hiddenClasses = [];
        foreach (glob(WK_ROOT . '/views/store/partials/*.php') as $view) {
            $lines = file($view) ?: [];
            foreach ($lines as $i => $line) {
                if (!preg_match("/(?:^|\s|')hidden(?:'|\s|>)/", $line)) continue;

                // The class may sit a line or two above, on a wrapped tag.
                $window = implode(' ', array_slice($lines, max(0, $i - 2), 3));
                if (!preg_match('/class="([^"]+)"/', $window, $c)) continue;

                foreach (preg_split('/\s+/', trim($c[1])) as $class) {
                    if ($class !== '' && !str_contains($class, '<')) {
                        $hiddenClasses['.' . $class] = basename($view);
                    }
                }
            }
        }
        $this->assertNotEmpty($hiddenClasses, 'no attribute-hidden elements found — update the parser in this test');

        foreach ($hiddenClasses as $class => $view) {
            $base = $rules[$class] ?? null;
            if ($base === null || !$this->setsDisplay($base)) continue;

            $this->assertArrayHasKey(
                $class . '[hidden]',
                $rules,
                "{$view}: {$class} is hidden with the attribute but its own rule sets display, "
                . "which overrides it. Add {$class}[hidden] { display: none }."
            );
            $this->assertMatchesRegularExpression(
                '/display:\s*none/',
                $rules[$class . '[hidden]'],
                "{$class}[hidden] must set display: none"
            );
        }
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
