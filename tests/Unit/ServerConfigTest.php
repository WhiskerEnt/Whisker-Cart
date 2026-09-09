<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Whisker ships its own server configuration, and the installer writes a copy
 * of it when the file is missing. Anything true of one has to be true of the
 * other, or a shop gets a quieter, slower site depending on how it was set up.
 */
class ServerConfigTest extends TestCase
{
    private function root(): string
    {
        return (string) file_get_contents(WK_ROOT . '/.htaccess');
    }

    private function assets(): string
    {
        return (string) file_get_contents(WK_ROOT . '/assets/.htaccess');
    }

    /** The copy the installer writes, pulled out of the installer itself. */
    private function generated(): string
    {
        $src = (string) file_get_contents(WK_ROOT . '/install/index.php');
        $found = preg_match("/<<<'WK_HTACCESS'\n(.*?)\nWK_HTACCESS;/s", $src, $m);
        $this->assertSame(1, $found, 'the installer no longer generates an .htaccess');
        return $m[1];
    }

    /**
     * The installer writes the shipped file verbatim.
     *
     * Kept as one comparison rather than a list of things to check for: a
     * hand-written list only catches the rules somebody remembered to add to
     * it, which is how the generated copy came to be missing the content
     * security policy, HSTS and half the upload rules in the first place.
     */
    public function testTheInstallerWritesExactlyWhatWhiskerShips(): void
    {
        $this->assertSame(
            rtrim($this->root(), "\n"),
            $this->generated(),
            'a shop installed onto a server with no .htaccess gets different rules from one that had it'
        );
    }

    // ── Compression ──────────────────────────────────────────────────────

    public function testTheShippedConfigCompressesText(): void
    {
        $htaccess = $this->root();
        $this->assertStringContainsString('mod_deflate.c', $htaccess);
        foreach (['text/html', 'text/css', 'application/javascript', 'application/json', 'image/svg+xml'] as $type) {
            $this->assertStringContainsString($type, $htaccess, "{$type} is sent uncompressed");
        }
    }

    /** Nothing a running shop relies on may be reachable over HTTP. */
    public function testTheDirectoriesThatMustNeverBeServedAreBlocked(): void
    {
        $htaccess = $this->root();
        foreach (['app', 'core', 'config', 'sql', 'views', 'tests', 'vendor', 'node_modules'] as $dir) {
            $this->assertMatchesRegularExpression(
                '/^RewriteRule \^' . preg_quote($dir, '/') . '\/ - \[F,L\]$/m',
                $htaccess,
                "{$dir}/ is served over HTTP"
            );
        }
        $this->assertStringContainsString('RewriteRule ^\.git', $htaccess,
            'a repository left in place would expose the whole history');
    }

    /**
     * The hidden-file rule matches on a file's own name, so it never sees
     * .git/config — that file is called "config".
     */
    public function testTheHiddenFileRuleIsNotRelivedOnForDotDirectories(): void
    {
        $htaccess = $this->root();
        $this->assertStringContainsString('<FilesMatch "^\.">', $htaccess);
        $this->assertMatchesRegularExpression('/RewriteRule \^\\\\\.git/', $htaccess,
            'dot-directories need a rule of their own');
    }

    /** An upload is data. It must never be run, whatever it is named. */
    public function testUploadsCannotBeExecuted(): void
    {
        $htaccess = $this->root();
        preg_match('/storage\/uploads\/[^\n]*/', $htaccess, $m);
        $this->assertNotEmpty($m, 'nothing stops an uploaded file being executed');

        foreach (['php', 'phtml', 'phar', 'shtml', 'php7'] as $ext) {
            $this->assertStringContainsString($ext, $m[0], "an uploaded .{$ext} would run");
        }
    }

    /**
     * A cache shared between people must key on the encoding, or somebody
     * gets handed a gzipped page their browser did not ask for.
     */
    public function testCompressedResponsesVaryOnTheEncoding(): void
    {
        $this->assertMatchesRegularExpression(
            '/Header append Vary Accept-Encoding/',
            $this->root(),
            'the config compresses without telling caches it did'
        );
    }

    /**
     * The policy names the payment gateways the checkout loads. Narrowing it
     * without them would leave shoppers on a blank payment step.
     */
    public function testTheContentPolicyStillAdmitsThePaymentGateways(): void
    {
        preg_match('/Content-Security-Policy "([^"]+)"/', $this->root(), $m);
        $this->assertNotEmpty($m, 'there is no content security policy');
        $csp = $m[1];

        foreach (['js.stripe.com', 'checkout.razorpay.com', 'api.stripe.com', 'api.razorpay.com'] as $host) {
            $this->assertStringContainsString($host, $csp, "the policy would block {$host}");
        }

        foreach (["object-src 'none'", "base-uri 'self'", "frame-ancestors 'self'"] as $directive) {
            $this->assertStringContainsString($directive, $csp, "the policy is missing {$directive}");
        }

        // A gateway posts the shopper back to the shop, so this one cannot be pinned.
        $this->assertStringNotContainsString('form-action', $csp,
            'pinning form-action breaks the return leg of a card payment');
    }

    /** Compressing an already-compressed format costs time and saves nothing. */
    public function testAlreadyCompressedFormatsAreLeftAlone(): void
    {
        $htaccess = $this->root();
        $filters = [];
        foreach (explode("\n", $htaccess) as $line) {
            if (str_contains($line, 'AddOutputFilterByType')) $filters[] = $line;
        }
        $this->assertNotEmpty($filters);

        $joined = implode(' ', $filters);
        foreach (['image/jpeg', 'image/png', 'image/webp', 'font/woff2', 'video/'] as $alreadySmall) {
            $this->assertStringNotContainsString($alreadySmall, $joined,
                "{$alreadySmall} is already compressed and should not be run through it again");
        }
    }

    // ── Caching ──────────────────────────────────────────────────────────

    /**
     * Caching Whisker's own assets for a year is only safe because every link
     * to them carries the file's modification time.
     */
    public function testAssetsAreCachedHardAndTheUrlsCarryAVersion(): void
    {
        $this->assertStringContainsString('immutable', $this->assets());
        $this->assertStringContainsString('max-age=31536000', $this->assets());

        $view = (string) file_get_contents(WK_ROOT . '/core/View.php');
        $asset = substr($view, strpos($view, 'public static function asset('));
        $this->assertStringContainsString('filemtime(', $asset,
            'assets are cached for a year but their URLs never change, so an update would never arrive');
        $this->assertStringContainsString("'?v='", $asset);
    }

    /**
     * An uploaded image keeps its filename when it is replaced, so it cannot
     * be cached anywhere near as hard as a versioned asset.
     */
    public function testUploadsAreCachedFarMoreCautiouslyThanAssets(): void
    {
        $root = $this->root();
        $this->assertMatchesRegularExpression(
            '/ExpiresByType image\/jpeg "access plus 7 days"/',
            $root,
            'a replaced product photo would be stuck in caches'
        );
        $this->assertStringNotContainsString('ExpiresDefault "access plus 1 year"', $root,
            'the year-long rule belongs to assets/ alone, not the whole site');
    }

    /** Nothing under assets/ is ever executed, whatever it is named. */
    public function testAssetsCannotExecutePhp(): void
    {
        $this->assertStringContainsString('Require all denied', $this->assets());
        $this->assertMatchesRegularExpression('/\\\\.\(php\|phtml/', $this->assets());
    }

    // ── Documentation ────────────────────────────────────────────────────

    /**
     * The check in the guide has to be one that works. `curl -I` sends a HEAD
     * request, which has no body to compress, so Apache leaves the header off
     * and a working server looks broken.
     */
    public function testTheDocumentedCheckDoesNotUseHead(): void
    {
        $install = (string) file_get_contents(WK_ROOT . '/INSTALL.md');
        $section = substr($install, strpos($install, '## Compression & Caching'));
        $section = substr($section, 0, strpos($section, '## Subfolder Installation'));

        $this->assertStringContainsString('content-encoding', strtolower($section));
        $this->assertMatchesRegularExpression('/curl [^\n]*-D -/', $section,
            'the documented check must read headers from a real GET');
        $this->assertDoesNotMatchRegularExpression(
            '/curl -s -I -H "Accept-Encoding/',
            $section,
            'a HEAD request reports no encoding even when compression is working'
        );
    }

    /** The people who cannot use .htaccess are the ones who need the guide. */
    public function testTheGuideCoversServersThatIgnoreHtaccess(): void
    {
        $install = (string) file_get_contents(WK_ROOT . '/INSTALL.md');
        $section = substr($install, strpos($install, '## Compression & Caching'));
        $section = substr($section, 0, strpos($section, '## Subfolder Installation'));

        foreach (['nginx', 'AllowOverride', 'LiteSpeed', 'cPanel'] as $case) {
            $this->assertStringContainsString($case, $section, "the guide never mentions {$case}");
        }
        $this->assertStringContainsString('gzip_types', $section, 'the nginx block is not usable as written');
    }
}
