<?php
namespace App\Services;

/**
 * WHISKER — Product FAQ
 *
 * Questions the shopkeeper answers up front, stored as JSON on the product.
 *
 * Bulk upload has to carry these in a single CSV cell, so a plain text form is
 * accepted too: pairs separated by || and the question separated from its
 * answer by ::
 *
 *     Does it shrink? :: Not if you wash cold. || Is it true to size? :: Yes.
 *
 * Both shapes read back as the same list, so it makes no difference whether a
 * product was typed in by hand or imported.
 */
class ProductFaqService
{
    public const PAIR_SEPARATOR = '||';
    public const QA_SEPARATOR   = '::';

    /** The most a single product may carry, so one row cannot bloat a page. */
    public const MAX_ITEMS = 20;

    /**
     * @return array<int,array{q:string,a:string}>
     */
    public static function parse(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') return [];

        // Stored form: JSON.
        if (str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return self::clean($decoded);
        }

        // Imported form: Question :: Answer || Question :: Answer
        $items = [];
        foreach (explode(self::PAIR_SEPARATOR, $raw) as $pair) {
            $bits = explode(self::QA_SEPARATOR, $pair, 2);
            if (count($bits) !== 2) continue;
            $items[] = ['q' => $bits[0], 'a' => $bits[1]];
        }
        return self::clean($items);
    }

    /** Normalise, drop anything incomplete, and cap the length. */
    public static function clean(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $q = trim((string) ($item['q'] ?? ''));
            $a = trim((string) ($item['a'] ?? ''));
            if ($q === '' || $a === '') continue;

            $out[] = ['q' => mb_substr($q, 0, 200), 'a' => mb_substr($a, 0, 2000)];
            if (count($out) >= self::MAX_ITEMS) break;
        }
        return $out;
    }

    /** What goes in the database column. Empty list stores null, not "[]". */
    public static function encode(array $items): ?string
    {
        $clean = self::clean($items);
        return $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE) : null;
    }

    /**
     * Read the two parallel arrays the admin form posts.
     *
     * @return array<int,array{q:string,a:string}>
     */
    public static function fromForm(array $questions, array $answers): array
    {
        $items = [];
        foreach ($questions as $i => $q) {
            $items[] = ['q' => (string) $q, 'a' => (string) ($answers[$i] ?? '')];
        }
        return self::clean($items);
    }

    /** The text form, for showing a shopkeeper what a CSV cell should look like. */
    public static function toText(array $items): string
    {
        $parts = [];
        foreach (self::clean($items) as $item) {
            $parts[] = $item['q'] . ' ' . self::QA_SEPARATOR . ' ' . $item['a'];
        }
        return implode(' ' . self::PAIR_SEPARATOR . ' ', $parts);
    }
}
