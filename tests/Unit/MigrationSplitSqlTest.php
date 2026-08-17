<?php
namespace Tests\Unit;

use App\Services\MigrationService;
use PHPUnit\Framework\TestCase;

class MigrationSplitSqlTest extends TestCase
{
    private function split(string $sql): array
    {
        $m = new \ReflectionMethod(MigrationService::class, 'splitSql');
        $m->setAccessible(true);
        return $m->invoke(null, $sql);
    }

    private function isBenign(\Exception $e): bool
    {
        $m = new \ReflectionMethod(MigrationService::class, 'isBenignDuplicateError');
        $m->setAccessible(true);
        return $m->invoke(null, $e);
    }

    public function testLeadingCommentBlockDoesNotSwallowFirstStatement(): void
    {
        // THE regression: pre-1.3.2, the header comment glued itself to the
        // first statement, the runner skipped it as "a comment", and the
        // migration was recorded as executed without ever running.
        $sql = "-- v1.x — header\n-- more header\nALTER TABLE t ADD COLUMN a INT;";
        $stmts = $this->split($sql);
        $this->assertCount(1, $stmts);
        $this->assertStringStartsWith('ALTER TABLE', $stmts[0]);
    }

    public function testApostrophesInsideCommentsDoNotBreakStringTracking(): void
    {
        // Comments like "don't" or "'payment_failed'" used to flip the
        // in-string state and merge later statements.
        $sql = "-- don't break on this, or on 'quoted' words\n"
             . "CREATE TABLE a (id INT);\n"
             . "-- another comment that isn't balanced'\n"
             . "CREATE TABLE b (id INT);";
        $stmts = $this->split($sql);
        $this->assertCount(2, $stmts);
        $this->assertStringStartsWith('CREATE TABLE a', $stmts[0]);
        $this->assertStringStartsWith('CREATE TABLE b', $stmts[1]);
    }

    public function testSemicolonInsideStringLiteralIsNotASplitPoint(): void
    {
        $stmts = $this->split("INSERT INTO t VALUES ('a;b');INSERT INTO t VALUES ('c');");
        $this->assertCount(2, $stmts);
        $this->assertSame("INSERT INTO t VALUES ('a;b')", $stmts[0]);
    }

    public function testHashCommentsAreStripped(): void
    {
        $stmts = $this->split("# hash comment\nSELECT 1;");
        $this->assertCount(1, $stmts);
        $this->assertSame('SELECT 1', $stmts[0]);
    }

    public function testDashesInsideStringsSurvive(): void
    {
        $stmts = $this->split("INSERT INTO t VALUES ('a--b');");
        $this->assertSame("INSERT INTO t VALUES ('a--b')", $stmts[0]);
    }

    public function testEveryShippedMigrationParsesToRealStatements(): void
    {
        $files = glob(WK_ROOT . '/sql/migrations/*.sql');
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $stmts = $this->split((string)file_get_contents($file));
            $this->assertNotEmpty($stmts, basename($file) . ' produced no statements');
            foreach ($stmts as $i => $stmt) {
                $this->assertStringNotContainsString('--', substr($stmt, 0, 2), basename($file) . " stmt {$i} still starts with a comment");
                $this->assertMatchesRegularExpression(
                    '/^(CREATE|ALTER|INSERT|UPDATE|DELETE|DROP|SET)\b/i',
                    $stmt,
                    basename($file) . " stmt {$i} does not start with a SQL keyword: " . substr($stmt, 0, 60)
                );
            }
        }
    }

    public function testMariaDbOnlySyntaxIsBanishedFromMigrations(): void
    {
        // ADD COLUMN IF NOT EXISTS is MariaDB-only; on real MySQL it's a
        // syntax error and the migration wedges. The runner's benign-duplicate
        // tolerance replaces the need for it. Checked against the PARSED
        // statements, not the raw file — comments may legitimately mention
        // the phrase when explaining why it's banned.
        foreach (glob(WK_ROOT . '/sql/migrations/*.sql') as $file) {
            foreach ($this->split((string)file_get_contents($file)) as $stmt) {
                $this->assertDoesNotMatchRegularExpression(
                    '/ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS/i',
                    $stmt,
                    basename($file) . ' uses MariaDB-only ADD COLUMN IF NOT EXISTS'
                );
            }
        }
    }

    public function testBenignDuplicateErrorClassification(): void
    {
        $dup = new \PDOException('SQLSTATE[42S21]: Column already exists: 1060 Duplicate column name \'email\'');
        $dup->errorInfo = ['42S21', 1060, "Duplicate column name 'email'"];
        $this->assertTrue($this->isBenign($dup));

        $tableExists = new \Exception("Table 'wk_pickup_locations' already exists");
        $this->assertTrue($this->isBenign($tableExists));

        $syntax = new \PDOException('SQLSTATE[42000]: Syntax error or access violation: 1064 You have an error in your SQL syntax');
        $syntax->errorInfo = ['42000', 1064, 'syntax error'];
        $this->assertFalse($this->isBenign($syntax));
    }
}
