<?php
namespace Tests\Unit;

use App\Services\SocialService;
use PHPUnit\Framework\TestCase;

/**
 * The contact bar takes URLs typed by a shopkeeper and pins them over the
 * storefront, so two things are pinned down here: what may become an href,
 * and that the bar cannot swallow clicks meant for the page behind it.
 */
class SocialBarTest extends TestCase
{
    private function callBuild(string $key, string $kind, string $raw): ?string
    {
        $m = new \ReflectionMethod(SocialService::class, 'buildUrl');
        $m->setAccessible(true);
        return $m->invoke(null, $key, $kind, $raw);
    }

    public function testPhoneNumbersSurviveHumanFormatting(): void
    {
        // Shopkeepers type numbers the way they say them.
        $this->assertSame('tel:+919876543210', $this->callBuild('phone', 'number', '+91 98765 43210'));
        $this->assertSame('tel:+8801577447884', $this->callBuild('phone', 'number', '(880) 1577-447884'));
    }

    public function testWhatsAppBecomesAWaMeLink(): void
    {
        $url = $this->callBuild('whatsapp', 'number', '+91 98765 43210');
        $this->assertSame('https://wa.me/919876543210', $url);
    }

    public function testTooShortANumberIsRejected(): void
    {
        $this->assertNull($this->callBuild('phone', 'number', '123'));
        $this->assertNull($this->callBuild('phone', 'number', 'not a number'));
    }

    public function testABareDomainBecomesHttps(): void
    {
        $this->assertSame('https://facebook.com/yourshop', $this->callBuild('facebook', 'url', 'facebook.com/yourshop'));
        // An address already carrying a scheme is left as typed.
        $this->assertSame('https://instagram.com/shop', $this->callBuild('instagram', 'url', 'https://instagram.com/shop'));
    }

    public function testOnlyValidEmailsBecomeMailtoLinks(): void
    {
        $this->assertSame('mailto:hello@shop.com', $this->callBuild('email', 'email', 'hello@shop.com'));
        $this->assertNull($this->callBuild('email', 'email', 'not-an-email'));
    }

    /** A pasted script URL must never reach an href. */
    public function testScriptUrlsAreRefused(): void
    {
        foreach (['javascript:alert(1)', 'vbscript:x', 'data:text/html,<script>', 'file:///etc/passwd'] as $bad) {
            $built = $this->callBuild('facebook', 'url', $bad);
            if ($built !== null) {
                $this->assertFalse(
                    \Core\View::isSafeUrl($built),
                    "{$bad} produced a URL the renderer would accept"
                );
            } else {
                $this->assertNull($built);
            }
        }
    }

    /** Protocol-relative URLs load over whatever the page used and are inert here. */
    public function testProtocolRelativeUrlsAreRefused(): void
    {
        $this->assertFalse(\Core\View::isSafeUrl('//evil.example.com'));
    }

    public function testEveryChannelHasAnIcon(): void
    {
        foreach (array_keys(SocialService::channels()) as $key) {
            $svg = SocialService::icon($key);
            $this->assertStringStartsWith('<svg', $svg, "{$key} has no icon");
            $this->assertStringContainsString('viewBox="0 0 24 24"', $svg);
        }
        $this->assertSame('', SocialService::icon('nonsense'), 'an unknown channel must render nothing');
    }

    private function css(): string
    {
        return (string) file_get_contents(WK_ROOT . '/assets/css/store.css');
    }

    private function rule(string $selector): string
    {
        $this->assertSame(1, preg_match('/(?:^|\n)' . preg_quote($selector, '/') . ' \{([^}]*)\}/', $this->css(), $m), "no {$selector} rule");
        return $m[1];
    }

    /**
     * The button floats over the page, so the wrapper must not take clicks;
     * otherwise the space around the button swallows taps on the page.
     */
    public function testTheButtonDoesNotObstructThePageBehindIt(): void
    {
        $this->assertMatchesRegularExpression('/pointer-events:\s*none/', $this->rule('.wk-contact'));
        $this->assertMatchesRegularExpression('/pointer-events:\s*auto/', $this->rule('.wk-contact > *'), 'what is inside must still be clickable');
    }

    /** It lives in a bottom corner, clear of the slider arrows and the page's text. */
    public function testItSitsInABottomCornerAndReservesNoColumn(): void
    {
        $wrapper = $this->rule('.wk-contact');
        $this->assertMatchesRegularExpression('/position:\s*fixed/', $wrapper);
        $this->assertMatchesRegularExpression('/bottom:\s*calc\(20px \+ env\(safe-area-inset-bottom\)\)/', $wrapper, 'clear of a phone\'s home bar');
        $this->assertDoesNotMatchRegularExpression('/top:\s*50%/', $wrapper, 'not pinned to the middle of the screen');
        $this->assertStringNotContainsString('wk-has-social', $this->css(), 'the page no longer gives up a strip of its width');
    }

    /** It sits below the chat bubble rather than over it, and above it when on the same side. */
    public function testItDoesNotOutrankOrCoverTheChatBubble(): void
    {
        preg_match('/z-index:\s*(\d+)/', $this->rule('.wk-contact'), $m);
        $this->assertNotEmpty($m, 'the button needs an explicit z-index');
        $this->assertLessThan(9999, (int) $m[1], 'the chat bubble sits at 9999 and must stay on top');
        $this->assertStringContainsString('body:has(#wkChatToggle) .wk-contact-right { bottom:', $this->css());
    }

    /** Anything that takes over the screen gets the screen to itself. */
    public function testItStepsAsideForTheCookieBannerCartAndChat(): void
    {
        $css = $this->css();
        foreach (['.wk-cookie.is-open', '.wk-cart-drawer.open'] as $overlay) {
            $this->assertStringContainsString('body:has(' . $overlay . ') .wk-contact', $css, "not hidden for {$overlay}");
        }
        // The chat window's inline style always mentions flex (flex-direction),
        // so its open state is marked on the body rather than read from the style.
        $this->assertStringContainsString('body.wk-chat-open .wk-contact', $css);
        $this->assertStringNotContainsString('[style*="flex"]', $css);
        $this->assertStringContainsString("document.body.classList.toggle('wk-chat-open', chatOpen);", (string) file_get_contents(WK_ROOT . '/views/store/layouts/main.php'));
    }

    /** Every channel has its colour as a class, so the markup carries no inline styles. */
    public function testEveryChannelHasItsColour(): void
    {
        foreach (array_keys(SocialService::channels()) as $key) {
            $this->assertMatchesRegularExpression('/\.wk-brand-' . preg_quote($key, '/') . '\s*\{\s*--wk-brand:/', $this->css(), "{$key} has no colour");
        }
        $this->assertStringNotContainsString('style="', (string) file_get_contents(WK_ROOT . '/views/store/partials/social-bar.php'));
    }

    /** A long list would make the row too wide for a phone, so it becomes the menu. */
    public function testManyChannelsUseTheMenu(): void
    {
        $partial = (string) file_get_contents(WK_ROOT . '/views/store/partials/social-bar.php');
        $this->assertStringContainsString("(SocialService::collapsed() || count(\$links) > SocialService::DOCK_MAX) ? 'menu' : 'dock'", $partial);
        $this->assertSame(5, SocialService::DOCK_MAX);
        $this->assertStringContainsString('wk-contact-style-<?= $style ?>', $partial, 'the style is not named like the dock itself');
        $this->assertSame(1, substr_count($partial, 'id="wkContactButton"'), 'the menu is always in the markup, once');
    }

    /** The menu button says whether it is open, and Escape hands focus back to it. */
    public function testTheMenuIsUsableFromTheKeyboard(): void
    {
        $partial = (string) file_get_contents(WK_ROOT . '/views/store/partials/social-bar.php');
        $this->assertStringContainsString('aria-expanded="false" aria-controls="wkContactMenu"', $partial);
        $this->assertStringContainsString("if (e.key === 'Escape'", $partial);
        $this->assertStringContainsString('button.focus();', $partial);
        $this->assertStringContainsString('aria-label="<?= $e($l[\'label\']) ?>"', $partial, 'a dock tile is only an icon, so it needs a name');
    }

    /** Glass where the browser can blur, a plain surface where it cannot. */
    public function testTheGlassHasAFallback(): void
    {
        $css = $this->css();
        $this->assertStringContainsString('backdrop-filter: saturate(180%) blur(20px);', $css);
        $this->assertStringContainsString('@supports not ((backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px)))', $css);
        $this->assertMatchesRegularExpression('/--wk-glass: color-mix\(in srgb, var\(--wk-surface\)/', $css, 'the glass follows the shop\'s theme');
    }

    /** Every field the Social tab posts must be saved. */
    public function testEverySocialFieldIsWhitelisted(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/admin/settings.php');
        preg_match_all('/name="social_(social_[a-z_]+)"/', $view, $m);
        $posted = array_unique($m[1]);
        $this->assertNotEmpty($posted, 'no social fields found in the settings form');

        $allowed = SocialService::settingKeys();
        foreach ($posted as $field) {
            $this->assertContains(
                $field,
                $allowed,
                "the Social tab posts '{$field}' but SocialService::settingKeys() does not list it, so saving drops it"
            );
        }

        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/SettingsController.php');
        $this->assertStringContainsString(
            "'social'  => \\App\\Services\\SocialService::settingKeys()",
            $controller,
            'the settings controller must take the social whitelist from the service'
        );
    }

    public function testDefaultsToOffAndToTheLeft(): void
    {
        $migration = (string) file_get_contents(WK_ROOT . '/sql/migrations/20260822_v141_social_bar.sql');
        $this->assertStringContainsString("('social', 'social_enabled', '0')", $migration);
        $this->assertStringContainsString("('social', 'social_position', 'left')", $migration);
    }
}
