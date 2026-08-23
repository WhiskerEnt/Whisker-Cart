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

    /**
     * The bar floats over the page, so the wrapper must not take pointer
     * events — otherwise the gaps between icons swallow clicks on the content
     * behind it.
     */
    public function testTheBarDoesNotObstructThePageBehindIt(): void
    {
        $css = (string) file_get_contents(WK_ROOT . '/assets/css/store.css');

        $this->assertSame(1, preg_match('/\.wk-social \{([^}]*)\}/', $css, $wrapper), 'no .wk-social rule');
        $this->assertMatchesRegularExpression(
            '/pointer-events:\s*none/',
            $wrapper[1],
            'the wrapper spans the viewport, so it must let clicks through'
        );

        $this->assertSame(1, preg_match('/\.wk-social-link \{([^}]*)\}/', $css, $link), 'no .wk-social-link rule');
        $this->assertMatchesRegularExpression(
            '/pointer-events:\s*auto/',
            $link[1],
            'the links themselves must still be clickable'
        );
    }

    /**
     * Setting one overflow axis to anything but visible makes the other axis
     * compute to auto, which clipped each icon's shadow at a hard rectangular
     * edge and cropped the icons themselves.
     */
    public function testTheColumnDoesNotClipItsIcons(): void
    {
        $css = (string) file_get_contents(WK_ROOT . '/assets/css/store.css');
        $this->assertSame(1, preg_match('/\.wk-social-inner \{([^}]*)\}/', $css, $m), 'no .wk-social-inner rule');

        $this->assertSame(
            0,
            preg_match('/overflow(-x|-y)?:\s*(auto|scroll|hidden|clip)/', $m[1]),
            'the column must not set overflow: one axis makes the other compute to auto, '
            . 'and the icons and their shadows get clipped'
        );
    }

    /** The bar paints nothing of its own; the icons carry all the colour. */
    public function testTheBarHasNoBackgroundPanel(): void
    {
        $css = (string) file_get_contents(WK_ROOT . '/assets/css/store.css');
        foreach (['.wk-social', '.wk-social-inner'] as $sel) {
            $this->assertSame(1, preg_match('/' . preg_quote($sel, '/') . ' \{([^}]*)\}/', $css, $m), "no {$sel} rule");
            $this->assertSame(
                0,
                preg_match('/(?:^|;)\s*background/', $m[1]),
                "{$sel} must not paint a background — the bar is meant to float over the page"
            );
        }
    }

    /** It sits below the chat bubble rather than over it. */
    public function testItDoesNotOutrankTheChatBubble(): void
    {
        $css = (string) file_get_contents(WK_ROOT . '/assets/css/store.css');
        preg_match('/\.wk-social \{[^}]*z-index:\s*(\d+)/', $css, $m);
        $this->assertNotEmpty($m, 'the bar needs an explicit z-index');
        $this->assertLessThan(9999, (int) $m[1], 'the chat bubble sits at 9999 and must stay on top');
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
