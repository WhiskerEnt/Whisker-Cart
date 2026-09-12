<?php
namespace Tests\Unit;

use App\Services\UpdateService;
use PHPUnit\Framework\TestCase;

/**
 * Composer is optional. A shop installed from the zip never sees it, so
 * what the manifest declares has to agree with what the installer checks
 * and what the shipped autoloader maps, or the two install paths drift.
 */
class ComposerManifestTest extends TestCase
{
    private function manifest(): array
    {
        $json = json_decode((string) file_get_contents(WK_ROOT . '/composer.json'), true);
        $this->assertIsArray($json, 'composer.json does not parse');
        return $json;
    }

    private function installer(): string
    {
        return (string) file_get_contents(WK_ROOT . '/install/index.php');
    }

    public function testManifestDescribesAnInstallableProject(): void
    {
        $m = $this->manifest();
        $this->assertSame('whiskerent/whisker-cart', $m['name']);
        $this->assertSame('project', $m['type']);
        $this->assertArrayHasKey('post-create-project-cmd', $m['scripts'], 'create-project should tell people to open /install/');
    }

    public function testNothingIsRequiredAtRuntimeBeyondPhpAndExtensions(): void
    {
        foreach (array_keys($this->manifest()['require']) as $package) {
            $this->assertMatchesRegularExpression(
                '/^(php|ext-[a-z_]+)$/',
                $package,
                "$package in require would make Composer mandatory"
            );
        }
    }

    public function testRequiredExtensionsAreTheOnesTheInstallerChecks(): void
    {
        preg_match_all("/extension_loaded\('([a-z_]+)'\)/", $this->installer(), $found);
        $checked = array_map(fn ($ext) => 'ext-' . $ext, $found[1]);
        sort($checked);

        $required = array_values(array_filter(
            array_keys($this->manifest()['require']),
            fn ($package) => str_starts_with($package, 'ext-')
        ));
        sort($required);

        $this->assertSame($checked, $required);
    }

    public function testPhpFloorIsTheInstallersFloor(): void
    {
        $this->assertStringContainsString("version_compare(PHP_VERSION, '8.0.0', '>=')", $this->installer());
        $this->assertSame('>=8.0', $this->manifest()['require']['php']);
    }

    /**
     * The shipped autoloader (registered by the test bootstrap) and the
     * PSR-4 map must resolve the same namespaces to the same directories.
     */
    public function testPsr4MapIsTheShippedAutoloaderMap(): void
    {
        $declared = $this->manifest()['autoload']['psr-4'];
        $samples  = ['App\\' => \App\Services\CurrencyService::class, 'Core\\' => \Core\View::class];

        $this->assertSame(array_keys($samples), array_keys($declared), 'namespace prefixes differ');

        foreach ($declared as $prefix => $dir) {
            $file = (new \ReflectionClass($samples[$prefix]))->getFileName();
            $this->assertStringStartsWith(
                str_replace('\\', '/', WK_ROOT . '/' . $dir),
                str_replace('\\', '/', (string) $file),
                "$prefix is not loaded from $dir by core/autoload.php"
            );
        }

        $autoloader = (string) file_get_contents(WK_ROOT . '/core/autoload.php');
        $this->assertSame(
            count($declared),
            substr_count($autoloader, "=> \$root . '/"),
            'core/autoload.php maps a namespace that composer.json does not'
        );
    }

    public function testFrontControllerLoadsComposerOnlyWhenPresentAndSecond(): void
    {
        $index = (string) file_get_contents(WK_ROOT . '/index.php');
        $own = strpos($index, "require_once WK_ROOT . '/core/autoload.php';");
        $hook = strpos($index, "if (is_file(WK_ROOT . '/vendor/autoload.php'))");

        $this->assertNotFalse($own);
        $this->assertNotFalse($hook, 'index.php never loads vendor/autoload.php');
        $this->assertGreaterThan($own, $hook, "Whisker's own autoloader is registered before Composer's");
    }

    public function testDevelopmentFilesStayOutOfComposerPackages(): void
    {
        $attributes = (string) file_get_contents(WK_ROOT . '/.gitattributes');
        foreach (['/tests', '/.github', '/phpunit.xml.dist'] as $path) {
            $this->assertMatchesRegularExpression(
                '/^' . preg_quote($path, '/') . '\s+export-ignore$/m',
                $attributes,
                "$path is not export-ignored"
            );
        }
    }

    public function testPreUpdateBackupLeavesLibrariesAlone(): void
    {
        $property = new \ReflectionProperty(UpdateService::class, 'backupSkipDirs');
        $property->setAccessible(true);
        $skipped = $property->getValue();

        $this->assertContains('vendor', $skipped);
        $this->assertContains('node_modules', $skipped);
    }

    public function testLibrariesAreNotReachableOverTheWeb(): void
    {
        $htaccess = (string) file_get_contents(WK_ROOT . '/.htaccess');
        $this->assertMatchesRegularExpression('/^RewriteRule \^vendor\/ - \[F,L\]$/m', $htaccess);
        $this->assertMatchesRegularExpression('/FilesMatch "[^"]*\b(json|lock)\b[^"]*"/', $htaccess, 'composer.json and composer.lock must be denied');
    }
}
