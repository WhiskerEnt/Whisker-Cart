<?php
namespace Tests\Unit;

use App\Services\ProductFaqService as Faq;
use PHPUnit\Framework\TestCase;

/**
 * The FAQ is typed in by hand on one product and imported in bulk on a
 * thousand, so both shapes have to read back the same. The import shape is
 * the fragile one: it lives in a single spreadsheet cell.
 */
class ProductFaqTest extends TestCase
{
    public function testTheImportShapeIsParsed(): void
    {
        $items = Faq::parse('Does it shrink? :: Not if you wash cold. || True to size? :: Yes.');

        $this->assertCount(2, $items);
        $this->assertSame('Does it shrink?', $items[0]['q']);
        $this->assertSame('Not if you wash cold.', $items[0]['a']);
        $this->assertSame('True to size?', $items[1]['q']);
    }

    /** Spreadsheets add stray spaces; they should not become part of the text. */
    public function testSpacingAroundSeparatorsIsIgnored(): void
    {
        $tight = Faq::parse('Q1::A1||Q2::A2');
        $loose = Faq::parse('  Q1  ::  A1   ||   Q2  ::  A2  ');
        $this->assertSame($tight, $loose);
    }

    /** A half-written pair is dropped, not imported with an empty side. */
    public function testIncompletePairsAreDropped(): void
    {
        $this->assertSame([], Faq::parse('Just a question with no answer'));
        $this->assertSame([], Faq::parse('Question ::'));
        $this->assertSame([], Faq::parse(':: Answer with no question'));

        $mixed = Faq::parse('Good question :: Good answer || broken pair here');
        $this->assertCount(1, $mixed, 'the good pair survives, the broken one does not');
    }

    /** An answer may legitimately contain a colon. */
    public function testAnAnswerMayContainAColon(): void
    {
        $items = Faq::parse('When does it ship? :: Same day: orders before 2pm go out that afternoon.');
        $this->assertCount(1, $items);
        $this->assertSame('Same day: orders before 2pm go out that afternoon.', $items[0]['a']);
    }

    public function testStoredJsonReadsBackTheSame(): void
    {
        $original = Faq::parse('Q1 :: A1 || Q2 :: A2');
        $stored = Faq::encode($original);

        $this->assertJson((string) $stored);
        $this->assertSame($original, Faq::parse($stored), 'a round trip must not change anything');
    }

    /** An empty list stores nothing rather than the string "[]". */
    public function testNothingStoresAsNull(): void
    {
        $this->assertNull(Faq::encode([]));
        $this->assertNull(Faq::encode([['q' => '', 'a' => '']]));
        $this->assertSame([], Faq::parse(null));
        $this->assertSame([], Faq::parse(''));
    }

    /** One row of a CSV must not be able to put a hundred questions on a page. */
    public function testTheListIsCapped(): void
    {
        $many = [];
        for ($i = 0; $i < 60; $i++) $many[] = ['q' => "Q{$i}", 'a' => "A{$i}"];
        $this->assertCount(Faq::MAX_ITEMS, Faq::clean($many));
    }

    public function testOverlongTextIsTrimmedRatherThanRefused(): void
    {
        $items = Faq::clean([['q' => str_repeat('x', 500), 'a' => str_repeat('y', 5000)]]);
        $this->assertCount(1, $items);
        $this->assertSame(200, mb_strlen($items[0]['q']));
        $this->assertSame(2000, mb_strlen($items[0]['a']));
    }

    public function testTheAdminFormPairsUpItsTwoArrays(): void
    {
        $items = Faq::fromForm(
            ['First question', '', 'Third question'],
            ['First answer', 'orphan answer', 'Third answer']
        );
        $this->assertCount(2, $items, 'a question with no text takes its answer with it');
        $this->assertSame('First question', $items[0]['q']);
        $this->assertSame('Third question', $items[1]['q']);
    }

    /** Whatever a shopkeeper typed must survive being shown back to them. */
    public function testTextFormRoundTrips(): void
    {
        $items = Faq::parse('Q1 :: A1 || Q2 :: A2');
        $this->assertSame($items, Faq::parse(Faq::toText($items)));
    }

    public function testTheImporterAcceptsTheColumn(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/ImportController.php');
        $this->assertStringContainsString("ProductFaqService::parse(\$row['faq'] ?? null)", $src);
    }

    /**
     * A sample file whose rows are narrower than its header silently shifts
     * every value after the gap into the wrong column.
     */
    public function testEverySampleRowMatchesItsHeader(): void
    {
        $src = (string) file_get_contents(WK_ROOT . '/app/Controllers/Admin/ImportController.php');

        foreach (['categories', 'products', 'variants', 'all'] as $type) {
            $start = strpos($src, "case '{$type}':");
            $this->assertNotFalse($start, "no sample block for {$type}");
            $block = substr($src, $start, strpos($src, 'break;', $start) - $start);

            preg_match_all('/fputcsv\(\$out, \[(.*?)\]\);/s', $block, $m);
            $this->assertNotEmpty($m[1], "no rows in the {$type} sample");

            $widths = [];
            foreach ($m[1] as $row) {
                preg_match_all("/'(?:[^']|'')*'/", $row, $fields);
                $widths[] = count($fields[0]);
            }

            $header = array_shift($widths);
            foreach ($widths as $i => $w) {
                $this->assertSame(
                    $header,
                    $w,
                    "{$type} sample: data row " . ($i + 1) . " has {$w} fields but the header has {$header}"
                );
            }
        }
    }
}
