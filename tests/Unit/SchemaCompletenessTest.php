<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * A column has to arrive two ways: by migration for shops that already exist,
 * and in schema.sql for shops that do not yet.
 *
 * Migrations only run when the version changes, and only from the admin
 * dashboard, so a fresh install that has not been signed into is running on
 * whatever schema.sql created. A column missing from there is a column the
 * storefront has to work without.
 */
class SchemaCompletenessTest extends TestCase
{
    private function schema(): string
    {
        return (string) file_get_contents(WK_ROOT . '/sql/schema.sql');
    }

    /** @return array<int,array{table:string,column:string,file:string}> */
    private function columnsAddedByMigrations(): array
    {
        $found = [];
        foreach (glob(WK_ROOT . '/sql/migrations/*.sql') as $file) {
            $sql = (string) file_get_contents($file);

            // One ALTER can carry several ADD COLUMN clauses separated by
            // commas, so each statement is taken whole and then read through.
            preg_match_all('/ALTER\s+TABLE\s+`?(\w+)`?(.*?);/is', $sql, $statements, PREG_SET_ORDER);

            foreach ($statements as $statement) {
                $table = $statement[1];
                preg_match_all('/ADD\s+(?:COLUMN\s+)?`?(\w+)`?/i', $statement[2], $adds);

                foreach ($adds[1] as $column) {
                    // ADD UNIQUE KEY / ADD INDEX are not columns.
                    if (in_array(strtoupper($column), ['UNIQUE', 'INDEX', 'KEY', 'CONSTRAINT', 'PRIMARY', 'FOREIGN', 'FULLTEXT'], true)) {
                        continue;
                    }
                    $found[] = ['table' => $table, 'column' => $column, 'file' => basename($file)];
                }
            }
        }
        return $found;
    }

    public function testEveryColumnAMigrationAddsAlsoExistsInTheFreshSchema(): void
    {
        $schema = $this->schema();
        $missing = [];

        foreach ($this->columnsAddedByMigrations() as $col) {
            // The table has to be in schema.sql at all before its columns can be.
            $start = strpos($schema, "CREATE TABLE IF NOT EXISTS {$col['table']} ");
            if ($start === false) continue;

            $end = strpos($schema, 'ENGINE=', $start);
            $body = substr($schema, $start, $end - $start);

            if (!preg_match('/^\s*`?' . preg_quote($col['column'], '/') . '`?\s/mi', $body)) {
                $missing[] = "{$col['table']}.{$col['column']} (added by {$col['file']})";
            }
        }

        $this->assertSame([], $missing,
            "a fresh install would not have these columns:\n  " . implode("\n  ", $missing));
    }

    /** The sweep is worthless if it is not actually finding the migrations. */
    public function testTheSweepIsLookingAtSomething(): void
    {
        $this->assertGreaterThan(5, count($this->columnsAddedByMigrations()),
            'no ALTER TABLE ... ADD COLUMN found, so this test proves nothing');
    }

    /**
     * A unique index the code relies on has to be there on a fresh install
     * too, or the thing it guarantees is not guaranteed.
     */
    public function testTheIdempotencyIndexIsInTheFreshSchema(): void
    {
        $schema = $this->schema();
        $start = strpos($schema, 'CREATE TABLE IF NOT EXISTS wk_orders ');
        $body  = substr($schema, $start, strpos($schema, 'ENGINE=', $start) - $start);

        $this->assertMatchesRegularExpression('/UNIQUE KEY \w+ \(idempotency_key\)/i', $body,
            'without the index two racing checkouts both insert, which is what it exists to stop');
    }
}
