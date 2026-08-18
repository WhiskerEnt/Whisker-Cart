<?php
namespace Tests\Unit;

use Core\View;
use PHPUnit\Framework\TestCase;

/**
 * Guards for defects found in the 1.4.0 audit. Each test pins the behaviour a
 * future change could plausibly undo.
 */
class RegressionGuardsTest extends TestCase
{
    // ── Slug generation ──────────────────────────────────────────────

    /** @dataProvider slugProvider */
    public function testSlugKeepsEveryWord(string $input, string $expected): void
    {
        $this->assertSame($expected, View::slug($input));
    }

    public static function slugProvider(): array
    {
        return [
            // Capitals must survive as lowercase, not be stripped as invalid.
            'title case'      => ['About Us', 'about-us'],
            'all caps'        => ['FAQ PAGE', 'faq-page'],
            'mixed'           => ['Audit Test Template', 'audit-test-template'],
            'leading capital' => ['Terms', 'terms'],
            'digits kept'     => ['Top 10 Picks', 'top-10-picks'],
            'punctuation'     => ['Refund & Exchange Policy!', 'refund-exchange-policy'],
            'already a slug'  => ['privacy-policy', 'privacy-policy'],
            'trims dashes'    => ['  Hello  World  ', 'hello-world'],
            'unusable input'  => ['!!!', ''],
        ];
    }

    public function testSlugNeverStartsOrEndsWithDash(): void
    {
        foreach (['About Us', '  Spaced  ', '???Weird???'] as $in) {
            $slug = View::slug($in);
            if ($slug === '') continue;
            $this->assertStringStartsNotWith('-', $slug);
            $this->assertStringEndsNotWith('-', $slug);
        }
    }

    // ── Editor form binding ──────────────────────────────────────────

    /**
     * Admin views must not bind behaviour to a document-wide form lookup: the
     * layout renders its own form before the page content, so the first match
     * is not the page's form. Scope to the field's own form instead.
     */
    public function testAdminViewsDoNotUseDocumentWideFormSelector(): void
    {
        $offenders = [];
        foreach ($this->phpFilesIn(WK_ROOT . '/views') as $file) {
            $src = (string) file_get_contents($file);
            if (preg_match('/document\.querySelector\(\s*[\'"]form[\'"]\s*\)/', $src)) {
                $offenders[] = str_replace(WK_ROOT, '', $file);
            }
        }
        $this->assertSame(
            [],
            $offenders,
            "Use hidden.closest('form') (or an id) instead of document.querySelector('form') in: " . implode(', ', $offenders)
        );
    }

    // ── Order items schema ───────────────────────────────────────────

    /**
     * Checkout writes variant_combo_id/variant_label for variant lines, and
     * the cancel + expiry sweeps read variant_combo_id. schema.sql must define
     * them or variant orders cannot be placed at all.
     */
    public function testSchemaDefinesOrderItemVariantColumns(): void
    {
        $schema = (string) file_get_contents(WK_ROOT . '/sql/schema.sql');
        $this->assertSame(
            1,
            preg_match('/CREATE TABLE IF NOT EXISTS wk_order_items\s*\((.*?)\)\s*ENGINE/s', $schema, $m),
            'could not locate the wk_order_items definition'
        );
        $this->assertStringContainsString('variant_combo_id', $m[1]);
        $this->assertStringContainsString('variant_label', $m[1]);
    }

    /**
     * Every column the codebase inserts into wk_order_items must exist in
     * schema.sql.
     */
    public function testOrderItemInsertColumnsAllExistInSchema(): void
    {
        $schema = (string) file_get_contents(WK_ROOT . '/sql/schema.sql');
        preg_match('/CREATE TABLE IF NOT EXISTS wk_order_items\s*\((.*?)\)\s*ENGINE/s', $schema, $m);
        $definition = $m[1] ?? '';

        $checkout = (string) file_get_contents(WK_ROOT . '/app/Controllers/Store/CheckoutController.php');
        $this->assertSame(
            1,
            preg_match('/\$orderItemData\s*=\s*\[(.*?)\];/s', $checkout, $om),
            'could not locate $orderItemData'
        );
        preg_match_all("/'([a-z_]+)'\s*=>/", $om[1], $keys);

        foreach (array_unique($keys[1]) as $column) {
            $this->assertStringContainsString(
                $column,
                $definition,
                "CheckoutController inserts wk_order_items.{$column} but schema.sql does not define it"
            );
        }
    }

    // ── Secrets ──────────────────────────────────────────────────────

    /** Stored credentials must not be rendered back into admin HTML. */
    public function testAdminViewsDoNotEchoStoredSecrets(): void
    {
        $settings = (string) file_get_contents(WK_ROOT . '/views/admin/settings.php');
        $this->assertSame(
            1,
            preg_match('/name="email_smtp_pass"[^>]*/', $settings, $m),
            'SMTP password field not found'
        );
        $this->assertStringNotContainsString(
            "\$v('email','smtp_pass')",
            $m[0],
            'SMTP password must render empty, not echo the stored value'
        );

        $gateways = (string) file_get_contents(WK_ROOT . '/views/admin/gateways/index.php');
        $this->assertStringContainsString(
            '$isSecret',
            $gateways,
            'gateway config fields must distinguish secrets and render them empty'
        );
    }

    /** @return string[] */
    private function phpFilesIn(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'php') $out[] = $f->getPathname();
        }
        return $out;
    }
}
