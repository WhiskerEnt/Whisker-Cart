<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The installer has the same "posted but never read" hazard as the settings
 * form: a field can be added to the wizard and silently ignored, so the
 * shopkeeper's answer never reaches the database. These read the real files
 * and keep the two sides honest.
 */
class InstallerFieldsTest extends TestCase
{
    private function view(): string
    {
        return (string) file_get_contents(WK_ROOT . '/views/install/layout.php');
    }

    private function handler(): string
    {
        return (string) file_get_contents(WK_ROOT . '/install/index.php');
    }

    public function testEveryFieldTheWizardPostsIsReadByTheHandler(): void
    {
        preg_match_all('/<(?:input|select|textarea)[^>]*\bname="([a-z0-9_]+)"/i', $this->view(), $m);
        $posted = array_unique($m[1]);
        $this->assertNotEmpty($posted, 'no form fields found — update this test\'s parser');

        $handler = $this->handler();
        $ignored = ['_install_csrf'];

        // Gateway credentials are collected by prefix rather than by name,
        // so the loop that walks them counts as reading every gw_ field.
        $prefixLoop = str_contains($handler, "str_starts_with(\$k, 'gw_')");

        foreach ($posted as $field) {
            if (in_array($field, $ignored, true)) continue;
            if ($prefixLoop && str_starts_with($field, 'gw_')) continue;
            $this->assertStringContainsString(
                "\$_POST['{$field}']",
                $handler,
                "the installer posts '{$field}' but install/index.php never reads it, so the answer is discarded"
            );
        }
    }

    public function testStoreSettingsAreWrittenAsUpserts(): void
    {
        // An UPDATE against a row the shipped seed does not contain changes
        // nothing and reports no error.
        $this->assertMatchesRegularExpression(
            '/INSERT INTO wk_settings .*ON DUPLICATE KEY UPDATE/s',
            $this->handler(),
            'installer store settings must upsert so a newly added setting cannot be dropped'
        );
    }

    public function testCookieConsentChoiceReachesTheDatabase(): void
    {
        $handler = $this->handler();
        $this->assertStringContainsString("\$_SESSION['wk_install']['cookie_consent']", $handler);
        $this->assertStringContainsString("['privacy','cookie_consent'", $handler);
    }

    public function testSchemaSeedsThePrivacyGroup(): void
    {
        $schema = (string) file_get_contents(WK_ROOT . '/sql/schema.sql');
        foreach (['cookie_consent', 'cookie_title', 'cookie_text', 'cookie_analytics', 'cookie_marketing', 'cookie_version'] as $key) {
            $this->assertStringContainsString(
                "('privacy', '{$key}'",
                $schema,
                "fresh installs need a seeded row for privacy.{$key}"
            );
        }
    }
}
