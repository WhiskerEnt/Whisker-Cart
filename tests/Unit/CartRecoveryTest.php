<?php
namespace Tests\Unit;

use App\Services\CartRecoveryService;
use App\Services\LeadService;
use PHPUnit\Framework\TestCase;

/**
 * Recovery sends marketing email to people who did not buy anything, and hands
 * out links that restore a basket to whoever holds them. Both deserve rules
 * that are checked rather than assumed.
 */
class CartRecoveryTest extends TestCase
{
    private function service(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Services/CartRecoveryService.php');
    }

    // ── The bug that started this ────────────────────────────────────────

    /** The reminder went out blank for want of a template. */
    public function testTheReminderTemplateIsSeeded(): void
    {
        $migration = (string) file_get_contents(WK_ROOT . '/sql/migrations/20260826_v141_cart_recovery.sql');
        $this->assertStringContainsString("('abandoned-cart'", $migration);

        // Only this template's placeholders — the file seeds more than one.
        $tpl = substr($migration, strpos($migration, "('abandoned-cart'"));

        // Every placeholder it uses must be supplied by the sender.
        preg_match_all('/\{\{([a-z_]+)\}\}/', $tpl, $used);
        $sender = $this->service();
        foreach (array_unique($used[1]) as $placeholder) {
            $this->assertStringContainsString(
                '{{' . $placeholder . '}}',
                $sender,
                "the template uses {{{$placeholder}}} but sendReminder does not supply it"
            );
        }
    }

    /** Every slug the code sends must resolve to something, seeded or built in. */
    public function testNoSenderCanFallThroughToABlankEmail(): void
    {
        $email = (string) file_get_contents(WK_ROOT . '/app/Services/EmailService.php');

        // Every migration, not a chosen few — a template seeded elsewhere is
        // still seeded, and hard-coding the list makes this test lie.
        $seeded = '';
        foreach (glob(WK_ROOT . '/sql/migrations/*.sql') as $m) {
            $seeded .= (string) file_get_contents($m);
        }
        $seeded .= (string) file_get_contents(WK_ROOT . '/sql/schema.sql');

        $slugs = [];
        foreach (array_merge(
            glob(WK_ROOT . '/app/Services/*.php'),
            glob(WK_ROOT . '/app/Controllers/*/*.php')
        ) as $file) {
            preg_match_all("/sendFromTemplate\(\s*'([a-z0-9-]+)'/", (string) file_get_contents($file), $m);
            foreach ($m[1] as $slug) $slugs[$slug] = basename($file);
        }
        $this->assertNotEmpty($slugs, 'no template sends found — update this test');

        foreach ($slugs as $slug => $from) {
            $hasRow = str_contains($seeded, "('" . $slug . "'");
            $hasBuiltIn = str_contains($email, "\$slug === '" . $slug . "'");
            $this->assertTrue(
                $hasRow || $hasBuiltIn,
                "{$from} sends '{$slug}', but nothing seeds it and there is no built-in wording, "
                . 'so it goes out as an empty email titled "Notification"'
            );
        }
    }

    // ── Links ────────────────────────────────────────────────────────────

    public function testOnlyAWellFormedTokenIsEvenLookedUp(): void
    {
        $this->assertNull(CartRecoveryService::cartForToken(''));
        $this->assertNull(CartRecoveryService::cartForToken('short'));
        $this->assertNull(CartRecoveryService::cartForToken(str_repeat('z', 40)));
        $this->assertNull(CartRecoveryService::cartForToken("' OR 1=1 --"));
    }

    /** A link that restores a basket should not work forever. */
    public function testRecoveryLinksExpire(): void
    {
        $this->assertGreaterThan(0, CartRecoveryService::TOKEN_TTL_DAYS);
        $this->assertStringContainsString(
            'updated_at > DATE_SUB(NOW(), INTERVAL ? DAY)',
            $this->service(),
            'an old token must stop resolving'
        );
    }

    /** /cart returns JSON, so sending a shopper there shows them a raw blob. */
    public function testRecoveryLandsOnAPageAndNotTheJsonEndpoint(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CartRecoveryController.php');
        $this->assertSame(
            0,
            preg_match("/Response::redirect\(View::url\('cart'\)\)/", $controller),
            'redirecting to /cart shows the customer the JSON endpoint'
        );
        $this->assertStringContainsString("?cart=open", $controller);

        $js = (string) file_get_contents(WK_ROOT . '/assets/js/store.js');
        $this->assertStringContainsString('cart=open', $js, 'the drawer must open when the link lands');
    }

    // ── Consent ──────────────────────────────────────────────────────────

    public function testAnUnsubscribedAddressIsNeverEmailedAgain(): void
    {
        $src = $this->service();
        $send = substr($src, strpos($src, 'public static function sendReminder('));
        $send = substr($send, 0, strpos($send, 'public static function sweep('));

        $checkAt = strpos($send, 'self::isSuppressed(');
        $sendAt  = strpos($send, 'EmailService::sendFromTemplate(');
        $this->assertNotFalse($checkAt, 'suppression must be checked');
        $this->assertLessThan($sendAt, $checkAt, 'and checked before the mail goes out');
    }

    /** Without the table there is no record of consent, so say nothing. */
    public function testAMissingSuppressionTableMeansSilence(): void
    {
        $src = $this->service();
        $fn = substr($src, strpos($src, 'public static function isSuppressed('));
        $fn = substr($fn, 0, strpos($fn, 'public static function suppress('));
        $this->assertMatchesRegularExpression(
            '/catch \(\\\\Exception \$e\) \{.*?return true;/s',
            $fn,
            'failing open would email people who may have opted out'
        );
    }

    public function testUnsubscribeLinksAreSigned(): void
    {
        $a = CartRecoveryService::unsubscribeToken('someone@example.com');
        $b = CartRecoveryService::unsubscribeToken('other@example.com');

        $this->assertNotSame($a, $b, 'each address gets its own token');
        $this->assertTrue(CartRecoveryService::verifyUnsubscribe('someone@example.com', $a));
        $this->assertFalse(CartRecoveryService::verifyUnsubscribe('someone@example.com', $b),
            'one person must not be able to unsubscribe another');
        $this->assertFalse(CartRecoveryService::verifyUnsubscribe('someone@example.com', 'guess'));
        $this->assertStringContainsString('hash_equals', $this->service());
    }

    public function testEveryReminderCarriesAWayOut(): void
    {
        $migration = (string) file_get_contents(WK_ROOT . '/sql/migrations/20260826_v141_cart_recovery.sql');
        $this->assertStringContainsString('{{unsubscribe_url}}', $migration,
            'marketing email to non-customers needs an opt-out');
    }

    // ── Timing ───────────────────────────────────────────────────────────

    /**
     * Stamping NOW() when the sweep notices would push every reminder back by
     * however long it took to get there.
     */
    public function testAbandonmentIsDatedFromWhenTheShopperStopped(): void
    {
        $this->assertStringContainsString(
            "c.abandoned_at = c.updated_at",
            $this->service(),
            'the schedule counts from when the basket went quiet, not when we noticed'
        );
    }

    public function testTheScheduleIsSaneWhateverIsStored(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('sort($mins)', $src, 'reminders must go out in order');
        $this->assertStringContainsString('array_slice($mins, 0, 5)', $src, 'and be bounded');
    }

    public function testTheSweepThrottlesItself(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('last_cart_sweep', $src);
        $this->assertStringContainsString('SWEEP_INTERVAL', $src,
            'it runs off storefront traffic, so it must not run on every page view');
    }

    public function testRecoveryIsOffUntilAskedFor(): void
    {
        $this->assertStringContainsString(
            "Database::setting('cart_recovery', 'recovery_enabled', '0')",
            $this->service()
        );
        $sweep = substr($this->service(), strpos($this->service(), 'public static function sweep('));
        $this->assertStringContainsString('if (!self::enabled()) return $idle;', $sweep);
    }

    // ── Lead capture ─────────────────────────────────────────────────────

    public function testLeadCaptureIsOffUntilAskedFor(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Services/LeadService.php');
        $this->assertStringContainsString("Database::setting('leads', 'lead_capture_enabled', '0')", $src);
    }

    /** An expired code is worse than no code. */
    public function testAnUnusableCouponIsNeverOffered(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Services/LeadService.php');
        $fn = substr($src, strpos($src, 'public static function usableCoupon('));
        $fn = substr($fn, 0, strpos($fn, 'public static function capture('));

        $this->assertStringContainsString("!\$coupon['is_active']", $fn);
        $this->assertStringContainsString("\$coupon['starts_at']", $fn, 'a code that has not started yet');
        $this->assertStringContainsString("\$coupon['expires_at']", $fn, 'or has run out');
        $this->assertStringContainsString("\$coupon['usage_limit']", $fn, 'or been used up');
    }

    public function testTheCaptureEndpointIsGuarded(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/LeadController.php');
        $this->assertStringContainsString('Session::verifyCsrf', $controller);
        $this->assertStringContainsString("RateLimiter::attempt('lead_capture'", $controller);

        $routes = (string) file_get_contents(WK_ROOT . '/config/routes.php');
        $this->assertMatchesRegularExpression("/post\('\/lead',.*'csrf'/s", $routes);
    }

    /** The point of asking is to make the basket reachable. */
    public function testACapturedContactReachesTheCart(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Services/LeadService.php');
        $this->assertMatchesRegularExpression(
            '/UPDATE wk_carts SET email = COALESCE\(email, \?\)/',
            $src,
            'a lead that never reaches the cart cannot be emailed a reminder'
        );
    }

    /**
     * Switching tabs is not leaving. Treating it as a reason to stay quiet
     * suppressed the prompt for anyone who alt-tabbed, which is nearly
     * everyone, so it almost never appeared.
     */
    public function testLeavingTheTabDoesNotSilenceThePrompt(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/partials/lead-capture.php');
        $this->assertStringNotContainsString(
            'visibilityState',
            $view,
            'a tab switch must not count against the visitor'
        );
    }

    /** Both events, because browsers disagree about which fires on leaving. */
    public function testExitIsWatchedOnBothEvents(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/partials/lead-capture.php');
        $this->assertStringContainsString("addEventListener('mouseout', maybeExit)", $view);
        $this->assertStringContainsString("addEventListener('mouseleave', maybeExit)", $view);
        $this->assertStringContainsString('armed = true', $view, 'and not before the page has settled');
    }

    public function testTheExitPromptDoesNotNag(): void
    {
        $view = (string) file_get_contents(WK_ROOT . '/views/store/partials/lead-capture.php');
        $this->assertStringContainsString('wk_lead_seen', $view, 'it must remember it has been shown');
        $this->assertStringContainsString('QUIET_DAYS', $view);
        $this->assertStringContainsString(
            "/^(INPUT|TEXTAREA|SELECT)$/.test(active.tagName)",
            $view,
            'it must not interrupt someone typing'
        );
    }
}
