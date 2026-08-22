<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Contract between the email senders, the seeded templates and the variable
 * hints shown in the admin editor. A mismatch here reaches customers as an
 * empty message or a raw {{placeholder}}, so it is checked from the source.
 */
class EmailTemplateTest extends TestCase
{
    private function seed(): string
    {
        return (string) file_get_contents(WK_ROOT . '/sql/migrations/20260818_v140_email_templates.sql');
    }

    private function service(): string
    {
        return (string) file_get_contents(WK_ROOT . '/app/Services/EmailService.php');
    }

    /** @return string[] */
    private function seededSlugs(): array
    {
        preg_match_all("/^\('([a-z-]+)',/m", $this->seed(), $m);
        return $m[1];
    }

    public function testEverySlugTheAppSendsHasASeededTemplate(): void
    {
        preg_match_all("/sendFromTemplate\('([a-z-]+)'/", $this->service(), $m);
        $sent = array_unique($m[1]);
        $this->assertNotEmpty($sent, 'no template sends found — update this test\'s parser');

        $seeded = $this->seededSlugs();
        foreach ($sent as $slug) {
            $this->assertContains(
                $slug,
                $seeded,
                "EmailService sends '{$slug}' but no template is seeded for it, so it would go out with the fallback wording"
            );
        }
    }

    public function testSeedCoversTheTemplatesAStoreExpects(): void
    {
        $seeded = $this->seededSlugs();
        foreach ([
            'welcome', 'order-confirmation', 'order-pending', 'order-cancelled',
            'payment-receipt', 'refund-notification', 'shipping-notification',
        ] as $slug) {
            $this->assertContains($slug, $seeded, "missing seeded template: {$slug}");
        }
    }

    /**
     * A subject is a plain-text mail header. An entity written there is shown
     * to the customer character by character.
     */
    public function testSeededSubjectsContainNoHtmlEntities(): void
    {
        preg_match_all("/^\('[a-z-]+', '[^']*', '([^']*)',/m", $this->seed(), $m);
        $this->assertNotEmpty($m[1], 'no subjects parsed');
        foreach ($m[1] as $subject) {
            $this->assertSame(
                0,
                preg_match('/&[a-z]+;|&#\d+;/i', $subject),
                "subject contains an HTML entity and would read literally: {$subject}"
            );
        }
    }

    public function testSubjectsAreDecodedAndEncodedForTheHeader(): void
    {
        $src = $this->service();
        $this->assertStringContainsString('private static function headerSubject', $src);
        $this->assertStringContainsString('html_entity_decode', $src);
        $this->assertStringContainsString('=?UTF-8?B?', $src);

        // Both senders must route through it, or one path leaks raw entities.
        $this->assertSame(
            2,
            preg_match_all('/self::headerSubject\(\$subject\)/', $src),
            'every sender must prepare the subject the same way'
        );
    }

    public function testSeededBodiesOnlyUseDeclaredVariables(): void
    {
        $controller = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/EmailTemplateController.php');
        $seed = $this->seed();

        // Pair each seeded row with the placeholders in its body.
        preg_match_all("/^\('([a-z-]+)', '[^']*', '[^']*', '(.*)', 1\)/m", $seed, $rows, PREG_SET_ORDER);
        $this->assertNotEmpty($rows, 'no template rows parsed');

        foreach ($rows as $row) {
            [$_, $slug, $body] = $row;
            preg_match_all('/\{\{([a-z_]+)\}\}/', $body, $used);
            $declared = $this->declaredFor($controller, $slug);

            foreach (array_unique($used[1]) as $var) {
                $this->assertContains(
                    '{{' . $var . '}}',
                    $declared,
                    "template '{$slug}' uses {{{$var}}} but the admin editor does not list it, "
                    . 'so a shopkeeper editing this template cannot know it exists'
                );
            }
        }
    }

    /** Variable names the admin editor offers for a slug, including the common set. */
    private function declaredFor(string $controller, string $slug): array
    {
        $common = ['{{store_name}}', '{{store_url}}', '{{logo}}', '{{customer_name}}',
                   '{{customer_email}}', '{{customer_phone}}', '{{currency_symbol}}'];

        if (!preg_match("/'" . preg_quote($slug, '/') . "' => array_merge\(\\\$common, \[(.*?)\]\),/s", $controller, $m)) {
            return $common;
        }
        preg_match_all("/'(\{\{[a-z_]+\}\})'/", $m[1], $keys);
        return array_merge($common, $keys[1]);
    }
}
