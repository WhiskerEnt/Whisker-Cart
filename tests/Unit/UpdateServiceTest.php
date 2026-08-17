<?php
namespace Tests\Unit;

use App\Services\UpdateService;
use PHPUnit\Framework\TestCase;

/**
 * Guards the backup-filename contract between createBackup() and rollback()/
 * getBackups(). A hardening pass once added a random suffix to the generated
 * names without updating the parsing regexes — every Restore click then
 * failed with "Invalid backup filename" and the dashboard listed backups as
 * version "unknown".
 *
 * rollback() checks the filename BEFORE checking the file exists, so the two
 * error messages distinguish "regex rejected" from "accepted but missing" —
 * letting us test the contract without creating real backup files.
 */
class UpdateServiceTest extends TestCase
{
    public function testRollbackAcceptsCurrentBackupFilenameFormat(): void
    {
        // Format produced by createBackup() today: version + timestamp + 8-hex suffix.
        $result = UpdateService::rollback('backup_v1.3.2_20260817_120000_a1b2c3d4.zip');
        $this->assertSame('Backup file not found.', $result['message'], 'filename with random suffix must pass validation');
    }

    public function testRollbackStillAcceptsLegacyBackupFilenames(): void
    {
        // Backups created before the random suffix existed.
        $result = UpdateService::rollback('backup_v1.2.1_20260511_090000.zip');
        $this->assertSame('Backup file not found.', $result['message'], 'legacy filename must pass validation');
    }

    /** @dataProvider badFilenameProvider */
    public function testRollbackRejectsMalformedOrHostileFilenames(string $filename): void
    {
        $result = UpdateService::rollback($filename);
        $this->assertSame('Invalid backup filename.', $result['message'], "'{$filename}' must be rejected");
    }

    public static function badFilenameProvider(): array
    {
        return [
            'traversal'          => ['../../etc/passwd'],
            'wrong extension'    => ['backup_v1.3.2_20260817_120000_a1b2c3d4.php'],
            'non-hex suffix'     => ['backup_v1.3.2_20260817_120000_zzzzzzzz.zip'],
            'short suffix'       => ['backup_v1.3.2_20260817_120000_a1b2.zip'],
            'arbitrary zip'      => ['evil.zip'],
            'double extension'   => ['backup_v1.3.2_20260817_120000_a1b2c3d4.zip.php'],
        ];
    }
}
