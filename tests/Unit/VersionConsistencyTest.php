<?php
namespace Tests\Unit;

use App\Services\MigrationService;
use PHPUnit\Framework\TestCase;

/**
 * Guards the release-discipline class: the 1.3.1 hotfix shipped without
 * bumping app/version.php, so stores reported the wrong version and the
 * updater compared against a stale number. Keep every version surface in
 * lockstep.
 */
class VersionConsistencyTest extends TestCase
{
    private function version(): string
    {
        return (string) require WK_ROOT . '/app/version.php';
    }

    public function testVersionFileIsSemver(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $this->version());
    }

    public function testReadmeBadgeAndFooterMatchVersionFile(): void
    {
        $readme = (string) file_get_contents(WK_ROOT . '/README.md');
        $v = $this->version();

        $this->assertSame(1, preg_match('/badge\/version-(\d+\.\d+\.\d+)-/', $readme, $m), 'no version badge found in README');
        $this->assertSame($v, $m[1], "README badge says {$m[1]} but app/version.php says {$v}");

        preg_match_all('/Whisker v(\d+\.\d+\.\d+)/', $readme, $all);
        foreach ($all[1] as $mention) {
            $this->assertSame($v, $mention, "README mentions Whisker v{$mention} but app/version.php says {$v}");
        }
    }

    public function testMigrationFilenamesAreWellFormed(): void
    {
        foreach (glob(WK_ROOT . '/sql/migrations/*.sql') as $file) {
            $this->assertMatchesRegularExpression(
                '/^\d{8}_v\d+_[a-z0-9_]+\.sql$/',
                basename($file),
                'migration filename must be YYYYMMDD_vNNN_description.sql (sorted order = execution order)'
            );
        }
    }

    public function testRepairRerunListReferencesRealMigrationFiles(): void
    {
        $const = new \ReflectionClassConstant(MigrationService::class, 'REPAIR_RERUN');
        foreach ($const->getValue() as $filename) {
            $this->assertFileExists(
                WK_ROOT . '/sql/migrations/' . $filename,
                "MigrationService::REPAIR_RERUN references {$filename} which does not exist"
            );
        }
    }
}
