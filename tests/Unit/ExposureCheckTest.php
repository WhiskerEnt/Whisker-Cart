<?php
namespace Tests\Unit;

use App\Services\ExposureCheck;
use PHPUnit\Framework\TestCase;

/**
 * The check asks the live server for files it must refuse. Whether the
 * server answers is an integration matter; what is pinned here is that
 * the probes are files every install really has, that the shipped rules
 * refuse each of them, and that the wording tells a shopkeeper what to do.
 */
class ExposureCheckTest extends TestCase
{
    public function testEveryProbeIsAFileThatShipsWithWhisker(): void
    {
        foreach (ExposureCheck::PROBES as $path) {
            $this->assertFileExists(WK_ROOT . '/' . $path, "$path is probed but does not ship, so a 404 would hide a misconfiguration");
        }
    }

    public function testTheShippedRulesRefuseEveryProbe(): void
    {
        $htaccess = (string) file_get_contents(WK_ROOT . '/.htaccess');

        foreach (ExposureCheck::PROBES as $path) {
            $dir = strtok($path, '/');
            if ($path === '.htaccess') {
                $this->assertMatchesRegularExpression('/<FilesMatch "\^\\\\\.">\s*Require all denied/', $htaccess, 'hidden files are not denied');
                continue;
            }
            $this->assertMatchesRegularExpression(
                '/^RewriteRule \^' . preg_quote($dir, '/') . '\/ - \[F,L\]$/m',
                $htaccess,
                "$dir/ is probed but .htaccess does not refuse it"
            );
        }
    }

    public function testTheMessageNamesTheFilesAndTheFix(): void
    {
        $message = ExposureCheck::message(['exposed' => ['sql/schema.sql', '.htaccess'], 'reached' => true]);

        $this->assertStringContainsString('sql/schema.sql, .htaccess', $message);
        $this->assertStringContainsString('nginx', $message);
        $this->assertStringContainsString('AllowOverride', $message);
    }

    public function testNothingToSayWhenNothingIsExposed(): void
    {
        $this->assertNull(ExposureCheck::message(['exposed' => [], 'reached' => true]));
        $this->assertNull(ExposureCheck::message(['exposed' => [], 'reached' => false]));
    }

    public function testTheInstallerAndDashboardBothAsk(): void
    {
        $installer = (string) file_get_contents(WK_ROOT . '/install/index.php');
        $this->assertStringContainsString("require_once WK_ROOT . '/app/Services/ExposureCheck.php'", $installer, 'the installer has no autoloader and must load the file itself');
        $this->assertStringContainsString('ExposureCheck::run(', $installer);

        $dashboard = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/DashboardController.php');
        $this->assertStringContainsString('ExposureCheck::cachedMessage(WK_BASE_URL)', $dashboard);

        $view = (string) file_get_contents(WK_ROOT . '/views/admin/dashboard.php');
        $this->assertStringContainsString('$e($exposureWarning)', $view, 'the warning is escaped on the way out');
    }
}
